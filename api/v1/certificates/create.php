<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse Info
require_once 'certificate_model.php';

// Default
$insert_id = false;

// Require name and domain_name
if (!empty($name) && !empty($domain) && !empty($client_id)) {

    // TODO: Doesn't work. Making this work seems tricky. Think we need to be json-encoding the pubkey on POST and then decoding & parsing it here?
    //   For now, just let the certificate refresher catch this
    // Parse public key data if provided and expire/issued_by are missing
    if (!empty($public_key) && (empty($expire) && empty($issued_by))) {
        $public_key_obj = openssl_x509_parse($_POST['certificate_public_key']);
        if ($public_key_obj) {
            $expire = date('Y-m-d', $public_key_obj['validTo_time_t']);
            $issued_by = sanitizeInput($public_key_obj['issuer']['O']);
        }
    }

    // Normalize expire value
    if (empty($expire)) {
        $expire = "NULL";
    } else {
        $expire = "'" . $expire . "'";
    }

    // Insert certificate
    $insert_sql = mysqli_query($mysqli, "
        INSERT INTO certificates SET
        certificate_name = '$name',
        certificate_description = '$description',
        certificate_domain = '$domain',
        certificate_issued_by = '$issued_by',
        certificate_expire = $expire,
        certificate_public_key = '$public_key',
        certificate_notes = '$notes',
        certificate_domain_id = $domain_id,
        certificate_client_id = $client_id
    ");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // Logging
        logAction("Certificate", "Create", "$name via API ($api_key_name)", $client_id, $insert_id);
        logAction("API", "Success", "Created certificate $name via API ($api_key_name)", $client_id, $insert_id);
    }
}

// Output
require_once '../create_output.php';