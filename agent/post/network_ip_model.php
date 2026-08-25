<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// $ip_address is deliberately NOT escaped here - checkIpForNetwork() in
// functions/network.php normalises it first and the handler escapes the
// canonical form it hands back.
$ip_address = trim($_POST['ip_address'] ?? '');
$hostname = escapeSql($_POST['hostname'] ?? '');
$description = escapeSql($_POST['description'] ?? '');
