<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$asset_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT asset_archived_at, asset_client_id, asset_contact_id, asset_created_at, asset_description,
    asset_id, asset_install_date, asset_location_id, asset_make, asset_model, asset_name,
    asset_notes, asset_os, asset_photo, asset_physical_location, asset_purchase_date,
    asset_purchase_reference, asset_serial, asset_status, asset_type, asset_uri, asset_uri_2,
    asset_vendor_id, asset_warranty_expire, interface_ip, interface_ipv6, interface_mac,
    interface_nat_ip, interface_network_id FROM assets
    LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
    WHERE asset_id = $asset_id LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
$client_id = intval($row['asset_client_id']);
$asset_id = intval($row['asset_id']);
$asset_type = escapeHtml($row['asset_type']);
$asset_name = escapeHtml($row['asset_name']);
$asset_description = escapeHtml($row['asset_description']);
$asset_make = escapeHtml($row['asset_make']);
$asset_model = escapeHtml($row['asset_model']);
$asset_serial = escapeHtml($row['asset_serial']);
$asset_os = escapeHtml($row['asset_os']);
$asset_ip = escapeHtml($row['interface_ip']);
$asset_ipv6 = escapeHtml($row['interface_ipv6']);
$asset_nat_ip = escapeHtml($row['interface_nat_ip']);
$asset_mac = escapeHtml($row['interface_mac']);
$asset_uri = escapeHtml($row['asset_uri']);
$asset_uri_2 = escapeHtml($row['asset_uri_2']);
$asset_status = escapeHtml($row['asset_status']);
$asset_purchase_reference = escapeHtml($row['asset_purchase_reference']);
$asset_purchase_date = escapeHtml($row['asset_purchase_date']);
$asset_warranty_expire = escapeHtml($row['asset_warranty_expire']);
$asset_install_date = escapeHtml($row['asset_install_date']);
$asset_photo = escapeHtml($row['asset_photo']);
$asset_physical_location = escapeHtml($row['asset_physical_location']);
$asset_notes = escapeHtml($row['asset_notes']);
$asset_created_at = escapeHtml($row['asset_created_at']);
$asset_archived_at = escapeHtml($row['asset_archived_at']);
$asset_vendor_id = intval($row['asset_vendor_id']);
$asset_location_id = intval($row['asset_location_id']);
$asset_contact_id = intval($row['asset_contact_id']);
$asset_network_id = intval($row['interface_network_id']);
$device_icon = getAssetIcon($asset_type);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class='fa fa-fw fa-<?= $device_icon ?> me-2'></i>Copying asset: <strong><?= $asset_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pillsDetailsCopy<?= $asset_id ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pillsAssignmentCopy<?= $asset_id ?>">Assignment</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pillsNetworkCopy<?= $asset_id ?>">Network</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pillsPurchaseCopy<?= $asset_id ?>">Purchase</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pillsLoginCopy<?= $asset_id ?>">Login</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pillsNotesCopy<?= $asset_id ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pillsDetailsCopy<?= $asset_id ?>">

                <div class="mb-3">
                    <label>Type <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        <select class="form-control select2" name="type" required>
                            <?php foreach($asset_types_array as $asset_type_select => $asset_icon_select) { ?>
                                <option <?php if ($asset_type_select == $asset_type) { echo "selected"; } ?>><?= $asset_type_select ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="Name the asset" maxlength="200" value="<?= $asset_name ?>" required>
                    </div>
                </div>

                <?php //Do not display Make Model or Serial if Virtual is selected
                if ($asset_type !== 'virtual') { ?>
                    <div class="mb-3">
                        <label>Make</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                            <input type="text" class="form-control" name="make" placeholder="Manufacturer" maxlength="200" value="<?= $asset_make ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Model</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                            <input type="text" class="form-control" name="model" placeholder="Model Number" maxlength="200" value="<?= $asset_model ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Serial Number</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                            <input type="text" class="form-control font-monospace" name="serial" placeholder="Serial number" maxlength="200">
                        </div>
                    </div>
                <?php } ?>

                <?php if ($asset_type !== 'Phone' && $asset_type !== 'Mobile Phone' && $asset_type !== 'Tablet' && $asset_type !== 'Access Point' && $asset_type !== 'Printer' && $asset_type !== 'Camera' && $asset_type !== 'TV' && $asset_type !== 'Other') { ?>
                    <div class="mb-3">
                        <label>Operating System</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-laptop-code"></i></span>
                            <input type="text" class="form-control" name="os" placeholder="ex Windows 10 Pro" maxlength="200" value="<?= $asset_os ?>">
                        </div>
                    </div>
                <?php } ?>

                <div class="mb-3">
                    <label>Description</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        <input type="text" class="form-control" name="description" placeholder="Description of the asset" maxlength="255" value="<?= $asset_description ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsAssignmentCopy<?= $asset_id ?>">

                <div class="mb-3">
                    <label>Location</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        <select class="form-control select2" name="location">
                            <option value="">- Select Location -</option>
                            <?php

                            $sql_locations = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_locations)) {
                                $location_id_select = intval($row['location_id']);
                                $location_name_select = escapeHtml($row['location_name']);
                                ?>
                                <option <?php if ($asset_location_id == $location_id_select) { echo "selected"; } ?> value="<?= $location_id_select ?>"><?= $location_name_select ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Physical Location</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        <input type="text" class="form-control" name="physical_location" placeholder="Physical location eg. Floor 2, Closet B" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Assign To</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        <select class="form-control select2" name="contact">
                            <option value="">- Select Contact -</option>
                            <?php

                            $sql_contacts = mysqli_query($mysqli, "SELECT contact_id, contact_name FROM contacts WHERE contact_archived_at IS NULL AND contact_client_id = $client_id ORDER BY contact_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_contacts)) {
                                $contact_id_select = intval($row['contact_id']);
                                $contact_name_select = escapeHtml($row['contact_name']);
                                ?>
                                <option value="<?= $contact_id_select ?>"><?= $contact_name_select ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Status</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-circle"></i></span>
                        <select class="form-control select2" name="status">
                            <option value="">- Select Status -</option>
                            <?php
                            $sql_interface_types_select = mysqli_query($mysqli, "
                                SELECT category_name FROM categories
                                WHERE category_type = 'asset_status'
                                AND category_archived_at IS NULL
                                ORDER BY category_order ASC, category_name ASC
                            ");
                            while ($row = mysqli_fetch_assoc($sql_interface_types_select)) {
                                $asset_status_select = escapeHtml($row['category_name']);
                                ?>
                                <option <?php if ($asset_status_select == $asset_status) { echo "selected"; } ?>>
                                    <?= $asset_status_select ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsNetworkCopy<?= $asset_id ?>">

                <div class="mb-3">
                    <label>Network</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                        <select class="form-control select2" name="network">
                            <option value="">- Select Network -</option>
                            <?php

                            $sql_networks = mysqli_query($mysqli, "SELECT network, network_id, network_name FROM networks WHERE network_archived_at IS NULL AND network_client_id = $client_id ORDER BY network_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_networks)) {
                                $network_id_select = intval($row['network_id']);
                                $network_name_select = escapeHtml($row['network_name']);
                                $network_select = escapeHtml($row['network']);

                                ?>
                                <option <?php if ($asset_network_id == $network_id_select) { echo "selected"; } ?> value="<?= $network_id_select ?>"><?= $network_name_select ?> - <?= $network_select ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>IP Address / <span class="text-muted">DHCP</span></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        <input type="text" class="form-control font-monospace" name="ip" placeholder="192.168.10.250" maxlength="200" data-inputmask="'alias': 'ip'" data-mask>
                            <div class="input-group-text">
                                <input type="checkbox" name="dhcp" value="1" <?php if($asset_ip == 'DHCP'){ echo "checked"; } ?>>
                            </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label>MAC Address</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        <input type="text" class="form-control font-monospace" name="mac" placeholder="00:11:22:AA:BB:CC" maxlength="200" data-inputmask="'alias': 'mac'" data-mask>
                    </div>
                </div>

                <div class="mb-3">
                    <label>IPv6 Address</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        <input type="text" class="form-control font-monospace" name="ipv6" value="<?= $asset_ipv6 ?>" placeholder="2001:0db8:1000::3" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>NAT Address</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                        <input type="text" class="form-control font-monospace" name="nat_ip" placeholder="10.52.4.55" maxlength="200" data-inputmask="'alias': 'ip'" data-mask>
                    </div>
                </div>

                <div class="mb-3">
                    <label>URI</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" name="uri" placeholder="URI http:// ftp:// ssh: etc" maxlength="500">
                    </div>
                </div>

                <div class="mb-3">
                    <label>URI 2</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" name="uri_2" placeholder="URI http:// ftp:// ssh: etc" maxlength="500">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsPurchaseCopy<?= $asset_id ?>">

                <div class="mb-3">
                    <label>Vendor</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        <select class="form-control select2" name="vendor">
                            <option value="">- Select Vendor -</option>
                            <?php

                            $sql_vendors = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_vendors)) {
                                $vendor_id_select = intval($row['vendor_id']);
                                $vendor_name_select = escapeHtml($row['vendor_name']);
                                ?>
                                <option <?php if ($asset_vendor_id == $vendor_id_select) { echo "selected"; } ?> value="<?= $vendor_id_select ?>"><?= $vendor_name_select ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Install Date</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                        <input type="date" class="form-control" name="install_date" max="2999-12-31" value="<?= $asset_install_date ?>">
                    </div>
                </div>

                <?php if ($asset_type !== 'Virtual Machine') { ?>
                    <div class="mb-3">
                        <label>Purchase Reference</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-shopping-cart"></i></span>
                            <input type="text" class="form-control" name="purchase_reference" placeholder="eg. Invoice, PO Number" maxlength="200" value="<?= $asset_purchase_reference ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Purchase Date</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-receipt"></i></span>
                            <input type="date" class="form-control" name="purchase_date" max="2999-12-31" value="<?= $asset_purchase_date ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Warranty Expire</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                            <input type="date" class="form-control" name="warranty_expire" max="2999-12-31" value="<?= $asset_warranty_expire ?>">
                        </div>
                    </div>
                <?php } ?>

            </div>

            <div class="tab-pane fade" id="pillsLoginCopy<?= $asset_id ?>">

                <div class="mb-3">
                    <label>Username</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa  fa-fw fa-user"></i></span>
                        <input type="text" class="form-control" name="username" placeholder="Username" maxlength="350">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Password</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                        <input type="text" class="form-control" name="password" placeholder="Password" maxlength="350" autocomplete="off">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsNotesCopy<?= $asset_id ?>">

                <div class="mb-3">
                    <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"><?= $asset_notes ?></textarea>
                </div>

                <div class="mb-3">
                    <label>Upload Photo</label>
                    <input type="file" class="form-control" name="file">
                </div>

            </div>

        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_asset" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Copy</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
