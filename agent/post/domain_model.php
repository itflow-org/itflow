<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = preg_replace("(^https?://)", "", escapeSql($_POST['name']));
$description = escapeSql($_POST['description']);
$registrar = intval($_POST['registrar'] ?? 0);
$dnshost = intval($_POST['dnshost'] ?? 0);
$webhost = intval($_POST['webhost'] ?? 0);
$mailhost = intval($_POST['mailhost'] ?? 0);
$expire = escapeSql($_POST['expire']);
$notes = escapeSql($_POST['notes']);
