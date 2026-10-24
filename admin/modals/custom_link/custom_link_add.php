<?php

/*
 * New Link - opened from Admin > Custom Links, in the menu of the tab it was
 * opened from (?location=). The fields are custom_link_form.php.
 */

require_once '../../includes/modal_header.php';

$custom_link = null;
$default_location = intval($_GET['location'] ?? 1);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-external-link-alt me-2"></i>New Link</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/custom_link_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_custom_link" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add link</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
