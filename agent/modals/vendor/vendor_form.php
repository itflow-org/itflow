<?php
/*
 * Vendor form fields - shared by vendor_add.php and vendor_edit.php, which set
 * $vendor_form_mode ('add' | 'edit'), $client_id (the vendor's client, 0 for
 * one of ours), $vendor_pick_client (pick the client here - the all-clients
 * list), $vendor_id and the $vendor_* values (escaped, empty for a new vendor)
 * plus $vendor_tag_ids, before including this. Posts what vendorFormSet()
 * (functions/vendor.php) reads.
 *
 * Tabs: Details (name, star, what they are, tags, template), Support (the
 * number to ring, hours, SLA, support email and site), Account (the account
 * number and PIN they ask for, the account manager) and Notes. A new vendor
 * can start from a template, which fills every field in.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$client_options = [];
if (!empty($vendor_pick_client)) {
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $client_options[] = $row;
    }
}

// Templates, and for a new vendor what each one fills in
$template_options = [];
$template_fields = [];
$sql = mysqli_query($mysqli, "SELECT * FROM vendor_templates WHERE vendor_template_archived_at IS NULL OR vendor_template_id = " . intval($vendor_template_id) . " ORDER BY vendor_template_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $template_options[] = $row;
    if ($vendor_form_mode === 'add') {
        $template_fields[intval($row['vendor_template_id'])] = vendorTemplateFormFields($row);
    }
}

$sql_tags_select = mysqli_query($mysqli, "SELECT tag_id, tag_name, tag_color, tag_icon FROM tags WHERE tag_type = 6 ORDER BY tag_name ASC");

$form_id = 'vendorForm' . ($vendor_form_mode === 'edit' ? $vendor_id : 'New');

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-building me-2 d-none d-lg-inline"></i>Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Support"><i class="fas fa-fw fa-headset me-2 d-none d-lg-inline"></i>Support</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Account"><i class="fas fa-fw fa-id-card me-2 d-none d-lg-inline"></i>Account</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Notes"><i class="fas fa-fw fa-sticky-note me-2 d-none d-lg-inline"></i>Notes</a>
    </li>
</ul>

<div class="tab-content" id="<?= $form_id ?>">

<!-- Who they are -->
<div class="tab-pane fade show active" id="<?= $form_id ?>Details">
    <div class="row g-3">
        <?php if ($vendor_form_mode === 'add' && $template_options) { ?>
            <div class="col-12">
                <label class="form-label">Start from a template</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-puzzle-piece"></i></span>
                    <select class="form-select select2" name="vendor_template_id" data-vendor-template <?= !empty($vendor_template_focus) ? 'autofocus' : '' ?>>
                        <option value="0">- Blank vendor -</option>
                        <?php foreach ($template_options as $template_option) {
                            $template_option_id = intval($template_option['vendor_template_id']); ?>
                            <option value="<?= $template_option_id ?>" <?= intval($vendor_template_id) === $template_option_id ? 'selected' : '' ?><?= selectOptionVendor($template_option['vendor_template_name'], $template_option['vendor_template_description']) ?>><?= escapeHtml($template_option['vendor_template_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Fills in their support line, hours and site - then add this client's account number and PIN.</div>
            </div>
        <?php } ?>
        <?php if (!empty($vendor_pick_client)) { ?>
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
            </div>
        <?php } ?>
        <div class="col-md-8">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Comcast Business, Dell, Meridian Dental" maxlength="200" value="<?= $vendor_name ?>" required <?= $vendor_form_mode === 'edit' || !empty($vendor_template_focus) ? '' : 'autofocus' ?>>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label d-none d-md-block">&nbsp;</label>
            <div class="form-check form-switch mt-md-2">
                <input class="form-check-input" type="checkbox" role="switch" name="favorite" value="1" id="<?= $form_id ?>Favorite" <?= $vendor_favorite ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $form_id ?>Favorite"><i class="fas fa-star text-warning me-1"></i>Starred</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">What they are</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Primary internet circuit, practice management software" maxlength="200" value="<?= $vendor_description ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Tags</label>
            <input type="hidden" name="tags_form" value="1">
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                <select class="form-select select2" name="tags[]" data-placeholder="ISP, Telecom, Software, Hardware" multiple>
                    <?php while ($row = mysqli_fetch_assoc($sql_tags_select)) {
                        $tag_id_select = intval($row['tag_id']); ?>
                        <option value="<?= $tag_id_select ?>" <?= in_array($tag_id_select, $vendor_tag_ids, true) ? 'selected' : '' ?><?= selectOptionColor($row['tag_color'] ?? '', $row['tag_icon'] ?? '') ?>><?= escapeHtml($row['tag_name']) ?></option>
                    <?php } ?>
                </select>
                <?php if (!empty($session_is_admin)) { ?>
                    <button class="btn btn-secondary ajax-modal" title="New tag" type="button" data-modal-url="../admin/modals/tag/tag_add.php?type=6">
                        <i class="fas fa-plus"></i>
                    </button>
                <?php } ?>
            </div>
        </div>
        <?php if ($vendor_form_mode === 'edit' && $template_options) { ?>
            <div class="col-12">
                <label class="form-label">Based on template</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-puzzle-piece"></i></span>
                    <select class="form-select select2" name="vendor_template_id">
                        <option value="0">- None -</option>
                        <?php foreach ($template_options as $template_option) {
                            $template_option_id = intval($template_option['vendor_template_id']); ?>
                            <option value="<?= $template_option_id ?>" <?= intval($vendor_template_id) === $template_option_id ? 'selected' : '' ?><?= selectOptionVendor($template_option['vendor_template_name'], $template_option['vendor_template_description']) ?>><?= escapeHtml($template_option['vendor_template_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Template changes can be pushed to every vendor based on it, from Admin &rsaquo; Vendor Templates.</div>
            </div>
        <?php } elseif ($vendor_form_mode === 'edit') { ?>
            <input type="hidden" name="vendor_template_id" value="<?= intval($vendor_template_id) ?>">
        <?php } ?>
    </div>
    <?php if ($vendor_form_mode === 'edit') { ?>
        <p class="text-body-secondary small text-end mt-3 mb-0">Vendor ID <span class="font-monospace"><?= $vendor_id ?></span></p>
    <?php } ?>
</div>

<!-- How to reach support -->
<div class="tab-pane fade" id="<?= $form_id ?>Support">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Support phone</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                <input type="hidden" name="phone_country_code" value="<?= $vendor_phone_country_code ?>">
                <input type="tel" class="form-control" name="phone" value="<?= $vendor_phone ?>" placeholder="Phone number" maxlength="200" data-itflow-phone="phone_country_code">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Extension / option</label>
            <input type="text" class="form-control" name="extension" value="<?= $vendor_extension ?>" placeholder="ext." maxlength="200" inputmode="numeric">
        </div>
        <div class="col-md-6">
            <label class="form-label">Support hours</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                <input type="text" class="form-control" name="hours" placeholder="24/7, Mon-Fri 8-6 ET" maxlength="200" value="<?= $vendor_hours ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">SLA</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-handshake"></i></span>
                <input type="text" class="form-control" name="sla" placeholder="4 hour response, next business day" maxlength="200" value="<?= $vendor_sla ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Support email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                <input type="email" class="form-control" name="email" placeholder="support@vendor.com" maxlength="200" value="<?= $vendor_email ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Support site or portal</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                <input type="text" class="form-control" name="website" placeholder="support.vendor.com" maxlength="200" value="<?= $vendor_website ?>">
            </div>
            <div class="form-text">Link the portal login itself from the vendor's Logins tab.</div>
        </div>
    </div>
</div>

<!-- What they ask for, and who looks after us -->
<div class="tab-pane fade" id="<?= $form_id ?>Account">
    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label">Account number</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-fingerprint"></i></span>
                <input type="text" class="form-control" name="account_number" placeholder="Account or customer number" maxlength="200" value="<?= $vendor_account_number ?>">
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label">PIN / passcode</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                <input type="text" class="form-control" name="code" placeholder="Verbal PIN or code" maxlength="200" value="<?= $vendor_code ?>" autocomplete="off">
            </div>
        </div>
        <div class="col-12">
            <div class="form-text mt-0">What support asks for before they will talk - shown beside the support line, the PIN as dots until it is asked for.</div>
        </div>
        <div class="col-12">
            <label class="form-label">Account manager</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-user-tie"></i></span>
                <input type="text" class="form-control" name="contact_name" placeholder="Name" maxlength="200" value="<?= $vendor_contact_name ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Their direct line</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                <input type="hidden" name="contact_phone_country_code" value="<?= $vendor_contact_phone_country_code ?>">
                <input type="tel" class="form-control" name="contact_phone" value="<?= $vendor_contact_phone ?>" placeholder="Phone number" maxlength="200" data-itflow-phone="contact_phone_country_code">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Their email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                <input type="email" class="form-control" name="contact_email" placeholder="name@vendor.com" maxlength="200" value="<?= $vendor_contact_email ?>">
            </div>
        </div>
    </div>
</div>

<!-- Everything else -->
<div class="tab-pane fade" id="<?= $form_id ?>Notes">
    <label class="form-label">Notes</label>
    <textarea class="form-control" rows="10" name="notes" placeholder="Escalation path, who is authorised to call them, circuit IDs, contract renewal, anything the next tech should know before they ring"><?= $vendor_notes ?></textarea>
    <div class="form-text">Shown on the vendor's page and its quick look.</div>
</div>

</div>

<script>
    itflowReady(function () {
        var form = document.getElementById(<?= json_encode($form_id) ?>).closest('form');

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

        // Starting from a template fills in everything it has
        var templates = <?= json_encode((object) $template_fields, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var picker = form.querySelector('[data-vendor-template]');
        if (!picker) {
            return;
        }
        function setField(name, value) {
            var field = form.querySelector('[name="' + name + '"]');
            if (field) {
                field.value = value || '';
            }
        }
        function setPhone(name, code, number) {
            var input = form.querySelector('input[name="' + name + '"]');
            var hidden = input ? form.querySelector('input[name="' + input.dataset.itflowPhone + '"]') : null;
            if (!input) {
                return;
            }
            var iti = window.intlTelInput && window.intlTelInput.getInstance ? window.intlTelInput.getInstance(input) : null;
            var iso = code && typeof isoForDialCode === 'function' ? isoForDialCode(String(code), '') : '';
            if (iti && iso) {
                iti.setSelectedCountry(iso.toLowerCase());
            }
            if (hidden && code) {
                hidden.value = String(code).replace(/[^0-9]/g, '');
            }
            input.value = number || '';
        }
        picker.addEventListener('change', function () {
            var fields = templates[picker.value];
            if (!fields) {
                return;
            }
            ['name', 'description', 'account_number', 'code', 'contact_name', 'email', 'website', 'hours', 'sla', 'notes', 'extension'].forEach(function (name) {
                setField(name, fields[name]);
            });
            setPhone('phone', fields.phone_country_code, fields.phone);
        });
    });
</script>
