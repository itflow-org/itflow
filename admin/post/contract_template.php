<?php

/*
 * ITFlow - contract templates: add, change, copy, archive, restore and
 * delete. A contract started from a template keeps its own copy of the
 * terms, so changing or deleting a template leaves those contracts as they
 * are. The fields are checked by contractFromInput() in functions/contract.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* The template asked for - or a flash and back. */
function contractTemplatePostRow($id) {
    $row = contractTemplateRow($id);
    if (!$row) {
        flashAlert("That contract template is gone - it may have been deleted.", 'error');
        redirect();
    }
    return $row;
}

if (isset($_POST['add_contract_template']) || isset($_POST['edit_contract_template'])) {

    validateCSRFToken();

    $row = isset($_POST['edit_contract_template']) ? contractTemplatePostRow($_POST['contract_template_id'] ?? 0) : null;
    [$fields, $error] = contractFromInput($_POST, 'template');
    if ($error !== '') {
        flashAlert(escapeHtml($error) . ' The template was not saved.', 'error');
        redirect();
    }

    $set = contractTermsSetSql($fields, 'contract_template') . ", contract_template_description = " . ($fields['description'] !== '' ? "'" . escapeSql($fields['description']) . "'" : 'NULL');
    $name = escapeSql($fields['name']);
    if ($row) {
        $contract_template_id = intval($row['contract_template_id']);
        mysqli_query($mysqli, "UPDATE contract_templates SET $set WHERE contract_template_id = $contract_template_id");
        logAudit("Contract Template", "Edit", "$session_name changed contract template $name", 0, $contract_template_id);
        flashAlert("Contract template <strong>" . escapeHtml($fields['name']) . "</strong> saved");
    } else {
        mysqli_query($mysqli, "INSERT INTO contract_templates SET $set");
        $contract_template_id = mysqli_insert_id($mysqli);
        logAudit("Contract Template", "Create", "$session_name created contract template $name (" . escapeSql(contractTypeLabel($fields['type'])) . ")", 0, $contract_template_id);
        flashAlert("Contract template <strong>" . escapeHtml($fields['name']) . "</strong> created");
    }

    redirect();

}

if (isset($_GET['copy_contract_template'])) {

    validateCSRFToken();

    $row = contractTemplatePostRow($_GET['copy_contract_template']);
    $contract_template_id = intval($row['contract_template_id']);

    // "Name (copy)", then "Name (copy 2)" and on - a name not already taken
    $base = mb_substr($row['contract_template_name'], 0, 240);
    $new_name = "$base (copy)";
    for ($n = 2; mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM contract_templates WHERE contract_template_name = '" . escapeSql($new_name) . "'")); $n++) {
        $new_name = "$base (copy $n)";
    }

    $columns = ['description', 'type', 'rate_standard', 'rate_after_hours', 'net_terms', 'support_hours', 'details',
        'term_months', 'auto_renew', 'notice_days', 'sla_low_id', 'sla_medium_id', 'sla_high_id', 'sla_urgent_id'];
    if (contractHoursReady()) {
        array_push($columns, 'monthly_hours', 'hours_alert');
    }
    $columns = implode(', ', array_map(function ($column) { return "contract_template_$column"; }, $columns));
    mysqli_query($mysqli, "INSERT INTO contract_templates (contract_template_name, $columns)
        SELECT '" . escapeSql($new_name) . "', $columns FROM contract_templates WHERE contract_template_id = $contract_template_id");
    $new_id = mysqli_insert_id($mysqli);

    logAudit("Contract Template", "Create", "$session_name copied contract template " . escapeSql($row['contract_template_name']) . " as " . escapeSql($new_name), 0, $new_id);
    flashAlert("Contract template <strong>" . escapeHtml($row['contract_template_name']) . "</strong> copied as <strong>" . escapeHtml($new_name) . "</strong>");

    redirect();

}

if (isset($_GET['archive_contract_template']) || isset($_GET['restore_contract_template'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_contract_template']);
    $row = contractTemplatePostRow($archive ? $_GET['archive_contract_template'] : $_GET['restore_contract_template']);
    $contract_template_id = intval($row['contract_template_id']);

    // Archived, it isn't offered on a new contract; contracts started from it are unchanged
    mysqli_query($mysqli, "UPDATE contract_templates SET contract_template_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE contract_template_id = $contract_template_id");

    logAudit("Contract Template", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " contract template " . escapeSql($row['contract_template_name']), 0, $contract_template_id);
    flashAlert("Contract template <strong>" . escapeHtml($row['contract_template_name']) . "</strong> " . ($archive ? 'archived' : 'restored'), $archive ? 'error' : 'success');

    redirect();

}

if (isset($_GET['delete_contract_template'])) {

    validateCSRFToken();

    $row = contractTemplatePostRow($_GET['delete_contract_template']);
    $contract_template_id = intval($row['contract_template_id']);

    // Contracts started from it keep their terms - only the link back goes
    mysqli_query($mysqli, "UPDATE contracts SET contract_template_id = 0 WHERE contract_template_id = $contract_template_id");
    mysqli_query($mysqli, "DELETE FROM contract_templates WHERE contract_template_id = $contract_template_id");

    logAudit("Contract Template", "Delete", "$session_name deleted contract template " . escapeSql($row['contract_template_name']), 0, $contract_template_id);
    flashAlert("Contract template <strong>" . escapeHtml($row['contract_template_name']) . "</strong> deleted", 'error');

    redirect();

}
