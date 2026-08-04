<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$type = escapeSql($_POST['type']);
$website = preg_replace("(^https?://)", "", escapeSql($_POST['website']));
$referral = escapeSql($_POST['referral']);
$rate = floatval($_POST['rate'] ?? 0);
$net_terms = intval($_POST['net_terms'] ?? $config_default_net_terms);
$tax_id_number = escapeSql($_POST['tax_id_number'] ?? '');
$abbreviation = escapeSql($_POST['abbreviation'] ?? '');
if (empty($abbreviation)) {
    $abbreviation = shortenClientName($name);
}
$notes = escapeSql($_POST['notes'] ?? '');
$lead = intval($_POST['lead'] ?? 0);
