<?php

// Variable assignment from POST (or: blank/from DB is updating)

if (isset($_POST['client_name'])) {
    $name = sanitizeInput($_POST['client_name']);
} elseif ($client_row) {
    $name = $client_row['client_name'];
} else {
    $name = '';
}

if (isset($_POST['client_type'])) {
    $type = sanitizeInput($_POST['client_type']);
} elseif ($client_row) {
    $type = $client_row['client_type'];
} else {
    $type = '';
}

if (isset($_POST['client_website'])) {
    $website = preg_replace("(^https?://)", "", sanitizeInput($_POST['client_website']));
} elseif ($client_row) {
    $website = $client_row['client_website'];
} else {
    $website = '';
}

if (isset($_POST['client_referral'])) {
    $referral = sanitizeInput($_POST['client_referral']);
} elseif ($client_row) {
    $referral = $client_row['client_referral'];
} else {
    $referral = '';
}

if (isset($_POST['client_rate'])) {
    $rate = floatval($_POST['client_rate']);
} elseif ($client_row) {
    $rate = $client_row['client_rate'];
} else {
    $rate = 0;
}

if (isset($_POST['client_currency_code'])) {
    $currency_code = sanitizeInput($_POST['client_currency_code']);
} elseif ($client_row) {
    $currency_code = $client_row['client_currency_code'];
} else {
    $currency_code = '';
}

if (isset($_POST['client_net_terms'])) {
    $net_terms = intval($_POST['client_net_terms']);
} elseif ($client_row) {
    $net_terms = $client_row['client_net_terms'];
} else {
    $net_terms = 0;
}

if (isset($_POST['client_tax_id_number'])) {
    $tax_id_number = sanitizeInput($_POST['client_tax_id_number']);
} elseif ($client_row) {
    $tax_id_number = $client_row['client_tax_id_number'];
} else {
    $tax_id_number = '';
}

if (isset($_POST['client_is_lead'])) {
    $lead = intval($_POST['client_is_lead']);
} elseif ($client_row) {
    $lead = $client_row['client_is_lead'];
} else {
    $lead = 0; // Default: Not a lead
}

if (isset($_POST['client_notes'])) {
    $notes = sanitizeInput($_POST['client_notes']);
} elseif ($client_row) {
    $notes = $client_row['client_notes'];
} else {
    $notes = '';
}
