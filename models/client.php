<?php
$name = sanitizeInput($_POST['name']);
$type = sanitizeInput($_POST['type']);
$website = preg_replace("(^https?://)", "", sanitizeInput($_POST['website']));
$referral = sanitizeInput($_POST['referral']);
$rate = floatval($_POST['rate']);
$currency_code = sanitizeInput($_POST['currency_code']);
$net_terms = intval($_POST['net_terms']);
$tax_id_number = sanitizeInput($_POST['tax_id_number']);
$notes = sanitizeInput($_POST['notes']);
