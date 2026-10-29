<?php

/*
 * Admin > Settings > Localization (admin/settings_localization.php):
 * edit_localization saves the language and currency (the company's) and the
 * timezone - each one ITFlow offers, or the page comes back saying which is not.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_localization'])) {

    validateCSRFToken();

    $localization = settingsLocalizationArrays();
    $before = settingsCurrent(['company_locale', 'company_currency'], 'companies');
    $before_settings = settingsCurrent(['config_timezone']);

    $after = [
        'company_locale' => trim((string) ($_POST['company_locale'] ?? '')),
        'company_currency' => strtoupper(trim((string) ($_POST['company_currency'] ?? ''))),
    ];
    $timezone = trim((string) ($_POST['config_timezone'] ?? ''));

    $errors = [];
    if (!isset($localization['locales'][$after['company_locale']])) {
        $errors['company_locale'] = 'Pick the language from the list.';
    }
    if (!isset($localization['currencies'][$after['company_currency']])) {
        $errors['company_currency'] = 'Pick the currency from the list.';
    }
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        $errors['config_timezone'] = 'Pick the timezone from the list.';
    }
    if ($errors) {
        settingsRefuse('localization', $errors);
    }

    $changed = settingsWrite('companies', $before, $after) + settingsWrite('settings', $before_settings, ['config_timezone' => $timezone]);
    settingsSaid('localization', settingsChangeLines($before + $before_settings, $changed));

    redirect();

}
