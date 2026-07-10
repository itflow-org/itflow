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
    <?php if(file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico')) { ?>
        <link rel="icon" href="/uploads/favicon.ico">
    <?php } ?>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">

    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="/libs/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
    <link rel="stylesheet" href="/libs/select2/css/select2.min.css">
    <link rel="stylesheet" href="/libs/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <link rel="stylesheet" href='/libs/daterangepicker/daterangepicker.css'>

    <!-- Scripts -->
    <script src="/libs/jquery/jquery.min.js"></script>
    <script src="/libs/toastr/toastr.min.js"></script>

</head>
<body class="layout-top-nav">
    <div class="wrapper text-sm">