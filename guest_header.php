<?php

require_once "config.php";
require_once "functions.php";

session_start();

// Set Timezone
require_once "inc_set_timezone.php";

$ip = sanitizeInput(getIP());
$user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT']);
$os = sanitizeInput(getOS($user_agent));
$browser = sanitizeInput(getWebBrowser($user_agent));

// Get Company Name
$sql = mysqli_query($mysqli, "SELECT company_name FROM companies WHERE company_id = 1");
$row = mysqli_fetch_array($sql);

$session_company_name = $row['company_name'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">

    <title><?php echo nullable_htmlentities($session_company_name); ?></title>

    <!-- 
    Favicon
    If Fav Icon exists else use the default one 
    -->
    <?php if(file_exists('uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">

    <!-- Custom Style Sheet -->
    <link href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" type="text/css">
    <link href="plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css">
    <link href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css" rel="stylesheet" type="text/css">
    <link href='plugins/daterangepicker/daterangepicker.css' rel='stylesheet' />

</head>
<body class="layout-top-nav">
<div class="wrapper text-sm">

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">

        <!-- Main content -->
        <div class="content">
            <div class="container">

                <?php
                //Alert Feedback
                if (!empty($_SESSION['alert_message'])) {
                    if (!isset($_SESSION['alert_type'])) {
                        $_SESSION['alert_type'] = "info";
                    }
                    ?>
                    <div class="alert alert-<?php echo $_SESSION['alert_type']; ?>" id="alert">
                        <?php echo nullable_htmlentities($_SESSION['alert_message']); ?>
                        <button class='close' data-dismiss='alert'>&times;</button>
                    </div>
                    <?php

                    unset($_SESSION['alert_type']);
                    unset($_SESSION['alert_message']);

                }
                ?>
