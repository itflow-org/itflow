<?php 

  include("config.php");
  include("check_login.php");
  include("vendor/Parsedown.php");
  //include("functions.php");

?>

<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

  <title><?php echo $config_company_name; ?></title>

  <link href="vendor/easy-markdown-editor-2.5.1/dist/easymde.min.css" rel="stylesheet" type="text/css">

  <!-- Page level plugin CSS-->
  <link href="vendor/datatables/dataTables.bootstrap4.css" rel="stylesheet">

  <!-- Custom fonts for this template-->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Custom styles for this template-->
  <link href="css/sb-admin.css" rel="stylesheet">

  

  <!-- Custom Style Sheet -->
  <link href="css/style.css" rel="stylesheet">
  
</head>

<body id="page-top">
  
  <?php include("top_nav.php"); ?>

  <div id="wrapper">
    
    <?php include("side_nav.php"); ?>
    
    <div id="content-wrapper">
      
      <div class="container-fluid">