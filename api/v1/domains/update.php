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

    $original_domain_info = $domain_row;

    require_once 'domain_model.php';

    // Use the supplied expiry when valid, otherwise look it up from WHOIS.
    if (strtotime($expire)) {
        $expire = "'$expire'";
    } else {
        $expire = getDomainExpirationDate($name);
        $expire = strtotime($expire) ? "'$expire'" : 'NULL';
    }

    // Populate DNS and WHOIS data from the domain rather than accepting it from the API.
    $records = getDnsRecords($name);
    $ip = escapeSql($records['a']);
    $name_servers = escapeSql($records['ns']);
    $mail_servers = escapeSql($records['mx']);
    $txt = escapeSql($records['txt']);
    $raw_whois = escapeSql($records['whois']);

    $update_sql = mysqli_query($mysqli, "UPDATE domains SET domain_name = '$name', domain_description = '$description', domain_expire = $expire, domain_ip = '$ip', domain_name_servers = '$name_servers', domain_mail_servers = '$mail_servers', domain_txt = '$txt', domain_raw_whois = '$raw_whois', domain_notes = '$notes', domain_registrar = $registrar, domain_webhost = $webhost, domain_dnshost = $dnshost, domain_mailhost = $mailhost WHERE domain_id = $domain_id AND domain_client_id = $client_id AND domain_archived_at IS NULL LIMIT 1");

    if ($update_sql && $domain_row) {
        // Capture the update result before any history or audit queries run.
        $update_count = mysqli_affected_rows($mysqli);

        $new_domain_info = mysqli_fetch_assoc(mysqli_query($mysqli, "
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
            WHERE domain_id = $domain_id AND domain_client_id = $client_id
            LIMIT 1
        "));

        $ignored_columns = [
            'domain_updated_at',
            'domain_accessed_at',
            'domain_registrar',
            'domain_webhost',
            'domain_dnshost',
            'domain_mailhost'
        ];

        foreach ($original_domain_info as $column => $old_value) {
            $new_value = $new_domain_info[$column];
            if ($old_value != $new_value && !in_array($column, $ignored_columns)) {
                $column = escapeSql($column);
                $old_value = escapeSql($old_value);
                $new_value = escapeSql($new_value);
                mysqli_query($mysqli, "INSERT INTO domain_history SET domain_history_column = '$column', domain_history_old_value = '$old_value', domain_history_new_value = '$new_value', domain_history_domain_id = $domain_id");
            }
        }

        logAudit("Domain", "Edit", "$name via API ($api_key_name)", $client_id, $domain_id);
        logAudit("API", "Success", "Updated domain $name via API ($api_key_name)", $client_id);
    }
}

require_once '../update_output.php';