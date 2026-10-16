<?php

/* Delete the ticked trips. */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_financial', 3);

$trip_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['trip_ids'] ?? [])))));
$count = count($trip_ids);
if (!$count) {
    echo json_encode(['error' => 'Tick the trips to delete first.']);
    exit;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-trash me-2"></i>Delete <?= $count ?> Trip<?= $count === 1 ? '' : 's' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($trip_ids as $trip_id) { ?>
        <input type="hidden" name="trip_ids[]" value="<?= $trip_id ?>">
    <?php } ?>
    <div class="modal-body">
        <div class="d-flex gap-3 align-items-start">
            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
            <div>
                <div class="fw-semibold"><?= $count === 1 ? 'This trip goes' : "These $count trips go" ?> for good.</div>
                <div class="text-body-secondary small mt-1">Their miles come out of the Mileage report and off their tickets and sites. This can't be undone.</div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="bulk_delete_trips" class="btn btn-danger text-bold"><i class="fas fa-trash me-2"></i>Delete <?= $count ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
