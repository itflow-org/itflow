<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$label = escapeSql($_POST['label']);
$type = escapeSql($_POST['type']);
