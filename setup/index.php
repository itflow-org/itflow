<?php

/*
 * ITFlow setup - the install wizard, at /setup until the last step closes it
 * ($config_enable_setup = 0 in config.php). Its steps: Welcome, Checks (what
 * the server needs - setup_checks.php), Database (setup_database.php, which
 * writes config.php), your account, the company, its region and Finish; and,
 * on an install with tables but no users yet, Restore from a backup. Each
 * step's handler is below, refusing to run twice; each step's page is in
 * steps/, drawn inside the frame at the bottom of this file.
 */

// config.php connects to the database as it loads - and mysqli throws when that fails
$config_error = '';
if (file_exists("../config.php")) {
    try {
        include "../config.php";
    } catch (Throwable $e) {
        $config_error = $e->getMessage();
    }
}

include "../functions.php"; // Global Functions
include "../includes/database_version.php";
define('FROM_SETUP', true);
require "seed_data.php"; // Shared install-time seed data
require "setup_checks.php";
require "setup_database.php";

// Setup's own session - for what a step has to say on the page after it
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    session_name('itflow_setup');
    session_start();
}

/* What a step has to say on the next page - $type success, info, warning or danger. */
function setupFlash($message, $type = 'success') {
    $_SESSION['alert_message'] = $message;
    $_SESSION['alert_type'] = $type;
}

$mysqli_available = isset($mysqli) && $mysqli instanceof mysqli;
$can_show_restore = false;
$should_skip_to_user = false;

/*
 * How far through the wizard this install already is. Each step is a separate question
 * because each one guards a different handler below - answering all of them with "are there
 * users?" is what used to lock the wizard out three steps early.
 *
 *   $database_ready    - config.php connects, and ITFlow's tables are there.
 *   $install_is_live   - users exist. A restore would destroy real data, so restore closes
 *                        and points at the CLI. One user row is enough.
 *   $company_exists    - the company step has run (companies row, and settings seeded).
 *   $localization_done - the localization step has run (company_locale filled in).
 */
$database_ready = false;
$install_is_live = false;
$company_exists = false;
$localization_done = false;
$resume_step = file_exists("../config.php") ? 'database' : 'checks';

if (file_exists("../config.php") && $mysqli_available) {
    $table_result = mysqli_query($mysqli, "SHOW TABLES LIKE 'users'");
    if ($table_result && mysqli_num_rows($table_result) > 0) {
        $database_ready = true;
        $should_skip_to_user = true;
        $resume_step = 'user';

        $user_count_result = mysqli_query($mysqli, "SELECT COUNT(*) AS user_count FROM users");
        if ($user_count_result) {
            $user_count_row = mysqli_fetch_assoc($user_count_result);
            if (intval($user_count_row['user_count']) > 0) {
                $install_is_live = true;
            }
        } else {
            // Cannot prove the install is empty, so treat it as live
            $install_is_live = true;
        }
    }

    if ($install_is_live) {
        $resume_step = 'company';

        $company_result = mysqli_query($mysqli, "SELECT company_locale FROM companies WHERE company_id = 1");
        if (!$company_result) {
            // Cannot prove either step is outstanding, so treat both as done
            $company_exists = true;
            $localization_done = true;
            $resume_step = 'telemetry';
        } elseif ($company_row = mysqli_fetch_assoc($company_result)) {
            $company_exists = true;
            $resume_step = 'localization';

            if (trim($company_row['company_locale'] ?? '') !== '') {
                $localization_done = true;
                $resume_step = 'telemetry';
            }
        }
    }

    // Restore needs a database connection and an empty install. A populated one restores
    // from the command line instead - scripts/restore_cli.php.
    if (!$install_is_live) {
        $all_tables = mysqli_query($mysqli, "SHOW TABLES");
        if ($all_tables && mysqli_num_rows($all_tables) > 0) {
            $can_show_restore = true;
        }
    }
}

/*
 * config.php is written when the database step completes, but $config_enable_setup is only
 * appended to it by the LAST step, so the flag is absent for the whole middle of an install
 * and the wizard has to stay open across that gap or it cannot be finished.
 *
 * Deriving the flag from the database instead - closing setup as soon as the install looked
 * "live" - is what stranded people: the first user made it live, three steps before there
 * were companies or settings rows, and /setup and /login.php then redirected at each other
 * until the browser gave up. Deriving it from any later step has the same shape, because the
 * step that writes the flag is behind the gate that reads it.
 *
 * So the page stays open until the flag says otherwise, and each handler below refuses to run
 * a second time on its own. That keeps the reason the derived flag was added in the first
 * place - the restore handler drops every table, imports whatever archive it is handed and
 * rewrites the uploads directory - without the page-level gate that came with it.
 */
if (!isset($config_enable_setup)) {
    $config_enable_setup = 1;
}

if ($config_enable_setup == 0) {
    header("Location: /login.php");
    exit;
}

include_once "../includes/settings_localization_array.php";
$errorLog = ini_get('error_log') ?: "Debian/Ubuntu default is usually /var/log/apache2/error.log";

// Get a list of all available timezones
$timezones = DateTimeZone::listIdentifiers();

// The Database step's form, when it has something to say about what was typed
$database_form = ['database' => '', 'host' => 'localhost', 'username' => ''];
$database_error = '';
$config_manual = '';

if (isset($_POST['add_database'])) {

    // Check if database has been set up already. If it has, direct user to edit directly instead.
    if (file_exists('../config.php')) {
        setupFlash("The database is already set up - any change to it is made in config.php.", 'info');
        header("Location: ?user");
        exit;
    }

    $host = trim((string) ($_POST['host'] ?? ''));
    $database = trim((string) ($_POST['database'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $database_form = ['database' => $database, 'host' => $host, 'username' => $username];

    if ($database === '' || $host === '' || $username === '') {
        $database_error = "Fill in the database's name, its host and the user to connect as.";
    } elseif (!preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
        $database_error = "The host is a name or an address - localhost, db.example.com or 10.0.0.5 - without a port or a slash.";
    } else {
        $connection = setupConnect($host, $username, $password, $database, $connect_error);
        if (!$connection) {
            $database_error = "Could not connect to <strong>" . escapeHtml($database) . "</strong> on <strong>" . escapeHtml($host) . "</strong> as <strong>" . escapeHtml($username) . "</strong>: "
                . escapeHtml($connect_error) . ". Check the details, and that the user may use that database.";
        } elseif (setupTables($connection)) {
            // Never run db.sql over tables that are already there
            $database_error = "<strong>" . escapeHtml($database) . "</strong> already has tables in it. A new install needs an empty database. To use an ITFlow database you already have, put its details in config.php by hand; to bring back a backup, create an empty database here first, then choose Restore a backup.";
        } elseif (!setupImportSchema($connection, '../db.sql', $import_error)) {
            $database_error = "Creating ITFlow's tables failed: " . escapeHtml($import_error) . ". The tables it had made were taken away again - fix the cause and try again.";
        } else {
            // The tables are there - now point ITFlow at them
            $installation_id = randomString(32);
            $new_config = setupConfigContents($host, $username, $password, $database, (string) ($_SERVER['HTTP_HOST'] ?? ''), $installation_id);
            if (@file_put_contents("../config.php", $new_config) !== false && file_exists('../config.php')) {
                setupConfigChanged("../config.php");
                setupFlash("Connected to <strong>" . escapeHtml($database) . "</strong> and created ITFlow's tables - now your account.");
                header("Location: ?user");
                exit;
            }
            // Written by hand instead - the page shows what to put in it
            $config_manual = $new_config;
        }
    }

}

if (isset($_POST['create_tables'])) {

    // config.php was made by hand, to an empty database - ITFlow's tables are still to create
    if ($database_ready || !$mysqli_available) {
        header("Location: ?database");
        exit;
    }
    if (setupTables($mysqli)) {
        $database_error = "That database already has tables in it, but not ITFlow's. Point config.php at an empty database.";
    } elseif (!setupImportSchema($mysqli, '../db.sql', $import_error)) {
        $database_error = "Creating ITFlow's tables failed: " . escapeHtml($import_error) . ". The tables it had made were taken away again - fix the cause and try again.";
    } else {
        setupFlash("Created ITFlow's tables - now your account.");
        header("Location: ?user");
        exit;
    }

}

if (isset($_POST['restore'])) {

    // Belt and braces: the page-level gate above already sends a live install to the login
    // page, but this handler is the destructive one, so it re-checks rather than trusting
    // that it was only reached through the form.
    if ($install_is_live || !$can_show_restore) {
        setupFlash("This install already has users. Restore over it from the command line instead: php scripts/restore_cli.php --file=/path/to/backup.zip", 'danger');
        header("Location: ?restore");
        exit;
    }

    // An upload larger than post_max_size arrives with $_FILES and $_POST both empty, so
    // PHP cannot tell us which field failed - it is worth naming, because a real full
    // backup is usually bigger than the limit and the old message just said the upload
    // failed.
    $max_upload_bytes = backupMaxUploadBytes();
    $content_length = intval($_SERVER['CONTENT_LENGTH'] ?? 0);

    $too_large = false;
    if (!isset($_FILES['backup_zip']) && $content_length > 0 && $max_upload_bytes > 0 && $content_length > $max_upload_bytes) {
        $too_large = true;
    } elseif (isset($_FILES['backup_zip']) && in_array($_FILES['backup_zip']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        $too_large = true;
    }

    if ($too_large) {
        setupFlash("That backup is too large to upload through a browser (this server accepts up to "
            . backupFormatBytes($max_upload_bytes)
            . "). Restore it from the command line instead - there is no size limit there. Copy the backup onto this server and run: php "
            . dirname(__DIR__) . "/scripts/restore_cli.php --file=/path/to/backup.zip", 'danger');
        header("Location: ?restore");
        exit;
    }

    if (!isset($_FILES['backup_zip']) || $_FILES['backup_zip']['error'] !== UPLOAD_ERR_OK) {
        setupFlash("No backup file was uploaded, or the upload failed.", 'danger');
        header("Location: ?restore");
        exit;
    }

    if (strtolower(pathinfo($_FILES['backup_zip']['name'], PATHINFO_EXTENSION)) !== 'zip') {
        setupFlash("Only .zip backup archives can be restored.", 'danger');
        header("Location: ?restore");
        exit;
    }

    // The key belongs to the install that MADE the backup, which on a rebuilt server is not
    // this one, so it is asked for rather than read from config.php.
    $restore_key = trim($_POST['backup_key'] ?? '');
    if ($restore_key === '') {
        $restore_key = $config_backup_key ?? '';
    }

    if ($restore_key === '') {
        setupFlash("Enter the backup encryption key. It is shown in Maintenance > Backup on the install that made this archive.", 'danger');
        header("Location: ?restore");
        exit;
    }

    $temp_zip = tempnam(sys_get_temp_dir(), "itflow_restore_upload_");
    if (!move_uploaded_file($_FILES['backup_zip']['tmp_name'], $temp_zip)) {
        @unlink($temp_zip);
        setupFlash("Could not save the uploaded backup file.", 'danger');
        header("Location: ?restore");
        exit;
    }
    @chmod($temp_zip, 0600);

    $restore_error = null;
    $restored = backupRestoreArchive($mysqli, $temp_zip, $restore_key, $restore_error);

    @unlink($temp_zip);

    if (!$restored) {
        setupFlash(escapeHtml((string) $restore_error), 'danger');
        header("Location: ?restore");
        exit;
    }

    // Close setup behind us. The gate above would now do this on its own because the
    // restored database has users, but the flag is what stops the wizard being reachable
    // at all.
    if (!setupClose(__DIR__ . "/../config.php")) {
        $_SESSION['alert_message'] = "Backup restored, but config.php could not be updated - please set \$config_enable_setup = 0 in it by hand.";
    } else {
        $_SESSION['alert_message'] = "Backup restored. Log in with the credentials that were in use when the backup was taken.";
    }

    header("Location: ../login.php");
    exit;
}

if (isset($_POST['add_user'])) {

    // SELECT COUNT(*) returns exactly one row whatever the count is, so the mysqli_num_rows()
    // test this replaces was always 1 and never fired: a resubmitted form created a second
    // user and then died on the duplicate user_settings row. $install_is_live is the same
    // count, taken at the top of the file, and it fails closed.
    if ($install_is_live) {
        setupFlash("Users already exist in the database. Clear them to reconfigure here.", 'info');
        header("Location: ?company");
        exit;
    }
    // Each step after the database needs the one before it
    if (!$database_ready) {
        header("Location: ?$resume_step");
        exit;
    }

    $name = escapeSql($_POST['name']);
    $email = escapeSql($_POST['email']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);

    //Generate master encryption key
    $site_encryption_master_key = randomString();

    //Generate user specific key
    $user_specific_encryption_ciphertext = setupFirstUserSpecificKey(trim($_POST['password']), $site_encryption_master_key);

    mysqli_query($mysqli,"INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext', user_role_id = 3");

    // Normally 1, but the table's AUTO_INCREMENT can already have moved on, so ask for it.
    $user_id = intval(mysqli_insert_id($mysqli));

    mkdirMissing("../uploads/users/$user_id");

    $avatar_message = '';

    //Check to see if a file is attached
    if (!empty($_FILES['file']['tmp_name'])) {

        // get details of the uploaded file
        $file_error = 0;
        $file_tmp_path = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // sanitize file-name
        $new_file_name = md5(time() . $file_name) . '.' . $file_extension;

        // check if file has one of the following extensions
        $allowed_file_extensions = array('jpg', 'jpeg', 'gif', 'png', 'webp');

        if (in_array($file_extension,$allowed_file_extensions) === false) {
            $file_error = 1;
        }

        //Check File Size
        if ($file_size > 2097152) {
            $file_error = 1;
        }

        if ($file_error == 0) {
            // directory in which the uploaded file will be moved
            $upload_file_dir = "../uploads/users/$user_id/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            //Set Avatar
            mysqli_query($mysqli,"UPDATE users SET user_avatar = '$new_file_name' WHERE user_id = $user_id");
        } else {
            $avatar_message = ' - the avatar was left off: it has to be a jpg, png, gif or webp image under 2 MB';
        }
    }

    //Create Settings
    mysqli_query($mysqli,"INSERT INTO user_settings SET user_id = $user_id");

    setupFlash("Account <strong>" . escapeHtml($_POST['name']) . "</strong> created$avatar_message.", $avatar_message ? 'warning' : 'success');

    header("Location: ?company");
    exit;

}

if (isset($_POST['add_company_settings'])) {

    // Run once. A second pass would add a second companies row and re-seed the defaults.
    if ($company_exists) {
        setupFlash("Company details have already been saved.", 'info');
        header("Location: ?localization");
        exit;
    }
    if (!$install_is_live) {
        header("Location: ?$resume_step");
        exit;
    }

    $name = escapeSql($_POST['name']);
    $country = escapeSql($_POST['country']);
    $address = escapeSql($_POST['address']);
    $city = escapeSql($_POST['city']);
    $state = escapeSql($_POST['state']);
    $zip = escapeSql($_POST['zip']);
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $email = escapeSql($_POST['email']);
    $website = escapeSql($_POST['website']);
    $tax_id = escapeSql($_POST['tax_id']);

    mysqli_query($mysqli,"INSERT INTO companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website', company_tax_id = '$tax_id'");

    $logo_message = '';

    //Check to see if a file is attached
    if (!empty($_FILES['file']['tmp_name'])) {

        // get details of the uploaded file
        $file_error = 0;
        $file_tmp_path = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // sanitize file-name
        $new_file_name = md5(time() . $file_name) . '.' . $file_extension;

        // check if file has one of the following extensions
        $allowed_file_extensions = array('jpg', 'jpeg', 'png');

        if (in_array($file_extension,$allowed_file_extensions) === false) {
            $file_error = 1;
        }

        //Check File Size
        if ($file_size > 2097152) {
            $file_error = 1;
        }

        if ($file_error == 0) {
            // directory in which the uploaded file will be moved
            $upload_file_dir = "../uploads/settings/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE companies SET company_logo = '$new_file_name' WHERE company_id = 1");
        } else {
            $logo_message = ' - the logo was left off: it has to be a jpg or png under 2 MB';
        }
    }

    // Seed the defaults shared with the CLI installer
    seedDefaultData($mysqli);

    setupFlash("Company <strong>" . escapeHtml($_POST['name']) . "</strong> saved$logo_message.", $logo_message ? 'warning' : 'success');

    header("Location: ?localization");
    exit;

}

if (isset($_POST['add_localization_settings'])) {

    // Run once. A second pass would add a second Cash account.
    if ($localization_done) {
        setupFlash("Localization has already been saved.", 'info');
        header("Location: ?telemetry");
        exit;
    }
    if (!$company_exists) {
        header("Location: ?$resume_step");
        exit;
    }

    $locale = escapeSql($_POST['locale']);
    $currency_code = escapeSql($_POST['currency_code']);
    $timezone = escapeSql($_POST['timezone']);

    mysqli_query($mysqli,"UPDATE companies SET company_locale = '$locale', company_currency = '$currency_code' WHERE company_id = 1");

    mysqli_query($mysqli,"UPDATE settings SET config_timezone = '$timezone' WHERE company_id = 1");

    // Create Default Cash Account
    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = 'Cash', account_type = 4, account_currency_code = '$currency_code'");

    setupFlash("Language, currency and timezone saved - one last step.");

    header("Location: ?telemetry");
    exit;

}

if (isset($_POST['add_telemetry'])) {

    // Finishing closes setup for good - not before the steps it needs are done
    if (!$localization_done) {
        header("Location: ?$resume_step");
        exit;
    }

    if (isset($_POST['share_data']) && $_POST['share_data'] == 1) {

        mysqli_query($mysqli,"UPDATE settings SET config_telemetry = 2");

        $comments = escapeSql($_POST['comments']);

        $sql = mysqli_query($mysqli,"SELECT company_city, company_country, company_currency, company_name, company_state,
            company_website FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_assoc($sql);

        $company_name = $row['company_name'];
        $website = $row['company_website'];
        $city = $row['company_city'];
        $state = $row['company_state'];
        $country = $row['company_country'];
        $currency = $row['company_currency'];

        $postdata = http_build_query(
            array(
                'installation_id' => "$installation_id",
                'company_name' => "$company_name",
                'website' => "$website",
                'city' => "$city",
                'state' => "$state",
                'country' => "$country",
                'currency' => "$currency",
                'comments' => "$comments",
                'collection_method' => 1
            )
        );

        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 10
            )
        );

        $context = stream_context_create($opts);

        // Best effort - an install that can't reach it still finishes
        @file_get_contents('https://telemetry.itflow.org', false, $context);

    }

    // Close setup - the sign-in page is next
    if (!setupClose(__DIR__ . "/../config.php")) {
        setupFlash("Setup is done, but config.php could not be written to. Add this line to the end of it by hand, then sign in: <code>\$config_enable_setup = 0;</code>", 'danger');
        header("Location: ?telemetry");
        exit;
    }

    header("Location: ../login.php");
    exit;

}

/*
 * ---------- the page ----------
 */

// The steps, in order - each with its place in the wizard, and the page it is
$setup_steps = [
    'welcome' => ['label' => 'Welcome', 'icon' => 'fa-paper-plane'],
    'checks' => ['label' => 'Checks', 'icon' => 'fa-clipboard-check'],
    'database' => ['label' => 'Database', 'icon' => 'fa-database'],
    'user' => ['label' => 'Your account', 'icon' => 'fa-user'],
    'company' => ['label' => 'Company', 'icon' => 'fa-building'],
    'localization' => ['label' => 'Region', 'icon' => 'fa-globe-americas'],
    'telemetry' => ['label' => 'Finish', 'icon' => 'fa-flag-checkered'],
];
$setup_step = 'welcome';
foreach (array_merge(array_keys($setup_steps), ['restore']) as $key) {
    if ($key !== 'welcome' && isset($_GET[$key])) {
        $setup_step = $key;
        break;
    }
}
// The Database step's errors, and the config.php to make by hand, show on it - wherever the form was sent from
if ($database_error !== '' || $config_manual !== '') {
    $setup_step = 'database';
}
$setup_order = array_keys($setup_steps);
$setup_resume_index = array_search($resume_step, $setup_order, true);
$setup_current_index = array_search($setup_step, $setup_order, true);

/* Where a step stands - done (behind where the install has got to), current or to come. */
function setupStepState($key) {
    global $setup_order, $setup_resume_index, $setup_step;
    if ($key === $setup_step) {
        return 'current';
    }
    return array_search($key, $setup_order, true) < $setup_resume_index ? 'done' : 'todo';
}

// The message a step left for this page
$setup_alert = '';
$setup_alert_type = 'info';
if (!empty($_SESSION['alert_message'])) {
    $setup_alert = $_SESSION['alert_message'];
    $setup_alert_type = in_array($_SESSION['alert_type'] ?? '', ['success', 'info', 'warning', 'danger'], true) ? $_SESSION['alert_type'] : 'info';
    $_SESSION['alert_message'] = '';
    $_SESSION['alert_type'] = '';
}

$setup_title = $setup_step === 'restore' ? 'Restore a backup' : $setup_steps[$setup_step]['label'];

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-lte-color-mode="off">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">

    <title><?= escapeHtml($setup_title) ?> - ITFlow Setup</title>

    <!-- Light or dark, as the system is set - before the first paint -->
    <script>
        (function () {
            try {
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                }
            } catch (e) {}
        })();
    </script>

    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">
</head>

<body class="itflow-setup bg-body-tertiary">

<div class="itflow-setup-wrap">

    <header class="itflow-setup-brand">
        <a href="index.php" class="text-reset text-decoration-none">
            <span class="text-primary fw-bold"><i class="fas fa-paper-plane me-2"></i>IT</span>Flow
        </a>
        <span class="itflow-setup-brand-sub">Setup</span>
    </header>

    <!-- Where the install has got to -->
    <nav class="itflow-setup-steps" aria-label="Setup steps">
        <ol class="list-unstyled d-none d-md-flex">
            <?php foreach ($setup_steps as $key => $step) {
                $state = setupStepState($key);
                $number = array_search($key, $setup_order, true) + 1;
                $marker = $state === 'done' ? '<i class="fas fa-check"></i>' : $number;
                $inner = '<span class="itflow-setup-step-marker">' . $marker . '</span><span class="itflow-setup-step-label">' . escapeHtml($step['label']) . '</span>'; ?>
                <li class="itflow-setup-step is-<?= $state ?>" data-setup-step="<?= $key ?>" <?= $state === 'current' ? 'aria-current="step"' : '' ?>>
                    <?php if ($state === 'done' || ($state === 'current' && $key !== 'welcome') || $key === 'checks' || array_search($key, $setup_order, true) === $setup_resume_index) { ?>
                        <a href="<?= $key === 'welcome' ? 'index.php' : "?$key" ?>"><?= $inner ?></a>
                    <?php } else { ?>
                        <span><?= $inner ?></span>
                    <?php } ?>
                </li>
            <?php } ?>
        </ol>
        <?php if ($setup_current_index !== false) { ?>
            <div class="d-md-none">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="fw-semibold"><?= escapeHtml($setup_title) ?></span>
                    <span class="text-body-secondary">Step <?= $setup_current_index + 1 ?> of <?= count($setup_steps) ?></span>
                </div>
                <div class="progress" style="height: 6px;" role="progressbar" aria-label="Setup progress" aria-valuenow="<?= $setup_current_index + 1 ?>" aria-valuemin="1" aria-valuemax="<?= count($setup_steps) ?>">
                    <div class="progress-bar" style="width: <?= round(($setup_current_index + 1) * 100 / count($setup_steps)) ?>%"></div>
                </div>
            </div>
        <?php } ?>
    </nav>

    <main>
        <?php if ($setup_alert !== '') { ?>
            <div class="alert alert-<?= $setup_alert_type ?> d-flex gap-2 align-items-start" role="alert" data-setup-alert="<?= $setup_alert_type ?>">
                <i class="fas fa-fw mt-1 <?= ['success' => 'fa-check-circle', 'info' => 'fa-info-circle', 'warning' => 'fa-exclamation-triangle', 'danger' => 'fa-times-circle'][$setup_alert_type] ?>"></i>
                <div class="flex-grow-1"><?= alertMessageHtml($setup_alert) ?></div>
            </div>
        <?php } ?>

        <?php require __DIR__ . "/steps/$setup_step.php"; ?>
    </main>

    <footer class="itflow-setup-footer">
        <a href="https://docs.itflow.org/installation" target="_blank" rel="noopener">Installation docs</a>
        <span aria-hidden="true">·</span>
        <a href="https://forum.itflow.org/t/support" target="_blank" rel="noopener">Ask on the forum</a>
        <?php if ($can_show_restore && $setup_step !== 'restore') { ?>
            <span aria-hidden="true">·</span>
            <a href="?restore">Restore a backup</a>
        <?php } ?>
        <div class="mt-2">
            ITFlow is free software under the <a href="https://www.gnu.org/licenses/gpl-3.0.en.html" target="_blank" rel="noopener">GNU General Public License</a>, without any warranty.
        </div>
    </footer>

</div>

<script src="/js/itflow_select.js"></script>
<script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/js/app.js"></script>

</body>

</html>
