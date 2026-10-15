<?php

/*
 * Edit Service - what it is, how important, how it comes back, when it is
 * next reviewed. What it depends on is linked and unlinked on its page.
 */

require_once '../../../includes/modal_header.php';

$form = serviceForModal($_GET['id'] ?? 0, 2, true);
$service_id = intval($form['service_id']);
$form_client_id = intval($form['service_client_id']);
$is_new = false;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-stream me-2"></i>Edit <strong><?= escapeHtml($form['service_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="service_id" value="<?= $service_id ?>">

    <div class="modal-body">
        <?php require 'service_form.php'; ?>
    </div>
    <div class="modal-footer">
        <a class="me-auto small" href="<?= escapeHtml(serviceUrl($service_id, $form_client_id)) ?>"><i class="fas fa-link me-1"></i>Link what it depends on from its page</a>
        <button type="submit" name="edit_service" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
