<div class="modal" id="editLoginModal<?php echo $login_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-key mr-2"></i>Editing password: <strong><?php echo $login_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="login_id" value="<?php echo $login_id; ?>">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $login_id; ?>">Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-relation<?php echo $login_id; ?>">Relation</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-notes<?php echo $login_id; ?>">Notes</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-details<?php echo $login_id; ?>">

                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Important?</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Name of Login" value="<?php echo $login_name; ?>" required>
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            <input type="checkbox" name="important" value="1" <?php if ($login_important == 1) { echo "checked"; } ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="description" placeholder="Description" value="<?php echo $login_description; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Username</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="username" placeholder="Username" value="<?php echo $login_username; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Password <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                    </div>
                                    <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Password" value="<?php echo $login_password; ?>" required autocomplete="new-password">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                    </div>
                                    <div class="input-group-append">
                                        <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?php echo $login_password; ?>"><i class="fa fa-fw fa-copy"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>OTP</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                    </div>
                                    <input type="password" class="form-control" data-toggle="password" name="otp_secret" value="<?php echo $login_otp_secret; ?>" placeholder="Insert secret key">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>URL/Host</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="uri" placeholder="ex. google.com" value="<?php echo $login_uri; ?>">
                                    <div class="input-group-append">

                                        <a href="<?php echo $login_uri; ?>" class="input-group-text"><i class="fa fa-fw fa-link"></i></a>
                                    </div>
                                    <div class="input-group-append">
                                        <button class="input-group-text clipboardjs" type="button" data-clipboard-text="<?php echo $login_uri; ?>"><i class="fa fa-fw fa-copy"></i></button>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-relation<?php echo $login_id; ?>">

                            <div class="form-group">
                                <label>Contact</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <select class="form-control select2" name="contact">
                                        <option value="">- Contact -</option>
                                        <?php

                                        $sql_contacts = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC");
                                        while ($row = mysqli_fetch_array($sql_contacts)) {
                                            $contact_id_select = intval($row['contact_id']);
                                            $contact_name_select = nullable_htmlentities($row['contact_name']);
                                            ?>
                                            <option <?php if ($login_contact_id == $contact_id_select) { echo "selected"; } ?> value="<?php echo $contact_id_select; ?>"><?php echo $contact_name_select; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Vendor</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                    </div>
                                    <select class="form-control select2" name="vendor">
                                        <option value="0">- None -</option>
                                        <?php

                                        $sql_vendors = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_client_id = $client_id ORDER BY vendor_name ASC");
                                        while ($row = mysqli_fetch_array($sql_vendors)) {
                                            $vendor_id_select = intval($row['vendor_id']);
                                            $vendor_name_select = nullable_htmlentities($row['vendor_name']);
                                            ?>
                                            <option <?php if ($login_vendor_id == $vendor_id_select) { echo "selected"; } ?> value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>
                                        <?php } ?>

                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Asset</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <select class="form-control select2" name="asset">
                                        <option value="0">- None -</option>
                                        <?php

                                        $sql_assets = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_client_id = $client_id ORDER BY asset_name ASC");
                                        while ($row = mysqli_fetch_array($sql_assets)) {
                                            $asset_id_select = intval($row['asset_id']);
                                            $asset_name_select = nullable_htmlentities($row['asset_name']);
                                            ?>
                                            <option <?php if ($login_asset_id == $asset_id_select) { echo "selected"; } ?> value="<?php echo $asset_id_select; ?>"><?php echo $asset_name_select; ?></option>

                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Software</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-box"></i></span>
                                    </div>
                                    <select class="form-control select2" name="software">
                                        <option value="0">- None -</option>
                                        <?php

                                        $sql_software = mysqli_query($mysqli, "SELECT * FROM software WHERE software_client_id = $client_id ORDER BY software_name ASC");
                                        while ($row = mysqli_fetch_array($sql_software)) {
                                            $software_id_select = intval($row['software_id']);
                                            $software_name_select = nullable_htmlentities($row['software_name']);
                                            ?>
                                            <option <?php if ($login_software_id == $software_id_select) { echo "selected"; } ?> value="<?php echo $software_id_select; ?>"><?php echo $software_name_select; ?></option>

                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-notes<?php echo $login_id; ?>">

                            <div class="form-group">
                                <textarea class="form-control" rows="8" placeholder="Enter some notes" name="note"><?php echo $login_note; ?></textarea>
                            </div>

                        </div>

                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_login" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
