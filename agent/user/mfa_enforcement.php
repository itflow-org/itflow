<?php
require_once "../../config.php";
require_once "../../functions.php";
require_once "../../includes/check_login.php";
require_once '../../libs/totp/totp.php'; //TOTP MFA Lib

// Set up already - nothing to do here
if (trim((string) $session_token) !== '') {
    redirect("../$config_start_page");
}

// Get Company Logo
$sql = mysqli_query($mysqli, "SELECT company_logo FROM companies");
$row = mysqli_fetch_assoc($sql);
$company_logo = escapeHtml($row['company_logo']);


// Only generate the token once and store it in session:
if (empty($_SESSION['mfa_token'])) {
    $_SESSION['mfa_token'] = generateTotpSecret();
}
$mfa_secret = $_SESSION['mfa_token'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="robots" content="noindex">

    <title>MFA Enforcement | <?= $session_company_name ?></title>

    <!--
    Favicon
    If Fav Icon exists else use the default one
    -->
    <?php if(file_exists('../../uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="../../uploads/favicon.ico">
    <?php } ?>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="<?= staticFileUrl('/libs/fontawesome-free/css/all.min.css') ?>">

    <!-- Theme style -->
    <link rel="stylesheet" href="<?= staticFileUrl('/libs/adminlte/css/adminlte.min.css') ?>">
    <link rel="stylesheet" href="<?= staticFileUrl('/css/itflow_custom.css') ?>">

    <!-- jQuery -->

</head>
<body class="hold-transition login-page">
    <?php require_once "../../includes/inc_alert_feedback.php"; ?>
    <div class="login-box">
        <div class="login-logo">
            <?php if (!empty($company_logo)) { ?>
                <img alt="<?= escapeHtml($company_name)?> logo" height="110" width="380" class="img-fluid" src="<?= "../../uploads/settings/$company_logo" ?>">
            <?php } else { ?>
                <span class="text-primary fw-bold"><i class="fas fa-paper-plane me-2"></i>IT</span>Flow
            <?php } ?>
        </div>

        <!-- /.login-logo -->
        <div class="card">
            <div class="card-body login-card-body text-center">

                <p class="login-box-msg fw-semibold mb-1">Set up multi-factor authentication</p>
                <p class="small text-body-secondary">Your administrator requires it - once it's set up, signing in takes your password and a code from your phone.</p>

                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <?php require __DIR__ . '/includes/mfa_setup.php'; ?>

                    <div class="mb-3"></div>
                    <button type="submit" name="enable_mfa" class="btn btn-primary w-100 mb-2"><i class="fas fa-check me-2"></i>Turn On MFA</button>
                    <a class="small text-body-secondary" href="post.php?logout">Sign out</a>
                </form>

            </div>
            <!-- /.login-card-body -->
        </div>
    </div>
    <!-- /.login-box -->

    <!-- REQUIRED SCRIPTS -->

    <!-- Bootstrap 4 -->
    <script src="<?= staticFileUrl('/js/http.js') ?>"></script>
    <script src="<?= staticFileUrl('/libs/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= staticFileUrl('/js/otp_input.js') ?>"></script>

    <script>

    // Fade the alert out after 5s, then collapse it
    (function () {
        const alertEl = document.getElementById('alert');
        if (!alertEl) {
            return;
        }
        setTimeout(function () {
            alertEl.style.transition = 'opacity .5s linear';
            alertEl.style.opacity = '0';
            setTimeout(function () {
                alertEl.style.display = 'none';
            }, 500);
        }, 5000);
    })();

    // Tooltip - manual trigger only, so a copy flash can never stay stranded
    // on screen. This page is standalone and does not load js/app.js, so it
    // carries its own copy of the helper.
    function flashTooltip(button, message) {
        const el = button instanceof Element ? button : document.querySelector(button);
        if (!el) {
            return;
        }
        bootstrap.Tooltip.getInstance(el)?.dispose();
        const tip = new bootstrap.Tooltip(el, {
            trigger: 'manual',
            placement: 'bottom',
            title: message
        });
        tip.show();

        setTimeout(function () {
            tip.dispose();
        }, 1000);
    }

    // Copy buttons (the secret beside the QR code). The same as js/app.js's,
    // cut down to the one data-clipboard-text button this page has: the
    // clipboard API where the browser allows it (https or localhost),
    // otherwise copying a selected textarea.
    function copyText(text, trigger) {
        if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.top = '0';
        area.style.left = '-9999px';
        document.body.appendChild(area);
        area.focus({ preventScroll: true });
        area.select();
        area.setSelectionRange(0, text.length);
        let copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (err) {
            copied = false;
        }
        area.remove();
        trigger.focus({ preventScroll: true });
        return copied ? Promise.resolve() : Promise.reject(new Error('Copy failed'));
    }

    document.querySelectorAll('.clipboardjs').forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            copyText(button.getAttribute('data-clipboard-text') || '', button).then(
                function () { flashTooltip(button, 'Copied!'); },
                function () { flashTooltip(button, 'Failed!'); }
            );
        });
    });

    // Enable Popovers
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
        bootstrap.Popover.getOrCreateInstance(el);
    });

    </script>

</body>

</html>
