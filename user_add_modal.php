<div class="modal" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-user"></i> New User</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <div class="modal-body bg-white">

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
                        <label>Company <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                            </div>
                            <select class="form-control select2" name="default_company" required>
                                <option value="">- Company -</option>
                                <?php

                                $sql_companies_select = mysqli_query($mysqli, "SELECT * FROM companies ORDER BY company_name ASC");
                                while ($row = mysqli_fetch_array($sql_companies_select)) {
                                    $company_id = $row['company_id'];
                                    $company_name = htmlentities($row['company_name']);
                                    ?>
                                    <option value="<?php echo $company_id; ?>"><?php echo $company_name; ?></option>

                                    <?php
                                }
                                ?>
                            </select>
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
                                <option value="3">Administrator</option>
                                <option value="2">Technician</option>
                                <option value="1">Accountant</option>
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

                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary text-bold"><i class="fa fa-check"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
