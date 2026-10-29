<?php

/* Edit a trip - your own, or anyone's with write access to Financial. */

require_once '../../../includes/modal_header.php';

$form_row = tripRow(intval($_GET['id'] ?? 0));
if (!$form_row) {
    exit(json_encode(['error' => 'That trip no longer exists, or is not one you can see.']));
}
if (!tripCanEdit($form_row)) {
    exit(json_encode(['error' => "That is someone else's trip - changing it needs write access to Financial."]));
}
$form_mode = 'edit';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-route me-2"></i>Edit Trip <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($form_row['trip_date']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="trip_id" value="<?= intval($form_row['trip_id']) ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/trip_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_trip" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
