<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql(trim($_POST['name'] ?? ''));
$folder = intval($_POST['folder'] ?? 0);
$description = escapeSql($_POST['description'] ?? '');
$content = mysqli_real_escape_string($mysqli, $_POST['content'] ?? '');
// For the FULLTEXT search - see documentContentRaw()
$content_raw = escapeSql(documentContentRaw($_POST['name'] ?? '', $_POST['content'] ?? ''));
