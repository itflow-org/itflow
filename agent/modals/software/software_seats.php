<?php

/*
 * Who and what is on a license - the client's devices and users in two
 * pickers, with the seats they take against the seats bought as you pick.
 * Taking someone off gives back any key they had.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$software_id = intval($_GET['id'] ?? 0);
$row = softwareRow($software_id);
if (!$row) {
    echo json_encode(['error' => 'That license no longer exists.']);
    exit;
}
$client_id = intval($row['software_client_id']);
enforceClientAccess();

$seats_total = intval($row['software_seats']);
$holders = softwareSeatHolders($software_id);
$asset_ids = array_map('intval', array_column($holders['assets'], 'asset_id'));
$contact_ids = array_map('intval', array_column($holders['contacts'], 'contact_id'));

$asset_options = [];
$sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, contact_name FROM assets
    LEFT JOIN contacts ON contact_id = asset_contact_id
    WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");
while ($option = mysqli_fetch_assoc($sql)) {
    $asset_options[] = $option;
}
$contact_options = [];
$sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_photo, contact_client_id FROM contacts
    WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_name ASC");
while ($option = mysqli_fetch_assoc($sql)) {
    $contact_options[] = $option;
}

// Start on users for a per-user license, devices otherwise
$users_first = in_array($row['software_license_type'], ['User', 'Subscription', 'Concurrent'], true);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-users me-2"></i>Seats on <strong><?= escapeHtml($row['software_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-seats-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="software_id" value="<?= $software_id ?>">

    <div class="modal-body">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <span class="text-body-secondary small">Each device and user here takes a seat.<?= $seats_total ? '' : ' This license has no seat limit.' ?></span>
            <span class="badge rounded-pill fs-6 fw-normal" data-seat-count></span>
        </div>
        <?php
        $pickers = [
            'contacts' => ['label' => 'Users', 'icon' => 'fa-user', 'placeholder' => $contact_options ? 'Pick the people licensed to use it' : 'This client has no contacts yet'],
            'assets' => ['label' => 'Devices', 'icon' => 'fa-desktop', 'placeholder' => $asset_options ? 'Pick the devices it is installed on' : 'This client has no assets yet'],
        ];
        if (!$users_first) {
            $pickers = array_reverse($pickers, true);
        }
        foreach ($pickers as $field => $picker) { ?>
            <div class="mb-3">
                <label class="form-label"><?= $picker['label'] ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw <?= $picker['icon'] ?>"></i></span>
                    <select class="form-select select2" name="<?= $field ?>[]" multiple data-placeholder="<?= $picker['placeholder'] ?>" data-seat-pick>
                        <?php if ($field === 'assets') {
                            foreach ($asset_options as $option) {
                                $option_id = intval($option['asset_id']); ?>
                                <option value="<?= $option_id ?>" <?= in_array($option_id, $asset_ids, true) ? 'selected' : '' ?><?= selectOptionAsset($option['asset_type'], implode(' · ', array_filter([trim($option['asset_make'] . ' ' . $option['asset_model']), $option['contact_name']]))) ?>><?= escapeHtml($option['asset_name']) ?></option>
                            <?php }
                        } else {
                            foreach ($contact_options as $option) {
                                $option_id = intval($option['contact_id']); ?>
                                <option value="<?= $option_id ?>" <?= in_array($option_id, $contact_ids, true) ? 'selected' : '' ?><?= selectOptionContact($option['contact_client_id'], $option['contact_name'], $option['contact_photo'], $option['contact_title'], $option_id) ?>><?= escapeHtml($option['contact_name']) ?></option>
                            <?php }
                        } ?>
                    </select>
                </div>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="set_software_seats" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    (function () {
        var form = document.querySelector('form[data-seats-form]:not([data-wired])');
        if (!form) {
            return;
        }
        form.dataset.wired = '1';
        var bought = <?= $seats_total ?>;
        var picks = form.querySelectorAll('[data-seat-pick]');
        var count = form.querySelector('[data-seat-count]');
        function seats() {
            var used = 0;
            picks.forEach(function (select) {
                used += select.selectedOptions.length;
            });
            var over = bought && used > bought;
            count.textContent = (bought ? used + ' of ' + bought + ' seats' : used + ' in use') + (over ? ' - ' + (used - bought) + ' over' : '');
            count.className = 'badge rounded-pill fs-6 fw-normal ' + (over ? 'text-bg-danger' : (bought && used === bought ? 'text-bg-warning' : 'text-bg-secondary'));
        }
        picks.forEach(function (select) {
            select.addEventListener('change', seats);
            select.addEventListener('input', seats);
        });
        seats();
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
