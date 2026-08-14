<?php

    // Calculate Execution time start
    // uncomment for test
    // $time_start = microtime(true);

header("X-Frame-Options: DENY");

?>

<!DOCTYPE html>
<html lang="en"<?php if ($user_config_theme_dark) echo ' data-bs-theme="dark"'; ?>>
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
    <link rel="stylesheet" href="/libs/flatpickr/css/flatpickr.min.css">
    <link rel="stylesheet" href="/libs/tom-select/css/tom-select.bootstrap5.min.css">
    <link rel="stylesheet" href="/libs/sweetalert2/css/sweetalert2.min.css">
    <link rel="stylesheet" href="/libs/DataTables/datatables.min.css">
    <link rel="stylesheet" href="/libs/intl-tel-input/css/intlTelInput.min.css">
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">

    <!-- Scripts -->
</head>
<body class="layout-fixed sidebar-expand-lg app-loaded theme-<?= escapeHtml($config_theme) ?>">
    <div class="app-wrapper text-sm">

