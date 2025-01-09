<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = sanitizeInput($_POST['name']);
$description = sanitizeInput($_POST['description']);
$vlan = intval($_POST['vlan']);
$network = sanitizeInput($_POST['network']);
$subnet = sanitizeInput($_POST['subnet']);
$gateway = sanitizeInput($_POST['gateway']);
$primary_dns = sanitizeInput($_POST['primary_dns']);
$secondary_dns = sanitizeInput($_POST['secondary_dns']);
$dhcp_range = sanitizeInput($_POST['dhcp_range']);
$notes = sanitizeInput($_POST['notes']);
$location_id = intval($_POST['location']);
$client_id = intval($_POST['client_id']);
