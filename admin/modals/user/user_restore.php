<?php

require_once '../../includes/modal_header.php';

$user_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT user_avatar, user_email, user_name, user_role_id FROM users WHERE user_id = $user_id AND user_archived_at IS NOT NULL LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$user_name = str_replace(" (archived)", "", $row['user_name']); //Removed (archived) from user_name
$user_name = escapeHtml($user_name);
$user_email = escapeHtml($row['user_email']);
$user_avatar = escapeHtml($row['user_avatar']);
$user_initials = initials($user_name);
$user_role_id = intval($row['user_role_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-redo-alt me-2"></i>Restoring user:
        <strong><?= $user_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?= $user_id ?>">
    <div class="modal-body">


        <center class="mb-3">
            <?php if (!empty($user_avatar)) { ?>
                <img class="img-fluid" src="<?= "../uploads/users/$user_id/$user_avatar" ?>">
            <?php } else { ?>
                <span class="fa-stack fa-4x">
                    <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                    <span class="fa fa-stack-1x text-white"><?= $user_initials ?></span>
                </span>
            <?php } ?>
        </center>

        <div class="mb-3">
            <label>Set a New Password</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                <input type="password" class="form-control" data-toggle="password" name="new_password"
                       placeholder="Enter a new password" autocomplete="new-password" required>
                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
            </div>
        </div>

        <div class="mb-3">
            <label>Role <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                <select class="form-select select2" name="role" required>
                    <?php
                    $sql_user_roles = mysqli_query($mysqli, "SELECT role_id, role_name FROM user_roles WHERE role_archived_at IS NULL");
                    while ($row = mysqli_fetch_assoc($sql_user_roles)) {
                        $role_id = intval($row['role_id']);
                        $role_name = escapeHtml($row['role_name']);

                        ?>
                        <option <?php if ($role_id == $user_role_id) {echo "selected";} ?> value="<?= $role_id ?>"><?= $role_name ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="restore_user" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Restore</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
