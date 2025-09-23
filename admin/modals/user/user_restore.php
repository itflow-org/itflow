<?php

require_once '../../../includes/modal_header.php';

$user_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM users WHERE user_id = $user_id AND user_archived_at IS NOT NULL LIMIT 1");

$row = mysqli_fetch_array($sql);
$user_name = str_replace(" (archived)", "", $row['user_name']); //Removed (archived) from user_name
$user_name = nullable_htmlentities($user_name);
$user_email = nullable_htmlentities($row['user_email']);
$user_avatar = nullable_htmlentities($row['user_avatar']);
$user_initials = initials($user_name);
$user_role_id = intval($row['user_role_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-redo-alt mr-2"></i>Restoring user:
        <strong><?php echo $user_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
    <div class="modal-body">


        <center class="mb-3">
            <?php if (!empty($user_avatar)) { ?>
                <img class="img-fluid" src="<?php echo "../uploads/users/$user_id/$user_avatar"; ?>">
            <?php } else { ?>
                <span class="fa-stack fa-4x">
                    <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                    <span class="fa fa-stack-1x text-white"><?php echo $user_initials; ?></span>
                </span>
            <?php } ?>
        </center>

        <div class="form-group">
            <label>Set a New Password</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                </div>
                <input type="password" class="form-control" data-toggle="password" name="new_password"
                       placeholder="Enter a new password" autocomplete="new-password" required>
                <div class="input-group-append">
                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Role <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                </div>
                <select class="form-control select2" name="role" required>
                    <?php
                    $sql_user_roles = mysqli_query($mysqli, "SELECT * FROM user_roles WHERE role_archived_at IS NULL");
                    while ($row = mysqli_fetch_array($sql_user_roles)) {
                        $role_id = intval($row['role_id']);
                        $role_name = nullable_htmlentities($row['role_name']);

                        ?>
                        <option <?php if ($role_id == $user_role_id) {echo "selected";} ?> value="<?php echo $role_id; ?>"><?php echo $role_name; ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="restore_user" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Restore</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
