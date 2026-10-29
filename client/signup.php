<?php
/*
 * Client Portal
 * Create an account - someone puts in their work email, and is emailed a link
 * to finish (client/join.php) when it is at one of the Domains of a client
 * that lets people sign up (portalSignupRequest(), functions/portal_invite.php).
 * What the page says never depends on the address, so it can't be used to
 * find out who has an account here. Open only while signing up is
 * (portalSignupOpen()); otherwise it goes to the sign-in page.
 */

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:");
header("X-Frame-Options: DENY");

require_once '../config.php';
require_once '../functions.php';
require_once '../includes/load_global_settings.php';

if (!portalSignupOpen()) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . "/../includes/session_init.php";
require_once "../includes/inc_set_timezone.php";

$company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1"));
$company_name = (string) ($company['company_name'] ?? '');

$message = '';
$asked = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['portal_signup'])) {
    $email = trim(mb_substr((string) ($_POST['email'] ?? ''), 0, 200));
    $form_ok = !empty($_SESSION['portal_signup_form']) && hash_equals((string) $_SESSION['portal_signup_form'], (string) ($_POST['form'] ?? ''));
    if (!$form_ok) {
        $message = 'That took a while - put your email address in again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'That is not an email address.';
    } else {
        // Filled in the field people can't see - a robot. It is told the same, and nothing is sent.
        if (trim((string) ($_POST['website'] ?? '')) === '') {
            portalSignupRequest($email, getIP(), $_SERVER['HTTP_USER_AGENT'] ?? '');
        }
        $asked = $email;
        unset($_SESSION['portal_signup_form']);
    }
}
if (empty($_SESSION['portal_signup_form'])) {
    $_SESSION['portal_signup_form'] = bin2hex(random_bytes(16));
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-lte-color-mode="off">
<head>
    <meta charset="utf-8">
    <title><?= escapeHtml($company_name) ?> | Create an account</title>
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

            <h1 class="h5 mb-1">Create an account</h1>

            <?php if ($asked !== '') { ?>

                <div class="alert alert-success small mt-3" data-signup-asked>
                    If <strong><?= escapeHtml($asked) ?></strong> can sign up here, a link to finish is on its way - it works for <?= PORTAL_SIGNUP_LINK_HOURS ?> hours.
                </div>
                <p class="text-body-secondary small">Nothing after a few minutes? Check your junk mail - or ask whoever looks after IT at your company to invite you.</p>

            <?php } else { ?>

                <p class="text-body-secondary small mb-3">For the <?= escapeHtml($company_name) ?> client portal - where you raise and follow support tickets. Put in your work email address and we'll email you a link to finish.</p>

                <?php if ($message !== '') { ?>
                    <div class="alert alert-danger small"><?= escapeHtml($message) ?></div>
                <?php } ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="form" value="<?= escapeHtml($_SESSION['portal_signup_form']) ?>">
                    <div class="d-none" aria-hidden="true">
                        <label for="signupWebsite">Leave this empty</label>
                        <input type="text" id="signupWebsite" name="website" value="" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                        <input type="email" class="form-control" placeholder="Your work email address" name="email" maxlength="200" autocomplete="email" aria-label="Your work email address" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-3" name="portal_signup" value="1"><i class="fas fa-paper-plane me-2"></i>Email me a link</button>
                </form>

                <p class="text-body-secondary small">Have an account already? Sign in below - or choose Forgot password there.</p>

            <?php } ?>

            <a href="/login.php"><i class="fas fa-arrow-left me-1"></i>Back to sign in</a>

        </div>
    </div>
</div>

<script src="/js/login_prevent_resubmit.js"></script>

</body>
</html>
