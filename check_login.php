<?php
	
	session_start();
	
	if(!$_SESSION['logged']){
	    header("Location: login.php");
	    die;
	}

	$session_user_id = $_SESSION['user_id'];

	$sql = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $session_user_id");
	$row = mysqli_fetch_array($sql);
	$session_name = $row['name'];
	$session_avatar = $row['avatar'];


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