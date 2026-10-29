<?php
/*
 * Joining as an agent - from an invite an admin sent (New User, or Get
 * started > Your team): ?invite=&token=. The person checks their name and
 * chooses a password - nobody else ever knows it - and is made an agent with
 * the role, MFA and client access the invite set (userInviteAccept(),
 * functions/user_invite.php). Then they sign in on the usual page. A link
 * that has run out, been used or cancelled says so, and what to do instead.
 *
 * The client portal's own invites open client/join.php.
 */

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

if (!file_exists('config.php')) {
    header("Location: /setup");
    exit();
}

require_once 'config.php';
require_once 'functions.php';
require_once 'includes/load_global_settings.php';

if (!isset($config_enable_setup) || $config_enable_setup == 1 || !userInvitesReady()) {
    header("Location: /login.php");
    exit();
}

// A password is chosen here - not over plain HTTP, as with signing in
if (
    $config_https_only
    && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')
    && (!isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https')
) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . "/includes/session_init.php";
require_once "includes/inc_set_timezone.php";

// Nobody is signed in here - the history notes where it came from
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');
$session_user_id = 0;

$company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1"));
$company_name = (string) ($company['company_name'] ?? '');
$app = userMailAppName();
$where = $company_name !== '' ? "$company_name on $app" : $app;

$invite_id = intval($_POST['invite'] ?? ($_GET['invite'] ?? 0));
$token = $_POST['token'] ?? ($_GET['token'] ?? '');
$token = is_string($token) ? $token : '';
$link = userInviteByLink($invite_id, $token);
$invite = $link['row'];
$error = '';
$in_name = $invite ? (string) $invite['invite_name'] : '';

// Where they sign in - with the login key when one is required, for someone with a real invite
$sign_in = '/login.php';
if ($invite && !empty($config_login_key_required)) {
    $sign_in .= '?key=' . rawurlencode((string) $config_login_key_secret);
}

if ($invite && $link['state'] === 'pending' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agent_join'])) {
    $in_name = (string) ($_POST['name'] ?? '');
    $user_id = userInviteAccept($invite, $token, [
        'name' => $in_name,
        'password' => (string) ($_POST['new_password'] ?? ''),
        'confirm' => (string) ($_POST['confirm_password'] ?? ''),
    ], $error);
    if ($user_id) {
        $first = strtok(trim(preg_replace('/\s+/', ' ', strip_tags($in_name))), ' ');
        $_SESSION['login_message'] = "Welcome, $first - your account is ready. Sign in with " . $invite['invite_email'] . ' and your new password.'
            . (intval($invite['invite_force_mfa']) ? ' Have your authenticator app ready - you set up multi-factor authentication as you sign in.' : '');
        $_SESSION['login_message_type'] = 'success';
        header("Location: $sign_in");
        exit();
    }
    // Used or cancelled in the meantime - say so rather than show the form again
    $link = userInviteByLink($invite_id, $token);
    $invite = $link['row'];
}

$by = $invite ? userInviteBy($invite) : '';
$ask = $by !== '' ? "Ask $by to send it again." : 'Ask your administrator to send it again.';
$notes = [
    'gone' => ["That link doesn't work", 'It may have been copied only in part - or a newer invite replaced it, so use the link in the latest one.'],
    'expired' => ['That invite has run out', 'Invites work for ' . USER_INVITE_LINK_DAYS . " days. $ask"],
    'accepted' => ["You've joined already", 'Sign in with ' . ($invite['invite_email'] ?? 'your email address') . ' and the password you chose.'],
    'cancelled' => ['That invite was cancelled', $by !== '' ? "Ask $by about it." : 'Ask your administrator about it.'],
];
$state = $link['state'];
$title = $state === 'pending' ? "Join $where" : $notes[$state][0];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-lte-color-mode="off">
<head>
    <meta charset="utf-8">
    <title><?= escapeHtml($company_name !== '' ? $company_name : $app) ?> | <?= escapeHtml($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">
    <?php if (file_exists(__DIR__ . '/uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <link rel="stylesheet" href="/css/itflow_custom.css">
</head>
<body class="hold-transition login-page">

<div class="login-box itflow-join-box">
    <div class="login-logo">
        <?php if (!empty($company['company_logo'])) { ?>
            <img alt="<?= escapeHtml($company_name) ?> logo" height="110" width="380" class="img-fluid" src="/uploads/settings/<?= escapeHtml($company['company_logo']) ?>">
        <?php } else { ?>
            <span class="fw-semibold"><?= escapeHtml($company_name !== '' ? $company_name : $app) ?></span>
        <?php } ?>
    </div>

    <div class="card">
        <div class="card-body login-card-body">

            <?php if ($state === 'pending') { ?>

                <h1 class="h5 mb-1" data-join="agent"><?= escapeHtml($title) ?></h1>
                <p class="text-body-secondary small mb-3">
                    <?= $by !== '' ? escapeHtml($by) . ' invited' : 'You are invited as' ?> <strong><?= escapeHtml($invite['invite_email']) ?></strong> to join <?= escapeHtml($where) ?><?php if ((string) $invite['role_name'] !== '') { ?>, as <strong><?= escapeHtml($invite['role_name']) ?></strong><?php } ?>.
                    Choose your password and you're ready to sign in.
                </p>

                <?php if ($error !== '') { ?>
                    <div class="alert alert-danger small" data-join-error><?= escapeHtml($error) ?></div>
                <?php } ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="invite" value="<?= intval($invite['invite_id']) ?>">
                    <input type="hidden" name="token" value="<?= escapeHtml($token) ?>">
                    <input type="hidden" name="agent_join" value="1">
                    <input type="text" class="d-none" name="username" value="<?= escapeHtml($invite['invite_email']) ?>" autocomplete="username" aria-hidden="true" tabindex="-1">

                    <label class="form-label small mb-1" for="joinName">Your name <strong class="text-danger">*</strong> <span class="text-body-secondary">- as it shows on tickets</span></label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                        <input type="text" class="form-control" id="joinName" name="name" value="<?= escapeHtml($in_name) ?>" maxlength="200" autocomplete="name" required<?= trim($in_name) === '' ? ' autofocus' : '' ?>>
                    </div>

                    <label class="form-label small mb-1" for="joinPassword">Choose a password <span class="text-body-secondary">- at least <?= USER_PASSWORD_MIN ?> characters</span></label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                        <input type="password" class="form-control" id="joinPassword" name="new_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" required<?= trim($in_name) !== '' ? ' autofocus' : '' ?>>
                    </div>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-check-double"></i></span>
                        <input type="password" class="form-control" name="confirm_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" placeholder="The same again" aria-label="The password again" required>
                    </div>
                    <p class="small text-body-secondary mb-3"><i class="fas fa-fw fa-key me-1"></i>It also unlocks the credential vault for you, so keep it to yourself.</p>

                    <?php if (intval($invite['invite_force_mfa'])) { ?>
                        <div class="alert alert-info small d-flex gap-2" data-join-mfa>
                            <i class="fas fa-mobile-alt mt-1"></i>
                            <div>Multi-factor authentication is required: have an authenticator app on your phone ready - you set it up as you sign in.</div>
                        </div>
                    <?php } ?>

                    <button type="submit" class="btn btn-primary w-100 mb-3"><i class="fas fa-check me-2"></i>Join</button>
                </form>

            <?php } else { ?>

                <h1 class="h5 mb-1" data-join-state="<?= escapeHtml($state) ?>"><?= escapeHtml($title) ?></h1>
                <p class="text-body-secondary small mb-3"><?= escapeHtml($notes[$state][1]) ?></p>
                <?php if ($state === 'accepted') { ?>
                    <a class="btn btn-primary w-100 mb-3" href="<?= escapeHtml($sign_in) ?>"><i class="fas fa-sign-in-alt me-2"></i>Sign in</a>
                <?php } ?>

            <?php } ?>

            <a href="<?= escapeHtml($sign_in) ?>"><i class="fas fa-arrow-left me-1"></i>Back to sign in</a>

        </div>
    </div>
</div>

<script src="/js/login_prevent_resubmit.js"></script>

</body>
</html>
