<?php

/*
 * One key on a license: change it, and hand it to the devices and users using
 * it. Handing it to someone not yet on the license gives them a seat.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$software_key_id = intval($_GET['id'] ?? 0);
$key_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT software_key, software_key_software_id FROM software_keys WHERE software_key_id = $software_key_id"));
$row = $key_row ? softwareRow($key_row['software_key_software_id']) : null;
if (!$row) {
    echo json_encode(['error' => 'That key no longer exists.']);
    exit;
}
$client_id = intval($row['software_client_id']);
enforceClientAccess();

$software_id = intval($row['software_id']);
$assigned_assets = $assigned_contacts = [];
$sql = mysqli_query($mysqli, "SELECT asset_id FROM software_key_asset_assignments WHERE software_key_id = $software_key_id");
while ($assigned = mysqli_fetch_row($sql)) {
    $assigned_assets[] = intval($assigned[0]);
}
$sql = mysqli_query($mysqli, "SELECT contact_id FROM software_key_contact_assignments WHERE software_key_id = $software_key_id");
while ($assigned = mysqli_fetch_row($sql)) {
    $assigned_contacts[] = intval($assigned[0]);
}

// Who is on the license already - listed first, so the usual pick is at the top
$holders = softwareSeatHolders($software_id);
$seat_assets = array_map('intval', array_column($holders['assets'], 'asset_id'));
$seat_contacts = array_map('intval', array_column($holders['contacts'], 'contact_id'));

$asset_options = [];
$sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model FROM assets
    WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
while ($option = mysqli_fetch_assoc($sql)) {
    $option['on_license'] = in_array(intval($option['asset_id']), $seat_assets, true);
    $asset_options[] = $option;
}
$contact_options = [];
$sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_photo, contact_client_id FROM contacts
    WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_name ASC");
while ($option = mysqli_fetch_assoc($sql)) {
    $option['on_license'] = in_array(intval($option['contact_id']), $seat_contacts, true);
    $contact_options[] = $option;
}
$on_license_first = function ($a, $b) {
    return intval($b['on_license']) - intval($a['on_license']);
};
usort($asset_options, $on_license_first);
usort($contact_options, $on_license_first);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-key me-2"></i>Key on <strong><?= escapeHtml($row['software_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="software_key_id" value="<?= $software_key_id ?>">
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Key <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-key"></i></span>
                <input type="text" class="form-control font-monospace" name="key" maxlength="400" value="<?= escapeHtml($key_row['software_key']) ?>" required>
                <button type="button" class="btn btn-outline-secondary clipboardjs" title="Copy key" data-clipboard-text="<?= escapeHtml($key_row['software_key']) ?>"><i class="far fa-copy"></i></button>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Installed on</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-desktop"></i></span>
                <select class="form-select select2" name="assets[]" multiple data-placeholder="- Nothing yet -">
                    <?php foreach ($asset_options as $option) {
                        $option_id = intval($option['asset_id']); ?>
                        <option value="<?= $option_id ?>" <?= in_array($option_id, $assigned_assets, true) ? 'selected' : '' ?><?= selectOptionAsset($option['asset_type'], $option['on_license'] ? 'On this license' : 'Not on this license yet - gets a seat') ?>><?= escapeHtml($option['asset_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Used by</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                <select class="form-select select2" name="contacts[]" multiple data-placeholder="- Nobody yet -">
                    <?php foreach ($contact_options as $option) {
                        $option_id = intval($option['contact_id']); ?>
                        <option value="<?= $option_id ?>" <?= in_array($option_id, $assigned_contacts, true) ? 'selected' : '' ?><?= selectOptionContact($option['contact_client_id'], $option['contact_name'], $option['contact_photo'], $option['on_license'] ? 'On this license' : 'Not on this license yet - gets a seat', $option_id) ?>><?= escapeHtml($option['contact_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-text">Leave both empty to put the key back in the pool.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_software_key" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
