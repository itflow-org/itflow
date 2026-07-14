<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$type = escapeSql($_POST['type']);
$color = escapeSql($_POST['color']);
