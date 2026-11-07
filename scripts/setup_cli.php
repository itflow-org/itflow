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
 * --ask-missing asks only for what is needed and wasn't given, and the database
 * password - the rest takes its default or suggestion. Any option can come from
 * the environment instead, as ITFLOW_SETUP_<OPTION> (ITFLOW_SETUP_PASSWORD for
 * --password), so a password needn't be on the command line.
 *
 * Signing in the first time opens Get started, the walk through what is left to
 * set up (functions/getting_started.php). --skip-get-started leaves it in Admin,
 * for an install set up by script or brought up to match another.
 *
 * For an install another ITFlow manages - one you host for a client - the
 * connection code from its Admin > Managed Instances page (--manager-code)
 * connects it as setup finishes, and --manager-required keeps it connected
 * (functions/managed.php). That page gives the whole command, with what it knows
 * of the client filled in. --generate-password makes the first account's password
 * and shows it once at the end; --json ends with a summary for a script to read.
 *
 * Exit codes: 0 set up; 1 stopped, nothing set up; 3 set up, but it couldn't
 * connect to the managing ITFlow - connect it from Admin > Managed By.
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
// Given, never asked for - an install another ITFlow manages (functions/managed.php)
$cli_managed = [
    'manager-code' => 'The connection code from Admin > Managed Instances on the ITFlow that manages this one - it connects as setup finishes',
];
$cli_flags = [
    'manager-required' => "Keep it managed - its admins can't disconnect it. For an install you host",
    'generate-password' => 'Make a strong password for your account, shown once at the end, instead of asking for one',
    'skip-get-started' => "Don't open Get started on first signing in, or show Finish setting up on the dashboard - it stays in Admin > Get started",
    'share-telemetry' => 'Tell the ITFlow project about this install, once - its company name, website, city, state, country and currency',
    'comments' => 'Something to tell the ITFlow project with it (optional)',
    'ask-missing' => 'Ask only for what is needed and not given, and the database password - the rest takes its default or suggestion',
    'non-interactive' => 'Ask for nothing - stop if anything needed is missing or wrong',
    'json' => 'End with a summary in JSON on standard output - the progress goes to standard error',
    'help' => 'Show this help',
];

$longopts = ['help', 'non-interactive', 'ask-missing', 'share-telemetry', 'skip-get-started', 'manager-required', 'generate-password', 'json', 'comments:', 'manager-code:'];
foreach ($cli_fields as $fields) {
    foreach ($fields as $key => $field) {
        $longopts[] = "$key:";
    }
}
$options = getopt('', $longopts);

// An option left off can come from the environment - ITFLOW_SETUP_PASSWORD for --password - so no password has to be typed on the command line
foreach (array_merge(array_keys(array_merge(...array_values($cli_fields))), array_keys($cli_managed), ['comments']) as $key) {
    $env = getenv('ITFLOW_SETUP_' . strtoupper(str_replace('-', '_', $key)));
    if (!array_key_exists($key, $options) && $env !== false) {
        $options[$key] = $env;
    }
}
$non_interactive = isset($options['non-interactive']);
$ask_missing = !$non_interactive && isset($options['ask-missing']);
$json = isset($options['json']);

// With --json, everything but the summary goes to standard error
if ($json && !isset($options['help'])) {
    ob_start(function ($text) {
        fwrite(STDERR, $text);
        return '';
    }, 1);
}

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
    echo "Managed by another ITFlow:\n";
    foreach ($cli_managed as $key => $what) {
        echo sprintf("  --%-20s %s\n", $key, $what);
    }
    echo "\n";
    foreach ($cli_flags as $key => $what) {
        echo sprintf("  --%-20s %s\n", $key, $what);
    }
    echo "\nLeft out, an option is asked for - unless --non-interactive. The language, currency and timezone\n";
    echo "are suggested from the country, and --non-interactive takes the suggestion when there is one.\n";
    echo "Any option can be set in the environment instead, as ITFLOW_SETUP_ and its name - ITFLOW_SETUP_PASSWORD\n";
    echo "for --password, ITFLOW_SETUP_USER_PASSWORD for --user-password.\n\n";
    echo "Exit codes: 0 set up; 1 stopped, nothing set up; 3 set up, but it couldn't connect to the ITFlow\n";
    echo "managing it - connect it from Admin > Managed By with a new code.\n";
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

/* Stop, saying why - to stderr, with a failing exit code. With --json, said in the summary too. */
function cliStop($message) {
    global $json;
    fwrite(STDERR, "\n$message\n");
    if ($json) {
        fwrite(STDOUT, json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
    }
    exit(1);
}

/* A path for a command line - as it is, or quoted when it has anything a shell would read. */
function cliShellWord($text) {
    return preg_match('#^[A-Za-z0-9_./=:@%+-]+$#', $text) ? $text : escapeshellarg($text);
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
 * --non-interactive, a missing or wrong one stops setup. With --ask-missing,
 * only what is needed with no default, or a secret, is asked for.
 */
function cliAsk($key, $label, $required, $default = '', $check = null, $secret = false) {
    global $options, $non_interactive, $ask_missing;

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
    } elseif ($non_interactive || ($ask_missing && !$secret && !($required && (string) $default === ''))) {
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

// The managing ITFlow's code - a code that can't work stops setup before anything is done
$manager_code = trim((string) ($options['manager-code'] ?? ''));
$manager_required = isset($options['manager-required']);
$manager = $manager_code !== '' ? managedParseCode($manager_code) : null;
if ($manager_code !== '' && !$manager) {
    cliStop("--manager-code: that isn't a connection code - copy it again from Admin > Managed Instances on the ITFlow that manages this one. It starts ITFLOW-MANAGE:");
}
if ($manager || $manager_required) {
    echo "\n== Managed ==\n";
    echo $manager ? "  Connects to {$manager['manager_url']} once it is set up" . ($manager_required ? ", and stays connected - its admins can't disconnect it" : '') . ".\n"
        : "  Kept managed - connect it from Admin > Managed By with a code from the ITFlow that manages it.\n";
}
if (isset($options['generate-password']) && trim((string) ($options['user-password'] ?? '')) !== '') {
    cliStop('Give --user-password or --generate-password, not both.');
}

// Asking nothing, all that has no default or suggestion has to be given - said before anything is done
if ($non_interactive) {
    foreach ($cli_fields as $fields) {
        foreach ($fields as $key => $field) {
            if ($field[1] && !isset($field[2]) && !in_array($key, ['locale', 'currency', 'timezone'], true) && trim((string) ($options[$key] ?? '')) === ''
                && !($key === 'user-password' && isset($options['generate-password']))) {
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
$password_generated = isset($options['generate-password']);
if ($password_generated) {
    $user['password'] = generateReadablePassword();
    echo "  Password: made for it - shown at the end, once.\n";
}
while (!$password_generated) {
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
if (!$share && !$non_interactive && !$ask_missing) {
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

// Managed by another ITFlow - turned on in config.php, as by hand (functions/managed.php)
if ($manager || $manager_required) {
    $managed_line = $manager_required ? '$config_manager_required = true;' : '$config_enable_managed_by = true;';
    if (@file_put_contents('../config.php', "\n// Managed by another ITFlow - its admins " . ($manager_required ? "can't disconnect it" : 'can disconnect it in Admin > Managed By') . "\n$managed_line\n", FILE_APPEND | LOCK_EX) === false) {
        cliStop("Setup is done, but config.php could not be written to. Add these lines to the end of it: $managed_line \$config_enable_setup = 0;");
    }
    $config_enable_managed_by = true;
    $config_manager_required = $manager_required;
    echo "  Managed mode is on in config.php.\n";
}

if (!setupClose('../config.php')) {
    cliStop("Setup is done, but config.php could not be written to. Add this line to the end of it: \$config_enable_setup = 0;");
}

// The key backups are encrypted with - made now, so it can be written down now
$config_backup_key = null;
$backup_key = backupEncryptionKey();

// Connected last, so an ITFlow that can't be reached leaves a whole install behind
$manager_result = null;
if ($manager) {
    $enroll_error = '';
    if (managedEnroll($manager_code, "https://$base_url", $enroll_error)) {
        $manager_result = ['connected' => true, 'name' => managedManagerName(), 'url' => $manager['manager_url']];
        echo "  Connected to {$manager_result['name']} - it checks in every minute once its cron job runs.\n";
    } else {
        $manager_result = ['connected' => false, 'url' => $manager['manager_url'], 'error' => $enroll_error];
        echo "  Not connected to {$manager['manager_url']}: $enroll_error\n";
    }
}

$itflow_path = realpath(__DIR__ . '/..');
$cron_command = cliShellWord(PHP_BINARY !== '' ? PHP_BINARY : 'php') . ' ' . cliShellWord("$itflow_path/cron/cron.php") . ' >/dev/null';

echo "\nITFlow is set up. Sign in at https://$base_url/login.php" . ($skip_get_started ? '' : " - Get started opens and walks you through the rest") . "\n";
if ($password_generated) {
    echo "\nPassword for {$user['email']} - it isn't shown again:\n\n";
    echo "     {$user['password']}\n";
}
if ($backup_key !== '') {
    echo "\nBackup key - keep a copy somewhere safe, away from this server; a backup can't be restored without it:\n\n";
    echo "     $backup_key\n";
}
echo "\nNext, add its cron job - nothing is emailed, turned into tickets, billed or backed up until it runs.\n";
echo "In the crontab of the user that owns the ITFlow files:\n\n";
echo "     * * * * * $cron_command\n\n";
echo "Or in a hosting control panel such as Jabali, HestiaCP or cPanel, a cron job that runs every minute\n";
echo "(* * * * *) with this command:\n\n";
echo "     $cron_command\n\n";
echo "Jobs start as soon as it runs. Their schedules are in Maintenance > Cron.\n";
if ($manager_result && $manager_result['connected']) {
    echo "Until then, {$manager_result['name']} shows it as waiting for its cron job.\n";
} elseif ($manager_result) {
    echo "\nIt isn't connected to the ITFlow that manages it. Make a new connection code there, in Admin > Managed\n";
    echo "Instances, and paste it into Admin > Managed By here.\n";
}
echo "\n";

if ($json) {
    ob_end_flush();
    fwrite(STDOUT, json_encode([
        'ok' => true,
        'url' => "https://$base_url",
        'sign_in' => "https://$base_url/login.php",
        'account' => ['name' => $user['name'], 'email' => $user['email']] + ($password_generated ? ['password' => $user['password']] : []),
        'backup_key' => $backup_key,
        'cron' => ['schedule' => '* * * * *', 'command' => $cron_command],
        'manager' => $manager_result ?? ($manager_required ? ['connected' => false, 'error' => 'No connection code was given - connect it from Admin > Managed By'] : null),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
}

exit($manager_result && !$manager_result['connected'] ? 3 : 0);
