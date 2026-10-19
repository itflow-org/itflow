<?php

/*
 * License templates (Admin > License Templates) - making, changing, copying,
 * archiving and deleting them, and making licenses from one for several
 * clients at once. The work is in functions/software_template.php; a single
 * license is made from one by add_software or add_software_from_template
 * (agent/post/software.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* The template a request is for - or back to the list, saying it is gone. */
function softwareTemplateForRequest($software_template_id) {
    $row = softwareTemplateRow($software_template_id);
    if (!$row) {
        flashAlert('That license template no longer exists.', 'error');
        redirect('software_templates.php');
    }
    return $row;
}

if (isset($_POST['add_software_template']) || isset($_POST['edit_software_template'])) {

    validateCSRFToken();

    $editing = isset($_POST['edit_software_template']);
    $software_template_id = $editing ? intval(softwareTemplateForRequest($_POST['software_template_id'] ?? 0)['software_template_id']) : 0;

    $fields = softwareTemplateFields($_POST, $error);
    if (!$fields) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $software_template_id = softwareTemplateSave($software_template_id, $fields);

    logAudit("Software Template", $editing ? "Edit" : "Create", "$session_name " . ($editing ? 'edited' : 'created') . " license template " . escapeSql($fields['name']), 0, $software_template_id);

    flashAlert("License template <strong>" . escapeHtml($fields['name']) . "</strong> " . ($editing ? 'saved' : 'created'));

    // A new one opens on its page
    redirect($editing ? null : "software_template.php?software_template_id=$software_template_id");

}

if (isset($_POST['make_licenses_from_template'])) {

    // The same license for each client picked - the seats, dates and order reference given once for all
    validateCSRFToken();

    $row = softwareTemplateForRequest($_POST['software_template_id'] ?? 0);
    $software_template_id = intval($row['software_template_id']);
    if ($row['software_template_archived_at']) {
        flashAlert('That template is archived - restore it to make licenses from it.', 'error');
        redirect();
    }
    $client_ids = array_values(array_filter(array_map('intval', (array) ($_POST['client_ids'] ?? []))));
    if (!$client_ids) {
        flashAlert('Pick the clients to make one for.', 'error');
        redirect();
    }

    $post = [
        'seats' => $_POST['seats'] ?? '',
        'purchase_reference' => $_POST['purchase_reference'] ?? '',
        'purchase' => $_POST['purchase'] ?? '',
        'expire' => $_POST['expire'] ?? '',
    ];
    $result = softwareTemplateMakeForClients($software_template_id, $client_ids, $post, !empty($_POST['skip_existing']));

    foreach ($result['made'] as $client_id => $software_id) {
        if (isset($result['vendors'][$client_id])) {
            $vendor_id = intval(getFieldById('software', $software_id, 'software_vendor_id'));
            logAudit("Vendor", "Create", "$session_name created vendor " . escapeSql((string) getFieldById('vendors', $vendor_id, 'vendor_name')) . " from a template for license " . escapeSql($row['software_template_name']), $client_id, $vendor_id);
        }
        logAudit("Software", "Create", "$session_name created license " . escapeSql($row['software_template_name']) . " from template " . escapeSql($row['software_template_name']), $client_id, $software_id);
    }

    $made = count($result['made']);
    $skipped = count($result['skipped']);
    $vendors = count($result['vendors']);
    flashAlert(($made ? "Made $made license" . ($made === 1 ? '' : 's') : 'Made no licenses') . " from <strong>" . escapeHtml($row['software_template_name']) . "</strong>"
        . ($vendors ? ", adding vendor <strong>" . escapeHtml((string) $row['vendor_template_name']) . "</strong> for $vendors client" . ($vendors === 1 ? '' : 's') : '')
        . ($skipped ? " - skipped " . ($skipped === 1 ? escapeHtml(reset($result['skipped'])) . ", which already has one" : "$skipped clients that already have one") : ''), $made ? 'success' : 'warning');

    redirect("software_template.php?software_template_id=$software_template_id");

}

if (isset($_GET['copy_software_template'])) {

    validateCSRFToken();

    $row = softwareTemplateForRequest($_GET['copy_software_template']);
    $new_id = softwareTemplateCopy($row['software_template_id']);
    $new_name = (string) softwareTemplateRow($new_id)['software_template_name'];

    logAudit("Software Template", "Create", "$session_name copied license template " . escapeSql($row['software_template_name']) . " as " . escapeSql($new_name), 0, $new_id);

    flashAlert("License template <strong>" . escapeHtml($row['software_template_name']) . "</strong> copied as <strong>" . escapeHtml($new_name) . "</strong>");

    redirect("software_template.php?software_template_id=$new_id");

}

if (isset($_GET['archive_software_template']) || isset($_GET['restore_software_template'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_software_template']);
    $row = softwareTemplateForRequest($archive ? $_GET['archive_software_template'] : $_GET['restore_software_template']);
    $software_template_id = intval($row['software_template_id']);

    // Archived, it isn't offered for new licenses; the ones made from it stay
    mysqli_query($mysqli, "UPDATE software_templates SET software_template_archived_at = " . ($archive ? 'NOW()' : 'NULL') . ", software_template_updated_at = software_template_updated_at
        WHERE software_template_id = $software_template_id");

    logAudit("Software Template", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " license template " . escapeSql($row['software_template_name']), 0, $software_template_id);

    flashAlert("License template <strong>" . escapeHtml($row['software_template_name']) . "</strong> " . ($archive ? 'archived' : 'restored'), $archive ? 'error' : 'success');

    redirect();

}

if (isset($_GET['delete_software_template'])) {

    validateCSRFToken();

    $row = softwareTemplateForRequest($_GET['delete_software_template']);

    // The licenses made from it stay, no longer linked to it
    softwareTemplateDelete($row['software_template_id']);

    logAudit("Software Template", "Delete", "$session_name deleted license template " . escapeSql($row['software_template_name']));

    flashAlert("License template <strong>" . escapeHtml($row['software_template_name']) . "</strong> deleted", 'error');

    // From its own page, back to the list
    redirect(!empty($_GET['from_page']) ? 'software_templates.php' : null);

}
