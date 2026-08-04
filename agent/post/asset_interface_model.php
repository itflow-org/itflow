<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$primary_interface = escapeSql($_POST['primary_interface']) ?? 0;
$description = escapeSql($_POST['description']);
$type = escapeSql($_POST['type']);
$mac = escapeSql($_POST['mac']);
$ip = escapeSql($_POST['ip']);
if ($_POST['dhcp'] == 1){
    $ip = 'DHCP';
}
$nat_ip = escapeSql($_POST['nat_ip']);
$ipv6 = escapeSql($_POST['ipv6']);
$network = intval($_POST['network']);
$notes = escapeSql($_POST['notes']);
$connected_to = intval($_POST['connected_to']);
