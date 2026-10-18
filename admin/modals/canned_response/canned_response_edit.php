<?php

/*
 * Edit a canned response - the fields are canned_response_form.php.
 */

require_once '../../includes/modal_header.php';

$response = cannedResponseRow($_GET['id'] ?? 0);

ob_start();

if (!$response) { ?>
    <div class="modal-body text-center text-secondary py-5">That canned response is gone - it may have been deleted.</div>
<?php } else {
    $canned_response_id = intval($response['canned_response_id']);
    $response_category_ids = cannedResponseCategoryIds($canned_response_id); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-comment-dots me-2"></i>Canned response: <strong><?= escapeHtml($response['canned_response_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="canned_response_id" value="<?= $canned_response_id ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/canned_response_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_canned_response" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }

require_once '../../../includes/modal_footer.php';
