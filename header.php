<?php
  //Debug - Page Load Time
  $time = microtime();
  $time = explode(' ', $time);
  $time = $time[1] + $time[0];
  $start = $time;
?>

<?php 

  include("config.php");
  include("check_login.php");
  include("vendor/Parsedown.php");
  include("functions.php");

?>

<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

  <title><?php echo $config_app_name; ?></title>

  <link href="vendor/easy-markdown-editor/css/easymde.css" rel="stylesheet" type="text/css">

  <!-- Custom fonts for this template-->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet" type="text/css">
  <link href="vendor/datepicker/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" type="text/css">


  <!-- Custom Style Sheet -->
  <link href="css/style.css" rel="stylesheet" type="text/css">
  <link href="vendor/bootstrap-select/css/bootstrap-select.min.css" rel="stylesheet" type="text/css">
  <link href='vendor/fullcalendar/core/main.min.css' rel='stylesheet' />
  <link href='vendor/fullcalendar/daygrid/main.min.css' rel='stylesheet' />
  <link href='vendor/fullcalendar/timegrid/main.min.css' rel='stylesheet' />
  <link href='vendor/fullcalendar/list/main.min.css' rel='stylesheet' />
  <link href='vendor/fullcalendar/bootstrap/main.min.css' rel='stylesheet' />
  <link href='vendor/daterangepicker/daterangepicker.css' rel='stylesheet' />
  <link href="vendor/Inputmask/css/inputmask.css" rel="stylesheet" />
  
</head>

<body id="page-top">
  
  <?php include("top_nav.php"); ?>

  <div id="wrapper">
    
    <?php 
    
    if(basename(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)) == "client.php"){
      include("client_side_nav.php");
    }elseif(basename(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)) == "client_print.php"){

    }else{
      include("side_nav.php");
    }

    ?>
    
    <div id="content-wrapper">
      
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