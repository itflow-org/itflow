<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = sanitizeInput($_POST['name']);
$description = sanitizeInput($_POST['description']);
$domain = sanitizeInput($_POST['domain']);
$issued_by = sanitizeInput($_POST['issued_by']);
$expire = sanitizeInput($_POST['expire']);
$public_key = sanitizeInput($_POST['public_key']);
$notes = sanitizeInput($_POST['notes']);
$domain_id = intval($_POST['domain_id']);
$client_id = intval($_POST['client_id']);
