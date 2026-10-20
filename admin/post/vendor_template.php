<?php

/*
 * Vendor templates (Admin > Vendor Templates) - making, changing (the
 * vendors made from it that still said what it did changing with it),
 * copying, archiving, restoring and deleting them, and saying who should have
 * one. The work is in functions/vendor_template.php; a vendor is made from
 * one by add_vendor, add_vendor_from_template and
 * add_vendor_template_to_clients, and brought in line with one, linked to one
 * or unlinked by update_vendors_from_template, link_vendors_to_template and
 * unlink_vendor_template (agent/post/vendor.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* The template a request is for - or back to the list, saying it is gone. */
function vendorTemplateForRequest($vendor_template_id) {
    $row = vendorTemplateInfo($vendor_template_id);
    if (!$row) {
        flashAlert('That vendor template no longer exists.', 'error');
        redirect('vendor_templates.php');
    }
    return $row;
}

if (isset($_POST['add_vendor_template']) || isset($_POST['edit_vendor_template'])) {

    validateCSRFToken();

    $editing = isset($_POST['edit_vendor_template']);
    $before = $editing ? vendorTemplateForRequest($_POST['vendor_template_id'] ?? 0) : null;
    $vendor_template_id = $before ? intval($before['vendor_template_id']) : 0;

    $fields = vendorTemplateFields($_POST, $error);
    if (!$fields) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $vendor_template_id = vendorTemplateSave($vendor_template_id, $fields);
    $name = $fields['name'];

    if ($editing) {
        $changed = vendorTemplateChanged(vendorTemplateRowFields($before), $fields);
        $renamed = $before['vendor_template_name'] !== $name ? ' (was ' . $before['vendor_template_name'] . ')' : '';
        logAudit("Vendor Template", "Edit", "$session_name edited vendor template " . escapeSql($name . $renamed) . ($changed ? ' - ' . escapeSql(mb_strtolower(implode(', ', $changed))) : ''), 0, $vendor_template_id);

        // The vendors made from it that still said what it did follow it (vendorTemplateFollow())
        $followed = !empty($_POST['follow']) ? vendorTemplateFollow($vendor_template_id, vendorTemplateRowFields($before), $fields) : [];
        foreach ($followed as $vendor_id => $vendor) {
            logAudit("Vendor", "Edit", "$session_name updated vendor " . escapeSql($vendor['name']) . " from template " . escapeSql($name) . " - " . escapeSql(mb_strtolower(implode(', ', $vendor['fields']))), $vendor['client_id'], $vendor_id);
        }
        $out_of_step = $followed || !empty($_POST['follow']) ? (vendorTemplateOutOfStep([$vendor_template_id])[$vendor_template_id] ?? 0) : 0;

        flashAlert("Vendor template <strong>" . escapeHtml($name) . "</strong> saved" . ($followed ? ", and " . count($followed) . " vendor" . (count($followed) === 1 ? '' : 's') . " made from it changed with it" : '')
            . ($out_of_step ? " - $out_of_step out of step" : ''));

        redirect();
    }

    // Made from one of a client's vendors - that vendor is now made from it
    $from_vendor = vendorRow($_POST['from_vendor'] ?? 0);
    if ($from_vendor) {
        mysqli_query($mysqli, "UPDATE vendors SET vendor_template_id = $vendor_template_id, vendor_updated_at = vendor_updated_at WHERE vendor_id = " . intval($from_vendor['vendor_id']));
    }

    logAudit("Vendor Template", "Create", "$session_name created vendor template " . escapeSql($name) . ($from_vendor ? " from vendor " . escapeSql($from_vendor['vendor_name']) . ($from_vendor['client_name'] !== null ? " of " . escapeSql($from_vendor['client_name']) : '') : ''), 0, $vendor_template_id);

    flashAlert("Vendor template <strong>" . escapeHtml($name) . "</strong> created");

    // A new one opens on its page
    redirect("vendor_template.php?vendor_template_id=$vendor_template_id");

}

if (isset($_GET['copy_vendor_template'])) {

    validateCSRFToken();

    $row = vendorTemplateForRequest($_GET['copy_vendor_template']);
    $new_id = vendorTemplateCopy($row['vendor_template_id']);
    $new_name = (string) vendorTemplateInfo($new_id)['vendor_template_name'];

    logAudit("Vendor Template", "Create", "$session_name copied vendor template " . escapeSql($row['vendor_template_name']) . " as " . escapeSql($new_name), 0, $new_id);

    flashAlert("Vendor template <strong>" . escapeHtml($row['vendor_template_name']) . "</strong> copied as <strong>" . escapeHtml($new_name) . "</strong>");

    redirect("vendor_template.php?vendor_template_id=$new_id");

}

if (isset($_GET['archive_vendor_template']) || isset($_GET['restore_vendor_template'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_vendor_template']);
    $row = vendorTemplateForRequest($archive ? $_GET['archive_vendor_template'] : $_GET['restore_vendor_template']);
    $vendor_template_id = intval($row['vendor_template_id']);

    // Archived, it isn't offered for new vendors; the ones made from it stay
    mysqli_query($mysqli, "UPDATE vendor_templates SET vendor_template_archived_at = " . ($archive ? 'NOW()' : 'NULL') . ", vendor_template_updated_at = vendor_template_updated_at
        WHERE vendor_template_id = $vendor_template_id");

    logAudit("Vendor Template", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " vendor template " . escapeSql($row['vendor_template_name']), 0, $vendor_template_id);

    flashAlert("Vendor template <strong>" . escapeHtml($row['vendor_template_name']) . "</strong> " . ($archive ? 'archived' : 'restored'), $archive ? 'error' : 'success');

    redirect();

}

if (isset($_GET['delete_vendor_template'])) {

    validateCSRFToken();

    $row = vendorTemplateForRequest($_GET['delete_vendor_template']);

    // The vendors made from it stay, no longer linked to it
    vendorTemplateDelete($row['vendor_template_id']);

    logAudit("Vendor Template", "Delete", "$session_name deleted vendor template " . escapeSql($row['vendor_template_name']), 0, intval($row['vendor_template_id']));

    flashAlert("Vendor template <strong>" . escapeHtml($row['vendor_template_name']) . "</strong> deleted", 'error');

    // From its own page, back to the list
    redirect(!empty($_GET['from_page']) ? 'vendor_templates.php' : null);

}

if (isset($_POST['save_vendor_template_required'])) {

    // Who should have a vendor from it
    validateCSRFToken();

    $row = vendorTemplateForRequest($_POST['vendor_template_id'] ?? 0);
    $vendor_template_id = intval($row['vendor_template_id']);
    $required = intval($_POST['required'] ?? 0);
    $tag_ids = array_map('intval', array_filter((array) ($_POST['tags'] ?? []), 'is_scalar'));
    if ($required === VENDOR_TEMPLATE_REQUIRED_TAGGED && !array_filter($tag_ids)) {
        flashAlert('Pick the client tags - or Every client.', 'error');
        redirect();
    }
    vendorTemplateSetRequired($vendor_template_id, $required, $tag_ids);

    $saved = vendorTemplateInfo($vendor_template_id);
    $scope = vendorTemplateScopeText($saved['vendor_template_required'], vendorTemplateRequiredTags([$vendor_template_id])[$vendor_template_id]);
    $said = $scope !== '' ? "required of " . lcfirst($scope) : 'not required of anyone';

    logAudit("Vendor Template", "Edit", "$session_name set vendor template " . escapeSql($row['vendor_template_name']) . " " . escapeSql($said), 0, $vendor_template_id);

    flashAlert("<strong>" . escapeHtml($row['vendor_template_name']) . "</strong> is " . escapeHtml($said));

    redirect();

}
