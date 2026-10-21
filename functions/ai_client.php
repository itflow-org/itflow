<?php

/*
 * ITFlow - AI client briefing
 *
 * A client summed up for whoever is about to talk to them - before a call, a
 * visit or a quarterly review (aiClientBriefing()): what is open, what keeps
 * coming up, what is coming up, what they owe, and what to bring up. Built
 * from what the client overview shows (functions/client_overview.php), and
 * only the parts the agent's role can see - no money without Sales, no
 * tickets without Support. Never a credential. The window is
 * agent/modals/client/client_briefing.php, the endpoint ai_client_briefing
 * in agent/ajax.php.
 */

/* The periods a briefing can look back over - days => what the window calls it. */
function aiBriefingPeriods() {
    return [30 => 'the last 30 days', 90 => 'the last 90 days - a quarterly review', 365 => 'the last year'];
}

/*
 * A client as a briefing reads it, for the last $days - in words, only what
 * the signed-in agent's role may see (clientOverviewAccess()).
 */
function aiClientBriefingContext($client_id, $days = 90) {
    global $mysqli;
    $client_id = intval($client_id);
    $days = isset(aiBriefingPeriods()[intval($days)]) ? intval($days) : 90;
    $access = clientOverviewAccess();
    $client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name, client_type, client_lead, client_notes, client_currency_code, client_created_at, client_archived_at FROM clients WHERE client_id = $client_id"));
    if (!$client) {
        return null;
    }
    $currency = (string) $client['client_currency_code'];
    $since = date('Y-m-d', strtotime("-$days days"));
    $lines = [
        'Today: ' . date('F j, Y') . " - looking back over the last $days days, since " . date('F j, Y', strtotime($since)),
        'Client: ' . $client['client_name'] . ($client['client_type'] ? " ({$client['client_type']})" : '') . ($client['client_lead'] ? ' - a lead, not yet a client' : '')
            . ($client['client_archived_at'] ? ' - archived' : '') . ' - with us since ' . date('F Y', strtotime($client['client_created_at'])),
    ];
    if (trim(strip_tags((string) $client['client_notes'])) !== '') {
        $lines[] = "Notes on them:\n" . aiHtmlToText($client['client_notes'], 1500);
    }

    $people = [];
    foreach (clientOverviewContacts($client_id, 8) as $contact) {
        $roles = array_keys(array_filter(['primary' => $contact['contact_primary'], 'important' => $contact['contact_important'], 'technical' => $contact['contact_technical'], 'billing' => $contact['contact_billing']]));
        $people[] = '- ' . $contact['contact_name'] . ($contact['contact_title'] ? ", {$contact['contact_title']}" : '') . ($roles ? ' (' . implode(', ', $roles) . ')' : '');
    }
    if ($people) {
        $lines[] = "Key people:\n" . implode("\n", $people);
    }

    if ($access['tickets']) {
        $open = clientOverviewOpenTickets($client_id, 15);
        $items = [];
        foreach ($open['tickets'] as $ticket) {
            $items[] = '- ' . $ticket['ticket_prefix'] . $ticket['ticket_number'] . ' ' . $ticket['ticket_subject'] . ' - ' . $ticket['ticket_status_name'] . ', ' . $ticket['ticket_priority']
                . ', opened ' . floor((time() - strtotime($ticket['ticket_created_at'])) / 86400) . ' days ago'
                . ($ticket['ticket_updated_at'] ? ', last updated ' . floor((time() - strtotime($ticket['ticket_updated_at'])) / 86400) . ' days ago' : '')
                . ($ticket['user_name'] ? ", with {$ticket['user_name']}" : ', unassigned') . (!empty($ticket['sla_breached']) ? ', PAST SLA' : '');
        }
        $lines[] = 'Open tickets: ' . intval($open['total']) . ($items ? "\n" . implode("\n", $items) . (intval($open['total']) > count($items) ? "\n- and " . (intval($open['total']) - count($items)) . ' more' : '') : '');

        $period = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS opened, COALESCE(SUM(ticket_resolved_at IS NOT NULL), 0) AS resolved,
                AVG(IF(ticket_resolved_at IS NOT NULL, TIMESTAMPDIFF(HOUR, ticket_created_at, ticket_resolved_at), NULL)) AS hours,
                COALESCE(SUM(ticket_sla_id > 0 AND (ticket_response_sla_met = 0 OR ticket_resolution_sla_met = 0)), 0) AS breached
            FROM tickets WHERE ticket_client_id = $client_id AND ticket_created_at >= '$since'"));
        $lines[] = "Tickets in the period: {$period['opened']} opened, {$period['resolved']} of them resolved" . ($period['hours'] !== null ? ', in ' . round(floatval($period['hours'])) . ' hours on average' : '')
            . ($period['breached'] ? ", {$period['breached']} missed their SLA" : '');
        $group = function ($label, $sql) use ($mysqli, &$lines) {
            $items = [];
            $result = mysqli_query($mysqli, $sql);
            while ($row = mysqli_fetch_row($result)) {
                $items[] = "$row[0] ($row[1])";
            }
            if ($items) {
                $lines[] = "$label: " . implode(', ', $items);
            }
        };
        $group('By category', "SELECT COALESCE(category_name, 'No category'), COUNT(*) FROM tickets LEFT JOIN categories ON category_id = ticket_category
            WHERE ticket_client_id = $client_id AND ticket_created_at >= '$since' GROUP BY 1 ORDER BY 2 DESC LIMIT 8");
        $group('Assets with more than one ticket', "SELECT asset_name, COUNT(DISTINCT t.ticket_id) FROM tickets t
            JOIN assets ON asset_id = t.ticket_asset_id OR asset_id IN (SELECT asset_id FROM ticket_assets WHERE ticket_assets.ticket_id = t.ticket_id)
            WHERE t.ticket_client_id = $client_id AND t.ticket_created_at >= '$since' GROUP BY asset_id HAVING COUNT(DISTINCT t.ticket_id) > 1 ORDER BY 2 DESC LIMIT 6");
        $group('Who raises them', "SELECT contact_name, COUNT(*) FROM tickets JOIN contacts ON contact_id = ticket_contact_id
            WHERE ticket_client_id = $client_id AND ticket_created_at >= '$since' GROUP BY contact_id ORDER BY 2 DESC LIMIT 4");
        $subjects = [];
        $result = mysqli_query($mysqli, "SELECT ticket_prefix, ticket_number, ticket_subject, category_name FROM tickets LEFT JOIN categories ON category_id = ticket_category
            WHERE ticket_client_id = $client_id AND ticket_created_at >= '$since' ORDER BY ticket_id DESC LIMIT 60");
        while ($row = mysqli_fetch_assoc($result)) {
            $subjects[] = '- ' . $row['ticket_prefix'] . $row['ticket_number'] . ' ' . $row['ticket_subject'] . ($row['category_name'] ? " [{$row['category_name']}]" : '');
        }
        if ($subjects) {
            $lines[] = "Their tickets in the period, newest first:\n" . implode("\n", $subjects);
        }

        $projects = clientOverviewProjects($client_id, 6);
        $items = [];
        foreach ($projects['projects'] as $project) {
            $items[] = '- ' . $project['project_name'] . (!empty($project['project_due']) ? ', due ' . date('M j, Y', strtotime($project['project_due'])) : '');
        }
        if ($items) {
            $lines[] = "Open projects:\n" . implode("\n", $items);
        }
    }

    $upcoming = [];
    foreach (clientOverviewUpcoming($client_id, $access, 30) as $item) {
        $upcoming[] = '- ' . date('M j', strtotime($item['when'])) . ': ' . ($item['ref'] ?? '') . ' ' . $item['title'] . (!empty($item['detail']) ? " ({$item['detail']})" : '');
    }
    if ($upcoming) {
        $lines[] = "In the next 30 days:\n" . implode("\n", array_slice($upcoming, 0, 15));
    }

    if ($access['docs']) {
        $renewals = [];
        foreach (clientOverviewRenewals($client_id, 90)['items'] as $item) {
            $renewals[] = '- ' . $item['label'] . ' ' . $item['name'] . ($item['days'] < 0 ? ' - expired ' . date('M j, Y', strtotime($item['date'])) : ' - ' . date('M j, Y', strtotime($item['date'])) . " (in {$item['days']} days)");
        }
        if ($renewals) {
            $lines[] = "Renewals and expiries, the next 90 days and those already past:\n" . implode("\n", array_slice($renewals, 0, 20));
        }
        $assets = clientOverviewAssets($client_id);
        if ($assets['total']) {
            $groups = [];
            foreach ($assets['groups'] as $group) {
                if ($group['count']) {
                    $groups[] = $group['count'] . ' ' . strtolower($group['label']);
                }
            }
            $lines[] = 'Assets: ' . $assets['total'] . ' (' . implode(', ', $groups) . ') - ' . $assets['warranty_expired'] . ' out of warranty, ' . $assets['warranty_unknown'] . ' with no warranty date';
        }
    }

    if ($access['sales']) {
        $money = function ($amount) use ($currency) {
            return billingMoney($amount, $currency);
        };
        $invoices = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS open_count,
                COALESCE(SUM(invoice_amount - COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0)), 0) AS owed,
                COALESCE(SUM(IF(invoice_due < CURDATE(), invoice_amount - COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0), 0)), 0) AS overdue,
                MIN(IF(invoice_due < CURDATE(), invoice_due, NULL)) AS oldest_due
            FROM invoices WHERE invoice_client_id = $client_id AND invoice_status IN ('Sent', 'Viewed', 'Partial')"));
        $lines[] = 'Unpaid invoices: ' . $invoices['open_count'] . ', ' . $money($invoices['owed']) . ' owed'
            . (floatval($invoices['overdue']) > 0 ? ', ' . $money($invoices['overdue']) . ' of it overdue, the oldest due ' . date('M j, Y', strtotime($invoices['oldest_due'])) : ', none overdue');
        $monthly = 0.0;
        foreach (clientOverviewRecurringInvoices($client_id) as $recurring) {
            $months = ['month' => 1, 'quarter' => 3, 'year' => 12][strtolower((string) $recurring['recurring_invoice_frequency'])] ?? 1;
            $monthly += floatval($recurring['recurring_invoice_amount']) / $months;
        }
        if ($monthly > 0) {
            $lines[] = 'Recurring billing: about ' . $money($monthly) . ' a month';
        }
        $quotes = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS waiting, COALESCE(SUM(quote_amount), 0) AS amount FROM quotes
            WHERE quote_client_id = $client_id AND quote_status IN ('Sent', 'Viewed') AND quote_archived_at IS NULL"));
        if (intval($quotes['waiting'])) {
            $lines[] = "Quotes waiting on them: {$quotes['waiting']}, " . $money($quotes['amount']);
        }
        $billed = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(invoice_amount), 0) FROM invoices WHERE invoice_client_id = $client_id AND invoice_date >= '$since' AND invoice_status NOT IN ('Draft', 'Cancelled')"));
        $lines[] = 'Invoiced in the period: ' . $money($billed[0]);
    }

    return ['text' => implode("\n", $lines), 'name' => $client['client_name'], 'days' => $days, 'money' => $access['sales'], 'tickets' => $access['tickets']];
}

/*
 * A client briefing - ['ok', 'html'] or ['ok' => false, 'error']. In short,
 * open work, what keeps coming up, what is coming up, money (only when the
 * agent may see it) and what to bring up with them.
 */
function aiClientBriefing($client_id, $days = 90) {
    $model = getAiModel('General');
    if (!$model) {
        return ['ok' => false, 'error' => aiModelMissingError('General')];
    }
    $client = aiClientBriefingContext($client_id, $days);
    if (!$client) {
        return ['ok' => false, 'error' => 'That client no longer exists.'];
    }
    $result = callAiApi($model, [
        ['role' => 'system', 'content' => 'You brief a managed service provider\'s account manager or engineer on one of their clients, before a call, a visit or a quarterly review. Use only the facts given, and say plainly when there is little to go on.'
            . ' Answer in HTML only, in this shape: <p><strong>In short:</strong> two sentences on how things stand with them.</p>'
            . ($client['tickets'] ? ' <h6>Open work</h6><ul>...</ul> (what is open and what needs pushing - past SLA, stuck, unassigned - with ticket numbers) <h6>What keeps coming up</h6><ul>...</ul> (patterns across their tickets - the same device, the same kind of problem, the same person - with counts, and what would stop it)' : '')
            . ' <h6>Coming up</h6><ul>...</ul> (renewals, expiries, scheduled work, projects)'
            . ($client['money'] ? ' <h6>Money</h6><ul>...</ul> (what they owe, anything overdue, waiting quotes)' : '')
            . ' <h6>Bring up with them</h6><ul>...</ul> (three to five things worth raising - fixes for what keeps happening, hardware out of warranty, renewals to decide, anything they should know).'
            . ' Short bullets, plain words. Leave out a section with nothing in it. No introduction, no code fences, no <html> or <body>.'],
        ['role' => 'user', 'content' => $client['text']],
    ], ['feature' => 'client_briefing', 'client_id' => intval($client_id)]);
    if (!$result['ok']) {
        return $result;
    }
    $html = aiCleanHtml($result['content']);
    return $html === '' ? ['ok' => false, 'error' => 'The AI provider answered with nothing - try again.'] : ['ok' => true, 'html' => $html];
}
