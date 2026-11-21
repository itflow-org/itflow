<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

// Parse ID
$domain_id = intval($_POST['domain_id'] ?? 0);

// Default
$delete_count = false;

if (!empty($domain_id)) {

    // Archived first, as in the app
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT domain_name FROM domains WHERE domain_id = $domain_id AND domain_client_id = $client_id AND domain_archived_at IS NOT NULL LIMIT 1"));

    if ($row) {

        // Off the services it was on; its certificates stay, on no domain (functions/domain.php)
        domainDeleteForGood($domain_id);

        if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM domains WHERE domain_id = $domain_id"))) {
            $delete_count = 1;
            $domain_name = escapeSql($row['domain_name']);
            logAudit("Domain", "Delete", "$domain_name via API ($api_key_name)", $client_id, $domain_id);
            logAudit("API", "Success", "Deleted domain $domain_name via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../delete_output.php';
