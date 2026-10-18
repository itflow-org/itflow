<?php
/*
 * Client Portal
 * Setting a password from a link - Forgot password, and the invite a primary
 * contact or an agent sends - and asking for a Forgot password link.
 *
 * A link is ?email=&token=&client=. Only the token's SHA-256 is kept, and it
 * runs out: an hour for Forgot password, a week for an invite
 * (functions/portal.php). Using it spends it. Asking for a link always says
 * the same thing, whether or not the address has an account, and sends at
 * most one per account every PORTAL_RESET_REQUEST_GAP_MINUTES.
 */

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:");
header("X-Frame-Options: DENY");

require_once '../config.php';
require_once '../functions.php';
require_once '../includes/load_global_settings.php';

if (intval($config_client_portal_enable) !== 1) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . "/../includes/session_init.php";
require_once "../includes/inc_set_timezone.php";

$ip = escapeSql(getIP());
$user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');
$can_email = portalMailReady();

$company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1"));
$company_name = (string) ($company['company_name'] ?? '');

$message = '';
$message_type = 'danger';
$link_user = null;
$link = ['email' => '', 'token' => '', 'client' => 0];

/** A row in the log for a password link - $user_id 0 when the address has no account. */
$reset_log = function ($action, $description, $user_id, $client_id) use ($mysqli, $ip, $user_agent) {
    mysqli_query($mysqli, "INSERT INTO logs SET log_type = 'Client Login', log_action = '" . escapeSql($action) . "', log_description = '" . escapeSql($description) . "',
        log_ip = '$ip', log_user_agent = '$user_agent', log_client_id = " . intval($client_id) . ", log_user_id = " . intval($user_id));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password_reset_email_request']) && $can_email) {

    $email = trim((string) ($_POST['email'] ?? ''));

    // From one address, ten asks in ten minutes is plenty - after that, nothing is sent
    $asked = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE log_type = 'Client Login' AND log_action = 'Reset Requested'
        AND log_ip = '$ip' AND log_created_at > NOW() - INTERVAL 10 MINUTE"))[0]);

    $row = null;
    if ($asked < 10 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_sql = escapeSql($email);
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_id, user_email, contact_name, contact_client_id FROM users
            JOIN contacts ON contact_user_id = user_id
            JOIN clients ON client_id = contact_client_id
            WHERE user_email = '$email_sql' AND user_type = 2 AND user_auth_method = 'local' AND user_status = 1 AND user_archived_at IS NULL
              AND contact_archived_at IS NULL AND client_archived_at IS NULL
            LIMIT 1"));
    }
    $reset_log('Reset Requested', 'Asked for a portal password link for ' . mb_substr($email, 0, 200), intval($row['user_id'] ?? 0), intval($row['contact_client_id'] ?? 0));

    if ($row) {
        $user_id = intval($row['user_id']);
        $recent = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE log_type = 'Client Login' AND log_action = 'Reset Sent'
            AND log_user_id = $user_id AND log_created_at > NOW() - INTERVAL " . PORTAL_RESET_REQUEST_GAP_MINUTES . " MINUTE"))[0]);
        if (!$recent) {
            portalMailPasswordReset($user_id);
            $reset_log('Reset Sent', 'Emailed a portal password link to ' . $row['user_email'], $user_id, intval($row['contact_client_id']));
        }
    }

    $message = "If there is a client portal account for that address, a link to reset its password is on its way. It works for " . PORTAL_RESET_LINK_MINUTES . " minutes.";
    $message_type = 'success';

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password_reset_set_password'])) {

    $link = ['email' => (string) ($_POST['email'] ?? ''), 'token' => (string) ($_POST['token'] ?? ''), 'client' => intval($_POST['client'] ?? 0)];
    $link_user = portalPasswordLinkUser($link['email'], $link['token'], $link['client']);
    $password = (string) ($_POST['new_password'] ?? '');

    if (!$link_user) {
        $message = 'That link has run out or has been used already. Ask for a new one below.';
    } elseif (($problem = userPasswordProblem($password)) !== '') {
        $message = $problem;
    } elseif ($password !== (string) ($_POST['confirm_password'] ?? '')) {
        $message = 'The two passwords are not the same - type it again in both.';
    } else {
        $user_id = intval($link_user['user_id']);
        $hash = escapeSql(password_hash($password, PASSWORD_DEFAULT));
        mysqli_query($mysqli, "UPDATE users SET user_password = '$hash' WHERE user_id = $user_id AND user_type = 2");
        portalPasswordLinkClear($user_id);
        $reset_log('Password Reset', 'Set a new portal password from an emailed link', $user_id, $link['client']);
        portalMailAccountChange($link_user['user_email'], $link_user['contact_name'], 'password_link');
        $_SESSION['login_message'] = 'Your password is set - sign in with it below.';
        $_SESSION['login_message_type'] = 'success';
        header("Location: /login.php");
        exit();
    }

} elseif (isset($_GET['token'])) {

    $link = ['email' => (string) ($_GET['email'] ?? ''), 'token' => (string) $_GET['token'], 'client' => intval($_GET['client'] ?? 0)];
    $link_user = portalPasswordLinkUser($link['email'], $link['token'], $link['client']);
    if (!$link_user) {
        $message = 'That link has run out or has been used already.' . ($can_email ? ' Ask for a new one below.' : ' Ask us for a new one.');
    }
}

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-lte-color-mode="off">
<head>
    <meta charset="utf-8">
    <title><?= escapeHtml($company_name) ?> | <?= $link_user ? 'Set your password' : 'Reset your password' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
    <?php if (file_exists(__DIR__ . '/../uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">
</head>
<body class="hold-transition login-page">

<div class="login-box">
    <div class="login-logo">
        <?php if (!empty($company['company_logo'])) { ?>
            <img alt="<?= escapeHtml($company_name) ?> logo" height="110" width="380" class="img-fluid" src="/uploads/settings/<?= escapeHtml($company['company_logo']) ?>">
        <?php } else { ?>
            <span class="fw-semibold"><?= escapeHtml($company_name) ?></span>
        <?php } ?>
    </div>

    <div class="card">
        <div class="card-body login-card-body">

            <?php if ($link_user) { ?>

                <h1 class="h5 mb-1">Set your password</h1>
                <p class="text-body-secondary small mb-3">For <?= escapeHtml($link_user['user_email']) ?> - at least <?= USER_PASSWORD_MIN ?> characters.</p>

                <?php if ($message !== '') { ?>
                    <div class="alert alert-<?= $message_type ?> small"><?= escapeHtml($message) ?></div>
                <?php } ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="email" value="<?= escapeHtml($link['email']) ?>">
                    <input type="hidden" name="token" value="<?= escapeHtml($link['token']) ?>">
                    <input type="hidden" name="client" value="<?= intval($link['client']) ?>">
                    <input type="text" class="d-none" name="username" value="<?= escapeHtml($link_user['user_email']) ?>" autocomplete="username" aria-hidden="true" tabindex="-1">

                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                        <input type="password" class="form-control" placeholder="New password" name="new_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" aria-label="New password" required autofocus>
                    </div>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-check-double"></i></span>
                        <input type="password" class="form-control" placeholder="The same again" name="confirm_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" aria-label="New password again" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3" name="password_reset_set_password">Set password</button>
                </form>

            <?php } else { ?>

                <h1 class="h5 mb-1">Reset your password</h1>
                <p class="text-body-secondary small mb-3">For the client portal. Enter the email address you sign in with and we will email you a link.</p>

                <?php if ($message !== '') { ?>
                    <div class="alert alert-<?= $message_type ?> small"><?= escapeHtml($message) ?></div>
                <?php } ?>

                <?php if ($can_email) { ?>
                    <form method="post" autocomplete="off">
                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                            <input type="email" class="form-control" placeholder="Email address" name="email" maxlength="200" autocomplete="email" aria-label="Email address" required<?= $message_type === 'success' ? '' : ' autofocus' ?>>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3" name="password_reset_email_request">Email me a link</button>
                    </form>
                <?php } else { ?>
                    <div class="alert alert-secondary small">Email is not set up here, so a link can't be sent - ask us to reset it for you.</div>
                <?php } ?>

            <?php } ?>

            <a href="/login.php"><i class="fas fa-arrow-left me-1"></i>Back to sign in</a>

        </div>
    </div>
</div>

<script src="/js/login_prevent_resubmit.js"></script>

</body>
</html>
