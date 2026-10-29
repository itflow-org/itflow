<?php

/*
 * Edit a contract template - contracts already started from it keep their
 * own terms. The fields are contract_template_form.php.
 */

require_once '../../includes/modal_header.php';

$template = contractTemplateRow(intval($_GET['id'] ?? 0));

ob_start();

if (!$template) { ?>
    <div class="modal-body text-center text-secondary py-5">That template is gone - it may have been deleted.</div>
<?php } else { ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-contract me-2"></i>Contract Template: <strong><?= escapeHtml($template['contract_template_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="contract_template_id" value="<?= intval($template['contract_template_id']) ?>">
    <div class="modal-body">
        <?php if (!empty($template['contract_template_archived_at'])) { ?>
            <div class="alert alert-secondary small py-2"><i class="fas fa-fw fa-archive me-1"></i>Archived - it isn't offered on a new contract. Restore it from the list.</div>
        <?php } ?>
        <?php require __DIR__ . '/contract_template_form.php'; ?>
        <small class="text-secondary d-block mt-3">Contracts already started from it keep their own terms.</small>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_contract_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save changes</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
