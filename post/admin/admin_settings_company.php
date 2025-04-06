<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_company'])) {

    validateCSRFToken($_POST['csrf_token']);

    $name = sanitizeInput($_POST['name']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zip = sanitizeInput($_POST['zip']);
    $country = sanitizeInput($_POST['country']);
    $phone_country_code = preg_replace("/[^0-9]/", '',$_POST['phone_country_code']);
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    $website = sanitizeInput($_POST['website']);

    $sql = mysqli_query($mysqli,"SELECT company_logo FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['company_logo']);

    // Company logo
    if (isset($_FILES['file']['tmp_name'])) {
        if ($new_file_name = checkFileUpload($_FILES['file'], array('jpg', 'jpeg', 'png'))) {
            $file_tmp_path = $_FILES['file']['tmp_name'];

            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/settings/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            // Delete old file
            unlink("uploads/settings/$existing_file_name");

            // Set Logo
            mysqli_query($mysqli,"UPDATE companies SET company_logo = '$new_file_name' WHERE company_id = 1");

        }
    }

    mysqli_query($mysqli,"UPDATE companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone_country_code = '$phone_country_code', company_phone = '$phone', company_email = '$email', company_website = '$website' WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited company details");

    $_SESSION['alert_message'] = "Company <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}

if (isset($_GET['remove_company_logo'])) {

    $sql = mysqli_query($mysqli,"SELECT company_logo FROM companies");
    $row = mysqli_fetch_array($sql);
    $company_logo = $row['company_logo']; // FileSystem Operation Logo is already sanitized

    unlink("uploads/settings/$company_logo");

    // Logging
    logAction("Settings", "Edit", "$session_name deleted company logo");

    $_SESSION['alert_type'] = "error";
    $_SESSION['alert_message'] = "Removed company logo";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
