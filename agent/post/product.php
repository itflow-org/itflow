<?php

/*
 * ITFlow - GET/POST request handler for products
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    require_once 'product_model.php';
    $type = escapeSql($_POST['type']);

    mysqli_query($mysqli,"INSERT INTO products SET product_name = '$name', product_type = '$type', product_description = '$description', product_code = '$code', product_location = '$location', product_price = '$price', product_currency_code = '$session_company_currency', product_tax_id = $tax, product_category_id = $category");

    $product_id = mysqli_insert_id($mysqli);

    logAudit("Product", "Create", "$session_name created product $name", 0, $product_id);

    flashAlert("Product <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    require_once 'product_model.php';

    $product_id = intval($_POST['product_id']);

    mysqli_query($mysqli,"UPDATE products SET product_name = '$name', product_description = '$description', product_code = '$code', product_location = '$location', product_price = '$price', product_tax_id = $tax, product_category_id = $category WHERE product_id = $product_id");

    logAudit("Product", "Edit", "$session_name edited product $name", 0, $product_id);

    flashAlert("Product <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['archive_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $product_id = intval($_GET['archive_product']);

    $product_name = escapeSql(getFieldById('products', $product_id, 'product_name'));

    mysqli_query($mysqli,"UPDATE products SET product_archived_at = NOW() WHERE product_id = $product_id");

    logAudit("Product", "Archive", "$session_name archived product $product_name", 0, $product_id);

    flashAlert("Product <strong>$product_name</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);

    $product_id = intval($_GET['restore_product']);

    $product_name = escapeSql(getFieldById('products', $product_id, 'product_name'));

    mysqli_query($mysqli,"UPDATE products SET product_archived_at = NULL WHERE product_id = $product_id");

    logAudit("Product", "Restore", "$session_name restored product $product_name", 0, $product_id);

    flashAlert("Product <strong>$product_name</strong> restored");

    redirect();

}

if (isset($_GET['delete_product'])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 3);

    $product_id = intval($_GET['delete_product']);

    //Get Product Name
    $product_name = escapeSql(getFieldById('products', $product_id, 'product_name'));

    mysqli_query($mysqli,"DELETE FROM products WHERE product_id = $product_id");

    logAudit("Product", "Delete", "$session_name deleted product $product_name");

    flashAlert("Product <strong>$product_name</strong> deleted", 'error');

    redirect();

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

        flashAlert("Assigned category <strong>$category_name</strong> to <strong>$count</strong> product(s)");
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

            mysqli_query($mysqli, "DELETE FROM products WHERE product_id = $product_id");

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

    // Type Filter
    if (isset($_POST['type']) && $_POST['type'] === 'product') {
        $type_query = "AND product_type = 'product'";
        $filter_summary['Type'] = 'Product';
    } elseif (isset($_POST['type']) && $_POST['type'] === 'service') {
        $type_query = "AND product_type = 'service'";
        $filter_summary['Type'] = 'Service';
    } else {
        // Default - any
        $type_query = '';
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

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT products.*, categories.*, taxes.*
        FROM products
        LEFT JOIN categories ON product_category_id = category_id
        LEFT JOIN taxes ON product_tax_id = tax_id
        WHERE (product_name LIKE '%$q%' OR product_description LIKE '%$q%' OR product_code LIKE '%$q%' OR product_location LIKE '%$q%' OR category_name LIKE '%$q%' OR product_price LIKE '%$q%' OR tax_name LIKE '%$q%')
        $type_query
        AND $archive_query
        $category_query
        GROUP BY product_id
        ORDER BY product_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('products', $format, $file_name_prepend . 'Products', 'Products', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
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

    $product_id = intval($_POST['product_id']);
    $qty = intval($_POST['qty']);
    $expense = intval($_POST['expense']);
    $note = escapeSql($_POST['note']);

    // Get product name
    $product_name = escapeSql(getFieldById('products', $product_id, 'product_name'));

    mysqli_query($mysqli,"INSERT INTO product_stock SET stock_qty = $qty, stock_expense_id = $expense, stock_note = '$note', stock_product_id = $product_id");

    logAudit("Product", "Stock", "$session_name added $qty units to stock for product $product_name", 0, $product_id);

    flashAlert("Added $qty units to <strong>$product_name</strong> stock");

    redirect();

}

if (isset($_POST["import_products_csv"])) {

    validateCSRFToken();

    enforceUserPermission('module_sales', 2);
    $error = false;

    if (!empty($_FILES["file"]["tmp_name"])) {
        $file_name = $_FILES["file"]["tmp_name"];
    } else {
        flashAlert("Please select a file to upload.", 'error');
        redirect();
    }

    //Check file is CSV
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));
    $allowed_file_extensions = array('csv');
    if (in_array($file_extension,$allowed_file_extensions) === false) {
        $error = true;
        flashAlert("Bad file extension", 'error');
    }

    //Check file isn't empty
    elseif ($_FILES["file"]["size"] < 1) {
        $error = true;
        flashAlert("Bad file size (empty?)", 'error');
    }

    //(Else)Check column count
    $f = fopen($file_name, "r");
    $f_columns = fgetcsv($f, 1000, ",");
    if (!$error & count($f_columns) != 3) {
        $error = true;
        flashAlert("Bad column count.", 'error');
    }

    //Else, parse the file
    if (!$error) {

        $file = fopen($file_name, "r");
        fgetcsv($file, 1000, ","); // Skip first line
        $row_count = 0;

        while(($column = fgetcsv($file, 1000, ",")) !== false) {
            $name = '';
            if (isset($column[0])) {
                $name = escapeSql(substr($column[0], 0, 200));
            }

            $description = '';
            if (isset($column[1])) {
                $description = escapeSql($column[1]);
            }

            $price = 0;
            if (isset($column[2])) {
                $price = floatval($column[2]);
            }

            if (!empty($name)) {
                mysqli_query($mysqli, "INSERT INTO products SET product_name = '$name', product_type = 'product', product_description = '$description', product_price = '$price', product_currency_code = '$session_company_currency', product_category_id = 0");
                $row_count++;
            }

        }
        fclose($file);

        logAudit("Product", "Import", "$session_name imported $row_count product(s) via CSV file");

        flashAlert("<strong>$row_count</strong> Product(s) added");

        redirect();

    }

    //Check for any errors, if there are notify user and redirect
    if ($error) {
        redirect();
    }
}

if (isset($_GET['download_products_csv_template'])) {

    $delimiter = ",";
    $enclosure = '"';
    $escape    = '\\';   // backsla
    $filename = "Products-Template.csv";

    //create a file pointer
    $f = fopen('php://memory', 'w');

    //set column headers
    $fields = array('Product Name', 'Description', 'Price');
    fputcsv($f, $fields, $delimiter, $enclosure, $escape);

    //move back to beginning of file
    fseek($f, 0);

    //set headers to download file rather than displayed
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    //output all remaining data on a file pointer
    fpassthru($f);

    exit;

}
