<?php

/*
 * ITFlow - GET/POST request handler for client domains
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_domain'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    require_once 'domain_model.php';
    $extended_log_description = '';
    $client_id = intval($_POST['client_id']);

    enforceClientAccess();

    // Set/check/lookup expiry date
    if (strtotime($expire)) {
        $expire = "'" . $expire . "'";
    }
    else {
        $expire = getDomainExpirationDate($name);
        if (strtotime($expire)) {
            $expire = "'" . $expire . "'";
        } else {
            $expire = 'NULL';
        }
    }

    // NS, MX, A and WHOIS records/data
    $records = getDnsRecords($name);
    $a = escapeSql($records['a']);
    $ns = escapeSql($records['ns']);
    $mx = escapeSql($records['mx']);
    $txt = escapeSql($records['txt']);
    $whois = escapeSql($records['whois']);

    // Add domain record
    mysqli_query($mysqli,"INSERT INTO domains SET domain_name = '$name', domain_description = '$description', domain_registrar = $registrar,  domain_webhost = $webhost, domain_dnshost = $dnshost, domain_mailhost = $mailhost, domain_expire = $expire, domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_txt = '$txt', domain_raw_whois = '$whois', domain_notes = '$notes', domain_client_id = $client_id");

    // Get inserted ID (for linking certificate, if exists)
    $domain_id = mysqli_insert_id($mysqli);

    // Get SSL cert for domain (if exists)
    $certificate = getSslCertificate($name);
    if ($certificate['success'] == "TRUE") {
        $expire = escapeSql($certificate['expire']);
        $issued_by = escapeSql($certificate['issued_by']);
        $public_key = escapeSql($certificate['public_key']);

        mysqli_query($mysqli,"INSERT INTO certificates SET certificate_name = '$name', certificate_domain = '$name', certificate_issued_by = '$issued_by', certificate_expire = '$expire', certificate_public_key = '$public_key', certificate_domain_id = $domain_id, certificate_client_id = $client_id");
        $extended_log_description = ', with associated SSL cert';
    }

    logAudit("Domain", "Create", "$session_name created domain $name$extended_log_description", $client_id, $domain_id);

    flashAlert("Domain <strong>$name</strong> created");

    redirect();

}

if (isset($_POST['edit_domain'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    require_once 'domain_model.php';

    $domain_id = intval($_POST['domain_id']);

    $client_id = intval(getFieldById('domains', $domain_id, 'domain_client_id'));

    enforceClientAccess();

    // Set/check/lookup expiry date
    if (strtotime($expire) && (new DateTime($expire)) > (new DateTime())) {
        $expire = "'" . $expire . "'";

    } else {
        $expire = getDomainExpirationDate($name);
        if (strtotime($expire)) {
            $expire = "'" . $expire . "'";
        } else {
            $expire = 'NULL';
        }
    }

    $client_id = intval($_POST['client_id']);

    // Update NS, MX, A and WHOIS records/data
    $records = getDnsRecords($name);
    $a = escapeSql($records['a']);
    $ns = escapeSql($records['ns']);
    $mx = escapeSql($records['mx']);
    $txt = escapeSql($records['txt']);
    $whois = escapeSql($records['whois']);

    // Current domain info
    $original_domain_info = mysqli_fetch_assoc(mysqli_query($mysqli,"
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
        WHERE domain_id = $domain_id
    "));

    // Update domain
    mysqli_query($mysqli,"UPDATE domains SET domain_name = '$name', domain_description = '$description', domain_registrar = $registrar,  domain_webhost = $webhost, domain_dnshost = $dnshost, domain_mailhost = $mailhost, domain_expire = $expire, domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_txt = '$txt', domain_raw_whois = '$whois', domain_notes = '$notes' WHERE domain_id = $domain_id");

    // Fetch updated info
    $new_domain_info = mysqli_fetch_assoc(mysqli_query($mysqli,"
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
        WHERE domain_id = $domain_id
    "));

    // Compare/log changes
    $ignored_columns = ["domain_updated_at", "domain_accessed_at", "domain_registrar", "domain_webhost", "domain_dnshost", "domain_mailhost"];
    foreach ($original_domain_info as $column => $old_value) {
        $new_value = $new_domain_info[$column];
        if ($old_value != $new_value && !in_array($column, $ignored_columns)) {
            $column = escapeSql($column);
            $old_value = escapeSql($old_value);
            $new_value = escapeSql($new_value);
            mysqli_query($mysqli,"INSERT INTO domain_history SET domain_history_column = '$column', domain_history_old_value = '$old_value', domain_history_new_value = '$new_value', domain_history_domain_id = $domain_id");
        }
    }

    logAudit("Domain", "Edit", "$session_name edited domain $name", $client_id, $domain_id);

    flashAlert("Domain <strong>$name</strong> edited");

    redirect();

}

if (isset($_GET['refresh_domain'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $domain_id = intval($_GET['refresh_domain']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id");
    $row = mysqli_fetch_assoc($sql);
    $domain_name = escapeSql($row['domain_name']);
    $client_id = intval($row['domain_client_id']);

    enforceClientAccess();

    // Lookup expiry date
    $expire = getDomainExpirationDate($domain_name);
    if (strtotime($expire)) {
        $expire = "'" . $expire . "'";
    } else {
        $expire = 'NULL';
    }

    // NS, MX, A and WHOIS records/data
    $records = getDnsRecords($domain_name);
    $a = escapeSql($records['a']);
    $ns = escapeSql($records['ns']);
    $mx = escapeSql($records['mx']);
    $txt = escapeSql($records['txt']);
    $whois = escapeSql($records['whois']);

    mysqli_query($mysqli,"UPDATE domains SET domain_expire = $expire, domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_txt = '$txt', domain_raw_whois = '$whois' WHERE domain_id = $domain_id");

    logAudit("Domain", "Refresh", "$session_name refreshed records for domain $domain_name", $client_id, $domain_id);

    flashAlert("Refreshed records for <strong>$domain_name</strong>");

    redirect();

}

if (isset($_GET['archive_domain'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $domain_id = intval($_GET['archive_domain']);

    //Get domain Name
    $sql = mysqli_query($mysqli,"SELECT * FROM domains WHERE domain_id = $domain_id");
    $row = mysqli_fetch_assoc($sql);
    $domain_name = escapeSql($row['domain_name']);
    $client_id = intval($row['domain_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE domains SET domain_archived_at = NOW() WHERE domain_id = $domain_id");

    logAudit("Domain", "Archive", "$session_name archived domain $domain_name", $client_id, $domain_id);

    flashAlert("Domain <strong>$domain_name archived", 'error');

    redirect();

}

if(isset($_GET['restore_domain'])){

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $domain_id = intval($_GET['restore_domain']);

    // Get Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id");
    $row = mysqli_fetch_assoc($sql);
    $domain_name = escapeSql($row['domain_name']);
    $client_id = intval($row['domain_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"UPDATE domains SET domain_archived_at = NULL WHERE domain_id = $domain_id");

    logAudit("Domain", "Restore", "$session_name restored domain $domain_name", $client_id, $domain_id);

    flashAlert("Domain <strong>$domain_name</strong> restored");

    redirect();

}

if (isset($_GET['delete_domain'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $domain_id = intval($_GET['delete_domain']);

    // Get Domain Name and Client ID for logging and alert message
    $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id");
    $row = mysqli_fetch_assoc($sql);
    $domain_name = escapeSql($row['domain_name']);
    $client_id = intval($row['domain_client_id']);

    enforceClientAccess();

    mysqli_query($mysqli,"DELETE FROM domains WHERE domain_id = $domain_id");

    logAudit("Domain", "Delete", "$session_name deleted domain $domain_name", $client_id);

    flashAlert("Domain <strong>$domain_name</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_archive_domains'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    if (isset($_POST['domain_ids'])) {

        // Get Selected Count
        $count = count($_POST['domain_ids']);

        // Cycle through array and archive each record
        foreach ($_POST['domain_ids'] as $domain_id) {

            $domain_id = intval($domain_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id");
            $row = mysqli_fetch_assoc($sql);
            $domain_name = escapeSql($row['domain_name']);
            $client_id = intval($row['domain_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE domains SET domain_archived_at = NOW() WHERE domain_id = $domain_id");

            logAudit("Domain", "Archive", "$session_name archived domain $domain_name", $client_id, $domain_id);
        }

        logAudit("Domain", "Bulk Archive", "$session_name archived $count domain(s)", $client_id);

        flashAlert("Archived <strong>$count</strong> domain(s)", 'error');

    }

    redirect();

}

if (isset($_POST['bulk_restore_domains'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    if (isset($_POST['domain_ids'])) {

        // Get Selected Count
        $count = count($_POST['domain_ids']);

        // Cycle through array and restore
        foreach ($_POST['domain_ids'] as $domain_id) {

            $domain_id = intval($domain_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id");
            $row = mysqli_fetch_assoc($sql);
            $domain_name = escapeSql($row['domain_name']);
            $client_id = intval($row['domain_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli,"UPDATE domains SET domain_archived_at = NULL WHERE domain_id = $domain_id");

            logAudit("Domain", "Restore", "$session_name restored domain $domain_name", $client_id, $domain_id);

        }

        logAudit("Domain", "Bulk Restore", "$session_name restored $count domain(s)", $client_id);

        flashAlert("Restored <strong>$count</strong> domain(s)");

    }

    redirect();

}

if (isset($_POST['bulk_delete_domains'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    if (isset($_POST['domain_ids'])) {

        // Get Selected Count
        $count = count($_POST['domain_ids']);

        // Cycle through array and delete each domain
        foreach ($_POST['domain_ids'] as $domain_id) {

            $domain_id = intval($domain_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id");
            $row = mysqli_fetch_assoc($sql);
            $domain_name = escapeSql($row['domain_name']);
            $client_id = intval($row['domain_client_id']);

            enforceClientAccess();

            mysqli_query($mysqli, "DELETE FROM domains WHERE domain_id = $domain_id AND domain_client_id = $client_id");

            logAudit("Domain", "Delete", "$session_name deleted domain $domain_name", $client_id);
        }

        logAudit("Domain", "Bulk Delete", "$session_name deleted $count domain(s)", $client_id);

        flashAlert("Deleted <strong>$count</strong> domain(s)", 'error');

    }

    redirect();

}

if (isset($_POST['bulk_refresh_domains'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    if (isset($_POST['domain_ids'])) {

        // WHOIS lookups are slow - don't time out mid-batch
        set_time_limit(0);

        // Get Selected Count
        $count = count($_POST['domain_ids']);

        // Cycle through array and refresh each record
        foreach ($_POST['domain_ids'] as $domain_id) {

            $domain_id = intval($domain_id);

            // Get Name and Client ID for logging and alert message
            $sql = mysqli_query($mysqli,"SELECT domain_name, domain_client_id FROM domains WHERE domain_id = $domain_id");
            $row = mysqli_fetch_assoc($sql);
            $domain_name = escapeSql($row['domain_name']);
            $client_id = intval($row['domain_client_id']);

            enforceClientAccess();

            // Lookup expiry date
            $expire = getDomainExpirationDate($domain_name);
            if (strtotime($expire)) {
                $expire = "'" . $expire . "'";
            } else {
                $expire = 'NULL';
            }

            // NS, MX, A and WHOIS records/data
            $records = getDnsRecords($domain_name);
            $a = escapeSql($records['a']);
            $ns = escapeSql($records['ns']);
            $mx = escapeSql($records['mx']);
            $txt = escapeSql($records['txt']);
            $whois = escapeSql($records['whois']);

            mysqli_query($mysqli,"UPDATE domains SET domain_expire = $expire, domain_ip = '$a', domain_name_servers = '$ns', domain_mail_servers = '$mx', domain_txt = '$txt', domain_raw_whois = '$whois' WHERE domain_id = $domain_id");

            logAudit("Domain", "Refresh", "$session_name refreshed records for domain $domain_name", $client_id, $domain_id);

            // Be gentle on WHOIS servers
            sleep(1);
        }

        logAudit("Domain", "Bulk Refresh", "$session_name refreshed records for $count domain(s)", $client_id);

        flashAlert("Refreshed records for <strong>$count</strong> domain(s)");

    }

    redirect();

}

if (isset($_POST['export_domains'])) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_domains']);

    // Filters inherited from the domains page - mirrors agent/domains.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND domain_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();

        $archive_query = $archived ? "domain_archived_at IS NOT NULL" : "domain_archived_at IS NULL";
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";

        // Client Filter
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND (domain_client_id = $filter_client_id)";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }

        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR domain_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND domain_archived_at IS NULL)";
    }

    // Expiring In Filter
    if (!empty($_POST['expire_days'])) {
        if ($_POST['expire_days'] == "expired") {
            $expire_query = "AND (domain_expire IS NOT NULL AND domain_expire != '0000-00-00' AND domain_expire < CURDATE())";
            $filter_summary['Expiry'] = 'Expired';
        } else {
            $expire_days = intval($_POST['expire_days']);
            $expire_query = "AND (domain_expire IS NOT NULL AND domain_expire != '0000-00-00' AND domain_expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $expire_days DAY))";
            $filter_summary['Expiry'] = "Expiring within $expire_days days";
        }
    } else {
        // Default - any
        $expire_query = '';
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT domains.*, clients.*,
        registrar.vendor_name AS domain_registrar_name,
        webhost.vendor_name AS domain_webhost_name
        FROM domains
        LEFT JOIN clients ON client_id = domain_client_id
        LEFT JOIN vendors AS registrar ON domains.domain_registrar = registrar.vendor_id
        LEFT JOIN vendors AS webhost ON domains.domain_webhost = webhost.vendor_id
        WHERE (domains.domain_name LIKE '%$q%' OR domains.domain_description LIKE '%$q%' OR registrar.vendor_name LIKE '%$q%' OR webhost.vendor_name LIKE '%$q%' OR client_name LIKE '%$q%')
        AND $archive_query
        $access_permission_query
        $client_query
        $expire_query
        ORDER BY domains.domain_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('domains', $format, $file_name_prepend . 'Domains', 'Domains', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Domain", "Export", "$session_name exported $num_rows domain(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
