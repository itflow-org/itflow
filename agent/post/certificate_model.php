<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$description = escapeSql($_POST['description']);
$domain = escapeSql($_POST['domain']);
$issued_by = escapeSql($_POST['issued_by']);
$expire = escapeSql($_POST['expire']);
$public_key = escapeSql($_POST['public_key']);
$notes = escapeSql($_POST['notes']);
$domain_id = intval($_POST['domain_id'] ?? 0);
