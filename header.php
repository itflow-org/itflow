<?php 

  // Calculate Execution time start
  // uncomment for test
  //$time_start = microtime(true);

header("X-Frame-Options: DENY");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="robots" content="noindex">

  <title><?php echo "$session_company_name | $config_app_name"; ?></title>

  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">

  <!-- Theme style -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <!-- Google Font: Source Sans Pro -->
  <!-- <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet"> -->

  <!-- Custom Style Sheet -->
  <link href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" type="text/css">
  <link href="plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css">
  <link href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css" rel="stylesheet" type="text/css">
  <link href='plugins/daterangepicker/daterangepicker.css' rel='stylesheet' />
  <link href="plugins/summernote/summernote-bs4.min.css" rel="stylesheet">
  <link href="plugins/toastr/toastr.min.css" rel="stylesheet">
  <!-- <link href="plugins/dropzone/min/dropzone.min.css" rel="stylesheet"> -->
  <!-- jQuery -->
  <script src="plugins/jquery/jquery.min.js"></script>
  <script src="plugins/toastr/toastr.min.js"></script>

</head>
<body class="hold-transition sidebar-mini layout-fixed accent-<?php echo $config_theme; ?>">
  <div class="wrapper text-sm">