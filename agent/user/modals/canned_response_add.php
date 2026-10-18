<?php

/*
 * New canned response of your own (Your account > Canned Responses) - the
 * fields are includes/canned_response_form.php. ?category starts it on a
 * ticket category.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$response = [];
$response_category_ids = array_filter([intval($_GET['category'] ?? 0)]);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-comment-dots me-2"></i>New Canned Response <span class="small fw-normal text-white-50 ms-1">yours</span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/agent/user/post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="small text-body-secondary mb-3"><i class="fas fa-fw fa-user me-1"></i>Offered to you alone, first in the picker above a reply.</div>
        <?php require __DIR__ . '/../../../includes/canned_response_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_your_canned_response" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create Canned Response</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
