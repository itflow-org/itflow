<?php

/*
 * New contract - ?client_id= starts it for that client (without one, the
 * client is picked here), and ?copy= starts it as a copy of another
 * contract. The fields are contract_form.php.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$form_contract = null;
$form_copy = null;
$form_client_id = intval($_GET['client_id'] ?? 0);
if (!empty($_GET['copy'])) {
    $form_copy = contractRow(intval($_GET['copy']));
    if ($form_copy) {
        $form_client_id = intval($form_copy['contract_client_id']);
    }
}
if ($form_client_id) {
    $client_id = $form_client_id;
    enforceClientAccess();
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-file-contract me-2"></i><?= $form_copy ? 'Copy Contract' : 'New Contract' ?><?php if ($form_client_id) { ?> <span class="fw-normal text-white-50 ms-1"><?= escapeHtml(getFieldById('clients', $form_client_id, 'client_name')) ?></span><?php } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/contract_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_contract" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
