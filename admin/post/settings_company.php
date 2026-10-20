<?php

/*
 * Admin > Settings > Company details (admin/settings_company.php): edit_company
 * saves the company - checked first, and sent back with what is wrong beside
 * each field when anything is - with a new logo, and the client record that
 * stands for it; remove_company_logo takes the logo off.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_company'])) {

    validateCSRFToken();

    $columns = ['company_name', 'company_tax_id', 'company_address', 'company_city', 'company_state', 'company_zip', 'company_country',
        'company_phone_country_code', 'company_phone', 'company_email', 'company_website', 'company_logo'];
    $before = settingsCurrent($columns, 'companies');
    $before_settings = settingsCurrent(['config_internal_client_id']);
    $localization = settingsLocalizationArrays();

    $after = [];
    foreach (['company_name', 'company_tax_id', 'company_address', 'company_city', 'company_state', 'company_zip', 'company_country', 'company_email'] as $column) {
        $after[$column] = mb_substr(trim((string) ($_POST[$column] ?? '')), 0, 200);
    }
    $after['company_website'] = mb_substr(settingsWebsite($_POST['company_website'] ?? ''), 0, 200);
    $after['company_phone'] = mb_substr(preg_replace('/[^0-9]/', '', (string) ($_POST['company_phone'] ?? '')), 0, 200);
    $after['company_phone_country_code'] = $after['company_phone'] === '' ? '' : mb_substr(preg_replace('/[^0-9]/', '', (string) ($_POST['company_phone_country_code'] ?? '')), 0, 10);

    $errors = [];
    if ($after['company_name'] === '') {
        $errors['company_name'] = 'Your company\'s name - it goes on every invoice and email.';
    }
    if ($after['company_country'] !== '' && !in_array($after['company_country'], $localization['countries'], true)) {
        $errors['company_country'] = 'Pick the country from the list.';
    }
    if ($after['company_email'] !== '' && !filter_var($after['company_email'], FILTER_VALIDATE_EMAIL)) {
        $errors['company_email'] = 'That is not an email address.';
    }
    if ($after['company_website'] !== '' && !preg_match('/^[^\s\/]+\.[^\s\/]+(\/\S*)?$/', $after['company_website'])) {
        $errors['company_website'] = 'A website looks like company.com.';
    }
    $logo_problem = settingsImageProblem($_FILES['company_logo'] ?? null, ['jpg', 'jpeg', 'png'], [IMAGETYPE_JPEG, IMAGETYPE_PNG], 'PNG or JPG');
    if ($logo_problem !== '') {
        $errors['company_logo'] = $logo_problem;
    }

    // The client record standing in for this company. Verified against the clients table
    // rather than trusted from the POST, so a stale or hand-made value cannot leave the
    // sidebar pointing at a client that no longer exists. 0 clears the designation.
    $internal_client_id = intval($_POST['config_internal_client_id'] ?? 0);
    if ($internal_client_id !== 0 && !mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $internal_client_id LIMIT 1"))) {
        $errors['config_internal_client_id'] = 'That client isn\'t there any more - pick another.';
    }

    if ($errors) {
        settingsRefuse('company', $errors);
    }

    // A new logo - in uploads/settings, the one it replaces taken away
    if (!empty($_FILES['company_logo']['tmp_name']) && ($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $new_logo = checkFileUpload($_FILES['company_logo'], ['jpg', 'jpeg', 'png']);
        if ($new_logo && move_uploaded_file($_FILES['company_logo']['tmp_name'], dirname(__DIR__, 2) . "/uploads/settings/$new_logo")) {
            $old_logo = (string) ($before['company_logo'] ?? '');
            if ($old_logo !== '' && basename($old_logo) === $old_logo && is_file(dirname(__DIR__, 2) . "/uploads/settings/$old_logo")) {
                unlink(dirname(__DIR__, 2) . "/uploads/settings/$old_logo");
            }
            $after['company_logo'] = $new_logo;
        }
    }

    $changed = settingsWrite('companies', $before, $after) + settingsWrite('settings', $before_settings, ['config_internal_client_id' => $internal_client_id]);
    settingsSaid('company', settingsChangeLines($before + $before_settings, $changed));

    redirect();

}

if (isset($_GET['remove_company_logo'])) {

    validateCSRFToken();

    $logo = (string) (settingsCurrent(['company_logo'], 'companies')['company_logo'] ?? '');
    if ($logo !== '' && basename($logo) === $logo && is_file(dirname(__DIR__, 2) . "/uploads/settings/$logo")) {
        unlink(dirname(__DIR__, 2) . "/uploads/settings/$logo");
    }

    mysqli_query($mysqli, "UPDATE companies SET company_logo = NULL WHERE company_id = 1");

    logAudit("Settings", "Edit", "$session_name took the company logo off");

    flashAlert("The logo is taken off");

    redirect();

}
