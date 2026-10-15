<?php

/*
 * New Project - for a client (opened inside one, or picked here) or our own
 * internal work, optionally with a project template's tickets.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$form_client_id = intval($_GET['client_id'] ?? 0);
if ($form_client_id) {
    enforceClientAccess($form_client_id);
}
$form = ['project_manager' => $session_user_id];
if ($form_client_id) {
    $form['fixed_client'] = true;
}
$is_new = true;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-project-diagram me-2"></i>New Project<?php if ($form_client_id) { ?> <small class="ms-1 opacity-75"><?= escapeHtml((string) getFieldById('clients', $form_client_id, 'client_name')) ?></small><?php } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require 'project_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_project" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/project_modal.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
