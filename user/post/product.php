<?php

/*
 * ITFlow - GET/POST request handler for products
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_product'])) {

    enforceUserPermission('module_sales', 2);

    require_once 'product_model.php';
    $type = sanitizeInput($_POST['type']);

    mysqli_query($mysqli,"INSERT INTO products SET product_name = '$name', product_type = '$type', product_description = '$description', product_code = '$code', product_location = '$location', product_price = '$price', product_currency_code = '$session_company_currency', product_tax_id = $tax, product_category_id = $category");

    $product_id = mysqli_insert_id($mysqli);

    logAction("Product", "Create", "$session_name created product $name", 0, $product_id);

    flash_alert("Product <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_product'])) {

    enforceUserPermission('module_sales', 2);

    require_once 'product_model.php';

    $product_id = intval($_POST['product_id']);

    mysqli_query($mysqli,"UPDATE products SET product_name = '$name', product_description = '$description', product_code = '$code', product_location = '$location', product_price = '$price', product_tax_id = $tax, product_category_id = $category WHERE product_id = $product_id");

    logAction("Product", "Edit", "$session_name edited product $name", 0, $product_id);

    flash_alert("Product <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['archive_product'])) {

    enforceUserPermission('module_sales', 2);

    $product_id = intval($_GET['archive_product']);

    $product_name = sanitizeInput(getFieldById('products', $product_id, 'product_name'));

    mysqli_query($mysqli,"UPDATE products SET product_archived_at = NOW() WHERE product_id = $product_id");

    logAction("Product", "Archive", "$session_name archived product $product_name", 0, $product_id);

    flash_alert("Product <strong>$product_name</strong> archived", 'error');

    redirect();

}

if (isset($_GET['unarchive_product'])) {

    enforceUserPermission('module_sales', 2);

    $product_id = intval($_GET['unarchive_product']);

    $product_name = sanitizeInput(getFieldById('products', $product_id, 'product_name'));

    mysqli_query($mysqli,"UPDATE products SET product_archived_at = NULL WHERE product_id = $product_id");

    logAction("Product", "Unarchive", "$session_name unarchived product $product_name", 0, $product_id);

    flash_alert("Product <strong>$product_name</strong> restored");

    redirect();

}

if (isset($_GET['delete_product'])) {
    
    enforceUserPermission('module_sales', 3);

    $product_id = intval($_GET['delete_product']);

    //Get Product Name
    $product_name = sanitizeInput(getFieldById('products', $product_id, 'product_name'));

    mysqli_query($mysqli,"DELETE FROM products WHERE product_id = $product_id");

    logAction("Product", "Delete", "$session_name deleted product $product_name");

    flash_alert("Product <strong>$product_name</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_edit_product_category'])) {

    enforceUserPermission('module_sales', 2);

    $category_id = intval($_POST['bulk_category_id']);

    // Get Category name for logging and Notification
    $category_name = sanitizeInput(getFieldById('categories', $category_id, 'category_name'));

    // Assign category to Selected Products
    if (isset($_POST['product_ids'])) {

        // Get Count
        $count = count($_POST['product_ids']);

        foreach($_POST['product_ids'] as $product_id) {
            $product_id = intval($product_id);

            // Get Product Details for Logging
            $product_name = sanitizeInput(getFieldById('products', $product_id, 'product_name'));

            mysqli_query($mysqli,"UPDATE products SET product_category_id = $category_id WHERE product_id = $product_id");

            logAction("Product", "Edit", "$session_name assigned product $product_name to category $category_name", 0, $product_id);

        } // End Assign Product Loop

        logAction("Product", "Edit", "$session_name assigned category $category_name to $count product(s)");

        flash_alert("Assigned category <strong>$category_name</strong> to <strong>$count</strong> product(s)");
    }

    redirect();

}

if (isset($_POST['bulk_archive_products'])) {
    
    validateCSRFToken($_POST['csrf_token']);

    enforceUserPermission('module_sales', 2);

    if (isset($_POST['product_ids'])) {

        $count = count($_POST['product_ids']);

        // Cycle through array and archive each record
        foreach ($_POST['product_ids'] as $product_id) {

            $product_id = intval($product_id);

            $product_name = sanitizeInput(getFieldById('products', $product_id, 'product_name'));

            mysqli_query($mysqli,"UPDATE products SET product_archived_at = NOW() WHERE product_id = $product_id");

            logAction("Product", "Archive", "$session_name archived product $product_name", 0, $product_id);
        }

        logAction("Product", "Bulk Archive", "$session_name archived $count product(s)");

        flash_alert("Archived <strong>$count</strong> product(s)", 'error');

    }

    redirect();

}

if (isset($_POST['bulk_unarchive_products'])) {

    validateCSRFToken($_POST['csrf_token']);
    
    enforceUserPermission('module_sales', 2);

    if (isset($_POST['product_ids'])) {

        $count = count($_POST['product_ids']);

        // Cycle through array and unarchive each record
        foreach ($_POST['product_ids'] as $product_id) {

            $product_id = intval($product_id);

            $product_name = sanitizeInput(getFieldById('products', $product_id, 'product_name'));

            mysqli_query($mysqli,"UPDATE products SET product_archived_at = NULL WHERE product_id = $product_id");

            logAction("Product", "Unarchive", "$session_name unarchived product $product_name", 0, $product_id);

        }

        logAction("Product", "Bulk Unarchive", "$session_name unarchived $count product(s)");

        flash_alert("Unarchived <strong>$count</strong> product(s)");

    }

    redirect();

}

if (isset($_POST['bulk_delete_products'])) {

    validateCSRFToken($_POST['csrf_token']);
    
    enforceUserPermission('module_sales', 3);
    

    if (isset($_POST['product_ids'])) {

        $count = count($_POST['product_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['product_ids'] as $product_id) {
            $product_id = intval($product_id);

            $product_name = sanitizeInput(getFieldById('products', $product_id, 'product_name'));

            mysqli_query($mysqli, "DELETE FROM products WHERE product_id = $product_id");

            logAction("Product", "Delete", "$session_name deleted product $product_name");

        }

        logAction("Product", "Bulk Delete", "$session_name deleted $count product(s)");

        flash_alert("Deleted <strong>$count</strong> product(s)", 'error');

    }

    redirect();

}

if (isset($_POST['export_products_csv'])) {

    //get records from database
    $sql = mysqli_query($mysqli,"SELECT * FROM products
      LEFT JOIN categories ON product_category_id = category_id
      LEFT JOIN taxes ON product_tax_id = tax_id
      WHERE product_archived_at IS NULL
      ORDER BY product_name DESC
    ");

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {
        $delimiter = ",";
        $filename = "$session_company_name-Products.csv";

        //create a file pointer
        $f = fopen('php://memory', 'w');

        //set column headers
        $fields = array('Product', 'Description', 'Price', 'Currency', 'Category', 'Tax');
        fputcsv($f, $fields, $delimiter);

        //output each row of the data, format line as csv and write to file pointer
        while($row = mysqli_fetch_assoc($sql)) {
            $lineData = array($row['product_name'], $row['product_description'], $row['product_price'], $row['product_currency_code'], $row['category_name'], $row['tax_name']);
            fputcsv($f, $lineData, $delimiter);
        }

        //move back to beginning of file
        fseek($f, 0);

        //set headers to download file rather than displayed
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        //output all remaining data on a file pointer
        fpassthru($f);
    }

    logAction("Product", "Export", "$session_name exported $num_rows product(s) to a CSV file");

    exit;
}

if (isset($_POST['add_product_stock'])) {

    enforceUserPermission('module_sales', 2);

    $product_id = intval($_POST['product_id']);
    $qty = intval($_POST['qty']);
    $expense = intval($_POST['expense']);
    $note = sanitizeInput($_POST['note']);

    // Get product name
    $product_name = sanitizeInput(getFieldById('products', $product_id, 'product_name'));

    mysqli_query($mysqli,"INSERT INTO product_stock SET stock_qty = $qty, stock_expense_id = $expense, stock_note = '$note', stock_product_id = $product_id");

    logAction("Product", "Stock", "$session_name added $qty units to stock for product $product_name", 0, $product_id);

    flash_alert("Added $qty units to <strong>$product_name</strong> stock");

    redirect();

}
