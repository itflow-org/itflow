<?php

/*
 * Edit a contract. The fields are contract_form.php; making a draft active,
 * renewing and cancelling have windows of their own.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_sales', 2);

$form_contract = contractRow(intval($_GET['id'] ?? 0));
$form_copy = null;
$form_client_id = $form_contract ? intval($form_contract['contract_client_id']) : 0;
if ($form_client_id) {
    $client_id = $form_client_id;
    enforceClientAccess();
}

ob_start();

if (!$form_contract) { ?>
    <div class="modal-body text-center text-secondary py-5">That contract is gone - it may have been deleted.</div>
<?php } else { ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-file-contract me-2"></i>Contract: <strong><?= escapeHtml($form_contract['contract_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contract_id" value="<?= intval($form_contract['contract_id']) ?>">
    <div class="modal-body">
        <?php require __DIR__ . '/contract_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_contract" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save changes</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
