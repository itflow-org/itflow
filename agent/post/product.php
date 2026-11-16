<?php

/*
 * ITFlow - GET/POST request handler for products
 *
 * Products and services in the catalog - functions/products.php does the
 * saving, so New, Edit, Copy and Import follow the same rules.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The product fields from a posted form, raw. */
function productFormInput() {
    $in = [];
    foreach (['name', 'type', 'category', 'price', 'cost', 'tax', 'frequency', 'seat_source', 'code', 'vendor', 'vendor_part', 'reorder_level', 'reorder_qty', 'location',
        'asset_type', 'make', 'model', 'warranty_months', 'description', 'notes'] as $key) {
        if (array_key_exists($key, $_POST)) {
            $in[$key] = (string) $_POST[$key];
        }
    }
    // A switch posts nothing when off
    if (array_key_exists('type', $_POST)) {
        $in['track_stock'] = !empty($_POST['track_stock']);
    }
    return $in;
}

if (isset($_POST['add_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $product_id = productSave(productFormInput(), 0, $error);
    if (!$product_id) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $product = productRow($product_id);
    $name = escapeSql($product['product_name']);
    $type_label = productTypes()[$product['product_type']]['label'];

    logAudit("Product", "Create", "$session_name created " . strtolower($type_label) . " $name", 0, $product_id);

    flashAlert("$type_label <strong>" . escapeHtml($product['product_name']) . "</strong> added");

    redirect();

}

if (isset($_POST['edit_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $product_id = intval($_POST['product_id'] ?? 0);
    $price_before = getFieldById('products', $product_id, 'product_price');

    if (!productSave(productFormInput(), $product_id, $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $product = productRow($product_id);
    $name = escapeSql($product['product_name']);

    logAudit("Product", "Edit", "$session_name edited product $name", 0, $product_id);

    // A new price - say how many lines are still at the old one
    $price_note = '';
    if ($price_before !== null && round(floatval($price_before), 2) != round(floatval($product['product_price']), 2)) {
        $behind = productRepriceCount(['product_id' => $product_id]);
        $price_note = " - its price is now " . escapeHtml(billingMoney($product['product_price'], $product['product_currency_code']));
        if ($behind['lines']) {
            $price_note .= ", and <strong>" . intval($behind['lines']) . "</strong> line" . ($behind['lines'] == 1 ? ' is' : 's are') . " on recurring invoices, drafts or quotes at an old price. Update them from its page.";
        }
    }

    flashAlert("<strong>" . escapeHtml($product['product_name']) . "</strong> saved$price_note");

    redirect();

}

if (isset($_GET['copy_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $product = productRow(intval($_GET['copy_product']));
    $new_id = $product ? productCopy($product['product_id']) : 0;
    if (!$new_id) {
        flashAlert('That product no longer exists.', 'error');
        redirect('products.php');
    }
    $name = escapeSql($product['product_name']);

    logAudit("Product", "Create", "$session_name copied product $name", 0, $new_id);

    flashAlert("Copy of <strong>" . escapeHtml($product['product_name']) . "</strong> made - give it a code and check its details");

    redirect("product.php?product_id=$new_id");

}

if (isset($_GET['archive_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $product_id = intval($_GET['archive_product']);

    $product_name = escapeSql(getFieldById('products', $product_id, 'product_name'));

    mysqli_query($mysqli,"UPDATE products SET product_archived_at = NOW() WHERE product_id = $product_id");

    logAudit("Product", "Archive", "$session_name archived product $product_name", 0, $product_id);

    flashAlert("Product <strong>" . escapeHtml(stripslashes($product_name)) . "</strong> archived - it is no longer offered on new lines", 'error');

    redirect();

}

if (isset($_GET['restore_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $product_id = intval($_GET['restore_product']);

    $product_name = escapeSql(getFieldById('products', $product_id, 'product_name'));

    mysqli_query($mysqli,"UPDATE products SET product_archived_at = NULL WHERE product_id = $product_id");

    logAudit("Product", "Restore", "$session_name restored product $product_name", 0, $product_id);

    flashAlert("Product <strong>" . escapeHtml(stripslashes($product_name)) . "</strong> restored");

    redirect();

}

if (isset($_GET['delete_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);

    $product_id = intval($_GET['delete_product']);

    $product_name = escapeSql(getFieldById('products', $product_id, 'product_name'));

    productDelete($product_id);

    logAudit("Product", "Delete", "$session_name deleted product $product_name");

    flashAlert("Product <strong>" . escapeHtml(stripslashes($product_name)) . "</strong> deleted", 'error');

    // Not back to its page - it is gone
    redirect('products.php?archived=1');

}

if (isset($_POST['bulk_edit_product_category'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $category_id = intval($_POST['bulk_category_id']);

    // Get Category name for logging and Notification
    $category_name = escapeSql(getFieldById('categories', $category_id, 'category_name'));

    // Assign category to Selected Products
    if (isset($_POST['product_ids'])) {

        // Get Count
        $count = count($_POST['product_ids']);

        foreach($_POST['product_ids'] as $product_id) {
            $product_id = intval($product_id);

            // Get Product Details for Logging
            $product_name = escapeSql(getFieldById('products', $product_id, 'product_name'));

            mysqli_query($mysqli,"UPDATE products SET product_category_id = $category_id WHERE product_id = $product_id");

            logAudit("Product", "Edit", "$session_name assigned product $product_name to category $category_name", 0, $product_id);

        } // End Assign Product Loop

        logAudit("Product", "Edit", "$session_name assigned category $category_name to $count product(s)");

        flashAlert("Assigned category <strong>" . escapeHtml(stripslashes($category_name)) . "</strong> to <strong>$count</strong> product(s)");
    }

    redirect();

}

if (isset($_POST['bulk_archive_products'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    if (isset($_POST['product_ids'])) {

        $count = count($_POST['product_ids']);

        // Cycle through array and archive each record
        foreach ($_POST['product_ids'] as $product_id) {

            $product_id = intval($product_id);

            $product_name = escapeSql(getFieldById('products', $product_id, 'product_name'));

            mysqli_query($mysqli,"UPDATE products SET product_archived_at = NOW() WHERE product_id = $product_id");

            logAudit("Product", "Archive", "$session_name archived product $product_name", 0, $product_id);
        }

        logAudit("Product", "Bulk Archive", "$session_name archived $count product(s)");

        flashAlert("Archived <strong>$count</strong> product(s)", 'error');

    }

    redirect();

}

if (isset($_POST['bulk_restore_products'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    if (isset($_POST['product_ids'])) {

        $count = count($_POST['product_ids']);

        // Cycle through array and unarchive each record
        foreach ($_POST['product_ids'] as $product_id) {

            $product_id = intval($product_id);

            $product_name = escapeSql(getFieldById('products', $product_id, 'product_name'));

            mysqli_query($mysqli,"UPDATE products SET product_archived_at = NULL WHERE product_id = $product_id");

            logAudit("Product", "Restore", "$session_name restored product $product_name", 0, $product_id);

        }

        logAudit("Product", "Bulk Restore", "$session_name restored $count product(s)");

        flashAlert("Restored <strong>$count</strong> product(s)");

    }

    redirect();

}

if (isset($_POST['bulk_delete_products'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);

    if (isset($_POST['product_ids'])) {

        $count = count($_POST['product_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['product_ids'] as $product_id) {
            $product_id = intval($product_id);

            $product_name = escapeSql(getFieldById('products', $product_id, 'product_name'));

            productDelete($product_id);

            logAudit("Product", "Delete", "$session_name deleted product $product_name");

        }

        logAudit("Product", "Bulk Delete", "$session_name deleted $count product(s)");

        flashAlert("Deleted <strong>$count</strong> product(s)", 'error');

    }

    redirect();

}

if (isExportRequest('export_products')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_sales');

    $format = resolveExportFormat($_POST['export_products']);

    // Filters inherited from the products page - mirrors agent/products.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    $client_id = 0; // for Logging
    $file_name_prepend = "$session_company_name-";

    // Type Filter - the page's tabs
    $type_filter = (string) ($_POST['type'] ?? '');
    $type_queries = [
        'service' => ["AND product_type = 'service'", 'Services'],
        'product' => ["AND product_type = 'product'", 'Products'],
        'recurring' => ["AND product_frequency IS NOT NULL", 'Billed each month, quarter or year'],
        'stocked' => ["AND product_track_stock = 1", 'Stock tracked'],
        'reorder' => ["AND " . productReorderSql(), 'To reorder'],
        'hardware' => ["AND " . productHardwareSql(), 'Become client assets'],
    ];
    $type_query = '';
    if (isset($type_queries[$type_filter])) {
        [$type_query, $filter_summary['Showing']] = $type_queries[$type_filter];
    }

    $archive_query = $archived ? "product_archived_at IS NOT NULL" : "product_archived_at IS NULL";

    // Category Filter
    if (!empty($_POST['category'])) {
        $filter_category_id = intval($_POST['category']);
        $category_query = "AND (category_id = $filter_category_id)";
        $filter_summary['Category'] = getFieldById('categories', $filter_category_id, 'category_name');
    } else {
        // Default - any
        $category_query = '';
    }

    // Vendor Filter
    $vendor_query = '';
    if (!empty($_POST['vendor'])) {
        $filter_vendor_id = intval($_POST['vendor']);
        $vendor_query = "AND product_vendor_id = $filter_vendor_id";
        $filter_summary['Vendor'] = exportRecordName('vendors', $filter_vendor_id, 'vendor_name', 'vendor_client_id');
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT products.*, category_name, tax_name, tax_percent, vendor_name, " . productStockSql() . " AS product_stock
        FROM products
        LEFT JOIN categories ON product_category_id = category_id
        LEFT JOIN taxes ON product_tax_id = tax_id
        LEFT JOIN vendors ON product_vendor_id = vendor_id
        WHERE (product_name LIKE '%$q%' OR product_description LIKE '%$q%' OR product_code LIKE '%$q%' OR product_vendor_part LIKE '%$q%' OR product_location LIKE '%$q%'
            OR category_name LIKE '%$q%' OR vendor_name LIKE '%$q%' OR product_price LIKE '%$q%' OR tax_name LIKE '%$q%')
        $type_query
        AND $archive_query
        $category_query
        $vendor_query
        ORDER BY product_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    guardExportHasRows($num_rows, 'products');

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('products', $format, $file_name_prepend . 'Products', 'Products', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $margin = productMargin($row['product_price'], $row['product_cost']);
            $row['product_type_label'] = productTypes()[$row['product_type']]['label'] ?? $row['product_type'];
            $row['product_billed'] = productBilledText($row['product_frequency'], $row['product_seat_source']);
            $row['product_margin'] = $margin ? $margin['percent'] . '%' : '';
            $row['product_tax'] = $row['tax_name'] ? taxLabel($row) : '';
            $row['product_stock_display'] = intval($row['product_track_stock']) ? billingQuantity($row['product_stock']) : '';
            $row['product_reorder_display'] = intval($row['product_track_stock']) && floatval($row['product_reorder_level']) > 0 ? billingQuantity($row['product_reorder_level']) : '';
            $row['product_order_display'] = intval($row['product_track_stock']) && floatval($row['product_reorder_qty']) > 0 ? billingQuantity($row['product_reorder_qty']) : '';
            $row['product_stock_value'] = intval($row['product_track_stock']) && floatval($row['product_stock']) > 0 ? round(floatval($row['product_stock']) * floatval($row['product_cost']), 2) : '';
            $row['product_warranty_display'] = intval($row['product_warranty_months']) > 0 ? intval($row['product_warranty_months']) : '';
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Product", "Export", "$session_name exported $num_rows product(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}

/** A stock quantity from a form - a number above zero (zero or more with $zero), up to 2 places, or null. */
function productStockInput($value, $zero = false) {
    $raw = trim(str_replace([',', ' '], '', (string) $value));
    if ($raw === '' || !is_numeric($raw) || floatval($raw) >= 1e9 || round(floatval($raw), 2) != floatval($raw)) {
        return null;
    }
    $qty = round(floatval($raw), 2);
    return $qty > 0 || ($zero && $qty == 0) ? $qty : null;
}

/*
 * The Stock window - mode receive (it came in, at a unit cost, on an
 * expense), count (what is on the shelf now, the difference recorded) or
 * write_off (damaged, lost, used by us, sent back).
 */
if (isset($_POST['product_stock_save'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $product = productRow(intval($_POST['product_id'] ?? 0));
    if (!$product) {
        flashAlert('That product no longer exists.', 'error');
        redirect('products.php');
    }
    $product_id = intval($product['product_id']);
    $name_html = '<strong>' . escapeHtml($product['product_name']) . '</strong>';
    if (!intval($product['product_track_stock'])) {
        flashAlert("$name_html doesn't track stock - turn on Track stock in Edit first.", 'error');
        redirect();
    }
    $mode = (string) ($_POST['mode'] ?? 'receive');
    $on_hand = productOnHand($product_id);
    $note = trim((string) ($_POST['note'] ?? ''));
    $product_name = escapeSql($product['product_name']);

    if ($mode === 'receive') {
        $qty = productStockInput($_POST['qty'] ?? '');
        if ($qty === null) {
            flashAlert('Enter how many came in - a number above zero.', 'error');
            redirect();
        }
        $unit_cost = trim(str_replace([',', '$', ' '], '', (string) ($_POST['unit_cost'] ?? '')));
        if ($unit_cost !== '' && (!is_numeric($unit_cost) || floatval($unit_cost) < 0 || floatval($unit_cost) >= 1e13)) {
            flashAlert('The unit cost has to be a number, zero or more.', 'error');
            redirect();
        }
        // Linked to an expense only by a role that can see expenses, and one it can see (the window's list)
        $expense = lookupUserPermission('module_financial') >= 1 ? intval($_POST['expense'] ?? 0) : 0;
        if ($expense && !mysqli_num_rows(mysqli_query($mysqli, "SELECT expense_id FROM expenses WHERE expense_id = $expense " . clientScopeSql('expense_client_id')))) {
            $expense = 0;
        }
        productStockMove($product_id, $qty, 'received', ['unit_cost' => $unit_cost === '' ? null : $unit_cost, 'expense_id' => $expense, 'note' => $note]);
        $cost_note = '';
        if ($unit_cost !== '' && !empty($_POST['update_cost']) && round(floatval($unit_cost), 2) != round(floatval($product['product_cost']), 2)) {
            $cost_error = '';
            productSave(['cost' => $unit_cost], $product_id, $cost_error, 'receive');
            $cost_note = ', and its cost is now ' . billingMoney($unit_cost, $product['product_currency_code']);
            logAudit("Product", "Edit", "$session_name changed the cost of product $product_name to " . escapeSql(billingMoney($unit_cost, $product['product_currency_code'])) . " from a delivery", 0, $product_id);
        }
        logAudit("Product", "Stock", "$session_name received " . billingQuantity($qty) . " of product $product_name", 0, $product_id);
        flashAlert(billingQuantity($qty) . " of $name_html received - " . billingQuantity($on_hand + $qty) . " on the shelf" . escapeHtml($cost_note));

    } elseif ($mode === 'count') {
        $counted = productStockInput($_POST['counted'] ?? '', true);
        if ($counted === null) {
            flashAlert('Enter what is on the shelf - zero or more.', 'error');
            redirect();
        }
        $difference = round($counted - $on_hand, 2);
        if ($difference == 0) {
            flashAlert(billingQuantity($counted) . " of $name_html on the shelf - that is what ITFlow had, nothing to change");
            redirect();
        }
        productStockMove($product_id, $difference, 'adjusted', ['note' => 'Counted ' . billingQuantity($counted) . ($note !== '' ? " - $note" : '')]);
        logAudit("Product", "Stock", "$session_name counted product $product_name - " . billingQuantity($counted) . ' on the shelf, ' . productStockQty($difference), 0, $product_id);
        flashAlert("$name_html counted - " . billingQuantity($counted) . ' on the shelf, ' . productStockQty($difference) . ' from what ITFlow had');

    } elseif ($mode === 'write_off') {
        $qty = productStockInput($_POST['qty'] ?? '');
        if ($qty === null) {
            flashAlert('Enter how many to write off - a number above zero.', 'error');
            redirect();
        }
        if ($qty > $on_hand) {
            flashAlert("Only " . billingQuantity($on_hand) . " of $name_html on the shelf - count it first if there are more.", 'error');
            redirect();
        }
        $reason = in_array($_POST['reason'] ?? '', productWriteOffReasons(), true) ? $_POST['reason'] : 'Other';
        productStockMove($product_id, -$qty, 'written_off', ['note' => $reason . ($note !== '' ? " - $note" : '')]);
        logAudit("Product", "Stock", "$session_name wrote off " . billingQuantity($qty) . " of product $product_name - " . escapeSql($reason), 0, $product_id);
        flashAlert(billingQuantity($qty) . " of $name_html written off - " . billingQuantity($on_hand - $qty) . ' on the shelf');

    } else {
        flashAlert('That is not something the Stock window does.', 'error');
    }

    redirect();

}

/*
 * Count Stock - what is on the shelf for each product counted (counted[id]),
 * the differences recorded together. Blank ones are left alone.
 */
if (isset($_POST['product_stock_count'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $note = trim((string) ($_POST['note'] ?? ''));
    $changed = $same = 0;
    $bad = [];
    foreach ((array) ($_POST['counted'] ?? []) as $product_id => $value) {
        if (trim((string) $value) === '') {
            continue;
        }
        $product = productRow(intval($product_id));
        if (!$product || !intval($product['product_track_stock'])) {
            continue;
        }
        $counted = productStockInput($value, true);
        if ($counted === null) {
            $bad[] = $product['product_name'];
            continue;
        }
        $difference = round($counted - productOnHand($product['product_id']), 2);
        if ($difference == 0) {
            $same++;
            continue;
        }
        productStockMove($product['product_id'], $difference, 'adjusted', ['note' => 'Stock count - ' . billingQuantity($counted) . ($note !== '' ? " - $note" : '')]);
        logAudit("Product", "Stock", "$session_name counted product " . escapeSql($product['product_name']) . ' - ' . billingQuantity($counted) . ' on the shelf, ' . productStockQty($difference), 0, intval($product['product_id']));
        $changed++;
    }

    logAudit("Product", "Stock", "$session_name saved a stock count - $changed changed, $same as expected");

    $message = "Stock count saved - <strong>$changed</strong> changed, <strong>$same</strong> as expected";
    if ($bad) {
        $message .= '. Not a number, left alone: ' . escapeHtml(implode(', ', $bad));
    }
    flashAlert($message, $bad && !$changed ? 'error' : 'success');

    redirect('products.php?type=stocked');

}

if (isset($_POST['products_reprice_save'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    // Only the lines picked, at the price typed for each
    $changes = [];
    foreach ((array) ($_POST['pick'] ?? []) as $key) {
        $key = (string) $key;
        $changes[$key] = (string) ($_POST['price'][$key] ?? '');
    }

    // functions/products.php - every price is checked before any line changes
    $done = productRepriceApply($changes, $error);
    if ($done === null) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    logAudit("Product", "Edit", "$session_name updated the price of {$done['lines']} line(s) on {$done['documents']} recurring invoice(s), draft(s) and quote(s) from the catalog");

    $message = "<strong>{$done['lines']}</strong> price" . ($done['lines'] === 1 ? '' : 's') . " updated on <strong>{$done['documents']}</strong> document" . ($done['documents'] === 1 ? '' : 's');
    if ($done['monthly'] != 0) {
        $message .= " - recurring invoices bring in " . escapeHtml(billingMoney(abs($done['monthly']), $session_company_currency)) . ($done['monthly'] > 0 ? ' more' : ' less') . " a month";
    }
    flashAlert($message);

    redirect();

}
