<div class="modal" id="editContactModal<?php echo $contact_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-user-edit mr-2"></i>Editing: <strong><?php echo $contact_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <!-- Prevent undefined checkbox errors on submit -->
                <input type="hidden" name="contact_primary" value="0">
                <input type="hidden" name="contact_important" value="0">
                <input type="hidden" name="contact_billing" value="0">
                <input type="hidden" name="contact_technical" value="0">
                <input type="hidden" name="send_email" value="0">
                <!-- End prevent undefined errors -->
                <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $contact_id; ?>"><i class="fa fa-fw fa-user mr-2"></i>Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-photo<?php echo $contact_id; ?>"><i class="fa fa-fw fa-image mr-2"></i>Photo</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-secure<?php echo $contact_id; ?>"><i class="fa fa-fw fa-lock mr-2"></i>Secure</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-notes<?php echo $contact_id; ?>"><i class="fa fa-fw fa-edit mr-2"></i>Notes</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-details<?php echo $contact_id; ?>">

                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Primary Contact</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Full Name" value="<?php echo $contact_name; ?>" required>
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            <input type="checkbox" name="contact_primary" value="1" <?php if ($contact_primary == 1) { echo "checked"; } ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Title</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="title" placeholder="Title" value="<?php echo $contact_title; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Department</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="department" placeholder="Department" value="<?php echo $contact_department; ?>">
                                </div>
                            </div>

                            <label>Phone</label>
                            <div class="form-row">
                                <div class="col-8">
                                    <div class="form-group">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                            </div>
                                            <input type="text" class="form-control" name="phone" placeholder="Phone Number" value="<?php echo $contact_phone; ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <input type="text" class="form-control" name="extension" placeholder="Extension" value="<?php echo $contact_extension; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Mobile</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="mobile" placeholder="Mobile Phone Number" value="<?php echo $contact_mobile; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Email</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                    </div>
                                    <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo $contact_email; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Location</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                    </div>
                                    <select class="form-control select2" name="location">
                                        <option value="">- Location -</option>
                                        <?php

                                        $sql_locations = mysqli_query($mysqli, "SELECT * FROM locations WHERE (location_archived_at > '$contact_created_at' OR location_archived_at IS NULL) AND location_client_id = $client_id ORDER BY location_name ASC");
                                        while ($row = mysqli_fetch_array($sql_locations)) {
                                            $location_id_select = intval($row['location_id']);
                                            $location_name_select = nullable_htmlentities($row['location_name']);
                                        ?>
                                            <option <?php if ($contact_location_id == $location_id_select) {
                                                        echo "selected";
                                                    } ?> value="<?php echo $location_id_select; ?>"><?php echo $location_name_select; ?></option>
                                        <?php } ?>

                                    </select>
                                </div>
                            </div>

                            <div class="form-row">

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="contactImportantCheckbox<?php echo $contact_id; ?>" name="contact_important" value="1" <?php if ($contact_important == 1) { echo "checked"; } ?>>
                                            <label class="custom-control-label" for="contactImportantCheckbox<?php echo $contact_id; ?>">Important</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="contactBillingCheckbox<?php echo $contact_id; ?>" name="contact_billing" value="1" <?php if ($contact_billing == 1) { echo "checked"; } ?>>
                                            <label class="custom-control-label" for="contactBillingCheckbox<?php echo $contact_id; ?>">Billing</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="contactTechnicalCheckbox<?php echo $contact_id; ?>" name="contact_technical" value="1" <?php if ($contact_technical == 1) { echo "checked"; } ?>>
                                            <label class="custom-control-label" for="contactTechnicalCheckbox<?php echo $contact_id; ?>">Technical</label>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-secure<?php echo $contact_id; ?>">

                            <div class="form-group">
                                <label>Pin</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="pin" placeholder="Security code or pin" value="<?php echo $contact_pin; ?>">
                                </div>
                            </div>

                            <?php if ($config_client_portal_enable == 1) { ?>
                                <div class="authForm">
                                    <div class="form-group">
                                        <label>Login</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-user-circle"></i></span>
                                            </div>
                                            <select class="form-control select2 authMethod" name="auth_method">
                                                <option value="">- None -</option>
                                                <option value="local" <?php if ($auth_method == "local") { echo "selected"; } ?>>Local</option>
                                                <option value="azure" <?php if ($auth_method == "azure") { echo "selected"; } ?>>Azure</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group passwordGroup" style="display: none;">
                                        <label>Password <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                            </div>
                                            <input type="password" class="form-control" data-toggle="password" id="password-edit-<?php echo $contact_id; ?>" name="password" placeholder="Password" autocomplete="new-password">
                                            <div class="input-group-append">
                                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                            </div>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-default" onclick="generatePassword('edit', <?php echo $contact_id; ?>)">
                                                    <i class="fa fa-fw fa-question"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="send_email" value="1" />
                                    <label class="form-check-label">Send user e-mail with login details?</label>
                                </div>

                            <?php } ?>

                        </div>

                        <div class="tab-pane fade" id="pills-photo<?php echo $contact_id; ?>">

                            <div class="mb-3 text-center">
                                <?php if (!empty($contact_photo)) { ?>
                                    <img class="img-fluid" alt="contact_photo" src="<?php echo "uploads/clients/$client_id/$contact_photo"; ?>">
                                <?php } else { ?>
                                    <span class="fa-stack fa-4x">
                                        <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                        <span class="fa fa-stack-1x text-white"><?php echo $contact_initials; ?></span>
                                    </span>
                                <?php } ?>
                            </div>

                            <div class="form-group">
                                <input type="file" class="form-control-file" name="file">
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-notes<?php echo $contact_id; ?>">

                            <div class="form-group">
                                <textarea class="form-control" rows="8" name="notes" placeholder="Enter some notes"><?php echo $contact_notes; ?></textarea>
                            </div>

                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_contact" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>