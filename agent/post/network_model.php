<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$description = escapeSql($_POST['description']);
$vlan = intval($_POST['vlan']);
$network = escapeSql($_POST['network']);
$gateway = escapeSql($_POST['gateway']);
$primary_dns = escapeSql($_POST['primary_dns']);
$secondary_dns = escapeSql($_POST['secondary_dns']);
$dhcp_range = escapeSql($_POST['dhcp_range']);
$notes = escapeSql($_POST['notes']);
$location_id = intval($_POST['location'] ?? 0);
