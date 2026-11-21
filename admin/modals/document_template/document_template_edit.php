<?php

/*
 * Edit a document template - the fields are document_template_form.php.
 */

require_once '../../includes/modal_header.php';

$template = documentTemplateRow($_GET['id'] ?? 0);

ob_start();

if (!$template) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fas fa-fw fa-file-alt me-2"></i>Document Template</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That document template is gone - it may have been deleted.</div>
<?php } else { ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-file-alt me-2"></i>Edit Document Template <span class="fw-normal text-white-50 ms-1"><?= escapeHtml($template['document_template_name']) ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_template_id" value="<?= intval($template['document_template_id']) ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/document_template_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_document_template" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }

require_once '../../../includes/modal_footer.php';
