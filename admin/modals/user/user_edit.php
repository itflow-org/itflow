<?php

require_once '../../includes/modal_header.php';

$user_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT user_avatar, user_config_force_mfa, user_email, user_name, user_role_id, user_token FROM users
    LEFT JOIN user_settings ON users.user_id = user_settings.user_id
    WHERE users.user_id = $user_id LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
$user_name = escapeHtml($row['user_name']);
$user_email = escapeHtml($row['user_email']);
$user_avatar = escapeHtml($row['user_avatar']);
$user_token = escapeHtml($row['user_token']);
$user_config_force_mfa = intval($row['user_config_force_mfa']);
$user_role_id = intval($row['user_role_id']);
$user_initials = escapeHtml(initials($user_name));

// Get User Client Access Permissions (allow + deny)
$user_client_access_sql = mysqli_query($mysqli,"SELECT client_id, permission_type FROM user_client_permissions WHERE user_id = $user_id");
$client_allow_array = []; // allow
$client_deny_array = [];   // deny
while ($row = mysqli_fetch_assoc($user_client_access_sql)) {
    if ($row['permission_type'] === 'deny') {
        $client_deny_array[] = intval($row['client_id']);
    } else {
        $client_allow_array[] = intval($row['client_id']);
    }
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-user-edit me-2"></i>Editing user:
        <strong><?= $user_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="user_id" value="<?= $user_id ?>">
    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-user-details<?= $user_id ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-user-access<?= $user_id ?>">Restrict Access</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-user-details<?= $user_id ?>">

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
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="Full Name" maxlength="200"
                               value="<?= $user_name ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Email <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        <input type="email" class="form-control" name="email" placeholder="Email Address" maxlength="200"
                               value="<?= $user_email ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>New Password</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                        <input type="password" class="form-control" data-toggle="password" name="new_password" id="password"
                               placeholder="Leave Blank For No Password Change" autocomplete="new-password">
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                            <span class="btn btn-default"><i class="fa fa-fw fa-question" onclick="generatePassword()"></i></span>
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

                <div class="mb-3">
                    <label>Avatar</label>
                    <input type="file" class="form-control" accept="image/*" name="file">
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="forceMFACheckBox<?= $user_id ?>" name="force_mfa" value="1" <?php if($user_config_force_mfa == 1){ echo "checked"; } ?>>
                        <label for="forceMFACheckBox<?= $user_id ?>" class="form-check-label">
                            Force MFA
                        </label>
                    </div>
                </div>

                <?php if (!empty($user_token)) { ?>

                    <div class="mb-3">
                        <label>2FA</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-id-card"></i></span>
                            <select class="form-select" name="2fa">
                                <option value="">Keep enabled</option>
                                <option value="disable">Disable</option>
                            </select>
                        </div>
                    </div>

                <?php } ?>
            </div>

            <div class="tab-pane fade" id="pills-user-access<?= $user_id ?>">

                <div class="alert alert-info">
                    <strong>Allow</strong> restricts the user to the selected clients (no Allow = full access). <strong>Deny</strong> blocks a client regardless of Allow. Admin users are unaffected.
                </div>

                <div class="mb-2">
                    <small class="text-muted me-2">Set all:</small>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('#accessTable<?= $user_id ?> .perm-none').forEach(r => r.checked = true);">No Rule</button>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="document.querySelectorAll('#accessTable<?= $user_id ?> .perm-allow').forEach(r => r.checked = true);">Allow</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="document.querySelectorAll('#accessTable<?= $user_id ?> .perm-deny').forEach(r => r.checked = true);">Deny</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="accessTable<?= $user_id ?>">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th class="text-center" style="width: 90px;">No Rule</th>
                                <th class="text-center text-success" style="width: 90px;">Allow</th>
                                <th class="text-center text-danger" style="width: 90px;">Deny</th>
                            </tr>
                        </thead>
                        <tbody>

                        <?php
                        $sql_client_select = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_client_select)) {
                            $client_id_select = intval($row['client_id']);
                            $client_name_select = escapeHtml($row['client_name']);
                            $client_is_allow = in_array($client_id_select, $client_allow_array);
                            $client_is_deny = in_array($client_id_select, $client_deny_array);
                        ?>

                            <tr>
                                <td class="align-middle"><?= $client_name_select ?></td>
                                <td class="text-center align-middle">
                                    <input type="radio" class="form-check-input perm-none" name="client_permission[<?= $client_id_select ?>]" value="" <?php if (!$client_is_allow && !$client_is_deny) { echo "checked"; } ?>>
                                </td>
                                <td class="text-center align-middle">
                                    <input type="radio" class="form-check-input perm-allow" name="client_permission[<?= $client_id_select ?>]" value="allow" <?php if ($client_is_allow) { echo "checked"; } ?>>
                                </td>
                                <td class="text-center align-middle">
                                    <input type="radio" class="form-check-input perm-deny" name="client_permission[<?= $client_id_select ?>]" value="deny" <?php if ($client_is_deny) { echo "checked"; } ?>>
                                </td>
                            </tr>

                        <?php } ?>

                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_user" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>

function generatePassword() {
    // Send a GET request to ajax.php as ajax.php?get_readable_pass=true
    itflowGet(
        "/agent/ajax.php", {
            get_readable_pass: 'true'
        },
        function(data) {
            //If we get a response from post.php, parse it as JSON
            const password = JSON.parse(data);
            document.getElementById("password").value = password;
        }
    );
}

</script>

<?php
require_once "../../../includes/modal_footer.php";
