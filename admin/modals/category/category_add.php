<?php

/*
 * New category - from Admin > Categories (?category= the kind), or the +
 * beside a category picker in a form (which posts to the agent's post.php).
 * The fields are category_form.php.
 */

require_once '../../includes/modal_header.php';

$category = null;
$kind = categoryKind($_GET['category'] ?? '') ? (string) $_GET['category'] : '';
$kind_config = $kind !== '' ? categoryKind($kind) : null;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw <?= $kind_config['icon'] ?? 'fa-list-ul' ?> me-2"></i>New <?= escapeHtml($kind_config ? ucwords($kind_config['one']) : 'Category') ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/category_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_category" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
