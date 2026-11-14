<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

/*
 * Update a certificate - only the fields you send change. The same rules as
 * the Edit Certificate form (certificateFormSet()): a PEM in
 * certificate_public_key is the last word on the issuer, dates, names and
 * fingerprint, and a new host is checked as it is saved.
 */

// Parse ID
$certificate_id = intval($_POST['certificate_id'] ?? 0);

// Default
$update_count = false;

if (!empty($certificate_id)) {

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_id = $certificate_id AND certificate_client_id = $client_id AND certificate_archived_at IS NULL LIMIT 1"));

    if ($row) {

        // What was sent, and what the certificate has for the rest
        $post = [];
        foreach (['name', 'description', 'domain', 'issued_by', 'expire', 'public_key', 'notes', 'domain_id', 'favorite'] as $field) {
            $post[$field] = array_key_exists("certificate_$field", $_POST) ? $_POST["certificate_$field"] : ($row["certificate_$field"] ?? '');
        }
        if (!array_key_exists('certificate_expire', $_POST)) {
            $post['expire'] = $row['certificate_expire'] ? substr($row['certificate_expire'], 0, 10) : '';
        }

        $form = certificateFormSet($post, $client_id, $error);
        if ($form === null) {
            http_response_code(400);
            $return_arr['success'] = "False";
            $return_arr['message'] = $error;
            echo json_encode($return_arr);
            exit();
        }

        $before = certificateHistorySnapshot($certificate_id);
        $update_sql = mysqli_query($mysqli, "UPDATE certificates SET {$form['set']} WHERE certificate_id = $certificate_id AND certificate_client_id = $client_id LIMIT 1");

        if ($update_sql) {
            // A certificate saved as it was still counts as updated
            $update_count = 1;
            certificateWriteHistory($certificate_id, $before, certificateHistorySnapshot($certificate_id));

            // A new host is a new place to check - a certificate newly pasted is kept over what it serves
            $host_changed = $form['host'] && $form['host']['display'] !== (string) $row['certificate_domain'];
            $pasted_new = $form['parsed'] && $form['parsed']['fingerprint'] !== (string) $row['certificate_fingerprint'];
            if (!$form['host']) {
                mysqli_query($mysqli, "UPDATE certificates SET certificate_check_status = NULL, certificate_checked_at = NULL WHERE certificate_id = $certificate_id");
            } elseif ($host_changed) {
                certificateCheckAndSave($certificate_id, $pasted_new ? $form['parsed']['pem'] : '');
            }

            $name = escapeSql($form['name']);
            logAudit("Certificate", "Edit", "$name via API ($api_key_name)", $client_id, $certificate_id);
            logAudit("API", "Success", "Edited certificate $name via API ($api_key_name)", $client_id, $certificate_id);
        }
    }
}

// Output
require_once '../update_output.php';
