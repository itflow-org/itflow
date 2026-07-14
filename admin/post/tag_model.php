<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$type = intval($_POST['type']);
$color = escapeSql($_POST['color']);
$icon = preg_replace("/[^0-9a-zA-Z-]/", "", escapeSql($_POST['icon']));
