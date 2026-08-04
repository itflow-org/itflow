<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$folder = intval($_POST['folder']);
$description = escapeSql($_POST['description']);
$content = mysqli_real_escape_string($mysqli,$_POST['content']);
$content_raw = escapeSql($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
// Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.
