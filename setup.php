<?php

if (file_exists("config.php")) {
    include "config.php";

}

include "functions.php";

include "database_version.php";


if (!isset($config_enable_setup)) {
    $config_enable_setup = 1;
}

if ($config_enable_setup == 0) {
    header("Location: login.php");
    exit;
}

include_once "settings_localization_array.php";
$errorLog = ini_get('error_log') ?: "Debian/Ubuntu default is usually /var/log/apache2/error.log";

// Get a list of all available timezones
$timezones = DateTimeZone::listIdentifiers();

if (isset($_POST['add_database'])) {

    // Check if database has been set up already. If it has, direct user to edit directly instead.
    if (file_exists('config.php')) {
        $_SESSION['alert_message'] = "Database already configured. Any further changes should be made by editing the config.php file.";
        header("Location: setup.php?user");
        exit;
    }

    $host = filter_var(trim($_POST['host']), FILTER_SANITIZE_STRING);
    $database = filter_var(trim($_POST['database']), FILTER_SANITIZE_STRING);
    $username = filter_var(trim($_POST['username']), FILTER_SANITIZE_STRING);
    $password = filter_var(trim($_POST['password']), FILTER_SANITIZE_STRING);
    $config_base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    $config_base_url = rtrim($config_base_url, '/');

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

    if (file_put_contents("config.php", $new_config) !== false && file_exists('config.php')) {

        include "config.php";


        // Name of the file
        $filename = 'db.sql';
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
        header("Location: setup.php?user");
        exit;

    } else {
        // There was an error writing the file
        // Display an error message and redirect to the setup page
        $_SESSION['alert_message'] = "Did not successfully write the config.php file to the filesystem, Please Input the database information again.";
        header("Location: setup.php?database");
        exit;
    }

}

if (isset($_POST['add_user'])) {
    $user_count = mysqli_num_rows(mysqli_query($mysqli,"SELECT COUNT(*) FROM users"));
    if ($user_count < 0) {
        $_SESSION['alert_message'] = "Users already exist in the database. Clear them to reconfigure here.";
        header("Location: setup.php?company");
        exit;
    }

    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);

    //Generate master encryption key
    $site_encryption_master_key = randomString();

    //Generate user specific key
    $user_specific_encryption_ciphertext = setupFirstUserSpecificKey(trim($_POST['password']), $site_encryption_master_key);

    mysqli_query($mysqli,"INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext'");

    mkdirMissing("uploads/users/1");

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
        $allowed_file_extensions = array('jpg', 'gif', 'png');

        if (in_array($file_extension,$allowed_file_extensions) === false) {
            $file_error = 1;
        }

        //Check File Size
        if ($file_size > 2097152) {
            $file_error = 1;
        }

        if ($file_error == 0) {
            // directory in which the uploaded file will be moved
            $upload_file_dir = "uploads/users/1/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            //Set Avatar
            mysqli_query($mysqli,"UPDATE users SET user_avatar = '$new_file_name' WHERE user_id = 1");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        } else {

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }

    //Create Settings
    mysqli_query($mysqli,"INSERT INTO user_settings SET user_id = 1, user_role = 3");

    $_SESSION['alert_message'] = "User <strong>$name</strong> created";

    header("Location: setup.php?company");
    exit;

}

if (isset($_POST['add_company_settings'])) {

    $name = sanitizeInput($_POST['name']);
    $country = sanitizeInput($_POST['country']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $zip = sanitizeInput($_POST['zip']);
    $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    $website = sanitizeInput($_POST['website']);
    $locale = sanitizeInput($_POST['locale']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $timezone = sanitizeInput($_POST['timezone']);

    mysqli_query($mysqli,"INSERT INTO companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website', company_locale = '$locale', company_currency = '$currency_code'");

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
            $upload_file_dir = "uploads/settings/";
            $dest_path = $upload_file_dir . $new_file_name;

            move_uploaded_file($file_tmp_path, $dest_path);

            mysqli_query($mysqli,"UPDATE companies SET company_logo = '$new_file_name' WHERE company_id = 1");

            $_SESSION['alert_message'] = 'File successfully uploaded.';
        } else {

            $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
        }
    }



    $latest_database_version = LATEST_DATABASE_VERSION;
    mysqli_query($mysqli,"INSERT INTO settings SET company_id = 1, config_current_database_version = '$latest_database_version', config_invoice_prefix = 'INV-', config_invoice_next_number = 1, config_recurring_prefix = 'REC-', config_recurring_next_number = 1, config_invoice_overdue_reminders = '1,3,7', config_quote_prefix = 'QUO-', config_quote_next_number = 1, config_default_net_terms = 30, config_ticket_next_number = 1, config_ticket_prefix = 'TCK-'");

    # Used only for the install script to grab the generated cronkey and insert into the db
    if (file_exists("uploads/tmp/cronkey.php")) {
        include "uploads/tmp/cronkey.php";


        mysqli_query($mysqli,"UPDATE settings SET config_cron_key = '$itflow_install_script_generated_cronkey'");

        unlink('uploads/tmp/cronkey.php');
    }

    // Create Categories
    // Expense Categories Examples
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Office Supplies', category_type = 'Expense', category_color = 'blue'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Travel', category_type = 'Expense', category_color = 'red'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Advertising', category_type = 'Expense', category_color = 'green'");

    // Income Categories Examples
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Service', category_type = 'Income', category_color = 'blue'");

    // Referral Examples
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Friend', category_type = 'Referral', category_color = 'blue'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Search Engine', category_type = 'Referral', category_color = 'red'");

    // Payment Methods
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Cash', category_type = 'Payment Method', category_color = 'blue'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Check', category_type = 'Payment Method', category_color = 'red'");
    mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Bank Transfer', category_type = 'Payment Method', category_color = 'green'");

    // Default Calendar
    mysqli_query($mysqli,"INSERT INTO calendars SET calendar_name = 'Default', calendar_color = 'blue'");

    // Add default ticket statuses
    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'New', ticket_status_color = '#dc3545'"); // Default ID for new tickets is 1
    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'Open', ticket_status_color = '#007bff'"); // 2
    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'On Hold', ticket_status_color = '#28a745'"); // 3
    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'Resolved', ticket_status_color = '#343a40'"); // 4 (was auto-close)
    mysqli_query($mysqli, "INSERT INTO ticket_statuses SET ticket_status_name = 'Closed', ticket_status_color = '#343a40'"); // 5

    // Add default modules
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_client', module_description = 'General client & contact management'");
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_support', module_description = 'Access to ticketing, assets and documentation'");
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_credential', module_description = 'Access to client credentials - usernames, passwords and 2FA codes'");
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_sales', module_description = 'Access to quotes, invoices and products'");
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_financial', module_description = 'Access to payments, accounts, expenses and budgets'");
    mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_reporting', module_description = 'Access to all reports'");

    // Add default roles
    mysqli_query($mysqli, "INSERT INTO user_roles SET user_role_id = 1, user_role_name = 'Accountant', user_role_description = 'Built-in - Limited access to financial-focused modules'");
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 1, user_role_permission_level = 1"); // Read clients
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 2, user_role_permission_level = 1"); // Read support
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 4, user_role_permission_level = 1"); // Read sales
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 5, user_role_permission_level = 2"); // Modify financial
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 1, module_id = 6, user_role_permission_level = 1"); // Read reports

    mysqli_query($mysqli, "INSERT INTO user_roles SET user_role_id = 2, user_role_name = 'Technician', user_role_description = 'Built-in - Limited access to technical-focused modules'");
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 1, user_role_permission_level = 2"); // Modify clients
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 2, user_role_permission_level = 2"); // Modify support
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 3, user_role_permission_level = 2"); // Modify credentials
    mysqli_query($mysqli, "INSERT INTO user_role_permissions SET user_role_id = 2, module_id = 4, user_role_permission_level = 2"); // Modify sales

    mysqli_query($mysqli, "INSERT INTO user_roles SET user_role_id = 3, user_role_name = 'Administrator', user_role_description = 'Built-in - Full administrative access to all modules (including user management)', user_role_is_admin = 1");

    // Custom Links
    mysqli_query($mysqli,"INSERT INTO custom_links SET custom_link_name = 'Docs', custom_link_uri = 'https://docs.itflow.org', custom_link_new_tab = 1, custom_link_icon = 'question-circle'");


    $_SESSION['alert_message'] = "Company <strong>$name</strong> created";

    header("Location: setup.php?localization");

}

if (isset($_POST['add_localization_settings'])) {

    $locale = sanitizeInput($_POST['locale']);
    $currency_code = sanitizeInput($_POST['currency_code']);
    $timezone = sanitizeInput($_POST['timezone']);
    $phone_mask = intval($_POST['phone_mask']);

    mysqli_query($mysqli,"UPDATE companies SET company_locale = '$locale', company_currency = '$currency_code' WHERE company_id = 1");

    mysqli_query($mysqli,"UPDATE settings SET config_timezone = '$timezone', config_phone_mask = $phone_mask WHERE company_id = 1");

    // Create Default Cash Account
    mysqli_query($mysqli,"INSERT INTO accounts SET account_name = 'Cash', account_currency_code = '$currency_code'");


    $_SESSION['alert_message'] = "Localization Info saved";

    header("Location: setup.php?telemetry");

}

if (isset($_POST['add_telemetry'])) {

    if (isset($_POST['share_data']) && $_POST['share_data'] == 1) {

        mysqli_query($mysqli,"UPDATE settings SET config_telemetry = 2");

        $comments = sanitizeInput($_POST['comments']);

        $sql = mysqli_query($mysqli,"SELECT * FROM companies WHERE company_id = 1");
        $row = mysqli_fetch_array($sql);

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
    $myfile = fopen("config.php", "a");

    $txt = "\$config_enable_setup = 0;\n\n";

    fwrite($myfile, $txt);

    fclose($myfile);

    header("Location: login.php");
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
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- Custom Style Sheet -->
    <link href="plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css">
    <link href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css" rel="stylesheet" type="text/css">

</head>

<body class="hold-transition sidebar-mini">

<div class="wrapper text-sm">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-primary navbar-dark">

        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav">
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">

        <!-- Brand Logo -->
        <a href="https://itflow.org" class="brand-link">
            <h3 class="brand-text font-weight-light">ITFlow</h3>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="?checks" class="nav-link <?php if (isset($_GET['checks'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-check"></i>
                            <p>Checks</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?database" class="nav-link <?php if (isset($_GET['database'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-database"></i>
                            <p>Database</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?user" class="nav-link <?php if (isset($_GET['user'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-user"></i>
                            <p>User</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?company" class="nav-link <?php if (isset($_GET['company'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-briefcase"></i>
                            <p>Company</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?localization" class="nav-link <?php if (isset($_GET['localization'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-globe-americas"></i>
                            <p>Localization</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?telemetry" class="nav-link <?php if (isset($_GET['telemetry'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-share-alt"></i>
                            <p>Telemetry</p>
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
                        <?php echo nullable_htmlentities($_SESSION['alert_message']); ?>
                        <button class='close' data-dismiss='alert'>&times;</button>
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
                        'php-mailparse' => 'mailparse',
                        'php-imap' => 'imap',
                        'php-mysqli' => 'mysqli',
                        'php-intl' => 'intl',
                        'php-curl' => 'curl',
                        'php-mbstring' => 'mbstring',
                        'php-gd' => 'gd',
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
                    function return_bytes($val) {
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

                    $upload_passed = return_bytes($upload_max_filesize) >= $required_bytes;
                    $post_passed = return_bytes($post_max_size) >= $required_bytes;

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
                        $commands = ['whois', 'dig', 'git'];

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

                    <div class="card card-dark mb-3">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-check mr-2"></i>Setup Checks</h3>
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

                            <a href="?database" class="btn btn-primary text-bold">Next (Database)<i class="fa fa-fw fa-arrow-circle-right ml-2"></i></a>
                        </div>
                    </div>

                <?php } elseif (isset($_GET['database'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-database mr-2"></i>Connect your Database</h3>
                        </div>
                        <div class="card-body">
                            <?php if (file_exists('config.php')) { ?>
                                Database is already configured. Any further changes should be made by editing the config.php file,
                                or deleting it and refreshing this page.
                            <?php } else { ?>
                                <form method="post" autocomplete="off">

                                    <h5>Database Connection Details</h5>

                                    <div class="form-group">
                                        <label>Database Name <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-database"></i></span>
                                            </div>
                                            <input type="text" class="form-control" name="database" placeholder="Database name" autofocus required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Database Host <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                                            </div>
                                            <input type="text" class="form-control" name="host" value="localhost" placeholder="Database Host" required>
                                        </div>
                                    </div>

                                    <br>
                                    <h5>Database Authentication Details</h5>

                                    <div class="form-group">
                                        <label>Database User <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                            </div>
                                            <input type="text" class="form-control" name="username" placeholder="Database user account" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Database Password <strong class="text-danger">*</strong></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                            </div>
                                            <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Database user password" autocomplete="new-password" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>
                                    <button type="submit" name="add_database" class="btn btn-primary text-bold">
                                        Next (First User)<i class="fas fa-fw fa-arrow-circle-right ml-2"></i>
                                    </button>
                                </form>
                            <?php } ?>
                        </div>
                    </div>

                <?php } elseif (isset($_GET['user'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-user mr-2"></i>Create your first user</h3>
                        </div>
                        <div class="card-body">

                            <form method="post" enctype="multipart/form-data" autocomplete="off">
                                <div class="form-group">
                                    <label>Name <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="name" placeholder="Full Name" autofocus required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Email <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                        </div>
                                        <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Password <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                        </div>
                                        <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Enter a Password" autocomplete="new-password" required minlength="8">
                                        <div class="input-group-append">
                                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Avatar</label>
                                    <input type="file" class="form-control-file" accept="image/*;capture=camera" name="file">
                                </div>

                                <hr>

                                <button type="submit" name="add_user" class="btn btn-primary text-bold">Next (Company details) <i class="fa fa-fw fa-arrow-circle-right"></i></button>
                            </form>
                        </div>
                    </div>

                <?php } elseif (isset($_GET['company'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-briefcase mr-2"></i>Company Details</h3>
                        </div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data" autocomplete="off">

                                <div class="form-group">
                                    <label>Company Name <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="name" placeholder="Company Name" autofocus required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Logo</label>
                                    <input type="file" class="form-control-file" name="file" accept=".jpg, .jpeg, .png">
                                </div>

                                <div class="form-group">
                                    <label>Address</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="address" placeholder="Street Address">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>City</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="city" placeholder="City">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>State / Province</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="state" placeholder="State or Province">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Postal Code</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Country <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-globe-americas"></i></span>
                                        </div>
                                        <select class="form-control select2" name="country" required>
                                            <option value="">- Country -</option>
                                            <?php foreach($countries_array as $country_name) { ?>
                                                <option><?php echo $country_name; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Phone</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="phone" placeholder="Phone Number">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Email</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                        </div>
                                        <input type="email" class="form-control" name="email" placeholder="Email address">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Website</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="website" placeholder="Website address">
                                    </div>
                                </div>

                                <hr>

                                <button type="submit" name="add_company_settings" class="btn btn-primary text-bold">
                                    Next (Localization)<i class="fas fa-fw fa-arrow-circle-right ml-2"></i>
                                </button>

                            </form>
                        </div>
                    </div>

                <?php } elseif (isset($_GET['localization'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-globe-americas mr-2"></i>Region and Language</h3>
                        </div>
                        <div class="card-body">
                            <form method="post" autocomplete="off">

                                <div class="form-group">
                                    <label>Language <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-language"></i></span>
                                        </div>
                                        <select class="form-control select2" name="locale" required>
                                            <option value="">- Select a Language -</option>
                                            <?php foreach($locales_array as $locale_code => $locale_name) { ?>
                                                <option value="<?php echo $locale_code; ?>"><?php echo $locale_name; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Currency <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                                        </div>
                                        <select class="form-control select2" name="currency_code" required>
                                            <option value="">- Select a Currency -</option>
                                            <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                                                <option value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Timezone <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-business-time"></i></span>
                                        </div>
                                        <select class="form-control select2" name="timezone" required>
                                            <option value="">- Select a Timezone -</option>
                                            <?php foreach ($timezones as $tz) { ?>
                                                <option value="<?php echo $tz; ?>"><?php echo $tz; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Phone Mask</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-phone"></i></span>
                                        </div>
                                        <select class="form-control select2" name="phone_mask">
                                            <option value="1">US Format - e.g. (412) 888-9999</option>
                                            <option value="0">Non-US Format - e.g. 4128889999</option>
                                        </select>
                                    </div>
                                </div>

                                <hr>

                                <button type="submit" name="add_localization_settings" class="btn btn-primary text-bold">
                                    Next (Telemetry Settings)<i class="fas fa-fw fa-arrow-circle-right ml-2"></i>
                                </button>

                            </form>
                        </div>
                    </div>


                <?php } elseif (isset($_GET['telemetry'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-broadcast-tower mr-2"></i>Telemetry</h3>
                        </div>
                        <div class="card-body">
                            <form method="post" autocomplete="off">
                                <h5>Would you like to share some data with us?</h5>

                                <hr>

                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="share_data" value="1">
                                    <label class="form-check-label ml-2">Share <small class="form-text"><a href="https://docs.itflow.org/telemetry" target="_blank">Click Here for additional details regarding the information we gather <i class="fas fa-external-link-alt"></i></a></small></label>
                                </div>

                                <br>

                                <div class="form-group">
                                    <label>Comments</label>
                                    <textarea class="form-control" rows="4" name="comments" placeholder="Any Comments?"></textarea>
                                </div>

                                <hr>

                                <h5>Post installation steps: </h5>
                                <p>A few <a href="https://docs.itflow.org/installation#post-installation_essential_housekeeping">housekeeping steps</a> are required to ensure everything runs smoothly, namely:</p>
                                <ul>
                                    <li><a href="https://docs.itflow.org/backups">Setup backups</a></li>
                                    <li><a href="https://docs.itflow.org/cron">Setup cron</a> *If Installing via script cron jobs will be automatically setup for you.</li>
                                    <li>Star ITFlow on <a href="https://github.com/itflow-org/itflow">Github</a> :)</li>
                                </ul>

                                <hr>

                                <button type="submit" name="add_telemetry" class="btn btn-primary text-bold">
                                    Finish and Sign in<i class="fas fa-fw fa-check-circle ml-2"></i>
                                </button>

                            </form>

                        </div>
                    </div>

                <?php } else { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-cube mr-2"></i>ITFlow Setup</h3>
                        </div>
                        <div class="card-body">
                            <h2><b>Thank you</b> for choosing to try ITFlow!</h2>
                            <p>This is the start of your journey towards amazing client management </p>
                            <p>A few tips:</p>
                            <ul>
                                <li>Please take a look over the install <a href="https://docs.itflow.org/installation">docs</a>, if you haven't already</li>
                                <li>Don't hesitate to reach out on the <a href="https://forum.itflow.org/t/support" target="_blank">forums</a> if you need any assistance</li>
                                <li><i>Apache/PHP Error log: <?php echo $errorLog ?></i></li>
                            </ul>
                            <br><p>A database must be created before proceeding - click on the button below to get started.</p>
                            <br><hr>
                            <p class="text-muted">ITFlow is <b>free software</b>: you can redistribute and/or modify it under the terms of the <a href="https://www.gnu.org/licenses/gpl-3.0.en.html" target="_blank">GNU General Public License</a>. <br> It is distributed in the hope that it will be useful, but <b>without any warranty</b>; without even the implied warranty of merchantability or fitness for a particular purpose.</p>
                            <?php
                            // Check that there is access to write to the current directory
                            if (!is_writable('.')) {
                                echo "<div class='alert alert-danger'>Warning: The current directory is not writable. Ensure the webserver process has write access (chmod/chown). Check the <a href='https://docs.itflow.org/installation#ubuntu_setup_guide'>docs</a> for info.</div>";
                            }
                            ?>
                            <hr>
                            <div style="text-align: center;">
                                <a href="?checks" class="btn btn-primary text-bold">
                                    Begin Setup<i class="fas fa-fw fa-arrow-alt-circle-right ml-2"></i>
                                </a>
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
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- Custom js-->
<script src='plugins/select2/js/select2.min.js'></script>
<script src="plugins/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>
<!-- AdminLTE App -->
<script src="dist/js/adminlte.min.js"></script>

<!-- Custom js-->
<script src="js/app.js"></script>

</body>

</html>
