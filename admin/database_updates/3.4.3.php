<?php

/*
 * ITFlow - Database update to version 3.4.3 (from 3.4.2)
 * Included by admin/database_updates.php - do not access directly
 *
 * Client contracts, stage 2: signing. Each time a contract is sent for
 * signature is a row of contract_signatures - the document as it was sent,
 * the link's key, who it went to, and the client's signature and the
 * agent's countersignature as they come in (functions/contract_signature.php).
 * Earlier rounds stay, so a contract signed again keeps the copies signed
 * before. The contract's own signature and snapshot columns, which nothing
 * wrote, go - a signature found in one is kept as a round of its own first.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `contract_signatures` (
        `contract_signature_id` int(11) NOT NULL AUTO_INCREMENT,
        `contract_signature_contract_id` int(11) NOT NULL,
        `contract_signature_url_key` varchar(64) NOT NULL DEFAULT '',
        `contract_signature_document` mediumtext NOT NULL,
        `contract_signature_document_hash` char(64) NOT NULL DEFAULT '',
        `contract_signature_contact_id` int(11) NOT NULL DEFAULT 0,
        `contract_signature_sent_to_name` varchar(200) DEFAULT NULL,
        `contract_signature_sent_to_email` varchar(255) DEFAULT NULL,
        `contract_signature_sent_at` datetime DEFAULT NULL,
        `contract_signature_sent_by` int(11) NOT NULL DEFAULT 0,
        `contract_signature_viewed_at` datetime DEFAULT NULL,
        `contract_signature_client_name` varchar(200) DEFAULT NULL,
        `contract_signature_client_title` varchar(200) DEFAULT NULL,
        `contract_signature_client_image` mediumtext DEFAULT NULL,
        `contract_signature_client_signed_at` datetime DEFAULT NULL,
        `contract_signature_client_via` varchar(20) DEFAULT NULL,
        `contract_signature_client_contact_id` int(11) NOT NULL DEFAULT 0,
        `contract_signature_client_ip` varchar(45) DEFAULT NULL,
        `contract_signature_client_user_agent` varchar(255) DEFAULT NULL,
        `contract_signature_agent_user_id` int(11) NOT NULL DEFAULT 0,
        `contract_signature_agent_name` varchar(200) DEFAULT NULL,
        `contract_signature_agent_title` varchar(200) DEFAULT NULL,
        `contract_signature_agent_image` mediumtext DEFAULT NULL,
        `contract_signature_agent_signed_at` datetime DEFAULT NULL,
        `contract_signature_agent_ip` varchar(45) DEFAULT NULL,
        `contract_signature_withdrawn_at` datetime DEFAULT NULL,
        `contract_signature_withdrawn_reason` varchar(255) DEFAULT NULL,
        `contract_signature_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`contract_signature_id`),
        KEY `contract_signature_contract_id` (`contract_signature_contract_id`),
        CONSTRAINT `contract_signatures_ibfk_1` FOREIGN KEY (`contract_signature_contract_id`) REFERENCES `contracts` (`contract_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // A signature already on a contract becomes a round of its own, recorded rather than signed here
    if ($itflow_column('contracts', 'contract_contact_signature')) {
        $itflow_rows = mysqli_query($mysqli, "SELECT * FROM contracts
            WHERE TRIM(IFNULL(contract_contact_signature, '')) <> '' OR TRIM(IFNULL(contract_agent_signature, '')) <> ''
                OR contract_contact_signature_date IS NOT NULL OR contract_agent_signature_date IS NOT NULL");
        while ($itflow_row = mysqli_fetch_assoc($itflow_rows)) {
            $itflow_sql = ['contract_signature_contract_id' => intval($itflow_row['contract_id'])];
            $itflow_document = (string) $itflow_row['contract_details'];
            $itflow_sql['contract_signature_document'] = $itflow_document;
            $itflow_sql['contract_signature_document_hash'] = hash('sha256', $itflow_document);
            foreach (['client' => 'contact', 'agent' => 'agent'] as $itflow_side => $itflow_old) {
                $itflow_mark = trim((string) $itflow_row["contract_{$itflow_old}_signature"]);
                $itflow_date = $itflow_row["contract_{$itflow_old}_signature_date"];
                $itflow_name = trim((string) $itflow_row["contract_{$itflow_old}_name"]);
                if ($itflow_mark === '' && empty($itflow_date)) {
                    continue;
                }
                // A drawn signature is kept as its picture; anything else was a typed one, kept as the name
                if (preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $itflow_mark)) {
                    $itflow_sql["contract_signature_{$itflow_side}_image"] = $itflow_mark;
                } elseif ($itflow_name === '') {
                    $itflow_name = mb_substr(strip_tags($itflow_mark), 0, 200);
                }
                $itflow_sql["contract_signature_{$itflow_side}_name"] = $itflow_name !== '' ? mb_substr($itflow_name, 0, 200) : 'Signed';
                $itflow_sql["contract_signature_{$itflow_side}_signed_at"] = !empty($itflow_date) ? $itflow_date : (string) $itflow_row['contract_created_at'];
                if ($itflow_side === 'client') {
                    $itflow_sql['contract_signature_client_via'] = 'recorded';
                }
            }
            $itflow_set = [];
            foreach ($itflow_sql as $itflow_key => $itflow_value) {
                $itflow_set[] = "`$itflow_key` = " . (is_int($itflow_value) ? $itflow_value : "'" . mysqli_real_escape_string($mysqli, (string) $itflow_value) . "'");
            }
            mysqli_query($mysqli, "INSERT INTO contract_signatures SET " . implode(', ', $itflow_set));
        }
    }

    // The contract's own snapshot and signature columns - nothing wrote them; signing is contract_signatures now
    foreach (['client_name', 'client_address', 'client_email', 'client_phone', 'contact_name', 'contact_signature', 'contact_signature_date',
        'agent_name', 'agent_signature', 'agent_signature_date'] as $itflow_old) {
        if ($itflow_column('contracts', "contract_$itflow_old")) {
            mysqli_query($mysqli, "ALTER TABLE `contracts` DROP COLUMN `contract_$itflow_old`");
        }
    }

    unset($itflow_column, $itflow_rows, $itflow_row, $itflow_sql, $itflow_document, $itflow_side, $itflow_old, $itflow_mark, $itflow_date, $itflow_name,
        $itflow_set, $itflow_key, $itflow_value);
