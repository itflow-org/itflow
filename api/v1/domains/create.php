<?php

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

require_once 'domain_model.php';

$insert_id = false;

if (!empty($name) && !empty($client_id) && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM domains WHERE domain_name = '$name' AND domain_client_id = $client_id LIMIT 1"))) {
    $expire_sql = $expire !== '' ? "'$expire'" : 'NULL';

    $insert_sql = mysqli_query($mysqli, "INSERT INTO domains SET domain_name = '$name', domain_description = '$description', domain_expire = $expire_sql, domain_auto_renew = $auto_renew, domain_notes = '$notes', domain_registrar = $registrar, domain_webhost = $webhost, domain_dnshost = $dnshost, domain_mailhost = $mailhost, domain_client_id = $client_id");

    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);

        // DNS, registration and the expiry come from the domain itself rather than the request
        domainApplyLookup($insert_id, domainLookup($name));

        logAudit("Domain", "Create", "$name via API ($api_key_name)", $client_id, $insert_id);
        logAudit("API", "Success", "Created domain $name via API ($api_key_name)", $client_id);
    }
}

require_once '../create_output.php';