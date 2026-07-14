<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$description = escapeSql($_POST['description']);
$code = escapeSql($_POST['code']);
$location = escapeSql($_POST['location']);
$price = floatval($_POST['price']);
$category = intval($_POST['category']);
$tax = intval($_POST['tax']);
