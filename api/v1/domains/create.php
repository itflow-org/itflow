<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

require_once 'domain_model.php';

$insert_id = false;

if (!empty($name) && !empty($client_id)) {
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

    $insert_sql = mysqli_query($mysqli, "INSERT INTO domains SET domain_name = '$name', domain_description = '$description', domain_expire = $expire, domain_ip = '$ip', domain_name_servers = '$name_servers', domain_mail_servers = '$mail_servers', domain_txt = '$txt', domain_raw_whois = '$raw_whois', domain_notes = '$notes', domain_registrar = $registrar, domain_webhost = $webhost, domain_dnshost = $dnshost, domain_mailhost = $mailhost, domain_client_id = $client_id");

    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        logAudit("Domain", "Create", "$name via API ($api_key_name)", $client_id, $insert_id);
        logAudit("API", "Success", "Created domain $name via API ($api_key_name)", $client_id);
    }
}

require_once '../create_output.php';