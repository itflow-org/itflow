<?php

/*
 * Admin > Settings > Company Details, Offices (functions/offices.php):
 * save_office adds or changes an office, and who works there, from its window
 * (admin/modals/office/office.php); remove_office takes one away - those in it
 * go back to the main office.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['save_office'])) {

    validateCSRFToken();

    $new = !intval($_POST['office_id'] ?? 0);
    [$office_id, $error] = officeSave($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect('settings_company.php#section-offices');
    }
    $name = office($office_id)['name'] ?? '';

    logAudit("Settings", $new ? "Create" : "Edit", "$session_name " . ($new ? 'added' : 'changed') . " the office " . escapeSql($name));
    flashAlert("Office <strong>" . escapeHtml($name) . "</strong> " . ($new ? 'added' : 'saved'));
    redirect('settings_company.php#section-offices');

}

if (isset($_GET['remove_office'])) {

    validateCSRFToken();

    $office_id = intval($_GET['remove_office']);
    $name = office($office_id)['name'] ?? '';
    if (!$office_id || !officeRemove($office_id)) {
        flashAlert("That office isn't there any more - it may have been removed", 'error');
        redirect('settings_company.php#section-offices');
    }

    logAudit("Settings", "Delete", "$session_name removed the office " . escapeSql($name));
    flashAlert("Removed the office <strong>" . escapeHtml($name) . "</strong> - those who worked there are in the main office now", 'error');
    redirect('settings_company.php#section-offices');

}
