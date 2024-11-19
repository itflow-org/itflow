<?php

if (isset($_POST['edit_company'])) {

    validateCSRFToken($_POST['csrf_token']);

    $name = sanitizeInput($_POST['name']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zip = sanitizeInput($_POST['zip']);
    $country = sanitizeInput($_POST['country']);
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    $website = sanitizeInput($_POST['website']);

    $sql = mysqli_query($mysqli,"SELECT company_logo FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $existing_file_name = sanitizeInput($row['company_logo']);

    // Company logo
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

    mysqli_query($mysqli,"UPDATE companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website' WHERE company_id = 1");

    // Logging
    logAction("Settings", "Edit", "$session_name edited company details");

    $_SESSION['alert_message'] = "Company <strong>$name</strong> edited";

    header("Location: " . $_SERVER["HTTP_REFERER"]);

}
