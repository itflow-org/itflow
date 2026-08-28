<?php

    // Calculate Execution time start
    // uncomment for test
    // $time_start = microtime(true);

header("X-Frame-Options: DENY");

?>

<!DOCTYPE html>
<?php /* data-color-scheme is FullCalendar v7's own switch - its themes ship a
         dark palette keyed on [data-color-scheme=dark] that nothing was turning on */ ?>
<?php /* data-bs-theme is emitted for BOTH modes on purpose. AdminLTE 4 ships a
         colour-mode manager that runs at DOMContentLoaded and resolves the theme
         as localStorage['lte-theme'] ?? the markup attribute ?? prefers-color-scheme.
         With no attribute in the markup it falls through to the OS preference and
         sets data-bs-theme AFTER first paint - which is the light-to-dark flash.
         data-lte-color-mode="off" disables that manager outright: ITFlow holds the
         per-user setting in user_settings.user_config_theme_dark, so a browser-local
         localStorage key or an OS preference must not be able to override it. */ ?>
<html lang="en" data-bs-theme="<?= $user_config_theme_dark ? 'dark' : 'light' ?>"<?php if ($user_config_theme_dark) echo ' data-color-scheme="dark"'; ?> data-lte-color-mode="off" data-lte-print="plain">
<head>
    <meta charset="utf-8">
    <?php /* Must come BEFORE the stylesheets. The browser applies a meta
             color-scheme while it parses the head, so the very first paint is
             already dark. Left to CSS alone the only declaration is
             [data-bs-theme=dark]{color-scheme:dark} inside adminlte.min.css,
             which is ~580KB of render-blocking CSS away - the white canvas
             painted while that loads is the dark-mode flash. */ ?>
    <meta name="color-scheme" content="<?= $user_config_theme_dark ? 'dark' : 'light' ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">

    <title><?= $session_company_name; ?></title>

    <!-- Favicon -->
    <?php if(file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>

    <?php /* The Tom Select pair is the first thing includes/footer.php runs,
             because until it does the browser is showing raw <select>
             controls. Preloading here starts both fetches during head parse,
             in parallel with the stylesheets, so the bytes are already warm
             when the parser reaches the tag instead of being requested only
             at that point. Hints only - the <script> tags in footer.php are
             what actually load them. */ ?>
    <link rel="preload" as="script" href="/libs/tom-select/js/tom-select.complete.min.js">
    <link rel="preload" as="script" href="/js/tom_select.js">

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
<?php /* bg-body-tertiary is what tints the page behind the cards. AdminLTE 3 painted
         it on .content-wrapper; v4's .app-main has no background at all and the tint
         moved to this body utility, so the migration dropped it silently. It matters
         because --bs-card-bg is --bs-body-bg - without it every card is the exact
         colour of the page and only its border separates the two. */ ?>
<body class="layout-fixed sidebar-expand-lg app-loaded bg-body-tertiary theme-<?= escapeHtml($config_theme) ?>" data-lte-primary="<?= escapeHtml($config_theme) ?>"
      data-itflow-phone-country="<?= escapeHtml($country_iso2_array[$session_company_country] ?? '') ?>">
    <div class="app-wrapper text-sm">

