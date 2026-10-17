<?php

/*
 * Billing documents - invoices, quotes and recurring invoices.
 *
 * The three share their shape: a numbered document for a client, with a
 * scope, an income category, line items (a name, a description, a quantity,
 * a price and a tax), a discount and a total. Each keeps its items in its own
 * table - invoice_items, quote_items, recurring_invoice_items - with the same
 * columns, and its history in the history table under its own id column.
 * billingTypes() says where each keeps what, so one set of functions serves
 * all three: the line items (billingItemSave(), billingItemDelete(),
 * billingRecalc()), the totals, the numbers, and the statuses and pills the
 * lists and pages show.
 *
 * Amounts are rounded to the cent where they are made - a line's tax and
 * total, a document's total - so what is stored is what is shown, printed
 * and paid.
 */

/** Where each kind of billing document keeps its parts. */
function billingTypes() {
    return [
        'invoice' => [
            'label' => 'Invoice', 'plural' => 'Invoices', 'icon' => 'fa-file-invoice-dollar',
            'table' => 'invoices', 'col' => 'invoice', 'items' => 'invoice_items', 'item_fk' => 'item_invoice_id',
            'history_fk' => 'history_invoice_id', 'page' => 'invoice.php', 'list' => 'invoices.php', 'param' => 'invoice_id',
            'number_setting' => 'config_invoice_next_number',
        ],
        'quote' => [
            'label' => 'Quote', 'plural' => 'Quotes', 'icon' => 'fa-comment-dollar',
            'table' => 'quotes', 'col' => 'quote', 'items' => 'quote_items', 'item_fk' => 'item_quote_id',
            'history_fk' => 'history_quote_id', 'page' => 'quote.php', 'list' => 'quotes.php', 'param' => 'quote_id',
            'number_setting' => 'config_quote_next_number',
        ],
        'recurring' => [
            'label' => 'Recurring invoice', 'plural' => 'Recurring invoices', 'icon' => 'fa-redo-alt',
            'table' => 'recurring_invoices', 'col' => 'recurring_invoice', 'items' => 'recurring_invoice_items', 'item_fk' => 'item_recurring_invoice_id',
            'history_fk' => 'history_recurring_invoice_id', 'page' => 'recurring_invoice.php', 'list' => 'recurring_invoices.php', 'param' => 'recurring_invoice_id',
            'number_setting' => 'config_recurring_invoice_next_number',
        ],
    ];
}

/** One kind's map, or null. */
function billingType($type) {
    return billingTypes()[$type] ?? null;
}

/**
 * An amount in a currency, the way the rest of the app shows money - in the
 * company's locale, also on the guest pages and in PDFs where the agent
 * session's formatter isn't set up.
 */
function billingMoney($amount, $currency_code = '') {
    global $currency_format, $session_company_currency;
    static $formatter = null;
    if ($currency_format instanceof NumberFormatter) {
        $format = $currency_format;
    } else {
        $formatter = $formatter ?: numfmt_create((string) (billingCompany()['company_locale'] ?? '') ?: 'en_US', NumberFormatter::CURRENCY);
        $format = $formatter;
    }
    $currency_code = (string) $currency_code !== '' ? (string) $currency_code : (string) (($session_company_currency ?? '') ?: (billingCompany()['company_currency'] ?? '') ?: 'USD');
    return numfmt_format_currency($format, floatval($amount), $currency_code);
}

/** A quantity without trailing zeros - 1, 2.5, 0.25. */
function billingQuantity($quantity) {
    $text = rtrim(rtrim(number_format(floatval($quantity), 2, '.', ''), '0'), '.');
    return $text === '' || $text === '-0' ? '0' : $text;
}

/** A document's number as shown - INV-123. */
function billingNumber($type, $row) {
    $t = billingType($type);
    return (string) ($row["{$t['col']}_prefix"] ?? '') . (string) ($row["{$t['col']}_number"] ?? '');
}

/**
 * One document by id, with its client - raw, or null. $scoped limits it to
 * the clients this agent may open; the guest pages pass false and check the
 * document's own url key instead.
 */
function billingRow($type, $id, $scoped = true) {
    global $mysqli;
    $t = billingType($type);
    $id = intval($id);
    if (!$t || !$id) {
        return null;
    }
    $c = $t['col'];
    $scope = $scoped ? clientScopeSql("{$c}_client_id") : '';
    $extra = $type === 'recurring' ? ', recurring_payment_id, recurring_payment_saved_payment_id, recurring_payment_method' : '';
    $join = $type === 'recurring' ? 'LEFT JOIN recurring_payments ON recurring_payment_recurring_invoice_id = recurring_invoice_id' : '';
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT {$t['table']}.*, client_id, client_name, client_abbreviation, client_currency_code, client_net_terms,
            client_tax_id_number, category_name $extra
        FROM {$t['table']}
        LEFT JOIN clients ON client_id = {$c}_client_id
        LEFT JOIN categories ON category_id = {$c}_category_id
        $join
        WHERE {$c}_id = $id $scope
        LIMIT 1"));
    return $row ?: null;
}

/** A document's line items, in order. */
function billingItems($type, $id) {
    global $mysqli;
    $t = billingType($type);
    $id = intval($id);
    $items = [];
    if (!$t || !$id) {
        return $items;
    }
    $seat_columns = $type === 'recurring' ? ', item_seat_source, item_seat_filter, item_seat_minimum' : ($type === 'quote' ? ', item_frequency' : '');
    $sql = mysqli_query($mysqli, "SELECT item_id, item_name, item_description, item_quantity, item_price, item_subtotal, item_tax, item_total,
            item_order, item_tax_id, item_product_id, item_created_at, tax_name, tax_percent $seat_columns
        FROM {$t['items']}
        LEFT JOIN taxes ON tax_id = item_tax_id
        WHERE {$t['item_fk']} = $id
        ORDER BY item_order ASC, item_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $items[] = $row;
    }
    return $items;
}

/**
 * The taxes to offer - those not archived, and those archived after $since
 * (a document's or line's date), so an old line keeps the tax it was given.
 */
function billingTaxes($since = null) {
    global $mysqli;
    $since_sql = $since ? "OR tax_archived_at > '" . escapeSql($since) . "'" : '';
    $taxes = [];
    $sql = mysqli_query($mysqli, "SELECT tax_id, tax_name, tax_percent FROM taxes WHERE tax_archived_at IS NULL $since_sql ORDER BY tax_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $taxes[intval($row['tax_id'])] = $row;
    }
    return $taxes;
}

/** The income categories to offer - the same rule as billingTaxes(). */
function billingCategories($since = null) {
    global $mysqli;
    $since_sql = $since ? "OR category_archived_at > '" . escapeSql($since) . "'" : '';
    $categories = [];
    $sql = mysqli_query($mysqli, "SELECT category_id, category_name, category_color, category_icon FROM categories
        WHERE category_type = 'Income' AND (category_archived_at IS NULL $since_sql) ORDER BY category_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $categories[intval($row['category_id'])] = $row;
    }
    return $categories;
}

/** A line's subtotal, tax and total, each to the cent, at a single percent. */
function billingLineAmounts($quantity, $price, $tax_percent) {
    $subtotal = round(floatval($quantity) * floatval($price), 2);
    $tax = round($subtotal * floatval($tax_percent) / 100, 2);
    return [$subtotal, $tax, round($subtotal + $tax, 2)];
}

/** A line's subtotal, tax and total at a rate (functions/tax.php) - each of its parts to the cent, on $base when a discount came off first. */
function billingLineTax($quantity, $price, $tax_id, $base = null) {
    $subtotal = round(floatval($quantity) * floatval($price), 2);
    $tax = intval($tax_id) ? taxAmounts($base === null ? $subtotal : $base, $tax_id)['tax'] : 0.0;
    return [$subtotal, $tax, round($subtotal + $tax, 2)];
}

/** Whether a document's lines may still change - so its tax is worked out again. Not a paid, cancelled or non-billable invoice, nor an answered or invoiced quote. */
function billingLinesOpen($type, $status) {
    if ($type === 'invoice') {
        return !in_array((string) $status, ['Paid', 'Cancelled', 'Non-Billable'], true);
    }
    if ($type === 'quote') {
        return !in_array((string) $status, ['Accepted', 'Declined', 'Invoiced'], true);
    }
    return true;
}

/**
 * A discount shared out over lines in proportion to what each comes to -
 * item id => its share, to the cent, adding up to the discount (never more
 * than the lines come to). Lines that come to nothing or less take none.
 */
function billingDiscountShares(array $items, $discount) {
    $discount = round(floatval($discount), 2);
    $positive = array_filter($items, function ($item) {
        return floatval($item['item_subtotal']) > 0;
    });
    $pool = array_sum(array_map(function ($item) {
        return floatval($item['item_subtotal']);
    }, $positive));
    if ($discount == 0 || $pool <= 0) {
        return [];
    }
    $sign = $discount < 0 ? -1 : 1;
    $cents = intval(round(min(abs($discount), $pool) * 100));
    $shares = [];
    $remainders = [];
    $given = 0;
    foreach ($positive as $item) {
        $exact = $cents * floatval($item['item_subtotal']) / $pool;
        $whole = intval(floor($exact));
        $shares[intval($item['item_id'])] = $whole;
        $remainders[intval($item['item_id'])] = $exact - $whole;
        $given += $whole;
    }
    arsort($remainders);
    foreach (array_keys($remainders) as $item_id) {
        if ($given >= $cents) {
            break;
        }
        $shares[$item_id]++;
        $given++;
    }
    return array_map(function ($share) use ($sign) {
        return $sign * $share / 100;
    }, $shares);
}

/**
 * Work out each line's tax again - on what the line comes to, less its share
 * of the discount when discounts come off before tax (Admin > Taxes). A quote's
 * discount is on its one-time lines, or the first recurring group when it has
 * none. A line whose rate no longer exists keeps the tax it has.
 */
function billingRecalcTaxes($type, $doc_id, $discount) {
    global $mysqli;
    $t = billingType($type);
    $doc_id = intval($doc_id);
    $frequency_column = $type === 'quote' ? ', item_frequency' : '';
    $items = [];
    $sql = mysqli_query($mysqli, "SELECT item_id, item_quantity, item_price, item_subtotal, item_tax, item_total, item_tax_id $frequency_column
        FROM {$t['items']} WHERE {$t['item_fk']} = $doc_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $items[] = $row;
    }
    $shares = [];
    if (taxSettings()['discount_before'] && floatval($discount) != 0) {
        $pool = $items;
        if ($type === 'quote') {
            $order = ['', 'month', 'quarter', 'year'];
            $present = array_unique(array_map(function ($item) {
                return quoteFrequency($item['item_frequency'] ?? '');
            }, $items));
            $on = '';
            foreach ($order as $frequency) {
                if (in_array($frequency, $present, true)) {
                    $on = $frequency;
                    break;
                }
            }
            $pool = array_filter($items, function ($item) use ($on) {
                return quoteFrequency($item['item_frequency'] ?? '') === $on;
            });
        }
        $shares = billingDiscountShares($pool, $discount);
    }
    foreach ($items as $item) {
        $tax_id = intval($item['item_tax_id']);
        if ($tax_id && !taxRow($tax_id)) {
            continue;
        }
        $subtotal = floatval($item['item_subtotal']);
        $base = round($subtotal - ($shares[intval($item['item_id'])] ?? 0), 2);
        $tax = $tax_id ? taxAmounts($base, $tax_id)['tax'] : 0.0;
        $total = round($subtotal + $tax, 2);
        if ($tax != floatval($item['item_tax']) || $total != floatval($item['item_total'])) {
            mysqli_query($mysqli, "UPDATE {$t['items']} SET item_tax = $tax, item_total = $total WHERE item_id = " . intval($item['item_id']));
        }
    }
}

/**
 * A document's tax by part, for its totals - label => amount, adding up to
 * the tax on its lines. A line's tax is shared over its rate's parts as they
 * come to on the line, so a rate made of parts shows each (State 6%, County
 * 1%), and parts of the same name and percent on different rates are one.
 * Also the registration numbers it is charged under, by the rate or part each is for.
 */
function billingTaxParts(array $items) {
    $parts = [];
    $registrations = [];
    foreach ($items as $item) {
        $tax = round(floatval($item['item_tax']), 2);
        if ($tax == 0) {
            continue;
        }
        $row = taxRow($item['item_tax_id'] ?? 0);
        if (!$row) {
            $label = trim(($item['tax_name'] ?? 'Tax') . (isset($item['tax_percent']) && $item['tax_percent'] !== null ? ' ' . taxPercentText($item['tax_percent']) . '%' : ''));
            $parts[$label] = round(($parts[$label] ?? 0) + $tax, 2);
            continue;
        }
        $split = taxAmounts(floatval($item['item_subtotal']), $row)['parts'];
        $whole = array_sum(array_column($split, 'amount'));
        $left = $tax;
        foreach ($split as $i => $part) {
            if ($part['registration'] !== '') {
                $registrations[$part['registration_for']] = $part['registration'];
            }
            $amount = $i === count($split) - 1 || $whole == 0 ? $left : round($tax * $part['amount'] / $whole, 2);
            $left = round($left - $amount, 2);
            $parts[$part['label']] = round(($parts[$part['label']] ?? 0) + $amount, 2);
        }
    }
    return ['parts' => $parts, 'registrations' => $registrations];
}

/** A number from a form - "1,250.50", "$99", "-15" - or null when it is not one. */
function billingNumberInput($value) {
    $value = trim(str_replace([',', ' ', '$', '€', '£'], '', (string) $value));
    if ($value === '') {
        return 0.0;
    }
    return is_numeric($value) ? round(floatval($value), 2) : null;
}

/**
 * May a document's lines change? Not once an invoice is paid, cancelled or
 * non-billable, nor once a quote has been answered or invoiced.
 */
function billingCanEditItems($type, $row) {
    if (!$row || lookupUserPermission('module_sales') < 2) {
        return false;
    }
    if ($type === 'invoice') {
        return !in_array($row['invoice_status'], ['Paid', 'Cancelled', 'Non-Billable'], true);
    }
    if ($type === 'quote') {
        return !in_array($row['quote_status'], ['Accepted', 'Declined', 'Invoiced'], true);
    }
    return true;
}

/**
 * Add a line to a document, or change one. $in: name, description, qty,
 * price, tax_id, product_id. For an invoice, a tracked product's stock goes
 * down by what the line takes (and back up if the quantity drops). The
 * document's total follows. The line's id, or null with $error.
 */
function billingItemSave($type, $doc_id, array $in, $item_id = 0, &$error = '') {
    global $mysqli;
    $error = '';
    $t = billingType($type);
    $doc_id = intval($doc_id);
    $item_id = intval($item_id);
    if (!$t || !$doc_id) {
        $error = 'That document no longer exists.';
        return null;
    }

    $name = trim((string) ($in['name'] ?? ''));
    $description = trim((string) ($in['description'] ?? ''));
    $quantity = billingNumberInput($in['qty'] ?? '1');
    $price = billingNumberInput($in['price'] ?? '0');
    $tax_id = intval($in['tax_id'] ?? 0);
    $product_id = intval($in['product_id'] ?? 0);

    if ($name === '') {
        $error = 'A line needs a name.';
        return null;
    }
    if (mb_strlen($name) > 200) {
        $error = 'That name is too long - keep it under 200 characters, and put the rest in the description.';
        return null;
    }
    // A recurring line can count its quantity from the client - see recurringSeatSources()
    $seat_set = '';
    if ($type === 'recurring' && array_key_exists('seat_source', $in)) {
        $seat_source = (string) $in['seat_source'];
        if (isset(recurringSeatSources()[$seat_source])) {
            $seat_filter = recurringSeatFilter($seat_source, [
                'tag' => $in['seat_tag'] ?? 0, 'types' => (array) ($in['seat_types'] ?? []),
                'software_id' => $in['seat_software_id'] ?? 0, 'count' => $in['seat_count'] ?? 'used',
            ]);
            $seat_minimum = billingNumberInput($in['seat_minimum'] ?? '0');
            if ($seat_minimum === null || $seat_minimum < 0) {
                $error = 'The minimum has to be a number, zero or more.';
                return null;
            }
            $seat_count = recurringSeatCount(getFieldById('recurring_invoices', $doc_id, 'recurring_invoice_client_id'), $seat_source, $seat_filter);
            if ($seat_count === null) {
                $error = $seat_source === 'license' ? "Pick one of the client's licenses to count." : "Those can't be counted.";
                return null;
            }
            $quantity = max(floatval($seat_count), $seat_minimum);
            $seat_set = ", item_seat_source = '$seat_source', item_seat_filter = '" . escapeSql(json_encode($seat_filter)) . "', item_seat_minimum = $seat_minimum";
        } else {
            $seat_set = ", item_seat_source = NULL, item_seat_filter = NULL, item_seat_minimum = 0";
        }
    }

    // A quote line is billed once, or each month, quarter or year
    if ($type === 'quote' && array_key_exists('frequency', $in)) {
        $frequency = quoteFrequency($in['frequency']);
        $seat_set = ", item_frequency = " . ($frequency === '' ? 'NULL' : "'$frequency'");
    }

    if ($quantity === null || $price === null) {
        $error = 'The quantity and price have to be numbers.';
        return null;
    }
    if (abs($quantity) >= 1e9 || abs($price) >= 1e11) {
        $error = 'That amount is too large.';
        return null;
    }

    if ($tax_id && !taxRow($tax_id)) {
        $error = 'That tax no longer exists.';
        return null;
    }
    // Its tax on the whole line for now - billingRecalc() takes any discount off first
    [$subtotal, $tax_amount, $total] = billingLineTax($quantity, $price, $tax_id);

    $old = null;
    if ($item_id) {
        $old = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT item_quantity, item_product_id FROM {$t['items']} WHERE item_id = $item_id AND {$t['item_fk']} = $doc_id"));
        if (!$old) {
            $error = 'That line no longer exists.';
            return null;
        }
        $product_id = intval($old['item_product_id']);
    } elseif ($product_id && !getFieldById('products', $product_id, 'product_name')) {
        $product_id = 0;
    }

    // Stock moves only with invoices, and only for products whose stock is tracked
    $stock_change = 0;
    if ($type === 'invoice' && $product_id && intval(getFieldById('products', $product_id, 'product_track_stock')) === 1) {
        $stock_change = round($quantity - ($old ? floatval($old['item_quantity']) : 0), 2);
        if ($stock_change > 0) {
            $available = productOnHand($product_id);
            if ($available < $stock_change) {
                $error = 'Not enough ' . $name . ' in stock - ' . billingQuantity($available) . ' left.';
                return null;
            }
        }
    }

    $set = "item_name = '" . escapeSql($name) . "', item_description = '" . escapeSql($description) . "',
        item_quantity = $quantity, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id$seat_set";
    if ($item_id) {
        mysqli_query($mysqli, "UPDATE {$t['items']} SET $set WHERE item_id = $item_id");
    } else {
        $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(item_order), 0) + 1 FROM {$t['items']} WHERE {$t['item_fk']} = $doc_id"))[0]);
        mysqli_query($mysqli, "INSERT INTO {$t['items']} SET $set, item_order = $order, item_product_id = $product_id, {$t['item_fk']} = $doc_id");
        $item_id = mysqli_insert_id($mysqli);
    }
    if ($stock_change != 0) {
        productStockMove($product_id, -$stock_change, $stock_change > 0 ? 'sold' : 'returned', [
            'invoice_id' => $doc_id, 'item_id' => $item_id, 'note' => billingNumber('invoice', billingRow('invoice', $doc_id, false)) . ($old ? ' - quantity changed' : ''),
        ]);
    }

    billingRecalc($type, $doc_id);
    return $item_id;
}

/** Change a line's price - its amounts at its tax rate, then the document's total. False when the line isn't on it. */
function billingItemSetPrice($type, $doc_id, $item_id, $price) {
    global $mysqli;
    $t = billingType($type);
    $doc_id = intval($doc_id);
    $item_id = intval($item_id);
    $item = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT item_quantity, item_subtotal, item_tax, item_tax_id FROM {$t['items']}
        WHERE item_id = $item_id AND {$t['item_fk']} = $doc_id"));
    if (!$item) {
        return false;
    }
    $price = round(floatval($price), 2);
    if (!intval($item['item_tax_id']) || taxRow($item['item_tax_id'])) {
        [$subtotal, $tax, $total] = billingLineTax($item['item_quantity'], $price, $item['item_tax_id']);
    } else {
        // Its rate is gone - the rate it was charged at
        $percent = floatval($item['item_subtotal']) != 0 ? floatval($item['item_tax']) / floatval($item['item_subtotal']) * 100 : 0;
        [$subtotal, $tax, $total] = billingLineAmounts($item['item_quantity'], $price, $percent);
    }
    mysqli_query($mysqli, "UPDATE {$t['items']} SET item_price = $price, item_subtotal = $subtotal, item_tax = $tax, item_total = $total WHERE item_id = $item_id");
    billingRecalc($type, $doc_id);
    return true;
}

/** Take a line off a document - a tracked product goes back on the shelf. The line, or null. */
function billingItemDelete($type, $doc_id, $item_id) {
    global $mysqli;
    $t = billingType($type);
    $doc_id = intval($doc_id);
    $item_id = intval($item_id);
    $item = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT item_name, item_quantity, item_product_id FROM {$t['items']} WHERE item_id = $item_id AND {$t['item_fk']} = $doc_id"));
    if (!$item) {
        return null;
    }
    mysqli_query($mysqli, "DELETE FROM {$t['items']} WHERE item_id = $item_id");
    $product_id = intval($item['item_product_id']);
    if ($type === 'invoice' && $product_id && intval(getFieldById('products', $product_id, 'product_track_stock')) === 1 && floatval($item['item_quantity']) > 0) {
        productStockMove($product_id, floatval($item['item_quantity']), 'returned', [
            'invoice_id' => $doc_id, 'item_id' => $item_id, 'note' => billingNumber('invoice', billingRow('invoice', $doc_id, false)) . ' - line taken off',
        ]);
    }
    // Assets made from the line stay, still bought on the invoice; an expense or trip billed on it is to bill again
    if ($type === 'invoice') {
        mysqli_query($mysqli, "UPDATE assets SET asset_invoice_item_id = 0 WHERE asset_invoice_id = $doc_id AND asset_invoice_item_id = $item_id");
        mysqli_query($mysqli, "UPDATE expenses SET expense_invoice_id = NULL, expense_invoice_item_id = NULL WHERE expense_invoice_id = $doc_id AND expense_invoice_item_id = $item_id");
        mysqli_query($mysqli, "UPDATE trips SET trip_invoice_id = 0, trip_invoice_item_id = 0 WHERE trip_invoice_id = $doc_id AND trip_invoice_item_id = $item_id");
    }
    billingRecalc($type, $doc_id);
    return $item;
}

/** Put a document's lines in this order - item ids, first to last. */
function billingItemsOrder($type, $doc_id, array $item_ids) {
    global $mysqli;
    $t = billingType($type);
    $doc_id = intval($doc_id);
    foreach (array_values($item_ids) as $order => $item_id) {
        $item_id = intval($item_id);
        mysqli_query($mysqli, "UPDATE {$t['items']} SET item_order = " . ($order + 1) . " WHERE item_id = $item_id AND {$t['item_fk']} = $doc_id");
    }
}

/**
 * A document's total from its lines, less its discount - stored, and for an
 * invoice the status follows what has been paid against the new total.
 */
function billingRecalc($type, $doc_id) {
    global $mysqli;
    $t = billingType($type);
    $doc_id = intval($doc_id);
    $c = $t['col'];
    $status_column = $type === 'recurring' ? "''" : "{$c}_status";
    $doc = mysqli_fetch_row(mysqli_query($mysqli, "SELECT {$c}_discount_amount, $status_column FROM {$t['table']} WHERE {$c}_id = $doc_id"));
    $discount = floatval($doc[0] ?? 0);
    // Each line's tax again, while its lines may change - on its share of the
    // discount taken off first, when discounts come off before tax
    if ($doc && billingLinesOpen($type, $doc[1])) {
        billingRecalcTaxes($type, $doc_id, $discount);
    }
    if ($type === 'quote') {
        // What is billed once, and what the recurring lines come to a month
        $groups = quoteLineGroups(billingItems('quote', $doc_id), $discount);
        $amount = isset($groups['']) ? $groups['']['total'] : 0.0;
        mysqli_query($mysqli, "UPDATE quotes SET quote_amount = $amount, quote_monthly_amount = " . quoteMonthly($groups) . " WHERE quote_id = $doc_id");
        return $amount;
    }
    $lines = floatval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(item_total), 0) FROM {$t['items']} WHERE {$t['item_fk']} = $doc_id"))[0]);
    $amount = round($lines - $discount, 2);
    mysqli_query($mysqli, "UPDATE {$t['table']} SET {$c}_amount = $amount WHERE {$c}_id = $doc_id");
    if ($type === 'invoice') {
        $status = getFieldById('invoices', $doc_id, 'invoice_status');
        if (in_array($status, ['Sent', 'Viewed', 'Partial', 'Paid'], true) && invoicePaid($doc_id) > 0) {
            updateInvoiceStatusFromPayments($doc_id);
        }
    }
    return $amount;
}

/** Take the tax off every line of a document - a tax exempt client's - and work it out again. How many lines were taxed. */
function billingRemoveTax($type, $doc_id) {
    global $mysqli;
    $t = billingType($type);
    $doc_id = intval($doc_id);
    mysqli_query($mysqli, "UPDATE {$t['items']} SET item_tax_id = 0, item_tax = 0, item_total = item_subtotal
        WHERE {$t['item_fk']} = $doc_id AND (item_tax_id != 0 OR item_tax != 0)");
    $lines = mysqli_affected_rows($mysqli);
    billingRecalc($type, $doc_id);
    return $lines;
}

/** Subtotal, discount, tax and total for showing - from the lines, and the stored total. */
function billingTotals($type, $row, array $items) {
    $c = billingType($type)['col'];
    $subtotal = 0;
    $tax = 0;
    foreach ($items as $item) {
        $subtotal += floatval($item['item_subtotal']);
        $tax += floatval($item['item_tax']);
    }
    $tax_parts = billingTaxParts($items);
    $totals = [
        'subtotal' => round($subtotal, 2),
        'discount' => floatval($row["{$c}_discount_amount"] ?? 0),
        'credit' => floatval($row["{$c}_credit_amount"] ?? 0),
        'tax' => round($tax, 2),
        'taxes' => $tax_parts['parts'],
        'tax_registrations' => $tax_parts['registrations'],
        'total' => floatval($row["{$c}_amount"] ?? 0),
    ];
    if ($type === 'quote') {
        // One-time and recurring apart - total is what is billed once
        $totals['groups'] = quoteLineGroups($items, $totals['discount']);
        $totals['recurring'] = quoteHasRecurring($totals['groups']);
        $totals['monthly'] = floatval($row['quote_monthly_amount'] ?? 0);
    }
    return $totals;
}

/** The next number for a new invoice, quote or recurring invoice - taken atomically. */
function billingNextNumber($type) {
    global $mysqli;
    $setting = billingType($type)['number_setting'];
    mysqli_query($mysqli, "UPDATE settings SET $setting = LAST_INSERT_ID($setting), $setting = $setting + 1 WHERE company_id = 1");
    return intval(mysqli_insert_id($mysqli));
}

/** A document's history, newest first. */
function billingHistory($type, $id) {
    global $mysqli;
    $t = billingType($type);
    $id = intval($id);
    $rows = [];
    // A recurring invoice's entry for the lines a quote added carries the quote's id too - it is the recurring invoice's
    $own = $type === 'quote' ? ' AND history_recurring_invoice_id = 0' : '';
    $sql = mysqli_query($mysqli, "SELECT history_status, history_description, history_created_at FROM history WHERE {$t['history_fk']} = $id$own ORDER BY history_id DESC LIMIT 200");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** Add to a document's history. $description raw. */
function billingLog($type, $id, $status, $description) {
    $id = intval($id);
    logHistory(escapeSql($status), escapeSql($description), $type === 'invoice' ? $id : 0, $type === 'quote' ? $id : 0, $type === 'recurring' ? $id : 0);
}

/** The company the documents come from - its name, address and how to reach it, raw. */
function billingCompany() {
    global $mysqli;
    static $company = null;
    if ($company === null) {
        $company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_address, company_city, company_state, company_zip, company_country,
            company_phone, company_phone_country_code, company_email, company_website, company_logo, company_tax_id, company_locale, company_currency FROM companies WHERE company_id = 1")) ?: [];
        $company['phone_display'] = !empty($company['company_phone']) ? formatPhoneNumber($company['company_phone'], $company['company_phone_country_code']) : '';
        $logo = basename((string) ($company['company_logo'] ?? ''));
        $company['logo_path'] = $logo !== '' && is_file(dirname(__DIR__) . "/uploads/settings/$logo") ? dirname(__DIR__) . "/uploads/settings/$logo" : '';
        $company['logo_url'] = $company['logo_path'] !== '' ? '/uploads/settings/' . rawurlencode($logo) : '';
    }
    return $company;
}

/**
 * Who a document is for - the client, its primary location and its billing
 * contact (or primary contact), raw.
 */
function billingBillTo($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name, client_tax_id_number, location_address, location_city, location_state, location_zip, location_country
        FROM clients
        LEFT JOIN locations ON location_client_id = client_id AND location_primary = 1 AND location_archived_at IS NULL
        WHERE client_id = $client_id LIMIT 1")) ?: [];
    $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_email, contact_phone, contact_phone_country_code, contact_extension
        FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND (contact_billing = 1 OR contact_primary = 1)
        ORDER BY contact_billing DESC, contact_primary DESC LIMIT 1")) ?: [];
    $row['contact_name'] = $contact['contact_name'] ?? '';
    $row['contact_email'] = $contact['contact_email'] ?? '';
    $row['contact_phone'] = !empty($contact['contact_phone']) ? formatPhoneNumber($contact['contact_phone'], $contact['contact_phone_country_code']) . (!empty($contact['contact_extension']) ? ' x' . $contact['contact_extension'] : '') : '';
    // "Tax exempt - certificate 12-345", printed under the client when nothing on the document is taxed
    $row['tax_exempt_text'] = taxExemptText(taxClient($client_id), false);
    return $row;
}

/** The registration numbers a document's tax is charged under - "GST No. 123456789 RT0001" - for under the company's details. */
function billingTaxRegistrationLines(array $totals) {
    $lines = [];
    foreach ($totals['tax_registrations'] ?? [] as $name => $number) {
        // One number used by two rates is shown once
        if (!isset($lines[$number])) {
            $lines[$number] = "$name No. $number";
        }
    }
    return array_values($lines);
}

/** The guest link for an invoice or a quote. */
function billingGuestUrl($type, $row) {
    global $config_base_url;
    if ($type === 'invoice') {
        return "https://$config_base_url/guest/guest_view_invoice.php?invoice_id=" . intval($row['invoice_id']) . '&url_key=' . rawurlencode((string) $row['invoice_url_key']);
    }
    return "https://$config_base_url/guest/guest_view_quote.php?quote_id=" . intval($row['quote_id']) . '&url_key=' . rawurlencode((string) $row['quote_url_key']);
}

/** How many of a client's contacts the Send window would offer - none means no Send. */
function billingEmailableContacts($type, $client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL
        AND contact_email IS NOT NULL AND contact_email != '' " . documentContactFilterSql($type)))[0]);
}

/* ---------------------------------------------------------------- Invoices */

/** What has been paid against an invoice. */
function invoicePaid($invoice_id) {
    global $mysqli;
    $invoice_id = intval($invoice_id);
    return round(floatval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE payment_invoice_id = $invoice_id"))[0]), 2);
}

/** Invoice statuses still waiting on money. */
function invoiceOpenStatuses() {
    return ['Sent', 'Viewed', 'Partial'];
}

/**
 * Where an invoice stands for payment: open (owed and not late), overdue,
 * draft, paid, cancelled or non-billable - with the balance, and how many
 * days until it is due or since it was.
 */
function invoiceState($row, $paid) {
    $status = (string) $row['invoice_status'];
    $balance = round(floatval($row['invoice_amount']) - floatval($paid), 2);
    $due = strtotime((string) $row['invoice_due']);
    $today = strtotime(date('Y-m-d'));
    $days = $due ? intval(round(($due - $today) / 86400)) : 0;
    $state = [
        'Draft' => 'draft', 'Paid' => 'paid', 'Cancelled' => 'cancelled', 'Non-Billable' => 'nonbillable',
    ][$status] ?? 'open';
    if ($state === 'open' && $balance <= 0) {
        $state = 'paid';
    }
    if ($state === 'open' && $days < 0) {
        $state = 'overdue';
    }
    return ['state' => $state, 'balance' => $balance, 'paid' => round(floatval($paid), 2), 'days' => $days];
}

/** An invoice's status as a badge - its stored status, and Overdue when it is late. */
function invoiceStatusBadge($status, $overdue = false) {
    $status = (string) $status;
    $html = '<span class="badge rounded-pill text-bg-' . getInvoiceBadgeColor($status) . '">' . escapeHtml($status) . '</span>';
    if ($overdue) {
        $html .= ' <span class="badge rounded-pill text-bg-danger">Overdue</span>';
    }
    return $html;
}

/** "Due in 5 days", "Due today", "12 days overdue" - for an invoice still owed. */
function invoiceDuePill($state) {
    if (!in_array($state['state'], ['open', 'overdue'], true)) {
        return '';
    }
    $days = $state['days'];
    if ($days < 0) {
        $late = -$days;
        return '<span class="badge rounded-pill text-bg-danger fw-normal" title="Past its due date"><i class="fas fa-exclamation-circle me-1"></i>' . $late . ' ' . ($late === 1 ? 'day' : 'days') . ' overdue</span>';
    }
    if ($days === 0) {
        return '<span class="badge rounded-pill text-bg-warning fw-normal"><i class="fas fa-clock me-1"></i>Due today</span>';
    }
    $class = $days <= 7 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-secondary-subtle text-secondary-emphasis';
    return '<span class="badge rounded-pill ' . $class . ' fw-normal"><i class="far fa-clock me-1"></i>Due in ' . $days . ' ' . ($days === 1 ? 'day' : 'days') . '</span>';
}

/** May a payment be recorded against this invoice? */
function invoiceCanPay($row, $state) {
    return in_array($row['invoice_status'], invoiceOpenStatuses(), true) && $state['balance'] > 0;
}

/** Does the client have a card on file with an active provider, to charge from the invoice? */
function invoiceHasSavedCard($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    return (bool) mysqli_fetch_row(mysqli_query($mysqli, "SELECT 1 FROM client_saved_payment_methods
        JOIN payment_providers ON saved_payment_provider_id = payment_provider_id
        WHERE saved_payment_client_id = $client_id AND payment_provider_active = 1 LIMIT 1"));
}

/** An invoice's payments, newest first, with the account they went to. */
function invoicePayments($invoice_id) {
    global $mysqli;
    $invoice_id = intval($invoice_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT payment_id, payment_date, payment_amount, payment_currency_code, payment_method, payment_reference, account_name
        FROM payments LEFT JOIN accounts ON account_id = payment_account_id
        WHERE payment_invoice_id = $invoice_id ORDER BY payment_date DESC, payment_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** The tickets billed on an invoice, with the time worked on each. */
function invoiceTickets($invoice_id) {
    global $mysqli;
    $invoice_id = intval($invoice_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_created_at, ticket_closed_at,
            COALESCE(SUM(TIME_TO_SEC(ticket_reply_time_worked)), 0) AS seconds
        FROM tickets LEFT JOIN ticket_replies ON ticket_reply_ticket_id = ticket_id AND ticket_reply_archived_at IS NULL
        WHERE ticket_invoice_id = $invoice_id
        GROUP BY ticket_id ORDER BY ticket_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/* ------------------------------------------------------------ Billing time */

/*
 * Time is billed by the ticket: a billable ticket that is resolved or closed
 * is billed once, with all the time worked on it, onto an invoice line at the
 * client's hourly rate (or the default rate when the client has none). The
 * time is rounded the way Admin > Settings > Invoice says - to a step of
 * minutes, to the nearest step or always up, never under a minimum - and
 * ticket_invoice_id marks it billed. Unbilled Time, the ticket's Invoice
 * window, Add Tickets on an invoice and the Unbilled Work report all count
 * and price it with the functions here.
 */

/**
 * The steps time can be rounded to, in minutes => label - each a whole
 * number of hundredths of an hour, so the hours on a line are exact.
 */
function billingTimeIncrements() {
    return [6 => '6 minutes (a tenth of an hour)', 12 => '12 minutes', 15 => '15 minutes (a quarter hour)', 30 => '30 minutes', 60 => 'An hour'];
}

/**
 * How time is billed: increment (minutes), rounding ('nearest' or 'up'),
 * minimum (minutes a ticket bills at least, once it has any time) and
 * item_name (the line's name). Read once a request; until the database has
 * the settings (2.9.2) it is the old way - the nearest 15 minutes.
 */
function billingTimeRules($fresh = false) {
    global $mysqli;
    static $rules = null;
    if ($rules !== null && !$fresh) {
        return $rules;
    }
    $rules = ['increment' => 15, 'rounding' => 'nearest', 'minimum' => 0, 'item_name' => 'Support [Hourly]'];
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_time_increment, config_time_rounding, config_time_minimum, config_time_item_name FROM settings WHERE company_id = 1"));
    } catch (mysqli_sql_exception $e) {
        $row = null;
    }
    if ($row) {
        $increment = intval($row['config_time_increment']);
        $rules['increment'] = isset(billingTimeIncrements()[$increment]) ? $increment : 15;
        $rules['rounding'] = $row['config_time_rounding'] === 'up' ? 'up' : 'nearest';
        $rules['minimum'] = max(0, min(1440, intval($row['config_time_minimum'])));
        $rules['item_name'] = trim((string) $row['config_time_item_name']) !== '' ? (string) $row['config_time_item_name'] : 'Support [Hourly]';
    }
    return $rules;
}

/** The rules in words - "to the nearest 15 minutes", "up to the next 6 minutes, at least 1 hour". */
function billingTimeRulesText(?array $rules = null) {
    $rules = $rules ?? billingTimeRules();
    $step = $rules['increment'] === 60 ? 'hour' : $rules['increment'] . ' minutes';
    $text = $rules['rounding'] === 'up' ? "up to the next $step" : "to the nearest $step";
    if ($rules['minimum'] > 0) {
        $text .= ', at least ' . billingDuration($rules['minimum'] * 60);
    }
    return $text;
}

/**
 * The hours to bill for some seconds of work: rounded to the step, then
 * never under the minimum. No time logged bills nothing.
 */
function billingTimeHours($seconds, ?array $rules = null) {
    $rules = $rules ?? billingTimeRules();
    $seconds = max(0, intval($seconds));
    if ($seconds === 0) {
        return 0.0;
    }
    $step = $rules['increment'] * 60;
    $steps = $rules['rounding'] === 'up' ? intdiv($seconds + $step - 1, $step) : intdiv($seconds, $step) + ($seconds % $step > $step / 2 ? 1 : 0);
    return round(max($steps * $step, $rules['minimum'] * 60) / 3600, 2);
}

/** The hourly rate a client's time is billed at - its own, or the default. */
function billingTimeRate($client_rate) {
    global $config_default_hourly_rate;
    return floatval($client_rate) > 0 ? floatval($client_rate) : floatval($config_default_hourly_rate);
}

/**
 * The SQL that makes a ticket unbilled: billable, resolved or closed, not
 * archived, on no invoice. $include_open takes open ones too - the ticket's
 * own Invoice window can bill a ticket before it is done.
 */
function billingUnbilledSql($include_open = false) {
    return "ticket_billable = 1 AND ticket_invoice_id = 0 AND ticket_archived_at IS NULL AND ticket_client_id > 0"
        . ($include_open ? '' : " AND ticket_status IN (" . TICKET_STATUS_RESOLVED . ", " . TICKET_STATUS_CLOSED . ")");
}

/**
 * Unbilled tickets - one client's ($client_id), or every client the agent
 * can open (0), optionally only $ticket_ids - longest waiting first. Each
 * with its client, its assignee, the seconds worked (archived replies left
 * out), the hours that bills, the rate and what that comes to, and the days
 * since it was done.
 */
function billingUnbilledTickets($client_id = 0, array $ticket_ids = [], $include_open = false) {
    global $mysqli;
    $client_id = intval($client_id);
    $ids = array_filter(array_map('intval', $ticket_ids));
    $where = billingUnbilledSql($include_open && $ids) . ($client_id ? " AND ticket_client_id = $client_id" : '') . ($ids ? ' AND ticket_id IN (' . implode(',', $ids) . ')' : '')
        . ' ' . clientScopeSql('ticket_client_id');
    $rules = billingTimeRules();
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_status, ticket_onsite, ticket_created_at, ticket_resolved_at, ticket_closed_at,
            ticket_client_id, ticket_assigned_to, client_name, client_abbreviation, client_rate, user_name, user_avatar,
            COALESCE((SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies WHERE ticket_reply_ticket_id = ticket_id AND ticket_reply_archived_at IS NULL), 0) AS seconds,
            DATEDIFF(CURDATE(), COALESCE(ticket_resolved_at, ticket_closed_at, ticket_created_at)) AS waiting
        FROM tickets
        JOIN clients ON client_id = ticket_client_id
        LEFT JOIN users ON user_id = ticket_assigned_to
        WHERE $where
        ORDER BY client_name ASC, waiting DESC, ticket_number ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['seconds'] = intval($row['seconds']);
        $row['waiting'] = max(0, intval($row['waiting']));
        $row['hours'] = billingTimeHours($row['seconds'], $rules);
        $row['rate'] = billingTimeRate($row['client_rate']);
        $row['default_rate'] = floatval($row['client_rate']) <= 0;
        $row['value'] = round($row['hours'] * $row['rate'], 2);
        $rows[intval($row['ticket_id'])] = $row;
    }
    return $rows;
}

/**
 * The same, by client - each client with its tickets, time, hours, value,
 * how long the oldest has waited, how many have no time logged, and its
 * draft invoices. Biggest value first.
 */
function billingUnbilledByClient($client_id = 0) {
    global $mysqli;
    $clients = [];
    foreach (billingUnbilledTickets($client_id) as $ticket) {
        $id = intval($ticket['ticket_client_id']);
        if (!isset($clients[$id])) {
            $clients[$id] = ['client_id' => $id, 'client_name' => $ticket['client_name'], 'client_abbreviation' => $ticket['client_abbreviation'],
                'rate' => $ticket['rate'], 'default_rate' => $ticket['default_rate'], 'tickets' => [], 'seconds' => 0, 'hours' => 0.0, 'value' => 0.0,
                'oldest' => 0, 'no_time' => 0, 'drafts' => []];
        }
        $clients[$id]['tickets'][intval($ticket['ticket_id'])] = $ticket;
        $clients[$id]['seconds'] += $ticket['seconds'];
        $clients[$id]['hours'] += $ticket['hours'];
        $clients[$id]['value'] += $ticket['value'];
        $clients[$id]['oldest'] = max($clients[$id]['oldest'], $ticket['waiting']);
        $clients[$id]['no_time'] += $ticket['seconds'] === 0 ? 1 : 0;
    }
    if ($clients) {
        $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_date, invoice_amount, invoice_client_id FROM invoices
            WHERE invoice_status = 'Draft' AND invoice_archived_at IS NULL AND invoice_client_id IN (" . implode(',', array_keys($clients)) . ")
            ORDER BY invoice_date DESC, invoice_id DESC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $clients[intval($row['invoice_client_id'])]['drafts'][intval($row['invoice_id'])] = $row;
        }
    }
    uasort($clients, function ($a, $b) {
        return $b['value'] <=> $a['value'] ?: count($b['tickets']) <=> count($a['tickets']);
    });
    return $clients;
}

/** How many unbilled tickets the agent can see - for the menu. */
function billingUnbilledCount() {
    global $mysqli;
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tickets WHERE " . billingUnbilledSql() . ' ' . clientScopeSql('ticket_client_id')))[0]);
}

/** A ticket's invoice line description - "Ticket TCK-123 - Subject - 10/14/2026". */
function billingTimeDescription(array $ticket) {
    return 'Ticket ' . $ticket['ticket_prefix'] . $ticket['ticket_number'] . ' - ' . $ticket['ticket_subject'] . ' - ' . date('m/d/Y', strtotime((string) $ticket['ticket_created_at']));
}

/**
 * A new draft invoice for a client, as New Invoice makes one: the next
 * number, due on the client's terms, in the company's currency. Its id.
 */
function billingInvoiceCreate($client_id, $date, $category_id, $scope, $by) {
    global $mysqli, $config_invoice_prefix, $session_company_currency;
    $client_id = intval($client_id);
    $date = ($parsed = DateTime::createFromFormat('!Y-m-d', (string) $date)) && $parsed->format('Y-m-d') === (string) $date ? (string) $date : date('Y-m-d');
    $terms = intval(getFieldById('clients', $client_id, 'client_net_terms'));
    $number = billingNextNumber('invoice');
    $prefix = escapeSql($config_invoice_prefix);
    $scope = escapeSql(mb_substr(trim((string) $scope), 0, 255));
    $currency = escapeSql($session_company_currency);
    $category_id = intval($category_id);
    $url_key = randomString(32);
    mysqli_query($mysqli, "INSERT INTO invoices SET invoice_prefix = '$prefix', invoice_number = $number, invoice_scope = '$scope', invoice_date = '$date',
        invoice_due = DATE_ADD('$date', INTERVAL $terms DAY), invoice_amount = 0, invoice_currency_code = '$currency', invoice_category_id = $category_id,
        invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_client_id = $client_id");
    $invoice_id = intval(mysqli_insert_id($mysqli));
    if ($invoice_id) {
        billingLog('invoice', $invoice_id, 'Draft', "Invoice created by $by");
    }
    return $invoice_id;
}

/**
 * Bill tickets' time on an invoice. $hours is ticket id => hours to bill
 * (null: what the rules make of the time logged). Only the client's
 * unbilled tickets are taken, and each is claimed on the way so two agents
 * can't bill one twice. $opts:
 *   invoice_id   one of the client's invoices whose lines can change, or 0 for a new draft
 *   date, category_id, scope   for a new draft
 *   tax_id (TAX_USUAL: the client's usual rate for time), item_name, price (null: the client's rate), description (one ticket only)
 *   combine      one line for all of them instead of one each
 *   lines        false to put the tickets on without adding lines (billed by hand)
 *   include_open take tickets that aren't done yet too
 *   by           who, for the history
 * A ticket with no hours goes on the invoice without a line. Returns
 * [invoice_id, number, new, tickets (references), hours, lines, no_charge]
 * or null with $error.
 */
function billingTimeInvoice($client_id, array $hours, array $opts, &$error = '') {
    global $mysqli;
    $error = '';
    $client_id = intval($client_id);
    $by = (string) ($opts['by'] ?? 'ITFlow');
    $tickets = $hours ? billingUnbilledTickets($client_id, array_keys($hours), !empty($opts['include_open'])) : [];
    $add_lines = !isset($opts['lines']) || $opts['lines'];
    if (!$client_id || !$tickets) {
        $error = 'Nothing to bill - those tickets are already on an invoice, or no longer billable.';
        return null;
    }

    // What each one bills
    $bill = [];
    foreach ($tickets as $ticket_id => $ticket) {
        $value = $hours[$ticket_id] ?? null;
        $h = ($value === null || $value === '') ? $ticket['hours'] : billingNumberInput($value);
        if ($h === null || $h < 0 || $h >= 10000) {
            $error = 'The hours for ' . $ticket['ticket_prefix'] . $ticket['ticket_number'] . ' have to be a number, zero or more.';
            return null;
        }
        $bill[$ticket_id] = round($h, 2);
    }
    $price = isset($opts['price']) && $opts['price'] !== '' && $opts['price'] !== null ? billingNumberInput($opts['price']) : billingTimeRate(reset($tickets)['client_rate']);
    if ($price === null || abs($price) >= 1e9) {
        $error = 'The rate has to be a number.';
        return null;
    }
    $tax_id = taxChoice($opts['tax_id'] ?? 0, $client_id, 'time');
    if ($tax_id && !taxRow($tax_id)) {
        $error = 'That tax no longer exists.';
        return null;
    }
    $item_name = trim((string) ($opts['item_name'] ?? ''));
    $item_name = $item_name !== '' ? $item_name : billingTimeRules()['item_name'];
    if (mb_strlen($item_name) > 200) {
        $error = 'That line name is too long - keep it under 200 characters.';
        return null;
    }

    // The invoice
    $invoice_id = intval($opts['invoice_id'] ?? 0);
    $new = false;
    if ($invoice_id) {
        $invoice = billingRow('invoice', $invoice_id);
        if (!$invoice || intval($invoice['client_id']) !== $client_id || !billingCanEditItems('invoice', $invoice)) {
            $error = "That invoice isn't one of the client's you can still add to.";
            return null;
        }
    } else {
        $invoice_id = billingInvoiceCreate($client_id, $opts['date'] ?? '', $opts['category_id'] ?? 0, $opts['scope'] ?? '', $by);
        if (!$invoice_id) {
            $error = "The invoice couldn't be made.";
            return null;
        }
        $new = true;
    }
    $number = billingNumber('invoice', billingRow('invoice', $invoice_id, false));

    // Claim them - only those still unbilled go on
    mysqli_query($mysqli, "UPDATE tickets SET ticket_invoice_id = $invoice_id WHERE ticket_id IN (" . implode(',', array_keys($bill)) . ") AND ticket_client_id = $client_id
        AND ticket_invoice_id = 0 AND ticket_billable = 1");
    $claimed = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_invoice_id = $invoice_id AND ticket_id IN (" . implode(',', array_keys($bill)) . ")");
    while ($row = mysqli_fetch_row($sql)) {
        $claimed[intval($row[0])] = true;
    }
    if (!$claimed) {
        if ($new) {
            mysqli_query($mysqli, "DELETE FROM history WHERE history_invoice_id = $invoice_id");
            mysqli_query($mysqli, "DELETE FROM invoices WHERE invoice_id = $invoice_id");
        }
        $error = 'Nothing to bill - someone else billed those tickets just now.';
        return null;
    }

    // The lines
    $lines = 0;
    $no_charge = [];
    $references = [];
    $total_hours = 0.0;
    $combined = [];
    foreach ($bill as $ticket_id => $h) {
        if (!isset($claimed[$ticket_id])) {
            continue;
        }
        $ticket = $tickets[$ticket_id];
        $reference = $ticket['ticket_prefix'] . $ticket['ticket_number'];
        $references[] = $reference;
        $total_hours += $h;
        if (!$add_lines) {
            logTicketHistory($ticket_id, escapeSql("Put on invoice $number by $by - no line added"));
            continue;
        }
        if ($h <= 0) {
            $no_charge[] = $reference;
        } elseif (!empty($opts['combine'])) {
            $combined[] = billingTimeDescription($ticket) . ' (' . billingQuantity($h) . ' h)';
        } else {
            $description = count($bill) === 1 && isset($opts['description']) ? (string) $opts['description'] : billingTimeDescription($ticket);
            if (billingItemSave('invoice', $invoice_id, ['name' => $item_name, 'description' => $description, 'qty' => $h, 'price' => $price, 'tax_id' => $tax_id])) {
                $lines++;
            }
        }
        logTicketHistory($ticket_id, escapeSql("Billed on invoice $number by $by - " . ($h > 0 ? billingQuantity($h) . ' h' : 'no charge')));
    }
    if ($combined) {
        $combined_hours = round(array_sum(array_intersect_key($bill, $claimed)), 2);
        if (billingItemSave('invoice', $invoice_id, ['name' => $item_name, 'description' => implode("\n", $combined), 'qty' => $combined_hours, 'price' => $price, 'tax_id' => $tax_id])) {
            $lines++;
        }
    }
    billingRecalc('invoice', $invoice_id);

    $invoice = billingRow('invoice', $invoice_id, false);
    $what = (count($references) === 1 ? 'ticket ' : count($references) . ' tickets ') . implode(', ', array_slice($references, 0, 20)) . (count($references) > 20 ? ' and more' : '');
    if ($add_lines) {
        billingLog('invoice', $invoice_id, $invoice['invoice_status'], ucfirst($what) . " billed by $by - " . billingQuantity($total_hours) . ' h');
        logAudit('Invoice', $new ? 'Create' : 'Edit', escapeSql("$by billed $what on invoice $number - " . billingQuantity($total_hours) . ' h'), $client_id, $invoice_id);
    } else {
        billingLog('invoice', $invoice_id, $invoice['invoice_status'], ucfirst($what) . " added by $by, without lines");
        logAudit('Invoice', 'Edit', escapeSql("$by added $what to invoice $number, without lines"), $client_id, $invoice_id);
    }
    if ($new) {
        triggerCustomAction('invoice_create', $invoice_id);
    }

    return ['invoice_id' => $invoice_id, 'number' => $number, 'new' => $new, 'tickets' => $references, 'hours' => round($total_hours, 2), 'lines' => $lines, 'no_charge' => $no_charge];
}

/**
 * Mark unbilled tickets not billable - written off, so they leave Unbilled
 * Time. Each gets it in its history. The references it changed.
 */
function billingTimeWriteOff($client_id, array $ticket_ids, $by) {
    global $mysqli;
    $tickets = $ticket_ids ? billingUnbilledTickets($client_id, $ticket_ids) : [];
    $done = [];
    foreach ($tickets as $ticket_id => $ticket) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_billable = 0 WHERE ticket_id = $ticket_id AND ticket_invoice_id = 0 AND ticket_billable = 1");
        if (mysqli_affected_rows($mysqli) === 1) {
            logTicketHistory($ticket_id, escapeSql("Marked not billable by $by - " . billingDuration($ticket['seconds']) . ' logged was not billed'));
            $done[] = $ticket['ticket_prefix'] . $ticket['ticket_number'];
        }
    }
    return $done;
}

/** Seconds as "1h 30m". */
function billingDuration($seconds) {
    $seconds = intval($seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    if (!$hours && !$minutes) {
        return $seconds ? '<1m' : '-';
    }
    return trim(($hours ? "{$hours}h " : '') . ($minutes ? "{$minutes}m" : ''));
}

/* ------------------------------------------------------------------ Quotes */

/** A quote's badge colour by status. */
function getQuoteBadgeColor($status) {
    return ['Sent' => 'warning', 'Viewed' => 'primary', 'Accepted' => 'success', 'Declined' => 'danger', 'Invoiced' => 'info'][(string) $status] ?? 'secondary';
}

/** Has a quote passed its expiry without an answer? */
function quoteExpired($row) {
    return in_array($row['quote_status'], ['Draft', 'Sent', 'Viewed'], true)
        && !empty($row['quote_expire']) && strtotime((string) $row['quote_expire']) < strtotime(date('Y-m-d'));
}

/** A quote's status as a badge, and Expired when it ran out unanswered. */
function quoteStatusBadge($row) {
    $html = '<span class="badge rounded-pill text-bg-' . getQuoteBadgeColor($row['quote_status']) . '">' . escapeHtml($row['quote_status']) . '</span>';
    if (quoteExpired($row)) {
        $html .= ' <span class="badge rounded-pill text-bg-dark">Expired</span>';
    }
    return $html;
}

/** "Expires in 5 days", "Expired 3 days ago" - for a quote not answered yet. */
function quoteExpiryPill($row) {
    if (!in_array($row['quote_status'], ['Draft', 'Sent', 'Viewed'], true) || empty($row['quote_expire'])) {
        return '';
    }
    $days = intval(round((strtotime((string) $row['quote_expire']) - strtotime(date('Y-m-d'))) / 86400));
    if ($days < 0) {
        return '<span class="badge rounded-pill bg-dark-subtle text-dark-emphasis fw-normal">Expired ' . (-$days) . ' ' . (-$days === 1 ? 'day' : 'days') . ' ago</span>';
    }
    if ($days === 0) {
        return '<span class="badge rounded-pill text-bg-warning fw-normal">Expires today</span>';
    }
    $class = $days <= 7 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-secondary-subtle text-secondary-emphasis';
    return '<span class="badge rounded-pill ' . $class . ' fw-normal">Expires in ' . $days . ' ' . ($days === 1 ? 'day' : 'days') . '</span>';
}

/*
 * A quote line is billed once, or each month, quarter or year
 * (item_frequency). The one-time lines go on an invoice when the quote is
 * invoiced, the others on a recurring invoice for each - quoteConvert().
 * quote_amount is what is billed once; quote_monthly_amount what the
 * recurring lines come to a month, for the lists and reports.
 */

/** How often a quote line is billed => label. '' is once. */
function quoteFrequencies() {
    return ['' => 'One time', 'month' => 'Monthly', 'quarter' => 'Quarterly', 'year' => 'Yearly'];
}

/** A line's frequency, cleaned - '' for once. */
function quoteFrequency($value) {
    $value = (string) $value;
    return in_array($value, ['month', 'quarter', 'year'], true) ? $value : '';
}

/**
 * A quote's lines by how often they are billed - one-time first, then each
 * month, quarter and year - each with its lines, subtotal, taxes, discount
 * and total. The discount comes off the one-time part; on a quote with
 * nothing one-time, off its first recurring part (and onto that recurring
 * invoice). Always has at least the one-time group.
 */
function quoteLineGroups(array $items, $discount = 0) {
    $labels = ['' => 'One-time', 'month' => 'Each month', 'quarter' => 'Each quarter', 'year' => 'Each year'];
    $blank = function ($frequency) use ($labels) {
        return ['frequency' => $frequency, 'label' => $labels[$frequency], 'items' => [], 'subtotal' => 0.0, 'tax' => 0.0, 'taxes' => [], 'discount' => 0.0, 'total' => 0.0];
    };
    $groups = [];
    foreach ($items as $item) {
        $frequency = quoteFrequency($item['item_frequency'] ?? '');
        $groups[$frequency] = $groups[$frequency] ?? $blank($frequency);
        $groups[$frequency]['items'][] = $item;
        $groups[$frequency]['subtotal'] += floatval($item['item_subtotal']);
        $groups[$frequency]['tax'] += floatval($item['item_tax']);
        $groups[$frequency]['total'] += floatval($item['item_total']);
    }
    foreach ($groups as $frequency => $group) {
        $groups[$frequency]['taxes'] = billingTaxParts($group['items'])['parts'];
    }
    $ordered = [];
    foreach (array_keys($labels) as $frequency) {
        if (isset($groups[$frequency])) {
            $ordered[$frequency] = $groups[$frequency];
        }
    }
    if (!$ordered) {
        $ordered[''] = $blank('');
    }
    $on = isset($ordered['']) ? '' : array_key_first($ordered);
    $ordered[$on]['discount'] = floatval($discount);
    foreach ($ordered as $frequency => $group) {
        $ordered[$frequency]['subtotal'] = round($group['subtotal'], 2);
        $ordered[$frequency]['tax'] = round($group['tax'], 2);
        $ordered[$frequency]['total'] = round($group['total'] - $group['discount'], 2);
    }
    return $ordered;
}

/** Whether a quote has recurring lines - from quoteLineGroups(). */
function quoteHasRecurring(array $groups) {
    return (bool) array_diff_key($groups, ['' => true]);
}

/** What the recurring groups come to a month - a quarterly one a third, a yearly one a twelfth. */
function quoteMonthly(array $groups) {
    $monthly = 0.0;
    foreach ($groups as $frequency => $group) {
        if ($frequency !== '') {
            $monthly += recurringInvoiceMonthly($group['total'], $frequency);
        }
    }
    return round($monthly, 2);
}

/**
 * What a quote comes to, in words - "$1,500.00", "$1,500.00 up front and
 * $2,400.00 a month", "$2,400.00 a month".
 */
function quoteAmountText(array $groups, $currency) {
    $parts = [];
    foreach ($groups as $frequency => $group) {
        if ($frequency === '') {
            if ($group['items'] || count($groups) === 1) {
                $parts[] = billingMoney($group['total'], $currency) . (count($groups) > 1 ? ' up front' : '');
            }
        } else {
            $parts[] = billingMoney($group['total'], $currency) . ' a ' . recurringInvoicePeriodWord($frequency);
        }
    }
    return count($parts) > 1 ? implode(', ', array_slice($parts, 0, -1)) . ' and ' . end($parts) : (string) reset($parts);
}

/** A quote's groups, from the database - for the pages that only have its row. */
function quoteGroups(array $quote) {
    return quoteLineGroups(billingItems('quote', intval($quote['quote_id'])), $quote['quote_discount_amount'] ?? 0);
}

/**
 * Put lines on a document after the ones already there - their stored
 * amounts as they are, linked to the same products. $suffix goes on the end
 * of each description. On an invoice, tracked products come off the shelf;
 * check productStockShortfall() first.
 */
function billingCopyLines(array $items, $to_type, $to_id, $suffix = '') {
    global $mysqli;
    $t = billingType($to_type);
    $to_id = intval($to_id);
    $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(item_order), 0) FROM {$t['items']} WHERE {$t['item_fk']} = $to_id"))[0]);
    $number = $to_type === 'invoice' ? billingNumber('invoice', billingRow('invoice', $to_id, false)) : '';
    foreach ($items as $item) {
        $order++;
        $name = escapeSql($item['item_name']);
        $description = escapeSql(trim(trim((string) $item['item_description']) . ($suffix !== '' ? "\n$suffix" : '')));
        $product_id = intval($item['item_product_id'] ?? 0);
        if ($product_id && !getFieldById('products', $product_id, 'product_id')) {
            $product_id = 0;
        }
        mysqli_query($mysqli, "INSERT INTO {$t['items']} SET item_name = '$name', item_description = '$description', item_quantity = " . floatval($item['item_quantity']) . ",
            item_price = " . floatval($item['item_price']) . ", item_subtotal = " . floatval($item['item_subtotal']) . ", item_tax = " . floatval($item['item_tax']) . ",
            item_total = " . floatval($item['item_total']) . ", item_order = $order, item_tax_id = " . intval($item['item_tax_id']) . ", item_product_id = $product_id, {$t['item_fk']} = $to_id");
        $new_item_id = intval(mysqli_insert_id($mysqli));
        // On an invoice, a tracked product comes off the shelf - check first with productStockShortfall()
        if ($to_type === 'invoice' && $product_id && floatval($item['item_quantity']) > 0 && intval(getFieldById('products', $product_id, 'product_track_stock')) === 1) {
            productStockMove($product_id, -floatval($item['item_quantity']), 'sold', ['invoice_id' => $to_id, 'item_id' => $new_item_id, 'note' => $number]);
        }
    }
}

/** The day recurring billing starts by default - the first of next month. */
function quoteRecurringStart() {
    return date('Y-m-01', strtotime('first day of next month'));
}

/**
 * Invoice an accepted quote. Its one-time lines go on a new draft invoice;
 * its lines billed each month, quarter or year go on a recurring invoice for
 * each - a new one, or added to one of the client's of that frequency. $opts:
 *   date          the invoice's date
 *   first_period  put the recurring lines on the invoice too - the first period billed now
 *   recurring     frequency => [to (0 for new, or a recurring invoice id), start (its first
 *                 invoice date, new ones), auto_send (1 emails them, 0 leaves drafts)]
 *   by            who, for the history
 * The quote is claimed on the way (Accepted to Invoiced), so it is invoiced
 * once. Returns [invoice_id (0 when nothing one-time), recurring (id =>
 * number), numbers] or null with $error.
 */
function quoteConvert($quote_id, array $opts, &$error = '') {
    global $mysqli, $config_invoice_prefix, $config_recurring_invoice_prefix;
    $error = '';
    $quote = billingRow('quote', $quote_id);
    if (!$quote) {
        $error = 'That quote no longer exists.';
        return null;
    }
    $quote_id = intval($quote['quote_id']);
    $client_id = intval($quote['client_id']);
    $number = billingNumber('quote', $quote);
    if ($quote['quote_status'] !== 'Accepted') {
        $error = $quote['quote_status'] === 'Invoiced' ? "$number is already invoiced." : "$number has to be accepted before it is invoiced.";
        return null;
    }
    $by = (string) ($opts['by'] ?? 'ITFlow');
    $groups = quoteLineGroups(billingItems('quote', $quote_id), $quote['quote_discount_amount']);
    $valid_date = function ($date) {
        $date = (string) $date;
        return ($parsed = DateTime::createFromFormat('!Y-m-d', $date)) && $parsed->format('Y-m-d') === $date ? $date : '';
    };
    $date = $valid_date($opts['date'] ?? '') ?: date('Y-m-d');
    $first_period = !empty($opts['first_period']);
    if (!$groups['']['items'] && !quoteHasRecurring($groups) && floatval($groups['']['discount'] ?? 0) == 0) {
        $error = "$number has nothing on it to invoice.";
        return null;
    }

    // Check every recurring part's choice before anything is made
    $plans = [];
    foreach ($groups as $frequency => $group) {
        if ($frequency === '') {
            continue;
        }
        $choice = (array) ($opts['recurring'][$frequency] ?? []);
        $to = intval($choice['to'] ?? 0);
        if ($to) {
            $existing = billingRow('recurring', $to);
            if (!$existing || intval($existing['client_id']) !== $client_id || $existing['recurring_invoice_frequency'] !== $frequency || $existing['recurring_invoice_archived_at']) {
                $error = 'That recurring invoice is not one of ' . $quote['client_name'] . "'s billed " . strtolower(recurringInvoiceFrequencies()[$frequency]) . '.';
                return null;
            }
            $plans[$frequency] = ['to' => $to, 'row' => $existing];
        } else {
            $start = $valid_date($choice['start'] ?? '') ?: quoteRecurringStart();
            $plans[$frequency] = ['to' => 0, 'start' => $start, 'auto_send' => intval($choice['auto_send'] ?? 1) ? 1 : 0];
        }
    }

    // What goes on the invoice has to be on the shelf, for products that track stock
    $invoice_lines = $groups['']['items'] ?? [];
    if ($first_period) {
        foreach ($groups as $frequency => $group) {
            if ($frequency !== '') {
                $invoice_lines = array_merge($invoice_lines, $group['items']);
            }
        }
    }
    $shortfall = productStockShortfall($invoice_lines);
    if ($shortfall !== '') {
        $error = "$shortfall Add stock before invoicing $number.";
        return null;
    }

    mysqli_begin_transaction($mysqli);
    try {
        // Claim it
        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Invoiced' WHERE quote_id = $quote_id AND quote_status = 'Accepted'");
        if (mysqli_affected_rows($mysqli) !== 1) {
            mysqli_rollback($mysqli);
            $error = "$number was invoiced just now.";
            return null;
        }
        $currency = escapeSql($quote['quote_currency_code']);
        $category_id = intval($quote['quote_category_id']);
        $scope = escapeSql($quote['quote_scope']);
        $note = escapeSql($quote['quote_note']);
        $numbers = [];

        // The invoice - what is billed once, and the first period when asked
        $invoice_id = 0;
        $one_time = $groups['']['items'] ?? [];
        $invoice_discount = isset($groups['']) ? floatval($groups['']['discount']) : 0.0;
        if ($one_time || ($first_period && quoteHasRecurring($groups)) || $invoice_discount != 0) {
            $terms = intval($quote['client_net_terms']);
            $invoice_number = billingNextNumber('invoice');
            $prefix = escapeSql($config_invoice_prefix);
            $url_key = randomString(32);
            mysqli_query($mysqli, "INSERT INTO invoices SET invoice_prefix = '$prefix', invoice_number = $invoice_number, invoice_scope = '$scope', invoice_date = '$date',
                invoice_due = DATE_ADD('$date', INTERVAL $terms DAY), invoice_discount_amount = $invoice_discount, invoice_amount = 0, invoice_currency_code = '$currency',
                invoice_note = '$note', invoice_category_id = $category_id, invoice_status = 'Draft', invoice_url_key = '$url_key', invoice_quote_id = $quote_id, invoice_client_id = $client_id");
            $invoice_id = intval(mysqli_insert_id($mysqli));
            billingCopyLines($one_time, 'invoice', $invoice_id);
            if ($first_period) {
                foreach ($groups as $frequency => $group) {
                    if ($frequency !== '') {
                        billingCopyLines($group['items'], 'invoice', $invoice_id, 'First ' . recurringInvoicePeriodWord($frequency));
                    }
                }
            }
            billingRecalc('invoice', $invoice_id);
            $invoice_ref = billingNumber('invoice', billingRow('invoice', $invoice_id, false));
            $numbers[] = $invoice_ref;
            billingLog('invoice', $invoice_id, 'Draft', "Made from quote $number by $by");
            logAudit('Invoice', 'Create', escapeSql("$by made invoice $invoice_ref from quote $number"), $client_id, $invoice_id);

            // The ticket the quote was raised from is billed with it
            mysqli_query($mysqli, "UPDATE tickets SET ticket_invoice_id = $invoice_id WHERE ticket_quote_id = $quote_id AND ticket_invoice_id = 0");
        }

        // The recurring invoices
        $recurring = [];
        foreach ($plans as $frequency => $plan) {
            $group = $groups[$frequency];
            if ($plan['to']) {
                $recurring_id = $plan['to'];
                billingCopyLines($group['items'], 'recurring', $recurring_id);
                if ($group['discount'] != 0) {
                    mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_discount_amount = recurring_invoice_discount_amount + " . floatval($group['discount']) . " WHERE recurring_invoice_id = $recurring_id");
                }
                billingRecalc('recurring', $recurring_id);
                $ref = billingNumber('recurring', $plan['row']);
                // On the recurring invoice's history, and carrying the quote's id too - that is
                // how the quote finds a recurring invoice it wasn't made into (quoteMadeFrom)
                logHistory(escapeSql(intval($plan['row']['recurring_invoice_status']) ? 'Active' : 'Paused'), escapeSql(count($group['items']) . ' line' . (count($group['items']) === 1 ? '' : 's') . " from quote $number added by $by"), 0, $quote_id, $recurring_id);
                logAudit('Recurring Invoice', 'Edit', escapeSql("$by added the lines of quote $number to recurring invoice $ref"), $client_id, $recurring_id);
            } else {
                $recurring_number = billingNextNumber('recurring');
                $prefix = escapeSql($config_recurring_invoice_prefix);
                $start = $plan['start'];
                $discount = floatval($group['discount']);
                mysqli_query($mysqli, "INSERT INTO recurring_invoices SET recurring_invoice_prefix = '$prefix', recurring_invoice_number = $recurring_number, recurring_invoice_scope = '$scope',
                    recurring_invoice_frequency = '$frequency', recurring_invoice_next_date = '$start', recurring_invoice_anchor_day = DAY('$start'), recurring_invoice_status = 1,
                    recurring_invoice_discount_amount = $discount, recurring_invoice_amount = 0, recurring_invoice_currency_code = '$currency', recurring_invoice_note = '$note',
                    recurring_invoice_auto_send = {$plan['auto_send']}, recurring_invoice_email_notify = 1, recurring_invoice_category_id = $category_id,
                    recurring_invoice_quote_id = $quote_id, recurring_invoice_client_id = $client_id");
                $recurring_id = intval(mysqli_insert_id($mysqli));
                billingCopyLines($group['items'], 'recurring', $recurring_id);
                billingRecalc('recurring', $recurring_id);
                $ref = billingNumber('recurring', billingRow('recurring', $recurring_id, false));
                billingLog('recurring', $recurring_id, 'Active', "Made from quote $number by $by - first invoice on $start");
                logAudit('Recurring Invoice', 'Create', escapeSql("$by made recurring invoice $ref from quote $number"), $client_id, $recurring_id);
            }
            $recurring[$recurring_id] = $ref;
            $numbers[] = $ref;
        }

        billingLog('quote', $quote_id, 'Invoiced', 'Invoiced as ' . implode(', ', $numbers) . " by $by");
        logAudit('Quote', 'Edit', escapeSql("$by invoiced quote $number as " . implode(', ', $numbers)), $client_id, $quote_id);
        mysqli_commit($mysqli);
    } catch (Throwable $e) {
        mysqli_rollback($mysqli);
        logApp('Quote', 'error', "Invoicing quote $number failed: " . $e->getMessage());
        $error = "$number couldn't be invoiced - nothing was changed.";
        return null;
    }

    if ($invoice_id) {
        triggerCustomAction('invoice_create', $invoice_id);
    }
    return ['invoice_id' => $invoice_id, 'recurring' => $recurring, 'numbers' => $numbers];
}

/**
 * What a quote became - the invoices and recurring invoices made from it, and
 * the recurring invoices its lines were added to ('added' 1).
 */
function quoteMadeFrom($quote_id) {
    global $mysqli;
    $quote_id = intval($quote_id);
    $made = [];
    $sql = mysqli_query($mysqli, "SELECT 'invoice' AS type, invoice_id AS id, invoice_prefix AS prefix, invoice_number AS number, invoice_amount AS amount, invoice_status AS status, invoice_currency_code AS currency, '' AS frequency, 0 AS added
        FROM invoices WHERE invoice_quote_id = $quote_id
        UNION ALL
        SELECT 'recurring', recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_amount, IF(recurring_invoice_status = 1, 'Active', 'Paused'), recurring_invoice_currency_code, recurring_invoice_frequency,
            IF(recurring_invoice_quote_id = $quote_id, 0, 1)
        FROM recurring_invoices WHERE recurring_invoice_quote_id = $quote_id
            OR recurring_invoice_id IN (SELECT history_recurring_invoice_id FROM history WHERE history_quote_id = $quote_id AND history_recurring_invoice_id > 0)");
    while ($row = mysqli_fetch_assoc($sql)) {
        $made[] = $row;
    }
    return $made;
}

/**
 * A client's answer to a quote, from its guest link or the client portal:
 * accepted only while it waits for one (Sent or Viewed) and has not run
 * out, declined only while it waits - the first answer through wins. Then
 * it goes in the quote's history, the agents are told, the quote_accept or
 * quote_decline custom action runs and the quote notification address
 * hears. $by, for the portal: name (who answered), note (what they said).
 * ['ok' => bool, 'error' => why not, for the client].
 */
function billingQuoteAnswer($quote_id, $accept, array $by = []) {
    global $mysqli;
    $quote_id = intval($quote_id);
    $quote = billingRow('quote', $quote_id, false);
    if (!$quote) {
        return ['ok' => false, 'error' => 'This quote is no longer awaiting a response'];
    }
    if ($accept) {
        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Accepted' WHERE quote_id = $quote_id AND quote_status IN ('Sent', 'Viewed') AND quote_expire >= CURDATE()");
    } else {
        mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Declined' WHERE quote_id = $quote_id AND quote_status IN ('Sent', 'Viewed')");
    }
    if (mysqli_affected_rows($mysqli) !== 1) {
        $expired = $accept ? mysqli_fetch_row(mysqli_query($mysqli, "SELECT quote_expire FROM quotes WHERE quote_id = $quote_id AND quote_status IN ('Sent', 'Viewed') AND quote_expire < CURDATE()")) : null;
        return ['ok' => false, 'error' => $expired ? "This quote ran out on " . escapeHtml($expired[0]) . " - ask for an updated one" : "This quote is no longer awaiting a response"];
    }

    $word = $accept ? 'Accepted' : 'Declined';
    $number = billingNumber('quote', $quote);
    $name = trim((string) ($by['name'] ?? ''));
    $note = trim((string) ($by['note'] ?? ''));
    billingLog('quote', $quote_id, $word, $name !== '' ? "$word by $name in the client portal" . ($note !== '' ? "\nNote: $note" : '') : "$word by the client");
    appNotify("Quote $word", escapeSql("Quote $number has been " . strtolower($word) . " by {$quote['client_name']}" . ($name !== '' ? " ($name, in the client portal)" : '')),
        "/agent/quote.php?quote_id=$quote_id", intval($quote['client_id']));
    triggerCustomAction($accept ? 'quote_accept' : 'quote_decline', $quote_id);
    if ($name !== '') {
        logAudit("Quote", $accept ? "Accept" : "Decline", escapeSql("$name " . strtolower($word) . " quote $number in the client portal"), intval($quote['client_id']), $quote_id);
    }

    // Our team hears - functions/billing_email.php
    $settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_smtp_host, config_quote_notification_email FROM settings WHERE company_id = 1")) ?: [];
    if (!empty($settings['config_smtp_host']) && !empty($settings['config_quote_notification_email'])) {
        billingQuoteAnsweredNotify($quote_id, $accept, $name, $note);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * PDFs a client sends back with an accepted quote - a signed copy, a purchase
 * order - from its guest link or the client portal. $files: the upload's
 * $_FILES entry (file[]). Each goes in the client's Client Uploads folder and
 * on the quote, and the agents hear. $by: name, for the portal.
 * ['added' => file names, 'failed' => how many could not be taken].
 */
function billingQuoteAddFiles($quote_id, array $files, array $by = []) {
    global $mysqli;
    $quote = billingRow('quote', $quote_id, false);
    $result = ['added' => [], 'failed' => 0];
    if (!$quote || !isset($files['name']) || !is_array($files['name'])) {
        return $result;
    }
    $quote_id = intval($quote['quote_id']);
    $client_id = intval($quote['client_id']);
    $number = billingNumber('quote', $quote);
    $number_sql = escapeSql($number);
    $name = trim((string) ($by['name'] ?? ''));

    $upload_file_dir = dirname(__DIR__) . "/uploads/clients/$client_id/";
    mkdirMissing($upload_file_dir);

    for ($i = 0; $i < count($files['name']); $i++) {
        $single_file = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
        $file_reference_name = checkFileUpload($single_file, ['pdf']);
        if (!$file_reference_name) {
            $result['failed']++;
            continue;
        }

        $file_name = escapeSql($single_file['name']);
        $extarr = explode('.', $single_file['name']);
        $file_extension = escapeSql(strtolower(end($extarr)));
        $file_mime_type = escapeSql($single_file['type']);
        $file_size = intval($single_file['size']);

        // Get/Create a top-level folder called Client Uploads
        $folder = mysqli_fetch_row(mysqli_query($mysqli, "SELECT folder_id FROM folders WHERE folder_name = 'Client Uploads' AND parent_folder = 0 AND folder_client_id = $client_id LIMIT 1"));
        if ($folder) {
            $folder_id = intval($folder[0]);
        } else {
            mysqli_query($mysqli, "INSERT INTO folders SET folder_name = 'Client Uploads', parent_folder = 0, folder_location = 1, folder_client_id = $client_id");
            $folder_id = intval(mysqli_insert_id($mysqli));
            logAudit("Folder", "Create", "Automatically created folder Client Uploads", $client_id, $folder_id);
        }

        if (!move_uploaded_file($single_file['tmp_name'], $upload_file_dir . $file_reference_name)) {
            $result['failed']++;
            continue;
        }

        mysqli_query($mysqli, "INSERT INTO files SET file_reference_name = '" . escapeSql($file_reference_name) . "', file_name = '$file_name', file_description = 'Uploaded via $number_sql', file_ext = '$file_extension', file_mime_type = '$file_mime_type', file_size = $file_size, file_folder_id = $folder_id, file_client_id = $client_id");
        $file_id = intval(mysqli_insert_id($mysqli));
        mysqli_query($mysqli, "INSERT INTO quote_files SET quote_id = $quote_id, file_id = $file_id");

        appNotify("Quote File", "$file_name was uploaded to quote $number_sql", "/agent/quote.php?quote_id=$quote_id", $client_id);
        billingLog('quote', $quote_id, 'Upload', $name !== '' ? "$name uploaded file {$single_file['name']} in the client portal" : "Client uploaded file {$single_file['name']}");
        logAudit("File", "Upload", ($name !== '' ? escapeSql($name) : 'Guest') . " uploaded file $file_name to quote $number_sql" . ($name !== '' ? ' in the client portal' : ''), $client_id);
        $result['added'][] = (string) $single_file['name'];
    }
    return $result;
}

/** The files sent back with a quote - name and when, newest first. */
function billingQuoteFiles($quote_id) {
    global $mysqli;
    $quote_id = intval($quote_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT files.file_id, file_name, file_size, file_created_at FROM quote_files
        JOIN files ON files.file_id = quote_files.file_id
        WHERE quote_files.quote_id = $quote_id AND file_archived_at IS NULL ORDER BY file_created_at DESC, files.file_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/* ------------------------------------------------------- Recurring invoices */

/** How long a missed run is still billed by the nightly run - a night or a few the cron didn't run. */
const RECURRING_INVOICE_GRACE_DAYS = 7;

/** How often a recurring invoice runs => label. Each is also a MySQL interval unit. */
function recurringInvoiceFrequencies() {
    return ['month' => 'Monthly', 'quarter' => 'Quarterly', 'year' => 'Yearly'];
}

/** How many months a run covers. */
function recurringInvoiceMonths($frequency) {
    return ['quarter' => 3, 'year' => 12][(string) $frequency] ?? 1;
}

/** "month", "quarter" or "year" - for "each month", "a quarter". */
function recurringInvoicePeriodWord($frequency) {
    return ['quarter' => 'quarter', 'year' => 'year'][(string) $frequency] ?? 'month';
}

/** What a recurring invoice brings in a month. */
function recurringInvoiceMonthly($amount, $frequency) {
    return floatval($amount) / recurringInvoiceMonths($frequency);
}

/**
 * The same in SQL, for sums - a quarterly one counts a third, a yearly one a
 * twelfth. Takes the amount and frequency column names.
 */
function recurringInvoiceMonthlySql($amount = 'recurring_invoice_amount', $frequency = 'recurring_invoice_frequency') {
    return "(CASE $frequency WHEN 'year' THEN $amount / 12 WHEN 'quarter' THEN $amount / 3 ELSE $amount END)";
}

/** "In 5 days", "Today", "3 days late" - for an active recurring invoice's next run. */
function recurringInvoiceNextPill($row) {
    if (intval($row['recurring_invoice_status']) !== 1) {
        return '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal"><i class="fas fa-pause me-1"></i>Paused</span>';
    }
    $days = intval(round((strtotime((string) $row['recurring_invoice_next_date']) - strtotime(date('Y-m-d'))) / 86400));
    if ($days < 0) {
        $title = -$days <= RECURRING_INVOICE_GRACE_DAYS ? 'Missed - the next nightly run bills it' : "Too far behind to bill by itself - use Invoice Now, or move it on";
        return '<span class="badge rounded-pill text-bg-danger fw-normal" title="' . $title . '"><i class="fas fa-exclamation-triangle me-1"></i>' . (-$days) . ' ' . (-$days === 1 ? 'day' : 'days') . ' late</span>';
    }
    if ($days === 0) {
        return '<span class="badge rounded-pill text-bg-info fw-normal">Today</span>';
    }
    return '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal">In ' . $days . ' ' . ($days === 1 ? 'day' : 'days') . '</span>';
}

/**
 * A run date one period on. It keeps to the day of the month the schedule
 * bills on ($anchor_day, recurring_invoice_anchor_day) - or the date's own
 * day when there isn't one - and falls back to a shorter month's last day:
 * the 31st runs on the 28th of February and the 31st of March.
 */
function recurringInvoiceStep($date, $frequency, $anchor_day = 0) {
    $d = new DateTime((string) $date);
    $day = intval($anchor_day) > 0 ? intval($anchor_day) : intval($d->format('j'));
    $d->modify('first day of this month')->modify('+' . recurringInvoiceMonths($frequency) . ' months');
    $d->setDate(intval($d->format('Y')), intval($d->format('n')), min($day, intval($d->format('t'))));
    return $d->format('Y-m-d');
}

/** A recurring invoice's next run date after $date. */
function recurringInvoiceAfter($row, $date) {
    return recurringInvoiceStep($date, $row['recurring_invoice_frequency'], intval($row['recurring_invoice_anchor_day'] ?? 0));
}

/** The next few dates a recurring invoice will run on. */
function recurringInvoiceUpcoming($row, $count = 3) {
    $dates = [];
    $date = (string) $row['recurring_invoice_next_date'];
    for ($i = 0; $i < $count && $date; $i++) {
        $dates[] = $date;
        $date = recurringInvoiceAfter($row, $date);
    }
    return $dates;
}

/** The first run date from a recurring invoice's next date on that hasn't passed - the missed runs skipped. */
function recurringInvoiceCatchUp($row) {
    $date = (string) $row['recurring_invoice_next_date'];
    $today = date('Y-m-d');
    for ($guard = 0; $date < $today && $guard < 1200; $guard++) {
        $date = recurringInvoiceAfter($row, $date);
    }
    return $date;
}

/**
 * The period a run bills for - from its date to the day before the next one -
 * and the words for it that can go in a recurring invoice's scope, notes and
 * lines: {month} "November 2026", {quarter} "Q4 2026", {year} "2026" and
 * {period} "Nov 1 - Nov 30, 2026".
 */
function recurringInvoicePeriod($row, $date) {
    $start = new DateTime((string) $date);
    $end = new DateTime(recurringInvoiceAfter($row, $date));
    $end->modify('-1 day');
    if ($end < $start) {
        $end = clone $start;
    }
    $same_year = $start->format('Y') === $end->format('Y');
    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        '{month}' => $start->format('F Y'),
        '{quarter}' => 'Q' . intval(ceil(intval($start->format('n')) / 3)) . ' ' . $start->format('Y'),
        '{year}' => $start->format('Y'),
        '{period}' => $start->format($same_year ? 'M j' : 'M j, Y') . ' - ' . $end->format('M j, Y'),
    ];
}

/** Put a period's words into text - the tokens it doesn't use are left as typed. */
function recurringInvoiceTokens($text, array $period) {
    return strtr((string) $text, array_intersect_key($period, array_flip(['{month}', '{quarter}', '{year}', '{period}'])));
}

/* ---------------------------------------------------------- Seat-based lines */

/*
 * A line on a recurring invoice can take its quantity from the client: their
 * contacts (users), their devices, or a license's seats - counted again each
 * time an invoice is made, and never less than the line's minimum.
 * item_seat_source says which, item_seat_filter (JSON) narrows it: a tag, the
 * device types, or the license and whether to count the seats it has or the
 * ones in use.
 */

/** The places a line's quantity can come from. */
function recurringSeatSources() {
    return [
        'contacts' => ['label' => 'Users', 'unit' => 'user', 'icon' => 'fa-user'],
        'assets' => ['label' => 'Devices', 'unit' => 'device', 'icon' => 'fa-desktop'],
        'license' => ['label' => 'License seats', 'unit' => 'seat', 'icon' => 'fa-cube'],
    ];
}

/** Device statuses that are not billed - gone, or out of use. */
function recurringSeatAssetSkipStatuses() {
    return ['Lost', 'Stolen', 'Retired'];
}

/** A line's seat filter, cleaned - from the stored JSON or a form's array. */
function recurringSeatFilter($source, $filter) {
    if (!is_array($filter)) {
        $filter = json_decode((string) $filter, true);
        $filter = is_array($filter) ? $filter : [];
    }
    $clean = [];
    if (in_array($source, ['contacts', 'assets'], true) && intval($filter['tag'] ?? 0)) {
        $clean['tag'] = intval($filter['tag']);
    }
    if ($source === 'assets' && !empty($filter['types']) && is_array($filter['types'])) {
        $types = array_values(array_unique(array_filter(array_map(function ($type) {
            return mb_substr(trim((string) $type), 0, 100);
        }, $filter['types']), 'strlen')));
        if ($types) {
            $clean['types'] = $types;
        }
    }
    if ($source === 'license') {
        $clean['software_id'] = intval($filter['software_id'] ?? 0);
        $clean['count'] = ($filter['count'] ?? '') === 'total' ? 'total' : 'used';
    }
    return $clean;
}

/**
 * How many a client has right now, for a seat source and filter - or null
 * when it can't be counted (the license is gone, or isn't the client's).
 */
function recurringSeatCount($client_id, $source, array $filter) {
    global $mysqli;
    $client_id = intval($client_id);
    $tag = intval($filter['tag'] ?? 0);
    if ($source === 'contacts') {
        $tag_sql = $tag ? "AND contact_id IN (SELECT contact_id FROM contact_tags WHERE tag_id = $tag)" : '';
        return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL $tag_sql"))[0]);
    }
    if ($source === 'assets') {
        $skip = "'" . implode("', '", array_map('escapeSql', recurringSeatAssetSkipStatuses())) . "'";
        $types_sql = !empty($filter['types']) ? "AND asset_type IN ('" . implode("', '", array_map('escapeSql', $filter['types'])) . "')" : '';
        $tag_sql = $tag ? "AND asset_id IN (SELECT asset_tag_asset_id FROM asset_tags WHERE asset_tag_tag_id = $tag)" : '';
        return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL
            AND (asset_status IS NULL OR asset_status NOT IN ($skip)) $types_sql $tag_sql"))[0]);
    }
    if ($source === 'license') {
        $software_id = intval($filter['software_id'] ?? 0);
        $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT software_seats, " . softwareSeatsUsedSql('software.software_id') . " FROM software
            WHERE software_id = $software_id AND software_client_id = $client_id AND software_archived_at IS NULL"));
        if (!$row) {
            return null;
        }
        return ($filter['count'] ?? 'used') === 'total' ? intval($row[0]) : intval($row[1]);
    }
    return null;
}

/** What a seat line counts, in words - "Users tagged VIP", "Desktops and laptops", "Seats on Microsoft 365 in use". */
function recurringSeatDescribe($source, array $filter) {
    global $mysqli;
    $tag = intval($filter['tag'] ?? 0);
    $tag_name = $tag ? (string) getFieldById('tags', $tag, 'tag_name') : '';
    if ($source === 'contacts') {
        return $tag_name !== '' ? "Contacts tagged $tag_name" : 'All contacts';
    }
    if ($source === 'assets') {
        $types = $filter['types'] ?? [];
        $what = $types ? (count($types) > 1 ? implode(', ', array_slice($types, 0, -1)) . ' and ' . end($types) : $types[0]) : 'All devices';
        return $what . ($tag_name !== '' ? " tagged $tag_name" : '') . ' in use';
    }
    if ($source === 'license') {
        $name = (string) getFieldById('software', intval($filter['software_id'] ?? 0), 'software_name');
        return ($name !== '' ? $name : 'A license that no longer exists') . (($filter['count'] ?? 'used') === 'total' ? ' - seats bought' : ' - seats in use');
    }
    return '';
}

/**
 * What a client has to count, for choosing a seat line's source: how many
 * contacts and devices, the tags on them, their device types and their
 * licenses - each with how many.
 */
function recurringSeatOptions($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $skip = "'" . implode("', '", array_map('escapeSql', recurringSeatAssetSkipStatuses())) . "'";
    $assets_in_use = "asset_client_id = $client_id AND asset_archived_at IS NULL AND (asset_status IS NULL OR asset_status NOT IN ($skip))";
    $options = [
        'contacts' => recurringSeatCount($client_id, 'contacts', []),
        'assets' => recurringSeatCount($client_id, 'assets', []),
        'contact_tags' => [], 'asset_types' => [], 'asset_tags' => [], 'licenses' => [],
    ];
    $sql = mysqli_query($mysqli, "SELECT tag_id, tag_name, COUNT(*) AS n FROM tags JOIN contact_tags USING (tag_id) JOIN contacts USING (contact_id)
        WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND tag_archived_at IS NULL GROUP BY tag_id ORDER BY tag_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $options['contact_tags'][intval($row['tag_id'])] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT asset_type, COUNT(*) AS n FROM assets WHERE $assets_in_use AND asset_type <> '' GROUP BY asset_type ORDER BY asset_type");
    while ($row = mysqli_fetch_assoc($sql)) {
        $options['asset_types'][$row['asset_type']] = intval($row['n']);
    }
    $sql = mysqli_query($mysqli, "SELECT tag_id, tag_name, COUNT(*) AS n FROM tags JOIN asset_tags ON asset_tag_tag_id = tag_id JOIN assets ON asset_id = asset_tag_asset_id
        WHERE $assets_in_use AND tag_archived_at IS NULL GROUP BY tag_id ORDER BY tag_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $options['asset_tags'][intval($row['tag_id'])] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT software_id, software_name, software_seats, " . softwareSeatsUsedSql('software.software_id') . " AS used FROM software
        WHERE software_client_id = $client_id AND software_archived_at IS NULL ORDER BY software_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $options['licenses'][intval($row['software_id'])] = $row;
    }
    return $options;
}

/**
 * The seat lines on a recurring invoice, each with what it counts now and
 * the quantity that makes (never under its minimum): line row + seat_count,
 * seat_quantity, seat_label and seat_drift (true when the line is off).
 */
function recurringSeatLines($recurring_invoice_id, $client_id = null) {
    global $mysqli;
    $recurring_invoice_id = intval($recurring_invoice_id);
    if ($client_id === null) {
        $client_id = intval(getFieldById('recurring_invoices', $recurring_invoice_id, 'recurring_invoice_client_id'));
    }
    $lines = [];
    $sql = mysqli_query($mysqli, "SELECT item_id, item_name, item_quantity, item_seat_source, item_seat_filter, item_seat_minimum FROM recurring_invoice_items
        WHERE item_recurring_invoice_id = $recurring_invoice_id AND item_seat_source IS NOT NULL AND item_seat_source <> '' ORDER BY item_order, item_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $filter = recurringSeatFilter($row['item_seat_source'], $row['item_seat_filter']);
        $count = recurringSeatCount($client_id, $row['item_seat_source'], $filter);
        $row['seat_filter'] = $filter;
        $row['seat_count'] = $count;
        $row['seat_quantity'] = $count === null ? null : max(floatval($count), floatval($row['item_seat_minimum']));
        $row['seat_label'] = recurringSeatDescribe($row['item_seat_source'], $filter);
        $row['seat_drift'] = $count !== null && round($row['seat_quantity'] - floatval($row['item_quantity']), 2) != 0;
        $lines[intval($row['item_id'])] = $row;
    }
    return $lines;
}

/**
 * Bring a recurring invoice's lines up to date: each seat line's quantity
 * from what the client has now, and each line's tax from the tax's rate now.
 * Seat changes go in its history. What changed, in words.
 */
function recurringInvoiceRefresh($recurring_invoice_id, $by = 'Cron') {
    global $mysqli;
    $recurring_invoice_id = intval($recurring_invoice_id);
    $client_id = intval(getFieldById('recurring_invoices', $recurring_invoice_id, 'recurring_invoice_client_id'));
    $seats = recurringSeatLines($recurring_invoice_id, $client_id);
    $changes = [];

    $sql = mysqli_query($mysqli, "SELECT item_id, item_name, item_quantity, item_price, item_subtotal FROM recurring_invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
    while ($item = mysqli_fetch_assoc($sql)) {
        $item_id = intval($item['item_id']);
        $quantity = floatval($item['item_quantity']);
        if (isset($seats[$item_id]) && $seats[$item_id]['seat_drift']) {
            $new_quantity = floatval($seats[$item_id]['seat_quantity']);
            $changes[] = "{$item['item_name']}: " . billingQuantity($quantity) . ' to ' . billingQuantity($new_quantity) . ' (' . $seats[$item_id]['seat_label'] . ')';
            $quantity = $new_quantity;
        }
        $subtotal = round($quantity * floatval($item['item_price']), 2);
        if ($quantity != floatval($item['item_quantity']) || $subtotal != floatval($item['item_subtotal'])) {
            mysqli_query($mysqli, "UPDATE recurring_invoice_items SET item_quantity = $quantity, item_subtotal = $subtotal WHERE item_id = $item_id");
        }
    }

    // Each line's tax at its rate now, after any discount (billingRecalcTaxes())
    billingRecalc('recurring', $recurring_invoice_id);
    foreach ($changes as $change) {
        billingLog('recurring', $recurring_invoice_id, 'Seats', "$change - by $by");
    }
    return $changes;
}

/* -------------------------------------------- Making invoices from recurring */

/**
 * Make the invoice for a recurring invoice's next run - the nightly run and
 * Invoice Now both come here.
 *
 * Seat lines are counted again and taxes taken at today's rates first. The
 * invoice is dated the run's date ($early: today, for Invoice Now), due on
 * the client's terms, with the period put into its scope, notes and lines.
 * The schedule moves one period on from the run it billed - so billing early
 * takes the place of that run, and a missed run billed now is not billed
 * again. The run is claimed first, so two requests can't bill it twice.
 *
 * $run is the run date the caller means to bill - what the nightly run
 * picked, or what the page showed when Invoice Now was pressed. When the
 * schedule has moved on since (another request billed it), nothing is made,
 * rather than billing the run after. Without $early a run still to come is
 * never billed.
 *
 * When the recurring invoice sends by itself and recurring invoices are
 * emailed (Admin > Settings > Invoice), it goes to the client's primary and
 * billing contacts and is marked sent; otherwise it is left as a draft.
 * The new invoice's id, or 0.
 */
function recurringInvoiceGenerate($recurring_invoice_id, $by = 'Cron', $early = false, $run = null) {
    global $mysqli, $config_base_url;

    $recurring_invoice_id = intval($recurring_invoice_id);
    $row = billingRow('recurring', $recurring_invoice_id, false);
    if (!$row) {
        return 0;
    }
    $due = (string) $row['recurring_invoice_next_date'];
    if (($run !== null && (string) $run !== $due) || (!$early && $due > date('Y-m-d'))) {
        return 0;
    }
    $next = recurringInvoiceAfter($row, $due);
    $date = $early ? date('Y-m-d') : $due;

    // Claim the run
    mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_next_date = '$next', recurring_invoice_last_sent = '$date'
        WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_next_date = '" . escapeSql($due) . "'");
    if (mysqli_affected_rows($mysqli) !== 1) {
        return 0;
    }

    $seat_changes = recurringInvoiceRefresh($recurring_invoice_id, $by);
    $row = billingRow('recurring', $recurring_invoice_id, false);
    $period = recurringInvoicePeriod($row, $due);

    $settings = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_invoice_prefix, config_recurring_auto_send_invoice,
        config_invoice_from_email, config_invoice_from_name, config_smtp_provider FROM settings WHERE company_id = 1")) ?: [];
    $terms = intval($row['client_net_terms']); // 0 is "On Receipt"
    $client_id = intval($row['client_id']);
    $prefix = escapeSql($settings['config_invoice_prefix'] ?? '');
    $number = billingNextNumber('invoice');
    $url_key = randomString(32);
    $scope = escapeSql(recurringInvoiceTokens($row['recurring_invoice_scope'], $period));
    $note = escapeSql(recurringInvoiceTokens($row['recurring_invoice_note'], $period));
    $discount = floatval($row['recurring_invoice_discount_amount']);
    $currency = escapeSql($row['recurring_invoice_currency_code']);
    $category_id = intval($row['recurring_invoice_category_id']);

    mysqli_query($mysqli, "INSERT INTO invoices SET invoice_prefix = '$prefix', invoice_number = $number, invoice_scope = '$scope', invoice_date = '$date',
        invoice_due = DATE_ADD('$date', INTERVAL $terms DAY), invoice_discount_amount = $discount, invoice_amount = 0, invoice_currency_code = '$currency',
        invoice_note = '$note', invoice_category_id = $category_id, invoice_status = 'Draft', invoice_url_key = '$url_key',
        invoice_recurring_invoice_id = $recurring_invoice_id, invoice_client_id = $client_id");
    $invoice_id = mysqli_insert_id($mysqli);
    if (!$invoice_id) {
        return 0;
    }

    foreach (billingItems('recurring', $recurring_invoice_id) as $item) {
        $name = escapeSql(recurringInvoiceTokens($item['item_name'], $period));
        $description = escapeSql(recurringInvoiceTokens($item['item_description'], $period));
        // Linked to its product, so it counts as sold - recurring invoices never move stock
        mysqli_query($mysqli, "INSERT INTO invoice_items SET item_name = '$name', item_description = '$description', item_quantity = " . floatval($item['item_quantity']) . ",
            item_price = " . floatval($item['item_price']) . ", item_subtotal = " . floatval($item['item_subtotal']) . ", item_tax = " . floatval($item['item_tax']) . ",
            item_total = " . floatval($item['item_total']) . ", item_order = " . intval($item['item_order']) . ", item_tax_id = " . intval($item['item_tax_id']) . ",
            item_product_id = " . intval($item['item_product_id']) . ", item_invoice_id = $invoice_id");
    }
    billingRecalc('invoice', $invoice_id);

    $invoice = billingRow('invoice', $invoice_id, false);
    $invoice_number = billingNumber('invoice', $invoice);
    $recurring_number = billingNumber('recurring', $row);
    billingLog('invoice', $invoice_id, 'Draft', "Made from $recurring_number by $by, for {$period['{period}']}" . ($seat_changes ? ' - seats updated' : ''));
    billingLog('recurring', $recurring_invoice_id, 'Invoiced', "$invoice_number made by $by, for {$period['{period}']} - next on $next");

    // Send it, when it sends itself - to the primary and billing contacts, on the billing email (functions/billing_email.php)
    $sent_to = [];
    if (intval($row['recurring_invoice_auto_send']) === 1 && intval($row['recurring_invoice_email_notify']) === 1
        && intval($settings['config_recurring_auto_send_invoice'] ?? 0) === 1 && !empty($settings['config_smtp_provider'])) {
        $recipients = billingEmailRecipients($client_id);
        if ($recipients) {
            [$subject, $email] = billingInvoiceEmail($invoice);
            $attach = [];
            if (billingReminderRules()['attach_pdf'] && ($file = billingEmailPdf(billingPdf('invoice', $invoice)))) {
                $attach[] = $file;
            }
            $sent_to = billingEmailQueue($recipients, $subject, $email, ['attach' => $attach]);
        }
        if ($sent_to) {
            mysqli_query($mysqli, "UPDATE invoices SET invoice_status = 'Sent' WHERE invoice_id = $invoice_id");
            billingLog('invoice', $invoice_id, 'Sent', 'Emailed to ' . implode(', ', $sent_to) . ($attach ? ' with the PDF' : ''));
        } else {
            billingLog('invoice', $invoice_id, 'Draft', 'Nobody to email it to - no primary or billing contact with an email address');
        }
    }

    $client_name = escapeSql($row['client_name']);
    appNotify($sent_to ? 'Recurring Sent' : 'Recurring Generated', escapeSql("$invoice_number for {$row['client_name']} made from $recurring_number" . ($sent_to ? ' and emailed' : ' - a draft to check and send')),
        "/agent/invoice.php?invoice_id=$invoice_id", $client_id);
    logAudit('Invoice', 'Create', escapeSql("$by made invoice $invoice_number from recurring invoice $recurring_number for {$period['{period}']}"), $client_id, $invoice_id);
    triggerCustomAction('invoice_create', $invoice_id);

    return $invoice_id;
}

/** The invoices a recurring invoice has made, newest first, with what has been paid on each. */
function recurringInvoiceGenerated($recurring_invoice_id, $limit = 12) {
    global $mysqli;
    $recurring_invoice_id = intval($recurring_invoice_id);
    $limit = max(1, intval($limit));
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_date, invoice_due, invoice_amount, invoice_status, invoice_currency_code,
            (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE payment_invoice_id = invoice_id) AS paid
        FROM invoices WHERE invoice_recurring_invoice_id = $recurring_invoice_id
        ORDER BY invoice_date DESC, invoice_id DESC LIMIT $limit");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/* ---------------------------------------------------------------- Showing */

/** A document's history as a timeline, newest first. */
function billingHistoryHtml(array $rows) {
    if (!$rows) {
        return '<div class="itflow-asset-empty py-4">Nothing has happened to it yet.</div>';
    }
    $html = '<ul class="list-unstyled mb-0 p-3 itflow-billing-history">';
    foreach ($rows as $row) {
        // Older guest views put the browser's HTML into the description
        $description = trim(preg_replace('/[ \t]+/', ' ', strip_tags((string) $row['history_description'])));
        $html .= '<li><div>' . nl2br(escapeHtml($description)) . '</div>'
            . '<div class="small text-body-secondary">' . escapeHtml($row['history_status'])
            . ' &middot; <span title="' . escapeHtml($row['history_created_at']) . '">' . escapeHtml(timeAgo($row['history_created_at'])) . '</span></div></li>';
    }
    return $html . '</ul>';
}

/** The square tile for a kind of billing document. */
function billingTile($type, $size = '') {
    $t = billingType($type);
    return '<span class="itflow-asset-icon itflow-billing-tile itflow-billing-tile-' . $type . ($size ? " itflow-asset-icon-$size" : '') . '" aria-hidden="true"><i class="fas fa-fw ' . $t['icon'] . '"></i></span>';
}

/** The "not found" page for a billing document that isn't there or can't be opened. */
function billingNotFound($type, $client_url = '') {
    $t = billingType($type);
    echo '<div class="itflow-asset-empty mt-5"><i class="fas ' . $t['icon'] . ' fa-3x mb-3 opacity-50"></i>'
        . '<h1 class="h4">That ' . strtolower($t['label']) . " isn't here</h1>"
        . '<p>It may have been deleted, or it belongs to a client you can\'t open.</p>'
        . '<a class="btn btn-outline-secondary" href="' . $t['list'] . ($client_url ? '?' . rtrim($client_url, '&') : '') . '"><i class="fas fa-arrow-left me-2"></i>Back to ' . $t['plural'] . '</a></div>';
}

/* ---------------------------------------------------------------- Payments */

/**
 * The accounts a payment can go to, with each one's balance (accountsList(),
 * the one balance rule). $keep_id keeps an archived account in the list (the
 * one a payment is in).
 */
function paymentAccounts($keep_id = 0) {
    return accountsList(false, $keep_id);
}

/** The ways a payment can be made - Cash, Check, ACH and whatever else is set up. */
function paymentMethods() {
    global $mysqli;
    $methods = [];
    $sql = mysqli_query($mysqli, "SELECT payment_method_name FROM payment_methods ORDER BY payment_method_name ASC");
    while ($row = mysqli_fetch_row($sql)) {
        $methods[] = $row[0];
    }
    return $methods;
}

/** Can clients pay invoices online - is a payment provider turned on? */
function billingOnlinePayments() {
    global $mysqli;
    static $online = null;
    if ($online === null) {
        $online = (bool) mysqli_fetch_row(mysqli_query($mysqli, "SELECT 1 FROM payment_providers WHERE payment_provider_active = 1 LIMIT 1"));
    }
    return $online;
}

/* -------------------------------------------------------------------- PDFs */

/**
 * An invoice, quote or recurring invoice as a PDF - or an invoice's packing
 * slip ($variant 'packing'): ['name' => file name, 'data' => the PDF].
 * $row is billingRow(). See functions/billing_pdf.php.
 */
function billingPdf($type, array $row, $variant = 'document') {
    require_once __DIR__ . '/../libs/TCPDF/tcpdf.php';
    require_once __DIR__ . '/billing_pdf.php';
    return billingPdfRender($type, $row, $variant);
}

/** Hand a billingPdf() to the browser - opened in a tab, or saved with $download. */
function billingPdfSend(array $pdf, $download = false) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $pdf['name'] . '"');
    header('Content-Length: ' . strlen($pdf['data']));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $pdf['data'];
    exit;
}

/* ------------------------------------------------------------- Guest pages */

/**
 * A client has opened an invoice or quote - from its link, or in the client
 * portal ($who, the contact): a sent one becomes viewed, the view goes in
 * its history (where from, in plain text), and the agents hear about it
 * while it still matters - an invoice not yet paid, a quote not yet
 * answered.
 */
function billingGuestViewed($type, array $row, $who = '') {
    global $mysqli;
    $t = billingType($type);
    $c = $t['col'];
    $id = intval($row["{$c}_id"]);
    $status = (string) $row["{$c}_status"];
    $number = billingNumber($type, $row);
    $who = trim((string) $who);

    if ($status === 'Sent') {
        mysqli_query($mysqli, "UPDATE {$t['table']} SET {$c}_status = 'Viewed' WHERE {$c}_id = $id AND {$c}_status = 'Sent'");
    }

    if ($who !== '') {
        $where = '';
        $description = "Viewed in the client portal by $who";
        $by = " ($who, in the client portal)";
    } else {
        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $where = implode(' - ', array_filter([getIP(), trim(strip_tags(getOS($agent))), trim(strip_tags(getWebBrowser($agent)))], function ($part) {
            return $part !== '' && $part !== '-';
        }));
        $description = 'Viewed by the client' . ($where !== '' ? " - $where" : '');
        $by = $where !== '' ? " - $where" : '';
    }
    billingLog($type, $id, $status === 'Sent' ? 'Viewed' : $status, $description);

    $matters = $type === 'invoice' ? !in_array($status, ['Paid', 'Cancelled', 'Non-Billable'], true) : in_array($status, ['Draft', 'Sent', 'Viewed'], true);
    if ($matters) {
        appNotify($t['label'] . ' Viewed', escapeSql("$number has been viewed by {$row['client_name']}$by"), "/agent/{$t['page']}?{$t['param']}=$id", intval($row['client_id']));
    }
}

/** A client's invoices still owed, oldest first, with what is left on each - their statement. */
function billingClientOpenInvoices($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_date, invoice_due, invoice_status,
            invoice_amount, invoice_currency_code, invoice_url_key,
            COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0) AS paid
        FROM invoices
        WHERE invoice_client_id = $client_id AND invoice_status IN ('Sent', 'Viewed', 'Partial')
        ORDER BY invoice_date ASC, invoice_number ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (round(floatval($row['invoice_amount']) - floatval($row['paid']), 2) > 0) {
            $rows[] = $row;
        }
    }
    return $rows;
}
