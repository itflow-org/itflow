<?php
/*
 * Client Portal
 * Includes for all pages (except login)
 */

include('../config.php');
include('../functions.php');
include('check_login.php');
include('portal_functions.php');

if(!isset($_SESSION)){
  // HTTP Only cookies
  ini_set("session.cookie_httponly", True);
  if($config_https_only){
    // Tell client to only send cookie(s) over HTTPS
    ini_set("session.cookie_secure", True);
  }
  session_start();
}

include("portal_header.php");