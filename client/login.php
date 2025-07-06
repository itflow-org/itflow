<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

header("Content-Security-Policy: default-src 'self'");

require_once '../config.php';

require_once '../functions.php';

require_once '../includes/get_settings.php';

if (!isset($_SESSION)) {
    // HTTP Only cookies
    ini_set("session.cookie_httponly", true);
    if ($config_https_only) {
        // Tell client to only send cookie(s) over HTTPS
        ini_set("session.cookie_secure", true);
    }
    session_start();
}

// Set Timezone after session_start
require_once "../includes/inc_set_timezone.php";

// Check to see if client portal is enabled
if($config_client_portal_enable == 0) {
    echo "Client Portal is Disabled";
    exit();
}

$session_ip = sanitizeInput(getIP());
$session_user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT']);

$sql_settings = mysqli_query($mysqli, "SELECT config_azure_client_id, config_login_message FROM settings WHERE company_id = 1");
$settings = mysqli_fetch_array($sql_settings);
$azure_client_id = $settings['config_azure_client_id'];
$config_login_message = nullable_htmlentities($settings['config_login_message']);

$company_sql = mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1");
$company_results = mysqli_fetch_array($company_sql);
$company_name = $company_results['company_name'];
$company_logo = $company_results['company_logo'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {

    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        header("HTTP/1.1 401 Unauthorized");

        $_SESSION['login_message'] = 'Invalid e-mail';

    } else {

        $sql = mysqli_query($mysqli, "SELECT * FROM users LEFT JOIN contacts ON user_id = contact_user_id WHERE user_email = '$email' AND user_archived_at IS NULL AND user_type = 2 AND user_status = 1 LIMIT 1");
        $row = mysqli_fetch_array($sql);
        $client_id = intval($row['contact_client_id']);
        $user_id = intval($row['user_id']);
        $session_user_id = $user_id; // to pass the user_id to logAction function
        $contact_id = intval($row['contact_id']);
        $user_email = sanitizeInput($row['user_email']);
        $user_auth_method = sanitizeInput($row['user_auth_method']);

        if ($user_auth_method == 'local') {
            if (password_verify($password, $row['user_password'])) {

                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_id'] = $client_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_type'] = 2;
                $_SESSION['contact_id'] = $contact_id;
                $_SESSION['login_method'] = "local";

                header("Location: index.php");

                // Logging
                logAction("Client Login", "Success", "Client contact $user_email successfully logged in locally", $client_id, $user_id);

            } else {

                // Logging
                logAction("Client Login", "Failed", "Failed client portal login attempt using $email (incorrect password for contact ID $contact_id)", $client_id, $user_id);

                header("HTTP/1.1 401 Unauthorized");
                $_SESSION['login_message'] = 'Incorrect username or password.';

            }

        } else {

            // Logging
            logAction("Client Login", "Failed", "Failed client portal login attempt using $email (invalid email/not allowed local auth)");

            header("HTTP/1.1 401 Unauthorized");

            $_SESSION['login_message'] = 'Incorrect username or password.';

        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title><?php echo $company_name; ?> | Client Portal Login</title>

        <!-- Tell the browser to be responsive to screen width -->
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">

        <!-- Favicon - If Fav Icon exists else use the default one -->
        <?php if(file_exists('../uploads/favicon.ico')) { ?>
            <link rel="icon" type="image/x-icon" href="../uploads/favicon.ico">
        <?php } ?>

        <!-- Font Awesome -->
        <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">

        <!-- Theme style -->
        <link rel="stylesheet" href="../plugins/adminlte/css/adminlte.min.css">

    </head>

    <body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <?php if (!empty($company_logo)) { ?>
                <img alt="<?=$company_name?> logo" height="110" width="380" class="img-fluid" src="<?php echo "../uploads/settings/$company_logo"; ?>">
            <?php } else { ?>
                <b><?=$company_name?></b> <br>Client Portal Login</h2>
            <?php } ?>
        </div>
        <div class="card">
            <div class="card-body login-card-body">
                <?php if(!empty($config_login_message)){ ?>
                <p class="login-box-msg px-0"><?php echo nl2br($config_login_message); ?></p>
                <?php } ?>
                <?php
                if (!empty($_SESSION['login_message'])) { ?>
                    <p class="login-box-msg text-danger">
                    <?php
                    echo $_SESSION['login_message'];
                    unset($_SESSION['login_message']);
                    ?>
                    </p>
                <?php
                }
                ?>
                <form method="post">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="Registered Client Email" name="email" required autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-envelope"></span>
                            </div>
                        </div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" class="form-control" placeholder="Client Password" name="password" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-block mb-3" name="login">Sign in</button>

                    <hr>

                    <?php
                    if (!empty($config_smtp_host)) { ?>
                        <h5 class="text-center"><a href="login_reset.php">Forgot password?</a></h5>
                    <?php } ?>

                </form>

                <?php
                if (!empty($azure_client_id)) { ?>
                    <hr>
                    <div class="col text-center">
                        <a href="login_microsoft.php">
                            <button type="button" class="btn btn-secondary">Login with Microsoft Entra</button>
                        </a>
                    </div>
                <?php } ?>

            </div>
            <!-- /.login-card-body -->

        </div>
        <!-- /.div.card -->

    </div>
    <!-- /.login-box -->

    <?php
    if (!$config_whitelabel_enabled) {
        echo '<small class="text-muted">Powered by ITFlow</small>';
    }
    ?>

    <!-- jQuery -->
    <script src="../plugins/jquery/jquery.min.js"></script>

    <!-- Bootstrap 4 -->
    <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- AdminLTE App -->
    <script src="../plugins/adminlte/js/adminlte.min.js"></script>

    <!-- Prevents resubmit on refresh or back -->
    <script src="../js/login_prevent_resubmit.js"></script>

    </body>
</html>
