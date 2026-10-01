<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$domain_id = intval($_POST['domain_id']);
$update_count = false;

if (!empty($domain_id)) {
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT domain_name FROM domains WHERE domain_id = $domain_id AND domain_client_id = $client_id AND domain_archived_at IS NULL LIMIT 1"));

    if ($row) {
        $domain_name = escapeSql($row['domain_name']);
        $update_sql = mysqli_query($mysqli, "UPDATE domains SET domain_archived_at = NOW() WHERE domain_id = $domain_id AND domain_client_id = $client_id AND domain_archived_at IS NULL");

        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);
            logAudit("Domain", "Archive", "$domain_name archived via API ($api_key_name)", $client_id, $domain_id);
        }
    }
}

require_once '../update_output.php';