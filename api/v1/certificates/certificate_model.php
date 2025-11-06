<?php

// Variable assignment from POST (or: blank/from DB is updating)

if (isset($_POST['certificate_name'])) {
    $name = sanitizeInput($_POST['certificate_name']);
} elseif ($certificate_row) {
    $name = $certificate_row['certificate_name'];
} else {
    $name = '';
}

if (isset($_POST['certificate_description'])) {
    $description = sanitizeInput($_POST['certificate_description']);
} elseif ($certificate_row) {
    $description = $certificate_row['certificate_description'];
} else {
    $description = '';
}

if (isset($_POST['certificate_domain'])) {
    $domain = sanitizeInput($_POST['certificate_domain']);
} elseif ($certificate_row) {
    $domain = $certificate_row['certificate_domain'];
} else {
    $domain = '';
}

if (isset($_POST['certificate_issued_by'])) {
    $issued_by = sanitizeInput($_POST['certificate_issued_by']);
} elseif ($certificate_row) {
    $issued_by = $certificate_row['certificate_issued_by'];
} else {
    $issued_by = '';
}

if (isset($_POST['certificate_expire'])) {
    $expire = sanitizeInput($_POST['certificate_expire']);
} elseif ($certificate_row) {
    $expire = $certificate_row['certificate_expire'];
} else {
    $expire = '';
}

if (isset($_POST['certificate_public_key'])) {
    $public_key = sanitizeInput($_POST['certificate_public_key']);
} elseif ($certificate_row) {
    $public_key = $certificate_row['certificate_public_key'];
} else {
    $public_key = '';
}

if (isset($_POST['certificate_notes'])) {
    $notes = sanitizeInput($_POST['certificate_notes']);
} elseif ($certificate_row) {
    $notes = $certificate_row['certificate_notes'];
} else {
    $notes = '';
}

if (isset($_POST['certificate_domain_id'])) {
    $domain_id = intval($_POST['certificate_domain_id']);
} elseif ($certificate_row) {
    $domain_id = intval($certificate_row['certificate_domain_id']);
} else {
    $domain_id = 0;
}
