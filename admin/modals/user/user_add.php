<?php

/*
 * New User - an agent who signs in to ITFlow. ?role= starts it on that role
 * (the role the users list is filtered to).
 */

require_once '../../includes/modal_header.php';

$is_new = true;
$role_id = intval($_GET['role'] ?? 0);
$form = [
    'user_role_id' => isset(userRoles()[$role_id]) ? $role_id : 0,
    'user_config_force_mfa' => 0,
];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-plus me-2"></i>New User</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php if (agentUserLimitReached()) { ?>
            <div class="alert alert-danger small"><?= agentUserLimitMessage() ?></div>
        <?php } ?>
        <?php require 'user_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_user" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
