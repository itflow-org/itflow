<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$description = escapeSql($_POST['description']);
$type = escapeSql($_POST['type']);
$make = escapeSql($_POST['make']);
$model = escapeSql($_POST['model']);
$serial = escapeSql($_POST['serial']);
$os = escapeSql($_POST['os']);
$ip = escapeSql($_POST['ip']);
$dhcp = intval($_POST['dhcp'] ?? 0);
if ($dhcp == 1) {
    $ip = 'DHCP';
}
$ipv6 = escapeSql($_POST['ipv6']);
$nat_ip = escapeSql($_POST['nat_ip']);
$mac = escapeSql($_POST['mac']);
$uri = escapeSql($_POST['uri']);
$uri_2 = escapeSql($_POST['uri_2']);
$uri_client = escapeSql($_POST['uri_client']);
$status = escapeSql($_POST['status']);
$location = intval($_POST['location'] ?? 0);
$physical_location = escapeSql($_POST['physical_location']);
$vendor = intval($_POST['vendor'] ?? 0);
$contact = intval($_POST['contact'] ?? 0);
$network = intval($_POST['network'] ?? 0);
$purchase_reference = escapeSql($_POST['purchase_reference']);
$purchase_date = escapeSql($_POST['purchase_date']);
if (empty($purchase_date)) {
    $purchase_date = "NULL";
} else {
    $purchase_date = "'" . $purchase_date . "'";
}
$warranty_expire = escapeSql($_POST['warranty_expire']);
if (empty($warranty_expire)) {
    $warranty_expire = "NULL";
} else {
    $warranty_expire = "'" . $warranty_expire . "'";
}
$install_date = escapeSql($_POST['install_date']);
if (empty($install_date)) {
    $install_date = "NULL";
} else {
    $install_date = "'" . $install_date . "'";
}
$notes = escapeSql($_POST['notes']);
$favorite = intval($_POST['favorite'] ?? 0);
