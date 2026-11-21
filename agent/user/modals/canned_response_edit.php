<?php

/*
 * Edit a canned response of your own (Your account > Canned Responses) - the
 * fields are includes/canned_response_form.php. Only your own.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$response = cannedResponseRow($_GET['id'] ?? 0);
if ($response && intval($response['canned_response_user_id']) !== intval($session_user_id)) {
    $response = null;
}

ob_start();

if (!$response) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fas fa-fw fa-comment-dots me-2"></i>Canned Response</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That canned response isn't one of yours now - it may have been deleted or shared with everyone.</div>
<?php } else {
    $canned_response_id = intval($response['canned_response_id']);
    $response_category_ids = cannedResponseCategoryIds($canned_response_id); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-comment-dots me-2"></i>Edit Canned Response <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($response['canned_response_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/agent/user/post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="canned_response_id" value="<?= $canned_response_id ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/../../../includes/canned_response_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_your_canned_response" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }

require_once '../../../includes/modal_footer.php';
