<div class="modal" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-user-plus mr-2"></i>New User</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-user-details">Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-user-access">Access</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-user-details">

                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Full Name" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Email <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                    </div>
                                    <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Password <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                    </div>
                                    <input type="password" class="form-control" data-toggle="password" name="password" id="password" placeholder="Enter a Password" autocomplete="new-password" required minlength="8">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                    </div>
                                    <div class="input-group-append">
                                        <span class="btn btn-default"><i class="fa fa-fw fa-question" onclick="generatePassword()"></i></span>
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
                                        <option value="">- Role -</option>
                                        <?php
                                            $sql_user_roles = mysqli_query($mysqli, "SELECT * FROM user_roles WHERE user_role_archived_at IS NULL");
                                            while ($row = mysqli_fetch_array($sql_user_roles)) {
                                                $user_role_id = intval($row['user_role_id']);
                                                $user_role_name = nullable_htmlentities($row['user_role_name']);

                                            ?>
                                            <option value="<?php echo $user_role_id; ?>"><?php echo $user_role_name; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Avatar</label>
                                <input type="file" class="form-control-file" accept="image/*;capture=camera" name="file">
                            </div>

                            <div class="form-group" <?php if(empty($config_smtp_host)) { echo "hidden"; } ?>>
                                <div class="custom-control custom-checkbox">
                                    <input class="custom-control-input" type="checkbox" id="sendEmailCheckBox" name="send_email" value="" checked>
                                    <label for="sendEmailCheckBox" class="custom-control-label">
                                        Send user e-mail with login details?
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input class="custom-control-input" type="checkbox" id="forceMFACheckBox" name="force_mfa" value=1>
                                    <label for="forceMFACheckBox" class="custom-control-label">
                                        Force MFA
                                    </label>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-user-access">
                            
                            <h5>Restrict Client Access</h5>
                            <small class="text-muted">Leave Blank for Full access to all clients, no affect on users with the admin role.</small>

                            <?php

                            $sql_client_select = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_array($sql_client_select)) {
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);

                            ?>

                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" name="clients[]" value="<?php echo $client_id; ?>">
                                <label class="form-check-label ml-2"><?php echo $client_name; ?></label>
                            </div>

                            <?php } ?>

                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_user" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
