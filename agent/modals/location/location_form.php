<?php
/*
 * Location form fields - shared by location_add.php and location_edit.php,
 * which set $location_form_mode ('add' | 'edit'), $client_id (0 = pick the
 * client here), $location_id and the $location_* values (escaped, empty for
 * a new location) plus $location_tag_ids, before including this. Posts what
 * locationFormSet() (functions/location.php) reads.
 *
 * Tabs: Details (name, primary, description, tags, photo), Address, Contact
 * (who to ask for on site, phone, fax, hours) and Access - the notes a tech
 * needs to get in: parking, door and alarm codes, where the comms cupboard is.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

// The client's people, for who to ask for on site
$contact_options = [];
$client_options = [];
if ($client_id) {
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_photo, contact_client_id, contact_archived_at FROM contacts
        WHERE contact_client_id = $client_id AND (contact_archived_at IS NULL OR contact_id = $location_contact_id)
        ORDER BY contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $contact_options[] = $row;
    }
} else {
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $client_options[] = $row;
    }
}

$sql_tags_select = mysqli_query($mysqli, "SELECT tag_id, tag_name, tag_color, tag_icon FROM tags WHERE tag_type = 2 ORDER BY tag_name ASC");

$form_id = 'locationForm' . ($location_form_mode === 'edit' ? $location_id : 'New');

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-map-marker-alt me-2 d-none d-lg-inline"></i>Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Address"><i class="fas fa-fw fa-map me-2 d-none d-lg-inline"></i>Address</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Contact"><i class="fas fa-fw fa-phone me-2 d-none d-lg-inline"></i>Contact</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Access"><i class="fas fa-fw fa-door-open me-2 d-none d-lg-inline"></i>Access</a>
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
                <div class="form-text">Pick who to ask for on site once it's saved.</div>
            </div>
        <?php } ?>
        <div class="col-md-8">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-map-marker-alt"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Main Office, Warehouse, Plant Two" maxlength="200" value="<?= $location_name ?>" required <?= $location_form_mode === 'edit' ? '' : 'autofocus' ?>>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label d-none d-md-block">&nbsp;</label>
            <div class="form-check form-switch mt-md-2">
                <input class="form-check-input" type="checkbox" role="switch" name="location_primary" value="1" id="<?= $form_id ?>Primary" <?= $location_primary ? 'checked' : '' ?> <?= $location_primary && $location_form_mode === 'edit' ? 'disabled' : '' ?>>
                <label class="form-check-label" for="<?= $form_id ?>Primary">Primary location</label>
            </div>
            <?php if ($location_primary && $location_form_mode === 'edit') { ?>
                <input type="hidden" name="location_primary" value="1">
                <div class="form-text mt-0">Make another location primary to change it.</div>
            <?php } ?>
        </div>
        <div class="col-12">
            <label class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Head office and comms room" value="<?= $location_description ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Tags</label>
            <input type="hidden" name="tags_form" value="1">
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                <select class="form-select select2" name="tags[]" data-placeholder="Add some tags" multiple>
                    <?php while ($row = mysqli_fetch_assoc($sql_tags_select)) {
                        $tag_id_select = intval($row['tag_id']); ?>
                        <option value="<?= $tag_id_select ?>" <?= in_array($tag_id_select, $location_tag_ids, true) ? 'selected' : '' ?><?= selectOptionColor($row['tag_color'] ?? '', $row['tag_icon'] ?? '') ?>><?= escapeHtml($row['tag_name']) ?></option>
                    <?php } ?>
                </select>
                <button class="btn btn-secondary ajax-modal" title="New tag" type="button" data-modal-url="../admin/modals/tag/tag_add.php?type=2">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Photo</label>
            <div class="d-flex align-items-center gap-3">
                <?php if ($location_photo) { ?>
                    <img class="itflow-asset-icon itflow-asset-icon-lg" src="<?= "../uploads/clients/$client_id/$location_photo" ?>" alt="">
                <?php } ?>
                <div class="flex-grow-1">
                    <input type="file" class="form-control" name="file" accept="image/*">
                    <?php if ($location_photo) { ?>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="<?= $form_id ?>RemovePhoto">
                            <label class="form-check-label small" for="<?= $form_id ?>RemovePhoto">Remove the photo</label>
                        </div>
                    <?php } else { ?>
                        <div class="form-text">The front of the building - so a tech knows it when they pull up.</div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <?php if ($location_form_mode === 'edit') { ?>
        <p class="text-body-secondary small text-end mt-3 mb-0">Location ID <span class="font-monospace"><?= $location_id ?></span></p>
    <?php } ?>
</div>

<!-- Where it is -->
<div class="tab-pane fade" id="<?= $form_id ?>Address">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Street address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                <input type="text" class="form-control" name="address" placeholder="1140 Ridge Avenue, Suite 200" maxlength="200" value="<?= $location_address ?>">
            </div>
        </div>
        <div class="col-md-5">
            <label class="form-label">City</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                <input type="text" class="form-control" name="city" placeholder="City" maxlength="200" value="<?= $location_city ?>">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">State / Province</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                <input type="text" class="form-control" name="state" placeholder="State or Province" maxlength="200" value="<?= $location_state ?>">
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Postal code</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-mail-bulk"></i></span>
                <input type="text" class="form-control" name="zip" placeholder="Zip" maxlength="200" value="<?= $location_zip ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Country</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                <select class="form-select select2" name="country">
                    <option value="">- Country -</option>
                    <?php foreach ($countries_array as $country_name) { ?>
                        <option <?= $location_country === $country_name ? 'selected' : '' ?> data-iso2="<?= $country_iso2_array[$country_name] ?? '' ?>"><?= $country_name ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Who to ask for, how to call, when it is open -->
<div class="tab-pane fade" id="<?= $form_id ?>Contact">
    <div class="row g-3">
        <?php if ($client_id) { ?>
            <div class="col-12">
                <label class="form-label">On-site contact</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="contact">
                        <option value="">- Nobody -</option>
                        <?php foreach ($contact_options as $contact_option) {
                            $contact_option_id = intval($contact_option['contact_id']); ?>
                            <option value="<?= $contact_option_id ?>" <?= $location_contact_id === $contact_option_id ? 'selected' : '' ?><?= selectOptionContact($contact_option['contact_client_id'], $contact_option['contact_name'], $contact_option['contact_photo'], $contact_option['contact_title'], $contact_option_id) ?>><?= escapeHtml($contact_option['contact_name']) ?><?= $contact_option['contact_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Who to ask for when you get there - shown with their number on the location.</div>
            </div>
        <?php } ?>
        <div class="col-md-8">
            <label class="form-label">Phone</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                <input type="hidden" name="phone_country_code" value="<?= $location_phone_country_code ?>">
                <input type="tel" class="form-control" name="phone" value="<?= $location_phone ?>" placeholder="Phone number" maxlength="200" data-itflow-phone="phone_country_code" data-itflow-phone-country-select="country">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Extension</label>
            <input type="text" class="form-control" name="extension" value="<?= $location_extension ?>" placeholder="ext." maxlength="10" inputmode="numeric">
        </div>
        <div class="col-md-8">
            <label class="form-label">Fax</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-fax"></i></span>
                <input type="hidden" name="fax_country_code" value="<?= $location_fax_country_code ?>">
                <input type="tel" class="form-control" name="fax" value="<?= $location_fax ?>" placeholder="Fax number" maxlength="200" data-itflow-phone="fax_country_code" data-itflow-phone-country-select="country">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Hours</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                <input type="text" class="form-control" name="hours" placeholder="Mon-Fri 8:00 - 17:00, closed for lunch 12-1" maxlength="200" value="<?= $location_hours ?>">
            </div>
        </div>
    </div>
</div>

<!-- Getting in -->
<div class="tab-pane fade" id="<?= $form_id ?>Access">
    <label class="form-label">Access notes</label>
    <textarea class="form-control" rows="10" name="notes" placeholder="Parking, which door to use, who holds the keys, alarm and door codes, where the comms cupboard is, anything a tech should know before they arrive"><?= $location_notes ?></textarea>
    <div class="form-text">Shown on the location page, its quick look and on tickets for this location.</div>
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
    });
</script>
