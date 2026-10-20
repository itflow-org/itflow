<?php

/*
 * Setup - Region: the language, currency and timezone ITFlow works in
 * (add_localization_settings in setup/index.php, which makes the Cash account
 * in that currency too). Saved once - changed later in Admin > Settings.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

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
            <form method="post" autocomplete="off">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="setupLocale">Language <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-language"></i></span>
                            <select class="form-select select2" id="setupLocale" name="locale" required>
                                <option value="">- Language -</option>
                                <?php foreach ($locales_array as $locale_code => $locale_name) { ?>
                                    <option value="<?= $locale_code ?>"><?= $locale_name ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="setupCurrency">Currency <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                            <select class="form-select select2" id="setupCurrency" name="currency_code" required>
                                <option value="">- Currency -</option>
                                <?php foreach ($currencies_array as $currency_code => $currency_name) { ?>
                                    <option value="<?= $currency_code ?>"><?= "$currency_code - $currency_name" ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="setupTimezone">Timezone <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-business-time"></i></span>
                            <select class="form-select select2" id="setupTimezone" name="timezone" required>
                                <option value="">- Timezone -</option>
                                <?php foreach ($timezones as $tz) { ?>
                                    <option value="<?= $tz ?>"><?= $tz ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" name="add_localization_settings" class="btn btn-primary" data-setup-next>Save and carry on<i class="fas fa-arrow-right ms-2"></i></button>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
