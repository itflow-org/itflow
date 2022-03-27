<?php 

include("config.php");
include_once("functions.php");
include("check_login.php");

if($session_user_role != 3){
  $_SESSION['alert_type'] = "danger";
  $_SESSION['alert_message'] = "You are not permitted to do that!";
  header("Location: index.php");
  exit();
}

include("header.php");
include("top_nav.php");
include("admin_side_nav.php");
include("inc_wrapper.php");
include("inc_alert_feedback.php");

?>