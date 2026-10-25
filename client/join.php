<?php
/*
 * Client Portal
 * Joining - from an invite (ours, or a colleague's in the portal) or the link
 * to finish signing up: ?invite=&token=. The person puts in their name, job
 * title, phone and a password - or, when Microsoft 365 sign-in is set up,
 * chooses that instead - and is made a contact who can sign in
 * (portalInviteAccept(), functions/portal_invite.php). Then they sign in on
 * the usual page. A link that has run out, been used or cancelled says so,
 * and what to do instead.
 */

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

require_once '../config.php';
require_once '../functions.php';
require_once '../includes/load_global_settings.php';

if (intval($config_client_portal_enable) !== 1 || !portalInvitesReady()) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . "/../includes/session_init.php";
require_once "../includes/inc_set_timezone.php";

// Nobody is signed in here - the history notes where it came from
$session_ip = escapeSql(getIP());
$session_user_agent = escapeSql($_SERVER['HTTP_USER_AGENT'] ?? '');
$session_user_id = 0;

$company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1"));
$company_name = (string) ($company['company_name'] ?? '');
$methods = portalSignInMethods();

$invite_id = intval($_POST['invite'] ?? ($_GET['invite'] ?? 0));
$token = (string) ($_POST['token'] ?? ($_GET['token'] ?? ''));
$link = portalInviteByLink($invite_id, $token);
$invite = $link['row'];
$error = '';
$in = ['name' => '', 'title' => '', 'phone' => ''];

if ($invite && $link['state'] === 'pending') {
    $who = portalInviteWho($invite['invite_email'], $invite['invite_client_id']);
    $in['name'] = trim((string) $invite['invite_name']) !== '' ? (string) $invite['invite_name'] : (string) ($who['name'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['portal_join'])) {
        $in = [
            'name' => (string) ($_POST['name'] ?? ''),
            'title' => (string) ($_POST['title'] ?? ''),
            'phone' => (string) ($_POST['phone'] ?? ''),
        ];
        $method = (string) ($_POST['method'] ?? 'local');
        $contact_id = portalInviteAccept($invite, $in + [
            'method' => $method,
            'password' => (string) ($_POST['new_password'] ?? ''),
            'confirm' => (string) ($_POST['confirm_password'] ?? ''),
        ], $error);
        if ($contact_id) {
            $first = strtok(trim(preg_replace('/\s+/', ' ', $in['name'])), ' ');
            $_SESSION['login_message'] = "Welcome, $first - your account is ready. " . (isset($methods[$method]) && $method === 'azure'
                ? 'Sign in with your Microsoft 365 account below.'
                : 'Sign in with ' . $invite['invite_email'] . ' and your new password.');
            $_SESSION['login_message_type'] = 'success';
            header("Location: /login.php");
            exit();
        }
        // Used in the meantime - say so rather than show the form again
        $link = portalInviteByLink($invite_id, $token);
        $invite = $link['row'];
    }
}

$signup = $invite && $invite['invite_kind'] === 'signup';
$by = $invite ? portalInviteBy($invite) : '';
$ask = $by !== '' ? "Ask $by to send it again." : 'Ask your company\'s primary contact or us to invite you.';
$notes = [
    'gone' => ["That link doesn't work", 'It may have been copied only in part - or a newer email replaced it, so use the link in the latest one.'],
    'expired' => ['That link has run out', $signup ? 'Links to finish signing up work for ' . PORTAL_SIGNUP_LINK_HOURS . ' hours - ask for a new one.' : 'Invites work for ' . PORTAL_INVITE_LINK_DAYS . " days. $ask"],
    'accepted' => ["You've joined already", 'Sign in with ' . ($invite['invite_email'] ?? 'your email address') . '. Forgot your password? Choose Forgot password on the sign-in page.'],
    'cancelled' => ['That invite was cancelled', $ask],
];
$state = $link['state'];
$title = $state === 'pending' ? ($signup ? 'Finish signing up' : 'Join ' . $invite['client_name']) : $notes[$state][0];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-lte-color-mode="off">
<head>
    <meta charset="utf-8">
    <title><?= escapeHtml($company_name) ?> | <?= escapeHtml($title) ?></title>
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

<div class="login-box itflow-join-box">
    <div class="login-logo">
        <?php if (!empty($company['company_logo'])) { ?>
            <img alt="<?= escapeHtml($company_name) ?> logo" height="110" width="380" class="img-fluid" src="/uploads/settings/<?= escapeHtml($company['company_logo']) ?>">
        <?php } else { ?>
            <span class="fw-semibold"><?= escapeHtml($company_name) ?></span>
        <?php } ?>
    </div>

    <div class="card">
        <div class="card-body login-card-body">

            <?php if ($state === 'pending') { ?>

                <h1 class="h5 mb-1" data-join="<?= $signup ? 'signup' : 'invite' ?>"><?= escapeHtml($title) ?></h1>
                <p class="text-body-secondary small mb-3">
                    <?php if ($signup) { ?>
                        Your client portal account for <?= escapeHtml($invite['client_name']) ?>, as <strong><?= escapeHtml($invite['invite_email']) ?></strong>.
                    <?php } else { ?>
                        <?= $by !== '' ? escapeHtml($by) . ' invited' : 'You are invited as' ?> <strong><?= escapeHtml($invite['invite_email']) ?></strong> to the <?= escapeHtml($company_name) ?> client portal - where you raise and follow support tickets.
                    <?php } ?>
                </p>

                <?php if ($error !== '') { ?>
                    <div class="alert alert-danger small" data-join-error><?= escapeHtml($error) ?></div>
                <?php } ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="invite" value="<?= intval($invite['invite_id']) ?>">
                    <input type="hidden" name="token" value="<?= escapeHtml($token) ?>">
                    <input type="hidden" name="portal_join" value="1">
                    <input type="hidden" name="method" value="local">
                    <input type="text" class="d-none" name="username" value="<?= escapeHtml($invite['invite_email']) ?>" autocomplete="username" aria-hidden="true" tabindex="-1">

                    <label class="form-label small mb-1" for="joinName">Your name <strong class="text-danger">*</strong></label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                        <input type="text" class="form-control" id="joinName" name="name" value="<?= escapeHtml($in['name']) ?>" maxlength="200" autocomplete="name" required<?= $in['name'] === '' ? ' autofocus' : '' ?>>
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label small mb-1" for="joinTitle">Job title</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-fw fa-id-badge"></i></span>
                                <input type="text" class="form-control" id="joinTitle" name="title" value="<?= escapeHtml($in['title']) ?>" maxlength="200" autocomplete="organization-title">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small mb-1" for="joinPhone">Phone</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-fw fa-phone"></i></span>
                                <input type="tel" class="form-control" id="joinPhone" name="phone" value="<?= escapeHtml($in['phone']) ?>" maxlength="40" autocomplete="tel">
                            </div>
                        </div>
                    </div>

                    <label class="form-label small mb-1" for="joinPassword">Choose a password <span class="text-body-secondary">- at least <?= USER_PASSWORD_MIN ?> characters</span></label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                        <input type="password" class="form-control" id="joinPassword" name="new_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" required<?= $in['name'] !== '' ? ' autofocus' : '' ?>>
                    </div>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-check-double"></i></span>
                        <input type="password" class="form-control" name="confirm_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" placeholder="The same again" aria-label="The password again" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-2"><i class="fas fa-check me-2"></i><?= $signup ? 'Create my account' : 'Join' ?></button>
                    <?php if (isset($methods['azure'])) { ?>
                        <div class="text-center small text-body-secondary my-2">or, with no password here</div>
                        <button type="submit" class="btn btn-outline-secondary w-100 mb-3" name="method" value="azure" formnovalidate data-join-microsoft><i class="fab fa-microsoft me-2"></i>Use my Microsoft 365 account</button>
                    <?php } ?>
                </form>

            <?php } else { ?>

                <h1 class="h5 mb-1" data-join-state="<?= escapeHtml($state) ?>"><?= escapeHtml($title) ?></h1>
                <p class="text-body-secondary small mb-3"><?= escapeHtml($notes[$state][1]) ?></p>
                <?php if ($state === 'accepted') { ?>
                    <a class="btn btn-primary w-100 mb-3" href="/login.php"><i class="fas fa-sign-in-alt me-2"></i>Sign in</a>
                <?php } elseif ($signup && portalSignupOpen()) { ?>
                    <a class="btn btn-primary w-100 mb-3" href="/client/signup.php"><i class="fas fa-paper-plane me-2"></i>Ask for a new link</a>
                <?php } ?>

            <?php } ?>

            <a href="/login.php"><i class="fas fa-arrow-left me-1"></i>Back to sign in</a>

        </div>
    </div>
</div>

<script src="/js/login_prevent_resubmit.js"></script>

</body>
</html>
