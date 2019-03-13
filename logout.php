<?php 
	
	include("config.php");
	include("check_login.php");

	session_start();
	session_destroy();
	header('Location: login.php');

?>