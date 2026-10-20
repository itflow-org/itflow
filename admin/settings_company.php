<?php

/*
 * Admin > Settings > Company details - your company as it shows on invoices,
 * quotes and emails: its name and tax ID, address, phone (with its country
 * code, following the country picked), email, website and logo, and the
 * client record that stands for it. Saved by edit_company; the logo is taken
 * off by remove_company_logo (admin/post/settings_company.php).
 */

require_once "includes/inc_all_admin.php";

$company = settingsCurrent(['company_name', 'company_tax_id', 'company_address', 'company_city', 'company_state', 'company_zip', 'company_country',
    'company_phone_country_code', 'company_phone', 'company_email', 'company_website', 'company_logo'], 'companies');
$localization = settingsLocalizationArrays();

$countries = ['' => '- Country -'];
foreach ($localization['countries'] as $country_name) {
    $countries[$country_name] = [$country_name, ' data-iso2="' . escapeHtml($localization['country_iso2'][$country_name] ?? '') . '"'];
}

$internal_clients = [0 => 'None'];
$sql_internal_clients = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL OR client_id = " . intval($config_internal_client_id) . " ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql_internal_clients)) {
    $internal_clients[intval($row['client_id'])] = [$row['client_name'], selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '')];
}

$logo = (string) ($company['company_logo'] ?? '');
$logo_url = $logo !== '' && basename($logo) === $logo && is_file(dirname(__DIR__) . "/uploads/settings/$logo") ? "/uploads/settings/" . rawurlencode($logo) : '';

// The phone, as it is shown - its number formatted for its country, its code apart
$phone_code = (string) settingsValue('company_phone_country_code', $company['company_phone_country_code']);
$phone = (string) settingsValue('company_phone', formatPhoneNumber((string) $company['company_phone'], $phone_code, false));

echo settingsPageStart('company');

?>

<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off" data-settings-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="card itflow-settings-card">
        <div class="card-body">

            <?= settingsSectionStart('company', 'Your company', 'As it is named on invoices, quotes and the emails ITFlow sends.') ?>
                <?= settingsInput('company_name', $company['company_name'], ['icon' => 'fa-building', 'required' => true, 'maxlength' => 200, 'placeholder' => 'Company name', 'autocomplete' => 'organization']) ?>
                <?= settingsInput('company_tax_id', $company['company_tax_id'], ['icon' => 'fa-balance-scale', 'maxlength' => 200, 'placeholder' => 'Tax ID',
                    'help' => 'Shown on invoices when Invoices and billing says so.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('address', 'Address', 'Where your company is - on invoices, and the country phone numbers start from.') ?>
                <?= settingsInput('company_address', $company['company_address'], ['icon' => 'fa-map-marker-alt', 'maxlength' => 200, 'placeholder' => 'Street address', 'autocomplete' => 'street-address']) ?>
                <div class="row g-3 mt-0">
                    <div class="col-md-5"><?= settingsInput('company_city', $company['company_city'], ['icon' => 'fa-city', 'maxlength' => 200, 'placeholder' => 'City', 'autocomplete' => 'address-level2']) ?></div>
                    <div class="col-md-4"><?= settingsInput('company_state', $company['company_state'], ['icon' => 'fa-flag', 'maxlength' => 200, 'placeholder' => 'State or province', 'autocomplete' => 'address-level1']) ?></div>
                    <div class="col-md-3"><?= settingsInput('company_zip', $company['company_zip'], ['icon' => 'fa-mail-bulk', 'maxlength' => 200, 'placeholder' => 'Postal code', 'autocomplete' => 'postal-code']) ?></div>
                </div>
                <div class="mt-3">
                    <?= settingsSelect('company_country', (string) $company['company_country'], $countries, ['icon' => 'fa-globe-americas', 'search' => true]) ?>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('contact', 'Contact', 'How clients reach you - on invoices and quotes, and in the client portal.') ?>
                <?php
                $phone_control = '<div class="input-group">'
                    . '<span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>'
                    . '<input type="hidden" name="company_phone_country_code" value="' . escapeHtml($phone_code) . '">'
                    . '<input type="tel" class="form-control" id="settings_company_phone" name="company_phone" value="' . escapeHtml($phone) . '" placeholder="Phone number" maxlength="200" autocomplete="tel-national" data-itflow-phone="company_phone_country_code" data-itflow-phone-country-select="company_country">'
                    . '</div>';
                echo settingsField('company_phone', 'Phone', $phone_control, 'Its country code follows the country above until you pick another.');
                ?>
                <?= settingsInput('company_email', $company['company_email'], ['icon' => 'fa-envelope', 'type' => 'email', 'maxlength' => 200, 'placeholder' => 'info@company.com', 'autocomplete' => 'email']) ?>
                <?= settingsInput('company_website', $company['company_website'], ['icon' => 'fa-globe', 'maxlength' => 200, 'placeholder' => 'company.com', 'autocomplete' => 'url',
                    'help' => 'Kept without its https://.']) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('logo', 'Logo', 'On invoices, quotes and statements, the sign-in page, the sidebar and the client portal.') ?>
                <div class="row g-3 align-items-center">
                    <div class="col-sm-4">
                        <div class="itflow-settings-picture" data-settings-logo>
                            <img id="settingsLogoPreview" alt="Your logo" <?= $logo_url !== '' ? 'src="' . escapeHtml($logo_url) . '"' : 'hidden' ?>>
                            <?php if ($logo_url === '') { ?>
                                <span class="small text-body-secondary" data-settings-preview-empty>No logo yet</span>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <?= settingsInput('company_logo', '', ['icon' => 'fa-image', 'type' => 'file', 'label' => $logo_url !== '' ? 'Replace it' : 'Add one', 'accept' => 'image/png,image/jpeg',
                            'data' => ['settings-preview' => 'settingsLogoPreview'], 'help' => 'A PNG or JPG, up to 5 MB. A wide one prints best.']) ?>
                        <?php if ($logo_url !== '') { ?>
                            <a href="post.php?remove_company_logo&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-sm btn-outline-danger mt-2 confirm-link" data-settings-remove-logo><i class="fas fa-trash-alt me-2"></i>Take the logo off</a>
                        <?php } ?>
                    </div>
                </div>
            <?= settingsSectionEnd() ?>

            <?= settingsSectionStart('internal', 'Your own client record', 'If you keep a client record for your own company - for internal tickets, documentation, assets and credentials - point it out here. Your company name in the sidebar then links straight to it.') ?>
                <?= settingsSelect('config_internal_client_id', intval($config_internal_client_id), $internal_clients, ['icon' => 'fa-house-user', 'search' => true]) ?>
            <?= settingsSectionEnd() ?>

            <?= settingsSaveBar('edit_company') ?>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
