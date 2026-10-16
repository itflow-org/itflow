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
    foreach (['name', 'type', 'category', 'price', 'cost', 'tax', 'frequency', 'seat_source', 'code', 'vendor', 'vendor_part', 'location', 'description', 'notes'] as $key) {
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

    if (!productSave(productFormInput(), $product_id, $error)) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $product = productRow($product_id);
    $name = escapeSql($product['product_name']);

    logAudit("Product", "Edit", "$session_name edited product $name", 0, $product_id);

    flashAlert("<strong>" . escapeHtml($product['product_name']) . "</strong> saved");

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
        $filter_summary['Vendor'] = getFieldById('vendors', $filter_vendor_id, 'vendor_name');
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

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('products', $format, $file_name_prepend . 'Products', 'Products', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $margin = productMargin($row['product_price'], $row['product_cost']);
            $row['product_type_label'] = productTypes()[$row['product_type']]['label'] ?? $row['product_type'];
            $row['product_billed'] = productBilledText($row['product_frequency'], $row['product_seat_source']);
            $row['product_margin'] = $margin ? $margin['percent'] . '%' : '';
            $row['product_tax'] = $row['tax_name'] ? $row['tax_name'] . ' ' . floatval($row['tax_percent']) . '%' : '';
            $row['product_stock_display'] = intval($row['product_track_stock']) ? $row['product_stock'] : '';
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Product", "Export", "$session_name exported $num_rows product(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}

if (isset($_POST['add_product_stock'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $product_id = intval($_POST['product_id'] ?? 0);
    $product = productRow($product_id);
    $qty = intval($_POST['qty'] ?? 0);
    $expense = intval($_POST['expense'] ?? 0);
    $note = escapeSql(trim((string) ($_POST['note'] ?? '')));

    if (!$product) {
        flashAlert('That product no longer exists.', 'error');
        redirect('products.php');
    }
    if (!intval($product['product_track_stock'])) {
        flashAlert(escapeHtml($product['product_name']) . " doesn't track stock - turn on Track stock in Edit first.", 'error');
        redirect();
    }
    if ($qty <= 0) {
        flashAlert('Enter how many came in - a whole number above zero.', 'error');
        redirect();
    }
    if ($expense && !getFieldById('expenses', $expense, 'expense_id')) {
        $expense = 0;
    }
    $product_name = escapeSql($product['product_name']);

    mysqli_query($mysqli,"INSERT INTO product_stock SET stock_qty = $qty, stock_expense_id = " . ($expense ?: 'NULL') . ", stock_note = '$note', stock_product_id = $product_id");

    logAudit("Product", "Stock", "$session_name added $qty units to stock for product $product_name", 0, $product_id);

    flashAlert("Added $qty to <strong>" . escapeHtml($product['product_name']) . "</strong> - " . (intval($product['product_stock']) + $qty) . " on the shelf");

    redirect();

}

/*
 * Import from a CSV with a header row. Columns are found by their heading,
 * in any order - only Name is needed:
 *   Name, Type, Code, Category, Description, Price, Cost, Tax, Billed, Counted,
 *   Vendor, Part Number, Track Stock, Location, Notes
 * The old three column file (Product Name, Description, Price) still works.
 * A row whose code matches a product already here updates it when asked to.
 */
if (isset($_POST["import_products_csv"])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    if (empty($_FILES["file"]["tmp_name"]) || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
        flashAlert("Choose a CSV file to import.", 'error');
        redirect();
    }
    if (strtolower(pathinfo((string) $_FILES['file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        flashAlert("That isn't a CSV file.", 'error');
        redirect();
    }
    $update_existing = !empty($_POST['update_existing']);

    $file = fopen($_FILES["file"]["tmp_name"], "r");
    $header = $file ? fgetcsv($file, 0, ",", '"', '\\') : false;
    if (!$header) {
        flashAlert("The file is empty.", 'error');
        redirect();
    }

    // Which column is which, by its heading
    $aliases = [
        'name' => ['name', 'product name', 'product', 'item'],
        'type' => ['type'],
        'code' => ['code', 'sku', 'product code'],
        'category' => ['category'],
        'description' => ['description'],
        'price' => ['price', 'unit price'],
        'cost' => ['cost', 'unit cost'],
        'tax' => ['tax'],
        'frequency' => ['billed', 'frequency', 'billing'],
        'seat_source' => ['counted', 'per'],
        'vendor' => ['vendor', 'supplier'],
        'vendor_part' => ['part number', 'vendor part', 'mpn', 'part'],
        'track_stock' => ['track stock', 'tracked', 'stock tracked'],
        'location' => ['location'],
        'notes' => ['notes', 'internal notes'],
    ];
    $columns = [];
    foreach ($header as $index => $heading) {
        $heading = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $heading)));
        foreach ($aliases as $key => $names) {
            if (in_array($heading, $names, true) && !isset($columns[$key])) {
                $columns[$key] = $index;
            }
        }
    }
    // A price list update can go by code alone
    if (!isset($columns['name']) && !($update_existing && isset($columns['code']))) {
        fclose($file);
        flashAlert("The first row needs a Name column - download the template to see the layout.", 'error');
        redirect();
    }

    // Names to ids, matched without case
    $lookup = function ($sql) use ($mysqli) {
        $map = [];
        $result = mysqli_query($mysqli, $sql);
        while ($row = mysqli_fetch_row($result)) {
            $map[mb_strtolower(trim((string) $row[1]))] = intval($row[0]);
        }
        return $map;
    };
    $categories = $lookup("SELECT category_id, category_name FROM categories WHERE category_type = 'Income' AND category_archived_at IS NULL");
    $taxes = $lookup("SELECT tax_id, tax_name FROM taxes WHERE tax_archived_at IS NULL");
    $vendors = $lookup("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = 0 AND vendor_archived_at IS NULL");
    $frequencies = ['' => '', 'once' => '', 'one time' => '', 'one-time' => '', 'monthly' => 'month', 'month' => 'month', 'quarterly' => 'quarter', 'quarter' => 'quarter', 'yearly' => 'year', 'year' => 'year', 'annually' => 'year', 'annual' => 'year'];
    $seat_sources = ['' => '', 'as typed' => '', 'per user' => 'contacts', 'user' => 'contacts', 'users' => 'contacts', 'per device' => 'assets', 'device' => 'assets', 'devices' => 'assets'];

    $added = $updated = 0;
    $skipped = [];
    $line = 1;
    while (($row = fgetcsv($file, 0, ",", '"', '\\')) !== false) {
        $line++;
        if ($row === [null] || !array_filter($row, function ($v) { return trim((string) $v) !== ''; })) {
            continue;
        }
        $in = [];
        foreach ($columns as $key => $index) {
            $in[$key] = trim((string) ($row[$index] ?? ''));
        }
        if (isset($in['type'])) {
            $type = strtolower($in['type']);
            $in['type'] = in_array($type, ['product', 'products', 'hardware', 'goods'], true) ? 'product' : 'service';
        }
        if (isset($in['category'])) {
            $in['category'] = $categories[mb_strtolower($in['category'])] ?? 0;
        }
        if (isset($in['tax'])) {
            $in['tax'] = $taxes[mb_strtolower($in['tax'])] ?? 0;
        }
        if (isset($in['vendor'])) {
            $in['vendor'] = $vendors[mb_strtolower($in['vendor'])] ?? 0;
        }
        if (isset($in['frequency'])) {
            $in['frequency'] = $frequencies[strtolower($in['frequency'])] ?? '';
        }
        if (isset($in['seat_source'])) {
            $in['seat_source'] = $seat_sources[strtolower($in['seat_source'])] ?? '';
        }
        if (isset($in['track_stock'])) {
            $in['track_stock'] = in_array(strtolower($in['track_stock']), ['1', 'yes', 'y', 'true', 'tracked'], true);
        }

        // The same code as one here - update it, or leave it
        $existing_id = 0;
        if (($in['code'] ?? '') !== '') {
            $code = escapeSql($in['code']);
            $existing_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT product_id FROM products WHERE product_code = '$code' ORDER BY product_archived_at IS NOT NULL, product_id LIMIT 1"))[0] ?? 0);
        }
        if ($existing_id && !$update_existing) {
            $skipped[] = "row $line - " . $in['code'] . " is already here";
            continue;
        }
        if ($existing_id) {
            // An update leaves the name alone when the file has none for it
            if (($in['name'] ?? '') === '') {
                unset($in['name']);
            }
        } else {
            if (($in['name'] ?? '') === '') {
                $skipped[] = "row $line has no name";
                continue;
            }
            // A new one starts as the kind the file says, a product when it doesn't say
            $in['type'] = $in['type'] ?? 'product';
        }

        $error = '';
        if (productSave($in, $existing_id, $error)) {
            $existing_id ? $updated++ : $added++;
        } else {
            $skipped[] = "row $line - " . rtrim($error, '.');
        }
    }
    fclose($file);

    logAudit("Product", "Import", "$session_name imported products from a CSV file - $added added, $updated updated, " . count($skipped) . " skipped");

    $message = "<strong>$added</strong> added" . ($update_existing ? ", <strong>$updated</strong> updated" : '');
    if ($skipped) {
        $message .= ", <strong>" . count($skipped) . "</strong> skipped: " . escapeHtml(implode('; ', array_slice($skipped, 0, 5))) . (count($skipped) > 5 ? '...' : '');
    }
    flashAlert($message, $skipped && !$added && !$updated ? 'error' : 'success');

    redirect();

}

if (isset($_GET['download_products_csv_template'])) {

    $f = fopen('php://memory', 'w');
    fputcsv($f, ['Name', 'Type', 'Code', 'Category', 'Description', 'Price', 'Cost', 'Tax', 'Billed', 'Counted', 'Vendor', 'Part Number', 'Track Stock', 'Location', 'Notes'], ',', '"', '\\');
    fputcsv($f, ['Managed Workstation', 'Service', 'MS-WKS', 'Managed Services', 'Per workstation, per month. Monitoring, patching and support.', '65.00', '18.00', '', 'Monthly', 'Per device', '', '', '', '', ''], ',', '"', '\\');
    fputcsv($f, ['24 Port PoE Switch', 'Product', 'HW-SW24', 'Hardware Sales', '24 port managed PoE switch.', '785.00', '520.00', '', 'One time', '', '', 'USW-PRO-24-POE', 'Yes', 'Shelf B2', 'Order from the distributor, 2 day lead time'], ',', '"', '\\');
    fseek($f, 0);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Products-Template.csv";');
    fpassthru($f);

    exit;

}
