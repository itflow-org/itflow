<?php

/*
 * New Service - for a client (opened inside one, or picked here). Opened
 * inside a client it can link what the service depends on as it is made.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$form_client_id = intval($_GET['client_id'] ?? 0);
if ($form_client_id) {
    $client_id = $form_client_id;
    enforceClientAccess();
}
$form = ['service_importance' => 'Medium', 'service_review_interval' => 12, 'service_review_due' => serviceNextReview(12)];
$is_new = true;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-stream me-2"></i>New Service<?php if ($form_client_id) { ?> <small class="ms-1 opacity-75"><?= escapeHtml((string) getFieldById('clients', $form_client_id, 'client_name')) ?></small><?php } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require 'service_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_service" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
