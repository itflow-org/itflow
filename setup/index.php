<?php

if (file_exists("../config.php")) {
    include "../config.php";

}

include "../functions.php"; // Global Functions
include "../includes/database_version.php";
define('FROM_SETUP', true);
require "seed_data.php"; // Shared install-time seed data

$mysqli_available = isset($mysqli) && $mysqli instanceof mysqli;
$can_show_restore = false;
$should_skip_to_user = false;

/*
 * How far through the wizard this install already is. Each step is a separate question
 * because each one guards a different handler below - answering all of them with "are there
 * users?" is what used to lock the wizard out three steps early.
 *
 *   $install_is_live   - users exist. A restore would destroy real data, so restore closes
 *                        and points at the CLI. One user row is enough.
 *   $company_exists    - the company step has run (companies row, and settings seeded).
 *   $localization_done - the localization step has run (company_locale filled in).
 */
$install_is_live = false;
$company_exists = false;
$localization_done = false;
$resume_step = 'checks';

if (file_exists("../config.php") && $mysqli_available) {
    $table_result = mysqli_query($mysqli, "SHOW TABLES LIKE 'users'");
    if ($table_result && mysqli_num_rows($table_result) > 0) {
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

if (isset($_POST['add_database'])) {

    // Check if database has been set up already. If it has, direct user to edit directly instead.
    if (file_exists('../config.php')) {
        $_SESSION['alert_message'] = "Database already configured. Any further changes should be made by editing the config.php file.";
        header("Location: ?user");
        exit;
    }

    $host = filter_var(trim($_POST['host']), FILTER_SANITIZE_STRING);
    $database = filter_var(trim($_POST['database']), FILTER_SANITIZE_STRING);
    $username = filter_var(trim($_POST['username']), FILTER_SANITIZE_STRING);
    $password = filter_var(trim($_POST['password']), FILTER_SANITIZE_STRING);
    $config_base_url = $_SERVER['HTTP_HOST'];

    $installation_id = randomString(32);

    // Ensure variables meet specific criteria (very basic examples)
    if (!preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
        die('Invalid host format.');
    }

    // Test database connection before writing it to config.php

    $conn = mysqli_connect($host, $username, $password, $database);
    if (!$conn) {
        exit("<b>Database connection failed - please check and try again</b> <br> <br>" . mysqli_connect_error());
    }

    $new_config = "<?php\n\n";
    $new_config .= "\$dbhost = " . var_export($host, true) . ";\n";
    $new_config .= "\$dbusername = " . var_export($username, true) . ";\n";
    $new_config .= "\$dbpassword = " . var_export($password, true) . ";\n";
    $new_config .= "\$database = " . var_export($database, true) . ";\n";
    $new_config .= "\$mysqli = mysqli_connect(\$dbhost, \$dbusername, \$dbpassword, \$database) or die('Database Connection Failed');\n";
    $new_config .= "\$config_app_name = 'ITFlow';\n";
    $new_config .= sprintf("\$config_base_url = '%s';\n", addslashes($config_base_url));
    $new_config .= "\$config_https_only = TRUE;\n";
    $new_config .= "\$repo_branch = 'master';\n";
    $new_config .= "\$installation_id = '$installation_id';\n";

    if (file_put_contents("../config.php", $new_config) !== false && file_exists('../config.php')) {

        include "../config.php";


        // Name of the file
        $filename = '../db.sql';
        // Temporary variable, used to store current query
        $templine = '';
        // Read in entire file
        $lines = file($filename);
        // Loop through each line
        foreach ($lines as $line) {
            // Skip it if it's a comment
            if (substr($line, 0, 2) == '--' || $line == '')
                continue;

            // Add this line to the current segment
            $templine .= $line;
            // If it has a semicolon at the end, it's the end of the query
            if (substr(trim($line), -1, 1) == ';') {
                // Perform the query
                mysqli_query($mysqli, $templine);
                // Reset temp variable to empty
                $templine = '';
            }
        }

        $_SESSION['alert_message'] = "Database successfully added, now lets add a user.";
        header("Location: ?user");
        exit;

    } else {
        // There was an error writing the file
        // Display an error message and redirect to the setup page
        $_SESSION['alert_message'] = "Did not successfully write the config.php file to the filesystem, Please Input the database information again.";
        header("Location: ?database");
        exit;
    }

}

if (isset($_POST['restore'])) {

    // Belt and braces: the page-level gate above already sends a live install to the login
    // page, but this handler is the destructive one, so it re-checks rather than trusting
    // that it was only reached through the form.
    if ($install_is_live || !$can_show_restore) {
        $_SESSION['alert_message'] = "This install already has users. Restore over it from the command line instead: php scripts/restore_cli.php --file=/path/to/backup.zip";
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
        $_SESSION['alert_message'] = "That backup is too large to upload through a browser (this server accepts up to "
            . backupFormatBytes($max_upload_bytes)
            . "). Restore it from the command line instead - there is no size limit there. Copy the backup onto this server and run: php "
            . dirname(__DIR__) . "/scripts/restore_cli.php --file=/path/to/backup.zip";
        header("Location: ?restore");
        exit;
    }

    if (!isset($_FILES['backup_zip']) || $_FILES['backup_zip']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['alert_message'] = "No backup file was uploaded, or the upload failed.";
        header("Location: ?restore");
        exit;
    }

    if (strtolower(pathinfo($_FILES['backup_zip']['name'], PATHINFO_EXTENSION)) !== 'zip') {
        $_SESSION['alert_message'] = "Only .zip backup archives can be restored.";
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
        $_SESSION['alert_message'] = "Enter the backup encryption key. It is shown in Maintenance > Backup on the install that made this archive.";
        header("Location: ?restore");
        exit;
    }

    $temp_zip = tempnam(sys_get_temp_dir(), "itflow_restore_upload_");
    if (!move_uploaded_file($_FILES['backup_zip']['tmp_name'], $temp_zip)) {
        @unlink($temp_zip);
        $_SESSION['alert_message'] = "Could not save the uploaded backup file.";
        header("Location: ?restore");
        exit;
    }
    @chmod($temp_zip, 0600);

    $restore_error = null;
    $restored = backupRestoreArchive($mysqli, $temp_zip, $restore_key, $restore_error);

    @unlink($temp_zip);

    if (!$restored) {
        $_SESSION['alert_message'] = $restore_error;
        header("Location: ?restore");
        exit;
    }

    // Close setup behind us. The gate above would now do this on its own because the
    // restored database has users, but the flag is what stops the wizard being reachable
    // at all.
    $config_path = __DIR__ . "/../config.php";
    if (@file_put_contents($config_path, "\n\$config_enable_setup = 0;\n\n", FILE_APPEND | LOCK_EX) === false) {
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
        $_SESSION['alert_message'] = "Users already exist in the database. Clear them to reconfigure here.";
        header("Location: ?company");
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

    //Check to see if a file is attached
    if ($_FILES['file']['tmp_name'] != '') {

        // get details of the uploaded file
        $file_error = 0;
        $file_tmp_path = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));

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

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        } else {

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Create Settings
    mysqli_query($mysqli,"INSERT INTO user_settings SET user_id = $user_id");

    $_SESSION['alert_message'] = "User <strong>$name</strong> created";

    header("Location: ?company");
    exit;

}

if (isset($_POST['add_company_settings'])) {

    // Run once. A second pass would add a second companies row and re-seed the defaults.
    if ($company_exists) {
        $_SESSION['alert_message'] = "Company details have already been saved.";
        header("Location: ?localization");
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

    //Check to see if a file is attached
    if ($_FILES['file']['tmp_name'] != '') {

        // get details of the uploaded file
        $file_error = 0;
        $file_tmp_path = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));

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

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        } else {

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    // Seed the defaults shared with the CLI installer
    seedDefaultData($mysqli);

    $_SESSION['alert_message'] = "Company <strong>$name</strong> created";

    header("Location: ?localization");

}

if (isset($_POST['add_localization_settings'])) {

    // Run once. A second pass would add a second Cash account.
    if ($localization_done) {
        $_SESSION['alert_message'] = "Localization has already been saved.";
        header("Location: ?telemetry");
        exit;
    }

    $locale = escapeSql($_POST['locale']);
    $currency_code = escapeSql($_POST['currency_code']);
    $timezone = escapeSql($_POST['timezone']);

    mysqli_query($mysqli,"UPDATE companies SET company_locale = '$locale', company_currency = '$currency_code' WHERE company_id = 1");

    mysqli_query($mysqli,"UPDATE settings SET config_timezone = '$timezone' WHERE company_id = 1");

    // Create Default Cash Account
    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = 'Cash', account_currency_code = '$currency_code'");


    $_SESSION['alert_message'] = "Localization Info saved";

    header("Location: ?telemetry");

}

if (isset($_POST['add_telemetry'])) {

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
                'content' => $postdata
            )
        );

        $context = stream_context_create($opts);

        $result = file_get_contents('https://telemetry.itflow.org', false, $context);

        echo $result;

    }

    //final setup stages
    $myfile = fopen("../config.php", "a");

    $txt = "\$config_enable_setup = 0;\n\n";

    fwrite($myfile, $txt);

    fclose($myfile);

    header("Location: ../login.php");
    exit;

}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    <title>ITFlow Setup</title>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <!-- Custom Style Sheet -->
    <link href="/libs/select2/css/select2.min.css" rel="stylesheet" type="text/css">
    <link href="/libs/select2-bootstrap4-theme/select2-bootstrap4.min.css" rel="stylesheet" type="text/css">

</head>

<body class="hold-transition sidebar-mini">

<div class="wrapper text-sm">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-primary navbar-dark">

        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav">
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary shadow">

        <!-- Brand Logo -->
        <a href="https://itflow.org" class="brand-link">
            <h3 class="brand-text fw-light"><i class="fas fa-paper-plane text-primary me-2"></i><span class="text-primary text-bold">IT</span>Flow</h3>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link <?php if (!isset($_GET) || empty($_GET)) { echo 'active'; } ?>">
                            <i class="nav-icon fas fa-home text-info"></i>
                            <p>1 - Welcome</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?checks" class="nav-link <?php if (isset($_GET['checks'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-check"></i>
                            <p>2 - Checks</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?database" class="nav-link <?php if (isset($_GET['database'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-database"></i>
                            <p>3 - Database</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?user" class="nav-link <?php if (isset($_GET['user'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-user"></i>
                            <p>4 - User</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?company" class="nav-link <?php if (isset($_GET['company'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-briefcase"></i>
                            <p>5 - Company</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?localization" class="nav-link <?php if (isset($_GET['localization'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-globe-americas"></i>
                            <p>6 - Localization</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?telemetry" class="nav-link <?php if (isset($_GET['telemetry'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-share-alt"></i>
                            <p>7 - Telemetry</p>
                        </a>
                    </li>

                    <li class="nav-header">Utilities</li>

                    <li class="nav-item">
                        <a href="?restore" class="nav-link <?php if (isset($_GET['restore'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-upload text-warning"></i>
                            <p>Restore Backup</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">

        <!-- Main content -->
        <div class="content mt-3">
            <div class="container-fluid">

                <?php
                //Alert Feedback
                if (!empty($_SESSION['alert_message'])) {
                    ?>
                    <div class="alert alert-info" id="alert">
                        <?= escapeHtml($_SESSION['alert_message']) ?>
                        <button class='close' data-bs-dismiss='alert'>&times;</button>
                    </div>
                    <?php
                    $_SESSION['alert_type'] = '';
                    $_SESSION['alert_message'] = '';
                }
                ?>

                <?php if (isset($_GET['checks'])) {

                    $checks = [];

                    // Section: PHP Extensions
                    $phpExtensions = [];
                    $extensions = [
                        'php-mysqli' => 'mysqli',
                        'php-intl' => 'intl',
                        'php-curl' => 'curl',
                        'php-mbstring' => 'mbstring',
                        'php-gd' => 'gd',
                        'php-xml' => 'xml',
                    ];

                    foreach ($extensions as $name => $ext) {
                        $loaded = extension_loaded($ext);
                        $phpExtensions[] = [
                            'name' => "$name installed",
                            'passed' => $loaded,
                            'value' => $loaded ? 'Installed' : 'Not Installed',
                        ];
                    }

                    // Section: PHP Configuration
                    $phpConfig = [];

                    // Check if shell_exec is enabled
                    $disabled_functions = explode(',', ini_get('disable_functions'));
                    $disabled_functions = array_map('trim', $disabled_functions);
                    $shell_exec_enabled = !in_array('shell_exec', $disabled_functions);

                    $phpConfig[] = [
                        'name' => 'shell_exec is enabled',
                        'passed' => $shell_exec_enabled,
                        'value' => $shell_exec_enabled ? 'Enabled' : 'Disabled',
                    ];

                    // Check upload_max_filesize and post_max_size >= 500M
                    function toBytes($val) {
                        $val = trim($val);
                        $unit = strtolower(substr($val, -1));
                        $num = (float)$val;
                        switch ($unit) {
                            case 'g':
                                $num *= 1024;
                            case 'm':
                                $num *= 1024;
                            case 'k':
                                $num *= 1024;
                        }
                        return $num;
                    }

                    $required_bytes = 500 * 1024 * 1024; // 500M in bytes

                    $upload_max_filesize = ini_get('upload_max_filesize');
                    $post_max_size = ini_get('post_max_size');

                    $upload_passed = toBytes($upload_max_filesize) >= $required_bytes;
                    $post_passed = toBytes($post_max_size) >= $required_bytes;

                    $phpConfig[] = [
                        'name' => 'upload_max_filesize >= 500M',
                        'passed' => $upload_passed,
                        'value' => $upload_max_filesize,
                    ];

                    $phpConfig[] = [
                        'name' => 'post_max_size >= 500M',
                        'passed' => $post_passed,
                        'value' => $post_max_size,
                    ];

                    // Check PHP version >= 8.2.0
                    $php_version = PHP_VERSION;
                    $php_passed = version_compare($php_version, '8.2.0', '>=');

                    $phpConfig[] = [
                        'name' => 'PHP version >= 8.2.0',
                        'passed' => $php_passed,
                        'value' => $php_version,
                    ];

                    // Section: Shell Commands
                    $shellCommands = [];

                    if ($shell_exec_enabled) {
                        $commands = ['git'];

                        foreach ($commands as $command) {
                            $which = trim(shell_exec("which $command 2>/dev/null"));
                            $exists = !empty($which);
                            $shellCommands[] = [
                                'name' => "Command '$command' available",
                                'passed' => $exists,
                                'value' => $exists ? $which : 'Not Found',
                            ];
                        }
                    } else {
                        // If shell_exec is disabled, mark commands as unavailable
                        foreach (['whois', 'dig', 'git'] as $command) {
                            $shellCommands[] = [
                                'name' => "Command '$command' available",
                                'passed' => false,
                                'value' => 'shell_exec Disabled',
                            ];
                        }
                    }

                    // Section: SSL Checks
                    $sslChecks = [];

                    // Check if accessing via HTTPS
                    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
                    $sslChecks[] = [
                        'name' => 'Accessing via HTTPS',
                        'passed' => $https,
                        'value' => $https ? 'Yes' : 'No',
                    ];

                    // SSL Certificate Validity Check
                    if ($https) {
                        $streamContext = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
                        $socket = @stream_socket_client("ssl://{$_SERVER['HTTP_HOST']}:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $streamContext);

                        if ($socket) {
                            $params = stream_context_get_params($socket);
                            $cert = $params['options']['ssl']['peer_certificate'];
                            $certInfo = openssl_x509_parse($cert);

                            $validFrom = $certInfo['validFrom_time_t'];
                            $validTo = $certInfo['validTo_time_t'];
                            $currentTime = time();

                            $certValid = ($currentTime >= $validFrom && $currentTime <= $validTo);

                            $sslChecks[] = [
                                'name' => 'SSL Certificate is valid',
                                'passed' => $certValid,
                                'value' => $certValid ? 'Valid' : 'Invalid or Expired',
                            ];
                        } else {
                            $sslChecks[] = [
                                'name' => 'SSL Certificate is valid',
                                'passed' => false,
                                'value' => 'Unable to retrieve certificate',
                            ];
                        }
                    } else {
                        $sslChecks[] = [
                            'name' => 'SSL Certificate is valid',
                            'passed' => false,
                            'value' => 'Not using HTTPS',
                        ];
                    }

                    // Section: Domain Checks
                    $domainChecks = [];

                    // Check if the site has a valid FQDN
                    $fqdn = $_SERVER['HTTP_HOST'];
                    $isValidFqdn = (bool) filter_var('http://' . $fqdn, FILTER_VALIDATE_URL) && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $fqdn);

                    $domainChecks[] = [
                        'name' => 'Site has a valid FQDN',
                        'passed' => $isValidFqdn,
                        'value' => $fqdn,
                    ];

                    // Section: File Permissions
                    $filePermissions = [];

                    // Check if web user has write access to webroot directory
                    $webroot = $_SERVER['DOCUMENT_ROOT'];
                    $writable = is_writable($webroot);
                    $filePermissions[] = [
                        'name' => 'Web user has write access to webroot directory',
                        'passed' => $writable,
                        'value' => $webroot,
                    ];
                    ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-check me-2"></i>Step 1 - Setup Checks</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-bordered">
                                <tbody>
                                    <!-- PHP Extensions Section -->
                                    <tr class="bg-light">
                                        <th colspan="3">PHP Extensions</th>
                                    </tr>
                                    <?php foreach ($phpExtensions as $check): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($check['name']); ?></td>
                                            <td style="width: 50px; text-align: center;">
                                                <?php if ($check['passed']): ?>
                                                    <i class="fa fa-check" style="color:green"></i>
                                                <?php else: ?>
                                                    <i class="fa fa-times" style="color:red"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($check['value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <!-- PHP Configuration Section -->
                                    <tr class="bg-light">
                                        <th colspan="3">PHP Configuration</th>
                                    </tr>
                                    <?php foreach ($phpConfig as $check): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($check['name']); ?></td>
                                            <td style="width: 50px; text-align: center;">
                                                <?php if ($check['passed']): ?>
                                                    <i class="fa fa-check" style="color:green"></i>
                                                <?php else: ?>
                                                    <i class="fa fa-times" style="color:red"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($check['value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <!-- Shell Commands Section -->
                                    <tr class="bg-light">
                                        <th colspan="3">Shell Commands</th>
                                    </tr>
                                    <?php foreach ($shellCommands as $check): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($check['name']); ?></td>
                                            <td style="width: 50px; text-align: center;">
                                                <?php if ($check['passed']): ?>
                                                    <i class="fa fa-check" style="color:green"></i>
                                                <?php else: ?>
                                                    <i class="fa fa-times" style="color:red"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($check['value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <!-- SSL Checks Section -->
                                    <tr class="bg-light">
                                        <th colspan="3">SSL Checks</th>
                                    </tr>
                                    <?php foreach ($sslChecks as $check): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($check['name']); ?></td>
                                            <td style="width: 50px; text-align: center;">
                                                <?php if ($check['passed']): ?>
                                                    <i class="fa fa-check" style="color:green"></i>
                                                <?php else: ?>
                                                    <i class="fa fa-times" style="color:red"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($check['value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <!-- Domain Checks Section -->
                                    <tr class="bg-light">
                                        <th colspan="3">Domain Checks</th>
                                    </tr>
                                    <?php foreach ($domainChecks as $check): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($check['name']); ?></td>
                                            <td style="width: 50px; text-align: center;">
                                                <?php if ($check['passed']): ?>
                                                    <i class="fa fa-check" style="color:green"></i>
                                                <?php else: ?>
                                                    <i class="fa fa-times" style="color:red"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($check['value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <!-- File Permissions Section -->
                                    <tr class="bg-light">
                                        <th colspan="3">File Permissions</th>
                                    </tr>
                                    <?php foreach ($filePermissions as $check): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($check['name']); ?></td>
                                            <td style="width: 50px; text-align: center;">
                                                <?php if ($check['passed']): ?>
                                                    <i class="fa fa-check" style="color:green"></i>
                                                <?php else: ?>
                                                    <i class="fa fa-times" style="color:red"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($check['value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <hr>

                            <a href="?database" class="btn btn-primary text-bold">Next (Database)<i class="fa fa-fw fa-arrow-circle-right ms-2"></i></a>
                        </div>
                    </div>

                <?php } elseif (isset($_GET['database'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-database me-2"></i>Step 2 - Connect your Database</h3>
                        </div>
                        <div class="card-body">
                            <?php
                            if (file_exists('../config.php')) {

                                echo "<p>Database is already configured. Any further changes should be made by editing the <code>config.php</code> file.</p>";

                                if (@$mysqli) {
                                    echo "<a href='?user' class='btn btn-success text-bold mt-3'>Next Step (User Setup) <i class='fa fa-fw fa-arrow-circle-right ms-2'></i></a>";
                                } else {
                                    echo "<div class='alert alert-danger mt-3'>Database connection failed. Check <code>config.php</code>.</div>";
                                }

                            } else {
                            ?>
                                <form method="post" autocomplete="off">

                                    <h5>Database Connection Details</h5>

                                    <div class="mb-3">
                                        <label>Database Name <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                                <span class="input-group-text"><i class="fa fa-fw fa-database"></i></span>
                                            <input type="text" class="form-control" name="database" placeholder="Database name" autofocus required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label>Database Host <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                                <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                                            <input type="text" class="form-control" name="host" value="localhost" placeholder="Database Host" required>
                                        </div>
                                    </div>

                                    <br>
                                    <h5>Database Authentication Details</h5>

                                    <div class="mb-3">
                                        <label>Database User <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                            <input type="text" class="form-control" name="username" placeholder="Database user account" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label>Database Password <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                            <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Database user password" autocomplete="new-password" required>
                                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                        </div>
                                    </div>

                                    <hr>
                                    <button type="submit" name="add_database" class="btn btn-primary text-bold">
                                        Next (First User)<i class="fas fa-fw fa-arrow-circle-right ms-2"></i>
                                    </button>
                                </form>
                            <?php } ?>
                        </div>
                    </div>

                <?php } elseif (isset($_GET['restore'])) { ?>

                    <?php if (!$can_show_restore) { ?>
                        <div class="card card-danger">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-exclamation-triangle me-2"></i>Database Not Ready</h3>
                            </div>
                            <div class="card-body text-center">
                                <p>You must configure the database before restoring a backup.</p>
                                <a href="?database" class="btn btn-primary text-bold">
                                    Go to Database Setup <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="card card-dark">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-fw fa-database me-2"></i>Restore from Backup</h3>
                            </div>
                            <div class="card-body">
                                <?php $setup_max_upload = backupMaxUploadBytes(); ?>

                                <form method="post" enctype="multipart/form-data" autocomplete="off">
                                    <div class="mb-3">
                                        <label>ITFlow backup archive (.zip)</label>
                                        <input type="file" name="backup_zip" accept=".zip" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Backup encryption key</label>
                                        <input type="text" name="backup_key" class="form-control" placeholder="The key from the install that made this backup" autocomplete="off" required>
                                        <small class="text-muted">Shown in Maintenance &gt; Backup on that install. The archive cannot be opened without it.</small>
                                    </div>

                                    <div class="alert alert-warning mb-0">
                                        <strong>This server accepts uploads up to <?= escapeHtml(backupFormatBytes($setup_max_upload)) ?>.</strong>
                                        A full backup is usually larger than that. If yours is, copy it onto the server and restore from the
                                        command line instead - there is no size limit there:
                                        <pre class="bg-dark text-white p-2 mt-2 mb-0"><?= escapeHtml("php " . dirname(__DIR__) . "/scripts/restore_cli.php --file=/path/to/backup.zip") ?></pre>
                                    </div>

                                    <p class="text-muted mt-2 mb-0"><small>The restore replaces the database and the uploads folder. Large restores take several minutes - do not close this page.</small></p>
                                    <hr>
                                    <button type="submit" name="restore" class="btn btn-primary text-bold">
                                        Restore Backup<i class="fas fa-fw fa-upload ms-2"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php } ?>

                <?php } elseif (isset($_GET['user'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-user me-2"></i>Step 3 - Create your first user</h3>
                        </div>
                        <div class="card-body">

                            <?php if ($install_is_live): ?>

                                <p>This install already has a user - the rest of your team is added from Admin &gt; Users once you are logged in.</p>
                                <hr>
                                <a href="?<?= $resume_step ?>" class="btn btn-primary text-bold">Continue Setup <i class="fa fa-fw fa-arrow-circle-right"></i></a>

                            <?php else: ?>

                            <form method="post" enctype="multipart/form-data" autocomplete="off">
                                <div class="mb-3">
                                    <label>Name <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        <input type="text" class="form-control" name="name" placeholder="Full Name" maxlength="200" autofocus required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Email <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                        <input type="email" class="form-control" name="email" placeholder="Email Address" maxlength="200" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Password <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                        <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Enter a Password" autocomplete="new-password" required minlength="8">
                                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Avatar</label>
                                    <input type="file" class="form-control" accept="image/*;capture=camera" name="file">
                                </div>

                                <hr>

                                <button type="submit" name="add_user" class="btn btn-primary text-bold">Next (Company details) <i class="fa fa-fw fa-arrow-circle-right"></i></button>
                            </form>

                            <?php endif; ?>

                        </div>
                    </div>

                <?php } elseif (isset($_GET['company'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-briefcase me-2"></i>Step 4 - Company Details</h3>
                        </div>
                        <div class="card-body">

                            <?php if ($company_exists): ?>

                                <p>Company details have already been saved - they can be changed later from Admin &gt; Settings.</p>
                                <hr>
                                <a href="?<?= $resume_step ?>" class="btn btn-primary text-bold">Continue Setup <i class="fa fa-fw fa-arrow-circle-right"></i></a>

                            <?php else: ?>
                            <form method="post" enctype="multipart/form-data" autocomplete="off">

                                <div class="mb-3">
                                    <label>Company Name <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                        <input type="text" class="form-control" name="name" placeholder="Company Name" maxlength="200" autofocus required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Logo</label>
                                    <input type="file" class="form-control" name="file" accept=".jpg, .jpeg, .png">
                                </div>

                                <div class="mb-3">
                                    <label>Address</label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                        <input type="text" class="form-control" name="address" placeholder="Street Address">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>City</label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                                        <input type="text" class="form-control" name="city" placeholder="City">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>State / Province</label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                                        <input type="text" class="form-control" name="state" placeholder="State or Province">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Postal Code</label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                                        <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Country <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                                        <select class="form-control select2" name="country" required>
                                            <option value="">- Country -</option>
                                            <?php foreach($countries_array as $country_name) { ?>
                                                <option><?= $country_name ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Phone</label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                        <input type="tel" class="form-control" name="phone" placeholder="Phone Number">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Email</label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                        <input type="email" class="form-control" name="email" placeholder="Company Email address eg: info@company.com" maxlength="200">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Website</label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                        <input type="text" class="form-control" name="website" placeholder="Website address">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Tax ID</label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-balance-scale"></i></span>
                                        <input type="text" class="form-control" name="tax_id" placeholder="Tax ID" maxlength="200">
                                    </div>
                                </div>

                                <hr>

                                <button type="submit" name="add_company_settings" class="btn btn-primary text-bold">
                                    Next (Localization)<i class="fas fa-fw fa-arrow-circle-right ms-2"></i>
                                </button>

                            </form>

                            <?php endif; ?>

                        </div>
                    </div>

                <?php } elseif (isset($_GET['localization'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-globe-americas me-2"></i>Step 5 - Region and Language</h3>
                        </div>
                        <div class="card-body">

                            <?php if ($localization_done): ?>

                                <p>Localization has already been saved - it can be changed later from Admin &gt; Settings.</p>
                                <hr>
                                <a href="?<?= $resume_step ?>" class="btn btn-primary text-bold">Continue Setup <i class="fa fa-fw fa-arrow-circle-right"></i></a>

                            <?php else: ?>
                            <form method="post" autocomplete="off">

                                <div class="mb-3">
                                    <label>Language <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-language"></i></span>
                                        <select class="form-control select2" name="locale" required>
                                            <option value="">- Select a Language -</option>
                                            <?php foreach($locales_array as $locale_code => $locale_name) { ?>
                                                <option value="<?= $locale_code ?>"><?= $locale_name ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Currency <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                                        <select class="form-control select2" name="currency_code" required>
                                            <option value="">- Select a Currency -</option>
                                            <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                                                <option value="<?= $currency_code ?>"><?= "$currency_code - $currency_name" ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label>Timezone <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-business-time"></i></span>
                                        <select class="form-control select2" name="timezone" required>
                                            <option value="">- Select a Timezone -</option>
                                            <?php foreach ($timezones as $tz) { ?>
                                                <option value="<?= $tz ?>"><?= $tz ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <hr>

                                <button type="submit" name="add_localization_settings" class="btn btn-primary text-bold">
                                    Next (Telemetry Settings)<i class="fas fa-fw fa-arrow-circle-right ms-2"></i>
                                </button>

                            </form>

                            <?php endif; ?>

                        </div>
                    </div>


                <?php } elseif (isset($_GET['telemetry'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-broadcast-tower me-2"></i>Step 6 - Telemetry</h3>
                        </div>
                        <div class="card-body">
                            <form method="post" autocomplete="off">
                                <h5>Would you like to share some data with us?</h5>

                                <hr>

                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="share_data" value="1">
                                    <label class="form-check-label ms-2">Share <small class="form-text"><a href="https://docs.itflow.org/telemetry" target="_blank">Click Here for additional details regarding the information we gather <i class="fas fa-external-link-alt"></i></a></small></label>
                                </div>

                                <br>

                                <div class="mb-3">
                                    <label>Comments</label>
                                    <textarea class="form-control" rows="4" name="comments" placeholder="Any Comments?"></textarea>
                                </div>

                                <hr>

                                <h5>Post installation steps: </h5>
                                <p>A few <a href="https://docs.itflow.org/installation#post-installation_essential_housekeeping">housekeeping steps</a> are required to ensure everything runs smoothly, namely:</p>
                                <ul>
                                    <li><a href="https://docs.itflow.org/backups">Setup backups</a></li>
                                    <li>
                                        <a href="https://docs.itflow.org/cron">Setup cron</a> - ITFlow needs one entry, which runs
                                        every job on the schedule set in Maintenance &gt; Cron. Add it to the crontab of the user that
                                        owns the ITFlow files:
                                        <pre class="bg-dark text-white p-2 mt-2"><?= escapeHtml("* * * * * php " . dirname(__DIR__) . "/cron/cron.php >/dev/null") ?></pre>
                                        Then <strong>turn cron on</strong> in Maintenance &gt; Cron - it ships off, and every job
                                        stops itself until it is enabled.
                                        *If installing via the script the crontab entry is set up for you.
                                    </li>
                                    <li>Star ITFlow on <a href="https://github.com/itflow-org/itflow">Github</a> :)</li>
                                </ul>

                                <hr>

                                <button type="submit" name="add_telemetry" class="btn btn-primary text-bold">
                                    Finish and Sign in<i class="fas fa-fw fa-check-circle ms-2"></i>
                                </button>

                            </form>

                        </div>
                    </div>

                <?php } else { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-cube me-2"></i>ITFlow Setup</h3>
                        </div>
                        <div class="card-body">
                            <h2><b>Thank you</b> for choosing to try ITFlow!</h2>
                            <p>This is the start of your journey towards amazing client management </p>
                            <p>A few tips:</p>
                            <ul>
                                <li>Please take a look over the install <a href="https://docs.itflow.org/installation">docs</a>, if you haven't already</li>
                                <li>Don't hesitate to reach out on the <a href="https://forum.itflow.org/t/support" target="_blank">forums</a> if you need any assistance</li>
                                <li><i>Apache/PHP Error log: <?= $errorLog ?></i></li>
                            </ul>
                            <?php if ($install_is_live): ?>
                                <br><p>This install was left part-way through setup - click on the button below to pick up where it stopped.</p>
                            <?php else: ?>
                                <br><p>A database must be created before proceeding - click on the button below to get started.</p>
                            <?php endif; ?>
                            <br><hr>
                            <p class="text-muted">ITFlow is <b>free software</b>: you can redistribute and/or modify it under the terms of the <a href="https://www.gnu.org/licenses/gpl-3.0.en.html" target="_blank">GNU General Public License</a>. <br> It is distributed in the hope that it will be useful, but <b>without any warranty</b>; without even the implied warranty of merchantability or fitness for a particular purpose.</p>
                            <?php
                            // Check that there is access to write to the current directory
                            if (!is_writable('.')) {
                                echo "<div class='alert alert-danger'>Warning: The current directory is not writable. Ensure the webserver process has write access (chmod/chown). Check the <a href='https://docs.itflow.org/installation'>docs</a> for info.</div>";
                            }
                            ?>
                            <hr>
                            <div class="text-center">
                                <?php if ($install_is_live): ?>
                                    <a href="?<?= $resume_step ?>" class="btn btn-primary text-bold me-2">
                                        Continue Setup <i class="fas fa-fw fa-arrow-alt-circle-right ms-2"></i>
                                    </a>
                                <?php elseif ($should_skip_to_user): ?>
                                    <a href="?user" class="btn btn-primary text-bold me-2">
                                        Create First User <i class="fas fa-fw fa-user ms-2"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ($can_show_restore): ?>
                                    <a href="?restore" class="btn btn-warning text-bold">
                                        Restore from Backup <i class="fas fa-fw fa-upload ms-2"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (!$should_skip_to_user && !$can_show_restore): ?>
                                    <a href="?checks" class="btn btn-primary text-bold">
                                        Begin Setup <i class="fas fa-fw fa-arrow-alt-circle-right ms-2"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php } ?>

            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="/libs/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- Custom js-->
<script src='/libs/select2/js/select2.min.js'></script>
<script src="/libs/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>
<!-- AdminLTE App -->
<script src="/libs/adminlte/js/adminlte.min.js"></script>

<!-- Custom js-->
<script src="/js/app.js"></script>

</body>

</html>
