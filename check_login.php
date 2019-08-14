<?php
	
	session_start();
	
	if(!$_SESSION['logged']){
	    header("Location: logout.php");
	    die;
	}
	
	//Check to see if its a client if so sandbox them to just post.php or client.php.
	if($_SESSION['client_id'] > 0 AND basename($_SERVER['PHP_SELF']) !== 'client.php' AND basename($_SERVER['PHP_SELF']) !== 'post.php'){
    	header("Location: logout.php");
    	die;
	}

	$session_user_id = $_SESSION['user_id'];

	$sql = mysqli_query($mysqli,"SELECT * FROM users, companies, user_companies WHERE users.user_id = user_companies.user_id AND companies.company_id = user_companies.company_id AND users.user_id = $session_user_id");
	$row = mysqli_fetch_array($sql);
	$session_name = $row['name'];
	$session_avatar = $row['avatar'];
	$session_company_id = $row['company_id'];
	$session_company_name = $row['company_name'];
	$session_client_id = $row['client_id'];
	$session_token = $row['token'];

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
	$row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('alert_id') AS num FROM alerts WHERE alert_ack_date = 0"));
  	$num_alerts = $row['num'];

?>