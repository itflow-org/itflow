<?php

/*
 * Taxes - the rates lines are charged at (Admin > Taxes).
 *
 * A rate is a name and a percent, or a rate made of parts - a state's and a
 * county's, GST and PST - each charged on the line, or on the line and the
 * parts before it (compound). A line's tax is its parts, each to the cent;
 * taxes.tax_percent is the whole rate they come to, for the windows that show
 * a line's total as it is typed.
 *
 * A rate on billed lines is never changed in place: changing its percent or
 * parts makes a new rate (taxChangeRate()) and archives the old one, so every
 * invoice keeps the rate it was billed at - in its totals, on its PDF and in
 * the tax report. Products, clients, the default, recurring invoices and drafts
 * move on to the new one.
 *
 * Which rate a new line gets (taxDefaultFor()): none for a tax exempt client;
 * none for billed time, trips or expenses unless they are taxed (Admin >
 * Taxes); none for a product that isn't taxed; and otherwise the client's
 * rate, a product's own rate, or the default - the first of them still in use.
 */

/** The tax settings - the default rate, what is taxed, discounts before tax. Read once a request. */
function taxSettings($forget = false) {
    global $mysqli;
    static $settings = null;
    if ($forget) {
        $settings = null;
        return [];
    }
    if ($settings === null) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_tax_default_id, config_tax_time, config_tax_trips, config_tax_expenses, config_tax_discount_before
            FROM settings WHERE company_id = 1")) ?: [];
        $settings = [
            'default_id' => intval($row['config_tax_default_id'] ?? 0),
            'time' => intval($row['config_tax_time'] ?? 0) === 1,
            'trip' => intval($row['config_tax_trips'] ?? 0) === 1,
            'expense' => intval($row['config_tax_expenses'] ?? 0) === 1,
            'discount_before' => intval($row['config_tax_discount_before'] ?? 1) === 1,
        ];
    }
    return $settings;
}

/** A percent as people write it - 6, 7.25, 9.975. */
function taxPercentText($percent) {
    $text = rtrim(rtrim(number_format(floatval($percent), 4, '.', ''), '0'), '.');
    return $text === '' || $text === '-0' ? '0' : $text;
}

/**
 * One rate with its parts, or null - cached for the request (taxForget() after
 * a change). A rate without parts of its own has one: itself.
 */
function taxRow($tax_id, $forget = false) {
    global $mysqli;
    static $cache = [];
    if ($forget) {
        $cache = [];
        return null;
    }
    $tax_id = intval($tax_id);
    if ($tax_id <= 0) {
        return null;
    }
    if (!array_key_exists($tax_id, $cache)) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tax_id, tax_name, tax_percent, tax_description, tax_registration_number, tax_replaced_by,
            tax_created_at, tax_archived_at FROM taxes WHERE tax_id = $tax_id LIMIT 1"));
        if ($row) {
            $row['components'] = [];
            $sql = mysqli_query($mysqli, "SELECT component_name, component_percent, component_compound, component_registration_number FROM tax_components
                WHERE component_tax_id = $tax_id ORDER BY component_order ASC, component_id ASC");
            while ($part = mysqli_fetch_assoc($sql)) {
                $row['components'][] = ['name' => (string) $part['component_name'], 'percent' => floatval($part['component_percent']), 'compound' => intval($part['component_compound']) === 1,
                    'registration' => trim((string) $part['component_registration_number'])];
            }
            $row['grouped'] = count($row['components']) > 0;
            if (!$row['components']) {
                $row['components'][] = ['name' => (string) $row['tax_name'], 'percent' => floatval($row['tax_percent']), 'compound' => false, 'registration' => ''];
            }
        }
        $cache[$tax_id] = $row ?: null;
    }
    return $cache[$tax_id];
}

/** Forget the cached rates - after one is saved. */
function taxForget() {
    taxRow(0, true);
}

/** The whole rate parts come to - compound parts are charged on the ones before them too. */
function taxEffectivePercent(array $components) {
    $total = 0.0;
    foreach ($components as $part) {
        $percent = floatval($part['percent']);
        $total += !empty($part['compound']) ? $percent * (1 + $total / 100) : $percent;
    }
    return round($total, 4);
}

/** A rate as it is offered and printed - "PA Sales Tax 6%". */
function taxLabel($row) {
    if (!$row) {
        return '';
    }
    return trim($row['tax_name'] . ' ' . taxPercentText($row['tax_percent']) . '%');
}

/**
 * The tax on an amount at a rate - ['tax' => the whole, 'parts' => [[label,
 * name, percent, amount, registration, registration_for], ...]], each part
 * to the cent.
 * $tax: a rate's id, or its taxRow().
 */
function taxAmounts($base, $tax) {
    $row = is_array($tax) ? $tax : taxRow($tax);
    $base = round(floatval($base), 2);
    if (!$row) {
        return ['tax' => 0.0, 'parts' => []];
    }
    $total = 0.0;
    $parts = [];
    foreach ($row['components'] as $part) {
        $on = !empty($part['compound']) ? $base + $total : $base;
        $amount = round($on * floatval($part['percent']) / 100, 2);
        $total = round($total + $amount, 2);
        $parts[] = [
            'label' => trim($part['name'] . ' ' . taxPercentText($part['percent']) . '%'),
            'name' => $part['name'],
            'percent' => floatval($part['percent']),
            'amount' => $amount,
            // A part's own registration number, or the rate's - and what it is for
            'registration' => ($part['registration'] ?? '') !== '' ? $part['registration'] : trim((string) ($row['tax_registration_number'] ?? '')),
            'registration_for' => ($part['registration'] ?? '') !== '' ? $part['name'] : (string) $row['tax_name'],
        ];
    }
    return ['tax' => $total, 'parts' => $parts];
}

/** The rate a rate was changed to, following each change to the one in use now - or 0 when it was archived without one. */
function taxCurrent($tax_id) {
    $seen = [];
    $row = taxRow($tax_id);
    while ($row && $row['tax_archived_at'] && intval($row['tax_replaced_by']) && !isset($seen[intval($row['tax_id'])])) {
        $seen[intval($row['tax_id'])] = true;
        $row = taxRow($row['tax_replaced_by']);
    }
    return $row && !$row['tax_archived_at'] ? intval($row['tax_id']) : 0;
}

/** A client's tax standing - its own rate, and whether it is exempt (and on what certificate, until when). */
function taxClient($client_id) {
    global $mysqli;
    static $cache = [];
    $client_id = intval($client_id);
    if (!array_key_exists($client_id, $cache)) {
        $row = $client_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_default_tax_id, client_tax_exempt, client_tax_exempt_number, client_tax_exempt_expires, client_tax_id_number
            FROM clients WHERE client_id = $client_id LIMIT 1")) : null;
        $expires = (string) ($row['client_tax_exempt_expires'] ?? '');
        $cache[$client_id] = [
            'default_id' => intval($row['client_default_tax_id'] ?? 0),
            'exempt' => intval($row['client_tax_exempt'] ?? 0) === 1,
            'number' => trim((string) ($row['client_tax_exempt_number'] ?? '')),
            'expires' => $expires,
            'expired' => $expires !== '' && $expires < date('Y-m-d'),
            'tax_id_number' => trim((string) ($row['client_tax_id_number'] ?? '')),
        ];
    }
    return $cache[$client_id];
}

/**
 * A client's tax fields, for its SET list - its own rate, and whether it is
 * exempt, on what certificate and until when. $in: default_tax_id,
 * tax_exempt, tax_exempt_number, tax_exempt_expires (raw); any left out keep
 * $current, the client's row ([] for a new client). A rate has to be one
 * still offered, or the one the client already has.
 */
function taxClientSetSql(array $in, array $current = []) {
    $current_default = intval($current['client_default_tax_id'] ?? 0);
    $default_id = array_key_exists('default_tax_id', $in) ? intval($in['default_tax_id']) : $current_default;
    if ($default_id && $default_id !== $current_default && (!($row = taxRow($default_id)) || $row['tax_archived_at'])) {
        $default_id = $current_default;
    }
    if ($default_id && !taxRow($default_id)) {
        $default_id = 0;
    }
    $exempt = array_key_exists('tax_exempt', $in) ? (!empty($in['tax_exempt']) ? 1 : 0) : intval($current['client_tax_exempt'] ?? 0);
    $number = trim(mb_substr((string) (array_key_exists('tax_exempt_number', $in) ? $in['tax_exempt_number'] : ($current['client_tax_exempt_number'] ?? '')), 0, 200));
    $expires = trim((string) (array_key_exists('tax_exempt_expires', $in) ? $in['tax_exempt_expires'] : ($current['client_tax_exempt_expires'] ?? '')));
    $expires = $expires !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires) && strtotime($expires) ? $expires : '';
    return "client_default_tax_id = $default_id, client_tax_exempt = $exempt,
        client_tax_exempt_number = " . ($number !== '' ? "'" . escapeSql($number) . "'" : 'NULL') . ",
        client_tax_exempt_expires = " . ($expires !== '' ? "'$expires'" : 'NULL');
}

/** "Tax exempt - certificate 12-345, expires Mar 1, 2027" for an exempt client - '' otherwise. Without $with_expiry, for what the client sees. */
function taxExemptText($client, $with_expiry = true) {
    $client = is_array($client) ? $client : taxClient($client);
    if (empty($client['exempt'])) {
        return '';
    }
    $text = 'Tax exempt';
    if ($client['number'] !== '') {
        $text .= ' - certificate ' . $client['number'];
    }
    if ($with_expiry && $client['expires'] !== '') {
        $text .= ($client['number'] !== '' ? ', ' : ' - ') . ($client['expired'] ? 'expired ' : 'expires ') . date('M j, Y', strtotime($client['expires']));
    }
    return $text;
}

/**
 * The warning over an invoice, quote or recurring invoice in the agent's view
 * when its client is tax exempt and its lines can still change ($open) -
 * lines still taxed (with a link to take the tax off when $can_edit), or an
 * exemption certificate that has run out. '' otherwise.
 */
function taxDocWarning($type, $doc_id, $client_id, array $items, $open, $can_edit = false) {
    $client = taxClient($client_id);
    if (!$client['exempt']) {
        return '';
    }
    $notes = [];
    $action = '';
    $taxed = 0;
    foreach ($items as $item) {
        $taxed += floatval($item['item_tax']) != 0 ? 1 : 0;
    }
    if ($open && $taxed) {
        $notes[] = 'The client is tax exempt, but ' . ($taxed === 1 ? '1 line here is' : "$taxed lines here are") . ' taxed.';
        if ($can_edit) {
            $action = '<a class="btn btn-sm btn-warning ms-auto text-nowrap confirm-link" href="post.php?billing_remove_tax=' . intval($doc_id) . '&type=' . urlencode($type) . '&csrf_token=' . urlencode($_SESSION['csrf_token'] ?? '') . '"'
                . ' data-confirm-text="Every line here is set to no tax, and the totals worked out again.">Take the tax off</a>';
        }
    }
    if ($open && $client['expired']) {
        $notes[] = 'Its exemption certificate' . ($client['number'] !== '' ? ' (' . escapeHtml($client['number']) . ')' : '') . ' expired on ' . date('M j, Y', strtotime($client['expires']))
            . ' - get a new one, or turn exemption off on the client\'s Billing tab.';
    }
    if (!$notes) {
        return '';
    }
    return '<div class="alert alert-warning d-print-none d-flex flex-wrap gap-2 align-items-center mb-3"><i class="fas fa-fw fa-exclamation-triangle"></i><div>' . implode(' ', $notes) . '</div>' . $action . '</div>';
}

/**
 * The rate a new line for a client gets. $kind: 'goods' (typed in), 'time'
 * (billed hours), 'trip', 'expense', or 'product' with the product's own rate
 * ($product_tax_id, 0 when the product isn't taxed). The rate in use now - a
 * changed rate's replacement - or 0 for no tax.
 */
function taxDefaultFor($client_id, $kind = 'goods', $product_tax_id = 0) {
    $settings = taxSettings();
    $client = taxClient($client_id);
    if ($client['exempt']) {
        return 0;
    }
    if (in_array($kind, ['time', 'trip', 'expense'], true) && !$settings[$kind]) {
        return 0;
    }
    if ($kind === 'product' && !intval($product_tax_id)) {
        return 0;
    }
    // A rate archived without a replacement no longer counts - the next one along does
    $tax_id = taxCurrent($client['default_id']);
    if (!$tax_id && $kind === 'product') {
        $tax_id = taxCurrent($product_tax_id);
    }
    return $tax_id ?: taxCurrent($settings['default_id']);
}

/** TAX_USUAL in a tax select: each line's usual rate for its client and kind (taxDefaultFor()) - where one choice covers time, expenses and trips, or many clients. */
const TAX_USUAL = -1;

/** A line's rate from a form's choice - TAX_USUAL is the usual rate for this client and kind. */
function taxChoice($input, $client_id, $kind) {
    $tax_id = intval($input);
    return $tax_id === TAX_USUAL ? taxDefaultFor($client_id, $kind) : max(0, $tax_id);
}

/** Where a rate is used - how many invoices, quotes and recurring invoices have a line at it, its products and clients, and whether it is the default. */
function taxUsage($tax_id) {
    global $mysqli;
    $tax_id = intval($tax_id);
    $count = function ($sql) use ($mysqli) {
        return intval(mysqli_fetch_row(mysqli_query($mysqli, $sql))[0] ?? 0);
    };
    return [
        'invoices' => $count("SELECT COUNT(DISTINCT item_invoice_id) FROM invoice_items WHERE item_tax_id = $tax_id"),
        'quotes' => $count("SELECT COUNT(DISTINCT item_quote_id) FROM quote_items WHERE item_tax_id = $tax_id"),
        'recurring' => $count("SELECT COUNT(DISTINCT item_recurring_invoice_id) FROM recurring_invoice_items WHERE item_tax_id = $tax_id"),
        'products' => $count("SELECT COUNT(*) FROM products WHERE product_tax_id = $tax_id AND product_archived_at IS NULL"),
        'clients' => $count("SELECT COUNT(*) FROM clients WHERE client_default_tax_id = $tax_id AND client_archived_at IS NULL"),
        'default' => taxSettings()['default_id'] === $tax_id,
    ];
}

/** Whether a rate has been billed - on an invoice or quote past draft, so its rate is part of what the client was sent. */
function taxBilled($tax_id) {
    global $mysqli;
    $tax_id = intval($tax_id);
    return mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM invoice_items JOIN invoices ON invoice_id = item_invoice_id
            WHERE item_tax_id = $tax_id AND invoice_status != 'Draft' LIMIT 1")) > 0
        || mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM quote_items JOIN quotes ON quote_id = item_quote_id
            WHERE item_tax_id = $tax_id AND quote_status != 'Draft' LIMIT 1")) > 0;
}

/**
 * A rate from the form - [$fields, $error]. $in: name, description,
 * registration, and parts (part_name[key], part_percent[key],
 * part_compound[key], part_registration[key], in order) or percent for a
 * single rate. Raw values;
 * taxCreate() and taxUpdate() escape them.
 */
function taxFromInput(array $in) {
    $name = trim(mb_substr((string) ($in['name'] ?? ''), 0, 200));
    $description = trim(mb_substr((string) ($in['description'] ?? ''), 0, 255));
    $registration = trim(mb_substr((string) ($in['registration'] ?? ''), 0, 100));
    $components = [];
    $names = (array) ($in['part_name'] ?? []);
    $percents = (array) ($in['part_percent'] ?? []);
    $compounds = (array) ($in['part_compound'] ?? []);
    $registrations = (array) ($in['part_registration'] ?? []);
    foreach ($names as $i => $part_name) {
        $part_name = trim(mb_substr((string) $part_name, 0, 200));
        $percent = trim(str_replace(['%', ' '], '', (string) ($percents[$i] ?? '')));
        if ($part_name === '' && $percent === '') {
            continue;
        }
        if ($part_name === '' || !is_numeric($percent)) {
            return [null, 'Each part needs a name and a percent.'];
        }
        $components[] = ['name' => $part_name, 'percent' => round(floatval($percent), 4), 'compound' => !empty($compounds[$i]) && count($components) > 0,
            'registration' => trim(mb_substr((string) ($registrations[$i] ?? ''), 0, 100))];
    }
    if ($name === '') {
        return [null, 'A tax needs a name.'];
    }
    if (count($components) === 1) {
        // One part is just the rate - with its registration number, when the rate has none
        $percent = $components[0]['percent'];
        $registration = $registration !== '' ? $registration : $components[0]['registration'];
        $components = [];
    } elseif (!$components) {
        $percent = trim(str_replace(['%', ' '], '', (string) ($in['percent'] ?? '')));
        if (!is_numeric($percent)) {
            return [null, 'A tax needs a percent.'];
        }
        $percent = round(floatval($percent), 4);
    } else {
        $percent = taxEffectivePercent($components);
    }
    foreach (array_merge([['percent' => $percent]], $components) as $part) {
        if ($part['percent'] < 0 || $part['percent'] > 100) {
            return [null, 'A percent is between 0 and 100.'];
        }
    }
    return [['name' => $name, 'description' => $description, 'registration' => $registration, 'percent' => $percent, 'components' => $components], ''];
}

/** Whether two rates charge the same - the same percent, and the same parts. */
function taxSameRate(array $row, array $fields) {
    $now = $row['grouped'] ? $row['components'] : [];
    if (abs(floatval($row['tax_percent']) - floatval($fields['percent'])) > 0.00001 || count($now) !== count($fields['components'])) {
        return false;
    }
    foreach ($fields['components'] as $i => $part) {
        if ($now[$i]['name'] !== $part['name'] || abs($now[$i]['percent'] - $part['percent']) > 0.00001 || (bool) $now[$i]['compound'] !== (bool) $part['compound']) {
            return false;
        }
    }
    return true;
}

/** Write a rate's parts - none for a single rate. */
function taxWriteComponents($tax_id, array $components) {
    global $mysqli;
    $tax_id = intval($tax_id);
    mysqli_query($mysqli, "DELETE FROM tax_components WHERE component_tax_id = $tax_id");
    foreach (array_values($components) as $order => $part) {
        $registration = trim((string) ($part['registration'] ?? ''));
        mysqli_query($mysqli, "INSERT INTO tax_components SET component_tax_id = $tax_id, component_name = '" . escapeSql($part['name']) . "',
            component_percent = " . round(floatval($part['percent']), 4) . ", component_compound = " . (!empty($part['compound']) ? 1 : 0) . ",
            component_registration_number = " . ($registration !== '' ? "'" . escapeSql($registration) . "'" : 'NULL') . ", component_order = " . ($order + 1));
    }
}

/** Add a rate. Its id. */
function taxCreate(array $fields) {
    global $mysqli;
    mysqli_query($mysqli, "INSERT INTO taxes SET tax_name = '" . escapeSql($fields['name']) . "', tax_percent = " . round(floatval($fields['percent']), 4) . ",
        tax_description = " . ($fields['description'] !== '' ? "'" . escapeSql($fields['description']) . "'" : 'NULL') . ",
        tax_registration_number = " . ($fields['registration'] !== '' ? "'" . escapeSql($fields['registration']) . "'" : 'NULL'));
    $tax_id = intval(mysqli_insert_id($mysqli));
    taxWriteComponents($tax_id, $fields['components']);
    taxForget();
    return $tax_id;
}

/**
 * Save a rate. Its name, description and registration number change in
 * place. Its percent or parts change in place too, until it has been billed
 * (taxBilled()); after that a new rate takes its place (taxChangeRate()). ['id' => the rate
 * now, 'replaced' => bool, 'moved' => what moved to it].
 */
function taxUpdate($tax_id, array $fields, $move_drafts = true) {
    global $mysqli;
    $tax_id = intval($tax_id);
    $row = taxRow($tax_id);
    if (!$row) {
        return null;
    }
    if (!taxSameRate($row, $fields) && taxBilled($tax_id)) {
        return taxChangeRate($tax_id, $fields, $move_drafts);
    }
    mysqli_query($mysqli, "UPDATE taxes SET tax_name = '" . escapeSql($fields['name']) . "', tax_percent = " . round(floatval($fields['percent']), 4) . ",
        tax_description = " . ($fields['description'] !== '' ? "'" . escapeSql($fields['description']) . "'" : 'NULL') . ",
        tax_registration_number = " . ($fields['registration'] !== '' ? "'" . escapeSql($fields['registration']) . "'" : 'NULL') . "
        WHERE tax_id = $tax_id");
    $rate_changed = !taxSameRate($row, $fields);
    taxWriteComponents($tax_id, $fields['components']);
    taxForget();
    $moved = [];
    if ($rate_changed) {
        // Not billed yet - recurring invoices and drafts that carry it are worked out again at its new rate
        $moved = taxRecalcOpen($tax_id, $move_drafts);
    }
    return ['id' => $tax_id, 'replaced' => false, 'moved' => $moved];
}

/**
 * A billed rate's percent or parts changed: a new rate with the same name
 * takes its place from now on. The old one is archived, saying which rate
 * replaced it, and stays on every line already billed. Products, clients,
 * the default rate and recurring invoices move to the new one - and draft
 * invoices and quotes too, unless $move_drafts is false.
 */
function taxChangeRate($tax_id, array $fields, $move_drafts = true) {
    global $mysqli;
    $tax_id = intval($tax_id);
    $new_id = taxCreate($fields);
    mysqli_query($mysqli, "UPDATE taxes SET tax_archived_at = NOW(), tax_replaced_by = $new_id WHERE tax_id = $tax_id");
    return ['id' => $new_id, 'replaced' => true, 'moved' => taxRepoint($tax_id, $new_id, $move_drafts)];
}

/**
 * Move what uses a rate on to another: products, clients, the default and
 * recurring invoices - and draft invoices and quotes too, unless $move_drafts
 * is false - each worked out again at the new rate. Lines already billed keep
 * theirs. What moved, for taxMovedText().
 */
function taxRepoint($from_id, $to_id, $move_drafts = true) {
    global $mysqli;
    $from_id = intval($from_id);
    $to_id = intval($to_id);
    if ($from_id <= 0 || $to_id <= 0 || $from_id === $to_id) {
        return [];
    }
    mysqli_query($mysqli, "UPDATE products SET product_tax_id = $to_id WHERE product_tax_id = $from_id");
    $moved = ['products' => mysqli_affected_rows($mysqli)];
    mysqli_query($mysqli, "UPDATE clients SET client_default_tax_id = $to_id WHERE client_default_tax_id = $from_id");
    $moved['clients'] = mysqli_affected_rows($mysqli);
    mysqli_query($mysqli, "UPDATE settings SET config_tax_default_id = $to_id WHERE config_tax_default_id = $from_id");
    // Only the documents that carried the old rate are worked out again
    $recurring = taxDocIds('recurring', $from_id);
    $drafts = $move_drafts ? ['invoice' => taxDocIds('invoice', $from_id), 'quote' => taxDocIds('quote', $from_id)] : ['invoice' => [], 'quote' => []];
    mysqli_query($mysqli, "UPDATE recurring_invoice_items SET item_tax_id = $to_id WHERE item_tax_id = $from_id");
    if ($move_drafts) {
        mysqli_query($mysqli, "UPDATE invoice_items JOIN invoices ON invoice_id = item_invoice_id SET item_tax_id = $to_id
            WHERE item_tax_id = $from_id AND invoice_status = 'Draft'");
        mysqli_query($mysqli, "UPDATE quote_items JOIN quotes ON quote_id = item_quote_id SET item_tax_id = $to_id
            WHERE item_tax_id = $from_id AND quote_status = 'Draft'");
    }
    taxSettings(true);
    taxForget();
    foreach (['recurring' => $recurring] + $drafts as $type => $ids) {
        foreach ($ids as $doc_id) {
            billingRecalc($type, $doc_id);
        }
    }
    $moved['recurring'] = count($recurring);
    $moved['invoices'] = count($drafts['invoice']);
    $moved['quotes'] = count($drafts['quote']);
    return $moved;
}

/** The recurring invoices, or draft invoices or quotes, with a line at a rate. */
function taxDocIds($type, $tax_id) {
    global $mysqli;
    $tax_id = intval($tax_id);
    $queries = [
        'recurring' => "SELECT DISTINCT item_recurring_invoice_id FROM recurring_invoice_items WHERE item_tax_id = $tax_id",
        'invoice' => "SELECT DISTINCT item_invoice_id FROM invoice_items JOIN invoices ON invoice_id = item_invoice_id WHERE item_tax_id = $tax_id AND invoice_status = 'Draft'",
        'quote' => "SELECT DISTINCT item_quote_id FROM quote_items JOIN quotes ON quote_id = item_quote_id WHERE item_tax_id = $tax_id AND quote_status = 'Draft'",
    ];
    $ids = [];
    $sql = mysqli_query($mysqli, $queries[$type]);
    while ($row = mysqli_fetch_row($sql)) {
        $ids[] = intval($row[0]);
    }
    return $ids;
}

/** Work out again the recurring invoices - and, with $drafts, the draft invoices and quotes - that carry a rate. How many of each. */
function taxRecalcOpen($tax_id, $drafts = true) {
    $moved = ['recurring' => 0, 'invoices' => 0, 'quotes' => 0];
    foreach (['recurring' => 'recurring', 'invoice' => 'invoices', 'quote' => 'quotes'] as $type => $key) {
        if ($type !== 'recurring' && !$drafts) {
            continue;
        }
        foreach (taxDocIds($type, $tax_id) as $doc_id) {
            billingRecalc($type, $doc_id);
            $moved[$key]++;
        }
    }
    return $moved;
}

/**
 * Archive a rate - no longer offered for new lines. What uses it moves to
 * $move_to when one is given (taxRepoint()); otherwise products and clients on
 * it fall back to the next rate along (taxDefaultFor()), and recurring
 * invoices keep charging it. It stops being the default either way. What
 * moved, and whether it was the default.
 */
function taxArchive($tax_id, $move_to = 0, $move_drafts = true) {
    global $mysqli;
    $tax_id = intval($tax_id);
    $move_to = intval($move_to);
    $was_default = taxSettings()['default_id'] === $tax_id;
    $moved = [];
    if ($move_to && $move_to !== $tax_id && ($to = taxRow($move_to)) && !$to['tax_archived_at']) {
        $moved = taxRepoint($tax_id, $move_to, $move_drafts);
    }
    mysqli_query($mysqli, "UPDATE taxes SET tax_archived_at = NOW() WHERE tax_id = $tax_id AND tax_archived_at IS NULL");
    mysqli_query($mysqli, "UPDATE settings SET config_tax_default_id = 0 WHERE config_tax_default_id = $tax_id");
    taxSettings(true);
    taxForget();
    return ['moved' => $moved, 'was_default' => $was_default && !$moved];
}

/** Offer an archived rate again. It no longer points at the rate that replaced it. */
function taxRestore($tax_id) {
    global $mysqli;
    $tax_id = intval($tax_id);
    mysqli_query($mysqli, "UPDATE taxes SET tax_archived_at = NULL, tax_replaced_by = 0 WHERE tax_id = $tax_id");
    taxForget();
}

/** Whether nothing has ever used a rate - no line, product or client (archived ones too), not the default, and no rate replaced by it. */
function taxUnused($tax_id) {
    global $mysqli;
    $tax_id = intval($tax_id);
    foreach ([
        "SELECT 1 FROM invoice_items WHERE item_tax_id = $tax_id LIMIT 1",
        "SELECT 1 FROM quote_items WHERE item_tax_id = $tax_id LIMIT 1",
        "SELECT 1 FROM recurring_invoice_items WHERE item_tax_id = $tax_id LIMIT 1",
        "SELECT 1 FROM products WHERE product_tax_id = $tax_id LIMIT 1",
        "SELECT 1 FROM clients WHERE client_default_tax_id = $tax_id LIMIT 1",
        "SELECT 1 FROM taxes WHERE tax_replaced_by = $tax_id LIMIT 1",
    ] as $query) {
        if (mysqli_num_rows(mysqli_query($mysqli, $query))) {
            return false;
        }
    }
    return taxSettings()['default_id'] !== $tax_id;
}

/** Delete a rate nothing has used, with its parts. Whether it went. */
function taxDelete($tax_id) {
    global $mysqli;
    $tax_id = intval($tax_id);
    if (!taxRow($tax_id) || !taxUnused($tax_id)) {
        return false;
    }
    mysqli_query($mysqli, "DELETE FROM tax_components WHERE component_tax_id = $tax_id");
    mysqli_query($mysqli, "DELETE FROM taxes WHERE tax_id = $tax_id");
    taxForget();
    return true;
}

/** Make a rate the default for new lines - 0 for none. */
function taxSetDefault($tax_id) {
    global $mysqli;
    $tax_id = intval($tax_id);
    if ($tax_id && (!($row = taxRow($tax_id)) || $row['tax_archived_at'])) {
        return false;
    }
    mysqli_query($mysqli, "UPDATE settings SET config_tax_default_id = $tax_id WHERE company_id = 1");
    taxSettings(true);
    return true;
}

/** Where each rate is used - taxUsage() for every rate at once, for Admin > Taxes. */
function taxUsageAll() {
    global $mysqli;
    $usage = [];
    $sql = mysqli_query($mysqli, "SELECT tax_id FROM taxes");
    while ($row = mysqli_fetch_row($sql)) {
        $usage[intval($row[0])] = ['invoices' => 0, 'quotes' => 0, 'recurring' => 0, 'products' => 0, 'clients' => 0, 'default' => false];
    }
    $queries = [
        'invoices' => "SELECT item_tax_id, COUNT(DISTINCT item_invoice_id) FROM invoice_items WHERE item_tax_id > 0 GROUP BY item_tax_id",
        'quotes' => "SELECT item_tax_id, COUNT(DISTINCT item_quote_id) FROM quote_items WHERE item_tax_id > 0 GROUP BY item_tax_id",
        'recurring' => "SELECT item_tax_id, COUNT(DISTINCT item_recurring_invoice_id) FROM recurring_invoice_items WHERE item_tax_id > 0 GROUP BY item_tax_id",
        'products' => "SELECT product_tax_id, COUNT(*) FROM products WHERE product_tax_id > 0 AND product_archived_at IS NULL GROUP BY product_tax_id",
        'clients' => "SELECT client_default_tax_id, COUNT(*) FROM clients WHERE client_default_tax_id > 0 AND client_archived_at IS NULL GROUP BY client_default_tax_id",
    ];
    foreach ($queries as $key => $query) {
        $sql = mysqli_query($mysqli, $query);
        while ($row = mysqli_fetch_row($sql)) {
            if (isset($usage[intval($row[0])])) {
                $usage[intval($row[0])][$key] = intval($row[1]);
            }
        }
    }
    $default_id = taxSettings()['default_id'];
    if (isset($usage[$default_id])) {
        $usage[$default_id]['default'] = true;
    }
    return $usage;
}

/** "3 products, 2 clients and 4 recurring invoices" from what moved - '' for nothing. */
function taxMovedText(array $moved) {
    $words = ['products' => ['product', 'products'], 'clients' => ['client', 'clients'], 'recurring' => ['recurring invoice', 'recurring invoices'],
        'invoices' => ['draft invoice', 'draft invoices'], 'quotes' => ['draft quote', 'draft quotes']];
    $parts = [];
    foreach ($words as $key => [$one, $many]) {
        $n = intval($moved[$key] ?? 0);
        if ($n) {
            $parts[] = "$n " . ($n === 1 ? $one : $many);
        }
    }
    if (count($parts) > 1) {
        $last = array_pop($parts);
        return implode(', ', $parts) . ' and ' . $last;
    }
    return $parts[0] ?? '';
}

/**
 * A line's tax select - No tax and the rates, with $selected chosen and each
 * <option data-percent> for the windows that work out a line as it is typed.
 * $taxes: billingTaxes(); a selected rate it leaves out (archived) is added.
 */
function taxOptionsHtml(array $taxes, $selected = 0, $usual_label = '', $usual_percent = 0) {
    $selected = intval($selected);
    if ($selected > 0 && !isset($taxes[$selected]) && ($row = taxRow($selected))) {
        $taxes[$selected] = $row;
    }
    $html = $usual_label !== '' ? '<option value="' . TAX_USUAL . '" data-percent="' . floatval($usual_percent) . '"' . ($selected === TAX_USUAL ? ' selected' : '') . '>' . escapeHtml($usual_label) . '</option>' : '';
    $html .= '<option value="0" data-percent="0"' . ($selected === 0 ? ' selected' : '') . '>No tax</option>';
    foreach ($taxes as $tax_id => $tax) {
        $html .= '<option value="' . intval($tax_id) . '" data-percent="' . floatval($tax['tax_percent']) . '"' . ($selected === intval($tax_id) ? ' selected' : '') . '>'
            . escapeHtml(taxLabel($tax)) . '</option>';
    }
    return $html;
}

/** taxOptionsHtml() with the rates to offer now - or, with $since, those in use since then too. */
function taxSelectOptions($selected = 0, $since = null) {
    return taxOptionsHtml(billingTaxes($since), $selected);
}
