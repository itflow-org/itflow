<?php

/*
 * The page a managed instance shows in place of what was asked for - suspended by its manager,
 * or (for someone with no session to carry a message back) a change turned away while it's
 * read-only. Shown by managedRequestGuard() (functions/managed.php), which sets:
 *
 *   $managed_blocked_title    the heading
 *   $managed_blocked_message  what the manager says, or a plain notice
 *   $managed_blocked_contact  how to reach the manager - its email and phone
 *   $managed_blocked_signout  where signing out goes, '' when nobody is signed in
 *   $managed_blocked_back     where going back goes (read-only), '' for no way back
 *
 * Standalone - nothing here may need a session, settings beyond the company, or the layout.
 */

isset($mysqli) || die("Direct file access is not allowed");

$managed_blocked_company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_name, company_logo FROM companies WHERE company_id = 1")) ?: [];
$managed_blocked_name = (string) ($managed_blocked_company['company_name'] ?? 'ITFlow');
$managed_blocked_logo = (string) ($managed_blocked_company['company_logo'] ?? '');

header('Cache-Control: no-store');
header('Content-Type: text/html; charset=UTF-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= escapeHtml($managed_blocked_name) ?> | <?= escapeHtml($managed_blocked_title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= staticFileUrl('/libs/fontawesome-free/css/all.min.css') ?>">
    <?php if (file_exists(__DIR__ . '/../uploads/favicon.ico')) { ?>
        <link rel="icon" type="image/x-icon" href="/uploads/favicon.ico">
    <?php } ?>
    <link rel="stylesheet" href="<?= staticFileUrl('/libs/adminlte/css/adminlte.min.css') ?>">
    <link rel="stylesheet" href="<?= staticFileUrl('/css/itflow_custom.css') ?>">
</head>
<body class="hold-transition login-page">

<div class="login-box" style="width: min(30rem, 100% - 2rem)">
    <div class="login-logo">
        <?php if ($managed_blocked_logo !== '') { ?>
            <img alt="<?= escapeHtml($managed_blocked_name) ?> logo" height="110" width="380" class="img-fluid" src="/uploads/settings/<?= escapeHtml($managed_blocked_logo) ?>">
        <?php } else { ?>
            <?= escapeHtml($managed_blocked_name) ?>
        <?php } ?>
    </div>

    <div class="card" data-managed-blocked>
        <div class="card-body p-4 text-center">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-<?= $managed_blocked_back !== '' ? 'warning' : 'danger' ?>-subtle text-<?= $managed_blocked_back !== '' ? 'warning' : 'danger' ?>-emphasis mb-3" style="width: 3.5rem; height: 3.5rem; font-size: 1.5rem" aria-hidden="true">
                <i class="fas fa-<?= $managed_blocked_back !== '' ? 'lock' : 'ban' ?>"></i>
            </span>
            <h1 class="h4 mb-2"><?= escapeHtml($managed_blocked_title) ?></h1>
            <p class="mb-0 text-body-secondary" style="white-space: pre-line"><?= escapeHtml($managed_blocked_message) ?></p>
            <?php if ($managed_blocked_contact !== '') { ?>
                <p class="mt-3 mb-0 small"><i class="fas fa-fw fa-headset me-1 text-body-secondary"></i><?= escapeHtml($managed_blocked_contact) ?></p>
            <?php } ?>

            <?php if ($managed_blocked_back !== '' || $managed_blocked_signout !== '') { ?>
                <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                    <?php if ($managed_blocked_back !== '') { ?>
                        <a href="<?= escapeHtml($managed_blocked_back) ?>" class="btn btn-light"><i class="fas fa-fw fa-arrow-left me-2"></i>Go back</a>
                    <?php } ?>
                    <?php if ($managed_blocked_signout !== '') { ?>
                        <a href="<?= escapeHtml($managed_blocked_signout) ?>" class="btn btn-outline-secondary"><i class="fas fa-fw fa-sign-out-alt me-2"></i>Sign out</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

</body>
</html>
