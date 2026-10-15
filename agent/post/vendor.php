<?php

/*
 * ITFlow - GET/POST request handler for vendors
 *
 * A client's vendors are the client module's; ours (no client) are the
 * financial module's - vendorEnforce() (functions/vendor.php) checks the right
 * one, and the client access, for the vendor a request names rather than for
 * whatever the form says. Documents, files and portal logins are linked to a
 * vendor through post/link.php; the notes save through ajax.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The vendor a request names, once the agent may do this to it - or back with an error if it is gone. */
function vendorForRequest($vendor_id, $level) {
    global $client_id;
    $row = vendorRow($vendor_id);
    if (!$row) {
        flashAlert('That vendor no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['vendor_client_id']);
    vendorEnforce($client_id, $level);
    return $row;
}

/** Back to the list a vendor was on - its own page is gone once it is deleted. */
function vendorRedirectAfterDelete($client_id) {
    if (strpos($_SERVER['HTTP_REFERER'] ?? '', '/vendor.php') !== false) {
        redirect($client_id ? "vendors.php?client_id=$client_id&archived=1" : 'vendors.php?archived=1');
    }
    redirect();
}

/** Insert a vendor from a form's fields for this client - the new id, or 0 without a name. */
function vendorCreate($fields, $client_id) {
    global $mysqli;
    $set = vendorFormSet($fields);
    if ($set === null) {
        return 0;
    }
    mysqli_query($mysqli, "INSERT INTO vendors SET $set, vendor_client_id = " . intval($client_id));
    return mysqli_insert_id($mysqli);
}

if (isset($_POST['add_vendor'])) {

    validateCSRFToken();

    $client_id = intval($_POST['client_id'] ?? 0);
    vendorEnforce($client_id, 2);
    if ($client_id && !getFieldById('clients', $client_id, 'client_name')) {
        flashAlert('That client no longer exists.', 'error');
        redirect();
    }

    $vendor_id = vendorCreate($_POST, $client_id);
    if (!$vendor_id) {
        flashAlert('A vendor needs a name.', 'error');
        redirect();
    }

    vendorSetTags($vendor_id, $_POST['tags'] ?? []);

    $name = trim($_POST['name']);
    $template_note = intval($_POST['vendor_template_id'] ?? 0) ? ' from a template' : '';
    logAudit("Vendor", "Create", "$session_name created vendor " . escapeSql($name) . $template_note, $client_id, $vendor_id);

    flashAlert("Vendor <strong>$name</strong> created");

    redirect();

}

// Kept for anything still posting the old New from Template form - the vendor form now does both
if (isset($_POST['add_vendor_from_template'])) {

    validateCSRFToken();

    $client_id = intval($_POST['client_id'] ?? 0);
    vendorEnforce($client_id, 2);

    $template = vendorTemplateRow($_POST['vendor_template_id'] ?? 0);
    if (!$template) {
        flashAlert('That template no longer exists.', 'error');
        redirect();
    }

    $vendor_id = vendorCreate(vendorTemplateFormFields($template), $client_id);

    logAudit("Vendor", "Create", "$session_name created vendor " . escapeSql($template['vendor_template_name']) . " from a template", $client_id, $vendor_id);

    flashAlert("Vendor <strong>{$template['vendor_template_name']}</strong> created from template");

    redirect();

}

/*
 * One template, added as a vendor to several clients at once - a new standard
 * vendor rolled out everywhere. A client that already has a vendor made from
 * this template is left alone.
 */
if (isset($_POST['add_vendor_template_to_clients'])) {

    validateCSRFToken();

    enforceUserPermission('module_client', 2);

    $template = vendorTemplateRow($_POST['vendor_template_id'] ?? 0);
    if (!$template) {
        flashAlert('That template no longer exists.', 'error');
        redirect();
    }
    $template_id = intval($template['vendor_template_id']);
    $fields = vendorTemplateFormFields($template);

    $added = $already = 0;
    foreach (array_unique(array_filter(array_map('intval', (array) ($_POST['client_ids'] ?? [])))) as $target_client_id) {
        if (!hasClientAccess($target_client_id) || !getFieldById('clients', $target_client_id, 'client_name')) {
            continue;
        }
        if (mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM vendors WHERE vendor_client_id = $target_client_id AND vendor_template_id = $template_id LIMIT 1"))) {
            $already++;
            continue;
        }
        $vendor_id = vendorCreate($fields, $target_client_id);
        logAudit("Vendor", "Create", "$session_name created vendor " . escapeSql($template['vendor_template_name']) . " from a template", $target_client_id, $vendor_id);
        $added++;
    }

    flashAlert("Added <strong>{$template['vendor_template_name']}</strong> to <strong>$added</strong> client" . ($added === 1 ? '' : 's') . ($already ? " - $already already had it" : ''));

    redirect();

}

if (isset($_POST['edit_vendor'])) {

    validateCSRFToken();

    $row = vendorForRequest($_POST['vendor_id'] ?? 0, 2);
    $vendor_id = intval($row['vendor_id']);

    $set = vendorFormSet($_POST);
    if ($set === null) {
        flashAlert('A vendor needs a name.', 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE vendors SET $set WHERE vendor_id = $vendor_id");

    // Only a form that showed the tags changes them
    if (isset($_POST['tags_form'])) {
        vendorSetTags($vendor_id, $_POST['tags'] ?? []);
    }

    $name = trim($_POST['name']);
    logAudit("Vendor", "Edit", "$session_name edited vendor " . escapeSql($name), $client_id, $vendor_id);

    flashAlert("Vendor <strong>$name</strong> updated");

    redirect();

}

if (isset($_GET['favorite_vendor']) || isset($_GET['unfavorite_vendor'])) {

    validateCSRFToken();

    $favorite = isset($_GET['favorite_vendor']) ? 1 : 0;
    $row = vendorForRequest($favorite ? $_GET['favorite_vendor'] : $_GET['unfavorite_vendor'], 2);
    $vendor_id = intval($row['vendor_id']);

    mysqli_query($mysqli, "UPDATE vendors SET vendor_favorite = $favorite WHERE vendor_id = $vendor_id");

    logAudit("Vendor", "Edit", "$session_name " . ($favorite ? 'starred' : 'unstarred') . " vendor " . escapeSql($row['vendor_name']), $client_id, $vendor_id);

    redirect();

}

if (isset($_GET['archive_vendor'])) {

    validateCSRFToken();

    $row = vendorForRequest($_GET['archive_vendor'], 2);
    $vendor_id = intval($row['vendor_id']);

    mysqli_query($mysqli, "UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id");

    logAudit("Vendor", "Archive", "$session_name archived vendor " . escapeSql($row['vendor_name']), $client_id, $vendor_id);

    flashAlert("Vendor <strong>{$row['vendor_name']}</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_vendor'])) {

    validateCSRFToken();

    $row = vendorForRequest($_GET['restore_vendor'], 2);
    $vendor_id = intval($row['vendor_id']);

    mysqli_query($mysqli, "UPDATE vendors SET vendor_archived_at = NULL WHERE vendor_id = $vendor_id");

    logAudit("Vendor", "Restore", "$session_name restored vendor " . escapeSql($row['vendor_name']), $client_id, $vendor_id);

    flashAlert("Vendor <strong>{$row['vendor_name']}</strong> restored");

    redirect();

}

if (isset($_GET['delete_vendor'])) {

    validateCSRFToken();

    $row = vendorForRequest($_GET['delete_vendor'], 3);
    $vendor_id = intval($row['vendor_id']);

    $blocker = vendorDeleteBlocker($vendor_id);
    if ($blocker) {
        flashAlert("<strong>{$row['vendor_name']}</strong> was not deleted - $blocker.", 'error');
        redirect();
    }

    // What came from them stays, from no vendor; tags and links go with it
    vendorClearReferences($vendor_id);
    mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_id = $vendor_id");

    logAudit("Vendor", "Delete", "$session_name deleted vendor " . escapeSql($row['vendor_name']), $client_id);

    flashAlert("Vendor <strong>{$row['vendor_name']}</strong> deleted", 'error');

    vendorRedirectAfterDelete($client_id);

}

if (isset($_POST['bulk_archive_vendors'])) {

    validateCSRFToken();

    $count = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['vendor_ids'] ?? []))) as $vendor_id) {
        $row = vendorRow($vendor_id);
        if (!$row || $row['vendor_archived_at']) {
            continue;
        }
        $client_id = intval($row['vendor_client_id']);
        vendorEnforce($client_id, 2);
        mysqli_query($mysqli, "UPDATE vendors SET vendor_archived_at = NOW() WHERE vendor_id = $vendor_id");
        logAudit("Vendor", "Archive", "$session_name archived vendor " . escapeSql($row['vendor_name']), $client_id, $vendor_id);
        $count++;
    }

    flashAlert("Archived <strong>$count</strong> vendor" . ($count === 1 ? '' : 's'), 'error');

    redirect();

}

if (isset($_POST['bulk_restore_vendors'])) {

    validateCSRFToken();

    $count = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['vendor_ids'] ?? []))) as $vendor_id) {
        $row = vendorRow($vendor_id);
        if (!$row || !$row['vendor_archived_at']) {
            continue;
        }
        $client_id = intval($row['vendor_client_id']);
        vendorEnforce($client_id, 2);
        mysqli_query($mysqli, "UPDATE vendors SET vendor_archived_at = NULL WHERE vendor_id = $vendor_id");
        logAudit("Vendor", "Restore", "$session_name restored vendor " . escapeSql($row['vendor_name']), $client_id, $vendor_id);
        $count++;
    }

    flashAlert("Restored <strong>$count</strong> vendor" . ($count === 1 ? '' : 's'));

    redirect();

}

if (isset($_POST['bulk_delete_vendors'])) {

    validateCSRFToken();

    $count = 0;
    $kept = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['vendor_ids'] ?? []))) as $vendor_id) {
        $row = vendorRow($vendor_id);
        if (!$row) {
            continue;
        }
        $client_id = intval($row['vendor_client_id']);
        vendorEnforce($client_id, 3);
        if (vendorDeleteBlocker($vendor_id)) {
            $kept[] = $row['vendor_name'];
            continue;
        }
        vendorClearReferences($vendor_id);
        mysqli_query($mysqli, "DELETE FROM vendors WHERE vendor_id = $vendor_id");
        logAudit("Vendor", "Delete", "$session_name deleted vendor " . escapeSql($row['vendor_name']), $client_id);
        $count++;
    }

    flashAlert("Deleted <strong>$count</strong> vendor" . ($count === 1 ? '' : 's') . ($kept ? ' - kept <strong>' . implode(', ', $kept) . '</strong>, which have expenses booked to them' : ''), 'error');

    redirect();

}

if (isExportRequest('export_vendors')) {

    validateCSRFToken();

    $format = resolveExportFormat($_POST['export_vendors']);

    // Filters inherited from the vendors page - mirrors agent/vendors.php
    $filter_summary = [];

    // Archived Filter
    $archived = (isset($_POST['archived']) && $_POST['archived'] == 1);
    if ($archived) {
        $filter_summary['Archived'] = 'Archived only';
    }
    $archive_query = $archived ? "vendor_archived_at IS NOT NULL" : "vendor_archived_at IS NULL";

    if (!empty($_POST['client_id'])) {
        // One client's vendors
        $client_id = intval($_POST['client_id']);
        enforceUserPermission('module_client');
        enforceClientAccess();
        $client_query = "AND vendor_client_id = $client_id";
        $client_name = getFieldById('clients', $client_id, 'client_name');
        $file_name_prepend = "$client_name-";
        $filter_summary['Client'] = $client_name;
    } elseif (($_POST['view'] ?? '') === 'clients') {
        // Every client's vendors this agent may see
        enforceUserPermission('module_client');
        $client_id = 0; // for Logging
        $client_query = "AND vendor_client_id > 0";
        $file_name_prepend = "$session_company_name-Client-";
        $filter_summary['Vendors'] = 'Client vendors';
        if (!empty($_POST['client'])) {
            $filter_client_id = intval($_POST['client']);
            $client_query = "AND vendor_client_id = $filter_client_id";
            $filter_summary['Client'] = getFieldById('clients', $filter_client_id, 'client_name');
        }
        $archive_query = $archived ? "(client_archived_at IS NOT NULL OR vendor_archived_at IS NOT NULL)" : "(client_archived_at IS NULL AND vendor_archived_at IS NULL)";
    } else {
        // Ours, same as the vendors page
        $client_id = 0; // for Logging
        $client_query = "AND vendor_client_id = 0";
        $file_name_prepend = "$session_company_name-";
    }

    // Tags Filter - a vendor with any of them
    $tag_query = '';
    if (isset($_POST['tags']) && is_array($_POST['tags']) && !empty($_POST['tags'])) {
        $tag_filter = implode(",", array_map('intval', $_POST['tags']));
        $tag_query = "AND vendors.vendor_id IN (SELECT vendor_id FROM vendor_tags WHERE tag_id IN ($tag_filter))";

        $tag_names = [];
        $sql_tags = mysqli_query($mysqli, "SELECT tag_name FROM tags WHERE tag_id IN ($tag_filter) ORDER BY tag_name ASC");
        while ($tag_row = mysqli_fetch_assoc($sql_tags)) {
            $tag_names[] = $tag_row['tag_name'];
        }
        $filter_summary['Tags'] = implode(', ', $tag_names);
    }

    // Search Filter
    $q = escapeSql($_POST['q'] ?? '');
    if (!empty($q)) {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query(
        $mysqli,
        "SELECT vendors.*, client_name,
            (SELECT GROUP_CONCAT(tag_name ORDER BY tag_name SEPARATOR ', ') FROM vendor_tags JOIN tags ON tags.tag_id = vendor_tags.tag_id WHERE vendor_tags.vendor_id = vendors.vendor_id) AS vendor_tags,
            (SELECT COUNT(*) FROM tickets WHERE ticket_client_id = vendor_client_id AND ticket_vendor_id = vendors.vendor_id AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL) AS open_case_count
        FROM vendors
        LEFT JOIN clients ON client_id = vendor_client_id
        WHERE $archive_query
        AND (vendor_name LIKE '%$q%' OR vendor_description LIKE '%$q%' OR vendor_account_number LIKE '%$q%' OR vendor_website LIKE '%$q%'
            OR vendor_contact_name LIKE '%$q%' OR vendor_email LIKE '%$q%' OR vendor_contact_email LIKE '%$q%' OR vendor_phone LIKE '%$q%' OR vendor_notes LIKE '%$q%')
        $client_query
        $tag_query
        " . clientScopeSql('vendor_client_id') . "
        ORDER BY client_name ASC, vendor_name ASC"
    );

    $num_rows = mysqli_num_rows($sql);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $export = beginExport('vendors', $format, $file_name_prepend . 'Vendors', 'Vendors', summarizeExportFilters($filter_summary));

        while ($row = mysqli_fetch_assoc($sql)) {
            addExportRow($export, $row);
        }

        finishExport($export);
    }

    logAudit("Vendor", "Export", "$session_name exported $num_rows vendor(s) to a " . strtoupper($format) . " file", $client_id);

    exit;

}
