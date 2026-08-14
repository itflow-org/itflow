<?php

require_once '../../includes/modal_header.php';

$key = randomString(32);
$decryptPW = randomString(32);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-key me-2"></i>New Key</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-api-details">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-api-keys">Keys</a>
            </li>
        </ul>
        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-api-details">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="key" value="<?= $key ?>">
                <input type="hidden" name="password" value="<?= $decryptPW ?>">

                <div class="mb-3">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-sticky-note"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="Key Name" maxlength="255" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Expiration Date <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        <input type="date" class="form-control" name="expire" min="<?= date('Y-m-d') ?>" max="2999-12-31" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Run as User <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                        <select class="form-control select2" name="run_as_user" required>
                            <option value="">- Select a user -</option>
                            <?php
                            $sql_run_users = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                            while ($run_user = mysqli_fetch_assoc($sql_run_users)) {
                                $run_user_id = intval($run_user['user_id']);
                                $run_user_name = escapeHtml($run_user['user_name']); ?>
                                <option value="<?= $run_user_id ?>"><?= $run_user_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <small class="form-text text-muted">The key inherits this user's module permissions and client access.</small>
                </div>
            </div>

            <div class="tab-pane fade" id="pills-api-keys">
                <div class="mb-3">
                    <label>API Key <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        <input type="text" class="form-control" value="<?= $key ?>" required disabled>
                            <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?= $key ?>"><i class="fa fa-fw fa-copy"></i></button>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Login credential decryption password <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-unlock-alt"></i></span>
                        <input type="text" class="form-control" value="<?= $decryptPW ?>" required disabled>
                            <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?= $decryptPW ?>"><i class="fa fa-fw fa-copy"></i></button>
                    </div>
                </div>
                <br>
                <div class="mb-3">
                    <label>I have made a copy of the key(s)<strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <input type="checkbox" name="ack" value="1" required>
                    </div>
                </div>
            </div>

        </div>

        </div>
    <div class="modal-footer">
        <button type="submit" name="add_api_key" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
