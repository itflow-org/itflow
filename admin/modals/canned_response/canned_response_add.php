<?php

/*
 * New canned response - the fields are canned_response_form.php. ?category
 * starts it on a ticket category.
 */

require_once '../../includes/modal_header.php';

$response = [];
$response_category_ids = array_filter([intval($_GET['category'] ?? 0)]);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-comment-dots me-2"></i>New Canned Response</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/canned_response_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_canned_response" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create Canned Response</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
