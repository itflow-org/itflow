<?php

/*
 * ITFlow - GET/POST request handler for client domains
 *
 * Every handler checks the support module and the domain's own client; the
 * registrar and hosts a form names must be the client's vendors or ours.
 * Lookups (DNS, registration data, expiry) go through domainLookup() in
 * functions/domain.php, and a lookup that finds nothing changes nothing -
 * a network blip must not wipe a domain's records or its expiry date.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The domain a request names, after the module and client checks - or back with an error if it is gone. */
function domainForRequest($domain_id, $level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = domainRow($domain_id);
    if (!$row) {
        flashAlert('That domain no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['domain_client_id']);
    enforceClientAccess();
    return $row;
}

/** The domains a bulk action names that this agent may act on - [id => row]. */
function domainsForBulk($level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['domain_ids'] ?? []))) as $domain_id) {
        $row = domainRow($domain_id);
        if (!$row) {
            continue;
        }
        $client_id = intval($row['domain_client_id']);
        enforceClientAccess();
        $rows[$domain_id] = $row;
    }
    return $rows;
}

/** Look a domain up and save what came back, with its history. True when something answered. */
function domainRefresh($row) {
    $domain_id = intval($row['domain_id']);
    $lookup = domainLookup($row['domain_name']);
    if (!$lookup) {
        return false;
    }
    $before = domainHistorySnapshot($domain_id);
    domainApplyLookup($domain_id, $lookup);
    domainWriteHistory($domain_id, $before, domainHistorySnapshot($domain_id));
    return true;
}

if (isset($_POST['add_domain'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id'] ?? 0);

    enforceClientAccess();

    if (!$client_id || !getFieldById('clients', $client_id, 'client_name')) {
        flashAlert('Pick the client this domain is for.', 'error');
        redirect();
    }

    // Several at once - a pasted list, one per line (or separated by spaces or commas)
    $names_input = trim((string) ($_POST['names'] ?? ''));
    if ($names_input !== '') {

        set_time_limit(0);

        $added = [];
        $skipped_invalid = $skipped_existing = 0;
        foreach (array_unique(array_filter(preg_split('/[\s,;]+/', $names_input), 'strlen')) as $input) {
            $post = $_POST;
            $post['name'] = $input;
            $post['expire'] = '';
            $form = domainFormSet($post, $client_id, 0, $error);
            if ($form === null) {
                if (domainNormalizeName($input) === '') {
                    $skipped_invalid++;
                } else {
                    $skipped_existing++;
                }
                continue;
            }
            if (isset($added[$form['name']])) {
                continue;
            }
            mysqli_query($mysqli, "INSERT INTO domains SET {$form['set']}, domain_client_id = $client_id");
            $domain_id = mysqli_insert_id($mysqli);
            domainSetTags($domain_id, $_POST['tags'] ?? []);
            $added[$form['name']] = $domain_id;
            logAudit("Domain", "Create", "$session_name created domain " . escapeSql($form['name']), $client_id, $domain_id);
        }

        // Look up what there is time for now; the refresher takes the rest first on its next run
        $looked_up = 0;
        $started = time();
        foreach ($added as $name => $domain_id) {
            if (time() - $started > 20) {
                break;
            }
            if (domainApplyLookup($domain_id, domainLookup($name))) {
                $looked_up++;
            }
        }

        $count = count($added);
        $message = "<strong>$count</strong> domain" . ($count === 1 ? '' : 's') . " added";
        if ($count) {
            $message .= $looked_up === $count ? ' and looked up' : ", <strong>$looked_up</strong> looked up now - the rest are checked on the refresher's next run";
        }
        if ($skipped_existing) {
            $message .= ", <strong>$skipped_existing</strong> already on this client";
        }
        if ($skipped_invalid) {
            $message .= ", <strong>$skipped_invalid</strong> not domain names";
        }
        logAudit("Domain", "Bulk Create", "$session_name added $count domain(s)", $client_id);
        flashAlert($message, $count ? 'success' : 'error');
        redirect();
    }

    $form = domainFormSet($_POST, $client_id, 0, $error);
    if ($form === null) {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO domains SET {$form['set']}, domain_client_id = $client_id");
    $domain_id = mysqli_insert_id($mysqli);

    domainSetTags($domain_id, $_POST['tags'] ?? []);

    // Its records, registration and expiry - and the certificate on its website
    $found = domainApplyLookup($domain_id, domainLookup($form['name']));
    $with_certificate = $found && domainAddCertificate($domain_id, $form['name'], $client_id);

    $name = $form['name'];
    logAudit("Domain", "Create", "$session_name created domain " . escapeSql($name) . ($with_certificate ? ', with its SSL certificate' : ''), $client_id, $domain_id);

    flashAlert("Domain <strong>$name</strong> created" . ($found ? ($with_certificate ? ', with its SSL certificate' : '') : ' - nothing answered for it in DNS, so check the name'), $found ? 'success' : 'warning');

    redirect();

}

if (isset($_POST['edit_domain'])) {

    validateCSRFToken();

    $row = domainForRequest($_POST['domain_id'] ?? 0, 2);
    $domain_id = intval($row['domain_id']);

    $form = domainFormSet($_POST, $client_id, $domain_id, $error);
    if ($form === null) {
        flashAlert($error, 'error');
        redirect();
    }

    $before = domainHistorySnapshot($domain_id);

    mysqli_query($mysqli, "UPDATE domains SET {$form['set']} WHERE domain_id = $domain_id");

    // Only a form that showed the tags changes them
    if (isset($_POST['tags_form'])) {
        domainSetTags($domain_id, $_POST['tags'] ?? []);
    }

    // A new name is a new domain to look up; otherwise saving stays quick
    if ($form['name'] !== $row['domain_name']) {
        domainApplyLookup($domain_id, domainLookup($form['name']));
    }

    domainWriteHistory($domain_id, $before, domainHistorySnapshot($domain_id));

    $name = $form['name'];
    logAudit("Domain", "Edit", "$session_name edited domain " . escapeSql($name), $client_id, $domain_id);

    flashAlert("Domain <strong>$name</strong> updated");

    redirect();

}

if (isset($_GET['refresh_domain'])) {

    validateCSRFToken();

    $row = domainForRequest($_GET['refresh_domain'], 2);
    $domain_id = intval($row['domain_id']);
    $name = $row['domain_name'];

    if (!domainRefresh($row)) {
        flashAlert("Nothing answered for <strong>$name</strong> in DNS - nothing was changed. Check the name, or whether it has lapsed.", 'error');
        redirect();
    }

    logAudit("Domain", "Refresh", "$session_name looked up domain " . escapeSql($name), $client_id, $domain_id);

    flashAlert("Looked up <strong>$name</strong>");

    redirect();

}

if (isset($_GET['favorite_domain']) || isset($_GET['unfavorite_domain'])) {

    validateCSRFToken();

    $favorite = isset($_GET['favorite_domain']) ? 1 : 0;
    $row = domainForRequest($favorite ? $_GET['favorite_domain'] : $_GET['unfavorite_domain'], 2);
    $domain_id = intval($row['domain_id']);

    mysqli_query($mysqli, "UPDATE domains SET domain_favorite = $favorite WHERE domain_id = $domain_id");

    logAudit("Domain", "Edit", "$session_name " . ($favorite ? 'starred' : 'unstarred') . " domain " . escapeSql($row['domain_name']), $client_id, $domain_id);

    redirect();

}

if (isset($_GET['archive_domain'])) {

    validateCSRFToken();

    $row = domainForRequest($_GET['archive_domain'], 2);
    $domain_id = intval($row['domain_id']);

    mysqli_query($mysqli, "UPDATE domains SET domain_archived_at = NOW() WHERE domain_id = $domain_id");

    logAudit("Domain", "Archive", "$session_name archived domain " . escapeSql($row['domain_name']), $client_id, $domain_id);

    flashAlert("Domain <strong>{$row['domain_name']}</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_domain'])) {

    validateCSRFToken();

    $row = domainForRequest($_GET['restore_domain'], 2);
    $domain_id = intval($row['domain_id']);

    mysqli_query($mysqli, "UPDATE domains SET domain_archived_at = NULL WHERE domain_id = $domain_id");

    logAudit("Domain", "Restore", "$session_name restored domain " . escapeSql($row['domain_name']), $client_id, $domain_id);

    flashAlert("Domain <strong>{$row['domain_name']}</strong> restored");

    redirect();

}

if (isset($_GET['delete_domain'])) {

    validateCSRFToken();

    $row = domainForRequest($_GET['delete_domain'], 3);

    domainDeleteForGood($row['domain_id']);

    logAudit("Domain", "Delete", "$session_name deleted domain " . escapeSql($row['domain_name']), $client_id);

    flashAlert("Domain <strong>{$row['domain_name']}</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_favorite_domains']) || isset($_POST['bulk_unfavorite_domains'])) {

    validateCSRFToken();

    $favorite = isset($_POST['bulk_favorite_domains']) ? 1 : 0;
    $count = 0;
    foreach (domainsForBulk(2) as $domain_id => $row) {
        mysqli_query($mysqli, "UPDATE domains SET domain_favorite = $favorite WHERE domain_id = $domain_id");
        logAudit("Domain", "Edit", "$session_name " . ($favorite ? 'starred' : 'unstarred') . " domain " . escapeSql($row['domain_name']), intval($row['domain_client_id']), $domain_id);
        $count++;
    }

    flashAlert(($favorite ? 'Starred' : 'Unstarred') . " <strong>$count</strong> domain" . ($count === 1 ? '' : 's'));

    redirect();

}

// Who registers or hosts them - one vendor into one role for several domains at once
if (isset($_POST['bulk_set_domain_vendor'])) {

    validateCSRFToken();

    $role = $_POST['role'] ?? '';
    if (!isset(DOMAIN_ROLES[$role])) {
        flashAlert('Pick what the vendor does for them.', 'error');
        redirect();
    }
    $column = DOMAIN_ROLES[$role]['column'];
    $vendor_input = intval($_POST['vendor_id'] ?? 0);

    $count = $skipped = 0;
    foreach (domainsForBulk(2) as $domain_id => $row) {
        $vendor_id = domainVendorId($vendor_input, $row['domain_client_id']);
        if ($vendor_input && !$vendor_id) {
            // That vendor is another client's
            $skipped++;
            continue;
        }
        $before = domainHistorySnapshot($domain_id);
        mysqli_query($mysqli, "UPDATE domains SET $column = $vendor_id WHERE domain_id = $domain_id");
        domainWriteHistory($domain_id, $before, domainHistorySnapshot($domain_id));
        logAudit("Domain", "Edit", "$session_name set the " . strtolower(DOMAIN_ROLES[$role]['label']) . " vendor on domain " . escapeSql($row['domain_name']), intval($row['domain_client_id']), $domain_id);
        $count++;
    }

    flashAlert("Set the " . strtolower(DOMAIN_ROLES[$role]['label']) . ($role === 'registrar' ? '' : ' host') . " on <strong>$count</strong> domain" . ($count === 1 ? '' : 's') . ($skipped ? ", <strong>$skipped</strong> skipped - that vendor is another client's" : ''));

    redirect();

}

if (isset($_POST['bulk_refresh_domains'])) {

    validateCSRFToken();

    // Lookups are slow - don't time out mid-batch
    set_time_limit(0);

    $count = $found = 0;
    foreach (domainsForBulk(2) as $domain_id => $row) {
        $count++;
        if (domainRefresh($row)) {
            $found++;
            logAudit("Domain", "Refresh", "$session_name looked up domain " . escapeSql($row['domain_name']), intval($row['domain_client_id']), $domain_id);
        }
        // Be gentle on the registries
        usleep(500000);
    }

    $missed = $count - $found;
    flashAlert("Looked up <strong>$found</strong> domain" . ($found === 1 ? '' : 's') . ($missed ? ", nothing answered for <strong>$missed</strong> - left as they were" : ''), $missed ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['bulk_archive_domains'])) {

    validateCSRFToken();

    $count = 0;
    foreach (domainsForBulk(2) as $domain_id => $row) {
        mysqli_query($mysqli, "UPDATE domains SET domain_archived_at = NOW() WHERE domain_id = $domain_id");
        logAudit("Domain", "Archive", "$session_name archived domain " . escapeSql($row['domain_name']), intval($row['domain_client_id']), $domain_id);
        $count++;
    }

    flashAlert("Archived <strong>$count</strong> domain" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isset($_POST['bulk_restore_domains'])) {

    validateCSRFToken();

    $count = 0;
    foreach (domainsForBulk(2) as $domain_id => $row) {
        mysqli_query($mysqli, "UPDATE domains SET domain_archived_at = NULL WHERE domain_id = $domain_id");
        logAudit("Domain", "Restore", "$session_name restored domain " . escapeSql($row['domain_name']), intval($row['domain_client_id']), $domain_id);
        $count++;
    }

    flashAlert("Restored <strong>$count</strong> domain" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_delete_domains'])) {

    validateCSRFToken();

    $count = 0;
    foreach (domainsForBulk(3) as $domain_id => $row) {
        domainDeleteForGood($domain_id);
        logAudit("Domain", "Delete", "$session_name deleted domain " . escapeSql($row['domain_name']), intval($row['domain_client_id']));
        $count++;
    }

    flashAlert("Deleted <strong>$count</strong> domain" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isExportRequest('export_domains')) {

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
    $expire_query = '';
    if (!empty($_POST['expire_days'])) {
        if ($_POST['expire_days'] == "expired") {
            $expire_query = "AND (domain_expire IS NOT NULL AND domain_expire < CURDATE())";
            $filter_summary['Expiry'] = 'Expired';
        } else {
            $expire_days = intval($_POST['expire_days']);
            $expire_query = "AND (domain_expire IS NOT NULL AND domain_expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $expire_days DAY))";
            $filter_summary['Expiry'] = "Expiring within $expire_days days";
        }
    }

    // Tags Filter - a domain with any of them
    $tag_query = '';
    if (isset($_POST['tags']) && is_array($_POST['tags']) && !empty($_POST['tags'])) {
        $tag_filter = implode(",", array_map('intval', $_POST['tags']));
        $tag_query = "AND domains.domain_id IN (SELECT domain_id FROM domain_tags WHERE tag_id IN ($tag_filter))";
    }

    // Vendor Filter - registrar or any host
    $vendor_query = '';
    if (!empty($_POST['vendor'])) {
        $vendor_filter = intval($_POST['vendor']);
        $vendor_query = "AND $vendor_filter IN (domain_registrar, domain_dnshost, domain_webhost, domain_mailhost)";
        $filter_summary['Vendor'] = getFieldById('vendors', $vendor_filter, 'vendor_name');
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    [$domain_columns, $domain_joins] = domainSelectSql();
    $sql = mysqli_query(
        $mysqli,
        "SELECT $domain_columns,
            registrar.vendor_name AS domain_registrar_name, webhost.vendor_name AS domain_webhost_name,
            dnshost.vendor_name AS domain_dnshost_name, mailhost.vendor_name AS domain_mailhost_name,
            IF(domain_auto_renew = 1, 'Yes', 'No') AS domain_auto_renew_label,
            (SELECT GROUP_CONCAT(tag_name ORDER BY tag_name SEPARATOR ', ') FROM domain_tags dt JOIN tags t ON t.tag_id = dt.tag_id WHERE dt.domain_id = domains.domain_id) AS domain_tags
        FROM domains $domain_joins
        WHERE (domains.domain_name LIKE '%$q%' OR domains.domain_description LIKE '%$q%' OR domains.domain_notes LIKE '%$q%'
            OR registrar.vendor_name LIKE '%$q%' OR dnshost.vendor_name LIKE '%$q%' OR webhost.vendor_name LIKE '%$q%' OR mailhost.vendor_name LIKE '%$q%' OR client_name LIKE '%$q%'
            OR domains.domain_id IN (SELECT domain_id FROM domain_tags JOIN tags USING (tag_id) WHERE tag_name LIKE '%$q%'))
        AND $archive_query
        " . clientScopeSql('domain_client_id') . "
        $client_query
        $expire_query
        $tag_query
        $vendor_query
        ORDER BY client_name ASC, domains.domain_name ASC"
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
