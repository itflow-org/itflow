<div class="modal" id="editAssetModal<?php echo $asset_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-<?php echo $device_icon; ?> mr-2"></i>Editing asset: <strong><?php echo $asset_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-details<?php echo $asset_id; ?>">Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-network<?php echo $asset_id; ?>">Network</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-assignment<?php echo $asset_id; ?>">Assignment</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-purchase<?php echo $asset_id; ?>">Purchase</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-notes<?php echo $asset_id; ?>">Notes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-history<?php echo $asset_id; ?>">History</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

                        <div class="tab-pane fade show active" id="pills-details<?php echo $asset_id; ?>">

                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Name the asset" value="<?php echo $asset_name; ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="description" placeholder="Description of the asset" value="<?php echo $asset_description; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Type <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                                    </div>
                                    <select class="form-control select2" name="type" required>
                                        <?php foreach($asset_types_array as $asset_type_select => $asset_icon_select) { ?>
                                            <option <?php if ($asset_type_select == $asset_type) { echo "selected"; } ?>><?php echo $asset_type_select; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <?php //Do not display Make Model or Serial if Virtual is selected
                            if ($asset_type !== 'virtual') { ?>
                                <div class="form-group">
                                    <label>Make </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="make" placeholder="Manufacturer" value="<?php echo $asset_make; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Model</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="model" placeholder="Model Number" value="<?php echo $asset_model; ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Serial Number</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="serial" placeholder="Serial number" value="<?php echo $asset_serial; ?>">
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if ($asset_type !== 'Phone' && $asset_type !== 'Mobile Phone' && $asset_type !== 'Tablet' && $asset_type !== 'Access Point' && $asset_type !== 'Printer' && $asset_type !== 'Camera' && $asset_type !== 'TV' && $asset_type !== 'Other') { ?>
                                <div class="form-group">
                                    <label>Operating System</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fab fa-fw fa-windows"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="os" placeholder="ex Windows 10 Pro" value="<?php echo $asset_os; ?>">
                                    </div>
                                </div>
                            <?php } ?>

                        </div>

                        <div class="tab-pane fade" id="pills-network<?php echo $asset_id; ?>">

                            <div class="form-group">
                                <label>Network</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                                    </div>
                                    <select class="form-control select2" name="network">
                                        <option value="">- Network -</option>
                                        <?php

                                        $sql_networks = mysqli_query($mysqli, "SELECT * FROM networks WHERE network_id = $asset_network_id OR network_archived_at IS NULL AND network_client_id = $client_id ORDER BY network_name ASC");
                                        while ($row = mysqli_fetch_array($sql_networks)) {
                                            $network_id_select = intval($row['network_id']);
                                            $network_name_select = nullable_htmlentities($row['network_name']);
                                            $network_select = nullable_htmlentities($row['network']);
                                            $network_archived_at = nullable_htmlentities($row['network_archived_at']);
                                            if ($network_archived_at) {
                                                $network_name_select_display = "($network_name_select - $network_select) - ARCHIVED";
                                            } else {
                                                $network_name_select_display = "$network_name_select - $network_select";
                                            }

                                            ?>
                                            <option <?php if ($asset_network_id == $network_id_select) { echo "selected"; } ?> value="<?php echo $network_id_select; ?>"><?php echo $network_name_select_display; ?></option>

                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>IP Address or DHCP</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="ip" value="<?php echo $asset_ip; ?>" placeholder="192.168.10.250" data-inputmask="'alias': 'ip'" data-mask>
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            <input type="checkbox" name="dhcp" value="1" <?php if($asset_ip == 'DHCP'){ echo "checked"; } ?>>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>NAT IP</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-random"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="nat_ip" value="<?php echo $asset_nat_ip; ?>" placeholder="10.52.4.55" data-inputmask="'alias': 'ip'" data-mask>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>IPv6 Address</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="ipv6" value="<?php echo $asset_ipv6; ?>" placeholder="ex. 2001:0db8:0000:0000:0000:ff00:0042:8329">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>MAC Address</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="mac" value="<?php echo $asset_mac; ?>" placeholder="MAC Address" data-inputmask="'alias': 'mac'" data-mask>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>URI</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="uri" placeholder="URI http:// ftp:// ssh: etc" value="<?php echo $asset_uri; ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>URI 2</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="uri_2" placeholder="URI http:// ftp:// ssh: etc" value="<?php echo $asset_uri_2; ?>">
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-assignment<?php echo $asset_id; ?>">

                            <div class="form-group">
                                <label>Physical Location</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="physical_location" placeholder="Physical location eg. Floor 2, Closet B" value="<?php echo $asset_physical_location; ?>">
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

                                        $sql_locations = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_id = $asset_location_id OR location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                                        while ($row = mysqli_fetch_array($sql_locations)) {
                                            $location_id_select = intval($row['location_id']);
                                            $location_name_select = nullable_htmlentities($row['location_name']);
                                            $location_archived_at = nullable_htmlentities($row['location_archived_at']);
                                            if ($location_archived_at) {
                                                $location_name_select_display = "($location_name_select) - ARCHIVED";
                                            } else {
                                                $location_name_select_display = $location_name_select;
                                            }
                                            ?>
                                            <option <?php if ($asset_location_id == $location_id_select) { echo "selected"; } ?> value="<?php echo $location_id_select; ?>"><?php echo $location_name_select_display; ?></option>

                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Assign To</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                    </div>
                                    <select class="form-control select2" name="contact">
                                        <option value="">- Contact -</option>
                                        <?php

                                        $sql_contacts = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_id = $asset_contact_id OR contact_archived_at IS NULL AND contact_client_id = $client_id ORDER BY contact_name ASC");
                                        while ($row = mysqli_fetch_array($sql_contacts)) {
                                            $contact_id_select = intval($row['contact_id']);
                                            $contact_name_select = nullable_htmlentities($row['contact_name']);
                                            $contact_archived_at = nullable_htmlentities($row['contact_archived_at']);
                                            if ($contact_archived_at) {
                                                $contact_name_select_display = "($contact_name_select) - ARCHIVED";
                                            } else {
                                                $contact_name_select_display = $contact_name_select;
                                            }
                                            ?>
                                            <option <?php if ($asset_contact_id == $contact_id_select) { echo "selected"; } ?> value="<?php echo $contact_id_select; ?>">
                                                <?php echo $contact_name_select_display; ?>
                                            </option>

                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-info"></i></span>
                                    </div>
                                    <select class="form-control select2" name="status">
                                        <?php foreach($asset_status_array as $asset_status_select) { ?>
                                            <option <?php if ($asset_status_select == $asset_status) { echo "selected"; } ?>><?php echo $asset_status_select; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-purchase<?php echo $asset_id; ?>">

                            <div class="form-group">
                                <label>Vendor</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                    </div>
                                    <select class="form-control select2" name="vendor">
                                        <option value="">- Vendor -</option>
                                        <?php

                                        $sql_vendors = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_id = $asset_vendor_id OR vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
                                        while ($row = mysqli_fetch_array($sql_vendors)) {
                                            $vendor_id_select = intval($row['vendor_id']);
                                            $vendor_name_select = nullable_htmlentities($row['vendor_name']);
                                            $vendor_archived_at = nullable_htmlentities($row['vendor_archived_at']);
                                            if ($vendor_archived_at) {
                                                $vendor_name_select_display = "($vendor_name_select) - ARCHIVED";
                                            } else {
                                                $vendor_name_select_display = $vendor_name_select;
                                            }
                                            ?>
                                            <option <?php if ($asset_vendor_id == $vendor_id_select) { echo "selected"; } ?> value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select_display; ?></option>

                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <?php if ($asset_type !== 'Virtual Machine') { ?>
                                <div class="form-group">
                                    <label>Purchase Date</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                                        </div>
                                        <input type="date" class="form-control" name="purchase_date" max="2999-12-31" value="<?php echo $asset_purchase_date; ?>">
                                    </div>
                                </div>
                            <?php } ?>

                            <div class="form-group">
                                <label>Install Date</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                                    </div>
                                    <input type="date" class="form-control" name="install_date" max="2999-12-31" value="<?php echo $asset_install_date; ?>">
                                </div>
                            </div>

                            <?php if ($asset_type !== 'Virtual Machine') { ?>
                                <div class="form-group">
                                    <label>Warranty Expire</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                                        </div>
                                        <input type="date" class="form-control" name="warranty_expire" max="2999-12-31" value="<?php echo $asset_warranty_expire; ?>">
                                    </div>
                                </div>
                            <?php } ?>

                        </div>

                        <div class="tab-pane fade" id="pills-notes<?php echo $asset_id; ?>">

                            <div class="mb-3 text-center">
                                <?php if ($asset_photo) { ?>
                                    <img class="img-fluid" alt="asset_photo" src="<?php echo "uploads/clients/$client_id/$asset_photo"; ?>">
                                <?php } ?>
                            </div>

                            <div class="form-group">
                                <label>Upload / Replace Photo</label>
                                <input type="file" class="form-control-file" name="file" accept="image/*">
                            </div>

                            <div class="form-group">
                                <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"><?php echo $asset_notes; ?></textarea>
                            </div>

                            <p class="text-muted text-right">Asset ID: <?= $asset_id ?></p>

                        </div>


                        <div class="tab-pane fade" id="pills-history<?php echo $asset_id; ?>">

                            <?php $sql_asset_history = mysqli_query($mysqli, "SELECT * FROM asset_history WHERE asset_history_asset_id = $asset_id ORDER BY asset_history_id DESC LIMIT 10");  ?>

                            <div class="form-group">
                                <label>Asset History</label>

                                <ul>

                                    <?php
                                    while ($row = mysqli_fetch_array($sql_asset_history)) {
                                        $asset_history_description = nullable_htmlentities(($row['asset_history_description']));
                                        $asset_history_created_at = nullable_htmlentities(($row['asset_history_created_at']));
                                        echo "<li><small class='text-secondary'>$asset_history_created_at</small><br>$asset_history_description</li>";
                                    }
                                    ?>
                                </ul>

                            </div>

                        </div>

                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_asset" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
