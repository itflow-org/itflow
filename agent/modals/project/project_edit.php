<?php

/*
 * Edit Project - its name, scope, dates, budget, manager and client
 * contact. Its client stays: its tickets are that client's.
 */

require_once '../../../includes/modal_header.php';

$form = projectForModal($_GET['id'] ?? 0, 2);
if (!empty($form['project_archived_at'])) {
    echo json_encode(['error' => 'Project ' . projectReference($form) . ' is archived - restore it to change it.']);
    exit;
}
$project_id = intval($form['project_id']);
$form_client_id = intval($form['project_client_id']);
$form['fixed_client'] = true;
$is_new = false;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate">
        <i class="fas fa-fw fa-project-diagram me-2"></i>Edit Project <small class="ms-1 opacity-75"><?= escapeHtml(projectReference($form)) ?> &middot; <?= $form_client_id ? escapeHtml($form['client_name']) : 'Internal' ?></small>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="project_id" value="<?= $project_id ?>">
    <div class="modal-body">
        <?php require 'project_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_project" class="btn btn-primary text-bold">
            <i class="fas fa-check me-2"></i>Save
        </button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
            <i class="fa fa-times me-2"></i>Cancel
        </button>
    </div>
</form>

<script src="/agent/js/project_modal.js"></script>

<?php

require_once '../../../includes/modal_footer.php';
