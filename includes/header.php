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

    <title><?php echo $session_company_name; ?></title>

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
    <link rel="stylesheet" href="plugins/adminlte/css/adminlte.min.css">

    <!-- Custom Style Sheet -->
    <link href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" type="text/css">
    <link href="plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css">
    <link href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css" rel="stylesheet" type="text/css">
    <link href='plugins/daterangepicker/daterangepicker.css' rel='stylesheet' />
    <link href="plugins/toastr/toastr.min.css" rel="stylesheet">
    <link href="plugins/DataTables/datatables.min.css" rel="stylesheet">
    <link href="plugins/intl-tel-input/css/intlTelInput.min.css" rel="stylesheet">
    <!-- CSS to allow regular button to show as block button in mobile response view using the class btn-responsive -->
    <link href="css/itflow_custom.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <script src="plugins/toastr/toastr.min.js"></script>

</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed accent-<?php if (isset($_GET['client_id'])) { echo "blue"; } else { echo nullable_htmlentities($config_theme); } ?>">
    <div class="wrapper text-sm">
