<?php

/*
 * Products and services - the catalog lines are picked from on invoices,
 * quotes and recurring invoices.
 *
 * Each one has a price and what it costs us (so its margin), the vendor we
 * buy it from with their part number, internal notes, and how it is billed by
 * default: once, or each month, quarter or year, counted per user or per
 * device. Picking it on a quote fills in Billed, on a recurring invoice
 * Counted. A product can track its stock: tracked ones move stock as they
 * are invoiced and can't be sold past what is on the shelf, the rest are
 * ordered as needed.
 */

/** Product types => label, plural and icon. */
function productTypes() {
    return [
        'service' => ['label' => 'Service', 'plural' => 'Services', 'icon' => 'fa-wrench'],
        'product' => ['label' => 'Product', 'plural' => 'Products', 'icon' => 'fa-box-open'],
    ];
}

/** How a recurring product is counted by default => label. Empty is as typed. */
function productSeatSources() {
    return ['' => 'As typed', 'contacts' => 'Per user', 'assets' => 'Per device'];
}

/** A product's default billing in words - "One time", "Monthly, per user". */
function productBilledText($frequency, $seat_source = '') {
    $frequency = quoteFrequency($frequency);
    if ($frequency === '') {
        return 'One time';
    }
    $text = recurringInvoiceFrequencies()[$frequency];
    $seat = (string) $seat_source;
    if ($seat !== '' && isset(productSeatSources()[$seat])) {
        $text .= ', ' . strtolower(productSeatSources()[$seat]);
    }
    return $text;
}

/**
 * The margin on a price at a cost - ['amount', 'percent'] (percent of the
 * price), or null when the cost isn't set or there is no price to take it from.
 */
function productMargin($price, $cost) {
    $price = floatval($price);
    $cost = floatval($cost);
    if ($cost <= 0 || $price <= 0) {
        return null;
    }
    return ['amount' => round($price - $cost, 2), 'percent' => round(($price - $cost) / $price * 100, 1)];
}

/** A margin as a pill - green from 30%, amber above 0, red at a loss. '' when there is none. */
function productMarginPill($margin) {
    if (!$margin) {
        return '';
    }
    $percent = $margin['percent'];
    $tone = $percent >= 30 ? 'success' : ($percent > 0 ? 'warning' : 'danger');
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis fw-normal" title="Margin on the price">' . escapeHtml(rtrim(rtrim(number_format($percent, 1), '0'), '.')) . '%</span>';
}

/** What is on the shelf, in SQL - use with products in the FROM. */
function productStockSql($column = 'products.product_id') {
    return "(SELECT COALESCE(SUM(stock_qty), 0) FROM product_stock WHERE stock_product_id = $column)";
}

/** A product's tile - its type's icon. */
function productTile($type, $size = '') {
    $type = isset(productTypes()[$type]) ? $type : 'service';
    return '<span class="itflow-asset-icon itflow-product-tile itflow-product-tile-' . $type . ($size ? " itflow-asset-icon-$size" : '') . '" aria-hidden="true"><i class="fas fa-fw ' . productTypes()[$type]['icon'] . '"></i></span>';
}

/** One product with its category, tax, vendor and stock - or null. */
function productRow($product_id) {
    global $mysqli;
    $product_id = intval($product_id);
    if (!$product_id) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT products.*, category_name, category_color, category_icon, tax_name, tax_percent, vendor_name,
            " . productStockSql() . " AS product_stock
        FROM products
        LEFT JOIN categories ON category_id = product_category_id
        LEFT JOIN taxes ON tax_id = product_tax_id
        LEFT JOIN vendors ON vendor_id = product_vendor_id
        WHERE product_id = $product_id")) ?: null;
}

/** Our vendors (not a client's), id => name, for picking who a product comes from. */
function productVendors() {
    global $mysqli;
    $vendors = [];
    $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = 0 AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $vendors[intval($row['vendor_id'])] = $row['vendor_name'];
    }
    return $vendors;
}

/**
 * Add a product ($product_id 0) or change one. $in holds raw values - only the
 * keys given are changed on an existing product:
 *   name, type (service or product), category (an Income category id), price,
 *   cost, tax (a tax id), frequency ('' once, month, quarter, year),
 *   seat_source ('' as typed, contacts, assets - only when billed recurring),
 *   code, vendor (one of our vendors), vendor_part, track_stock (products
 *   only), reorder_level and reorder_qty (when to buy more, how many),
 *   location, description, notes.
 * Returns the product id, or 0 with $error.
 */
function productSave(array $in, $product_id = 0, &$error = '') {
    global $mysqli, $session_company_currency;
    $error = '';
    $product_id = intval($product_id);
    $old = $product_id ? productRow($product_id) : null;
    if ($product_id && !$old) {
        $error = 'That product no longer exists.';
        return 0;
    }
    $has = function ($key) use ($in, $old) {
        return !$old || array_key_exists($key, $in);
    };
    $set = [];

    if ($has('name')) {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            $error = 'A product needs a name.';
            return 0;
        }
        $set[] = "product_name = '" . escapeSql(mb_substr($name, 0, 200)) . "'";
    }

    $type = $has('type') ? (string) ($in['type'] ?? 'service') : $old['product_type'];
    if (!isset(productTypes()[$type])) {
        $error = 'A product is a service or a product.';
        return 0;
    }
    if ($has('type')) {
        $set[] = "product_type = '$type'";
    }

    foreach (['price' => 'product_price', 'cost' => 'product_cost'] as $key => $column) {
        if (!$has($key)) {
            continue;
        }
        $raw = trim(str_replace([',', '$', ' '], '', (string) ($in[$key] ?? '')));
        if ($raw === '') {
            $raw = '0';
        }
        if (!is_numeric($raw) || abs(floatval($raw)) >= 1e13) {
            $error = ucfirst($key) . ' has to be a number.';
            return 0;
        }
        if ($key === 'cost' && floatval($raw) < 0) {
            $error = 'Cost can\'t be below zero.';
            return 0;
        }
        $set[] = "$column = " . round(floatval($raw), 2);
    }

    if ($has('category')) {
        $category_id = intval($in['category'] ?? 0);
        if ($category_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM categories WHERE category_id = $category_id AND category_type = 'Income'"))) {
            $error = 'That category is not an income category.';
            return 0;
        }
        $set[] = "product_category_id = $category_id";
    }

    if ($has('tax')) {
        $tax_id = intval($in['tax'] ?? 0);
        if ($tax_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM taxes WHERE tax_id = $tax_id"))) {
            $tax_id = 0;
        }
        $set[] = "product_tax_id = $tax_id";
    }

    if ($has('vendor')) {
        $vendor_id = intval($in['vendor'] ?? 0);
        if ($vendor_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM vendors WHERE vendor_id = $vendor_id AND vendor_client_id = 0"))) {
            $error = 'That vendor is not one of yours.';
            return 0;
        }
        $set[] = "product_vendor_id = $vendor_id";
    }

    // How it is billed - counted per user or device only when it recurs
    $frequency = $has('frequency') ? quoteFrequency($in['frequency'] ?? '') : quoteFrequency($old['product_frequency'] ?? '');
    if ($has('frequency')) {
        $set[] = 'product_frequency = ' . ($frequency === '' ? 'NULL' : "'$frequency'");
    }
    if ($has('seat_source') || $has('frequency')) {
        $seat = $has('seat_source') ? (string) ($in['seat_source'] ?? '') : (string) ($old['product_seat_source'] ?? '');
        if ($frequency === '' || !isset(productSeatSources()[$seat])) {
            $seat = '';
        }
        $set[] = 'product_seat_source = ' . ($seat === '' ? 'NULL' : "'$seat'");
    }

    // Stock is tracked for products only
    if ($has('track_stock') || $has('type')) {
        $track = $has('track_stock') ? !empty($in['track_stock']) : intval($old['product_track_stock']) === 1;
        $set[] = 'product_track_stock = ' . ($type === 'product' && $track ? 1 : 0);
    }

    // When to buy more, and how many
    foreach (['reorder_level' => 'product_reorder_level', 'reorder_qty' => 'product_reorder_qty'] as $key => $column) {
        if (!$has($key)) {
            continue;
        }
        $raw = trim(str_replace([',', ' '], '', (string) ($in[$key] ?? '')));
        if ($raw === '') {
            $raw = '0';
        }
        if (!is_numeric($raw) || floatval($raw) < 0 || floatval($raw) >= 1e9) {
            $error = ($key === 'reorder_level' ? 'Reorder at' : 'Order') . ' has to be a number, zero or more.';
            return 0;
        }
        $set[] = "$column = " . round(floatval($raw), 2);
    }

    foreach (['code' => ['product_code', 200], 'vendor_part' => ['product_vendor_part', 200], 'location' => ['product_location', 250]] as $key => [$column, $length]) {
        if ($has($key)) {
            $value = trim((string) ($in[$key] ?? ''));
            $set[] = "$column = " . ($value === '' ? 'NULL' : "'" . escapeSql(mb_substr($value, 0, $length)) . "'");
        }
    }
    foreach (['description' => 'product_description', 'notes' => 'product_notes'] as $key => $column) {
        if ($has($key)) {
            $value = trim((string) ($in[$key] ?? ''));
            $set[] = "$column = " . ($value === '' ? 'NULL' : "'" . escapeSql($value) . "'");
        }
    }

    if (!$set) {
        return $product_id;
    }
    if ($product_id) {
        mysqli_query($mysqli, "UPDATE products SET " . implode(', ', $set) . " WHERE product_id = $product_id");
        return $product_id;
    }
    $currency = escapeSql($session_company_currency ?? '');
    mysqli_query($mysqli, "INSERT INTO products SET " . implode(', ', $set) . ", product_currency_code = '$currency'");
    return intval(mysqli_insert_id($mysqli));
}

/** Copy a product - its new id. "Name (copy)", with no stock. */
function productCopy($product_id) {
    global $mysqli;
    $product = productRow($product_id);
    if (!$product) {
        return 0;
    }
    $error = '';
    return productSave([
        'name' => mb_substr($product['product_name'] . ' (copy)', 0, 200), 'type' => $product['product_type'], 'category' => $product['product_category_id'],
        'price' => $product['product_price'], 'cost' => $product['product_cost'], 'tax' => $product['product_tax_id'],
        'frequency' => $product['product_frequency'], 'seat_source' => $product['product_seat_source'], 'code' => '',
        'vendor' => $product['product_vendor_id'], 'vendor_part' => $product['product_vendor_part'], 'track_stock' => $product['product_track_stock'],
        'reorder_level' => $product['product_reorder_level'], 'reorder_qty' => $product['product_reorder_qty'],
        'location' => $product['product_location'], 'description' => $product['product_description'], 'notes' => $product['product_notes'],
    ], 0, $error);
}

/** Delete a product and its stock. Lines it is on keep their text and amounts. */
function productDelete($product_id) {
    global $mysqli;
    $product_id = intval($product_id);
    mysqli_query($mysqli, "DELETE FROM product_stock WHERE stock_product_id = $product_id");
    mysqli_query($mysqli, "DELETE FROM products WHERE product_id = $product_id");
}

/**
 * Where a product is used and what it sold - for its page. Scoped to the
 * agent's clients.
 *   recurring  the recurring invoices it is on (not archived), with qty, amount and a month's worth
 *   quotes     open quotes it is on (draft, sent, viewed or accepted)
 *   invoices   the last 50 invoice lines it is on
 *   sold       over the last 12 months of invoices sent (not drafts, cancelled or non-billable): amount, quantity, invoices, clients
 *   monthly    what the recurring lines bring in a month, active ones only, and on how many clients
 */
function productUsage($product_id) {
    global $mysqli;
    $product_id = intval($product_id);
    $scope = clientScopeSql('client_id');
    $usage = ['recurring' => [], 'quotes' => [], 'invoices' => [], 'monthly' => 0.0, 'monthly_clients' => 0];

    $clients = [];
    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_frequency, recurring_invoice_status,
            recurring_invoice_next_date, recurring_invoice_currency_code, client_id, client_name, SUM(item_quantity) AS qty, SUM(item_subtotal) AS amount
        FROM recurring_invoice_items
        JOIN recurring_invoices ON recurring_invoice_id = item_recurring_invoice_id
        JOIN clients ON client_id = recurring_invoice_client_id
        WHERE item_product_id = $product_id AND recurring_invoice_archived_at IS NULL $scope
        GROUP BY recurring_invoice_id
        ORDER BY recurring_invoice_status DESC, client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['monthly'] = recurringInvoiceMonthly($row['amount'], $row['recurring_invoice_frequency']);
        if (intval($row['recurring_invoice_status']) === 1) {
            $usage['monthly'] += $row['monthly'];
            $clients[intval($row['client_id'])] = true;
        }
        $usage['recurring'][] = $row;
    }
    $usage['monthly_clients'] = count($clients);

    $sql = mysqli_query($mysqli, "SELECT quote_id, quote_prefix, quote_number, quote_status, quote_date, quote_expire, quote_currency_code, client_id, client_name,
            SUM(item_quantity) AS qty, SUM(item_subtotal) AS amount, MAX(item_frequency) AS frequency
        FROM quote_items
        JOIN quotes ON quote_id = item_quote_id
        JOIN clients ON client_id = quote_client_id
        WHERE item_product_id = $product_id AND quote_archived_at IS NULL AND quote_status IN ('Draft', 'Sent', 'Viewed', 'Accepted') $scope
        GROUP BY quote_id
        ORDER BY quote_date DESC, quote_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $usage['quotes'][] = $row;
    }

    $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_status, invoice_date, invoice_due, invoice_amount, invoice_currency_code,
            invoice_archived_at, client_id, client_name, item_quantity, item_price, item_subtotal
        FROM invoice_items
        JOIN invoices ON invoice_id = item_invoice_id
        JOIN clients ON client_id = invoice_client_id
        WHERE item_product_id = $product_id $scope
        ORDER BY invoice_date DESC, item_id DESC
        LIMIT 50");
    while ($row = mysqli_fetch_assoc($sql)) {
        $usage['invoices'][] = $row;
    }

    $usage['sold'] = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(item_subtotal), 0) AS amount, COALESCE(SUM(item_quantity), 0) AS qty,
            COUNT(DISTINCT invoice_id) AS invoices, COUNT(DISTINCT client_id) AS clients
        FROM invoice_items
        JOIN invoices ON invoice_id = item_invoice_id
        JOIN clients ON client_id = invoice_client_id
        WHERE item_product_id = $product_id AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable')
            AND invoice_date >= CURDATE() - INTERVAL 12 MONTH $scope"));

    return $usage;
}

/** A product's history from the audit log, newest first. */
function productHistory($product_id, $limit = 100) {
    global $mysqli;
    $product_id = intval($product_id);
    $limit = max(1, intval($limit));
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT log_action, log_description, log_created_at, user_name FROM logs
        LEFT JOIN users ON user_id = log_user_id
        WHERE log_type = 'Product' AND log_entity_id = $product_id
        ORDER BY log_id DESC LIMIT $limit");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/* ------------------------------------------------------------- Stock ledger */

/** Kinds of stock move => label, icon and tone. */
function productStockTypes() {
    return [
        'received' => ['label' => 'Received', 'icon' => 'fa-dolly', 'tone' => 'success'],
        'sold' => ['label' => 'Sold', 'icon' => 'fa-file-invoice-dollar', 'tone' => 'primary'],
        'returned' => ['label' => 'Returned', 'icon' => 'fa-undo', 'tone' => 'info'],
        'adjusted' => ['label' => 'Counted', 'icon' => 'fa-clipboard-check', 'tone' => 'secondary'],
        'written_off' => ['label' => 'Written off', 'icon' => 'fa-minus-circle', 'tone' => 'danger'],
    ];
}

/** Why stock is written off. */
function productWriteOffReasons() {
    return ['Damaged', 'Lost or stolen', 'Used by us', 'Sent back to the vendor', 'Other'];
}

/** What is on the shelf now. */
function productOnHand($product_id) {
    global $mysqli;
    return round(floatval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(stock_qty), 0) FROM product_stock WHERE stock_product_id = " . intval($product_id)))[0]), 2);
}

/** Tracked products at or under their reorder level, or out - in SQL, with products in the FROM. */
function productReorderSql() {
    return "(products.product_track_stock = 1 AND products.product_archived_at IS NULL AND " . productStockSql() . " <= GREATEST(products.product_reorder_level, 0))";
}

/** How many tracked products need ordering. */
function productReorderCount() {
    global $mysqli;
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM products WHERE " . productReorderSql()))[0]);
}

/**
 * Record a stock move. $qty is signed - in above zero, out below. $type is one
 * of productStockTypes(). $opts: note (raw), unit_cost (anything but a
 * delivery takes the product's cost when not given), invoice_id, item_id,
 * expense_id. Tells
 * agents when it takes the product to its reorder level or out. The move's id,
 * or 0 when there is nothing to record.
 */
function productStockMove($product_id, $qty, $type, array $opts = []) {
    global $mysqli, $session_user_id;
    $product_id = intval($product_id);
    $qty = round(floatval($qty), 2);
    if (!$product_id || $qty == 0 || !isset(productStockTypes()[$type])) {
        return 0;
    }
    $before = productOnHand($product_id);
    $unit_cost = $opts['unit_cost'] ?? null;
    // What it cost - given for a delivery; anything else is at the product's cost now
    if (($unit_cost === null || $unit_cost === '') && $type !== 'received') {
        $unit_cost = getFieldById('products', $product_id, 'product_cost');
    }
    $unit_cost = $unit_cost === null || $unit_cost === '' ? 'NULL' : round(floatval($unit_cost), 2);
    $note = escapeSql(mb_substr(trim((string) ($opts['note'] ?? '')), 0, 1000));
    $invoice_id = intval($opts['invoice_id'] ?? 0);
    $item_id = intval($opts['item_id'] ?? 0);
    $expense_id = intval($opts['expense_id'] ?? 0);
    $user_id = intval($session_user_id ?? 0);
    mysqli_query($mysqli, "INSERT INTO product_stock SET stock_qty = $qty, stock_type = '$type', stock_unit_cost = $unit_cost, stock_note = '$note',
        stock_expense_id = " . ($expense_id ?: 'NULL') . ", stock_invoice_id = $invoice_id, stock_item_id = " . ($item_id ?: 'NULL') . ",
        stock_user_id = $user_id, stock_product_id = $product_id");
    $stock_id = intval(mysqli_insert_id($mysqli));
    productReorderCheck($product_id, $before, round($before + $qty, 2));
    return $stock_id;
}

/** Tell agents when stock going out reaches the reorder level, or runs out. */
function productReorderCheck($product_id, $before, $after) {
    if ($after >= $before) {
        return;
    }
    $product = productRow($product_id);
    if (!$product || !intval($product['product_track_stock'])) {
        return;
    }
    $level = floatval($product['product_reorder_level']);
    $name = $product['product_name'];
    if ($after <= 0 && $before > 0) {
        $text = "$name is out of stock";
    } elseif ($level > 0 && $before > $level && $after <= $level) {
        $text = "$name is down to " . billingQuantity($after) . " - reorder at " . billingQuantity($level);
    } else {
        return;
    }
    if (floatval($product['product_reorder_qty']) > 0) {
        $text .= ', order ' . billingQuantity($product['product_reorder_qty']);
    }
    if ($product['vendor_name']) {
        $text .= ' from ' . $product['vendor_name'];
    }
    appNotify('Low Stock', escapeSql($text), 'product.php?product_id=' . intval($product['product_id']), 0, intval($product['product_id']));
}

/**
 * Put back what an invoice took off the shelf and still has - when it is
 * cancelled or deleted. $why is the note, raw. The number of products returned.
 */
function productStockReturnInvoice($invoice_id, $why) {
    global $mysqli;
    $invoice_id = intval($invoice_id);
    $returned = 0;
    $sql = mysqli_query($mysqli, "SELECT stock_product_id, SUM(stock_qty) AS net FROM product_stock WHERE stock_invoice_id = $invoice_id GROUP BY stock_product_id HAVING net < 0");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (productStockMove($row['stock_product_id'], -floatval($row['net']), 'returned', ['invoice_id' => $invoice_id, 'note' => $why])) {
            $returned++;
        }
    }
    return $returned;
}

/**
 * Lines about to go on an invoice that take tracked products past what is on
 * the shelf - the first one as a message, or '' when there is enough of all.
 */
function productStockShortfall(array $items) {
    global $mysqli;
    $needed = [];
    foreach ($items as $item) {
        $id = intval($item['item_product_id'] ?? 0);
        if ($id && floatval($item['item_quantity']) > 0) {
            $needed[$id] = ($needed[$id] ?? 0) + floatval($item['item_quantity']);
        }
    }
    if (!$needed) {
        return '';
    }
    $sql = mysqli_query($mysqli, "SELECT product_id, product_name, " . productStockSql() . " AS on_hand FROM products
        WHERE product_track_stock = 1 AND product_id IN (" . implode(',', array_keys($needed)) . ")");
    while ($row = mysqli_fetch_assoc($sql)) {
        $want = round($needed[intval($row['product_id'])], 2);
        if (floatval($row['on_hand']) < $want) {
            return 'Not enough ' . $row['product_name'] . ' in stock - ' . billingQuantity($row['on_hand']) . ' left, ' . billingQuantity($want) . ' needed.';
        }
    }
    return '';
}

/**
 * A product's stock moves, newest first, with the balance after each, the
 * invoice or expense behind it and who made it. Invoices of clients the agent
 * can't open have no link ('invoice_open' 0).
 */
function productStockMoves($product_id, $limit = 25) {
    global $mysqli;
    $product_id = intval($product_id);
    $limit = max(1, intval($limit));
    $balance = productOnHand($product_id);
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT stock_id, stock_qty, stock_type, stock_unit_cost, stock_note, stock_created_at, stock_expense_id, stock_invoice_id,
            expense_description, expense_date, expense_amount, expense_currency_code, invoice_prefix, invoice_number, invoice_client_id, user_name
        FROM product_stock
        LEFT JOIN expenses ON expense_id = stock_expense_id
        LEFT JOIN invoices ON invoice_id = stock_invoice_id AND stock_invoice_id > 0
        LEFT JOIN users ON user_id = stock_user_id AND stock_user_id > 0
        WHERE stock_product_id = $product_id
        ORDER BY stock_id DESC LIMIT $limit");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['balance'] = $balance;
        $balance = round($balance - floatval($row['stock_qty']), 2);
        $row['invoice_open'] = $row['invoice_number'] !== null && hasClientAccess(intval($row['invoice_client_id'])) ? 1 : 0;
        $rows[] = $row;
    }
    return $rows;
}

/** A move's quantity with its sign - "+4", "-2", "+1.5". */
function productStockQty($qty) {
    $qty = floatval($qty);
    return ($qty > 0 ? '+' : '') . billingQuantity($qty);
}

/**
 * The margin on a document's lines that come from a product with a cost, at
 * today's cost - for the agent's eyes only. By how often each line is billed
 * for a quote ('' once, month, quarter, year), otherwise under ''. Each:
 * ['cost', 'revenue', 'margin', 'percent', 'lines']. Empty when no line has a
 * cost to go on.
 */
function billingMargin($type, array $items) {
    global $mysqli;
    $ids = [];
    foreach ($items as $item) {
        if (intval($item['item_product_id'] ?? 0)) {
            $ids[intval($item['item_product_id'])] = true;
        }
    }
    if (!$ids) {
        return [];
    }
    $costs = [];
    $sql = mysqli_query($mysqli, "SELECT product_id, product_cost FROM products WHERE product_cost > 0 AND product_id IN (" . implode(',', array_keys($ids)) . ")");
    while ($row = mysqli_fetch_assoc($sql)) {
        $costs[intval($row['product_id'])] = floatval($row['product_cost']);
    }
    $groups = [];
    foreach ($items as $item) {
        $id = intval($item['item_product_id'] ?? 0);
        if (!isset($costs[$id])) {
            continue;
        }
        $key = $type === 'quote' ? quoteFrequency($item['item_frequency'] ?? '') : '';
        $groups[$key] = $groups[$key] ?? ['cost' => 0.0, 'revenue' => 0.0, 'lines' => 0];
        $groups[$key]['cost'] += round(floatval($item['item_quantity']) * $costs[$id], 2);
        $groups[$key]['revenue'] += floatval($item['item_subtotal']);
        $groups[$key]['lines']++;
    }
    $order = array_flip(array_keys(quoteFrequencies()));
    uksort($groups, function ($a, $b) use ($order) {
        return ($order[$a] ?? 9) <=> ($order[$b] ?? 9);
    });
    foreach ($groups as $key => $group) {
        $groups[$key]['margin'] = round($group['revenue'] - $group['cost'], 2);
        $groups[$key]['percent'] = $group['revenue'] > 0 ? round(($group['revenue'] - $group['cost']) / $group['revenue'] * 100, 1) : null;
    }
    return $groups;
}

/** A document's margin for its sidebar - "$1,203.00 (41%) up front, $612.00 (30%) a month". '' when none. */
function billingMarginHtml($type, array $items, $currency, $period = '') {
    $groups = billingMargin($type, $items);
    if (!$groups) {
        return '';
    }
    $parts = [];
    $lines = 0;
    foreach ($groups as $key => $group) {
        $lines += $group['lines'];
        $when = $type === 'quote' ? ($key === '' ? ' up front' : ' a ' . recurringInvoicePeriodWord($key)) : ($period !== '' ? " a $period" : '');
        $percent = $group['percent'] === null ? '' : ' <span class="text-body-secondary">(' . escapeHtml(rtrim(rtrim(number_format($group['percent'], 1), '0'), '.')) . '%)</span>';
        $tone = $group['margin'] < 0 ? 'text-danger-emphasis' : '';
        $parts[] = '<span class="' . $tone . '">' . escapeHtml(billingMoney($group['margin'], $currency)) . '</span>' . $percent . escapeHtml($when);
    }
    return '<span title="On the ' . $lines . ' line' . ($lines === 1 ? '' : 's') . ' from products with a cost, at today\'s cost - only you see this">' . implode(', ', $parts) . '</span>';
}
