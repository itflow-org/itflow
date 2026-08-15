<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">

    <title><?= escapeHtml($session_company_name) ?></title>

    <!-- 
    Favicon
    If Fav Icon exists else use the default one 
    -->
    <?php if(file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico')) { ?>
        <link rel="icon" href="/uploads/favicon.ico">
    <?php } ?>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">

    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="/libs/flatpickr/css/flatpickr.min.css">
    <link rel="stylesheet" href="/libs/tom-select/css/tom-select.bootstrap5.min.css">

    <!-- ITFlow style: loaded last so it wins. See includes/header.php -->
    <link rel="stylesheet" href="/css/itflow_custom.css">

    <!-- Scripts -->

</head>
<body class="layout-fixed theme-<?= escapeHtml($config_theme) ?>">
    <div class="app-wrapper text-sm">