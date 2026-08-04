<?php

require_once '../../includes/modal_header.php';

$api_key_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM api_keys WHERE api_key_id = $api_key_id LIMIT 1");
$row = mysqli_fetch_assoc($sql);
$api_key_name = escapeHtml($row['api_key_name']);
$api_key_expire = escapeHtml($row['api_key_expire']);
$api_key_user_id = intval($row['api_key_user_id']);

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-key mr-2"></i>Editing API Key:
        <strong><?= $api_key_name ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <div class="modal-body">

        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="api_key_id" value="<?= $api_key_id ?>">

        <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-sticky-note"></i></span>
                </div>
                <input type="text" class="form-control" name="name" placeholder="Key Name" maxlength="255"
                       value="<?= $api_key_name ?>" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label>Expiration Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="date" class="form-control" name="expire" min="<?= date('Y-m-d') ?>" max="2999-12-31"
                       value="<?= $api_key_expire ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Run as User <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                </div>
                <select class="form-control select2" name="run_as_user" required>
                    <option value="">- Select a user -</option>
                    <?php
                    $sql_run_users = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                    while ($run_user = mysqli_fetch_assoc($sql_run_users)) {
                        $run_user_id = intval($run_user['user_id']);
                        $run_user_name = escapeHtml($run_user['user_name']); ?>
                        <option value="<?= $run_user_id ?>" <?php if ($run_user_id == $api_key_user_id) { echo "selected"; } ?>><?= $run_user_name ?></option>
                    <?php } ?>
                </select>
            </div>
            <small class="form-text text-muted">The key inherits this user's module permissions and client access.</small>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_api_key" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../../../includes/modal_footer.php";
