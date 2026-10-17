<?php

/*
 * Taxes (Admin > Taxes) - rates, the parts they are made of, and how new
 * lines are taxed. The work is in functions/tax.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_tax'])) {

    validateCSRFToken();

    [$fields, $error] = taxFromInput($_POST);
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }

    $tax_id = taxCreate($fields);
    $label = taxLabel(taxRow($tax_id));
    if (!empty($_POST['make_default']) && !taxSettings()['default_id']) {
        taxSetDefault($tax_id);
    }

    logAudit("Tax", "Create", "$session_name created tax " . escapeSql($label), 0, $tax_id);

    flashAlert("Tax <strong>" . escapeHtml($label) . "</strong> created");

    redirect();

}

if (isset($_POST['edit_tax'])) {

    validateCSRFToken();

    $tax_id = intval($_POST['tax_id'] ?? 0);
    $row = taxRow($tax_id);
    if (!$row) {
        flashAlert("That tax is gone", 'error');
        redirect();
    }

    [$fields, $error] = taxFromInput($_POST);
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }
    if ($row['tax_archived_at'] && !taxSameRate($row, $fields) && taxBilled($tax_id)) {
        flashAlert("An archived tax keeps the rate it was billed at - restore it, or make a new tax", 'error');
        redirect();
    }

    $before = taxLabel($row);
    $move_drafts = empty($_POST['drafts_asked']) || !empty($_POST['move_drafts']);
    $saved = taxUpdate($tax_id, $fields, $move_drafts);
    $after = taxLabel(taxRow($saved['id']));
    $moved = taxMovedText($saved['moved']);

    if ($saved['replaced']) {
        logAudit("Tax", "Edit", "$session_name changed tax " . escapeSql($before) . " to " . escapeSql($after) . " - a new rate, the old one archived", 0, $saved['id']);
        flashAlert("<strong>" . escapeHtml($after) . "</strong> takes over from " . escapeHtml($before) . " from now on" . ($moved !== '' ? " - $moved moved to it" : '') . ". What was already billed keeps the old rate.");
    } else {
        logAudit("Tax", "Edit", "$session_name edited tax " . escapeSql($after), 0, $tax_id);
        flashAlert("Tax <strong>" . escapeHtml($after) . "</strong> saved" . ($moved !== '' ? " - $moved worked out again" : ''));
    }

    redirect();

}

if (isset($_POST['archive_tax'])) {

    validateCSRFToken();

    $tax_id = intval($_POST['tax_id'] ?? 0);
    $row = taxRow($tax_id);
    if (!$row || $row['tax_archived_at']) {
        redirect();
    }

    $move_to = intval($_POST['move_to'] ?? 0);
    $done = taxArchive($tax_id, $move_to);
    $label = taxLabel($row);
    $moved = taxMovedText($done['moved']);
    $to = $done['moved'] ? taxLabel(taxRow($move_to)) : '';

    logAudit("Tax", "Archive", "$session_name archived tax " . escapeSql($label) . ($to !== '' ? ", moving what used it to " . escapeSql($to) : ''), 0, $tax_id);

    flashAlert("Tax <strong>" . escapeHtml($label) . "</strong> archived"
        . ($moved !== '' ? " - $moved moved to " . escapeHtml($to) : '')
        . ($done['was_default'] ? ". There is no default rate now" : ''), 'error');

    redirect();

}

if (isset($_GET['restore_tax'])) {

    validateCSRFToken();

    $tax_id = intval($_GET['restore_tax']);
    $row = taxRow($tax_id);
    if (!$row) {
        redirect();
    }

    taxRestore($tax_id);
    $label = taxLabel($row);

    logAudit("Tax", "Restore", "$session_name restored tax " . escapeSql($label), 0, $tax_id);

    flashAlert("Tax <strong>" . escapeHtml($label) . "</strong> restored");

    redirect();

}

if (isset($_GET['default_tax'])) {

    validateCSRFToken();

    $tax_id = intval($_GET['default_tax']);
    if (!taxSetDefault($tax_id)) {
        flashAlert("Only a current tax can be the default", 'error');
        redirect();
    }
    $label = taxLabel(taxRow($tax_id));

    logAudit("Tax", "Edit", "$session_name made " . escapeSql($label) . " the default tax", 0, $tax_id);

    flashAlert("<strong>" . escapeHtml($label) . "</strong> is the default for new lines");

    redirect();

}

if (isset($_GET['delete_tax'])) {

    validateCSRFToken();

    $tax_id = intval($_GET['delete_tax']);
    $row = taxRow($tax_id);
    if (!$row) {
        redirect();
    }
    $label = taxLabel($row);

    if (!taxDelete($tax_id)) {
        flashAlert("<strong>" . escapeHtml($label) . "</strong> has been used, so it stays - archive it instead", 'error');
        redirect();
    }

    logAudit("Tax", "Delete", "$session_name deleted tax " . escapeSql($label));

    flashAlert("Tax <strong>" . escapeHtml($label) . "</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['edit_tax_settings'])) {

    validateCSRFToken();

    $default_id = intval($_POST['default_tax_id'] ?? 0);
    $time = intval(!empty($_POST['tax_time']));
    $trips = intval(!empty($_POST['tax_trips']));
    $expenses = intval(!empty($_POST['tax_expenses']));
    $discount_before = intval(!empty($_POST['tax_discount_before']));

    if (!taxSetDefault($default_id)) {
        $default_id = taxSettings()['default_id'];
    }
    mysqli_query($mysqli, "UPDATE settings SET config_tax_time = $time, config_tax_trips = $trips, config_tax_expenses = $expenses,
        config_tax_discount_before = $discount_before WHERE company_id = 1");

    logAudit("Settings", "Edit", "$session_name edited the tax settings");

    flashAlert("Tax settings saved");

    redirect();

}
