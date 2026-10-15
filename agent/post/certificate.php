<?php

/*
 * ITFlow - GET/POST request handler for client SSL certificates
 *
 * Every handler checks the support module and the certificate's own client;
 * the domain a certificate links to must be one of that client's. Checks of
 * the host go through certificateCheck() in functions/certificate.php, and a
 * check that reaches nothing records only that - the certificate on file,
 * its dates and its issuer stay as they were.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The certificate a request names, after the module and client checks - or back with an error if it is gone. */
function certificateForRequest($certificate_id, $level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = certificateRow($certificate_id);
    if (!$row) {
        flashAlert('That certificate no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['certificate_client_id']);
    enforceClientAccess();
    return $row;
}

/** The certificates a bulk action names that this agent may act on - [id => row]. */
function certificatesForBulk($level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['certificate_ids'] ?? []))) as $certificate_id) {
        $row = certificateRow($certificate_id);
        if (!$row) {
            continue;
        }
        $client_id = intval($row['certificate_client_id']);
        enforceClientAccess();
        $rows[$certificate_id] = $row;
    }
    return $rows;
}

/** What a check found, for a flash message - [message, type]. $name is shown as given. */
function certificateCheckMessage($check, $name, $host_display) {
    $name = escapeHtml($name);
    if ($check === null) {
        return ["<strong>$name</strong> has no host to check - Edit it to add the host it is served on, or paste the certificate.", 'error'];
    }
    if (empty($check['reachable'])) {
        return ["Nothing answered on <strong>" . escapeHtml($host_display) . "</strong> - the certificate on file was left as it was. Check the host and port, or paste the certificate if ITFlow can't reach it.", 'error'];
    }
    $state = CERTIFICATE_CHECK_STATES[$check['status']];
    if ($check['status'] === 'ok') {
        $expiry = certificateExpiry(['certificate_expire' => $check['expire'], 'certificate_issued_at' => $check['issued_at']]);
        return ["Checked <strong>$name</strong> - OK" . ($expiry ? ', ' . lcfirst($expiry['text']) : ''), $expiry && $expiry['state'] === 'soon' ? 'warning' : 'success'];
    }
    return ["Checked <strong>$name</strong> - " . escapeHtml($state['label']) . ': ' . escapeHtml(lcfirst($state['text'])), $check['status'] === 'expired' ? 'error' : 'warning'];
}

if (isset($_POST['add_certificate'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    $client_id = intval($_POST['client_id'] ?? 0);

    enforceClientAccess();

    if (!$client_id || !getFieldById('clients', $client_id, 'client_name')) {
        flashAlert('Pick the client this certificate is for.', 'error');
        redirect();
    }

    $form = certificateFormSet($_POST, $client_id, $error);
    if ($form === null) {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO certificates SET {$form['set']}, certificate_client_id = $client_id");
    $certificate_id = mysqli_insert_id($mysqli);

    // What the host serves - unless a different certificate was pasted, which is kept
    $check = $form['host'] ? certificateCheckAndSave($certificate_id, $form['parsed']['pem'] ?? '', false) : null;

    $name = $form['name'];
    $name_html = escapeHtml($name);
    logAudit("Certificate", "Create", "$session_name created certificate " . escapeSql($name), $client_id, $certificate_id);

    if ($check !== null && empty($check['reachable'])) {
        flashAlert("Certificate <strong>$name_html</strong> created - nothing answered on <strong>" . escapeHtml($form['host']['display']) . "</strong>, so " . ($form['parsed'] ? 'what you pasted is what is on file' : 'paste the certificate, or set the expiry by hand'), 'warning');
    } elseif ($check !== null) {
        [$message, $type] = certificateCheckMessage($check, $name, $form['host']['display']);
        flashAlert("Certificate <strong>$name_html</strong> created and " . lcfirst(str_replace("<strong>$name_html</strong> ", '', $message)), $type);
    } else {
        flashAlert("Certificate <strong>$name_html</strong> created");
    }

    redirect();

}

if (isset($_POST['edit_certificate'])) {

    validateCSRFToken();

    $row = certificateForRequest($_POST['certificate_id'] ?? 0, 2);
    $certificate_id = intval($row['certificate_id']);

    $form = certificateFormSet($_POST, $client_id, $error);
    if ($form === null) {
        flashAlert($error, 'error');
        redirect();
    }

    $before = certificateHistorySnapshot($certificate_id);

    mysqli_query($mysqli, "UPDATE certificates SET {$form['set']} WHERE certificate_id = $certificate_id");

    certificateWriteHistory($certificate_id, $before, certificateHistorySnapshot($certificate_id));

    // A new host is a new place to check; otherwise saving stays quick. A
    // certificate newly pasted here is kept over what the host serves.
    $host_changed = $form['host'] && $form['host']['display'] !== (string) $row['certificate_domain'];
    $pasted_new = $form['parsed'] && $form['parsed']['fingerprint'] !== (string) $row['certificate_fingerprint'];
    if (!$form['host']) {
        // No host - nothing to check, so no result from one
        mysqli_query($mysqli, "UPDATE certificates SET certificate_check_status = NULL, certificate_checked_at = NULL WHERE certificate_id = $certificate_id");
    } elseif ($host_changed) {
        certificateCheckAndSave($certificate_id, $pasted_new ? $form['parsed']['pem'] : '');
    }

    $name = $form['name'];
    logAudit("Certificate", "Edit", "$session_name edited certificate " . escapeSql($name), $client_id, $certificate_id);

    flashAlert("Certificate <strong>" . escapeHtml($name) . "</strong> updated");

    redirect();

}

// Check now - connect to the host and save what it serves
if (isset($_GET['check_certificate']) || isset($_GET['refresh_certificate'])) {

    validateCSRFToken();

    $row = certificateForRequest($_GET['check_certificate'] ?? $_GET['refresh_certificate'], 2);
    $certificate_id = intval($row['certificate_id']);

    $check = certificateCheckAndSave($certificate_id);
    [$message, $type] = certificateCheckMessage($check, $row['certificate_name'], $row['certificate_domain']);

    if ($check !== null) {
        logAudit("Certificate", "Check", "$session_name checked certificate " . escapeSql($row['certificate_name']) . ' - ' . (empty($check['reachable']) ? 'unreachable' : $check['status']), $client_id, $certificate_id);
    }

    flashAlert($message, $type);

    redirect();

}

if (isset($_GET['favorite_certificate']) || isset($_GET['unfavorite_certificate'])) {

    validateCSRFToken();

    $favorite = isset($_GET['favorite_certificate']) ? 1 : 0;
    $row = certificateForRequest($favorite ? $_GET['favorite_certificate'] : $_GET['unfavorite_certificate'], 2);
    $certificate_id = intval($row['certificate_id']);

    mysqli_query($mysqli, "UPDATE certificates SET certificate_favorite = $favorite WHERE certificate_id = $certificate_id");

    logAudit("Certificate", "Edit", "$session_name " . ($favorite ? 'starred' : 'unstarred') . " certificate " . escapeSql($row['certificate_name']), $client_id, $certificate_id);

    redirect();

}

if (isset($_GET['archive_certificate'])) {

    validateCSRFToken();

    $row = certificateForRequest($_GET['archive_certificate'], 2);
    $certificate_id = intval($row['certificate_id']);

    mysqli_query($mysqli, "UPDATE certificates SET certificate_archived_at = NOW() WHERE certificate_id = $certificate_id");

    logAudit("Certificate", "Archive", "$session_name archived certificate " . escapeSql($row['certificate_name']), $client_id, $certificate_id);

    flashAlert("Certificate <strong>" . escapeHtml($row['certificate_name']) . "</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_certificate'])) {

    validateCSRFToken();

    $row = certificateForRequest($_GET['restore_certificate'], 2);
    $certificate_id = intval($row['certificate_id']);

    mysqli_query($mysqli, "UPDATE certificates SET certificate_archived_at = NULL WHERE certificate_id = $certificate_id");

    logAudit("Certificate", "Restore", "$session_name restored certificate " . escapeSql($row['certificate_name']), $client_id, $certificate_id);

    flashAlert("Certificate <strong>" . escapeHtml($row['certificate_name']) . "</strong> restored");

    redirect();

}

if (isset($_GET['delete_certificate'])) {

    validateCSRFToken();

    $row = certificateForRequest($_GET['delete_certificate'], 3);
    $certificate_id = intval($row['certificate_id']);

    // Its history and its links to services go with it (ON DELETE CASCADE)
    mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_id = $certificate_id");

    logAudit("Certificate", "Delete", "$session_name deleted certificate " . escapeSql($row['certificate_name']), $client_id);

    flashAlert("Certificate <strong>" . escapeHtml($row['certificate_name']) . "</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['bulk_favorite_certificates']) || isset($_POST['bulk_unfavorite_certificates'])) {

    validateCSRFToken();

    $favorite = isset($_POST['bulk_favorite_certificates']) ? 1 : 0;
    $count = 0;
    foreach (certificatesForBulk(2) as $certificate_id => $row) {
        mysqli_query($mysqli, "UPDATE certificates SET certificate_favorite = $favorite WHERE certificate_id = $certificate_id");
        logAudit("Certificate", "Edit", "$session_name " . ($favorite ? 'starred' : 'unstarred') . " certificate " . escapeSql($row['certificate_name']), intval($row['certificate_client_id']), $certificate_id);
        $count++;
    }

    flashAlert(($favorite ? 'Starred' : 'Unstarred') . " <strong>$count</strong> certificate" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_check_certificates']) || isset($_POST['bulk_refresh_certificates'])) {

    validateCSRFToken();

    // A host that doesn't answer waits out a timeout - don't time out mid-batch
    set_time_limit(0);

    $tally = array_fill_keys(array_keys(CERTIFICATE_CHECK_STATES), 0);
    $no_host = 0;
    foreach (certificatesForBulk(2) as $certificate_id => $row) {
        $check = certificateCheckAndSave($certificate_id);
        if ($check === null) {
            $no_host++;
            continue;
        }
        $status = empty($check['reachable']) ? 'unreachable' : $check['status'];
        $tally[$status]++;
        logAudit("Certificate", "Check", "$session_name checked certificate " . escapeSql($row['certificate_name']) . " - $status", intval($row['certificate_client_id']), $certificate_id);
    }

    $checked = array_sum($tally);
    $parts = [];
    foreach ($tally as $status => $count) {
        if ($count) {
            $parts[] = "<strong>$count</strong> " . (in_array($status, ['ok', 'mismatch'], true) ? CERTIFICATE_CHECK_STATES[$status]['label'] : strtolower(CERTIFICATE_CHECK_STATES[$status]['label']));
        }
    }
    $problems = $checked - $tally['ok'];
    flashAlert("Checked <strong>$checked</strong> certificate" . ($checked === 1 ? '' : 's') . ($parts ? ' - ' . implode(', ', $parts) : '') . ($no_host ? "; <strong>$no_host</strong> with no host to check" : ''), $problems || $no_host ? 'warning' : 'success');

    redirect();

}

if (isset($_POST['bulk_archive_certificates'])) {

    validateCSRFToken();

    $count = 0;
    foreach (certificatesForBulk(2) as $certificate_id => $row) {
        mysqli_query($mysqli, "UPDATE certificates SET certificate_archived_at = NOW() WHERE certificate_id = $certificate_id");
        logAudit("Certificate", "Archive", "$session_name archived certificate " . escapeSql($row['certificate_name']), intval($row['certificate_client_id']), $certificate_id);
        $count++;
    }

    flashAlert("Archived <strong>$count</strong> certificate" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isset($_POST['bulk_restore_certificates'])) {

    validateCSRFToken();

    $count = 0;
    foreach (certificatesForBulk(2) as $certificate_id => $row) {
        mysqli_query($mysqli, "UPDATE certificates SET certificate_archived_at = NULL WHERE certificate_id = $certificate_id");
        logAudit("Certificate", "Restore", "$session_name restored certificate " . escapeSql($row['certificate_name']), intval($row['certificate_client_id']), $certificate_id);
        $count++;
    }

    flashAlert("Restored <strong>$count</strong> certificate" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_delete_certificates'])) {

    validateCSRFToken();

    $count = 0;
    foreach (certificatesForBulk(3) as $certificate_id => $row) {
        mysqli_query($mysqli, "DELETE FROM certificates WHERE certificate_id = $certificate_id");
        logAudit("Certificate", "Delete", "$session_name deleted certificate " . escapeSql($row['certificate_name']), intval($row['certificate_client_id']));
        $count++;
    }

    flashAlert("Deleted <strong>$count</strong> certificate" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isExportRequest('export_certificates')) {

    validateCSRFToken();

    // Exports are reads - see CONTRIBUTING.md
    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_certificates']);

    // Filters inherited from the certificates page - mirrors agent/certificates.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }

    if (!empty($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $client_query = "AND certificate_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;

        enforceClientAccess();

        $archive_query = $archived ? "certificate_archived_at IS NOT NULL" : "certificate_archived_at IS NULL";
    } else {
        $client_query = '';
        $client_id = 0; // for Logging
        $file_name_prepend = "$session_company_name-";

        // Client Filter
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND (certificate_client_id = $filter_client_id)";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }

        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR certificate_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND certificate_archived_at IS NULL)";
    }

    // Expiring In Filter
    $expire_query = '';
    if (!empty($_POST['expire_days'])) {
        if ($_POST['expire_days'] == "expired") {
            $expire_query = "AND (certificate_expire IS NOT NULL AND certificate_expire < CURDATE())";
            $filter_summary['Expiry'] = 'Expired';
        } else {
            $expire_days = intval($_POST['expire_days']);
            $expire_query = "AND (certificate_expire IS NOT NULL AND certificate_expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $expire_days DAY))";
            $filter_summary['Expiry'] = "Expiring within $expire_days days";
        }
    }

    // Problems Filter - the last check of the host found something wrong
    $problem_query = '';
    if (!empty($_POST['problems'])) {
        $problem_query = 'AND ' . certificateProblemSql();
        $filter_summary['Check'] = 'Problems found';
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    [$certificate_columns, $certificate_joins] = certificateSelectSql();
    $status_labels = [];
    foreach (CERTIFICATE_CHECK_STATES as $status => $state) {
        $status_labels[] = "WHEN '$status' THEN '" . escapeSql($state['label']) . "'";
    }
    $sql = mysqli_query(
        $mysqli,
        "SELECT $certificate_columns,
            CASE certificate_check_status " . implode(' ', $status_labels) . " ELSE '' END AS certificate_check_label,
            REPLACE(certificate_names, '\\n', ', ') AS certificate_names_list
        FROM certificates $certificate_joins
        WHERE $archive_query
        AND (certificate_name LIKE '%$q%' OR certificate_domain LIKE '%$q%' OR certificate_description LIKE '%$q%' OR certificate_issued_by LIKE '%$q%'
            OR certificate_names LIKE '%$q%' OR certificate_fingerprint LIKE '%$q%' OR client_name LIKE '%$q%')
        " . clientScopeSql('certificate_client_id') . "
        $client_query
        $expire_query
        $problem_query
        ORDER BY client_name ASC, certificate_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('certificates', $format, $file_name_prepend . 'Certificates', 'Certificates', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Certificate", "Export", "$session_name exported $num_rows certificate(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
