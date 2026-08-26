<?php

    // Calculate Execution time start
    // uncomment for test
    // $time_start = microtime(true);

header("X-Frame-Options: DENY");

?>

<!DOCTYPE html>
<?php /* data-color-scheme is FullCalendar v7's own switch - its themes ship a
         dark palette keyed on [data-color-scheme=dark] that nothing was turning on */ ?>
<html lang="en"<?php if ($user_config_theme_dark) echo ' data-bs-theme="dark" data-color-scheme="dark"'; ?> data-lte-print="plain">
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
    <?php /* Opt-in AdminLTE 3 palette, new in v4.5.0. Supplies the
             --bs-<colour> tokens plus .text-bg-* / .card-* / .callout-*
             / .bg-gradient-* families. Loads BEFORE itflow_custom.css so
             our own .bg-<colour> box colours still win. */ ?>
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte-colors-v3.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">

    <!-- Scripts -->
</head>
<?php /* intl-tel-input needs an ISO2 country, the companies table stores a
         country NAME - $country_iso2_array bridges the two. Passed as a data
         attribute rather than an inline <script> so it does not add to the
         CSP unsafe-inline debt. Empty when the company has no country set,
         which js/app.js reads as "let the library decide". */ ?>
<body class="layout-fixed sidebar-expand-lg app-loaded theme-<?= escapeHtml($config_theme) ?>" data-lte-primary="<?= escapeHtml($config_theme) ?>"
      data-itflow-phone-country="<?= escapeHtml($country_iso2_array[$session_company_country] ?? '') ?>">
    <div class="app-wrapper text-sm">

