<?php

if (file_exists("includes/config.php")) {
    include "includes/config.php";

}

include "includes/functions.php";
include "includes/database_version.php";


if (!isset($config_enable_setup)) {
    $config_enable_setup = 1;
}

if ($config_enable_setup == 0) {
    header("Location: login.php");
    exit;
}

$errorLog = ini_get('error_log') ?: "Debian/Ubuntu default is usually /var/log/apache2/error.log";

if (isset($_POST['create_database'])) {

    // Check if database has been set up already. If it has, direct user to edit directly instead.
    if (file_exists('includes/config.php')) {
        $_SESSION['alert_message'] = "Database already configured. Any further changes should be made by editing the includes/config.php file.";
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
    $new_config .= "\$config_app_name = 'TaskFlow';\n";
    $new_config .= sprintf("\$config_base_url = '%s';\n", addslashes($config_base_url));
    $new_config .= "\$repo_branch = 'main';\n";
    $new_config .= "\$installation_id = '$installation_id';\n";

    if (file_put_contents("includes/config.php", $new_config) !== false && file_exists('includes/config.php')) {

        include "includes/config.php";


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
        $_SESSION['alert_message'] = "Did not successfully write the includes/config.php file to the filesystem, Please Input the database information again.";
        header("Location: setup.php?database");
        exit;
    }

}

if (isset($_POST['create_user'])) {
    $user_count = mysqli_num_rows(mysqli_query($mysqli,"SELECT COUNT(*) FROM users"));
    if ($user_count < 0) {
        $_SESSION['alert_message'] = "Users already exist in the database. Clear them to reconfigure here.";
        header("Location: setup.php?organization");
        exit;
    }

    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);

    mysqli_query($mysqli,"INSERT INTO users SET user_name = '$name', user_role = 'admin', user_email = '$email', user_password = '$password'");

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

    $_SESSION['alert_message'] = "User <strong>$name</strong> created";

    header("Location: setup.php?organization");
    exit;

}

if (isset($_POST['create_organization'])) {

    $name = sanitizeInput($_POST['name']);

    mysqli_query($mysqli,"INSERT INTO global_settings SET global_setting_organization_name = '$name'");

    //final setup stages
    $myfile = fopen("includes/config.php", "a");

    $txt = "\$config_enable_setup = 0;\n\n";

    fwrite($myfile, $txt);

    fclose($myfile);

    $_SESSION['alert_message'] = "Organization <strong>$name</strong> created";

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
    <link rel="stylesheet" href="plugins/adminlte/css/adminlte.min.css">
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
            <h3 class="brand-text font-weight-light"><i class="fas fa-paper-plane text-primary mr-2"></i><span class="text-primary text-bold">IT</span>Flow</h3>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="?checks" class="nav-link <?php if (isset($_GET['checks'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-check"></i>
                            <p>1 - Checks</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?database" class="nav-link <?php if (isset($_GET['database'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-database"></i>
                            <p>2 - Database</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?user" class="nav-link <?php if (isset($_GET['user'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-user"></i>
                            <p>3 - User</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?company" class="nav-link <?php if (isset($_GET['organization'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-briefcase"></i>
                            <p>4 - Organization</p>
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
                        'php-mysqli' => 'mysqli',
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
                        foreach (['git'] as $command) {
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
                            <h3 class="card-title"><i class="fas fa-fw fa-check mr-2"></i>Step 1 - Setup Checks</h3>
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
                            <h3 class="card-title"><i class="fas fa-fw fa-database mr-2"></i>Step 2 - Connect your Database</h3>
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
                                    <button type="submit" name="create_database" class="btn btn-primary text-bold">
                                        Next (First User)<i class="fas fa-fw fa-arrow-circle-right ml-2"></i>
                                    </button>
                                </form>
                            <?php } ?>
                        </div>
                    </div>

                <?php } elseif (isset($_GET['user'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-user mr-2"></i>Step 3 - Create your first user</h3>
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

                                <button type="submit" name="create_user" class="btn btn-primary text-bold">Next (Organization details) <i class="fa fa-fw fa-arrow-circle-right"></i></button>
                            </form>
                        </div>
                    </div>

                <?php } elseif (isset($_GET['organization'])) { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-briefcase mr-2"></i>Step 4 - Organization Details</h3>
                        </div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data" autocomplete="off">

                                <div class="form-group">
                                    <label>Organization Name <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="name" placeholder="Organization Name" autofocus required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Logo</label>
                                    <input type="file" class="form-control-file" name="file" accept=".jpg, .jpeg, .png">
                                </div>

                                <hr>

                                <button type="submit" name="create_organization" class="btn btn-primary text-bold">
                                    Finish and Sign in<i class="fas fa-fw fa-check-circle ml-2"></i>
                                </button>

                            </form>
                        </div>
                    </div>

                <?php } else { ?>

                    <div class="card card-dark">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fw fa-cube mr-2"></i>TaskFlow Setup</h3>
                        </div>
                        <div class="card-body">
                            <h2><b>Thank you</b> for choosing to try TaskFlow!</h2>
                            <p>This is the start of your journey towards amazing task management </p>
                            <p>A database must be created before proceeding - click on the button below to get started.</p>
                            <hr>
                            <p class="text-muted">TaskFlow is <b>free software</b>: you can redistribute and/or modify it under the terms of the <a href="https://www.gnu.org/licenses/gpl-3.0.en.html" target="_blank">GNU General Public License</a>. <br> It is distributed in the hope that it will be useful, but <b>without any warranty</b>; without even the implied warranty of merchantability or fitness for a particular purpose.</p>
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
<script src="plugins/adminlte/js/adminlte.min.js"></script>

<!-- Custom js-->
<script src="js/app.js"></script>

</body>

</html>
