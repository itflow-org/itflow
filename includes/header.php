<?php

    // Calculate Execution time start
    // uncomment for test
    // $time_start = microtime(true);

header("X-Frame-Options: DENY");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">

    <title><?= $session_company_name; ?></title>

    <!-- Favicon -->
    <?php if(file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="/libs/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css" >
    <link rel="stylesheet" href="/libs/select2/css/select2.min.css">
    <link rel="stylesheet" href="/libs/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="/libs/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="/libs/toastr/toastr.min.css">
    <link rel="stylesheet" href="/libs/DataTables/datatables.min.css">
    <link rel="stylesheet" href="/libs/intl-tel-input/css/intlTelInput.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">

    <!-- Scripts -->
    <script src="/libs/jquery/jquery.min.js"></script>
    <script src="/libs/toastr/toastr.min.js"></script>
</head>
<body class="
    hold-transition sidebar-mini layout-fixed layout-navbar-fixed 
    accent-<?php echo nullable_htmlentities($config_theme); ?>
    <?php if ($user_config_theme_dark) echo 'dark-mode'; ?>
">
    <div class="wrapper text-sm">

