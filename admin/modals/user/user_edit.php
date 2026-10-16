<?php

/*
 * Edit User - their details, role, password, MFA and the clients they see.
 * Not the admin's own account: that is changed from their Account pages.
 */

require_once '../../includes/modal_header.php';

$form = userForModal($_GET['id'] ?? 0);
$user_id = intval($form['user_id']);
$is_new = false;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-user-edit me-2"></i><?= escapeHtml($form['user_name']) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<?php if ($user_id === intval($session_user_id)) { ?>
    <div class="modal-body">
        <p class="mb-0">That's you - change your own details, password and MFA from <a href="/agent/user/user_details.php">your account</a>, so you can't lock yourself out from here.</p>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
    </div>
<?php } else { ?>
    <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="user_id" value="<?= $user_id ?>">

        <div class="modal-body">
            <?php require 'user_form.php'; ?>
        </div>
        <div class="modal-footer">
            <button type="submit" name="edit_user" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
        </div>
    </form>
<?php } ?>

<?php
require_once "../../../includes/modal_footer.php";
