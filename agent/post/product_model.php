<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = sanitizeInput($_POST['name']);
$description = sanitizeInput($_POST['description']);
$code = sanitizeInput($_POST['code']);
$location = sanitizeInput($_POST['location']);
$price = floatval($_POST['price']);
$category = intval($_POST['category']);
$tax = intval($_POST['tax']);
