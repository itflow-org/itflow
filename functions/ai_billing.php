<?php

/*
 * ITFlow - AI on quotes and invoices
 *
 * Two things, both for the Billing use case (functions/ai.php):
 *
 *  - the work done on a ticket, in words, for the invoice line that bills its
 *    time (aiTicketInvoiceDescription()) - from the replies and notes, the
 *    ones that logged time first, for the client who pays it
 *  - a quote or invoice written from a line on what it is for
 *    (aiBillingDraft()) - its scope, its lines and a note for the client.
 *    Lines come from the product catalog wherever one fits, labor in hours at
 *    the client's rate, anything else with no price. The model never prices
 *    anything: the catalog and the rate do, and what it can't price is left
 *    for the agent to set.
 *
 * Nothing is saved here. The ticket's Invoice window puts the description in
 * its field; Write it with AI (modals/billing/billing_ai_writer.php) shows the
 * lines to tick and change, and agent/post/billing.php adds them. The
 * endpoints are in agent/ajax.php.
 */

/* ------------------------------------------------------------- The catalog */

/*
 * The products and services a line can be - id => row (name, type, code,
 * description, price, frequency, seat source, tax id). Not archived, at most
 * $limit, by name.
 */
function aiBillingCatalog($limit = 200) {
    global $mysqli;
    $catalog = [];
    $sql = mysqli_query($mysqli, "SELECT product_id, product_name, product_type, product_code, product_description, product_price, product_frequency, product_seat_source, product_tax_id
        FROM products WHERE product_archived_at IS NULL ORDER BY product_name ASC LIMIT " . max(1, intval($limit)));
    while ($row = mysqli_fetch_assoc($sql)) {
        $catalog[intval($row['product_id'])] = $row;
    }
    return $catalog;
}

/* The catalog as a model reads it - one line each: id | name | service or product | how it is billed | price | what it is. */
function aiBillingCatalogText(array $catalog, $currency) {
    $billed = ['month' => 'monthly', 'quarter' => 'quarterly', 'year' => 'yearly'];
    $per = ['contacts' => ' per user', 'assets' => ' per device'];
    $lines = [];
    foreach ($catalog as $id => $row) {
        $description = aiHtmlToText((string) $row['product_description'], 120);
        $lines[] = $id . ' | ' . $row['product_name'] . ($row['product_code'] ? " ({$row['product_code']})" : '') . ' | ' . $row['product_type']
            . ' | ' . ($billed[(string) $row['product_frequency']] ?? 'once') . ($per[(string) $row['product_seat_source']] ?? '')
            . ' | ' . billingMoney($row['product_price'], $currency) . ($description !== '' ? ' | ' . str_replace("\n", ' ', $description) : '');
    }
    return implode("\n", $lines);
}

/* ------------------------------------------------------------- A ticket's time, described */

/*
 * A ticket's work as a model reads it, for its invoice line - what was asked,
 * then each reply and note in order, saying who it is from and the time it
 * logged, then the tasks. The latest of a long one, to about 30,000
 * characters. Null when the ticket is gone.
 */
function aiTicketWorkText($ticket_id) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_details, ticket_created_at, ticket_client_id, client_name
        FROM tickets LEFT JOIN clients ON client_id = ticket_client_id WHERE ticket_id = $ticket_id"));
    if (!$row) {
        return null;
    }
    $lines = ['Ticket: ' . ticketReference($row) . ' - ' . $row['ticket_subject'], 'Client: ' . ($row['client_name'] ?: 'none'), "\nWhat was asked:\n" . aiHtmlToText($row['ticket_details'], 6000)];

    $replies = [];
    $seconds = 0;
    $sql = mysqli_query($mysqli, "SELECT ticket_reply, ticket_reply_type, ticket_reply_created_at, TIME_TO_SEC(ticket_reply_time_worked) AS seconds
        FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL ORDER BY ticket_reply_id ASC");
    while ($reply = mysqli_fetch_assoc($sql)) {
        $worked = intval($reply['seconds']);
        $seconds += $worked;
        $who = ['Client' => 'The client', 'Internal' => 'Internal note'][$reply['ticket_reply_type']] ?? 'Reply to the client';
        $replies[] = '[' . date('M j, Y', strtotime($reply['ticket_reply_created_at'])) . "] $who" . ($worked ? ' - ' . billingDuration($worked) . ' logged' : '') . ":\n" . aiHtmlToText($reply['ticket_reply'], 3000);
    }
    $kept = [];
    $room = 30000;
    foreach (array_reverse($replies) as $reply) {
        if (mb_strlen($reply) > $room) {
            break;
        }
        $room -= mb_strlen($reply);
        array_unshift($kept, $reply);
    }
    $lines[] = $kept ? "\nThe work, in order:\n" . implode("\n\n", $kept) : "\nNo replies or notes on it.";
    if ($seconds) {
        $lines[] = "\nTime logged in all: " . billingDuration($seconds);
    }

    $tasks = [];
    $sql = mysqli_query($mysqli, "SELECT task_name FROM tasks WHERE task_ticket_id = $ticket_id AND task_completed_at IS NOT NULL ORDER BY task_order, task_id");
    while ($task = mysqli_fetch_assoc($sql)) {
        $tasks[] = '- ' . $task['task_name'];
    }
    if ($tasks) {
        $lines[] = "\nTasks done:\n" . implode("\n", $tasks);
    }
    return ['text' => implode("\n", $lines), 'row' => $row];
}

/*
 * What a model wrote as lines of plain text, tidied - no HTML, code fences or
 * bold, each line a "- " point, at most $max of them.
 */
function aiBillingPoints($content, $max = 6) {
    $text = aiHtmlToText(preg_replace('/^```[a-z]*\s*|\s*```$/im', '', (string) $content));
    $points = [];
    foreach (preg_split('/\n+/', $text) as $line) {
        $line = trim(str_replace(['**', '__'], '', $line));
        $line = trim(preg_replace('/^(?:[-*•–]|\d+[.)])\s*/u', '', $line));
        if ($line === '' || preg_match('/:$/', $line)) {
            continue;
        }
        $points[] = '- ' . mb_substr($line, 0, 200);
        if (count($points) >= $max) {
            break;
        }
    }
    return $points;
}

/*
 * The invoice line description for a ticket's time - ['ok', 'text'] or ['ok'
 * => false, 'error']. The usual first line (billingTimeDescription() - the
 * ticket, its subject and date), then the work done as short points, for the
 * client. Passwords and keys are never sent, and never come back.
 */
function aiTicketInvoiceDescription($ticket_id) {
    $model = getAiModel('Billing');
    if (!$model) {
        return ['ok' => false, 'error' => aiModelMissingError('Billing')];
    }
    $work = aiTicketWorkText($ticket_id);
    if (!$work) {
        return ['ok' => false, 'error' => 'That ticket no longer exists.'];
    }
    $model['ai_provider_redact'] = 1;
    $result = callAiApi($model, [
        ['role' => 'system', 'content' => 'You write the description on an invoice line that bills the time worked on an IT support ticket, for the client who pays it.'
            . ' From the ticket, say what was done: one to five short lines, each starting with "- ", in plain past tense (like "- Replaced the failed switch in the server room"), the work that took the time first.'
            . ' No times, dates, prices or the names of the people who did it. Nothing from internal notes the client shouldn\'t read - passwords, guesses, blame or remarks about the client - and never the placeholders like [secret-1].'
            . ' Plain text only: no introduction, no headings, nothing after the lines.'],
        ['role' => 'user', 'content' => $work['text']],
    ], ['feature' => 'invoice_description', 'client_id' => intval($work['row']['ticket_client_id']), 'secrets' => 'remove']);
    if (!$result['ok']) {
        return $result;
    }
    $points = aiBillingPoints($result['content'], 6);
    if (!$points) {
        return ['ok' => false, 'error' => 'The AI provider answered with nothing to use - try again.'];
    }
    return ['ok' => true, 'text' => billingTimeDescription($work['row']) . "\n" . implode("\n", $points)];
}

/* ------------------------------------------------------------- A quote or invoice, written */

/* The ticket a quote was raised for - the row, or null. Only quotes have one to draw on. */
function aiBillingTicket($type, $doc_id) {
    global $mysqli;
    if ($type !== 'quote') {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject FROM tickets WHERE ticket_quote_id = " . intval($doc_id) . " AND ticket_archived_at IS NULL LIMIT 1"));
    return $row ?: null;
}

/* How a line the model wrote is billed - '' (once), month, quarter or year. */
function aiBillingFrequency($value) {
    $value = strtolower(trim((string) $value));
    $value = ['monthly' => 'month', 'quarterly' => 'quarter', 'yearly' => 'year', 'annually' => 'year', 'annual' => 'year'][$value] ?? $value;
    return quoteFrequency($value);
}

/*
 * One line the model wrote, made safe to show - ['kind' => catalog, labor or
 * other, 'product_id', 'name', 'description', 'qty', 'frequency', 'price' (null
 * when there is none to give it)], or null when it has no name. A catalog line
 * takes the product's name and price, and how the product is billed when it
 * says; a line named exactly as a product is that product. Labor is hours at
 * the rate. Anything else has no price - the model's own is never taken.
 */
function aiBillingLine($type, $line, array $catalog, $rate) {
    if (!is_array($line)) {
        return null;
    }
    $text = function ($key) use ($line) {
        return is_scalar($line[$key] ?? null) ? trim(strip_tags((string) $line[$key])) : '';
    };
    $name = $text('name');
    $description = $text('description');
    $product_id = is_scalar($line['product_id'] ?? null) ? intval($line['product_id']) : 0;
    if (!isset($catalog[$product_id])) {
        $product_id = 0;
        foreach ($catalog as $id => $product) {
            if ($name !== '' && mb_strtolower(trim($product['product_name'])) === mb_strtolower($name)) {
                $product_id = $id;
                break;
            }
        }
    }
    $labor = !$product_id && filter_var($line['labor'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $qty = is_numeric($line['qty'] ?? null) ? round(floatval($line['qty']), 2) : 1.0;
    $qty = $qty > 0 ? min($qty, 99999) : 1.0;
    $frequency = $type === 'quote' ? aiBillingFrequency($text('billed')) : '';

    if ($product_id) {
        $product = $catalog[$product_id];
        $out = ['kind' => 'catalog', 'product_id' => $product_id, 'name' => (string) $product['product_name'],
            'description' => $description !== '' ? $description : aiHtmlToText((string) $product['product_description'], 1000),
            'price' => round(floatval($product['product_price']), 2)];
        if ($type === 'quote' && quoteFrequency($product['product_frequency']) !== '') {
            $frequency = quoteFrequency($product['product_frequency']);
        }
    } elseif ($labor) {
        $out = ['kind' => 'labor', 'product_id' => 0, 'name' => $name !== '' ? $name : billingTimeRules()['item_name'], 'description' => $description,
            'price' => $rate > 0 ? round($rate, 2) : null];
        $qty = round(ceil($qty * 4) / 4, 2);
    } else {
        if ($name === '') {
            return null;
        }
        $out = ['kind' => 'other', 'product_id' => 0, 'name' => $name, 'description' => $description, 'price' => null];
    }
    $out['name'] = mb_substr($out['name'], 0, 200);
    $out['description'] = mb_substr($out['description'], 0, 1000);
    $out['qty'] = $qty;
    $out['frequency'] = $frequency;
    return $out;
}

/*
 * A quote or invoice written from a line on what it is for - ['ok', 'scope',
 * 'lines' => aiBillingLine()s, 'note' => for the client, 'checks' => what the
 * agent should confirm, 'rate', 'ticket' => its reference when it was used] or
 * ['ok' => false, 'error']. $row is the document (billingRow()); with
 * $use_ticket, the ticket a quote was raised for goes with it, and with
 * $use_client, what ITFlow knows of the client (its people, sites and
 * assets - for sizing per-user and per-device lines). The ticket and the
 * client's documentation only for an agent with Support; without it, just
 * how many people the client has.
 */
function aiBillingDraft($type, array $row, $prompt, $use_ticket = true, $use_client = true) {
    global $mysqli;
    $model = getAiModel('Billing');
    if (!$model) {
        return ['ok' => false, 'error' => aiModelMissingError('Billing')];
    }
    $prompt = trim((string) $prompt);
    if ($prompt === '') {
        return ['ok' => false, 'error' => 'Say what it is for first.'];
    }
    $t = billingType($type);
    $c = $t['col'];
    $doc_id = intval($row["{$c}_id"]);
    $client_id = intval($row['client_id']);
    $currency = (string) $row["{$c}_currency_code"];
    $rate = contractClientRate($client_id)['rate'];
    $catalog = aiBillingCatalog();

    // The client's documentation and its tickets go only for an agent who may read them
    $support = lookupUserPermission('module_support') >= 1;
    $profile = $use_client && $support ? aiClientProfile($client_id) : '';
    $facts = [$profile !== '' ? $profile : 'Client: ' . $row['client_name']];
    if ($use_client) {
        $facts[] = 'People (contacts): ' . intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL"))[0]);
    }
    $facts[] = 'Labor is billed by the hour' . ($rate > 0 ? ', at ' . billingMoney($rate, $currency) : '') . '.';
    $ticket = $use_ticket && $support ? aiBillingTicket($type, $doc_id) : null;
    $ticket_text = '';
    if ($ticket) {
        $context = aiTicketContext(intval($ticket['ticket_id']));
        $ticket_text = $context ? "\n\nThe ticket it is for (internal notes are for you only - never quote them):\n" . $context['text'] : '';
    }
    $existing = [];
    foreach (billingItems($type, $doc_id) as $item) {
        $existing[] = '- ' . $item['item_name'] . ' x ' . billingQuantity($item['item_quantity']);
    }

    $quote = $type === 'quote';
    $result = callAiApi($model, [
        ['role' => 'system', 'content' => 'You write ' . ($quote ? 'quotes' : 'invoices') . ' for a managed service provider - the lines a client reads and pays for.'
            . ' From what the agent says it is for, choose the lines: products and services from the catalog given, by their id, wherever one fits; labor in hours for the work to do it; and anything else needed that isn\'t in the catalog.'
            . ' Never write a price - prices come from the catalog and the hourly rate. Be realistic about quantities and hours, and size per-user and per-device lines from the client\'s numbers when they are given.'
            . ' Line descriptions are for the client: a plain sentence or two on what it is, or what the work covers.'
            . ($quote ? ' Say how each line is billed: once, or each month, quarter or year - licenses and managed services are usually monthly, hardware and project labor once.' : ' Everything on an invoice is billed once.')
            . ' Answer with JSON only, in this shape: {"scope": "a short line on what it is for, under 80 characters", "lines": [{"product_id": a catalog id or null, "labor": true for hours of work, "name": "the line\'s name", "description": "for the client", "qty": a number - hours for labor'
            . ($quote ? ', "billed": "once" or "month" or "quarter" or "year"' : '') . '}], "note": "for the client: what is assumed and what is not included, as short lines starting with - ", "checks": ["for the agent: what to confirm before sending - quantities you guessed, what the catalog didn\'t have"]}. At most 20 lines.'],
        ['role' => 'user', 'content' => "What it is for: $prompt\n\n" . implode("\n", $facts)
            . ($existing ? "\n\nOn it already (don't repeat these):\n" . implode("\n", $existing) : '')
            . $ticket_text
            . "\n\nThe catalog (id | name | type | billed | price | what it is):\n" . ($catalog ? aiBillingCatalogText($catalog, $currency) : 'Empty - nothing is in the catalog.')],
    ], ['feature' => 'billing_draft', 'client_id' => $client_id, 'secrets' => 'remove']);
    if (!$result['ok']) {
        return $result;
    }
    $answer = aiJsonFromText($result['content']);
    if (!$answer || !is_array($answer['lines'] ?? null)) {
        return ['ok' => false, 'error' => "The AI provider's answer couldn't be read - try again."];
    }
    $lines = [];
    foreach (array_slice($answer['lines'], 0, 20) as $line) {
        $line = aiBillingLine($type, $line, $catalog, $rate);
        if ($line) {
            $lines[] = $line;
        }
    }
    if (!$lines) {
        return ['ok' => false, 'error' => 'The AI provider answered with no lines to use - say a little more about what it is for.'];
    }
    $checks = [];
    foreach (array_slice((array) ($answer['checks'] ?? []), 0, 6) as $check) {
        $check = trim(strip_tags(is_scalar($check) ? (string) $check : ''));
        if ($check !== '') {
            $checks[] = mb_substr($check, 0, 300);
        }
    }
    $note = is_scalar($answer['note'] ?? null) ? implode("\n", aiBillingPoints((string) $answer['note'], 10)) : '';
    return [
        'ok' => true,
        'scope' => mb_substr(trim(strip_tags(is_scalar($answer['scope'] ?? null) ? (string) $answer['scope'] : '')), 0, 255),
        'lines' => $lines,
        'note' => mb_substr($note, 0, 2000),
        'checks' => $checks,
        'rate' => $rate,
        'ticket' => $ticket ? ticketReference($ticket) : '',
    ];
}
