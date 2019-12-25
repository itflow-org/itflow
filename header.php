<?php 

  include("config.php");
  include("check_login.php");
  include("functions.php");

?>

<!DOCTYPE html>
<!--
This is a starter template page. Use this page to start your new project from
scratch. This page gets rid of all links and provides the needed markup only.
-->
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="x-ua-compatible" content="ie=edge">

  <title><?php echo $config_app_name; ?></title>

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
  <link href='plugins/fullcalendar/main.min.css' rel='stylesheet' />
  <link href='plugins/fullcalendar-daygrid/main.min.css' rel='stylesheet' />
  <link href='plugins/fullcalendar-timegrid/main.min.css' rel='stylesheet' />
  <link href='plugins/fullcalendar-bootstrap/main.min.css' rel='stylesheet' />
  <link href='plugins/daterangepicker/daterangepicker.css' rel='stylesheet' />
  <link href="plugins/summernote/summernote-bs4.css" rel="stylesheet">

</head>
<body class="hold-transition sidebar-mini">
  <div class="wrapper text-sm">
    <?php include("top_nav.php"); ?>

    <?php 
    
    if(basename(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)) == "client.php"){
      include("client_side_nav.php");
    }elseif(basename(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)) == "client_print.php"){

    }else{
      include("side_nav.php");
    } 
    
    ?>
    
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">

      <!-- Main content -->
      <div class="content mt-3">
        <div class="container-fluid">

    <?php
    //Alert Feedback
    if(!empty($_SESSION['alert_message'])){
      ?>
        <div class="alert alert-success alert-<?php echo $_SESSION['alert_type']; ?>" id="alert">
          <?php echo $_SESSION['alert_message']; ?>
          <button class='close' data-dismiss='alert'>&times;</button>
        </div>
      <?php
      
      $_SESSION['alert_type'] = '';
      $_SESSION['alert_message'] = '';

    }

    ?>