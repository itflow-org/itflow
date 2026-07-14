<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = cleanInput($_POST['name']);
$type = cleanInput($_POST['type']);
$website = preg_replace("(^https?://)", "", cleanInput($_POST['website']));
$referral = cleanInput($_POST['referral']);
$rate = floatval($_POST['rate'] ?? 0);
$net_terms = intval($_POST['net_terms'] ?? $config_default_net_terms);
$tax_id_number = cleanInput($_POST['tax_id_number'] ?? '');
$abbreviation = cleanInput($_POST['abbreviation'] ?? '');
if (empty($abbreviation)) {
    $abbreviation = shortenClientName($name);
}
$notes = cleanInput($_POST['notes'] ?? '');
$lead = intval($_POST['lead'] ?? 0);
