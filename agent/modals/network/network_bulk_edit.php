<?php

/*
 * Bulk - the type and site of the networks ticked on the networks list. Each
 * field left on "No change" stays as it is. A site belongs to a client, so it
 * is offered inside one client's networks only.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$network_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['network_ids'] ?? [])))));
$count = count($network_ids);

$client_id = intval($_GET['client_id'] ?? 0);
$locations = [];
if ($client_id) {
    enforceClientAccess();
    $sql = mysqli_query($mysqli, "SELECT location_id, location_name, location_primary FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_primary DESC, location_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $locations[] = $row;
    }
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-edit me-2"></i>Change <strong><?= $count ?></strong> network<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($network_ids as $network_id) { ?><input type="hidden" name="network_ids[]" value="<?= $network_id ?>"><?php } ?>

    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Type</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-shapes"></i></span>
                <select class="form-select select2" name="type">
                    <option value="">- No change -</option>
                    <?php foreach (NETWORK_TYPES as $type => [$icon, $tone, $hint]) { ?>
                        <option value="<?= $type ?>" data-icon="fas <?= $icon ?>" data-color="<?= networkToneColor($tone) ?>" data-description="<?= escapeHtml($hint) ?>"><?= $type ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <?php if ($client_id) { ?>
            <div class="mb-0">
                <label class="form-label">Site</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-map-marker-alt"></i></span>
                    <select class="form-select select2" name="location_id">
                        <option value="">- No change -</option>
                        <option value="0" data-icon="fas fa-times">No site</option>
                        <?php foreach ($locations as $location) { ?>
                            <option value="<?= intval($location['location_id']) ?>" data-icon="fas fa-<?= intval($location['location_primary']) ? 'building' : 'map-marker-alt' ?>"><?= escapeHtml($location['location_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        <?php } else { ?>
            <div class="form-text">To move networks to a site, open the client's networks - sites are per client.</div>
        <?php } ?>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_networks" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Change</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
