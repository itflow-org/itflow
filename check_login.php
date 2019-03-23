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

?>