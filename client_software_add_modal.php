<div class="modal" id="addSoftwareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-cube mr-2"></i>New License</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-device-licenses">Device Licenses</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-user-licenses">User Licenses</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-notes">Notes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-login">Login</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-details">

                            <div class="form-group">
                                <label>Software Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Software name" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Version</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="version" placeholder="Software version">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Type <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <select class="form-control select2" name="type" required>
                                        <option value="">- Type -</option>
                                        <?php foreach($software_types_array as $software_type) { ?>
                                            <option><?php echo $software_type; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>License Type</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                                    </div>
                                    <select class="form-control select2" name="license_type">
                                        <option value="">- Select a License Type -</option>
                                        <?php foreach($license_types_array as $license_type) { ?>
                                            <option><?php echo $license_type; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Seats</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-chair"></i></span>
                                    </div>
                                    <input type="number" class="form-control" name="seats" placeholder="Number of seats">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>License Key</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="key" placeholder="License key">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Purchase Date</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                    </div>
                                    <input type="date" class="form-control" name="purchase" max="2999-12-31">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Expire</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                    </div>
                                    <input type="date" class="form-control" name="expire" max="2999-12-31">
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-device-licenses">

                            <div class="alert alert-info">
                                Select Assets that are licensed for this software
                            </div>

                            <ul class="list-group">

                                <?php
                                $sql = mysqli_query($mysqli, "SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id WHERE asset_client_id = $client_id ORDER BY asset_name ASC");

                                while ($row = mysqli_fetch_array($sql)) {
                                    $asset_id = intval($row['asset_id']);
                                    $asset_name = nullable_htmlentities($row['asset_name']);
                                    $asset_type = nullable_htmlentities($row['asset_type']);
                                    $contact_name = nullable_htmlentities($row['contact_name']);

                                    ?>
                                    <li class="list-group-item">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="assets[]" value="<?php echo $asset_id; ?>">
                                            <label class="form-check-label ml-2"><?php echo "$asset_name - $contact_name"; ?></label>
                                        </div>
                                    </li>

                                <?php } ?>

                            </ul>

                        </div>

                        <div class="tab-pane fade" id="pills-user-licenses">

                            <div class="alert alert-info">
                                Select Users that are licensed for this software
                            </div>

                            <ul class="list-group">

                                <?php
                                $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_client_id = $client_id ORDER BY contact_name ASC");

                                while ($row = mysqli_fetch_array($sql)) {
                                    $contact_id = intval($row['contact_id']);
                                    $contact_name = nullable_htmlentities($row['contact_name']);
                                    $contact_email = nullable_htmlentities($row['contact_email']);

                                    ?>
                                    <li class="list-group-item">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="contacts[]" value="<?php echo $contact_id; ?>">
                                            <label class="form-check-label ml-2"><?php echo "$contact_name - $contact_email"; ?></label>
                                        </div>
                                    </li>

                                <?php } ?>

                            </ul>

                        </div>

                        <div class="tab-pane fade" id="pills-notes">

                            <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"></textarea>

                        </div>

                        <div class="tab-pane fade" id="pills-login">

                            <div class="form-group">
                                <label>Username</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="username" placeholder="Username">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Password</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                    </div>
                                    <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Password" autocomplete="new-password">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_software" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
