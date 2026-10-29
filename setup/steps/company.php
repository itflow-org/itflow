<?php

/*
 * Setup - Company: your company's details, on invoices, quotes and emails
 * (add_company_settings in setup/index.php, checked by setupCompanyForm(),
 * which seeds the defaults too). Saved once - the rest is changed in Admin >
 * Settings. The phone takes its country code from the Country picked, as on
 * that page. A mistake comes back with everything typed kept.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

$form = $company_form + ['name' => '', 'country' => '', 'address' => '', 'city' => '', 'state' => '', 'zip' => '', 'phone_country_code' => '', 'phone' => '', 'email' => '', 'website' => '', 'tax_id' => ''];
$first_error = array_key_first($company_errors);
$field = function ($name, $label, $icon, $type = 'text', $placeholder = '', $autocomplete = 'off') use ($form, $company_errors, $first_error) {
    $id = 'setupCompany' . str_replace('_', '', ucwords($name, '_'));
    ob_start(); ?>
    <label class="form-label" for="<?= $id ?>"><?= $label ?></label>
    <div class="input-group has-validation">
        <span class="input-group-text"><i class="<?= $icon ?>"></i></span>
        <input type="<?= $type ?>" class="form-control<?= setupFieldInvalid($company_errors, $name) ?>" id="<?= $id ?>" name="<?= $name ?>" value="<?= escapeHtml($form[$name]) ?>" placeholder="<?= escapeHtml($placeholder) ?>" maxlength="200" autocomplete="<?= $autocomplete ?>" <?= $first_error === $name ? 'autofocus' : '' ?>>
    </div>
    <?= setupFieldError($company_errors, $name) ?>
    <?php return ob_get_clean();
};

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4">
        <h1 class="h4 fw-semibold mb-1"><i class="fas fa-building text-body-secondary me-2"></i>Company</h1>
        <p class="text-body-secondary mb-4">Your company, as it shows on invoices, quotes and emails. Only the name and country now - the rest can wait.</p>

        <?php if ($company_exists) { ?>
            <div class="d-flex gap-3 align-items-start mb-4" data-step-done>
                <i class="fas fa-check-circle text-success fa-lg mt-1"></i>
                <div>
                    <div class="fw-semibold">The company's details are saved.</div>
                    <div class="small text-body-secondary">Change them later in Admin &gt; Settings.</div>
                </div>
            </div>
            <a href="?<?= $resume_step ?>" class="btn btn-primary" data-setup-next>Continue<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } elseif (!$install_is_live) { ?>
            <div class="alert alert-info mb-3"><i class="fas fa-info-circle me-2"></i>Your account comes first.</div>
            <a href="?<?= $resume_step ?>" class="btn btn-primary">Carry on where setup is<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } else { ?>
            <?php if ($company_errors) { ?>
                <div class="alert alert-danger d-flex gap-2" role="alert" data-step-errors>
                    <i class="fas fa-times-circle mt-1"></i>
                    <div><?= count($company_errors) === 1 ? 'One thing to put right' : count($company_errors) . ' things to put right' ?><?= isset($company_errors['file']) ? ' - and pick the logo again' : '' ?>.</div>
                </div>
            <?php } ?>
            <form method="post" action="?company" enctype="multipart/form-data" autocomplete="off" data-setup-form novalidate>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="setupCompanyName">Company name <strong class="text-danger">*</strong></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                            <input type="text" class="form-control<?= setupFieldInvalid($company_errors, 'name') ?>" id="setupCompanyName" name="name" value="<?= escapeHtml($form['name']) ?>" placeholder="Company name" maxlength="200" autocomplete="organization" required <?= $first_error === null || $first_error === 'name' ? 'autofocus' : '' ?>>
                        </div>
                        <?= setupFieldError($company_errors, 'name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupCompanyCountry">Country <strong class="text-danger">*</strong></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                            <select class="form-select select2<?= setupFieldInvalid($company_errors, 'country') ?>" id="setupCompanyCountry" name="country" required>
                                <option value="">- Country -</option>
                                <?php foreach ($countries_array as $country_name) { ?>
                                    <option data-iso2="<?= $country_iso2_array[$country_name] ?? '' ?>" <?= $form['country'] === $country_name ? 'selected' : '' ?>><?= $country_name ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?= setupFieldError($company_errors, 'country') ?: '<div class="form-text">Region suggests your currency and language from it.</div>' ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupCompanyLogo">Logo</label>
                        <div class="d-flex gap-3 align-items-center">
                            <div class="flex-grow-1 min-w-0">
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="fa fa-fw fa-image"></i></span>
                                    <input type="file" class="form-control<?= setupFieldInvalid($company_errors, 'file') ?>" id="setupCompanyLogo" name="file" accept="image/jpeg,image/png" data-setup-image="JPG or PNG" data-setup-image-max="<?= SETUP_IMAGE_MAX_BYTES ?>">
                                </div>
                                <?= setupFieldError($company_errors, 'file') ?: '<div class="form-text">Optional - JPG or PNG, up to 5 MB.</div>' ?>
                            </div>
                            <div class="itflow-setup-preview" data-setup-preview="setupCompanyLogo"><i class="fas fa-image"></i></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <?= $field('address', 'Address', 'fa fa-fw fa-map-marker-alt', 'text', 'Street address', 'street-address') ?>
                    </div>
                    <div class="col-md-5">
                        <?= $field('city', 'City', 'fa fa-fw fa-city', 'text', 'City', 'address-level2') ?>
                    </div>
                    <div class="col-md-4">
                        <?= $field('state', 'State / province', 'fa fa-fw fa-flag', 'text', 'State', 'address-level1') ?>
                    </div>
                    <div class="col-md-3">
                        <?= $field('zip', 'Postal code', 'fab fa-fw fa-usps', 'text', 'Zip', 'postal-code') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupCompanyPhone">Phone</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                            <input type="hidden" name="phone_country_code" value="<?= escapeHtml($form['phone_country_code']) ?>">
                            <input type="tel" class="form-control" id="setupCompanyPhone" name="phone" value="<?= escapeHtml($form['phone']) ?>" placeholder="Phone number" maxlength="200" autocomplete="tel-national" data-itflow-phone="phone_country_code" data-itflow-phone-country-select="country">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <?= $field('email', 'Email', 'fa fa-fw fa-envelope', 'email', 'info@company.com', 'email') ?>
                    </div>
                    <div class="col-md-6">
                        <?= $field('website', 'Website', 'fa fa-fw fa-globe', 'text', 'company.com', 'url') ?>
                    </div>
                    <div class="col-md-6">
                        <?= $field('tax_id', 'Tax ID', 'fa fa-fw fa-balance-scale', 'text', 'Tax ID') ?>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" name="add_company_settings" class="btn btn-primary" data-setup-next>Save and carry on<i class="fas fa-arrow-right ms-2"></i></button>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
