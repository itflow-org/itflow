<?php

/*
 * ITFlow - Database update to version 2.8.4 (from 2.8.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // What a certificate says about itself - when it was issued, the names it
    // covers and its SHA-256 fingerprint - and what the last check of the
    // site found (ok, mismatch, untrusted, expired, unreachable) and when
    mysqli_query($mysqli, "ALTER TABLE `certificates`
        ADD COLUMN `certificate_issued_at` date DEFAULT NULL AFTER `certificate_issued_by`,
        ADD COLUMN `certificate_names` text DEFAULT NULL AFTER `certificate_domain`,
        ADD COLUMN `certificate_fingerprint` varchar(100) DEFAULT NULL AFTER `certificate_public_key`,
        ADD COLUMN `certificate_check_status` varchar(20) DEFAULT NULL AFTER `certificate_fingerprint`,
        ADD COLUMN `certificate_checked_at` datetime DEFAULT NULL AFTER `certificate_accessed_at`
    ");

    // Fill those in from the certificates already on file
    $sql_certificate_update = mysqli_query($mysqli, "SELECT certificate_id, certificate_public_key FROM certificates WHERE certificate_public_key LIKE '%BEGIN CERTIFICATE%'");
    while ($row_certificate_update = mysqli_fetch_assoc($sql_certificate_update)) {
        $parsed_certificate_update = @openssl_x509_parse($row_certificate_update['certificate_public_key']);
        if (!$parsed_certificate_update) {
            continue;
        }
        $names_certificate_update = [];
        foreach (explode(',', (string) ($parsed_certificate_update['extensions']['subjectAltName'] ?? '')) as $alt_name) {
            $alt_name = trim($alt_name);
            if (stripos($alt_name, 'DNS:') === 0) {
                $names_certificate_update[] = strtolower(substr($alt_name, 4));
            } elseif (stripos($alt_name, 'IP Address:') === 0) {
                $names_certificate_update[] = strtolower(trim(substr($alt_name, 11)));
            }
        }
        if (!$names_certificate_update && !empty($parsed_certificate_update['subject']['CN'])) {
            $names_certificate_update[] = strtolower(is_array($parsed_certificate_update['subject']['CN']) ? end($parsed_certificate_update['subject']['CN']) : $parsed_certificate_update['subject']['CN']);
        }
        $fingerprint_certificate_update = @openssl_x509_fingerprint($row_certificate_update['certificate_public_key'], 'sha256');
        mysqli_query($mysqli, "UPDATE certificates SET
            certificate_issued_at = '" . date('Y-m-d', $parsed_certificate_update['validFrom_time_t']) . "',
            certificate_names = '" . mysqli_real_escape_string($mysqli, implode("\n", array_unique($names_certificate_update))) . "',
            certificate_fingerprint = '" . mysqli_real_escape_string($mysqli, $fingerprint_certificate_update ? strtoupper(implode(':', str_split($fingerprint_certificate_update, 2))) : '') . "'
            WHERE certificate_id = " . intval($row_certificate_update['certificate_id']));
    }

    // A certificate linked to another client's domain, or to one that is gone, is linked to none
    mysqli_query($mysqli, "UPDATE `certificates` LEFT JOIN `domains` ON `domain_id` = `certificate_domain_id` AND `domain_client_id` = `certificate_client_id`
        SET `certificate_domain_id` = 0 WHERE `certificate_domain_id` > 0 AND `domain_id` IS NULL");
