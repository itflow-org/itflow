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
    $sql = mysqli_query($mysqli, "SELECT item_id, item_name, item_description, item_quantity, item_price, item_subtotal, item_tax, item_total,
            item_order, item_tax_id, item_product_id, item_created_at, tax_name, tax_percent
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

/** A line's subtotal, tax and total, each to the cent. */
function billingLineAmounts($quantity, $price, $tax_percent) {
    $subtotal = round(floatval($quantity) * floatval($price), 2);
    $tax = round($subtotal * floatval($tax_percent) / 100, 2);
    return [$subtotal, $tax, round($subtotal + $tax, 2)];
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
 * price, tax_id, product_id. For an invoice, a stocked product's stock goes
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
    if ($quantity === null || $price === null) {
        $error = 'The quantity and price have to be numbers.';
        return null;
    }
    if (abs($quantity) >= 1e9 || abs($price) >= 1e11) {
        $error = 'That amount is too large.';
        return null;
    }

    $tax_percent = 0;
    if ($tax_id) {
        $tax = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tax_percent FROM taxes WHERE tax_id = $tax_id"));
        if (!$tax) {
            $error = 'That tax no longer exists.';
            return null;
        }
        $tax_percent = floatval($tax['tax_percent']);
    }
    [$subtotal, $tax_amount, $total] = billingLineAmounts($quantity, $price, $tax_percent);

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

    // Stock moves only with invoices, and only for things on a shelf
    if ($type === 'invoice' && $product_id && getFieldById('products', $product_id, 'product_type') === 'product') {
        $change = round($quantity - ($old ? floatval($old['item_quantity']) : 0), 2);
        if ($change > 0) {
            $available = floatval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(stock_qty), 0) FROM product_stock WHERE stock_product_id = $product_id"))[0]);
            if ($available < $change) {
                $error = 'Not enough ' . $name . ' in stock - ' . billingQuantity($available) . ' left.';
                return null;
            }
        }
        if ($change != 0) {
            $note = escapeSql(($change > 0 ? 'QTY ' . billingQuantity($change) . ' to' : 'Returned QTY ' . billingQuantity(-$change) . ' from') . " Invoice $doc_id");
            mysqli_query($mysqli, "INSERT INTO product_stock SET stock_qty = " . (-$change) . ", stock_note = '$note', stock_product_id = $product_id");
        }
    }

    $set = "item_name = '" . escapeSql($name) . "', item_description = '" . escapeSql($description) . "',
        item_quantity = $quantity, item_price = $price, item_subtotal = $subtotal, item_tax = $tax_amount, item_total = $total, item_tax_id = $tax_id";
    if ($item_id) {
        mysqli_query($mysqli, "UPDATE {$t['items']} SET $set WHERE item_id = $item_id");
    } else {
        $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(item_order), 0) + 1 FROM {$t['items']} WHERE {$t['item_fk']} = $doc_id"))[0]);
        mysqli_query($mysqli, "INSERT INTO {$t['items']} SET $set, item_order = $order, item_product_id = $product_id, {$t['item_fk']} = $doc_id");
        $item_id = mysqli_insert_id($mysqli);
    }

    billingRecalc($type, $doc_id);
    return $item_id;
}

/** Take a line off a document - a stocked product goes back on the shelf. The line, or null. */
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
    if ($type === 'invoice' && $product_id && getFieldById('products', $product_id, 'product_type') === 'product' && floatval($item['item_quantity']) > 0) {
        $quantity = floatval($item['item_quantity']);
        mysqli_query($mysqli, "INSERT INTO product_stock SET stock_qty = $quantity, stock_note = 'Returned QTY " . billingQuantity($quantity) . " back to stock from Invoice $doc_id', stock_product_id = $product_id");
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
    $lines = floatval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(item_total), 0) FROM {$t['items']} WHERE {$t['item_fk']} = $doc_id"))[0]);
    $discount = floatval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT {$c}_discount_amount FROM {$t['table']} WHERE {$c}_id = $doc_id"))[0] ?? 0);
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

/** Subtotal, discount, tax and total for showing - from the lines, and the stored total. */
function billingTotals($type, $row, array $items) {
    $c = billingType($type)['col'];
    $subtotal = 0;
    $tax = 0;
    $taxes = [];
    foreach ($items as $item) {
        $subtotal += floatval($item['item_subtotal']);
        $tax += floatval($item['item_tax']);
        if (floatval($item['item_tax']) != 0) {
            $label = trim(($item['tax_name'] ?? 'Tax') . ' ' . billingQuantity($item['tax_percent'] ?? 0) . '%');
            $taxes[$label] = ($taxes[$label] ?? 0) + floatval($item['item_tax']);
        }
    }
    return [
        'subtotal' => round($subtotal, 2),
        'discount' => floatval($row["{$c}_discount_amount"] ?? 0),
        'credit' => floatval($row["{$c}_credit_amount"] ?? 0),
        'tax' => round($tax, 2),
        'taxes' => $taxes,
        'total' => floatval($row["{$c}_amount"] ?? 0),
    ];
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
    $sql = mysqli_query($mysqli, "SELECT history_status, history_description, history_created_at FROM history WHERE {$t['history_fk']} = $id ORDER BY history_id DESC LIMIT 200");
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
    return $row;
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
        FROM tickets LEFT JOIN ticket_replies ON ticket_reply_ticket_id = ticket_id
        WHERE ticket_invoice_id = $invoice_id
        GROUP BY ticket_id ORDER BY ticket_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/** A client's billable tickets, resolved or closed, not on an invoice yet. */
function invoiceUnbilledTickets($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_created_at,
            COALESCE(SUM(TIME_TO_SEC(ticket_reply_time_worked)), 0) AS seconds
        FROM tickets LEFT JOIN ticket_replies ON ticket_reply_ticket_id = ticket_id
        WHERE ticket_client_id = $client_id AND ticket_billable = 1 AND ticket_invoice_id = 0 AND ticket_status IN (4, 5)
        GROUP BY ticket_id ORDER BY ticket_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
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

/* ------------------------------------------------------- Recurring invoices */

/** How often a recurring invoice runs => label. */
function recurringInvoiceFrequencies() {
    return ['month' => 'Monthly', 'year' => 'Yearly'];
}

/** What a recurring invoice brings in a month. */
function recurringInvoiceMonthly($amount, $frequency) {
    return $frequency === 'year' ? floatval($amount) / 12 : floatval($amount);
}

/** "In 5 days", "Today", "3 days late" - for an active recurring invoice's next run. */
function recurringInvoiceNextPill($row) {
    if (intval($row['recurring_invoice_status']) !== 1) {
        return '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal"><i class="fas fa-pause me-1"></i>Paused</span>';
    }
    $days = intval(round((strtotime((string) $row['recurring_invoice_next_date']) - strtotime(date('Y-m-d'))) / 86400));
    if ($days < 0) {
        return '<span class="badge rounded-pill text-bg-danger fw-normal" title="The next run date is in the past - it will not run until it is moved forward"><i class="fas fa-exclamation-triangle me-1"></i>' . (-$days) . ' ' . (-$days === 1 ? 'day' : 'days') . ' late</span>';
    }
    if ($days === 0) {
        return '<span class="badge rounded-pill text-bg-info fw-normal">Today</span>';
    }
    return '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal">In ' . $days . ' ' . ($days === 1 ? 'day' : 'days') . '</span>';
}

/**
 * A run date one period on - the way MySQL's DATE_ADD (and so the nightly
 * run) does it: the 31st of January plus a month is the 28th of February.
 */
function recurringInvoiceStep($date, $frequency) {
    $d = new DateTime((string) $date);
    $day = intval($d->format('j'));
    $d->modify('first day of this month')->modify($frequency === 'year' ? '+12 months' : '+1 month');
    $d->setDate(intval($d->format('Y')), intval($d->format('n')), min($day, intval($d->format('t'))));
    return $d->format('Y-m-d');
}

/** The next few dates a recurring invoice will run on. */
function recurringInvoiceUpcoming($row, $count = 3) {
    $dates = [];
    $date = (string) $row['recurring_invoice_next_date'];
    for ($i = 0; $i < $count && $date; $i++) {
        $dates[] = $date;
        $date = recurringInvoiceStep($date, $row['recurring_invoice_frequency']);
    }
    return $dates;
}

/** The first run date from a recurring invoice's next date on that hasn't passed - the missed runs skipped. */
function recurringInvoiceCatchUp($row) {
    $date = (string) $row['recurring_invoice_next_date'];
    $today = date('Y-m-d');
    for ($guard = 0; $date < $today && $guard < 1200; $guard++) {
        $date = recurringInvoiceStep($date, $row['recurring_invoice_frequency']);
    }
    return $date;
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
 * The accounts a payment can go to, with each one's balance - one query.
 * $keep_id keeps an archived account in the list (the one a payment is in).
 */
function paymentAccounts($keep_id = 0) {
    global $mysqli;
    $keep_id = intval($keep_id);
    $accounts = [];
    $sql = mysqli_query($mysqli, "SELECT account_id, account_name, account_currency_code, account_archived_at,
            opening_balance + COALESCE(payments_in.total, 0) + COALESCE(revenues_in.total, 0) - COALESCE(expenses_out.total, 0) AS balance
        FROM accounts
        LEFT JOIN (SELECT payment_account_id, SUM(payment_amount) AS total FROM payments GROUP BY payment_account_id) AS payments_in ON payments_in.payment_account_id = account_id
        LEFT JOIN (SELECT revenue_account_id, SUM(revenue_amount) AS total FROM revenues GROUP BY revenue_account_id) AS revenues_in ON revenues_in.revenue_account_id = account_id
        LEFT JOIN (SELECT expense_account_id, SUM(expense_amount) AS total FROM expenses GROUP BY expense_account_id) AS expenses_out ON expenses_out.expense_account_id = account_id
        WHERE account_archived_at IS NULL OR account_id = $keep_id
        ORDER BY account_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $accounts[intval($row['account_id'])] = $row;
    }
    return $accounts;
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
 * A client has opened an invoice or quote from its link: a sent one becomes
 * viewed, the view goes in its history (where from, in plain text), and the
 * agents hear about it while it still matters - an invoice not yet paid, a
 * quote not yet answered.
 */
function billingGuestViewed($type, array $row) {
    global $mysqli;
    $t = billingType($type);
    $c = $t['col'];
    $id = intval($row["{$c}_id"]);
    $status = (string) $row["{$c}_status"];
    $number = billingNumber($type, $row);

    if ($status === 'Sent') {
        mysqli_query($mysqli, "UPDATE {$t['table']} SET {$c}_status = 'Viewed' WHERE {$c}_id = $id AND {$c}_status = 'Sent'");
    }

    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $where = implode(' - ', array_filter([getIP(), trim(strip_tags(getOS($agent))), trim(strip_tags(getWebBrowser($agent)))], function ($part) {
        return $part !== '' && $part !== '-';
    }));
    billingLog($type, $id, $status === 'Sent' ? 'Viewed' : $status, 'Viewed by the client' . ($where !== '' ? " - $where" : ''));

    $matters = $type === 'invoice' ? !in_array($status, ['Paid', 'Cancelled', 'Non-Billable'], true) : in_array($status, ['Draft', 'Sent', 'Viewed'], true);
    if ($matters) {
        appNotify($t['label'] . ' Viewed', escapeSql("$number has been viewed by {$row['client_name']}" . ($where !== '' ? " - $where" : '')), "/agent/{$t['page']}?{$t['param']}=$id", intval($row['client_id']));
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
