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
 * ordered as needed. Hardware can become a client asset when it is sold -
 * the invoice offers to add each unit to the client's assets.
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
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT products.*, category_name, category_color, category_icon, tax_name, tax_percent, tax_archived_at, vendor_name,
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
 *   asset_type (products only - one of the asset types, '' for none), make,
 *   model, warranty_months, location, description, notes.
 * A change to an existing product's price or cost goes in its price history,
 * from $source - edit, import or receive.
 * Returns the product id, or 0 with $error.
 */
function productSave(array $in, $product_id = 0, &$error = '', $source = 'edit') {
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

    // Hardware that becomes a client asset when sold - products only
    if ($has('asset_type') || $has('type')) {
        $asset_type = $has('asset_type') ? trim((string) ($in['asset_type'] ?? '')) : (string) ($old['product_asset_type'] ?? '');
        if (in_array(strtolower($asset_type), ['', 'none', 'no', '-'], true)) {
            $asset_type = '';
        } else {
            $asset_type = productAssetType($asset_type);
            if ($asset_type === '') {
                $error = "\"" . trim((string) ($in['asset_type'] ?? '')) . "\" isn't an asset type - use one of " . implode(', ', array_keys(productAssetTypes())) . '.';
                return 0;
            }
        }
        if ($type !== 'product') {
            $asset_type = '';
        }
        $set[] = 'product_asset_type = ' . ($asset_type === '' ? 'NULL' : "'" . escapeSql($asset_type) . "'");
    }
    if ($has('warranty_months')) {
        $raw = trim((string) ($in['warranty_months'] ?? ''));
        if ($raw === '') {
            $raw = '0';
        }
        if (!ctype_digit($raw) || intval($raw) > 600) {
            $error = 'Warranty is a number of months, up to 600.';
            return 0;
        }
        $set[] = 'product_warranty_months = ' . intval($raw);
    }

    foreach (['code' => ['product_code', 200], 'vendor_part' => ['product_vendor_part', 200], 'location' => ['product_location', 250], 'make' => ['product_make', 200], 'model' => ['product_model', 200]] as $key => [$column, $length]) {
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
        productPriceRecord($product_id, $old, $source);
        return $product_id;
    }
    $currency = escapeSql($session_company_currency ?? '');
    mysqli_query($mysqli, "INSERT INTO products SET " . implode(', ', $set) . ", product_currency_code = '$currency'");
    return intval(mysqli_insert_id($mysqli));
}

/** Where a price change came from => label. */
function productPriceSources() {
    return ['edit' => 'Edited', 'import' => 'Imported', 'receive' => 'Stock received'];
}

/** Put a change to a product's price or cost in its history - $old is the product before. */
function productPriceRecord($product_id, array $old, $source = 'edit') {
    global $mysqli, $session_user_id;
    $product_id = intval($product_id);
    $now = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT product_price, product_cost FROM products WHERE product_id = $product_id"));
    if (!$now || (round(floatval($now['product_price']), 2) == round(floatval($old['product_price']), 2) && round(floatval($now['product_cost']), 2) == round(floatval($old['product_cost']), 2))) {
        return;
    }
    $source = isset(productPriceSources()[$source]) ? $source : 'edit';
    mysqli_query($mysqli, "INSERT INTO product_prices SET price_product_id = $product_id,
        price_old = " . round(floatval($old['product_price']), 2) . ", price_new = " . round(floatval($now['product_price']), 2) . ",
        price_cost_old = " . round(floatval($old['product_cost']), 2) . ", price_cost_new = " . round(floatval($now['product_cost']), 2) . ",
        price_source = '$source', price_user_id = " . intval($session_user_id ?? 0));
}

/** A product's price changes, newest first, with who made them. */
function productPrices($product_id, $limit = 100) {
    global $mysqli;
    $product_id = intval($product_id);
    $limit = max(1, intval($limit));
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT product_prices.*, user_name FROM product_prices
        LEFT JOIN users ON user_id = price_user_id
        WHERE price_product_id = $product_id
        ORDER BY price_created_at DESC, price_id DESC LIMIT $limit");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
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
        'asset_type' => $product['product_asset_type'], 'make' => $product['product_make'], 'model' => $product['product_model'], 'warranty_months' => $product['product_warranty_months'],
        'location' => $product['product_location'], 'description' => $product['product_description'], 'notes' => $product['product_notes'],
    ], 0, $error);
}

/** Delete a product, its stock and its price history. Lines it is on keep their text and amounts; license templates sold as it no longer are. */
function productDelete($product_id) {
    global $mysqli;
    $product_id = intval($product_id);
    mysqli_query($mysqli, "UPDATE software_templates SET software_template_product_id = 0, software_template_updated_at = software_template_updated_at WHERE software_template_product_id = $product_id");
    mysqli_query($mysqli, "DELETE FROM product_stock WHERE stock_product_id = $product_id");
    mysqli_query($mysqli, "DELETE FROM product_prices WHERE price_product_id = $product_id");
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

/*
 * Sold hardware becomes client assets. A product with an asset type is
 * hardware: when it is sold on an invoice, each unit can go on the client's
 * assets - as a new asset, or one already on file linked to the sale - with
 * its serial and who has it, the make and model, bought on the invoice date
 * and under warranty for the product's months from it. The asset keeps the
 * product, the invoice and the line, so each line knows how many of its
 * units are recorded.
 */

/** Asset types => icon class (fa-laptop) - the list in load_global_settings.php. */
function productAssetTypes() {
    global $asset_types_array;
    return is_array($asset_types_array ?? null) ? $asset_types_array : [];
}

/** An asset type as the list spells it, matched without case - '' when it isn't one. */
function productAssetType($value) {
    $value = trim((string) $value);
    foreach (array_keys(productAssetTypes()) as $asset_type) {
        if (strcasecmp($asset_type, $value) === 0) {
            return $asset_type;
        }
    }
    return '';
}

/** An asset type's round icon, as on the assets list - $size '' or sm. */
function productAssetIcon($asset_type, $size = '') {
    return '<span class="itflow-asset-icon' . ($size ? " itflow-asset-icon-$size" : '') . ' itflow-asset-tone-' . assetTypeTone($asset_type) . '" aria-hidden="true">'
        . '<i class="fas fa-fw fa-' . escapeHtml(getAssetIcon($asset_type)) . '"></i></span>';
}

/** Hardware, in SQL - with products in the FROM. */
function productHardwareSql() {
    return "products.product_type = 'product' AND products.product_asset_type IS NOT NULL AND products.product_asset_type <> ''";
}

/** How many units a line stands for - its quantity rounded up, none for a credit. */
function productHardwareUnits($qty) {
    $qty = round(floatval($qty), 2);
    return $qty > 0 ? (int) ceil($qty) : 0;
}

/** When a warranty of $months from $date (Y-m-d) runs out - the 31st becomes the month's last day. Null for none. */
function productWarrantyUntil($date, $months) {
    $months = intval($months);
    if ($months <= 0 || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $date, $m)) {
        return null;
    }
    $month = intval($m[2]) - 1 + $months;
    $year = intval($m[1]) + intdiv($month, 12);
    $month = $month % 12 + 1;
    $day = min(intval($m[3]), intval(date('t', mktime(0, 0, 0, $month, 1, $year))));
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/**
 * The invoices whose hardware counts as sold and waiting to be recorded, in
 * SQL with invoices in the FROM: not drafts or cancelled, and not made by a
 * recurring invoice (hardware billed each month is rented, not sold).
 */
function productHardwareInvoiceSql() {
    return "invoices.invoice_status NOT IN ('Draft', 'Cancelled') AND invoices.invoice_recurring_invoice_id = 0";
}

/** Lines with units still to add to assets - a condition on invoice_items joined to products. */
function productHardwareShortSql() {
    return productHardwareSql() . " AND invoice_items.item_quantity > 0
        AND CEIL(ROUND(invoice_items.item_quantity, 2)) > (SELECT COUNT(*) FROM assets
            WHERE assets.asset_invoice_id = invoice_items.item_invoice_id AND assets.asset_invoice_item_id = invoice_items.item_id)";
}

/**
 * Hardware sold in the last $days days, up to today, that isn't on the
 * client's assets yet: ['units', 'invoices']. Scoped to the agent's clients.
 */
function productHardwarePending($days = 90) {
    global $mysqli;
    $days = max(1, intval($days));
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(units - made), 0) AS units, COUNT(DISTINCT invoice_id) AS invoices FROM (
            SELECT invoice_id, CEIL(ROUND(item_quantity, 2)) AS units,
                (SELECT COUNT(*) FROM assets WHERE asset_invoice_id = invoice_id AND asset_invoice_item_id = item_id) AS made
            FROM invoice_items
            JOIN invoices ON invoice_id = item_invoice_id
            JOIN products ON product_id = item_product_id
            WHERE " . productHardwareSql() . " AND item_quantity > 0 AND " . productHardwareInvoiceSql() . "
                AND invoice_date BETWEEN CURDATE() - INTERVAL $days DAY AND CURDATE() " . clientScopeSql('invoice_client_id') . "
        ) AS hardware WHERE units > made"));
    return ['units' => intval($row['units'] ?? 0), 'invoices' => intval($row['invoices'] ?? 0)];
}

/**
 * An invoice's hardware lines and the assets made from each - for its
 * Hardware card and the Add to Assets window. Each line: item_id, item_name,
 * item_description, item_quantity, units, product_id, product_name,
 * product_asset_type, product_make, product_model, product_warranty_months,
 * assets (asset_id, asset_name, asset_serial, asset_type, asset_status,
 * asset_client_id, asset_archived_at) and to_add.
 */
function productInvoiceHardware($invoice_id) {
    global $mysqli;
    $invoice_id = intval($invoice_id);
    $lines = [];
    $sql = mysqli_query($mysqli, "SELECT item_id, item_name, item_description, item_quantity, product_id, product_name,
            product_asset_type, product_make, product_model, product_warranty_months
        FROM invoice_items
        JOIN products ON product_id = item_product_id
        WHERE item_invoice_id = $invoice_id AND " . productHardwareSql() . "
        ORDER BY item_order ASC, item_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['units'] = productHardwareUnits($row['item_quantity']);
        $row['assets'] = [];
        $lines[intval($row['item_id'])] = $row;
    }
    if ($lines) {
        $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_serial, asset_type, asset_status, asset_client_id, asset_archived_at, asset_invoice_item_id
            FROM assets
            WHERE asset_invoice_id = $invoice_id AND asset_invoice_item_id IN (" . implode(', ', array_keys($lines)) . ")
            ORDER BY asset_name ASC, asset_id ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $lines[intval($row['asset_invoice_item_id'])]['assets'][] = $row;
        }
    }
    foreach ($lines as &$line) {
        $line['to_add'] = max(0, $line['units'] - count($line['assets']));
    }
    unset($line);
    return array_values($lines);
}

/** Assets made from a product, newest first - its installed base. Scoped to the agent's clients. */
function productAssets($product_id, $limit = 200) {
    global $mysqli;
    $product_id = intval($product_id);
    $limit = max(1, intval($limit));
    $assets = [];
    $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_serial, asset_type, asset_status, asset_warranty_expire, asset_purchase_date, asset_archived_at,
            client_id, client_name, invoice_id, invoice_prefix, invoice_number, invoice_date
        FROM assets
        JOIN clients ON client_id = asset_client_id
        LEFT JOIN invoices ON invoice_id = asset_invoice_id AND asset_invoice_id > 0
        WHERE asset_product_id = $product_id " . clientScopeSql('asset_client_id') . "
        ORDER BY asset_archived_at IS NOT NULL, asset_purchase_date DESC, asset_id DESC
        LIMIT $limit");
    while ($row = mysqli_fetch_assoc($sql)) {
        $assets[] = $row;
    }
    return $assets;
}

/** The client's assets that could be the hardware sold - not archived, not from a sale already. Same type first. */
function productAssetCandidates($client_id, $asset_type = '') {
    global $mysqli;
    $client_id = intval($client_id);
    $asset_type = escapeSql($asset_type);
    $assets = [];
    $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_serial, asset_make, asset_model
        FROM assets
        WHERE asset_client_id = $client_id AND asset_archived_at IS NULL AND asset_invoice_item_id = 0
        ORDER BY asset_type = '$asset_type' DESC, asset_type ASC, asset_name ASC
        LIMIT 300");
    while ($row = mysqli_fetch_assoc($sql)) {
        $assets[] = $row;
    }
    return $assets;
}

/**
 * Put an invoice's hardware on the client's assets. Raw input:
 *   $lines   item_id => ['make', 'model', 'warranty' (Y-m-d, '' for none)]
 *   $units   item_id => list of ['name', 'serial', 'contact', 'existing']
 *            - existing is one of the client's assets to link instead of adding one
 *   $shared  ['status', 'location'] for every one added
 * Only hardware lines still short are used, and no more units than each is
 * short. Returns ['added' => n, 'linked' => n], or null with $error.
 */
function productSaleAssets($invoice_id, array $lines, array $units, array $shared, &$error = '') {
    global $mysqli, $session_name;
    $error = '';
    $invoice = billingRow('invoice', $invoice_id);
    if (!$invoice) {
        $error = 'That invoice no longer exists.';
        return null;
    }
    $invoice_id = intval($invoice['invoice_id']);
    $client_id = intval($invoice['client_id']);
    $number = billingNumber('invoice', $invoice);
    if ($invoice['invoice_status'] === 'Cancelled') {
        $error = "$number is cancelled - nothing on it was sold.";
        return null;
    }
    $date = substr((string) $invoice['invoice_date'], 0, 10);

    $status = trim((string) ($shared['status'] ?? ''));
    if ($status !== '' && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM categories WHERE category_type = 'asset_status' AND category_name = '" . escapeSql($status) . "'"))) {
        $status = '';
    }
    $location_id = assetClientOwnedId('location', $shared['location'] ?? 0, $client_id);

    // Check it all before anything is written
    $plan = [];
    $picked = [];
    foreach (productInvoiceHardware($invoice_id) as $line) {
        $item_id = intval($line['item_id']);
        $rows = array_values(array_filter((array) ($units[$item_id] ?? []), 'is_array'));
        if (!$rows || !$line['to_add']) {
            continue;
        }
        $rows = array_slice($rows, 0, $line['to_add']);
        $line_in = (array) ($lines[$item_id] ?? []);
        $make = trim((string) ($line_in['make'] ?? $line['product_make']));
        $model = trim((string) ($line_in['model'] ?? $line['product_model']));
        $warranty = trim((string) ($line_in['warranty'] ?? ''));
        if ($warranty !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $warranty) || !strtotime($warranty))) {
            $error = "The warranty date for {$line['item_name']} isn't a date.";
            return null;
        }
        foreach ($rows as $n => $row) {
            $existing = intval($row['existing'] ?? 0);
            if ($existing) {
                $asset = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_invoice_item_id FROM assets WHERE asset_id = $existing AND asset_client_id = $client_id"));
                if (!$asset || intval($asset['asset_invoice_item_id']) || isset($picked[$existing])) {
                    $error = isset($picked[$existing]) ? "{$asset['asset_name']} is picked twice." : "An asset picked for {$line['item_name']} isn't one of {$invoice['client_name']}'s, or came from another sale.";
                    return null;
                }
                $picked[$existing] = true;
                $plan[] = ['line' => $line, 'existing' => $existing, 'name' => $asset['asset_name'], 'make' => $make, 'model' => $model, 'warranty' => $warranty];
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $error = "Each new asset needs a name - " . ($n + 1) . " of {$line['item_name']} has none.";
                return null;
            }
            $plan[] = ['line' => $line, 'existing' => 0, 'name' => mb_substr($name, 0, 200), 'serial' => mb_substr(trim((string) ($row['serial'] ?? '')), 0, 200),
                'contact' => assetClientOwnedId('contact', $row['contact'] ?? 0, $client_id), 'make' => $make, 'model' => $model, 'warranty' => $warranty];
        }
    }
    if (!$plan) {
        $error = "Nothing to add - every unit of hardware on $number is on {$invoice['client_name']}'s assets.";
        return null;
    }

    $by = stripslashes((string) $session_name);
    $ref = escapeSql(mb_substr($number, 0, 200));
    $number_sql = escapeSql($number);
    $done = ['added' => 0, 'linked' => 0];
    mysqli_begin_transaction($mysqli);
    try {
        foreach ($plan as $unit) {
            $line = $unit['line'];
            $item_id = intval($line['item_id']);
            $product_id = intval($line['product_id']);
            $name = escapeSql($unit['name']);
            $make = escapeSql(mb_substr($unit['make'], 0, 200));
            $model = $unit['model'] === '' ? 'NULL' : "'" . escapeSql(mb_substr($unit['model'], 0, 200)) . "'";
            $warranty = $unit['warranty'] === '' ? 'NULL' : "'" . escapeSql($unit['warranty']) . "'";
            $status_sql = escapeSql($status);

            if ($unit['existing']) {
                $asset_id = $unit['existing'];
                mysqli_query($mysqli, "UPDATE assets SET asset_product_id = $product_id, asset_invoice_id = $invoice_id, asset_invoice_item_id = $item_id,
                        asset_purchase_date = COALESCE(asset_purchase_date, '$date'),
                        asset_warranty_expire = COALESCE(asset_warranty_expire, $warranty),
                        asset_purchase_reference = IF(COALESCE(asset_purchase_reference, '') = '', '$ref', asset_purchase_reference),
                        asset_make = IF(asset_make = '', '$make', asset_make),
                        asset_model = IF(COALESCE(asset_model, '') = '', $model, asset_model)
                    WHERE asset_id = $asset_id");
                $history = escapeSql(mb_substr("$by linked {$unit['name']} to $number, where it was sold", 0, 255));
                mysqli_query($mysqli, "INSERT INTO asset_history SET asset_history_status = COALESCE((SELECT asset_status FROM assets WHERE asset_id = $asset_id), ''), asset_history_description = '$history', asset_history_asset_id = $asset_id");
                logAudit("Asset", "Edit", "$session_name linked asset $name to invoice $number_sql", $client_id, $asset_id);
                $done['linked']++;
                continue;
            }

            $type = escapeSql($line['product_asset_type']);
            $serial = $unit['serial'] === '' ? 'NULL' : "'" . escapeSql($unit['serial']) . "'";
            $description = trim((string) $line['item_description']);
            $description = $description === '' ? 'NULL' : "'" . escapeSql(mb_substr($description, 0, 255)) . "'";
            $contact_id = intval($unit['contact']);
            mysqli_query($mysqli, "INSERT INTO assets SET asset_type = '$type', asset_name = '$name', asset_description = $description, asset_make = '$make', asset_model = $model,
                asset_serial = $serial, asset_status = '$status_sql', asset_purchase_reference = '$ref', asset_purchase_date = '$date', asset_warranty_expire = $warranty,
                asset_location_id = $location_id, asset_contact_id = $contact_id, asset_client_id = $client_id,
                asset_product_id = $product_id, asset_invoice_id = $invoice_id, asset_invoice_item_id = $item_id");
            $asset_id = intval(mysqli_insert_id($mysqli));
            mysqli_query($mysqli, "INSERT INTO asset_interfaces SET interface_name = '01', interface_primary = 1, interface_asset_id = $asset_id");
            $history = escapeSql(mb_substr("$by created {$unit['name']} from $number", 0, 255));
            mysqli_query($mysqli, "INSERT INTO asset_history SET asset_history_status = '$status_sql', asset_history_description = '$history', asset_history_asset_id = $asset_id");
            logAudit("Asset", "Create", "$session_name created asset $name from invoice $number_sql", $client_id, $asset_id);
            $done['added']++;
        }
        mysqli_commit($mysqli);
    } catch (Throwable $e) {
        mysqli_rollback($mysqli);
        throw $e;
    }
    return $done;
}

/*
 * Price changes reach the documents. When a product's price changes, its
 * lines on recurring invoices, draft invoices and draft quotes keep the price
 * they had. Those still at one of the product's old prices are listed to
 * update. A price the product never had looks agreed with the client, so
 * those lines are listed apart and left alone unless picked.
 */

/** The documents whose lines can follow a price change => label and icon. */
function productRepriceKinds() {
    return [
        'recurring' => ['label' => 'Recurring invoices', 'one' => 'Recurring invoice', 'icon' => 'fa-redo-alt'],
        'invoice' => ['label' => 'Draft invoices', 'one' => 'Draft invoice', 'icon' => 'fa-file-invoice-dollar'],
        'quote' => ['label' => 'Draft quotes', 'one' => 'Draft quote', 'icon' => 'fa-comment-dollar'],
    ];
}

/**
 * Every line from a product that can still change price, in SQL to alias:
 * kind, item_id, doc_id, number, status, client_id, item_name, item_price,
 * item_quantity, item_product_id, frequency and currency. Recurring invoices
 * not archived, draft invoices and draft quotes.
 */
function productRepriceUnionSql() {
    return "SELECT 'recurring' AS kind, item_id, recurring_invoice_id AS doc_id, CONCAT(recurring_invoice_prefix, recurring_invoice_number) AS number,
                IF(recurring_invoice_status = 1, 'Active', 'Paused') AS status, recurring_invoice_client_id AS client_id, item_name, item_price, item_quantity,
                item_product_id, recurring_invoice_frequency AS frequency, recurring_invoice_currency_code AS currency
            FROM recurring_invoice_items JOIN recurring_invoices ON recurring_invoice_id = item_recurring_invoice_id
            WHERE recurring_invoice_archived_at IS NULL AND item_product_id > 0
        UNION ALL
        SELECT 'invoice', item_id, invoice_id, CONCAT(invoice_prefix, invoice_number), invoice_status, invoice_client_id, item_name, item_price, item_quantity,
                item_product_id, NULL, invoice_currency_code
            FROM invoice_items JOIN invoices ON invoice_id = item_invoice_id
            WHERE invoice_status = 'Draft' AND invoice_archived_at IS NULL AND item_product_id > 0
        UNION ALL
        SELECT 'quote', item_id, quote_id, CONCAT(quote_prefix, quote_number), quote_status, quote_client_id, item_name, item_price, item_quantity,
                item_product_id, item_frequency, quote_currency_code
            FROM quote_items JOIN quotes ON quote_id = item_quote_id
            WHERE quote_status = 'Draft' AND quote_archived_at IS NULL AND item_product_id > 0";
}

/** A line (the union aliased l) at one of its product's old prices, in SQL - with products joined. */
function productRepriceOldSql() {
    return "EXISTS (SELECT 1 FROM product_prices WHERE price_product_id = l.item_product_id AND price_old = l.item_price)";
}

/** The filters the Update Prices page takes, as SQL on the union aliased l. */
function productRepriceFilterSql(array $filters) {
    $sql = '';
    if (!empty($filters['product_id'])) {
        $sql .= ' AND l.item_product_id = ' . intval($filters['product_id']);
    }
    if (!empty($filters['client_id'])) {
        $sql .= ' AND l.client_id = ' . intval($filters['client_id']);
    }
    if (!empty($filters['kind']) && isset(productRepriceKinds()[$filters['kind']])) {
        $sql .= " AND l.kind = '{$filters['kind']}'";
        if (!empty($filters['doc_id'])) {
            $sql .= ' AND l.doc_id = ' . intval($filters['doc_id']);
        }
    }
    return $sql;
}

/**
 * How many lines are at an old price: ['lines', 'recurring', 'invoice',
 * 'quote', 'products', 'monthly' - what updating the recurring ones would
 * add a month at today's prices]. Same filters as productRepriceLines().
 * Scoped to the agent's clients, archived products left out.
 */
function productRepriceCount(array $filters = []) {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS `lines`, COALESCE(SUM(l.kind = 'recurring'), 0) AS recurring,
            COALESCE(SUM(l.kind = 'invoice'), 0) AS invoice, COALESCE(SUM(l.kind = 'quote'), 0) AS quote, COUNT(DISTINCT l.item_product_id) AS products,
            COALESCE(SUM(IF(l.kind = 'recurring', " . recurringInvoiceMonthlySql('(products.product_price - l.item_price) * l.item_quantity', 'l.frequency') . ", 0)), 0) AS monthly
        FROM (" . productRepriceUnionSql() . ") AS l
        JOIN products ON products.product_id = l.item_product_id
        WHERE products.product_archived_at IS NULL AND l.item_price <> products.product_price AND " . productRepriceOldSql()
            . productRepriceFilterSql($filters) . clientScopeSql('l.client_id')));
    return array_map('floatval', $row ?: []) + ['lines' => 0, 'recurring' => 0, 'invoice' => 0, 'quote' => 0, 'products' => 0, 'monthly' => 0];
}

/** A product's last change of price - price_old, price_new, price_created_at - or null. */
function productPriceLastChange($product_id) {
    global $mysqli;
    $product_id = intval($product_id);
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT price_old, price_new, price_created_at FROM product_prices
        WHERE price_product_id = $product_id AND price_old <> price_new ORDER BY price_created_at DESC, price_id DESC LIMIT 1")) ?: null;
}

/**
 * The lines whose price isn't their product's, for the Update Prices page,
 * by product: product_id => [product_id, product_name, product_type,
 * product_price, product_currency_code, 'last' => productPriceLastChange(),
 * 'lines' => [...]]. Each line is a productRepriceUnionSql() row with
 * client_name, old (at one of the product's old prices) and suggested - the
 * product's price, or for a price of its own the last change applied to it.
 * Filters: product_id, client_id, kind and doc_id; up to 1000 lines.
 */
function productRepriceLines(array $filters = []) {
    global $mysqli;
    $groups = [];
    $sql = mysqli_query($mysqli, "SELECT l.*, client_name, products.product_id, product_name, product_type, product_price, product_currency_code, " . productRepriceOldSql() . " AS old
        FROM (" . productRepriceUnionSql() . ") AS l
        JOIN products ON products.product_id = l.item_product_id
        JOIN clients ON clients.client_id = l.client_id
        WHERE products.product_archived_at IS NULL AND l.item_price <> products.product_price" . productRepriceFilterSql($filters) . clientScopeSql('l.client_id') . "
        ORDER BY product_name ASC, products.product_id ASC, FIELD(l.kind, 'recurring', 'invoice', 'quote'), client_name ASC, l.doc_id ASC, l.item_id ASC
        LIMIT 1000");
    while ($row = mysqli_fetch_assoc($sql)) {
        $product_id = intval($row['product_id']);
        if (!isset($groups[$product_id])) {
            $groups[$product_id] = [
                'product_id' => $product_id, 'product_name' => $row['product_name'], 'product_type' => $row['product_type'], 'product_price' => $row['product_price'],
                'product_currency_code' => $row['product_currency_code'], 'last' => productPriceLastChange($product_id), 'lines' => [],
            ];
        }
        $last = $groups[$product_id]['last'];
        $row['old'] = intval($row['old']) === 1;
        $row['suggested'] = !$row['old'] && $last && floatval($last['price_old']) > 0
            ? round(floatval($row['item_price']) * floatval($last['price_new']) / floatval($last['price_old']), 2)
            : round(floatval($row['product_price']), 2);
        $groups[$product_id]['lines'][] = $row;
    }
    return $groups;
}

/**
 * Change the price of lines the Update Prices page lists. $changes: "kind:item_id"
 * => the new price, raw. Every price is checked first. Each document's total
 * follows, and its history says what changed. Returns ['lines' => n,
 * 'documents' => n, 'monthly' => the change a month on recurring invoices],
 * or null with $error.
 */
function productRepriceApply(array $changes, &$error = '') {
    global $mysqli, $session_name;
    $error = '';
    $plan = [];
    foreach ($changes as $key => $raw) {
        if (!preg_match('/^(recurring|invoice|quote):(\d+)$/', (string) $key, $m)) {
            continue;
        }
        [$kind, $item_id] = [$m[1], intval($m[2])];
        $line = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT l.* FROM (" . productRepriceUnionSql() . ") AS l
            JOIN products ON products.product_id = l.item_product_id
            WHERE l.kind = '$kind' AND l.item_id = $item_id AND products.product_archived_at IS NULL" . clientScopeSql('l.client_id')));
        if (!$line) {
            $error = 'A line picked is no longer on a recurring invoice, draft or draft quote you can change - nothing was updated.';
            return null;
        }
        $price = billingNumberInput($raw);
        if ($price === null || $price < 0 || $price >= 1e11) {
            $error = "The new price for {$line['item_name']} on {$line['number']} has to be a number, zero or more - nothing was updated.";
            return null;
        }
        if (round($price, 2) != round(floatval($line['item_price']), 2)) {
            $plan[] = [$line, $price];
        }
    }
    if (!$plan) {
        $error = 'Nothing to update - pick the lines to change, with a new price.';
        return null;
    }

    $by = stripslashes((string) $session_name);
    $done = ['lines' => 0, 'documents' => 0, 'monthly' => 0.0];
    $documents = [];
    mysqli_begin_transaction($mysqli);
    try {
        foreach ($plan as [$line, $price]) {
            $kind = $line['kind'];
            billingItemSetPrice($kind, $line['doc_id'], $line['item_id'], $price);
            $from = billingMoney($line['item_price'], $line['currency']);
            $to = billingMoney($price, $line['currency']);
            billingLog($kind, $line['doc_id'], $kind === 'recurring' ? 'Price' : $line['status'], "{$line['item_name']} from $from to $to by $by - a catalog price change");
            if ($kind === 'recurring') {
                $done['monthly'] += recurringInvoiceMonthly(($price - floatval($line['item_price'])) * floatval($line['item_quantity']), $line['frequency']);
            }
            $documents["$kind:{$line['doc_id']}"] = true;
            $done['lines']++;
        }
        mysqli_commit($mysqli);
    } catch (Throwable $e) {
        mysqli_rollback($mysqli);
        throw $e;
    }
    $done['documents'] = count($documents);
    $done['monthly'] = round($done['monthly'], 2);
    return $done;
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
