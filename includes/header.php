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
    <?php if(file_exists('../uploads/favicon.ico')): ?>
        <link rel="icon" type="image/x-icon" href="../../uploads/favicon.ico">
    <?php endif; ?>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="../../plugins/fontawesome-free/css/all.min.css">

    <!-- Custom Styles -->
    <link href="../../plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" type="text/css">
    <link href="../../plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css">
    <link href="../../plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css" rel="stylesheet" type="text/css">
    <link href="../../plugins/daterangepicker/daterangepicker.css" rel="stylesheet">
    <link href="../../plugins/toastr/toastr.min.css" rel="stylesheet">
    <link href="../../plugins/DataTables/datatables.min.css" rel="stylesheet">
    <link href="../../plugins/intl-tel-input/css/intlTelInput.min.css" rel="stylesheet">
    <link href="../../css/itflow_custom.css" rel="stylesheet">
    <link rel="stylesheet" href="../../plugins/adminlte/css/adminlte.min.css">

    <!-- Scripts -->
    <script src="../../plugins/jquery/jquery.min.js"></script>
    <script src="../../plugins/toastr/toastr.min.js"></script>
</head>
<body class="
    hold-transition sidebar-mini layout-fixed layout-navbar-fixed 
    accent-<?php echo isset($_GET['client_id']) ? 'blue' : nullable_htmlentities($config_theme); ?>
    <?php if ($user_config_theme_dark) echo 'dark-mode'; ?>
">
    <div class="wrapper text-sm">

