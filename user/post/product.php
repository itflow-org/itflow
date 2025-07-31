<?php

/*
 * ITFlow - GET/POST request handler for products
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Products
if (isset($_POST['add_product'])) {

    enforceUserPermission('module_sales', 2);

    require_once 'product_model.php';

    mysqli_query($mysqli,"INSERT INTO products SET product_name = '$name', product_description = '$description', product_price = '$price', product_currency_code = '$session_company_currency', product_tax_id = $tax, product_category_id = $category");

    $product_id = mysqli_insert_id($mysqli);

    // Logging
    logAction("Product", "Create", "$session_name created product $name", 0, $product_id);

    $_SESSION['alert_message'] = "Product <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_product'])) {

    enforceUserPermission('module_sales', 2);

    require_once 'product_model.php';

    $product_id = intval($_POST['product_id']);

    mysqli_query($mysqli,"UPDATE products SET product_name = '$name', product_description = '$description', product_price = '$price', product_tax_id = $tax, product_category_id = $category WHERE product_id = $product_id");

    // Logging
    logAction("Product", "Edit", "$session_name edited product $name", 0, $product_id);

    $_SESSION['alert_message'] = "Product <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_product'])) {

    enforceUserPermission('module_sales', 2);

    $product_id = intval($_GET['archive_product']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
    $row = mysqli_fetch_array($sql);
    $product_name = sanitizeInput($row['product_name']);

    mysqli_query($mysqli,"UPDATE products SET product_archived_at = NOW() WHERE product_id = $product_id");

    // Logging
    logAction("Product", "Archive", "$session_name archived product $product_name", 0, $product_id);

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Product <strong>$product_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unarchive_product'])) {

    enforceUserPermission('module_sales', 2);

    $product_id = intval($_GET['unarchive_product']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
    $row = mysqli_fetch_array($sql);
    $product_name = sanitizeInput($row['product_name']);

    mysqli_query($mysqli,"UPDATE products SET product_archived_at = NULL WHERE product_id = $product_id");

    // Logging
    logAction("Product", "Unarchive", "$session_name unarchived product $product_name", 0, $product_id);

    $_SESSION['alert_message'] = "Product <strong>$product_name</strong> restored";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_product'])) {
    
    enforceUserPermission('module_sales', 3);

    $product_id = intval($_GET['delete_product']);

    //Get Product Name
    $sql = mysqli_query($mysqli,"SELECT * FROM products WHERE product_id = $product_id");
    $row = mysqli_fetch_array($sql);
    $product_name = sanitizeInput($row['product_name']);

    mysqli_query($mysqli,"DELETE FROM products WHERE product_id = $product_id");

    // Logging
    logAction("Product", "Delete", "$session_name deleted product $product_name");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Product <strong>$product_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_edit_product_category'])) {

    enforceUserPermission('module_sales', 2);

    $category_id = intval($_POST['bulk_category_id']);

    // Get Category name for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT category_name FROM categories WHERE category_id = $category_id");
    $row = mysqli_fetch_array($sql);
    $category_name = sanitizeInput($row['category_name']);

    // Assign category to Selected Products
    if (isset($_POST['product_ids'])) {

        // Get Count
        $count = count($_POST['product_ids']);

        foreach($_POST['product_ids'] as $product_id) {
            $product_id = intval($product_id);

            // Get Product Details for Logging
            $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
            $row = mysqli_fetch_array($sql);
            $product_name = sanitizeInput($row['product_name']);

            mysqli_query($mysqli,"UPDATE products SET product_category_id = $category_id WHERE product_id = $product_id");

            //Logging
            logAction("Product", "Edit", "$session_name assigned product $product_name to category $category_name", 0, $product_id);

        } // End Assign Product Loop

        //Logging
        logAction("Product", "Edit", "$session_name assigned category $category_name to $count product(s)");

        $_SESSION['alert_message'] = "Assigned category <strong>$category_name</strong> to <strong>$count</strong> product(s)";
    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_archive_products'])) {
    
    enforceUserPermission('module_sales', 2);
    
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['product_ids'])) {

        $count = count($_POST['product_ids']);

        // Cycle through array and archive each record
        foreach ($_POST['product_ids'] as $product_id) {

            $product_id = intval($product_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
            $row = mysqli_fetch_array($sql);
            $product_name = sanitizeInput($row['product_name']);

            mysqli_query($mysqli,"UPDATE products SET product_archived_at = NOW() WHERE product_id = $product_id");

            // Individual Contact logging
            logAction("Product", "Archive", "$session_name archived product $product_name", 0, $product_id);
        }

        // Bulk Logging
        logAction("Product", "Bulk Archive", "$session_name archived $count product(s)");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Archived <strong>$count</strong> product(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_unarchive_products'])) {
    enforceUserPermission('module_sales', 2);
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['product_ids'])) {

        $count = count($_POST['product_ids']);

        // Cycle through array and unarchive each record
        foreach ($_POST['product_ids'] as $product_id) {

            $product_id = intval($product_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
            $row = mysqli_fetch_array($sql);
            $product_name = sanitizeInput($row['product_name']);

            mysqli_query($mysqli,"UPDATE products SET product_archived_at = NULL WHERE product_id = $product_id");

            // Individual logging
            logAction("Product", "Unarchive", "$session_name unarchived product $product_name", 0, $product_id);

        }

        // Bulk Logging
        logAction("Product", "Bulk Unarchive", "$session_name unarchived $count product(s)");

        $_SESSION['alert_message'] = "Unarchived <strong>$count</strong> product(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_products'])) {
    enforceUserPermission('module_sales', 3);
    validateCSRFToken($_POST['csrf_token']);

    if (isset($_POST['product_ids'])) {

        $count = count($_POST['product_ids']);

        // Cycle through array and delete each record
        foreach ($_POST['product_ids'] as $product_id) {

            $product_id = intval($product_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
            $row = mysqli_fetch_array($sql);
            $product_name = sanitizeInput($row['product_name']);

            mysqli_query($mysqli, "DELETE FROM products WHERE product_id = $product_id");

            // Individual logging
            logAction("Product", "Delete", "$session_name deleted product $product_name");

        }

        // Bulk logging
        logAction("Product", "Bulk Delete", "$session_name deleted $count product(s)");

        $_SESSION['alert_message'] = "Deleted <strong>$count</strong> product(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
    exit();
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

    //Logging
    logAction("Product", "Export", "$session_name exported $num_rows product(s) to a CSV file");

    exit;
}
