<?php

/*
 * Copy a trip - the same route, client and purpose, dated today, driven by
 * you. With ?return=1 it is the Return Trip: from where it went back to where
 * it started, one way, on the same day.
 */

require_once '../../../includes/modal_header.php';

$form_row = tripRow(intval($_GET['id'] ?? 0));
if (!$form_row) {
    exit(json_encode(['error' => 'That trip no longer exists, or is not one you can see.']));
}
$form_mode = !empty($_GET['return']) ? 'return' : 'copy';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-<?= $form_mode === 'return' ? 'undo-alt' : 'copy' ?> me-2"></i><?= $form_mode === 'return' ? 'Return Trip' : 'Copy Trip' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/trip_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_trip" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Log Trip</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
