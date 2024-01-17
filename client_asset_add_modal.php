<div class="modal" id="addAssetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-desktop mr-2"></i>New <?php if (!empty($_GET['type'])) { echo ucwords(strip_tags($_GET['type'])); }else{ echo "Asset"; } ?></h5>
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
                            <a class="nav-link" data-toggle="pill" href="#pills-network">Network</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-assignment">Assignment</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-purchase">Purchase</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-login">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-notes">Notes</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-details">

                            <div class="form-group">
                                <label>Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" placeholder="Asset name or asset tag" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="description" placeholder="Description of the asset">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Type <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                                    </div>
                                    <select class="form-control select2" name="type" required>
                                        <option value="">- Type -</option>
                                        <?php foreach($asset_types_array as $asset_type => $asset_icon) { ?>
                                            <option><?php echo $asset_type; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <?php //Do not display Make Model or Serial if Virtual is selected
                            if ($_GET['type'] !== 'virtual') { ?>
                                <div class="form-group">
                                    <label>Make</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="make" placeholder="Manufacturer">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Model</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="model" placeholder="Model Number">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Serial Number</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="serial" placeholder="Serial number">
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if ($_GET['type'] !== 'network' && $_GET['type'] !== 'other') { ?>
                                <div class="form-group">
                                    <label>Operating System</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fab fa-fw fa-windows"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="os" placeholder="ex Windows 10 Pro">
                                    </div>
                                </div>
                            <?php } ?>

                        </div>

                        <div class="tab-pane fade" id="pills-network">

                            <div class="form-group">
                                <label>Network</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                                    </div>
                                    <select class="form-control select2" name="network">
                                        <option value="">- Network -</option>
                                        <?php

                                        $sql = mysqli_query($mysqli, "SELECT * FROM networks WHERE network_archived_at IS NULL AND network_client_id = $client_id ORDER BY network_name ASC");
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $network_id = intval($row['network_id']);
                                            $network_name = nullable_htmlentities($row['network_name']);
                                            $network = nullable_htmlentities($row['network']);

                                            ?>
                                            <option value="<?php echo $network_id; ?>"><?php echo $network_name; ?> - <?php echo $network; ?></option>

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
                                    <input type="text" class="form-control" name="ip" placeholder="192.168.10.250" data-inputmask="'alias': 'ip'" data-mask>
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                            <input type="checkbox" name="dhcp" value="1">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>NAT IP Address</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-random"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="nat_ip" placeholder="10.52.4.55" data-inputmask="'alias': 'ip'" data-mask>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>MAC Address</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="mac" placeholder="MAC Address" data-inputmask="'alias': 'mac'" data-mask>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>URI</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="uri" placeholder="URI http:// ftp:// ssh: etc">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>URI 2</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="uri_2" placeholder="URI http:// ftp:// ssh: etc">
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-assignment">

                            <div class="form-group">
                                <label>Location</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                    </div>
                                    <select class="form-control select2" name="location">
                                        <option value="">- Location -</option>
                                        <?php

                                        $sql = mysqli_query($mysqli, "SELECT * FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $location_id = intval($row['location_id']);
                                            $location_name = nullable_htmlentities($row['location_name']);
                                            ?>
                                            <option value="<?php echo $location_id; ?>"><?php echo $location_name; ?></option>
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

                                        $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_archived_at IS NULL AND contact_client_id = $client_id ORDER BY contact_name ASC");
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $contact_id = intval($row['contact_id']);
                                            $contact_name = nullable_htmlentities($row['contact_name']);
                                            ?>
                                            <option value="<?php echo $contact_id; ?>"><?php echo $contact_name; ?></option>

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
                                        <option value="">- Status -</option>
                                        <?php foreach($asset_status_array as $asset_status) { ?>
                                            <option><?php echo $asset_status; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-purchase">

                            <div class="form-group">
                                <label>Vendor</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                    </div>
                                    <select class="form-control select2" name="vendor">
                                        <option value="">- Vendor -</option>
                                        <?php

                                        $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id AND vendor_template = 0 ORDER BY vendor_name ASC");
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $vendor_id = intval($row['vendor_id']);
                                            $vendor_name = nullable_htmlentities($row['vendor_name']);
                                            ?>
                                            <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>

                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Install Date</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                                    </div>
                                    <input type="date" class="form-control" name="install_date" max="2999-12-31">
                                </div>
                            </div>

                            <?php if ($_GET['type'] !== 'virtual') { ?>
                                <div class="form-group">
                                    <label>Purchase Date</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                                        </div>
                                        <input type="date" class="form-control" name="purchase_date" max="2999-12-31">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Warranty Expire</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                                        </div>
                                        <input type="date" class="form-control" name="warranty_expire" max="2999-12-31">
                                    </div>
                                </div>
                            <?php } ?>

                        </div>

                        <div class="tab-pane fade" id="pills-login">

                            <div class="form-group">
                                <label>Username</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa  fa-fw fa-user"></i></span>
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
                                    <input type="text" class="form-control" name="password" placeholder="Password" autocomplete="new-password">
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-notes">

                            <div class="form-group">
                                <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"></textarea>
                            </div>

                        </div>

                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_asset" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
