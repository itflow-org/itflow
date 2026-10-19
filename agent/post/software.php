<?php

/*
 * ITFlow - GET/POST request handler for client licenses (the software table)
 *
 * The license itself, its seats (the devices and users on it), its individual
 * keys and who each is handed to, renewals one at a time or in bulk, and the
 * export - and a license made from a license template (Admin > License
 * Templates), which it remembers. Every handler checks the license's own
 * client, and every device, user and vendor a form names must be that
 * client's - a form posting another client's asset id gets nothing. Links to documents, files and credentials,
 * and single seats from the asset and contact pages, go through post/link.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/**
 * The license a request names, after the client access check - or back to
 * the page with an error if it is gone.
 */
function softwareForRequest($software_id) {
    global $client_id;
    $row = softwareRow($software_id);
    if (!$row) {
        flashAlert('That license no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['software_client_id']);
    enforceClientAccess();
    return $row;
}

/** The license ids a bulk form posted, each checked for client access. Returns [id => row]. */
function softwareBulkRows($ids) {
    global $client_id;
    $rows = [];
    foreach (array_unique(array_map('intval', (array) $ids)) as $software_id) {
        $row = softwareRow($software_id);
        if (!$row) {
            continue;
        }
        $client_id = intval($row['software_client_id']);
        enforceClientAccess();
        $rows[$software_id] = $row;
    }
    return $rows;
}

if (isset($_POST['add_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id'] ?? 0);

    enforceClientAccess();

    if (trim((string) ($_POST['name'] ?? '')) === '') {
        flashAlert('A license needs a name.', 'error');
        redirect();
    }

    // A template's vendor the client hasn't got yet is made for them from its vendor template - by someone who can add vendors
    $vendor_made = false;
    if (preg_match('/^template:(\d+)$/', (string) ($_POST['vendor'] ?? ''), $match)) {
        $_POST['vendor'] = softwareVendorFromTemplate($match[1], $client_id, intval(lookupUserPermission('module_client')) >= 2, $vendor_made);
    }
    $set = softwareFormSet($_POST, $client_id);

    // Started from a template, it remembers which
    mysqli_query($mysqli, "INSERT INTO software SET $set, software_client_id = $client_id, software_template_id = " . softwareTemplateLiveId($_POST['software_template_id'] ?? 0));
    $software_id = mysqli_insert_id($mysqli);

    $changes = softwareSyncSeats($software_id, $client_id, $_POST['assets'] ?? [], $_POST['contacts'] ?? []);
    $keys_added = softwareAddKeys($software_id, $_POST['keys'] ?? '');
    tagSetForRecord('license', $software_id, (array) ($_POST['tags'] ?? []));

    $name = trim($_POST['name']);
    $vendor_name = $vendor_made ? (string) getFieldById('vendors', $_POST['vendor'], 'vendor_name') : '';
    if ($vendor_made) {
        logAudit("Vendor", "Create", "$session_name created vendor " . escapeSql($vendor_name) . " from a template for license " . escapeSql($name), $client_id, intval($_POST['vendor']));
    }
    logAudit("Software", "Create", "$session_name created license " . escapeSql($name . softwareSeatChangeText($changes)), $client_id, $software_id);

    $warning = softwareSeatWarning($software_id);
    flashAlert("License <strong>$name</strong> created" . ($keys_added ? " with $keys_added key" . ($keys_added === 1 ? '' : 's') : '') . ($vendor_made ? ", and vendor <strong>" . escapeHtml($vendor_name) . "</strong> added for the client" : '') . $warning, $warning ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['add_software_from_template'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    // From a license template - Admin's New license for a client: its details from the template, the seats, key and dates from the window
    $client_id = intval($_POST['client_id'] ?? 0);

    enforceClientAccess();

    if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"))) {
        flashAlert('Pick the client it is for.', 'error');
        redirect();
    }
    $template = softwareTemplateRow($_POST['software_template_id'] ?? 0);
    $software_id = softwareTemplateMakeLicense($_POST['software_template_id'] ?? 0, $client_id, $_POST, intval(lookupUserPermission('module_client')) >= 2, $vendor_made);
    if (!$software_id) {
        flashAlert('That license template is gone or archived.', 'error');
        redirect();
    }

    $vendor_id = intval(getFieldById('software', $software_id, 'software_vendor_id'));
    $vendor_name = $vendor_made ? (string) getFieldById('vendors', $vendor_id, 'vendor_name') : '';
    if ($vendor_made) {
        logAudit("Vendor", "Create", "$session_name created vendor " . escapeSql($vendor_name) . " from a template for license " . escapeSql($template['software_template_name']), $client_id, $vendor_id);
    }
    logAudit("Software", "Create", "$session_name created license " . escapeSql($template['software_template_name']) . " from template " . escapeSql($template['software_template_name']), $client_id, $software_id);

    flashAlert("License <strong>" . escapeHtml($template['software_template_name']) . "</strong> created from template <strong>" . escapeHtml($template['software_template_name']) . "</strong>" . ($vendor_made ? ", with vendor <strong>" . escapeHtml($vendor_name) . "</strong> added for the client" : '') . " - put its devices and users on it");

    redirect("/agent/software.php?client_id=$client_id");

}

if (isset($_POST['edit_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $row = softwareForRequest($_POST['software_id'] ?? 0);
    $software_id = intval($row['software_id']);

    $set = softwareFormSet($_POST, $client_id);
    if ($set === null) {
        flashAlert('A license needs a name.', 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE software SET $set WHERE software_id = $software_id");

    // Only a form that showed the seats changes them
    $changes = ['added' => [], 'removed' => []];
    if (isset($_POST['seats_form'])) {
        $changes = softwareSyncSeats($software_id, $client_id, $_POST['assets'] ?? [], $_POST['contacts'] ?? []);
    }
    // ...and the tags
    if (isset($_POST['tags_form'])) {
        tagSetForRecord('license', $software_id, (array) ($_POST['tags'] ?? []));
    }

    $name = trim($_POST['name']);
    logAudit("Software", "Edit", "$session_name edited license " . escapeSql($name . softwareSeatChangeText($changes)), $client_id, $software_id);

    $warning = softwareSeatWarning($software_id);
    flashAlert("License <strong>$name</strong> updated$warning", $warning ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['set_software_seats'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $row = softwareForRequest($_POST['software_id'] ?? 0);
    $software_id = intval($row['software_id']);

    $changes = softwareSyncSeats($software_id, $client_id, $_POST['assets'] ?? [], $_POST['contacts'] ?? []);

    if ($changes['added'] || $changes['removed']) {
        logAudit("Software", "Seats", "$session_name changed seats on license " . escapeSql($row['software_name'] . softwareSeatChangeText($changes)), $client_id, $software_id);
    }

    $warning = softwareSeatWarning($software_id);
    $summary = [];
    if ($changes['added']) {
        $summary[] = count($changes['added']) . ' added';
    }
    if ($changes['removed']) {
        $summary[] = count($changes['removed']) . ' removed';
    }
    flashAlert("Seats on <strong>{$row['software_name']}</strong> " . ($summary ? 'updated - ' . implode(', ', $summary) : 'unchanged') . $warning, $warning ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['renew_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $rows = softwareBulkRows($_POST['software_ids'] ?? []);
    $mode = ($_POST['renew_mode'] ?? '') === 'date' ? 'date' : 'extend';
    $term = (string) ($_POST['renew_term'] ?? '');
    $new_date = softwareFormDate($_POST['renew_date'] ?? '');
    $reference = trim((string) ($_POST['purchase_reference'] ?? ''));

    if (!$rows || ($mode === 'extend' && !isset(SOFTWARE_RENEWAL_TERMS[$term])) || ($mode === 'date' && !$new_date)) {
        flashAlert('Pick a term or a new renewal date.', 'error');
        redirect();
    }

    $renewed = [];
    foreach ($rows as $software_id => $row) {
        $client_id = intval($row['software_client_id']);
        $until = $mode === 'date' ? $new_date : softwareRenewalExtended($row['software_expire'], $term);
        $reference_sql = $reference !== '' ? ", software_purchase_reference = '" . escapeSql(mb_substr($reference, 0, 200)) . "'" : '';

        mysqli_query($mysqli, "UPDATE software SET software_expire = '$until' $reference_sql WHERE software_id = $software_id");

        $was = $row['software_expire'] ?: 'no date';
        logAudit("Software", "Renew", "$session_name renewed license " . escapeSql($row['software_name']) . " until $until (was $was)" . ($reference !== '' ? ', reference ' . escapeSql($reference) : ''), $client_id, $software_id);
        $renewed[] = [$row['software_name'], $until];
    }

    if (count($renewed) === 1) {
        flashAlert("License <strong>{$renewed[0][0]}</strong> renewed until <strong>{$renewed[0][1]}</strong>");
    } else {
        flashAlert("Renewed <strong>" . count($renewed) . "</strong> licenses");
    }

    redirect();

}

if (isset($_POST['add_software_keys'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $row = softwareForRequest($_POST['software_id'] ?? 0);
    $software_id = intval($row['software_id']);

    $added = softwareAddKeys($software_id, $_POST['keys'] ?? '');

    if ($added) {
        logAudit("Software", "Keys", "$session_name added $added key" . ($added === 1 ? '' : 's') . " to license " . escapeSql($row['software_name']), $client_id, $software_id);
        flashAlert("Added <strong>$added</strong> key" . ($added === 1 ? '' : 's') . " to <strong>{$row['software_name']}</strong>");
    } else {
        flashAlert("No new keys to add - blank, or <strong>{$row['software_name']}</strong> already has them.", 'warning');
    }

    redirect();

}

if (isset($_POST['edit_software_key'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $software_key_id = intval($_POST['software_key_id'] ?? 0);
    $key_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT software_key, software_key_software_id FROM software_keys WHERE software_key_id = $software_key_id"));
    if (!$key_row) {
        flashAlert('That key no longer exists.', 'error');
        redirect();
    }
    $row = softwareForRequest($key_row['software_key_software_id']);
    $software_id = intval($row['software_id']);

    $key = mb_substr(trim((string) ($_POST['key'] ?? '')), 0, 400);
    if ($key === '') {
        flashAlert('A key cannot be blank - delete it instead.', 'error');
        redirect();
    }
    mysqli_query($mysqli, "UPDATE software_keys SET software_key = '" . escapeSql($key) . "' WHERE software_key_id = $software_key_id");

    // Who it is handed to - only the client's own, and each one gets a seat if they had none
    $assets = softwareClientIds('asset', $_POST['assets'] ?? [], $client_id);
    $contacts = softwareClientIds('contact', $_POST['contacts'] ?? [], $client_id);
    mysqli_query($mysqli, "DELETE FROM software_key_asset_assignments WHERE software_key_id = $software_key_id");
    mysqli_query($mysqli, "DELETE FROM software_key_contact_assignments WHERE software_key_id = $software_key_id");
    $holders = [];
    foreach ($assets as $asset_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO software_key_asset_assignments SET software_key_id = $software_key_id, asset_id = $asset_id");
        mysqli_query($mysqli, "INSERT IGNORE INTO software_assets SET software_id = $software_id, asset_id = $asset_id");
        $holders[] = getFieldById('assets', $asset_id, 'asset_name');
    }
    foreach ($contacts as $contact_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO software_key_contact_assignments SET software_key_id = $software_key_id, contact_id = $contact_id");
        mysqli_query($mysqli, "INSERT IGNORE INTO software_contacts SET software_id = $software_id, contact_id = $contact_id");
        $holders[] = getFieldById('contacts', $contact_id, 'contact_name');
    }

    logAudit("Software", "Keys", "$session_name updated a key on license " . escapeSql($row['software_name']) . ($holders ? ', handed to ' . escapeSql(implode(', ', $holders)) : ', not handed to anyone'), $client_id, $software_id);

    $warning = softwareSeatWarning($software_id);
    flashAlert("Key on <strong>{$row['software_name']}</strong> saved" . ($holders ? ' - handed to <strong>' . implode(', ', $holders) . '</strong>' : '') . $warning, $warning ? 'warning' : 'success');

    redirect();

}

if (isset($_GET['delete_software_key'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $software_key_id = intval($_GET['delete_software_key']);
    $key_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT software_key_software_id FROM software_keys WHERE software_key_id = $software_key_id"));
    if (!$key_row) {
        flashAlert('That key no longer exists.', 'error');
        redirect();
    }
    $row = softwareForRequest($key_row['software_key_software_id']);

    mysqli_query($mysqli, "DELETE FROM software_keys WHERE software_key_id = $software_key_id");

    logAudit("Software", "Keys", "$session_name deleted a key from license " . escapeSql($row['software_name']), $client_id, intval($row['software_id']));

    flashAlert("Key deleted from <strong>{$row['software_name']}</strong>", 'error');

    redirect();

}

if (isset($_GET['archive_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $row = softwareForRequest($_GET['archive_software']);
    $software_id = intval($row['software_id']);

    // Seats stay put - an archived license drops off every device and user
    // anyway, and comes back with them if it is restored
    mysqli_query($mysqli, "UPDATE software SET software_archived_at = NOW() WHERE software_id = $software_id");

    logAudit("Software", "Archive", "$session_name archived license " . escapeSql($row['software_name']), $client_id, $software_id);

    flashAlert("License <strong>{$row['software_name']}</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $row = softwareForRequest($_GET['restore_software']);
    $software_id = intval($row['software_id']);

    mysqli_query($mysqli, "UPDATE software SET software_archived_at = NULL WHERE software_id = $software_id");

    logAudit("Software", "Restore", "$session_name restored license " . escapeSql($row['software_name']), $client_id, $software_id);

    flashAlert("License <strong>{$row['software_name']}</strong> restored");

    redirect();

}

if (isset($_GET['delete_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $row = softwareForRequest($_GET['delete_software']);
    $software_id = intval($row['software_id']);

    // Seats, keys and links go with it (foreign keys cascade)
    mysqli_query($mysqli, "DELETE FROM software WHERE software_id = $software_id");

    logAudit("Software", "Delete", "$session_name deleted license " . escapeSql($row['software_name']) . " with its seats and keys", $client_id);

    flashAlert("License <strong>{$row['software_name']}</strong> deleted", 'error');

    // From its own page, back to the client's licenses - that page is gone
    if (strpos($_SERVER['HTTP_REFERER'] ?? '', '/license.php') !== false) {
        redirect("software.php?client_id=$client_id&archived=1");
    }
    redirect();

}

if (isset($_POST['bulk_archive_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $rows = softwareBulkRows($_POST['software_ids'] ?? []);
    foreach ($rows as $software_id => $row) {
        $client_id = intval($row['software_client_id']);
        mysqli_query($mysqli, "UPDATE software SET software_archived_at = NOW() WHERE software_id = $software_id AND software_archived_at IS NULL");
        logAudit("Software", "Archive", "$session_name archived license " . escapeSql($row['software_name']), $client_id, $software_id);
    }

    flashAlert("Archived <strong>" . count($rows) . "</strong> license" . (count($rows) === 1 ? '' : 's'), 'error');

    redirect();

}

if (isset($_POST['bulk_restore_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $rows = softwareBulkRows($_POST['software_ids'] ?? []);
    foreach ($rows as $software_id => $row) {
        $client_id = intval($row['software_client_id']);
        mysqli_query($mysqli, "UPDATE software SET software_archived_at = NULL WHERE software_id = $software_id");
        logAudit("Software", "Restore", "$session_name restored license " . escapeSql($row['software_name']), $client_id, $software_id);
    }

    flashAlert("Restored <strong>" . count($rows) . "</strong> license" . (count($rows) === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_delete_software'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 3);

    $rows = softwareBulkRows($_POST['software_ids'] ?? []);
    foreach ($rows as $software_id => $row) {
        $client_id = intval($row['software_client_id']);
        mysqli_query($mysqli, "DELETE FROM software WHERE software_id = $software_id");
        logAudit("Software", "Delete", "$session_name deleted license " . escapeSql($row['software_name']) . " with its seats and keys", $client_id);
    }

    flashAlert("Deleted <strong>" . count($rows) . "</strong> license" . (count($rows) === 1 ? '' : 's'), 'error');

    redirect();

}

if (isExportRequest('export_software')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_software']);

    // Filters inherited from the software page - mirrors agent/software.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND software_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();

        $archive_query = $archived ? "software_archived_at IS NOT NULL" : "software_archived_at IS NULL";
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";

        // Client Filter
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND (software_client_id = $filter_client_id)";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }

        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR software_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND software_archived_at IS NULL)";
    }

    // Expiring In Filter
    if (!empty($_POST['expire_days'])) {
        if ($_POST['expire_days'] == "expired") {
            $expire_query = "AND (software_expire IS NOT NULL AND software_expire != '0000-00-00' AND software_expire < CURDATE())";
            $filter_summary['Expiry'] = 'Expired';
        } else {
            $expire_days = intval($_POST['expire_days']);
            $expire_query = "AND (software_expire IS NOT NULL AND software_expire != '0000-00-00' AND software_expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $expire_days DAY))";
            $filter_summary['Expiry'] = "Expiring within $expire_days days";
        }
    } else {
        // Default - any
        $expire_query = '';
    }

    // Attention tab
    $status_filters = softwareStatusFilters();
    $status = (string) ($_POST['status'] ?? '');
    $status_query = '';
    if (isset($status_filters[$status])) {
        $status_query = "AND ({$status_filters[$status]['sql']})";
        $filter_summary['Showing'] = $status_filters[$status]['label'];
    }

    // Software type
    $software_type_filter = trim(strip_tags((string) ($_POST['software_type'] ?? '')));
    $software_type_query = '';
    if ($software_type_filter !== '') {
        $software_type_query = "AND software_type = '" . escapeSql($software_type_filter) . "'";
        $filter_summary['Software Type'] = $software_type_filter;
    }

    // License type
    $license_type_query = '';
    if (!empty($_POST['license_type'])) {
        $license_type_query = "AND software_license_type = '" . escapeSql($_POST['license_type']) . "'";
        $filter_summary['License Type'] = $_POST['license_type'];
    }

    // Tags - a license with any of them
    $tag_query = tagExportFilterSql('license', $_POST['tags'] ?? [], $filter_summary, 'software.software_id');

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    // Who is on each license, in the same query - not two more queries per row
    mysqli_query($mysqli, "SET SESSION group_concat_max_len = 65535");
    $sql = mysqli_query(
        $mysqli,
        "SELECT software_description, software_expire, software_id, software_key, software_license_type,
        software_name, software_notes, software_purchase, software_purchase_reference, software_seats, software_type,
        software_version, client_name, vendor_name,
        " . softwareSeatsUsedSql('software.software_id') . " AS seats_used,
        (SELECT GROUP_CONCAT(asset_name ORDER BY asset_name SEPARATOR ', ') FROM software_assets AS sa
            JOIN assets ON assets.asset_id = sa.asset_id AND asset_archived_at IS NULL WHERE sa.software_id = software.software_id) AS assigned_to_assets,
        (SELECT GROUP_CONCAT(contact_name ORDER BY contact_name SEPARATOR ', ') FROM software_contacts AS sc
            JOIN contacts ON contacts.contact_id = sc.contact_id AND contact_archived_at IS NULL WHERE sc.software_id = software.software_id) AS assigned_to_contacts,
        (SELECT GROUP_CONCAT(sk.software_key ORDER BY sk.software_key_id SEPARATOR ', ') FROM software_keys AS sk WHERE sk.software_key_software_id = software.software_id) AS software_keys_list,
        " . tagNamesSql('license', 'software.software_id') . " AS software_tags
        FROM software
        LEFT JOIN clients ON client_id = software_client_id
        LEFT JOIN vendors ON vendor_id = software_vendor_id
        WHERE (software_name LIKE '%$q%' OR software_type LIKE '%$q%' OR software_key LIKE '%$q%' OR client_name LIKE '%$q%' OR vendor_name LIKE '%$q%'
            OR software.software_id IN (SELECT software_key_software_id FROM software_keys WHERE software_key LIKE '%$q%')
            " . tagSearchSql('license', $q, 'software.software_id') . ")
        AND $archive_query
        " . clientScopeSql('software_client_id') . "
        $client_query
        $tag_query
        $expire_query
        $status_query
        $software_type_query
        $license_type_query
        ORDER BY software_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('software', $format, $file_name_prepend . 'Licenses', 'Licenses', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            $row['assigned_to_assets'] = (string) $row['assigned_to_assets'];
            $row['assigned_to_contacts'] = (string) $row['assigned_to_contacts'];
            $row['software_keys_list'] = (string) $row['software_keys_list'];
            $row['software_tags'] = (string) $row['software_tags'];
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Software", "Export", "$session_name exported $num_rows license(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
