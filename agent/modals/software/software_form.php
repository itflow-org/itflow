<?php
/*
 * License form fields - shared by software_add.php and software_edit.php,
 * which set $software_form_mode ('add' | 'edit'), $client_id (0 = pick the
 * client here), $software_id and the $software_* values (escaped, empty for a
 * new license), plus $software_asset_ids / $software_contact_ids (ints) for
 * the seats, before including this. Posts what softwareFormSet()
 * (functions/software.php) reads.
 *
 * Tabs: Details (with its tags), License (type, seats, key, dates), Seats (the client's
 * devices and users, with a running count against the seats bought) and
 * Notes. A new license can start from a template, which fills in the details,
 * and take a batch of individual keys, one per line.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

// Software types - from Admin > Categories, keeping one the license has that is archived or isn't a type any more
$software_type_raw = html_entity_decode($software_type, ENT_QUOTES, 'UTF-8');
$type_options = array_map('strval', array_keys(categoryPicker('software_type', [$software_type_raw])));

// License types - the usual ones, keeping whatever this one already has
$license_type_options = array_keys(SOFTWARE_LICENSE_TYPES);
$software_license_type_raw = html_entity_decode($software_license_type, ENT_QUOTES, 'UTF-8');
if ($software_license_type_raw !== '' && !in_array($software_license_type_raw, $license_type_options, true)) {
    $license_type_options[] = $software_license_type_raw;
}

// The client's own lists - only once the client is known
$vendor_options = $asset_options = $contact_options = [];
if ($client_id) {
    $sql = mysqli_query($mysqli, "SELECT vendor_archived_at, vendor_id, vendor_name, vendor_description FROM vendors
        WHERE vendor_client_id = $client_id AND (vendor_archived_at IS NULL OR vendor_id = $software_vendor_id)
        ORDER BY vendor_favorite DESC, vendor_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $vendor_options[] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, contact_name FROM assets
        LEFT JOIN contacts ON contact_id = asset_contact_id
        WHERE asset_client_id = $client_id AND asset_archived_at IS NULL
        ORDER BY asset_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $asset_options[] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_photo, contact_client_id FROM contacts
        WHERE contact_client_id = $client_id AND contact_archived_at IS NULL
        ORDER BY contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $contact_options[] = $row;
    }
} else {
    $client_options = [];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $client_options[] = $row;
    }
}

// Templates (Admin > License Templates) to start a new license from
$template_options = [];
if ($software_form_mode === 'add') {
    $sql = mysqli_query($mysqli, "SELECT software_template_id, software_template_name, software_template_version, software_template_description,
            software_template_type, software_template_license_type, software_template_notes
        FROM software_templates WHERE software_template_archived_at IS NULL ORDER BY software_template_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $template_options[intval($row['software_template_id'])] = [
            'name' => $row['software_template_name'],
            'version' => (string) $row['software_template_version'],
            'description' => (string) $row['software_template_description'],
            'type' => $row['software_template_type'],
            'license_type' => (string) $row['software_template_license_type'],
            'notes' => (string) $row['software_template_notes'],
        ];
    }
}

$software_type_style = softwareTypeStyle($software_type);
$form_id = 'softwareForm' . ($software_form_mode === 'edit' ? $software_id : 'New');

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-cube me-2 d-none d-lg-inline"></i>Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>License"><i class="fas fa-fw fa-key me-2 d-none d-lg-inline"></i>License</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Seats"><i class="fas fa-fw fa-users me-2 d-none d-lg-inline"></i>Seats <span class="badge rounded-pill text-bg-secondary ms-1" data-seat-badge></span></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Notes"><i class="fas fa-fw fa-sticky-note me-2 d-none d-lg-inline"></i>Notes</a>
    </li>
</ul>

<div class="tab-content" id="<?= $form_id ?>">

<!-- What it is -->
<div class="tab-pane fade show active" id="<?= $form_id ?>Details">
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
                <div class="form-text">Pick the vendor and who is on it once it's saved.</div>
            </div>
        <?php } ?>

        <?php if ($template_options) { ?>
            <div class="col-12">
                <label class="form-label">Start from a template</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-puzzle-piece"></i></span>
                    <select class="form-select select2" data-software-template data-placeholder="- None - fill it in yourself -">
                        <option value="">- None -</option>
                        <?php foreach ($template_options as $template_id => $template) { ?>
                            <option value="<?= $template_id ?>" <?= intval($_GET['template_id'] ?? 0) === $template_id ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . softwareTypeStyle($template['type'])['icon'], 'description' => implode(' · ', array_filter([$template['type'], $template['license_type']]))]) ?>><?= escapeHtml($template['name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Fills in the name, type, license type, description and notes - change anything after.</div>
            </div>
        <?php } ?>

        <div class="col-md-8">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw <?= $software_type_style['icon'] ?>" data-software-type-icon></i></span>
                <input type="text" class="form-control" name="name" placeholder="Microsoft 365 Business Premium" maxlength="200" value="<?= $software_name ?>" required <?= $software_form_mode === 'edit' ? '' : 'autofocus' ?>>
                <div class="input-group-text">
                    <label class="star-toggle mb-0" title="Favorite - pinned to the client overview">
                        <input class="form-check-input" type="checkbox" name="favorite" value="1" <?= $software_favorite ? 'checked' : '' ?>><i class="far fa-star"></i>
                    </label>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Version</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-code-branch"></i></span>
                <input type="text" class="form-control" name="version" placeholder="2024, 16.0, LTSC" maxlength="200" value="<?= $software_version ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Type <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-layer-group"></i></span>
                <select class="form-select select2" name="type" required>
                    <option value="">- Select Type -</option>
                    <?= categoryOptionsHtml('software_type', $software_type_raw) ?>
                </select>
                <?= categoryAddButtonHtml('software_type', 'btn btn-secondary') ?>
            </div>
        </div>
        <?php if ($client_id) { ?>
            <div class="col-md-6">
                <label class="form-label">Vendor</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    <select class="form-select select2" name="vendor">
                        <option value="">- Select Vendor -</option>
                        <?php foreach ($vendor_options as $vendor_option) {
                            $vendor_option_id = intval($vendor_option['vendor_id']); ?>
                            <option value="<?= $vendor_option_id ?>" <?= $software_vendor_id === $vendor_option_id ? 'selected' : '' ?><?= selectOptionVendor($vendor_option['vendor_name'], $vendor_option['vendor_description']) ?>><?= escapeHtml($vendor_option['vendor_name']) ?><?= $vendor_option['vendor_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        <?php } ?>
        <div class="col-12">
            <label class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Email, Office apps and Intune for the whole office" value="<?= $software_description ?>">
            </div>
        </div>
        <div class="col-12">
            <?= tagFormFieldHtml('license', $software_form_mode === 'edit' ? tagIdsForRecord('license', $software_id) : [], 'Core stack, Client-paid, Annual true-up') ?>
        </div>
    </div>

    <?php if ($software_form_mode === 'edit') { ?>
        <p class="text-body-secondary small text-end mt-3 mb-0">License ID <span class="font-monospace"><?= $software_id ?></span></p>
    <?php } ?>
</div>

<!-- The license: what kind, how many, the key, when it renews -->
<div class="tab-pane fade" id="<?= $form_id ?>License">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">License type</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-certificate"></i></span>
                <select class="form-select select2" name="license_type">
                    <option value="">- Select License Type -</option>
                    <?php foreach ($license_type_options as $license_type_option) { ?>
                        <option <?= $license_type_option === $software_license_type_raw ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas ' . softwareLicenseTypeIcon($license_type_option)]) ?>><?= escapeHtml($license_type_option) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Seats bought</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-users"></i></span>
                <input type="number" class="form-control" name="seats" min="0" step="1" placeholder="No limit" value="<?= $software_seats ?>" data-seat-total>
            </div>
            <div class="form-text">Leave blank for a site or unlimited license.</div>
        </div>
        <div class="col-12">
            <label class="form-label">License key</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-key"></i></span>
                <input type="text" class="form-control font-monospace" name="key" placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX" maxlength="200" value="<?= $software_key ?>">
            </div>
            <div class="form-text">The one key for the whole license, if it has one.<?= $software_form_mode === 'edit' ? ' A key per device or user goes on the license\'s Keys tab.' : '' ?></div>
        </div>
        <?php if ($software_form_mode === 'add') { ?>
            <div class="col-12">
                <label class="form-label">Individual keys</label>
                <textarea class="form-control font-monospace" name="keys" rows="3" placeholder="One key per line - for a license with a key for each device or user"></textarea>
                <div class="form-text">Hand each one to a device or user from the license's Keys tab.</div>
            </div>
        <?php } ?>
        <div class="col-md-6">
            <label class="form-label">Order / invoice reference</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-receipt"></i></span>
                <input type="text" class="form-control" name="purchase_reference" placeholder="INV-1045, PO-7782, contract number" maxlength="200" value="<?= $software_purchase_reference ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Purchased</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-shopping-cart"></i></span>
                <input type="date" class="form-control" name="purchase" max="2999-12-31" value="<?= $software_purchase ?>" data-software-purchase>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Renews / expires</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-calendar-alt"></i></span>
                <input type="date" class="form-control" name="expire" max="2999-12-31" value="<?= $software_expire ?>" data-software-expire>
                <button type="button" class="btn btn-outline-secondary" data-renew-term="1m" title="A month after it was bought (or today)">1 mo</button>
                <button type="button" class="btn btn-outline-secondary" data-renew-term="1y" title="A year after it was bought (or today)">1 yr</button>
                <button type="button" class="btn btn-outline-secondary" data-renew-term="3y" title="Three years after it was bought (or today)">3 yr</button>
            </div>
            <div class="form-text">Shows on the client overview's renewals and the dashboard as it gets close.</div>
        </div>
    </div>
</div>

<!-- Who and what is on it -->
<div class="tab-pane fade" id="<?= $form_id ?>Seats">
    <?php if ($client_id) { ?>
        <input type="hidden" name="seats_form" value="1">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <span class="text-body-secondary small">Each device and user here takes a seat.</span>
            <span class="badge rounded-pill fs-6 fw-normal" data-seat-count></span>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Devices</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-desktop"></i></span>
                    <select class="form-select select2" name="assets[]" multiple data-placeholder="<?= $asset_options ? 'Pick the devices it is installed on' : 'This client has no assets yet' ?>" data-seat-pick>
                        <?php foreach ($asset_options as $asset_option) {
                            $asset_option_id = intval($asset_option['asset_id']); ?>
                            <option value="<?= $asset_option_id ?>" <?= in_array($asset_option_id, $software_asset_ids, true) ? 'selected' : '' ?><?= selectOptionAsset($asset_option['asset_type'], implode(' · ', array_filter([trim($asset_option['asset_make'] . ' ' . $asset_option['asset_model']), $asset_option['contact_name']]))) ?>><?= escapeHtml($asset_option['asset_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Users</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="contacts[]" multiple data-placeholder="<?= $contact_options ? 'Pick the people licensed to use it' : 'This client has no contacts yet' ?>" data-seat-pick>
                        <?php foreach ($contact_options as $contact_option) {
                            $contact_option_id = intval($contact_option['contact_id']); ?>
                            <option value="<?= $contact_option_id ?>" <?= in_array($contact_option_id, $software_contact_ids, true) ? 'selected' : '' ?><?= selectOptionContact($contact_option['contact_client_id'], $contact_option['contact_name'], $contact_option['contact_photo'], $contact_option['contact_title'], $contact_option_id) ?>><?= escapeHtml($contact_option['contact_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <p class="text-body-secondary text-center my-4">Pick the devices and users once it's saved - they come from the client.</p>
    <?php } ?>
</div>

<div class="tab-pane fade" id="<?= $form_id ?>Notes">
    <textarea class="form-control" rows="12" placeholder="Admin portal, how it is billed, who to call to renew..." name="notes"><?= $software_notes ?></textarea>
</div>

</div>

<script>
    itflowReady(function () {
        var box = document.getElementById(<?= json_encode($form_id) ?>);
        var form = box.closest('form');

        // A required field on a tab that isn't showing: show that tab and point at it
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

        // Seats picked against seats bought, on the tab and above the pickers
        var total = form.querySelector('[data-seat-total]');
        var picks = form.querySelectorAll('[data-seat-pick]');
        var count = form.querySelector('[data-seat-count]');
        var badge = form.querySelector('[data-seat-badge]');
        function seats() {
            var used = 0;
            picks.forEach(function (select) {
                used += select.selectedOptions.length;
            });
            var bought = parseInt(total.value, 10) || 0;
            var over = bought && used > bought;
            var text = bought ? used + ' of ' + bought + ' seats' : used + ' in use';
            if (count) {
                count.textContent = text + (over ? ' - ' + (used - bought) + ' over' : '');
                count.className = 'badge rounded-pill fs-6 fw-normal ' + (over ? 'text-bg-danger' : (bought && used === bought ? 'text-bg-warning' : 'text-bg-secondary'));
            }
            badge.textContent = picks.length ? (bought ? used + '/' + bought : used) : '';
            badge.className = 'badge rounded-pill ms-1 ' + (over ? 'text-bg-danger' : 'text-bg-secondary');
        }
        picks.forEach(function (select) {
            select.addEventListener('change', seats);
            select.addEventListener('input', seats);
        });
        total.addEventListener('input', seats);
        seats();

        // The name's icon follows the type
        var typeIcons = <?= json_encode(array_combine($type_options, array_map(function ($type) { return softwareTypeStyle($type)['icon']; }, $type_options)) ?: new stdClass(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var type = form.querySelector('[name="type"]');
        var typeIcon = form.querySelector('[data-software-type-icon]');
        type.addEventListener('change', function () {
            typeIcon.className = 'fas fa-fw ' + (typeIcons[type.value] || 'fa-cube');
        });

        // Renewal a term after it was bought - or after today
        var purchase = form.querySelector('[data-software-purchase]');
        var expire = form.querySelector('[data-software-expire]');
        form.querySelectorAll('[data-renew-term]').forEach(function (button) {
            button.addEventListener('click', function () {
                var from = purchase.value ? new Date(purchase.value + 'T00:00:00') : new Date();
                var term = button.dataset.renewTerm;
                if (term.slice(-1) === 'y') {
                    from.setFullYear(from.getFullYear() + parseInt(term, 10));
                } else {
                    from.setMonth(from.getMonth() + parseInt(term, 10));
                }
                var pad = function (n) { return (n < 10 ? '0' : '') + n; };
                expire.value = from.getFullYear() + '-' + pad(from.getMonth() + 1) + '-' + pad(from.getDate());
                expire.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        // Starting from a template fills in what it knows
        var templates = <?= json_encode($template_options ?: new stdClass(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var picker = form.querySelector('[data-software-template]');
        function applyTemplate() {
            var t = templates[picker.value];
            if (!t) {
                return;
            }
            form.querySelector('[name="name"]').value = t.name;
            form.querySelector('[name="version"]').value = t.version;
            form.querySelector('[name="description"]').value = t.description;
            form.querySelector('[name="notes"]').value = t.notes;
            [['type', t.type], ['license_type', t.license_type]].forEach(function (pair) {
                var select = form.querySelector('[name="' + pair[0] + '"]');
                if (!pair[1]) {
                    return;
                }
                if (!Array.prototype.some.call(select.options, function (o) { return o.value === pair[1]; })) {
                    select.add(new Option(pair[1], pair[1]));
                }
                select.value = pair[1];
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
        if (picker) {
            picker.addEventListener('change', applyTemplate);
            applyTemplate();
        }
    });
</script>
