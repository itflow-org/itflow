<?php
/*
 * Contact form fields - shared by contact_add.php and contact_edit.php, which
 * set $contact_form_mode ('add' | 'edit'), $client_id (0 = pick the client
 * here) and the $contact_* values (escaped, empty for a new contact) before
 * including this. Posts the field set agent/post/contact_model.php reads.
 *
 * Tabs like the other add / edit modals: Details (who and how to reach them),
 * Roles (primary, important, billing, technical), Access (PIN and the client
 * portal) and Notes (photo, notes, tags).
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

// The client's locations - only once the client is known
$location_options = [];
if ($client_id) {
    $sql = mysqli_query($mysqli, "SELECT location_archived_at, location_id, location_name, location_primary FROM locations
        WHERE location_client_id = $client_id AND (location_archived_at IS NULL OR location_id = $contact_location_id)
        ORDER BY location_primary DESC, location_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $location_options[] = $row;
    }
} else {
    $client_options = [];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $client_options[] = $row;
    }
}

// The roles, with what each one does
$contact_roles = [
    'contact_important' => ['label' => 'Important', 'icon' => 'fa-star', 'value' => $contact_important, 'help' => 'Pinned to the top of the contacts list.'],
    'contact_billing' => ['label' => 'Billing', 'icon' => 'fa-file-invoice-dollar', 'value' => $contact_billing, 'help' => 'Receives invoices and quotes.'],
    'contact_technical' => ['label' => 'Technical', 'icon' => 'fa-tools', 'value' => $contact_technical, 'help' => 'Who to talk to about the technical side.'],
];

?>

<!-- Tabs, like the other add / edit modals -->
<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#contactFormDetails"><i class="fas fa-fw fa-id-badge me-2 d-none d-sm-inline"></i>Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#contactFormRoles"><i class="fas fa-fw fa-user-tag me-2 d-none d-sm-inline"></i>Roles</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#contactFormAccess"><i class="fas fa-fw fa-lock me-2 d-none d-sm-inline"></i>Access</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#contactFormNotes"><i class="fas fa-fw fa-sticky-note me-2 d-none d-sm-inline"></i>Notes</a>
    </li>
</ul>

<div class="tab-content">

<!-- Who they are and how to reach them -->
<div class="tab-pane fade show active" id="contactFormDetails">
    <div class="row g-3">
        <?php if (!$client_id) { ?>
            <div class="col-12">
                <label class="form-label">Client <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-users fa-fw"></i></span>
                    <select class="form-select select2" name="client_id" required>
                        <option value="">- Select Client -</option>
                        <?php foreach ($client_options as $client_option) { ?>
                            <option value="<?= intval($client_option['client_id']) ?>"<?= selectOptionClient($client_option['client_id'], $client_option['client_name'], $client_option['client_abbreviation'] ?? '') ?>><?= escapeHtml($client_option['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Pick their location once they're saved.</div>
            </div>
        <?php } ?>
        <div class="col-md-6">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user fa-fw"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Full name" maxlength="200" value="<?= $contact_name ?>" required <?= $contact_form_mode === 'add' ? 'autofocus' : '' ?>>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Title</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-id-badge fa-fw"></i></span>
                <input type="text" class="form-control" name="title" placeholder="Office Manager" maxlength="200" value="<?= $contact_title ?>">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Department / group</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-sitemap fa-fw"></i></span>
                <input type="text" class="form-control" name="department" placeholder="Accounting" maxlength="200" value="<?= $contact_department ?>">
            </div>
        </div>
        <?php if ($client_id) { ?>
            <div class="col-md-6">
                <label class="form-label">Location</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-map-marker-alt fa-fw"></i></span>
                    <select class="form-select select2" name="location">
                        <option value="">- Select Location -</option>
                        <?php foreach ($location_options as $location_option) {
                            $location_option_id = intval($location_option['location_id']); ?>
                            <option value="<?= $location_option_id ?>" <?php if ($contact_location_id === $location_option_id) { echo "selected"; } ?><?= selectOptionData(['description' => $location_option['location_primary'] ? 'Primary' : '']) ?>><?= escapeHtml($location_option['location_name']) ?><?= $location_option['location_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        <?php } ?>

        <div class="col-12"><div class="itflow-form-section mb-0 mt-2">Reach</div></div>

        <div class="col-md-8">
            <label class="form-label">Phone</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-phone fa-fw"></i></span>
                <input type="hidden" name="phone_country_code" value="<?= $contact_phone_country_code ?>">
                <input type="tel" class="form-control" name="phone" value="<?= $contact_phone ?>" placeholder="Phone number" maxlength="200" data-itflow-phone="phone_country_code">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Extension</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-hashtag fa-fw"></i></span>
                <input type="text" class="form-control" name="extension" value="<?= $contact_extension ?>" placeholder="114" maxlength="200" inputmode="numeric">
            </div>
        </div>
        <div class="col-md-8">
            <label class="form-label">Mobile</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-mobile-alt fa-fw"></i></span>
                <input type="hidden" name="mobile_country_code" value="<?= $contact_mobile_country_code ?>">
                <input type="tel" class="form-control" name="mobile" value="<?= $contact_mobile ?>" placeholder="Mobile number" maxlength="200" data-itflow-phone="mobile_country_code">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope fa-fw"></i></span>
                <input type="email" class="form-control" name="email" id="contactFormEmail" placeholder="name@example.com" maxlength="200" value="<?= $contact_email ?>">
            </div>
            <?php if ($contact_form_mode === 'add') { ?>
                <div class="form-text text-info" id="contactFormEmailCheck"></div>
            <?php } ?>
        </div>
    </div>

    <?php if ($contact_form_mode === 'edit') { ?>
        <p class="text-body-secondary small text-end mt-3 mb-0">Contact ID <span class="font-monospace"><?= $contact_id ?></span></p>
    <?php } ?>
</div>

<!-- What they are to the client -->
<div class="tab-pane fade" id="contactFormRoles">
    <div class="list-group">
        <label class="list-group-item form-switch d-flex gap-3 align-items-start py-3 ps-3">
            <input class="form-check-input flex-shrink-0 mt-1 ms-0" type="checkbox" role="switch" name="contact_primary" value="1"
                <?= $contact_primary ? 'checked disabled' : ($contact_primary_default ? 'checked' : '') ?>>
            <span>
                <span class="fw-semibold"><i class="fas fa-fw fa-crown text-success me-1"></i>Primary contact</span>
                <span class="d-block small text-body-secondary">
                    <?= $contact_primary
                        ? 'The client\'s main contact. To change it, make someone else primary.'
                        : 'The client\'s main contact - one per client, and always important. Takes over from the current primary contact.' ?>
                </span>
            </span>
        </label>
        <?php foreach ($contact_roles as $role_name => $role) { ?>
            <label class="list-group-item form-switch d-flex gap-3 align-items-start py-3 ps-3">
                <input class="form-check-input flex-shrink-0 mt-1 ms-0" type="checkbox" role="switch" name="<?= $role_name ?>" value="1" <?= $role['value'] ? 'checked' : '' ?>>
                <span>
                    <span class="fw-semibold"><i class="fas fa-fw <?= $role['icon'] ?> text-secondary me-1"></i><?= $role['label'] ?></span>
                    <span class="d-block small text-body-secondary"><?= $role['help'] ?></span>
                </span>
            </label>
        <?php } ?>
    </div>
</div>

<!-- How we know it's them, and the client portal -->
<div class="tab-pane fade" id="contactFormAccess">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">PIN</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-key fa-fw"></i></span>
                <input type="text" class="form-control font-monospace" name="pin" placeholder="Security code or PIN" maxlength="255" value="<?= $contact_pin ?>" autocomplete="off">
            </div>
            <div class="form-text">Ask for it to verify who's calling.</div>
        </div>

        <?php if ($config_client_portal_enable == 1) { ?>
            <div class="col-12"><div class="itflow-form-section mb-0 mt-2">Client portal</div></div>
            <div class="col-12 authForm">
                <label class="form-label">Login</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user-circle fa-fw"></i></span>
                    <select class="form-select select2 authMethod" name="auth_method">
                        <option value="">- No access -</option>
                        <option value="local" <?php if ($auth_method === 'local') { echo "selected"; } ?>>With a password</option>
                        <option value="azure" <?php if ($auth_method === 'azure') { echo "selected"; } ?>>With their Microsoft (Entra ID) account</option>
                    </select>
                </div>
                <div class="mt-3 passwordGroup" style="display: none;">
                    <label class="form-label">Password<?= $contact_form_mode === 'add' ? '' : ' <span class="text-body-secondary fw-normal">- leave empty to keep the current one</span>' ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock fa-fw"></i></span>
                        <input type="password" class="form-control" data-toggle="password" id="contactFormPassword" name="contact_password" placeholder="Password" autocomplete="new-password">
                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        <button type="button" class="btn btn-outline-secondary" title="Generate a password" data-contact-form-generate>
                            <i class="fas fa-fw fa-magic"></i>
                        </button>
                    </div>
                    <div class="form-text">Leave it empty and they set their own from the invite, or with Forgot password.</div>
                </div>
                <div class="form-check mt-3 inviteGroup" style="display: none;">
                    <input type="checkbox" class="form-check-input" name="send_email" value="1" id="contactFormSendEmail">
                    <label class="form-check-label" for="contactFormSendEmail">Email them an invite to the portal</label>
                    <div class="form-text mt-0">With a password login, a link to set their own password, good for <?= PORTAL_INVITE_LINK_DAYS ?> days - the email never has the password in it. With Microsoft, a link to sign in.</div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<!-- Photo, notes and tags -->
<div class="tab-pane fade" id="contactFormNotes">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" rows="6" name="notes" placeholder="Personal tidbits, how they like to be contacted, anything worth knowing"><?= $contact_notes ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Tags</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-tags fa-fw"></i></span>
                <select class="form-select select2" name="tags[]" data-placeholder="Add some tags" multiple<?= !empty($session_is_admin) ? ' data-tags="true"' : '' ?>>
                    <?= tagOptionsHtml('contact', $contact_tag_id_array) ?>
                </select>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Photo</label>
            <div class="d-flex align-items-center gap-3">
                <?= contactAvatar($client_id, $contact_id, html_entity_decode($contact_name), $contact_photo ? html_entity_decode($contact_photo) : '', 'lg') ?>
                <div class="flex-grow-1">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-camera fa-fw"></i></span>
                        <input type="file" class="form-control" name="file" accept="image/jpeg,image/png,image/gif,image/webp">
                    </div>
                    <?php if ($contact_photo) { ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="contactFormRemovePhoto">
                            <label class="form-check-label" for="contactFormRemovePhoto">Remove this photo</label>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<script>
    itflowReady(function () {
        var form = document.getElementById('contactFormDetails').closest('form');

        // Client portal: the password only matters for a password login, and an invite needs a login
        form.querySelectorAll('.authMethod').forEach(function (select) {
            function sync() {
                select.closest('.authForm').querySelectorAll('.passwordGroup').forEach(function (group) {
                    group.style.display = select.value === 'local' ? '' : 'none';
                });
                select.closest('.authForm').querySelectorAll('.inviteGroup').forEach(function (group) {
                    group.style.display = select.value !== '' ? '' : 'none';
                    if (select.value === '') {
                        group.querySelector('input').checked = false;
                    }
                });
            }
            select.addEventListener('change', sync);
            sync();
        });

        var generate = form.querySelector('[data-contact-form-generate]');
        if (generate) {
            generate.addEventListener('click', function () {
                itflowGet('ajax.php', { get_readable_pass: 'true' }, function (data) {
                    form.querySelector('#contactFormPassword').value = JSON.parse(data);
                });
            });
        }

        // A new contact whose email is already on file somewhere
        var emailCheck = form.querySelector('#contactFormEmailCheck');
        if (emailCheck) {
            form.querySelector('#contactFormEmail').addEventListener('change', function () {
                var email = this.value.trim();
                if (email === '') {
                    emailCheck.textContent = '';
                    return;
                }
                itflowGet('ajax.php', { contact_email_check: 'true', email: email }, function (data) {
                    emailCheck.innerHTML = JSON.parse(data).message || '';
                });
            });
        }

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
    });
</script>
