<?php

/*
 * Move people, assets, networks or racks to a location (?id=&kind=asset).
 * Pills switch between the kinds this agent may move; each lists the client's
 * own records that are somewhere else, with where they are now, so a whole
 * floor can be moved in one go after an office move.
 */

require_once '../../../includes/modal_header.php';

$location_id = intval($_GET['id'] ?? 0);
$location = locationRow($location_id);
if (!$location) {
    echo json_encode(['error' => 'That location no longer exists.']);
    exit;
}
$client_id = intval($location['location_client_id']);
enforceClientAccess();

// Only the kinds this agent may move
$kinds = array_filter(LOCATION_KINDS, function ($k) {
    return lookupUserPermission($k['module']) >= 2;
});
if (!$kinds) {
    echo json_encode(['error' => 'You do not have permission to move things to a location.']);
    exit;
}
$kind = isset($kinds[$_GET['kind'] ?? '']) ? $_GET['kind'] : array_key_first($kinds);

/** The client's records of one kind that are not at this location, with where they are now. */
function locationAssignCandidates($kind, $client_id, $location_id) {
    global $mysqli;
    $k = LOCATION_KINDS[$kind];
    $extra = [
        'contact' => 'contact_title AS detail, contact_photo, contact_client_id',
        'asset' => "asset_type, TRIM(CONCAT(IFNULL(asset_make, ''), ' ', IFNULL(asset_model, ''))) AS detail",
        'network' => "CONCAT(network, IF(network_vlan > 0, CONCAT(' · VLAN ', network_vlan), '')) AS detail",
        'rack' => "CONCAT(rack_units, 'U') AS detail",
    ][$kind];
    $sql = mysqli_query($mysqli, "SELECT t.{$k['id']} AS id, t.{$k['name']} AS name, $extra, l.location_name AS current_location
        FROM {$k['table']} AS t
        LEFT JOIN locations AS l ON l.location_id = t.{$k['location']}
        WHERE t.{$k['client']} = $client_id AND t.{$k['archived']} IS NULL
        AND (t.{$k['location']} IS NULL OR t.{$k['location']} != $location_id)
        ORDER BY t.{$k['name']} ASC");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

$location_name = escapeHtml($location['location_name']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-dolly me-2"></i>Move to <strong><?= $location_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-location-assign-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="location_id" value="<?= $location_id ?>">
    <input type="hidden" name="kind" value="<?= $kind ?>" data-assign-kind>

    <div class="modal-body">

        <?php if (count($kinds) > 1) { ?>
            <nav class="nav nav-pills itflow-asset-pills itflow-link-types mb-3" role="tablist">
                <?php foreach ($kinds as $kind_key => $k) { ?>
                    <button type="button" class="nav-link <?= $kind_key === $kind ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#locationAssign-<?= $kind_key ?>" data-assign-type="<?= $kind_key ?>" role="tab">
                        <i class="fas fa-fw <?= $k['icon'] ?> me-1"></i><?= $k['plural'] ?>
                    </button>
                <?php } ?>
            </nav>
        <?php } ?>

        <div class="tab-content">
            <?php foreach ($kinds as $kind_key => $k) {
                $candidates = locationAssignCandidates($kind_key, $client_id, $location_id); ?>
                <div class="tab-pane fade <?= $kind_key === $kind ? 'show active' : '' ?>" id="locationAssign-<?= $kind_key ?>" role="tabpanel">
                    <?php if ($candidates) { ?>
                        <label class="form-label">Which <?= strtolower($k['plural']) ?>?</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw <?= $k['icon'] ?>"></i></span>
                            <select class="form-select select2" name="ids[]" multiple data-placeholder="Pick one or more <?= strtolower($k['plural']) ?>" <?= $kind_key === $kind ? '' : 'disabled' ?> data-assign-select="<?= $kind_key ?>">
                                <?php foreach ($candidates as $row) {
                                    $where = $row['current_location'] ? 'At ' . $row['current_location'] : 'No location';
                                    $description = implode(' · ', array_filter([$row['detail'], $where]));
                                    if ($kind_key === 'contact') {
                                        $data = selectOptionContact($row['contact_client_id'], $row['name'], $row['contact_photo'], $description, $row['id']);
                                    } elseif ($kind_key === 'asset') {
                                        $data = selectOptionAsset($row['asset_type'], $description);
                                    } else {
                                        $data = selectOptionData(['icon' => 'fas ' . $k['icon'], 'description' => $description]);
                                    } ?>
                                    <option value="<?= intval($row['id']) ?>"<?= $data ?>><?= escapeHtml($row['name']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-text"><?= count($candidates) ?> <?= count($candidates) === 1 ? $k['label'] : strtolower($k['plural']) ?> somewhere else or at no location.</div>
                    <?php } else { ?>
                        <div class="itflow-asset-empty py-4">
                            <i class="fas <?= $k['icon'] ?> fa-2x mb-2"></i>
                            <div>Every <?= $k['label'] ?> this client has is already here.</div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="set_location_records" class="btn btn-primary text-bold"><i class="fas fa-dolly me-2"></i>Move here</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    (function () {
        // Only the pane on show posts its picks
        var form = document.querySelector('form[data-location-assign-form]:not([data-wired])');
        if (!form) {
            return;
        }
        form.dataset.wired = '1';
        var kind = form.querySelector('[data-assign-kind]');
        form.querySelectorAll('[data-assign-type]').forEach(function (pill) {
            pill.addEventListener('shown.bs.tab', function () {
                kind.value = pill.dataset.assignType;
                form.querySelectorAll('[data-assign-select]').forEach(function (select) {
                    select.disabled = select.dataset.assignSelect !== kind.value;
                });
            });
        });
        form.addEventListener('submit', function (e) {
            var select = form.querySelector('[data-assign-select="' + kind.value + '"]');
            if (!select || !select.selectedOptions.length) {
                e.preventDefault();
                if (select && select.itflowSelect) {
                    select.itflowSelect.open();
                }
            }
        });
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
