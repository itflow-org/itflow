<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$description = escapeSql($_POST['description']);
$type = escapeSql($_POST['type']);
$color = escapeSql($_POST['color']);
