<?php

/*
 * Add or change an office - Admin > Settings > Company Details, Offices
 * (functions/offices.php; saved by save_office and taken away by remove_office
 * in admin/post/office.php). ?office_id= to change one.
 */

require_once '../../includes/modal_header.php';

$office_id = intval($_GET['office_id'] ?? 0);
$office = $office_id ? office($office_id) : null;
$office_id = $office ? $office_id : 0;
$company = billingCompany();
$localization = settingsLocalizationArrays();
$agents = officeAgents();
$people = officePeople();
$offices = officesAll();
$office_country = (string) ($office['country'] ?? $company['company_country'] ?? '');
$phone_code = (string) ($office['phone_country_code'] ?? $company['company_phone_country_code'] ?? '');

ob_start();

if (!officesReady()) { ?>
    <div class="modal-body text-center text-secondary py-5">Offices need the database update - run it from Maintenance &gt; Update first.</div>
<?php } elseif (intval($_GET['office_id'] ?? 0) && !$office) { ?>
    <div class="modal-body text-center text-secondary py-5">That office is gone - it may have been removed.</div>
<?php } else { ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-map-marker-alt me-2"></i><?= $office ? 'Edit Office <span class="fw-normal text-white-50 ms-1">' . escapeHtml($office['name']) . '</span>' : 'New Office' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-office-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="office_id" value="<?= $office_id ?>">
    <input type="hidden" name="office_people_shown" value="1">

    <div class="modal-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
                    <input type="text" class="form-control" name="office_name" maxlength="100" required placeholder="What you call it - Downtown, the Erie office" value="<?= escapeHtml($office['name'] ?? '') ?>" <?= $office ? '' : 'autofocus' ?>>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-map-marker-alt"></i></span>
                    <input type="text" class="form-control" name="office_address" maxlength="200" placeholder="Street address" value="<?= escapeHtml($office['address'] ?? '') ?>" autocomplete="street-address">
                </div>
            </div>
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-city"></i></span>
                    <input type="text" class="form-control" name="office_city" maxlength="200" placeholder="City" value="<?= escapeHtml($office['city'] ?? '') ?>" autocomplete="address-level2">
                </div>
            </div>
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-flag"></i></span>
                    <input type="text" class="form-control" name="office_state" maxlength="200" placeholder="State or province" value="<?= escapeHtml($office['state'] ?? '') ?>" autocomplete="address-level1">
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-mail-bulk"></i></span>
                    <input type="text" class="form-control" name="office_zip" maxlength="200" placeholder="Postal code" value="<?= escapeHtml($office['zip'] ?? '') ?>" autocomplete="postal-code">
                </div>
            </div>
            <div class="col-12">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-globe-americas"></i></span>
                    <select class="form-select select2" name="office_country">
                        <option value="">- Select a country -</option>
                        <?php foreach ($localization['countries'] as $country_name) { ?>
                            <option value="<?= escapeHtml($country_name) ?>" data-iso2="<?= escapeHtml($localization['country_iso2'][$country_name] ?? '') ?>" <?= $country_name === $office_country ? 'selected' : '' ?>><?= escapeHtml($country_name) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-phone"></i></span>
                    <input type="hidden" name="office_phone_country_code" value="<?= escapeHtml($phone_code) ?>">
                    <input type="tel" class="form-control" name="office_phone" maxlength="200" placeholder="Phone number" value="<?= escapeHtml($office ? formatPhoneNumber($office['phone'], $office['phone_country_code'], false) : '') ?>" autocomplete="tel-national" data-itflow-phone="office_phone_country_code" data-itflow-phone-country-select="office_country">
                </div>
                <div class="form-text">Empty - the main office's.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                    <input type="email" class="form-control" name="office_email" maxlength="200" placeholder="erie@company.com" value="<?= escapeHtml($office['email'] ?? '') ?>">
                </div>
                <div class="form-text">Empty - the address ticket emails come from.</div>
            </div>
            <div class="col-12">
                <label class="form-label">Who works there</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-users"></i></span>
                    <select class="form-select select2" name="office_people[]" multiple data-placeholder="Agents in this office" data-office-people>
                        <?php foreach ($agents as $agent_id => $agent) {
                            $here = $office_id && in_array($agent_id, $people[$office_id] ?? [], true);
                            $at = officeIdForUser($agent_id); ?>
                            <option value="<?= $agent_id ?>" <?= $here ? 'selected' : '' ?><?= selectOptionUser($agent_id, $agent['user_name'], $agent['user_avatar'] ?? '') ?>><?= escapeHtml($agent['user_name']) ?><?= !$here && $at ? ' (in ' . escapeHtml($offices[$at]['name'] ?? '') . ')' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Picking someone moves them here; taking someone off puts them back in the main office.</div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <?php if ($office) { ?>
            <a href="post.php?remove_office=<?= $office_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-danger me-auto confirm-link" data-confirm-title="Remove this office?" data-confirm-text="Those who work there go back to the main office." data-confirm-button="Remove" data-office-remove><i class="fas fa-trash me-2"></i>Remove</a>
        <?php } ?>
        <button type="submit" name="save_office" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i><?= $office ? 'Save' : 'Create' ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
