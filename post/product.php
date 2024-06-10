<?php

/*
 * ITFlow - GET/POST request handler for products
 */

// Products
if (isset($_POST['add_product'])) {

    require_once 'post/product_model.php';


    mysqli_query($mysqli,"INSERT INTO products SET product_name = '$name', product_description = '$description', product_price = '$price', product_currency_code = '$session_company_currency', product_tax_id = $tax, product_category_id = $category");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Create', log_description = '$session_name created product $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Product <strong>$name</strong> created";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['edit_product'])) {

    require_once 'post/product_model.php';


    $product_id = intval($_POST['product_id']);

    mysqli_query($mysqli,"UPDATE products SET product_name = '$name', product_description = '$description', product_price = '$price', product_tax_id = $tax, product_category_id = $category WHERE product_id = $product_id");

    //Logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Modify', log_description = '$name', log_user_id = $session_user_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Modify', log_description = '$session_name modified product $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_message'] = "Product <strong>$name</strong> modified";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['archive_product'])) {

    validateTechRole();

    $product_id = intval($_GET['archive_product']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
    $row = mysqli_fetch_array($sql);
    $product_name = sanitizeInput($row['product_name']);

    mysqli_query($mysqli,"UPDATE products SET product_archived_at = NOW() WHERE product_id = $product_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Archive', log_description = '$session_name archived product $product_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $product_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Product <strong>$product_name</strong> archived";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['unarchive_product'])) {

    validateTechRole();

    $product_id = intval($_GET['unarchive_product']);

    // Get Contact Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
    $row = mysqli_fetch_array($sql);
    $product_name = sanitizeInput($row['product_name']);

    mysqli_query($mysqli,"UPDATE products SET product_archived_at = NULL WHERE product_id = $product_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Unarchive', log_description = '$session_name restored product $product_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $product_id");

    $_SESSION['alert_message'] = "Product <strong>$product_name</strong> restored";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['delete_product'])) {
    $product_id = intval($_GET['delete_product']);

    //Get Product Name
    $sql = mysqli_query($mysqli,"SELECT * FROM products WHERE product_id = $product_id");
    $row = mysqli_fetch_array($sql);
    $product_name = sanitizeInput($row['product_name']);

    mysqli_query($mysqli,"DELETE FROM products WHERE product_id = $product_id");

    //logging
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Delete', log_description = '$session_name deleted product $name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Product <strong>$product_name</strong> deleted";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_POST['bulk_edit_product_category'])) {

    $category_id = intval($_POST['bulk_category_id']);

    // Get Category name for logging and Notification
    $sql = mysqli_query($mysqli,"SELECT category_name FROM categories WHERE category_id = $category_id");
    $row = mysqli_fetch_array($sql);
    $category_name = sanitizeInput($row['category_name']);

    // Get Count
    $count = count($_POST['product_ids']);
    
    // Assign category to Selected Products
    if (!empty($_POST['product_ids'])) {
        foreach($_POST['product_ids'] as $product_id) {
            $product_id = intval($product_id);

            // Get Product Details for Logging
            $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
            $row = mysqli_fetch_array($sql);
            $product_name = sanitizeInput($row['product_name']);

            mysqli_query($mysqli,"UPDATE products SET product_category_id = $category_id WHERE product_id = $product_id");

            //Logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Edit', log_description = '$session_name assigned $product_name to income category $category_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $product_id");

        } // End Assign Product Loop
    
        $_SESSION['alert_message'] = "You assigned product category <b>$category_name</b> to <b>$count</b> products";
    }
    
    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_archive_products'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $product_ids = $_POST['product_ids']; // Get array of IDs to be deleted

    if (!empty($product_ids)) {

        // Cycle through array and archive each record
        foreach ($product_ids as $product_id) {

            $product_id = intval($product_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
            $row = mysqli_fetch_array($sql);
            $product_name = sanitizeInput($row['product_name']);

            mysqli_query($mysqli,"UPDATE products SET product_archived_at = NOW() WHERE product_id = $product_id");

            // Individual Contact logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Archive', log_description = '$session_name archived product $product_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $product_id");
            $count++;
        }

        // Bulk Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Product', log_action = 'Bulk Archive', log_description = '$session_name archived $count products', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_type'] = "error";
        $_SESSION['alert_message'] = "Archived $count product(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_unarchive_products'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $product_ids = $_POST['product_ids']; // Get array of IDs

    if (!empty($product_ids)) {

        // Cycle through array and unarchive
        foreach ($product_ids as $product_id) {

            $product_id = intval($product_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
            $row = mysqli_fetch_array($sql);
            $product_name = sanitizeInput($row['product_name']);

            mysqli_query($mysqli,"UPDATE products SET product_archived_at = NULL WHERE product_id = $product_id");

            // Individual logging
            mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Unarchive', log_description = '$session_name Unarchived product $product_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $product_id");


            $count++;
        }

        // Bulk Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Product', log_action = 'Unarchive', log_description = '$session_name Unarchived $count products', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Unarchived $count product(s)";

    }

    header("Location: " . $_SERVER["HTTP_REFERER"]);
}

if (isset($_POST['bulk_delete_products'])) {
    validateAdminRole();
    validateCSRFToken($_POST['csrf_token']);

    $count = 0; // Default 0
    $product_ids = $_POST['product_ids']; // Get array of IDs to be deleted

    if (!empty($product_ids)) {

        // Cycle through array and delete each record
        foreach ($product_ids as $product_id) {

            $product_id = intval($product_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT product_name FROM products WHERE product_id = $product_id");
            $row = mysqli_fetch_array($sql);
            $product_name = sanitizeInput($row['product_name']);
            
            mysqli_query($mysqli, "DELETE FROM products WHERE product_id = $product_id");

            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Product', log_action = 'Delete', log_description = '$session_name deleted product $product_name', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id, log_entity_id = $product_id");

            $count++;
        }

        // Logging
        mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Product', log_action = 'Bulk Delete', log_description = '$session_name bulk deleted $count products', log_ip = '$session_ip', log_user_agent = '$session_user_agent', log_user_id = $session_user_id");

        $_SESSION['alert_message'] = "Deleted $count product(s)";

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

    if (mysqli_num_rows($sql) > 0) {
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
    mysqli_query($mysqli,"INSERT INTO logs SET log_type = 'Product', log_action = 'Export', log_description = '$session_name exported products to CSV File', log_ip = '$session_ip', log_user_agent = '$session_user_agent',  log_user_id = $session_user_id");

    exit;
}