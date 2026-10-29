<?php

/*
 * Setup - Region: the language, currency and timezone ITFlow works in
 * (add_localization_settings in setup/index.php, checked by
 * setupRegionForm(), which makes the Cash account in that currency too).
 * The currency and language start on the company's country's, the timezone
 * on the browser's (js/setup.js) - or the country's, when it has only one.
 * Saved once - changed later in Admin > Settings.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

$form = $region_form;
$suggested = ['locale' => false, 'currency_code' => false, 'timezone' => false];
$company_country = '';
if ($company_exists && !$localization_done && !$form) {
    $company_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_country FROM companies WHERE company_id = 1"));
    $company_country = (string) ($company_row['company_country'] ?? '');
    $company_iso2 = $country_iso2_array[$company_country] ?? '';
    $form = [
        'locale' => setupSuggestLocale($company_iso2, (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), $locales_array),
        'currency_code' => setupSuggestCurrency($company_iso2, $currencies_array),
        'timezone' => setupSuggestTimezone($company_iso2),
    ];
    foreach ($form as $key => $value) {
        $suggested[$key] = $value !== '';
    }
}
$form += ['locale' => '', 'currency_code' => '', 'timezone' => ''];
$hint = function ($key, $text) use ($region_errors, $suggested) {
    if (isset($region_errors[$key])) {
        return setupFieldError($region_errors, $key);
    }
    return $suggested[$key] ? '<div class="form-text" data-setup-suggested="' . $key . '">' . $text . '</div>' : '';
};

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4">
        <h1 class="h4 fw-semibold mb-1"><i class="fas fa-globe-americas text-body-secondary me-2"></i>Region</h1>
        <p class="text-body-secondary mb-4">The language numbers and dates are written in, the currency you bill in, and your timezone.</p>

        <?php if ($localization_done) { ?>
            <div class="d-flex gap-3 align-items-start mb-4" data-step-done>
                <i class="fas fa-check-circle text-success fa-lg mt-1"></i>
                <div>
                    <div class="fw-semibold">The language, currency and timezone are saved.</div>
                    <div class="small text-body-secondary">Change them later in Admin &gt; Settings.</div>
                </div>
            </div>
            <a href="?<?= $resume_step ?>" class="btn btn-primary" data-setup-next>Continue<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } elseif (!$company_exists) { ?>
            <div class="alert alert-info mb-3"><i class="fas fa-info-circle me-2"></i>The company comes first.</div>
            <a href="?<?= $resume_step ?>" class="btn btn-primary">Carry on where setup is<i class="fas fa-arrow-right ms-2"></i></a>
        <?php } else { ?>
            <?php if ($region_errors) { ?>
                <div class="alert alert-danger d-flex gap-2" role="alert" data-step-errors>
                    <i class="fas fa-times-circle mt-1"></i>
                    <div><?= count($region_errors) === 1 ? 'One thing to put right.' : count($region_errors) . ' things to put right.' ?></div>
                </div>
            <?php } ?>
            <form method="post" action="?localization" autocomplete="off" data-setup-form data-setup-region novalidate>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="setupLocale">Language <strong class="text-danger">*</strong></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="fa fa-fw fa-language"></i></span>
                            <select class="form-select select2<?= setupFieldInvalid($region_errors, 'locale') ?>" id="setupLocale" name="locale" required>
                                <option value="">- Language -</option>
                                <?php foreach ($locales_array as $locale_code => $locale_name) { ?>
                                    <option value="<?= escapeHtml($locale_code) ?>" <?= $form['locale'] === $locale_code ? 'selected' : '' ?>><?= escapeHtml($locale_name) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?= $hint('locale', 'Picked for ' . escapeHtml($company_country) . ' and your browser.') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="setupCurrency">Currency <strong class="text-danger">*</strong></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                            <select class="form-select select2<?= setupFieldInvalid($region_errors, 'currency_code') ?>" id="setupCurrency" name="currency_code" required>
                                <option value="">- Currency -</option>
                                <?php foreach ($currencies_array as $currency_code => $currency_name) { ?>
                                    <option value="<?= escapeHtml($currency_code) ?>" <?= $form['currency_code'] === $currency_code ? 'selected' : '' ?>><?= escapeHtml("$currency_code - $currency_name") ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?= $hint('currency_code', 'The currency of ' . escapeHtml($company_country) . ' - change it if you bill in another.') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="setupTimezone">Timezone <strong class="text-danger">*</strong></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text"><i class="fa fa-fw fa-business-time"></i></span>
                            <select class="form-select select2<?= setupFieldInvalid($region_errors, 'timezone') ?>" id="setupTimezone" name="timezone" required <?= $region_errors ? '' : 'data-setup-browser-timezone' ?>>
                                <option value="">- Timezone -</option>
                                <?php foreach ($timezones as $tz) { ?>
                                    <option <?= $form['timezone'] === $tz ? 'selected' : '' ?>><?= escapeHtml($tz) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <?= $hint('timezone', 'The timezone of ' . escapeHtml($company_country) . '.') ?>
                    </div>
                </div>

                <div class="itflow-setup-sample mt-4" data-setup-sample hidden>
                    <span class="text-body-secondary">Looks like</span>
                    <span data-setup-sample-money></span>
                    <span data-setup-sample-date></span>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" name="add_localization_settings" class="btn btn-primary" data-setup-next>Save and carry on<i class="fas fa-arrow-right ms-2"></i></button>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
