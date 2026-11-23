<?php

/*
 * Link a device from an RMM to another of its client's assets, by hand (rmmDeviceLinkAsset() in
 * functions/rmm.php) - from the menu on its card on the asset page, for a device matched to the
 * wrong asset or brought in as a new one beside the asset you already had. The asset it has now
 * can be archived as it goes - ticked when the sync made it for this device.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$device = rmmDeviceById(intval($_GET['device_id'] ?? 0));
if (!$device || $device['rmm_device_removed_at'] !== null) {
    echo json_encode(['error' => 'That device is no longer in its RMM.']);
    exit;
}
$client_id = intval($device['rmm_device_client_id']);

enforceClientAccess();

$provider_name = rmmProviderName($device);
$host = (string) $device['rmm_device_hostname'];
$current_id = intval($device['rmm_device_asset_id']);
$current = $current_id ? rmmAssetCurrent($current_id) : null;
$current_serial = $current ? mb_strtolower(trim($current['serial'])) : '';
// Made by the sync for this device - the one to archive once the device has the asset it is
$current_made = $current_id && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM asset_history WHERE asset_history_asset_id = $current_id
    AND asset_history_description LIKE '" . escapeSql(str_replace(['%', '_'], ['\%', '\_'], $provider_name)) . " brought it in from %' LIMIT 1")) > 0;

// The most likely ones first - the same name or serial number as the asset it has
$targets = rmmDeviceLinkTargets($device);
uasort($targets, function ($a, $b) use ($current, $current_serial) {
    $likely = function ($row) use ($current, $current_serial) {
        return $current && (strcasecmp(trim($row['asset_name']), trim($current['name'])) === 0
            || ($current_serial !== '' && mb_strtolower(trim((string) $row['asset_serial'])) === $current_serial));
    };
    return [$likely($b), $a['taken'] !== null] <=> [$likely($a), $b['taken'] !== null] ?: strcasecmp($a['asset_name'], $b['asset_name']);
});

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-link me-2"></i>Link <strong><?= escapeHtml($host) ?></strong> to another asset</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="device_id" value="<?= intval($device['rmm_device_id']) ?>">
    <div class="modal-body">

        <p class="mb-3">
            <?= escapeHtml($provider_name) ?>'s <strong><?= escapeHtml($host) ?></strong><?= $current ? ' is linked to <strong>' . escapeHtml($current['name']) . '</strong>' : '' ?>.
            Pick the asset it is - that asset keeps its name and what you've filled in, and gets what it's missing from <?= escapeHtml($provider_name) ?>.
        </p>

        <div class="mb-3">
            <label class="form-label">Asset <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-desktop"></i></span>
                <select class="form-select select2" name="asset_id" required>
                    <option value="">- Select an asset -</option>
                    <?php foreach ($targets as $target_id => $target) {
                        $taken = $target['taken'] !== null;
                        $about = $taken ? "Already $target[taken] in $provider_name" : trim(trim($target['asset_make'] . ' ' . $target['asset_model']) . ($target['asset_serial'] !== '' ? ' - ' . $target['asset_serial'] : ''), ' -'); ?>
                        <option value="<?= $target_id ?>" <?= $taken ? 'disabled' : '' ?><?= selectOptionAsset($target['asset_type'], $about) ?>><?= escapeHtml($target['asset_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <?php if ($current) { ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="archive_old" value="1" id="rmmLinkArchive" <?= $current_made ? 'checked' : '' ?>>
                <label class="form-check-label" for="rmmLinkArchive">Archive <strong><?= escapeHtml($current['name']) ?></strong><?= $current_made ? ' - ' . escapeHtml($provider_name) . ' made it for this device' : '' ?></label>
            </div>
        <?php } ?>

    </div>

    <div class="modal-footer">
        <button type="submit" name="rmm_link_asset" class="btn btn-primary fw-bold"><i class="fas fa-link me-2"></i>Link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
