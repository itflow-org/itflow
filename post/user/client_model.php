<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = sanitizeInput($_POST['name']);
$type = sanitizeInput($_POST['type']);
$website = preg_replace("(^https?://)", "", sanitizeInput($_POST['website']));
$referral = sanitizeInput($_POST['referral']);
$rate = floatval($_POST['rate'] ?? 0);
$currency_code = sanitizeInput($_POST['currency_code'] ?? $session_company_currency); // So we dont have to to have a hidden form input if module sales is disabled
$net_terms = intval($_POST['net_terms'] ?? $config_default_net_terms);
$tax_id_number = sanitizeInput($_POST['tax_id_number'] ?? '');
$abbreviation = sanitizeInput($_POST['abbreviation']);
if (empty($abbreviation)) {
	$abbreviation = shortenClient($name);
}
$notes = sanitizeInput($_POST['notes']);
$lead = intval($_POST['lead'] ?? 0);
