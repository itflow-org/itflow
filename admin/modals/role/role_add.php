<?php

/*
 * New Role - from nothing, or a copy of another (?copy=role_id) to start
 * from its levels. The fields are role_form.php.
 */

require_once '../../includes/modal_header.php';

$copy_of = isset($_GET['copy']) ? roleRow(intval($_GET['copy'])) : null;
$role = null;
if ($copy_of) {
    $role = $copy_of;
    $role['role_name'] = 'Copy of ' . $copy_of['role_name'];
    // A copy isn't built in
    $role['role_description'] = preg_replace('/^Built-in - /', '', (string) $copy_of['role_description']);
}
$role_admin_blocker = '';

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-shield me-2"></i><?= $copy_of ? 'Copy ' . escapeHtml($copy_of['role_name']) : 'New Role' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php if ($copy_of) { ?>
            <div class="alert alert-secondary small py-2"><i class="fas fa-fw fa-copy me-1"></i>A new role, starting from <?= escapeHtml($copy_of['role_name']) ?>'s access - its agents keep theirs.</div>
        <?php } ?>
        <?php require __DIR__ . '/role_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_role" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create role</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
