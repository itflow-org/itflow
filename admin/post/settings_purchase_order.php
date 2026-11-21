<?php

/*
 * Admin > Settings > Purchase orders (admin/settings_purchase_order.php):
 * edit_purchase_order_settings saves the numbering - a next number already
 * in use comes back to the page saying so - and the approval switch and amount.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_purchase_order_settings'])) {

    validateCSRFToken();

    if (!poReady()) {
        flashAlert('Run the database update first - Admin > Update.', 'error');
        redirect();
    }

    $before = settingsCurrent(['config_po_prefix', 'config_po_next_number', 'config_po_approval', 'config_po_approval_over']);

    [$prefix, $number, $errors] = settingsNumberingCheck('purchase_order', $_POST + array_map('strval', $before), $before);
    $over_text = trim(str_replace([',', ' ', '$'], '', (string) ($_POST['config_po_approval_over'] ?? '')));
    if ($over_text !== '' && (!is_numeric($over_text) || floatval($over_text) < 0 || floatval($over_text) > 9999999999)) {
        $errors['config_po_approval_over'] = 'An amount, such as 500 - or empty for every purchase order.';
    }
    if ($errors) {
        settingsRefuse('purchase_order', $errors);
    }

    settingsSave('purchase_order', $before, [
        'config_po_prefix' => $prefix,
        'config_po_next_number' => $number,
        // A switch sends nothing when off
        'config_po_approval' => empty($_POST['config_po_approval']) ? '0' : '1',
        'config_po_approval_over' => $over_text === '' ? '0.00' : number_format(round(floatval($over_text), 2), 2, '.', ''),
    ]);

    redirect();

}
