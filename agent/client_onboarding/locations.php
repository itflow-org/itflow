<?php

/*
 * Onboarding - locations: the client's sites so far, and one to add - its
 * name, address, phone and hours (client_onboarding_location). The first is
 * the primary location; another can be made it. Add as many as they have,
 * one at a time.
 */

defined('FROM_CLIENT_ONBOARDING') || die("Direct file access is not allowed");

$co_locations = [];
$co_sql = mysqli_query($mysqli, "SELECT location_id, location_name, location_address, location_city, location_state, location_zip, location_country, location_phone,
        location_phone_country_code, location_phone_extension, location_hours, location_primary
    FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_primary DESC, location_name ASC");
while ($co_row_loc = mysqli_fetch_assoc($co_sql)) {
    $co_locations[] = $co_row_loc;
}
$co_country = (string) settingsValue('country', $session_company_country);

?>
<?php if ($co_locations) { ?>
    <ul class="list-unstyled itflow-gs-people mb-4" data-co-locations="<?= count($co_locations) ?>">
        <?php foreach ($co_locations as $co_loc) { ?>
            <li data-co-location="<?= intval($co_loc['location_id']) ?>">
                <span class="itflow-gs-person-initials" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
                <div class="min-w-0 flex-grow-1">
                    <div class="fw-semibold text-truncate"><?= escapeHtml($co_loc['location_name']) ?>
                        <?php if (intval($co_loc['location_primary'])) { ?><span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-normal ms-1">Primary</span><?php } ?>
                    </div>
                    <div class="small text-body-secondary text-truncate"><?= escapeHtml(locationAddressText($co_loc) ?: 'No address') ?></div>
                </div>
                <?php if (trim((string) $co_loc['location_phone']) !== '') { ?>
                    <span class="small text-body-secondary text-nowrap d-none d-sm-inline"><i class="fas fa-phone me-1"></i><?= escapeHtml(formatPhoneNumber($co_loc['location_phone'], $co_loc['location_phone_country_code'])) ?></span>
                <?php } ?>
            </li>
        <?php } ?>
    </ul>
    <h2 class="h6 fw-semibold">Add another</h2>
<?php } ?>

<form action="post.php" method="post" autocomplete="off" data-gs-form data-co-form="location">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <?= settingsInput('name', $co_locations ? '' : 'Main office', ['id' => 'coLocName', 'icon' => 'fa-map-marker-alt', 'label' => 'Name', 'required' => true, 'maxlength' => 200, 'placeholder' => 'Warehouse, branch office...']) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsInput('hours', '', ['id' => 'coLocHours', 'icon' => 'fa-clock', 'label' => 'Hours', 'maxlength' => 200, 'placeholder' => 'Mon-Fri 8am-5pm']) ?>
        </div>
        <div class="col-12">
            <?= settingsInput('address', '', ['id' => 'coLocAddress', 'icon' => 'fa-road', 'label' => 'Address', 'maxlength' => 200, 'placeholder' => 'Street address']) ?>
        </div>
        <div class="col-sm-5">
            <?= settingsInput('city', '', ['id' => 'coLocCity', 'icon' => 'fa-city', 'label' => 'City', 'maxlength' => 200]) ?>
        </div>
        <div class="col-sm-4">
            <?= settingsInput('state', '', ['id' => 'coLocState', 'icon' => 'fa-flag', 'label' => 'State or province', 'maxlength' => 200]) ?>
        </div>
        <div class="col-sm-3">
            <?= settingsInput('zip', '', ['id' => 'coLocZip', 'icon' => 'fa-mail-bulk', 'label' => 'Zip or postal code', 'maxlength' => 200]) ?>
        </div>
        <div class="col-sm-6">
            <div class="itflow-settings-field">
                <label class="form-label" for="coLocCountry">Country</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                    <select class="form-select select2" id="coLocCountry" name="country">
                        <option value="">- Country -</option>
                        <?php foreach ($countries_array as $co_country_name) { ?>
                            <option<?= $co_country === $co_country_name ? ' selected' : '' ?> data-iso2="<?= escapeHtml($country_iso2_array[$co_country_name] ?? '') ?>"><?= escapeHtml($co_country_name) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="itflow-settings-field">
                <label class="form-label" for="coLocPhone">Phone</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                    <input type="hidden" name="phone_country_code" value="<?= escapeHtml((string) settingsValue('phone_country_code', '')) ?>">
                    <input type="tel" class="form-control" id="coLocPhone" name="phone" value="<?= escapeHtml((string) settingsValue('phone', '')) ?>" maxlength="200" data-itflow-phone="phone_country_code" data-itflow-phone-country-select="country">
                </div>
            </div>
        </div>
        <?php if ($co_locations) { ?>
            <div class="col-12">
                <?= settingsSwitch('primary', '', ['id' => 'coLocPrimary', 'label' => 'Make it the primary location', 'help' => 'The one the client\'s overview, invoices and portal show.']) ?>
            </div>
        <?php } ?>
    </div>
    <button type="submit" name="client_onboarding_location" value="1" class="btn btn-primary" data-gs-save><i class="fas fa-plus me-2"></i>Add <?= $co_locations ? 'it' : 'the location' ?></button>
</form>
