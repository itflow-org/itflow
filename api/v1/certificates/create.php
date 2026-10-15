<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

/*
 * Create a certificate - a name and/or the host it is served on
 * (certificate_domain, as host or host:port). The same rules as the New
 * Certificate form (certificateFormSet()): the host is tidied, a PEM in
 * certificate_public_key is the last word on the issuer, dates, names and
 * fingerprint, the domain must be one of the client's (matched from the host
 * when none is given), and a host is checked as it is saved.
 */

$insert_id = false;

if (!empty($client_id) && is_array($_POST)) {
    $post = [];
    foreach (['name', 'description', 'domain', 'issued_by', 'expire', 'public_key', 'notes', 'domain_id', 'favorite'] as $field) {
        $post[$field] = $_POST["certificate_$field"] ?? '';
    }

    $form = certificateFormSet($post, $client_id, $error);
    if ($form !== null) {
        $insert_sql = mysqli_query($mysqli, "INSERT INTO certificates SET {$form['set']}, certificate_client_id = " . intval($client_id));

        if ($insert_sql) {
            $insert_id = mysqli_insert_id($mysqli);

            // What the host serves - unless a different certificate was posted, which is kept
            if ($form['host']) {
                certificateCheckAndSave($insert_id, $form['parsed']['pem'] ?? '', false);
            }

            $name = escapeSql($form['name']);
            logAudit("Certificate", "Create", "$name via API ($api_key_name)", $client_id, $insert_id);
            logAudit("API", "Success", "Created certificate $name via API ($api_key_name)", $client_id, $insert_id);
        }
    }
}

// Output
require_once '../create_output.php';
