<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = escapeSql($_POST['name']);
$type = escapeSql($_POST['type']);
$website = preg_replace("(^https?://)", "", escapeSql($_POST['website']));
$rate = floatval($_POST['rate'] ?? 0);
$net_terms = intval($_POST['net_terms'] ?? $config_default_net_terms);
$tax_id_number = escapeSql($_POST['tax_id_number'] ?? '');
$abbreviation = escapeSql($_POST['abbreviation'] ?? '');
if (empty($abbreviation)) {
    $abbreviation = shortenClientName($name);
}
$notes = escapeSql($_POST['notes'] ?? '');
$lead = intval($_POST['lead'] ?? 0);
// Reminders and statements - only when the Billing tab was on the form
$reminders = isset($_POST['reminders_field']) ? intval(!empty($_POST['reminders'])) : null;
// Its tax rate and exemption - only when the Billing tab was on the form;
// client.php adds the client's row when editing (taxClientSetSql())
$tax_input = isset($_POST['tax_fields']) ? [
    'default_tax_id' => $_POST['default_tax_id'] ?? 0,
    'tax_exempt' => !empty($_POST['tax_exempt']),
    'tax_exempt_number' => $_POST['tax_exempt_number'] ?? '',
    'tax_exempt_expires' => $_POST['tax_exempt_expires'] ?? '',
] : null;
