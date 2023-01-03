<?php 

include("config.php");
include_once("functions.php");
include("check_login.php");

validateAdminRole();

include("header.php");
include("top_nav.php");
include("settings_side_nav.php");
include("inc_wrapper.php");
include("inc_alert_feedback.php");
include("pagination_head.php");

?>