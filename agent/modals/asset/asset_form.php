<?php
/*
 * Asset form fields - shared by asset_add.php, asset_edit.php and
 * asset_copy.php, which set $asset_form_mode ('add' | 'edit' | 'copy'),
 * $client_id (0 = pick the client here) and the $asset_* values (escaped,
 * empty for a new asset) before including this. Posts the field set
 * agent/post/asset_model.php reads.
 *
 * Tabs like the other add / edit modals: Details, Assignment, Network,
 * Purchase, then Login on a new asset or History on an existing one, and
 * Notes. Every field is always in the form, whatever the asset type: fields
 * that came and went with the type used to drop out of the POST entirely,
 * and the handler then saved them as empty - editing an access point wiped
 * its firmware version.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

// Status - from Admin > Categories, keeping a retired value that is still set
$status_options = [];
$sql_status_select = mysqli_query($mysqli, "SELECT category_name, category_color, category_icon FROM categories
    WHERE category_type = 'asset_status' AND category_archived_at IS NULL
    ORDER BY category_order ASC, category_name ASC");
$status_colors = [];
while ($row = mysqli_fetch_assoc($sql_status_select)) {
    $status_options[escapeHtml($row['category_name'])] = selectOptionColor($row['category_color'] ?? '', $row['category_icon'] ?? '');
    $status_colors[$row['category_name']] = assetStatusColor($row['category_name'], $row['category_color'] ?? '');
}
if ($asset_status !== '' && !isset($status_options[$asset_status])) {
    $status_options[$asset_status] = '';
}

// The client's own lists - only once the client is known
$location_options = $contact_options = $network_options = $vendor_options = [];
if ($client_id) {
    $sql = mysqli_query($mysqli, "SELECT location_archived_at, location_id, location_name FROM locations
        WHERE location_client_id = $client_id AND (location_archived_at IS NULL OR location_id = $asset_location_id)
        ORDER BY location_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $location_options[] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT contact_archived_at, contact_id, contact_name, contact_client_id, contact_photo, contact_title FROM contacts
        WHERE contact_client_id = $client_id AND (contact_archived_at IS NULL OR contact_id = $asset_contact_id)
        ORDER BY contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $contact_options[] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT network, network_archived_at, network_id, network_name FROM networks
        WHERE network_client_id = $client_id AND (network_archived_at IS NULL OR network_id = $asset_network_id)
        ORDER BY network_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $network_options[] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT vendor_archived_at, vendor_id, vendor_name, vendor_description FROM vendors
        WHERE vendor_client_id = $client_id AND (vendor_archived_at IS NULL OR vendor_id = $asset_vendor_id)
        ORDER BY vendor_favorite DESC, vendor_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $vendor_options[] = $row;
    }
} else {
    $client_options = [];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $client_options[] = $row;
    }
}

// OS suggestions - what's already in use, most common first
$os_suggestions = [];
$sql = mysqli_query($mysqli, "SELECT asset_os FROM assets WHERE asset_archived_at IS NULL AND asset_os IS NOT NULL AND asset_os != '' GROUP BY asset_os ORDER BY COUNT(*) DESC LIMIT 200");
while ($row = mysqli_fetch_assoc($sql)) {
    $os_suggestions[] = ['label' => $row['asset_os'], 'value' => $row['asset_os']];
}

$sql_tags_select = mysqli_query($mysqli, "SELECT tag_id, tag_name, tag_color, tag_icon FROM tags WHERE tag_type = 5 ORDER BY tag_name ASC");

$asset_ip_is_dhcp = $asset_ip === 'DHCP';

// Leading icons that follow what is picked or typed: the status in its
// colour, the OS brand and the kind of link - the same as the assets list
// shows them (functions/asset.php). Type needs none, its picker already shows
// the type's icon. These are the starting ones; the script at the end keeps
// them in step.
$asset_form_icons = [
    'status' => escapeHtml($status_colors[html_entity_decode($asset_status)] ?? 'var(--bs-secondary-color)'),
    'os' => assetOsIcon(html_entity_decode($asset_os)),
    'uri' => $asset_uri !== '' ? assetLinkIcon(html_entity_decode($asset_uri)) : 'fas fa-link',
    'uri_2' => $asset_uri_2 !== '' ? assetLinkIcon(html_entity_decode($asset_uri_2)) : 'fas fa-link',
];

?>

<!-- Tabs, like the other add / edit modals - Login on a new asset, History on an existing one -->
<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#assetFormDetails"><i class="fas fa-fw fa-desktop me-2 d-none d-lg-inline"></i>Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#assetFormAssignment"><i class="fas fa-fw fa-map-marker-alt me-2 d-none d-lg-inline"></i>Assignment</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#assetFormNetwork"><i class="fas fa-fw fa-network-wired me-2 d-none d-lg-inline"></i>Network</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#assetFormPurchase"><i class="fas fa-fw fa-receipt me-2 d-none d-lg-inline"></i>Purchase</a>
    </li>
    <?php if ($asset_form_mode !== 'edit') { ?>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="pill" href="#assetFormLogin"><i class="fas fa-fw fa-key me-2 d-none d-lg-inline"></i>Login</a>
        </li>
    <?php } ?>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#assetFormNotes"><i class="fas fa-fw fa-sticky-note me-2 d-none d-lg-inline"></i>Notes</a>
    </li>
    <?php if ($asset_form_mode === 'edit') { ?>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="pill" href="#assetFormHistory"><i class="fas fa-fw fa-history me-2 d-none d-lg-inline"></i>History</a>
        </li>
    <?php } ?>
</ul>

<div class="tab-content">

<!-- What it is -->
<div class="tab-pane fade show active" id="assetFormDetails">
    <div class="row g-3">
        <?php if (!$client_id) { ?>
            <div class="col-12">
                <label class="form-label">Client <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client_id" required>
                        <option value="">- Select Client -</option>
                        <?php foreach ($client_options as $client_option) { ?>
                            <option value="<?= intval($client_option['client_id']) ?>"<?= selectOptionClient($client_option['client_id'], $client_option['client_name'], $client_option['client_abbreviation'] ?? '') ?>><?= escapeHtml($client_option['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Pick the asset's location, contact, network and vendor once it's saved.</div>
            </div>
        <?php } ?>
        <div class="col-md-4">
            <label class="form-label">Type <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-layer-group fa-fw"></i></span>
                <select class="form-select select2" name="type" required>
                    <option value="">- Select Type -</option>
                    <?php foreach ($asset_types_array as $asset_type_select => $asset_icon_select) { ?>
                        <option <?php if ($asset_type_select === html_entity_decode($asset_type)) { echo "selected"; } ?><?= selectOptionData(['icon' => "fas $asset_icon_select"]) ?>><?= $asset_type_select ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-id-badge fa-fw"></i></span>
                <input type="text" class="form-control" name="name" placeholder="SRV-DC01" maxlength="200" value="<?= $asset_name ?>" required <?= $asset_form_mode === 'edit' ? '' : 'autofocus' ?>>
                <div class="input-group-text">
                    <label class="star-toggle mb-0" title="Favorite - pinned to the client overview">
                        <input class="form-check-input" type="checkbox" name="favorite" value="1" <?= $asset_favorite ? 'checked' : '' ?>><i class="far fa-star"></i>
                    </label>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-circle fa-fw" data-asset-form-icon="status" style="color: <?= $asset_form_icons['status'] ?>"></i></span>
                <select class="form-select select2" name="status">
                    <option value="">- Status -</option>
                    <?php foreach ($status_options as $status_option => $status_option_data) { ?>
                        <option <?php if ($status_option === $asset_status) { echo "selected"; } ?><?= $status_option_data ?>><?= $status_option ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="col-md-7">
            <label class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Domain controller for HQ" maxlength="255" value="<?= $asset_description ?>">
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label">Tags</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                <select class="form-select select2" name="tags[]" data-placeholder="Add some tags" multiple>
                    <?php while ($row = mysqli_fetch_assoc($sql_tags_select)) {
                        $tag_id_select = intval($row['tag_id']); ?>
                        <option value="<?= $tag_id_select ?>" <?php if (in_array($tag_id_select, $asset_tag_id_array)) { echo "selected"; } ?><?= selectOptionColor($row['tag_color'] ?? '', $row['tag_icon'] ?? '') ?>><?= escapeHtml($row['tag_name']) ?></option>
                    <?php } ?>
                </select>
                <button class="btn btn-secondary ajax-modal" title="New tag" type="button" data-modal-url="../admin/modals/tag/tag_add.php?type=5">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="itflow-form-section mt-4">Hardware</div>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Make</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-industry fa-fw"></i></span>
                <input type="text" class="form-control" name="make" placeholder="Dell" maxlength="200" value="<?= $asset_make ?>">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Model</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-box fa-fw"></i></span>
                <input type="text" class="form-control" name="model" placeholder="PowerEdge R650" maxlength="200" value="<?= $asset_model ?>">
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label">Serial number</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
                <input type="text" class="form-control font-monospace" name="serial" placeholder="ABC1234XYZ" maxlength="200" value="<?= $asset_serial ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Operating system / firmware</label>
            <div class="input-group">
                <span class="input-group-text"><i class="<?= $asset_form_icons['os'] ?> fa-fw" data-asset-form-icon="os"></i></span>
                <input type="text" class="form-control" name="os" id="assetFormOs" placeholder="Windows 11 Pro 24H2, Ubuntu 24.04, UniFi firmware 7.0" maxlength="200" value="<?= $asset_os ?>">
            </div>
        </div>
    </div>

    <?php if ($asset_form_mode === 'edit') { ?>
        <p class="text-body-secondary small text-end mt-3 mb-0">Asset ID <span class="font-monospace"><?= $asset_id ?></span></p>
    <?php } ?>
</div>

<!-- Where it is and who has it -->
<div class="tab-pane fade" id="assetFormAssignment">
    <?php if ($client_id) { ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Location</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-map-marker-alt fa-fw"></i></span>
                    <select class="form-select select2" name="location">
                        <option value="">- Select Location -</option>
                        <?php foreach ($location_options as $location_option) {
                            $location_option_id = intval($location_option['location_id']); ?>
                            <option value="<?= $location_option_id ?>" <?php if ($asset_location_id === $location_option_id) { echo "selected"; } ?>><?= escapeHtml($location_option['location_name']) ?><?= $location_option['location_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Where in the building</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-door-open fa-fw"></i></span>
                    <input type="text" class="form-control" name="physical_location" placeholder="Floor 2, comms closet B" maxlength="200" value="<?= $asset_physical_location ?>">
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Assigned to</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user fa-fw"></i></span>
                    <select class="form-select select2" name="contact">
                        <option value="">- Nobody -</option>
                        <?php foreach ($contact_options as $contact_option) {
                            $contact_option_id = intval($contact_option['contact_id']); ?>
                            <option value="<?= $contact_option_id ?>" <?php if ($asset_contact_id === $contact_option_id) { echo "selected"; } ?><?= selectOptionContact($contact_option['contact_client_id'] ?? 0, $contact_option['contact_name'], $contact_option['contact_photo'] ?? '', $contact_option['contact_title'] ?? '', $contact_option['contact_id'] ?? 0) ?>><?= escapeHtml($contact_option['contact_name']) ?><?= $contact_option['contact_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Where in the building</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-door-open fa-fw"></i></span>
                    <input type="text" class="form-control" name="physical_location" placeholder="Floor 2, comms closet B" maxlength="200" value="<?= $asset_physical_location ?>">
                </div>
                <div class="form-text">The location and who it's assigned to come from the client - pick them once it's saved.</div>
            </div>
        </div>
    <?php } ?>
</div>

<!-- How to reach it -->
<div class="tab-pane fade" id="assetFormNetwork">
    <p class="text-body-secondary small">The primary interface. Add more ports from the asset's Interfaces tab.</p>
    <div class="row g-3">
        <?php if ($client_id) { ?>
            <div class="col-md-6">
                <label class="form-label">Network</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                    <select class="form-select select2" name="network">
                        <option value="">- Select Network -</option>
                        <?php foreach ($network_options as $network_option) {
                            $network_option_id = intval($network_option['network_id']); ?>
                            <option value="<?= $network_option_id ?>" <?php if ($asset_network_id === $network_option_id) { echo "selected"; } ?><?= selectOptionData(['description' => $network_option['network']]) ?>><?= escapeHtml($network_option['network_name']) ?><?= $network_option['network_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        <?php } ?>
        <div class="col-md-6">
            <label class="form-label">IPv4 address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-globe fa-fw"></i></span>
                <input type="text" class="form-control font-monospace" name="ip" id="assetFormIp" placeholder="192.168.1.10" maxlength="200" value="<?= $asset_ip_is_dhcp ? '' : $asset_ip ?>" data-inputmask="'alias': 'ip'" data-mask <?= $asset_ip_is_dhcp ? 'disabled' : '' ?>>
                <div class="input-group-text">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="dhcp" value="1" id="assetFormDhcp" <?= $asset_ip_is_dhcp ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="assetFormDhcp">DHCP</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">MAC address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                <input type="text" class="form-control font-monospace" name="mac" placeholder="00:1A:2B:3C:4D:5E" maxlength="200" value="<?= $asset_mac ?>" data-inputmask="'alias': 'mac'" data-mask>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">NAT address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-exchange-alt"></i></span>
                <input type="text" class="form-control font-monospace" name="nat_ip" placeholder="203.0.113.10" maxlength="200" value="<?= $asset_nat_ip ?>" data-inputmask="'alias': 'ip'" data-mask>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">IPv6 address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-globe fa-fw"></i></span>
                <input type="text" class="form-control font-monospace" name="ipv6" placeholder="2001:db8::1" maxlength="200" value="<?= $asset_ipv6 ?>">
            </div>
        </div>
    </div>

    <div class="itflow-form-section mt-4">Links</div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Link</label>
            <div class="input-group">
                <span class="input-group-text"><i class="<?= $asset_form_icons['uri'] ?> fa-fw" data-asset-form-icon="link"></i></span>
                <input type="text" class="form-control" name="uri" placeholder="https://, ssh://, rdp://" maxlength="500" value="<?= $asset_uri ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Second link</label>
            <div class="input-group">
                <span class="input-group-text"><i class="<?= $asset_form_icons['uri_2'] ?> fa-fw" data-asset-form-icon="link"></i></span>
                <input type="text" class="form-control" name="uri_2" placeholder="https://, ssh://, rdp://" maxlength="500" value="<?= $asset_uri_2 ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Client link</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-share-square fa-fw"></i></span>
                <input type="text" class="form-control" name="uri_client" placeholder="https://" maxlength="500" value="<?= $asset_uri_client ?>">
            </div>
            <div class="form-text">Shown to the client in their portal.</div>
        </div>
    </div>
</div>

<!-- Where it came from -->
<div class="tab-pane fade" id="assetFormPurchase">
    <div class="row g-3">
        <?php if ($client_id) { ?>
            <div class="col-md-6">
                <label class="form-label">Vendor</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    <select class="form-select select2" name="vendor">
                        <option value="">- Select Vendor -</option>
                        <?php foreach ($vendor_options as $vendor_option) {
                            $vendor_option_id = intval($vendor_option['vendor_id']); ?>
                            <option value="<?= $vendor_option_id ?>" <?php if ($asset_vendor_id === $vendor_option_id) { echo "selected"; } ?><?= selectOptionVendor($vendor_option['vendor_name'], $vendor_option['vendor_description']) ?>><?= escapeHtml($vendor_option['vendor_name']) ?><?= $vendor_option['vendor_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        <?php } ?>
        <div class="col-md-6">
            <label class="form-label">Order / invoice reference</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-receipt"></i></span>
                <input type="text" class="form-control" name="purchase_reference" placeholder="INV-1045, PO-7782" maxlength="200" value="<?= $asset_purchase_reference ?>">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Purchased</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-shopping-cart fa-fw"></i></span>
                <input type="date" class="form-control" name="purchase_date" max="2999-12-31" value="<?= $asset_purchase_date ?>">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Installed</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-tools fa-fw"></i></span>
                <input type="date" class="form-control" name="install_date" max="2999-12-31" value="<?= $asset_install_date ?>">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Warranty expires</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-shield-alt fa-fw"></i></span>
                <input type="date" class="form-control" name="warranty_expire" max="2999-12-31" value="<?= $asset_warranty_expire ?>">
            </div>
        </div>
    </div>
</div>

<?php if ($asset_form_mode !== 'edit') { ?>
    <!-- A login to keep with it -->
    <div class="tab-pane fade" id="assetFormLogin">
        <p class="text-body-secondary small">Saved as a credential on the asset. Leave it empty to skip.</p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <input type="text" class="form-control" name="username" placeholder="admin" maxlength="350" autocomplete="off">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                    <input type="text" class="form-control" name="password" placeholder="Leave empty to skip" maxlength="350" autocomplete="off">
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<!-- Notes and a photo -->
<div class="tab-pane fade" id="assetFormNotes">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" rows="7" placeholder="Configuration details, quirks, anything worth knowing" name="notes"><?= $asset_notes ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Photo</label>
            <?php if ($asset_photo) { ?>
                <div class="mb-2"><img class="img-thumbnail" style="max-height: 7rem" alt="Current photo" src="<?= "../uploads/clients/$client_id/$asset_photo" ?>"></div>
            <?php } ?>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-camera fa-fw"></i></span>
                <input type="file" class="form-control" name="file" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>
            <?php if ($asset_photo) { ?>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="assetFormRemovePhoto">
                    <label class="form-check-label" for="assetFormRemovePhoto">Remove this photo</label>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php if ($asset_form_mode === 'edit') { ?>
    <div class="tab-pane fade" id="assetFormHistory">
        <?php if (mysqli_num_rows($sql_asset_history)) { ?>
            <ul class="list-group list-group-flush">
                <?php while ($row = mysqli_fetch_assoc($sql_asset_history)) { ?>
                    <li class="list-group-item px-0">
                        <div class="d-flex justify-content-between gap-2">
                            <span><?= escapeHtml($row['asset_history_description']) ?></span>
                            <?php if ($row['asset_history_status']) { ?>
                                <?= assetStatusPill($row['asset_history_status']) ?>
                            <?php } ?>
                        </div>
                        <div class="small text-body-secondary" title="<?= escapeHtml($row['asset_history_created_at']) ?>"><?= timeAgo($row['asset_history_created_at']) ?> &middot; <?= escapeHtml($row['asset_history_created_at']) ?></div>
                    </li>
                <?php } ?>
            </ul>
        <?php } else { ?>
            <p class="text-body-secondary text-center my-4">No history recorded yet.</p>
        <?php } ?>
    </div>
<?php } ?>
</div>

<script>
    itflowReady(function () {
        var form = document.getElementById('assetFormOs').closest('form');

        // OS / firmware suggestions from what's already in use
        var osInput = form.querySelector('#assetFormOs');
        itflowAutocomplete(osInput, {
            minLength: 1,
            source: <?= json_encode($os_suggestions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            onSelect: function (item) {
                osInput.value = item.label;
            }
        });

        // A required field on a tab that isn't showing: show that tab and point
        // at the field - otherwise the browser just quietly refuses to send
        var reported = false;
        form.addEventListener('invalid', function (e) {
            var pane = e.target.closest('.tab-pane');
            var tab = pane && form.querySelector('[data-bs-toggle="pill"][href="#' + pane.id + '"]');
            if (!tab || pane.classList.contains('active') || reported) {
                return;
            }
            reported = true;
            tab.addEventListener('shown.bs.tab', function () {
                reported = false;
                e.target.reportValidity();
            }, { once: true });
            bootstrap.Tab.getOrCreateInstance(tab).show();
        }, true);

        // Leading icons that follow the field: the status colour, the OS
        // brand, the kind of link
        var icons = {
            status: <?= json_encode($status_colors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            os: <?= json_encode(assetOsIconMap(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            link: <?= json_encode(assetLinkIconMap(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        };
        function setIcon(icon, classes) {
            icon.className = classes + ' fa-fw';
        }
        function follow(field, kind, update) {
            var group = field.closest('.input-group');
            var icon = group && group.querySelector('[data-asset-form-icon="' + kind + '"]');
            if (!icon) {
                return;
            }
            ['input', 'change'].forEach(function (type) {
                field.addEventListener(type, function () {
                    update(icon, field.value);
                });
            });
        }
        follow(form.querySelector('[name="status"]'), 'status', function (icon, value) {
            icon.style.color = icons.status[value] || 'var(--bs-secondary-color)';
        });
        follow(osInput, 'os', function (icon, value) {
            var os = value.toLowerCase();
            var match = Object.keys(icons.os).find(function (needle) {
                return os.indexOf(needle) !== -1;
            });
            setIcon(icon, match ? icons.os[match] : 'fas fa-compact-disc');
        });
        form.querySelectorAll('[name="uri"], [name="uri_2"]').forEach(function (field) {
            follow(field, 'link', function (icon, value) {
                var scheme = (value.match(/^([a-z][a-z0-9+.-]*):/i) || [])[1];
                setIcon(icon, value.trim() === '' ? 'fas fa-link' : (icons.link[(scheme || '').toLowerCase()] || 'fas fa-external-link-alt'));
            });
        });

        // DHCP means no fixed address to type
        var dhcp = form.querySelector('#assetFormDhcp');
        var ip = form.querySelector('#assetFormIp');
        dhcp.addEventListener('change', function () {
            ip.disabled = dhcp.checked;
            if (!dhcp.checked) {
                ip.focus();
            }
        });
    });
</script>
