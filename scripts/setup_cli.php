#!/usr/bin/env php
<?php

/*
 * ITFlow setup from the command line - the web wizard's steps in one go, through
 * the same code: its checks (setup/setup_checks.php), its database step
 * (setup_database.php), its checks on what it is given (setup_forms.php) and its
 * writes (setup_install.php), so an install made here comes out as one made in a
 * browser does.
 *
 * Whatever is not given as an option is asked for, with a suggestion where there
 * is one - Enter takes it. --non-interactive asks nothing: it stops at the first
 * thing missing or wrong, before the database is touched, and takes the suggestion
 * or the default for the rest.
 *
 * Signing in the first time opens Get started, the walk through what is left to
 * set up (functions/getting_started.php). --skip-get-started leaves it in Admin,
 * for an install set up by script or brought up to match another.
 *
 *   php setup_cli.php --help
 *   php setup_cli.php --host=localhost --username=itflow --password=secret --database=itflow \
 *       --base-url=itflow.example.com --company-name="My Company" --country="United States" \
 *       --user-name="John Doe" --user-email=john@example.com --user-password='a long password' \
 *       --non-interactive
 */

// Run from here, so ../config.php and ../db.sql are ITFlow's own
chdir(__DIR__);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Every option - what it is, whether setup needs it, and its default
$cli_fields = [
    'Database' => [
        'database' => ['Database name', true],
        'host' => ['Database host', true, 'localhost'],
        'username' => ['Database user', true],
        'password' => ['Database password', false],
    ],
    'Where ITFlow is reached' => [
        'base-url' => ['Its address, without https:// (e.g. itflow.example.com)', true],
        'repo-branch' => ['Git branch it updates from', false, 'master'],
    ],
    'Your account - an administrator' => [
        'user-name' => ['Your name', true],
        'user-email' => ['Your email - what you sign in with', true],
        'user-password' => ['Password (at least 8 characters)', true],
    ],
    'Company' => [
        'company-name' => ['Company name', true],
        'country' => ['Country (e.g. United States)', true],
        'address' => ['Address', false],
        'city' => ['City', false],
        'state' => ['State / province', false],
        'zip' => ['Postal code', false],
        'phone-country-code' => ['Phone country code (e.g. 1)', false],
        'phone' => ['Phone', false],
        'company-email' => ['Company email', false],
        'website' => ['Website', false],
        'tax-id' => ['Tax ID', false],
    ],
    'Region' => [
        'locale' => ['Language (e.g. en_US)', true],
        'currency' => ['Currency code (e.g. USD)', true],
        'timezone' => ['Timezone (e.g. America/New_York)', true],
    ],
];
$cli_flags = [
    'skip-get-started' => "Don't open Get started on first signing in, or show Finish setting up on the dashboard - it stays in Admin > Get started",
    'share-telemetry' => 'Tell the ITFlow project about this install, once - its company name, website, city, state, country and currency',
    'comments' => 'Something to tell the ITFlow project with it (optional)',
    'non-interactive' => 'Ask for nothing - stop if anything needed is missing or wrong',
    'help' => 'Show this help',
];

$longopts = ['help', 'non-interactive', 'share-telemetry', 'skip-get-started', 'comments:'];
foreach ($cli_fields as $fields) {
    foreach ($fields as $key => $field) {
        $longopts[] = "$key:";
    }
}
$options = getopt('', $longopts);
$non_interactive = isset($options['non-interactive']);

if (isset($options['help'])) {
    echo "ITFlow setup from the command line\n\n";
    echo "Usage:\n  php setup_cli.php [options]\n\n";
    foreach ($cli_fields as $group => $fields) {
        echo "$group:\n";
        foreach ($fields as $key => $field) {
            echo sprintf("  --%-20s %s%s%s\n", $key, $field[0], $field[1] ? ' (needed)' : '', isset($field[2]) ? " [default: {$field[2]}]" : '');
        }
        echo "\n";
    }
    foreach ($cli_flags as $key => $what) {
        echo sprintf("  --%-20s %s\n", $key, $what);
    }
    echo "\nLeft out, an option is asked for - unless --non-interactive. The language, currency and timezone\n";
    echo "are suggested from the country, and --non-interactive takes the suggestion when there is one.\n";
    exit(0);
}

$config_error = '';
if (file_exists("../config.php")) {
    try {
        include_once "../config.php";
    } catch (Throwable $e) {
        $config_error = $e->getMessage();
    }
}

require_once "../functions.php";
require_once "../includes/database_version.php";
define('FROM_SETUP', true);
require_once "../setup/seed_data.php";
require_once "../setup/setup_checks.php";
require_once "../setup/setup_database.php";
require_once "../setup/setup_forms.php";
require_once "../setup/setup_install.php";
require_once "../includes/settings_localization_array.php";

$timezones = DateTimeZone::listIdentifiers();

/* Stop, saying why - to stderr, with a failing exit code. */
function cliStop($message) {
    fwrite(STDERR, "\n$message\n");
    exit(1);
}

/* A setup hint is written for the page - as plain text. */
function cliText($html) {
    return html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8');
}

/*
 * A line typed in answer - null at the end of input. A secret is not shown
 * as it is typed, and kept exactly as typed; anything else is trimmed.
 */
function cliPrompt($label, $secret = false) {
    echo "$label: ";
    $hidden = $secret && function_exists('posix_isatty') && @posix_isatty(STDIN) && trim((string) @shell_exec('command -v stty')) !== '';
    if ($hidden) {
        @shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($hidden) {
        @shell_exec('stty echo');
        echo "\n";
    }
    if ($line === false) {
        return null;
    }
    $line = rtrim($line, "\r\n");
    return $secret ? $line : trim($line);
}

/*
 * One answer - the option given, or else asked for (with its default) until
 * $check, which says what is wrong with a value or '', is happy. With
 * --non-interactive, a missing or wrong one stops setup.
 */
function cliAsk($key, $label, $required, $default = '', $check = null, $secret = false) {
    global $options, $non_interactive;

    // What was given rather than typed, said back
    $given = function ($value) use ($label, $secret) {
        if ($value !== '') {
            echo "  $label: " . ($secret ? str_repeat('*', 8) : $value) . "\n";
        }
        return $value;
    };
    $problem = function ($value) use ($required, $check) {
        if ($value === '') {
            return $required ? 'This is needed.' : '';
        }
        return $check ? (string) $check($value) : '';
    };

    if (array_key_exists($key, $options)) {
        $value = is_string($options[$key]) ? $options[$key] : '';
        $value = $secret ? $value : trim($value);
        if ($value === '') {
            $value = (string) $default;
        }
        $error = $problem($value);
        if ($error === '') {
            return $given($value);
        }
        if ($non_interactive) {
            cliStop("--$key: $error");
        }
        echo "  --$key: $error\n";
    } elseif ($non_interactive) {
        if ($required && (string) $default === '') {
            cliStop("--$key is needed - $label.");
        }
        return $given((string) $default);
    }

    while (true) {
        $value = cliPrompt($label . ((string) $default !== '' && !$secret ? " [$default]" : '') . ($required ? '' : ' (optional)'), $secret);
        if ($value === null) {
            cliStop("No answer for: $label.");
        }
        if ($value === '') {
            $value = (string) $default;
        }
        $error = $problem($value);
        if ($error === '') {
            return $value;
        }
        echo "  $error\n";
    }
}

/* Yes or no, asked - no unless answered yes. */
function cliConfirm($question) {
    $answer = cliPrompt("$question [y/N]");
    return $answer !== null && in_array(strtolower($answer), ['y', 'yes'], true);
}

echo "ITFlow setup\n";

// ---------- Already set up? ----------
if (file_exists('../config.php')) {
    if (isset($config_enable_setup) && $config_enable_setup == 0) {
        cliStop("ITFlow is already set up. To start again, remove config.php and empty the database.");
    }
    cliStop("config.php is already there" . ($config_error !== '' ? " (and can't connect: $config_error)" : '') . ". Finish setting up in a browser at /setup, or remove config.php and run this again.");
}

// ---------- What this server has ----------
echo "\n== Checks ==\n";
$checks = setupChecks(dirname(__DIR__), false);
foreach (['required' => 'Needed', 'recommended' => 'Worth having'] as $kind => $heading) {
    foreach ($checks[$kind] as $check) {
        echo sprintf("  %s %s - %s\n", $check['passed'] ? '[ok]' : ($kind === 'required' ? '[!!]' : '[--]'), $check['label'], $check['value']);
        if (!$check['passed'] && $check['fix'] !== '') {
            echo "       " . cliText($check['fix']) . "\n";
        }
    }
}
if (setupChecksFailed($checks['required'])) {
    cliStop("Fix what is marked [!!], then run this again.");
}

// Asking nothing, all that has no default or suggestion has to be given - said before anything is done
if ($non_interactive) {
    foreach ($cli_fields as $fields) {
        foreach ($fields as $key => $field) {
            if ($field[1] && !isset($field[2]) && !in_array($key, ['locale', 'currency', 'timezone'], true) && trim((string) ($options[$key] ?? '')) === '') {
                cliStop("--$key is needed - {$field[0]}.");
            }
        }
    }
}

// ---------- Database ----------
echo "\n== Database ==\n";
$database_defaults = ['database' => '', 'host' => 'localhost', 'username' => ''];
while (true) {
    $database = cliAsk('database', 'Database name', true, $database_defaults['database']);
    $host = cliAsk('host', 'Database host', true, $database_defaults['host'], 'setupHostProblem');
    $username = cliAsk('username', 'Database user', true, $database_defaults['username']);
    $password = cliAsk('password', 'Database password', false, '', null, true);

    $connection = setupConnect($host, $username, $password, $database, $connect_error);
    if ($connection && !setupTables($connection)) {
        echo "  Connected to $database on $host - it is empty.\n";
        break;
    }
    $why = $connection
        ? "$database already has tables in it. A new install needs an empty database - to bring a backup back, create an empty one, run setup, then restore."
        : "Could not connect to $database on $host as $username: $connect_error.";
    if ($non_interactive) {
        cliStop($why);
    }
    echo "  $why\n";
    // Asked again - with what was given, bar the password, as the defaults
    foreach (['database', 'host', 'username', 'password'] as $key) {
        unset($options[$key]);
    }
    $database_defaults = ['database' => $database, 'host' => $host, 'username' => $username];
}

echo "\n== Where ITFlow is reached ==\n";
$base_url = cliAsk('base-url', 'Its address, without https:// (e.g. itflow.example.com)', true, '', function ($value) {
    $value = rtrim(preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $value), '/');
    return $value === '' || preg_match('/\s/', $value) ? 'An address like itflow.example.com, or example.com/itflow.' : '';
});
$base_url = rtrim(preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $base_url), '/');
$repo_branch = $options['repo-branch'] ?? 'master';
$repo_branch = is_string($repo_branch) && trim($repo_branch) !== '' ? trim($repo_branch) : 'master';

// ---------- Your account ----------
echo "\n== Your account - an administrator ==\n";
$user_field = function ($field) {
    return function ($value) use ($field) {
        $sample = ['name' => 'Name', 'email' => 'name@example.com', 'password' => str_repeat('x', USER_PASSWORD_MIN)];
        [, $errors] = setupUserForm([$field => $value] + $sample);
        return $errors[$field] ?? '';
    };
};
$user = [
    'name' => cliAsk('user-name', 'Your name', true, '', $user_field('name')),
    'email' => cliAsk('user-email', 'Your email - what you sign in with', true, '', $user_field('email')),
];
$password_given = array_key_exists('user-password', $options);
while (true) {
    $user['password'] = cliAsk('user-password', 'Password (at least ' . USER_PASSWORD_MIN . ' characters)', true, '', 'userPasswordProblem', true);
    if ($password_given || $non_interactive) {
        break;
    }
    $again = cliPrompt('The same again', true);
    if ($again === null) {
        cliStop('No answer for: the password again.');
    }
    if ($again === $user['password']) {
        break;
    }
    echo "  The two passwords are not the same.\n";
}

// ---------- Company ----------
echo "\n== Company ==\n";
$country_names = [];
foreach ($countries_array as $country_name) {
    $country_names[strtolower($country_name)] = $country_name;
}
$company_field = function ($field) use ($countries_array) {
    return function ($value) use ($field, $countries_array) {
        [, $errors] = setupCompanyForm([$field => $value] + ['name' => 'Company', 'country' => $countries_array[0]], $countries_array);
        return $errors[$field] ?? '';
    };
};
$company = ['name' => cliAsk('company-name', 'Company name', true, '', $company_field('name'))];
$company['country'] = cliAsk('country', 'Country (e.g. United States)', true, '', function ($value) use ($country_names) {
    return isset($country_names[strtolower($value)]) ? '' : 'Not a country ITFlow knows - as it is named in Admin > Settings > Company, like United States or United Kingdom.';
});
$company['country'] = $country_names[strtolower($company['country'])];
foreach (['address' => 'address', 'city' => 'city', 'state' => 'state', 'zip' => 'zip', 'phone_country_code' => 'phone-country-code', 'phone' => 'phone', 'email' => 'company-email', 'website' => 'website', 'tax_id' => 'tax-id'] as $field => $key) {
    $company[$field] = cliAsk($key, $cli_fields['Company'][$key][0], false, '', in_array($field, ['email', 'website'], true) ? $company_field($field) : null);
}
[$company] = setupCompanyForm($company, $countries_array);

// ---------- Region ----------
echo "\n== Region ==\n";
$iso2 = $country_iso2_array[$company['country']] ?? '';
$shell_language = str_replace('_', '-', strtok((string) (getenv('LC_ALL') ?: getenv('LANG') ?: ''), '.'));
$region_field = function ($field) use ($locales_array, $currencies_array, $timezones) {
    return function ($value) use ($field, $locales_array, $currencies_array, $timezones) {
        $sample = ['locale' => 'en_US', 'currency_code' => 'USD', 'timezone' => 'UTC'];
        [, $errors] = setupRegionForm([$field => $value] + $sample, $locales_array, $currencies_array, $timezones);
        return $errors[$field] ?? '';
    };
};
$region = [
    'locale' => cliAsk('locale', 'Language (e.g. en_US)', true, setupSuggestLocale($iso2, in_array($shell_language, ['', 'C', 'POSIX'], true) ? '' : $shell_language, $locales_array), $region_field('locale')),
    'currency_code' => strtoupper(cliAsk('currency', 'Currency code (e.g. USD)', true, setupSuggestCurrency($iso2, $currencies_array), $region_field('currency_code'))),
    'timezone' => cliAsk('timezone', 'Timezone (e.g. America/New_York)', true, setupSuggestTimezone($iso2), $region_field('timezone')),
];

// ---------- Telling the ITFlow project ----------
$share = isset($options['share-telemetry']);
$comments = is_string($options['comments'] ?? null) ? trim($options['comments']) : '';
if (!$share && !$non_interactive) {
    echo "\n== Tell the ITFlow project about this install? ==\n";
    echo "  Once, now: the company's name, website, city, state, country and currency, and this install's id.\n";
    $share = cliConfirm('Share them');
    if ($share && !array_key_exists('comments', $options)) {
        $comments = (string) cliPrompt('Anything to tell us? (Enter for nothing)');
    }
}

// ---------- Setting it up ----------
echo "\n== Setting up ==\n";
if (!setupImportSchema($connection, '../db.sql', $import_error)) {
    cliStop("Creating ITFlow's tables failed: $import_error. The tables it had made were taken away again - fix the cause and run this again.");
}
echo "  ITFlow's tables are made.\n";

$installation_id = randomString(32);
if (@file_put_contents('../config.php', setupConfigContents($host, $username, $password, $database, $base_url, $installation_id, $repo_branch)) === false) {
    setupDropTables($connection);
    cliStop("config.php could not be written to " . dirname(__DIR__) . " - make the folder writable, then run this again.");
}
echo "  config.php is written.\n";

// What follows writes through the global connection, as the wizard's does
$mysqli = $connection;

setupCreateFirstUser($mysqli, $user);
echo "  Your account, {$user['name']}, is made.\n";
setupCreateCompany($mysqli, $company);
echo "  {$company['name']} is saved.\n";
setupSaveRegion($mysqli, $region);
echo "  {$region['locale']}, {$region['currency_code']} and {$region['timezone']} are saved.\n";

if ($share) {
    echo setupShareTelemetry($mysqli, $installation_id, $comments) ? "  Thank you - the ITFlow project has it.\n" : "  The ITFlow project could not be reached - nothing was sent.\n";
}

$skip_get_started = isset($options['skip-get-started']);
if ($skip_get_started) {
    gettingStartedDismiss();
    echo "  Get started won't open on signing in - it is in Admin > Get started.\n";
}

if (!setupClose('../config.php')) {
    cliStop("Setup is done, but config.php could not be written to. Add this line to the end of it: \$config_enable_setup = 0;");
}

// The key backups are encrypted with - made now, so it can be written down now
$config_backup_key = null;
$backup_key = backupEncryptionKey();

$itflow_path = realpath(__DIR__ . '/..');
echo "\nITFlow is set up. Sign in at https://$base_url/login.php" . ($skip_get_started ? '' : " - Get started opens and walks you through the rest") . "\n";
if ($backup_key !== '') {
    echo "\nBackup key - keep a copy somewhere safe, away from this server; a backup can't be restored without it:\n\n";
    echo "     $backup_key\n";
}
echo "\nNext, before ITFlow can send mail, parse tickets or bill anyone:\n\n";
echo "  1. Add this single entry to the crontab of the user that owns the ITFlow files:\n\n";
echo "     * * * * * php $itflow_path/cron/cron.php >/dev/null\n\n";
echo "  2. Turn cron on in Maintenance > Cron. It ships off, and every job stops\n";
echo "     itself until it is enabled. Per-job schedules live on the same page.\n\n";

exit(0);
