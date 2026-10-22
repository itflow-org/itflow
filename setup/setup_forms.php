<?php

/*
 * The setup wizard's account, company and region steps (setup/index.php) -
 * what was sent, checked: each returns [values, errors], the errors keyed by
 * field and said in words, so a step that has something to say shows it
 * beside the field with everything typed kept. And what Region suggests from
 * the company's country.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

const SETUP_IMAGE_MAX_BYTES = 5 * 1024 * 1024;

/* The account step - name, email and the password, typed twice. The password is kept exactly as typed, as the sign-in page checks it. */
function setupUserForm(array $post) {
    $values = [
        'name' => trim((string) ($post['name'] ?? '')),
        'email' => trim((string) ($post['email'] ?? '')),
        'password' => (string) ($post['password'] ?? ''),
    ];
    $errors = [];
    if ($values['name'] === '') {
        $errors['name'] = 'Your name, as your team will see it.';
    } elseif (mb_strlen($values['name']) > 200) {
        $errors['name'] = 'Up to 200 characters.';
    }
    if ($values['email'] === '') {
        $errors['email'] = 'The email you will sign in with.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 200) {
        $errors['email'] = 'That is not an email address.';
    }
    if (($problem = userPasswordProblem($values['password'])) !== '') {
        $errors['password'] = $problem;
    } elseif (array_key_exists('password_confirm', $post) && (string) $post['password_confirm'] !== $values['password']) {
        $errors['password_confirm'] = 'The two passwords are not the same.';
    }
    return [$values, $errors];
}

/*
 * What is wrong with an uploaded picture - '' when nothing is, or nothing was
 * sent. A real image of one of $types (IMAGETYPE_*), checked rather than
 * trusted by its name, up to 5 MB.
 */
function setupImageProblem($file, array $extensions, array $types, $kinds) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
        return '';
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($file['size'] ?? 0) > SETUP_IMAGE_MAX_BYTES) {
        return "That picture is too large - up to 5 MB.";
    }
    $image = checkFileUpload($file, $extensions) ? @getimagesize($file['tmp_name']) : false;
    if (!$image || !in_array($image[2], $types, true)) {
        return "That isn't a $kinds picture.";
    }
    return '';
}

/* The company step - its name and country, and the rest as given. The website loses its http:// and trailing slash. */
function setupCompanyForm(array $post, array $countries) {
    $values = [];
    foreach (['name', 'country', 'address', 'city', 'state', 'zip', 'email', 'website', 'tax_id'] as $field) {
        $values[$field] = mb_substr(trim((string) ($post[$field] ?? '')), 0, 200);
    }
    $values['phone_country_code'] = mb_substr(preg_replace('/[^0-9]/', '', (string) ($post['phone_country_code'] ?? '')), 0, 10);
    $values['phone'] = mb_substr(preg_replace('/[^0-9]/', '', (string) ($post['phone'] ?? '')), 0, 200);
    $values['website'] = rtrim(preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $values['website']), '/');
    if ($values['phone'] === '') {
        $values['phone_country_code'] = '';
    }

    $errors = [];
    if ($values['name'] === '') {
        $errors['name'] = "Your company's name, as it goes on invoices.";
    }
    if (!in_array($values['country'], $countries, true)) {
        $errors['country'] = 'Pick the country the company is in.';
    }
    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That is not an email address.';
    }
    if ($values['website'] !== '' && !preg_match('/^[^\s\/]+\.[^\s\/]+(\/\S*)?$/', $values['website'])) {
        $errors['website'] = 'A website looks like company.com.';
    }
    return [$values, $errors];
}

/* The region step - a language, currency and timezone from the lists offered. */
function setupRegionForm(array $post, array $locales, array $currencies, array $timezones) {
    $values = [
        'locale' => trim((string) ($post['locale'] ?? '')),
        'currency_code' => strtoupper(trim((string) ($post['currency_code'] ?? ''))),
        'timezone' => trim((string) ($post['timezone'] ?? '')),
    ];
    $errors = [];
    if (!isset($locales[$values['locale']])) {
        $errors['locale'] = 'Pick the language dates and numbers are written in.';
    }
    if (!isset($currencies[$values['currency_code']])) {
        $errors['currency_code'] = 'Pick the currency you bill in.';
    }
    if (!in_array($values['timezone'], $timezones, true)) {
        $errors['timezone'] = 'Pick your timezone.';
    }
    return [$values, $errors];
}

/* The currency a country uses (intl knows) - when it is one ITFlow offers, or ''. */
function setupSuggestCurrency($iso2, array $currencies) {
    $iso2 = strtoupper((string) $iso2);
    if (!preg_match('/^[A-Z]{2}$/', $iso2) || !class_exists('NumberFormatter')) {
        return '';
    }
    $code = (new NumberFormatter("en_$iso2", NumberFormatter::CURRENCY))->getTextAttribute(NumberFormatter::CURRENCY_CODE);
    return isset($currencies[$code]) ? $code : '';
}

/*
 * The language for a country - the browser's own language there when ITFlow
 * has it (fr_CA for a French browser in Canada), then English there, then any
 * language of that country; failing those the browser's language, then en_US.
 */
function setupSuggestLocale($iso2, $accept_language, array $locales) {
    $iso2 = strtoupper((string) $iso2);
    $browser = '';
    if ($accept_language !== '' && function_exists('locale_accept_from_http')) {
        $browser = (string) locale_accept_from_http($accept_language);
    }
    $language = $browser !== '' ? strtolower(strtok(str_replace('-', '_', $browser), '_')) : '';

    $candidates = [];
    if (preg_match('/^[A-Z]{2}$/', $iso2)) {
        if ($language !== '') {
            $candidates[] = "{$language}_$iso2";
        }
        $candidates[] = "en_$iso2";
        foreach (array_keys($locales) as $locale) {
            if (substr($locale, -3) === "_$iso2") {
                $candidates[] = $locale;
            }
        }
    }
    if ($browser !== '') {
        $candidates[] = str_replace('-', '_', $browser);
        $candidates[] = $language;
    }
    $candidates[] = 'en_US';
    foreach ($candidates as $candidate) {
        if (isset($locales[$candidate])) {
            return $candidate;
        }
    }
    return '';
}

/* A country's timezone, when it has only the one - as the UK has, and Canada has not. */
function setupSuggestTimezone($iso2) {
    $iso2 = strtoupper((string) $iso2);
    if (!preg_match('/^[A-Z]{2}$/', $iso2)) {
        return '';
    }
    $zones = @DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $iso2) ?: [];
    return count($zones) === 1 ? $zones[0] : '';
}

/* The is-invalid class and the message under a field, when the step has one for it. */
function setupFieldInvalid(array $errors, $field) {
    return isset($errors[$field]) ? ' is-invalid' : '';
}
function setupFieldError(array $errors, $field) {
    return isset($errors[$field]) ? '<div class="invalid-feedback d-block" data-field-error="' . escapeHtml($field) . '">' . escapeHtml($errors[$field]) . '</div>' : '';
}
