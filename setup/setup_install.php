<?php

/*
 * What setup writes - the first account, the company and its region, and the
 * note to the ITFlow project - from values setup_forms.php has checked.
 * Shared by the web wizard (setup/index.php) and scripts/setup_cli.php, so an
 * install made either way comes out the same.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

/*
 * The first account - an administrator, with its share of the vault key under
 * its password, exactly as typed. Its id - normally 1, but the table's
 * AUTO_INCREMENT can already have moved on.
 */
function setupCreateFirstUser($mysqli, array $user) {
    $name = escapeSql($user['name']);
    $email = escapeSql($user['email']);
    $password = escapeSql(password_hash($user['password'], PASSWORD_DEFAULT));
    $ciphertext = escapeSql(setupFirstUserSpecificKey($user['password'], randomString()));

    mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_specific_encryption_ciphertext = '$ciphertext', user_role_id = 3");
    $user_id = intval(mysqli_insert_id($mysqli));
    mysqli_query($mysqli, "INSERT INTO user_settings SET user_id = $user_id");
    mkdirMissing(dirname(__DIR__) . "/uploads/users/$user_id");

    return $user_id;
}

/* The company - and the defaults every install starts with (seed_data.php). */
function setupCreateCompany($mysqli, array $company) {
    $sql_values = [];
    foreach (['name', 'address', 'city', 'state', 'zip', 'country', 'phone_country_code', 'phone', 'email', 'website', 'tax_id'] as $field) {
        $sql_values[] = "company_$field = '" . escapeSql($company[$field] ?? '') . "'";
    }
    mysqli_query($mysqli, "INSERT INTO companies SET " . implode(', ', $sql_values));
    seedDefaultData($mysqli);
}

/* The language, currency and timezone - and the Cash account, in that currency. */
function setupSaveRegion($mysqli, array $region) {
    $locale = escapeSql($region['locale']);
    $currency_code = escapeSql($region['currency_code']);
    $timezone = escapeSql($region['timezone']);

    mysqli_query($mysqli, "UPDATE companies SET company_locale = '$locale', company_currency = '$currency_code' WHERE company_id = 1");
    mysqli_query($mysqli, "UPDATE settings SET config_timezone = '$timezone' WHERE company_id = 1");
    mysqli_query($mysqli, "INSERT INTO accounts SET account_name = 'Cash', account_type = 4, account_currency_code = '$currency_code'");
}

/* What sharing sends the ITFlow project about this install - besides its id and any comment. */
function setupTelemetryDetails($mysqli) {
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_website, company_city, company_state, company_country, company_currency FROM companies WHERE company_id = 1")) ?: [];
    return [
        'company_name' => (string) ($row['company_name'] ?? ''),
        'website' => (string) ($row['company_website'] ?? ''),
        'city' => (string) ($row['company_city'] ?? ''),
        'state' => (string) ($row['company_state'] ?? ''),
        'country' => (string) ($row['company_country'] ?? ''),
        'currency' => (string) ($row['company_currency'] ?? ''),
    ];
}

/*
 * Tell the ITFlow project about this install, once - when asked to. Best
 * effort: an install that can't reach it still finishes, after 10 seconds at
 * most. True when the project answered.
 */
function setupShareTelemetry($mysqli, $installation_id, $comments) {
    mysqli_query($mysqli, "UPDATE settings SET config_telemetry = 2");

    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => 'Content-type: application/x-www-form-urlencoded',
        'content' => http_build_query(['installation_id' => (string) $installation_id] + setupTelemetryDetails($mysqli) + ['comments' => (string) $comments, 'collection_method' => 1]),
        'timeout' => 10,
    ]]);

    return @file_get_contents('https://telemetry.itflow.org', false, $context) !== false;
}
