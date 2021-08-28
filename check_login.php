<?php
	//Check to see if setup is enabled
	if(!isset($config_enable_setup) or $config_enable_setup == 1){
  	header("Location: setup.php");
	}

	if(!isset($_SESSION)){
    session_start();
	}
	
	if(!$_SESSION['logged']){
    header("Location: logout.php");
    die;
	}

	$session_user_id = $_SESSION['user_id'];

	$sql = mysqli_query($mysqli,"SELECT * FROM users, permissions  WHERE users.user_id = permissions.user_id AND users.user_id = $session_user_id");
	
	$row = mysqli_fetch_array($sql);
	$session_name = $row['user_name'];
	$session_email = $row['user_email'];
	$session_avatar = $row['user_avatar'];
	$session_company_id = $row['permission_default_company'];
	$session_token = $row['user_token'];

	$session_permission_level = $row['permission_level'];
  if($session_permission_level == 5){
    $session_permission_level_display = "Global Administrator";
  }elseif($session_permission_level == 4){
    $session_permission_level_display = "Administrator";
  }elseif($session_permission_level == 3){
    $session_permission_level_display = "Technician";
  }elseif($session_permission_level == 2){
    $session_permission_level_display = "IT Contractor";
  }else{
    $session_permission_level_display = "Accounting";  
  }
	$session_permission_companies_array = explode(",",$row['permission_companies']);
	$session_permission_companies = $row['permission_companies'];
	$session_permission_clients_array = explode(",",$row['permission_clients']);
	$session_permission_clients = $row['permission_clients'];

	$sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = $session_company_id");
	$row = mysqli_fetch_array($sql);

	$session_company_name = $row['company_name'];

	include("get_settings.php");

	//Detects if using an apple device and uses apple maps instead of google
	$iPod    = stripos($_SERVER['HTTP_USER_AGENT'],"iPod");
	$iPhone  = stripos($_SERVER['HTTP_USER_AGENT'],"iPhone");
	$iPad    = stripos($_SERVER['HTTP_USER_AGENT'],"iPad");

	if( $iPod || $iPhone || $iPad){
	    $session_map_source = "apple";
	}else{
		$session_map_source = "google";
	}

	//Get unAcked Alert Count for the badge on the top nav
	$row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('alert_id') AS num FROM alerts WHERE alert_ack_date IS NULL AND company_id = $session_company_id"));
  $num_alerts = $row['num'];

?>
