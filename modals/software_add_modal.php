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
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-licensing">Licensing</a>
                        </li>
                        <?php if ($client_url) { // Dont show these when in global mode ?>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-device-licenses">Devices</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-user-licenses">Users</a>
                        </li>
                        <?php } ?>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-notes">Notes</a>
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
                                                <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            <?php } ?>

                            <div class="form-group">
                                <label>Software Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Software name" maxlength="200" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Version</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="version" placeholder="Software version" maxlength="200">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="description" placeholder="Short description">
                                </div>
                            </div>

                            <?php if ($client_url) { ?>
                            <div class="form-group">
                                <label>Vendor</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                    </div>
                                    <select class="form-control select2" name="vendor">
                                        <option value="">- Select Vendor -</option>
                                        <?php

                                        $sql = mysqli_query($mysqli, "SELECT vendor_name, vendor_id FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $vendor_id = intval($row['vendor_id']);
                                            $vendor_name = nullable_htmlentities($row['vendor_name']);
                                            ?>
                                            <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <?php } ?>

                            <div class="form-group">
                                <label>Type <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <select class="form-control select2" name="type" required>
                                        <option value="">- Select Type -</option>
                                        <?php foreach ($software_types_array as $software_type) { ?>
                                            <option><?php echo $software_type; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-licensing">

                            <div class="form-group">
                                <label>License Type</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                                    </div>
                                    <select class="form-control select2" name="license_type">
                                        <option value="">- Select a License Type -</option>
                                        <?php foreach ($license_types_array as $license_type) { ?>
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
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control" name="seats" placeholder="Number of seats">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>License Key</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="key" placeholder="License key" maxlength="200">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Purchase Reference</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="purchase_reference" placeholder="eg. Invoice, PO Number">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Purchase Date</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                                    </div>
                                    <input type="date" class="form-control" name="purchase" max="2999-12-31">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Expire</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                                    </div>
                                    <input type="date" class="form-control" name="expire" max="2999-12-31">
                                </div>
                            </div>

                        </div>

                        <?php if ($client_url) { // Dont show these when in global mode ?>

                        <div class="tab-pane fade" id="pills-device-licenses">

                            <ul class="list-group">

                                <li class="list-group-item bg-dark">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" onclick="this.closest('.tab-pane').querySelectorAll('.asset-checkbox').forEach(checkbox => checkbox.checked = this.checked);">
                                        <label class="form-check-label ml-3"><strong>Licensed Devices</strong></label>
                                    </div>
                                </li>


                                <?php
                                $sql_assets_select = mysqli_query($mysqli, "SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id WHERE asset_archived_at IS NULL AND asset_client_id = $client_id ORDER BY asset_archived_at ASC, asset_name ASC");

                                while ($row = mysqli_fetch_array($sql_assets_select)) {
                                    $asset_id_select = intval($row['asset_id']);
                                    $asset_name_select = nullable_htmlentities($row['asset_name']);
                                    $asset_type_select = nullable_htmlentities($row['asset_type']);
                                    $asset_archived_at = nullable_htmlentities($row['asset_archived_at']);
                                    if (empty($asset_archived_at)) {
                                        $asset_archived_display = "";
                                    } else {
                                        $asset_archived_display = "Archived - ";
                                    }
                                    $contact_name_select = nullable_htmlentities($row['contact_name']);

                                    ?>
                                    <li class="list-group-item">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input asset-checkbox" name="assets[]" value="<?php echo $asset_id_select; ?>">
                                            <label class="form-check-label ml-2"><?php echo "$asset_archived_display$asset_name_select - $contact_name_select"; ?></label>
                                        </div>
                                    </li>

                                <?php } ?>

                            </ul>

                        </div>

                        <div class="tab-pane fade" id="pills-user-licenses">

                            <ul class="list-group">

                                <li class="list-group-item bg-dark">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" onclick="this.closest('.tab-pane').querySelectorAll('.user-checkbox').forEach(checkbox => checkbox.checked = this.checked);">
                                        <label class="form-check-label ml-3"><strong>Licensed Users</strong></label>
                                    </div>
                                </li>

                                <?php
                                $sql_contacts_select = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_archived_at IS NULL AND contact_client_id = $client_id ORDER BY contact_name ASC");

                                while ($row = mysqli_fetch_array($sql_contacts_select)) {
                                    $contact_id_select = intval($row['contact_id']);
                                    $contact_name_select = nullable_htmlentities($row['contact_name']);
                                    $contact_email_select = nullable_htmlentities($row['contact_email']);

                                    ?>
                                    <li class="list-group-item">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input user-checkbox" name="contacts[]" value="<?php echo $contact_id_select; ?>">
                                            <label class="form-check-label ml-2"><?php echo "$contact_name_select - $contact_email_select"; ?></label>
                                        </div>
                                    </li>

                                <?php } ?>

                            </ul>

                        </div>

                        <?php } ?>

                        <div class="tab-pane fade" id="pills-notes">

                            <textarea class="form-control" rows="12" placeholder="Enter some notes" name="notes"></textarea>

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
