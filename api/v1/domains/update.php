<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$domain_id = intval($_POST['domain_id']);
$update_count = false;

if (!empty($domain_id)) {
    $domain_row = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT
            domains.*,
            registrar.vendor_name AS registrar_name,
            dnshost.vendor_name AS dnshost_name,
            mailhost.vendor_name AS mailhost_name,
            webhost.vendor_name AS webhost_name
        FROM domains
        LEFT JOIN vendors AS registrar ON domains.domain_registrar = registrar.vendor_id
        LEFT JOIN vendors AS dnshost ON domains.domain_dnshost = dnshost.vendor_id
        LEFT JOIN vendors AS mailhost ON domains.domain_mailhost = mailhost.vendor_id
        LEFT JOIN vendors AS webhost ON domains.domain_webhost = webhost.vendor_id
        WHERE domain_id = $domain_id AND domain_client_id = $client_id AND domain_archived_at IS NULL
        LIMIT 1
    "));

    require_once 'domain_model.php';

    $expire_sql = $expire !== '' ? "'$expire'" : 'NULL';
    $duplicate = $domain_row && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM domains WHERE domain_name = '$name' AND domain_client_id = $client_id AND domain_id <> $domain_id LIMIT 1"));

    if ($domain_row && $name !== '' && !$duplicate) {
        $before = domainHistorySnapshot($domain_id);

        $update_sql = mysqli_query($mysqli, "UPDATE domains SET domain_name = '$name', domain_description = '$description', domain_expire = $expire_sql, domain_auto_renew = $auto_renew, domain_notes = '$notes', domain_registrar = $registrar, domain_webhost = $webhost, domain_dnshost = $dnshost, domain_mailhost = $mailhost WHERE domain_id = $domain_id AND domain_client_id = $client_id AND domain_archived_at IS NULL LIMIT 1");

        if ($update_sql) {
            // Capture the update result before any history or audit queries run.
            $update_count = mysqli_affected_rows($mysqli);

            // DNS and registration come from the domain itself; a lookup that finds nothing keeps what was there
            domainApplyLookup($domain_id, domainLookup($name));
            domainWriteHistory($domain_id, $before, domainHistorySnapshot($domain_id));

            logAudit("Domain", "Edit", "$name via API ($api_key_name)", $client_id, $domain_id);
            logAudit("API", "Success", "Updated domain $name via API ($api_key_name)", $client_id);
        }
    }
}

require_once '../update_output.php';