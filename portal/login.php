<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

header("X-Frame-Options: DENY");

$session_company_id = 1;
require_once('../config.php');
require_once('../functions.php');
require_once ('../get_settings.php');

if (!isset($_SESSION)) {
    // HTTP Only cookies
    ini_set("session.cookie_httponly", true);
    if ($config_https_only) {
        // Tell client to only send cookie(s) over HTTPS
        ini_set("session.cookie_secure", true);
    }
    session_start();
}

$ip = sanitizeInput(getIP());
$user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT']);

$sql_settings = mysqli_query($mysqli, "SELECT config_azure_client_id FROM settings WHERE company_id = 1");
$settings = mysqli_fetch_array($sql_settings);
$azure_client_id = $settings['config_azure_client_id'];

$company_sql = mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1");
$company_results = mysqli_fetch_array($company_sql);
$company_name = $company_results['company_name'];
$company_logo = $company_results['company_logo'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {

    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['login_message'] = 'Invalid e-mail';
    } else {
        $sql = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_email = '$email' LIMIT 1");
        $row = mysqli_fetch_array($sql);
        if ($row['contact_auth_method'] == 'local') {
            if (password_verify($password, $row['contact_password_hash'])) {

                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_id'] = intval($row['contact_client_id']);
                $_SESSION['contact_id'] = intval($row['contact_id']);
                $_SESSION['company_id'] = intval($row['company_id']);
                $_SESSION['login_method'] = "local";

                header("Location: index.php");

                mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client Login', log_action = 'Success', log_description = 'Client contact $row[contact_email] successfully logged in locally', log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = $row[contact_client_id]");

            } else {
                mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client Login', log_action = 'Failed', log_description = 'Failed client portal login attempt using $email', log_ip = '$ip', log_user_agent = '$user_agent'");
                $_SESSION['login_message'] = 'Incorrect username or password.';
            }

        } else {
            mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client Login', log_action = 'Failed', log_description = 'Failed client portal login attempt using $email', log_ip = '$ip', log_user_agent = '$user_agent'");
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

    <!-- Font Awesome -->
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">

    <!-- Theme style -->
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">

    <!-- Google Font: Source Sans Pro -->
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
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
            <p class="login-box-msg text-danger">
                <?php
                if (!empty($_SESSION['login_message'])) {
                    echo $_SESSION['login_message'];
                    unset($_SESSION['login_message']);
                }
                ?>
            </p>
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

                <?php
                if (!empty($config_smtp_host)) { ?>
                    <a href="login_reset.php">Forgot password?</a>
                <?php } ?>

            </form>

            <?php
            if (!empty($azure_client_id)) { ?>
                <hr>
                <div class="col text-center">
                    <button type="button" class="btn btn-secondary" onclick="location.href = 'login_microsoft.php';">Login with Microsoft Azure AD</button>
                </div>
            <?php } ?>

        </div>
        <!-- /.login-card-body -->

    </div>
    <!-- /.div.card -->

</div>
<!-- /.login-box -->

<!-- jQuery -->
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="../dist/js/adminlte.min.js"></script>

<script src="../plugins/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>

<!-- Prevents resubmit on refresh or back -->
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null,null,window.location.href);
    }
</script>

</body>
</html>
