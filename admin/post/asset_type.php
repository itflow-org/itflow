<?php

/*
 * ITFlow - Admin > Asset Types: add and change your own types, switch any
 * type off or on again (but Other), and delete one of yours - moving what has
 * it first. ITFlow's own keep their names and settings, whatever a request
 * says. The work is in functions/asset_type_custom.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_asset_type']) || isset($_POST['edit_asset_type'])) {

    validateCSRFToken();

    $row = isset($_POST['edit_asset_type']) ? assetTypeRow($_POST['asset_type_id'] ?? 0) : null;
    if (isset($_POST['edit_asset_type']) && !$row) {
        flashAlert("That type is gone - it may have been deleted.", 'error');
        redirect();
    }

    $fields = assetTypeFromInput($_POST, $row);
    $problem = assetTypeProblem($fields, $row ? $row['asset_type_id'] : 0);
    if ($problem !== '') {
        flashAlert(escapeHtml($problem) . ' The type was not saved.', 'error');
        redirect();
    }

    if ($row) {
        $asset_type_id = intval($row['asset_type_id']);
        $changes = assetTypeUpdate($row, $fields);
        if ($changes) {
            logAudit("Asset Type", "Edit", "$session_name changed asset type " . escapeSql($fields['name']) . ": " . escapeSql(implode('; ', $changes)), 0, $asset_type_id);
        }
        flashAlert("Asset type <strong>" . escapeHtml($fields['name']) . "</strong> saved");
    } else {
        [$asset_type_id, $had] = assetTypeCreate($fields);
        logAudit("Asset Type", "Create", "$session_name added asset type " . escapeSql($fields['name']) . ($fields['details'] ? ' with ' . count($fields['details']) . ' details of its own' : ''), 0, $asset_type_id);
        flashAlert("Asset type <strong>" . escapeHtml($fields['name']) . "</strong> created" . ($had ? " - $had " . ($had === 1 ? 'asset already has' : 'assets already have') . " it" : ''));
    }

    redirect();

}

if (isset($_GET['switch_off_asset_type'])) {

    validateCSRFToken();

    $name = assetTypeName($_GET['switch_off_asset_type']);
    if ($name === 'Other') {
        flashAlert("Other can't be switched off - it is what any type ITFlow doesn't know is shown as.", 'error');
        redirect();
    }
    if ($name !== '' && assetTypes()[$name]['active']) {
        $asset_type_id = assetTypeEnsureRow($name);
        $undo = undoSnapshot('asset_types', 'asset_type_id', [$asset_type_id], ['asset_type_active']);
        assetTypeSetActive($name, false);
        $assets = assetTypeUsage($name)['assets'];
        logAudit("Asset Type", "Edit", "$session_name switched off asset type " . escapeSql($name), 0, $asset_type_id);
        flashAlert("Asset type <strong>" . escapeHtml($name) . "</strong> switched off - it can't be picked" . ($assets ? ", and its $assets " . ($assets === 1 ? 'asset keeps' : 'assets keep') . " it" : ''), 'error');
        undoOfferChanges($undo, '<strong>' . escapeHtml($name) . '</strong> is switched on again', 'admin', 3);
    }

    redirect();

}

if (isset($_GET['switch_on_asset_type'])) {

    validateCSRFToken();

    $name = assetTypeName($_GET['switch_on_asset_type']);
    if ($name !== '' && !assetTypes()[$name]['active']) {
        assetTypeSetActive($name, true);
        logAudit("Asset Type", "Edit", "$session_name switched on asset type " . escapeSql($name), 0, assetTypes()[$name]['id']);
        flashAlert("Asset type <strong>" . escapeHtml($name) . "</strong> switched on - it can be picked again");
    }

    redirect();

}

if (isset($_POST['delete_asset_type'])) {

    validateCSRFToken();

    $row = assetTypeRow($_POST['asset_type_id'] ?? 0);
    if (!$row) {
        flashAlert("That type is gone - it may have been deleted.", 'error');
        redirect();
    }
    $name = $row['asset_type_name'];
    $move_to = assetTypeName($_POST['move_to'] ?? '');
    if ($move_to !== '' && !isset(assetTypeMoveTargets($name)[$move_to])) {
        flashAlert("Its assets can't be moved there. Nothing was changed.", 'error');
        redirect();
    }

    [$ok, $moved] = assetTypeDelete($row, $move_to);
    if (!$ok) {
        flashAlert("Pick a type to move what has <strong>" . escapeHtml($name) . "</strong> to first. Nothing was changed.", 'error');
        redirect();
    }

    logAudit("Asset Type", "Delete", "$session_name deleted asset type " . escapeSql($name) . ($move_to !== '' ? ", moving $moved assets to " . escapeSql($move_to) : ''), 0, intval($row['asset_type_id']));
    flashAlert("Asset type <strong>" . escapeHtml($name) . "</strong> deleted" . ($moved ? " - $moved " . ($moved === 1 ? 'asset' : 'assets') . " moved to <strong>" . escapeHtml($move_to) . "</strong>" : ''), 'error');

    redirect();

}
