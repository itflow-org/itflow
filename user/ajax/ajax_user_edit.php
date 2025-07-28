<?php

require_once '../includes/ajax_header.php';

$user_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM users 
    LEFT JOIN user_settings ON users.user_id = user_settings.user_id
    WHERE users.user_id = $user_id LIMIT 1"
);

$row = mysqli_fetch_array($sql);
$user_name = nullable_htmlentities($row['user_name']);
$user_email = nullable_htmlentities($row['user_email']);
$user_avatar = nullable_htmlentities($row['user_avatar']);
$user_token = nullable_htmlentities($row['user_token']);
$user_config_force_mfa = intval($row['user_config_force_mfa']);
$user_role_id = intval($row['user_role_id']);
$user_initials = nullable_htmlentities(initials($user_name));

// Get User Client Access Permissions
$user_client_access_sql = mysqli_query($mysqli,"SELECT client_id FROM user_client_permissions WHERE user_id = $user_id");
$client_access_array = [];
while ($row = mysqli_fetch_assoc($user_client_access_sql)) {
    $client_access_array[] = intval($row['client_id']);
}

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-edit mr-2"></i>Editing user:
        <strong><?php echo $user_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
    <div class="modal-body bg-white">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-user-details<?php echo $user_id; ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-user-access<?php echo $user_id; ?>">Restrict Access</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-user-details<?php echo $user_id; ?>">

                <center class="mb-3">
                    <?php if (!empty($user_avatar)) { ?>
                        <img class="img-fluid" src="<?php echo "uploads/users/$user_id/$user_avatar"; ?>">
                    <?php } else { ?>
                        <span class="fa-stack fa-4x">
                            <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                            <span class="fa fa-stack-1x text-white"><?php echo $user_initials; ?></span>
                        </span>
                    <?php } ?>
                </center>

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Full Name" maxlength="200"
                               value="<?php echo $user_name; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="email" placeholder="Email Address" maxlength="200"
                               value="<?php echo $user_email; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                        </div>
                        <input type="password" class="form-control" data-toggle="password" name="new_password"
                               placeholder="Leave Blank For No Password Change" autocomplete="new-password">
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

                <div class="form-group">
                    <label>Avatar</label>
                    <input type="file" class="form-control-file" accept="image/*" name="file">
                </div>

                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" type="checkbox" id="forceMFACheckBox<?php echo $user_id; ?>" name="force_mfa" value="1" <?php if($user_config_force_mfa == 1){ echo "checked"; } ?>>
                        <label for="forceMFACheckBox<?php echo $user_id; ?>" class="custom-control-label">
                            Force MFA
                        </label>
                    </div>
                </div>

                <?php if (!empty($user_token)) { ?>

                    <div class="form-group">
                        <label>2FA</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-id-card"></i></span>
                            </div>
                            <select class="form-control" name="2fa">
                                <option value="">Keep enabled</option>
                                <option value="disable">Disable</option>
                            </select>
                        </div>
                    </div>

                <?php } ?>
            </div>

            <div class="tab-pane fade" id="pills-user-access<?php echo $user_id; ?>">

                <div class="alert alert-info">
                    Check boxes to authorize user client access. No boxes grant full client access. Admin users are unaffected.
                </div>

                <ul class="list-group">
                    <li class="list-group-item bg-dark">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" onclick="this.closest('.tab-pane').querySelectorAll('.client-checkbox').forEach(checkbox => checkbox.checked = this.checked);">
                            <label class="form-check-label ml-3"><strong>Restrict Access to Clients</strong></label>
                        </div>
                    </li>

                    <?php

                    $sql_client_select = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_array($sql_client_select)) {
                        $client_id_select = intval($row['client_id']);
                        $client_name_select = nullable_htmlentities($row['client_name']);

                    ?>

                    <li class="list-group-item">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input client-checkbox" name="clients[]" value="<?php echo $client_id_select; ?>" <?php if (in_array($client_id_select, $client_access_array)) { echo "checked"; } ?>>
                            <label class="form-check-label ml-2"><?php echo $client_name_select; ?></label>
                        </div>
                    </li>

                    <?php } ?>

                </ul>

            </div>

        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_user" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
