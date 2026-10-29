<?php

// Variable assignment from POST (or: blank/from DB is updating)

if (isset($_POST['domain_name'])) {
    // Tidied the way the app keeps it - lowercase, no scheme, path or leading www.
    $name = escapeSql(domainNormalizeName($_POST['domain_name']));
} elseif (isset($domain_row) && isset($domain_row['domain_name'])) {
    $name = mysqli_real_escape_string($mysqli, $domain_row['domain_name']);
} else {
    $name = '';
}

if (isset($_POST['domain_description'])) {
    $description = escapeSql($_POST['domain_description']);
} elseif (isset($domain_row) && isset($domain_row['domain_description'])) {
    $description = mysqli_real_escape_string($mysqli, $domain_row['domain_description']);
} else {
    $description = '';
}

if (isset($_POST['domain_expire'])) {
    $expire = escapeSql($_POST['domain_expire']);
} elseif (isset($domain_row) && !empty($domain_row['domain_expire'])) {
    $expire = mysqli_real_escape_string($mysqli, $domain_row['domain_expire']);
} else {
    $expire = '';
}

if (isset($_POST['domain_notes'])) {
    $notes = escapeSql($_POST['domain_notes']);
} elseif (isset($domain_row) && isset($domain_row['domain_notes'])) {
    $notes = mysqli_real_escape_string($mysqli, $domain_row['domain_notes']);
} else {
    $notes = '';
}

if (isset($_POST['domain_registrar'])) {
    $registrar = intval($_POST['domain_registrar']);
} elseif (isset($domain_row) && isset($domain_row['domain_registrar'])) {
    $registrar = $domain_row['domain_registrar'];
} else {
    $registrar = 0;
}

if (isset($_POST['domain_webhost'])) {
    $webhost = intval($_POST['domain_webhost']);
} elseif (isset($domain_row) && isset($domain_row['domain_webhost'])) {
    $webhost = $domain_row['domain_webhost'];
} else {
    $webhost = 0;
}

if (isset($_POST['domain_dnshost'])) {
    $dnshost = intval($_POST['domain_dnshost']);
} elseif (isset($domain_row) && isset($domain_row['domain_dnshost'])) {
    $dnshost = $domain_row['domain_dnshost'];
} else {
    $dnshost = 0;
}

if (isset($_POST['domain_mailhost'])) {
    $mailhost = intval($_POST['domain_mailhost']);
} elseif (isset($domain_row) && isset($domain_row['domain_mailhost'])) {
    $mailhost = $domain_row['domain_mailhost'];
} else {
    $mailhost = 0;
}

if (isset($_POST['domain_auto_renew'])) {
    $auto_renew = intval($_POST['domain_auto_renew']) ? 1 : 0;
} elseif (isset($domain_row) && isset($domain_row['domain_auto_renew'])) {
    $auto_renew = intval($domain_row['domain_auto_renew']);
} else {
    $auto_renew = 0;
}

// The registrar and hosts must be the client's vendors or ours
$registrar = domainVendorId($registrar, $client_id);
$webhost = domainVendorId($webhost, $client_id);
$dnshost = domainVendorId($dnshost, $client_id);
$mailhost = domainVendorId($mailhost, $client_id);

// An expiry that is not a date is no expiry
if ($expire !== '' && !DateTime::createFromFormat('!Y-m-d', $expire)) {
    $expire = '';
}