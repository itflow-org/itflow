<?php

require_once("config.php");
require_once("functions.php");

session_start();

$ip = trim(strip_tags(mysqli_real_escape_string($mysqli, getIP())));
$user_agent = strip_tags(mysqli_real_escape_string($mysqli, $_SERVER['HTTP_USER_AGENT']));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">

    <title><?php echo $config_app_name; ?></title>

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
                        <?php echo $_SESSION['alert_message']; ?>
                        <button class='close' data-dismiss='alert'>&times;</button>
                    </div>
                    <?php

                    unset($_SESSION['alert_type']);
                    unset($_SESSION['alert_message']);

                }
                ?>
