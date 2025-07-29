<div class="modal" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-user-plus mr-2"></i>New User</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <div class="modal-body">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-user-details">Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-user-access">Restrict Access</a>
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
                                    <input type="text" class="form-control" name="name" placeholder="Full Name" maxlength="200" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Email <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                    </div>
                                    <input type="email" class="form-control" name="email" placeholder="Email Address" maxlength="200" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Password <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                    </div>
                                    <input type="password" class="form-control" data-toggle="password" name="password" id="password" placeholder="Enter a Password" autocomplete="new-password" minlength="8" required>
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
                                            $sql_user_roles = mysqli_query($mysqli, "SELECT * FROM user_roles WHERE role_archived_at IS NULL");
                                            while ($row = mysqli_fetch_array($sql_user_roles)) {
                                                $role_id = intval($row['role_id']);
                                                $role_name = nullable_htmlentities($row['role_name']);

                                            ?>
                                            <option value="<?php echo $role_id; ?>"><?php echo $role_name; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Avatar</label>
                                <input type="file" class="form-control-file" accept="image/*" name="file">
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
                                    $client_id = intval($row['client_id']);
                                    $client_name = nullable_htmlentities($row['client_name']);

                                ?>
                                <li class="list-group-item">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input client-checkbox" name="clients[]" value="<?php echo $client_id; ?>">
                                        <label class="form-check-label ml-3"><?php echo $client_name; ?></label>
                                    </div>
                                </li>

                                <?php } ?>

                            </ul>

                        </div>

                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_user" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
