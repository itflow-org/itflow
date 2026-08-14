<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$contact_id = intval($_GET['contact_id'] ?? 0);
$type = escapeHtml(ucwords($_GET['type']) ?? '');

if ($client_id) {
    $sql_network_select = mysqli_query($mysqli, "SELECT network, network_id, network_name FROM networks WHERE network_archived_at IS NULL AND network_client_id = $client_id ORDER BY network_name ASC");
    $sql_vendor_select = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
    $sql_location_select = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
    $sql_contact_select = mysqli_query($mysqli, "SELECT contact_id, contact_name FROM contacts WHERE contact_archived_at IS NULL AND contact_client_id = $client_id ORDER BY contact_name ASC");
} else {
    $sql_client_select = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
}

// OS typeahead suggestions
$os_sql = mysqli_query($mysqli, "SELECT DISTINCT asset_os AS label FROM assets WHERE asset_archived_at IS NULL");
if ($os_sql && mysqli_num_rows($os_sql) > 0) {
    $os_arr = [];
    while ($row = mysqli_fetch_assoc($os_sql)) {
        // jQuery UI Autocomplete expects {label: "...", value: "..."}
        $label = $row['label'];
        $os_arr[] = ['label' => $label, 'value' => $label];
    }
    $json_os = json_encode($os_arr);
}

$sql_tags_select = mysqli_query($mysqli, "SELECT tag_id, tag_name FROM tags WHERE tag_type = 5 ORDER BY tag_name ASC");

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-desktop me-2"></i>New <?php if ($type) { echo $type; } ?> Asset</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body ui-front">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-asset-details">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-asset-assignment">Assignment</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-asset-network">Network</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-asset-purchase">Purchase</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-asset-login">Login</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-asset-notes">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-asset-details">

                <?php if ($client_id) { ?>
                    <input type="hidden" name="client_id" value="<?= $client_id ?>">
                <?php } else { ?>

                    <div class="mb-3">
                        <label>Client <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            <select class="form-control select2" name="client_id" required>
                                <option value="">- Select Client -</option>
                                <?php

                                while ($row = mysqli_fetch_assoc($sql_client_select)) {
                                    $client_id_select = intval($row['client_id']);
                                    $client_name = escapeHtml($row['client_name']); ?>
                                    <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?= $client_id_select ?>"><?= $client_name ?></option>

                                <?php } ?>
                            </select>
                        </div>
                    </div>

                <?php } ?>

                <div class="mb-3">
                    <label>Type <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        <select class="form-control select2" name="type" required>
                            <option value="">- Select Type -</option>
                            <?php foreach($asset_types_array as $asset_type => $asset_icon) { ?>
                                <option><?= $asset_type ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Name <strong class="text-danger">*</strong> / <span class="text-secondary" title="Pin to Overview">Favorite</span></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="e.g. SRV-DC01" maxlength="200" required autofocus>
                            <div class="input-group-text">
                                <label class="star-toggle mb-0" title="Favorite">
                                    <input type="checkbox" name="favorite" value="1"><i class="far fa-star"></i>
                                </label>
                            </div>
                    </div>
                </div>

                <?php //Do not display Make Model or Serial if Virtual is selected
                if ($type !== 'Virtual') { ?>
                    <div class="mb-3">
                        <label>Make</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                            <input type="text" class="form-control" name="make" placeholder="e.g. Dell, HP, Lenovo" maxlength="200">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Model</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                            <input type="text" class="form-control" name="model" placeholder="e.g. PowerEdge R740" maxlength="200">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Serial Number</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                            <input type="text" class="form-control font-monospace" name="serial" placeholder="e.g. ABC1234XYZ" maxlength="200">
                        </div>
                    </div>
                <?php } ?>

                <?php if ($type !== 'Network' && $type !== 'Other') { ?>
                    <div class="mb-3">
                        <label>Operating System</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-laptop-code"></i></span>
                            <input type="text" class="form-control" name="os" id="os" placeholder="e.g. Windows 11 Pro, Ubuntu 24.04" maxlength="200">
                        </div>
                    </div>
                <?php } ?>

                <div class="mb-3">
                    <label>Description</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        <input type="text" class="form-control" name="description" placeholder="e.g. Domain controller for HQ" maxlength="255">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-asset-assignment">

                <?php if ($client_id) { ?>
                <div class="mb-3">
                    <label>Location</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        <select class="form-control select2" name="location">
                            <option value="">- Select Location -</option>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_location_select)) {
                                $location_id = intval($row['location_id']);
                                $location_name = escapeHtml($row['location_name']);
                                ?>
                                <option value="<?= $location_id ?>"><?= $location_name ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>
                <?php } ?>

                <div class="mb-3">
                    <label>Physical Location</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        <input type="text" class="form-control" name="physical_location" placeholder="e.g. Floor 2, Closet B" maxlength="200">
                    </div>
                </div>

                <?php if ($client_id) { ?>
                <div class="mb-3">
                    <label>Assign To</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        <select class="form-control select2" name="contact">
                            <option value="">- Select Contact -</option>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_contact_select)) {
                                $contact_id_select = intval($row['contact_id']);
                                $contact_name = escapeHtml($row['contact_name']);
                                ?>
                                <option
                                    <?php if ($contact_id == $contact_id_select) {
                                    echo "selected"; }
                                    ?>
                                    value="<?= $contact_id_select ?>"><?= $contact_name ?>
                                </option>

                            <?php } ?>

                        </select>
                    </div>
                </div>
                <?php } ?>

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
                                <option><?= $asset_status_select ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-asset-network">
                <?php if ($client_id) { ?>
                <div class="mb-3">
                    <label>Network</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                        <select class="form-control select2" name="network">
                            <option value="">- Select Network -</option>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_network_select)) {
                                $network_id = intval($row['network_id']);
                                $network_name = escapeHtml($row['network_name']);
                                $network = escapeHtml($row['network']);

                                ?>
                                <option value="<?= $network_id ?>"><?= $network_name ?> - <?= $network ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>
                <?php } ?>

                <div class="mb-3">
                    <label>IPv4 Address / <span class="text-muted">DHCP</span></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        <input type="text" class="form-control font-monospace" name="ip" placeholder="e.g. 192.168.1.10" maxlength="200" data-inputmask="'alias': 'ip'" data-mask>
                            <div class="input-group-text">
                                <input type="checkbox" name="dhcp" value="1">
                            </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label>MAC Address</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        <input type="text" class="form-control font-monospace" name="mac" placeholder="e.g. 00:1A:2B:3C:4D:5E" data-inputmask="'alias': 'mac'" maxlength="200" data-mask>
                    </div>
                </div>

                <div class="mb-3">
                    <label>IPv6 Address</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        <input type="text" class="form-control font-monospace" name="ipv6" placeholder="e.g. 2001:db8::1" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>NAT Address</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                        <input type="text" class="form-control font-monospace" name="nat_ip" placeholder="e.g. 203.0.113.10 or 10.0.0.5" data-inputmask="'alias': 'ip'" maxlength="200" data-mask>
                    </div>
                </div>

                <div class="mb-3">
                    <label>URI</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" name="uri" placeholder="e.g. https:// or ssh://" maxlength="500">
                    </div>
                </div>

                <div class="mb-3">
                    <label>URI 2</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" name="uri_2" placeholder="e.g. https:// or ssh://" maxlength="500">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Client URI</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" name="uri_client" placeholder="e.g. https:// or ssh://" maxlength="500">
                    </div>
                    <small class="text-muted">Viewable in client portal.</small>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-asset-purchase">

                <?php if ($client_id) { ?>
                <div class="mb-3">
                    <label>Vendor</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        <select class="form-control select2" name="vendor">
                            <option value="">- Select Vendor -</option>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_vendor_select)) {
                                $vendor_id = intval($row['vendor_id']);
                                $vendor_name = escapeHtml($row['vendor_name']);
                                ?>
                                <option value="<?= $vendor_id ?>"><?= $vendor_name ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>
                <?php } ?>

                <?php if ($type !== 'Virtual') { ?>
                    <div class="mb-3">
                        <label>Purchase Reference</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-receipt"></i></span>
                            <input type="text" class="form-control" name="purchase_reference" placeholder="e.g. INV-1045 or PO-7782" maxlength="200">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Purchase Date</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            <input type="date" class="form-control" name="purchase_date" max="2999-12-31">
                        </div>
                    </div>
                <?php } ?>

                <div class="mb-3">
                    <label>Install Date</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                        <input type="date" class="form-control" name="install_date" max="2999-12-31">
                    </div>
                </div>

                <?php if ($type !== 'Virtual') { ?>
                    <div class="mb-3">
                        <label>Warranty Expire</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                            <input type="date" class="form-control" name="warranty_expire" max="2999-12-31">
                        </div>
                    </div>
                <?php } ?>

            </div>

            <div class="tab-pane fade" id="pills-asset-login">

                <div class="mb-3">
                    <label>Username</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        <input type="text" class="form-control" name="username" placeholder="e.g. admin" maxlength="350">
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

            <div class="tab-pane fade" id="pills-asset-notes">

                <div class="mb-3">
                    <textarea class="form-control" rows="8" placeholder="Additional notes or configuration details" name="notes"></textarea>
                </div>

                <div class="mb-3">
                    <label>Upload Photo</label>
                    <input type="file" class="form-control" name="file" accept="image/*">
                </div>

                <div class="mb-3">
                    <label>Tags</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                        <select class="form-control select2" name="tags[]" data-placeholder="Add some tags" multiple>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_tags_select)) {
                                $tag_id = intval($row['tag_id']);
                                $tag_name = escapeHtml($row['tag_name']);
                                ?>
                                <option value="<?= $tag_id ?>"><?= $tag_name ?></option>
                            <?php } ?>

                        </select>
                            <button class="btn btn-secondary ajax-modal" type="button"
                                data-modal-url="../admin/modals/tag/tag_add.php?type=5">
                                <i class="fas fa-plus"></i>
                            </button>
                    </div>
                </div>


            </div>

        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_asset" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<!-- JSON Autocomplete / type ahead -->
<link rel="stylesheet" href="/libs/jquery-ui/jquery-ui.min.css">
<script src="/libs/jquery-ui/jquery-ui.min.js"></script>
<script>
    $(function() {
        var operatingSystems = <?= $json_os ?>;
        $("#os").autocomplete({
            source: operatingSystems,  // Should be an array of objects with 'label' and 'value'
            select: function(event, ui) {
                $("#os").val(ui.item.label); // Set the input field value to the selected label
                return false;
            }
        });
    });
</script>

<?php

require_once '../../../includes/modal_footer.php';
