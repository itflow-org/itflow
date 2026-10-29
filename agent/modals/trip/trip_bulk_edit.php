<?php

/*
 * Set the driver, client, vehicle or billable of the ticked trips - ?field=
 * driver, client, vehicle or billable. A new client takes the site and
 * ticket off a trip that was another client's. A trip paid back for keeps
 * its driver and vehicle, one billed its client and billable. Posts
 * bulk_edit_trips with field, value and trip_ids[].
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 2);

$trip_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['trip_ids'] ?? [])))));
$count = count($trip_ids);
$field = (string) ($_GET['field'] ?? '');

$fields = [
    'driver' => ['label' => 'Driver', 'icon' => 'fa-user'],
    'client' => ['label' => 'Client', 'icon' => 'fa-building'],
    'vehicle' => ['label' => 'Vehicle', 'icon' => 'fa-car-side'],
    'billable' => ['label' => 'Billable', 'icon' => 'fa-file-invoice-dollar'],
];
if (!isset($fields[$field])) {
    echo json_encode(['error' => 'Pick what to set from the Bulk Action menu.']);
    exit;
}
if (!$count) {
    echo json_encode(['error' => 'Tick the trips to change first.']);
    exit;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw <?= $fields[$field]['icon'] ?> me-2"></i>Set <?= $fields[$field]['label'] ?> <span class="fw-normal text-white-50 ms-1"><?= $count ?> trip<?= $count === 1 ? '' : 's' ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="field" value="<?= $field ?>">
    <?php foreach ($trip_ids as $trip_id) { ?>
        <input type="hidden" name="trip_ids[]" value="<?= $trip_id ?>">
    <?php } ?>
    <div class="modal-body">
        <label class="form-label"><?= $fields[$field]['label'] ?><?= $field === 'driver' ? ' <strong class="text-danger">*</strong>' : '' ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw <?= $fields[$field]['icon'] ?>"></i></span>
            <?php if ($field === 'driver') { ?>
                <select class="form-select select2" name="value" required>
                    <option value="">- Pick who drove -</option>
                    <?php foreach (tripDrivers() as $user_id => $driver) { ?>
                        <option value="<?= $user_id ?>"<?= selectOptionUser($user_id, $driver['user_name'], $driver['user_avatar'] ?? '') ?>><?= escapeHtml($driver['user_name']) ?></option>
                    <?php } ?>
                </select>
            <?php } elseif ($field === 'vehicle') { ?>
                <select class="form-select" name="value">
                    <option value="1">Their own vehicle - paid back for the distance</option>
                    <option value="0">A company vehicle</option>
                </select>
            <?php } elseif ($field === 'billable') { ?>
                <select class="form-select" name="value">
                    <option value="1">Bill the client for them</option>
                    <option value="0">Not billed - our cost</option>
                </select>
            <?php } else { ?>
                <select class="form-select select2" name="value">
                    <option value="0">- Our own - not for a client -</option>
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['client_id']) ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                    <?php } ?>
                </select>
            <?php } ?>
        </div>
        <?php if ($field === 'client') { ?>
            <div class="form-text">A trip that was another client's loses its site and ticket - they were that client's. Trips billed already keep their client.</div>
        <?php } elseif ($field === 'billable') { ?>
            <div class="form-text">Only trips for a client are billed. Trips billed already stay as they are.</div>
        <?php } elseif ($field !== 'client') { ?>
            <div class="form-text">Trips paid back for already stay as they are.</div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_edit_trips" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Set on <?= $count ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
