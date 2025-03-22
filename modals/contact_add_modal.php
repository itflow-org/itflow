<div class="modal" id="addContactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-user-plus mr-2"></i>New Contact</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-details"><i class="fa fa-fw fa-user mr-2"></i>Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-photo"><i class="fa fa-fw fa-image mr-2"></i>Photo</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-access"><i class="fa fa-fw fa-lock mr-2"></i>Access</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-notes"><i class="fa fa-fw fa-edit mr-2"></i>Notes</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-details">

                            <?php if ($client_url) { ?>
                                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                            <?php } else { ?>

                                <div class="form-group">
                                    <label>Client <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <select class="form-control select2" name="client_id" required>
                                            <option value="">- Select Client -</option>
                                            <?php

                                            $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
                                            while ($row = mysqli_fetch_array($sql)) {
                                                $client_id = intval($row['client_id']);
                                                $client_name = nullable_htmlentities($row['client_name']); ?>
                                                <option <?php if ($client_id == $_GET['client']) { echo "selected"; } ?> value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            <?php } ?>

                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary">Primary Contact</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Full Name" maxlength="200" required autofocus>
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            <input type="checkbox" name="contact_primary" value="1">
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
                                    <input type="text" class="form-control" name="title" placeholder="Job Title" maxlength="200">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Department / Group</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="department" placeholder="Department or group" maxlength="200">
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
                                            <input type="text" class="form-control" name="phone" placeholder="Phone Number" maxlength="200">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <input type="text" class="form-control" name="extension" placeholder="Extension" maxlength="200">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Mobile</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="mobile" placeholder="Mobile Phone Number" maxlength="200">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Email</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                    </div>
                                    <input type="email" class="form-control" name="email" placeholder="Email Address" maxlength="200">
                                </div>
                            </div>

                            <?php if($client_url) { ?>
                            <div class="form-group">
                                <label>Location</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                    </div>
                                    <select class="form-control select2" name="location">
                                        <option value="">- Select Location -</option>
                                        <?php

                                        $sql = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $location_id = intval($row['location_id']);
                                            $location_name = nullable_htmlentities($row['location_name']);
                                        ?>
                                            <option value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>
                                        <?php } ?>

                                    </select>
                                </div>
                            </div>
                            <?php } ?>

                        </div>

                        <div class="tab-pane fade" id="pills-photo">

                            <div class="form-group">
                                <label>Upload Photo</label>
                                <input type="file" class="form-control-file" name="file" accept="image/*">
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-access">

                            <div class="form-group">
                                <label>Pin</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="pin" placeholder="Security code or pin" maxlength="255">
                                </div>
                            </div>
                            <?php if ($config_client_portal_enable == 1) { ?>
                                <div class="authForm">
                                    <div class="form-group">
                                        <label>Client Portal</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-user-circle"></i></span>
                                            </div>
                                            <select class="form-control select2 authMethod" name="auth_method">
                                                <option value="">- No Access -</option>
                                                <option value="local">Using Set Password</option>
                                                <option value="azure">Using Azure Credentials</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group passwordGroup" style="display: none;">
                                        <label>Password</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                            </div>
                                            <input type="password" class="form-control" data-toggle="password" id="password-add" name="contact_password" placeholder="Password" autocomplete="new-password">
                                            <div class="input-group-append">
                                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                            </div>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-default" onclick="generatePassword('add')">
                                                    <i class="fa fa-fw fa-question"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            
                            <label>Roles:</label>
                            <div class="form-row">

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="contactImportantCheckbox" name="contact_important" value="1">
                                            <label class="custom-control-label" for="contactImportantCheckbox">Important</label>
                                            <p class="text-secondary"><small>Pin Top</small></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="contactBillingCheckbox" name="contact_billing" value="1">
                                            <label class="custom-control-label" for="contactBillingCheckbox">Billing</label>
                                            <p class="text-secondary"><small>Receives Invoices</small></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="contactTechnicalCheckbox" name="contact_technical" value="1">
                                            <label class="custom-control-label" for="contactTechnicalCheckbox">Technical</label>
                                            <p class="text-secondary"><small>Access </small></p>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-notes">

                            <div class="form-group">
                                <textarea class="form-control" rows="8" name="notes" placeholder="Enter some notes"></textarea>
                            </div>

                            <div class="form-group">
                                <label>Tags</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                                    </div>
                                    <select class="form-control select2" name="tags[]" data-placeholder="Add some tags" multiple>
                                        <?php

                                        $sql_tags_select = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 3 ORDER BY tag_name ASC");
                                        while ($row = mysqli_fetch_array($sql_tags_select)) {
                                            $tag_id_select = intval($row['tag_id']);
                                            $tag_name_select = nullable_htmlentities($row['tag_name']);
                                            ?>
                                            <option value="<?php echo $tag_id_select; ?>"><?php echo $tag_name_select; ?></option>
                                        <?php } ?>

                                    </select>
                                    <div class="input-group-append">
                                        <button class="btn btn-secondary" type="button"
                                            data-toggle="ajax-modal"
                                            data-modal-size="sm"
                                            data-ajax-url="ajax/ajax_tag_add.php"
                                            data-ajax-id="3">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_contact" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>