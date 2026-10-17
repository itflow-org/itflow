<?php
/*
 * Client Portal
 * HTML Header - the portal's shell: a light top bar carrying the company's
 * logo (or its initials on the theme colour), the sections this contact can
 * open, New ticket and their account menu. On a phone the sections move to
 * a bar along the bottom and a menu that slides in.
 *
 * Portal pages set a Content-Security-Policy of default-src 'self', so nothing
 * in here - or in a page that sets it - may use an inline script or a style=""
 * attribute: behaviour lives in js/portal.js, looks in css/itflow_custom.css.
 */

header("X-Frame-Options: DENY"); // Legacy

// A page that turned the policy on keeps it, with data: images allowed - the
// arrows on drop-downs, ticks in checkboxes and the close crosses are data:
// SVGs in Bootstrap's CSS, which default-src 'self' alone blocked. A page
// that takes a card sets $portal_stripe first: the same policy, with
// Stripe's script, frames and API let in - Stripe's own list, from
// docs.stripe.com/security/guide (Stripe.js, Checkout and Link)
if (!empty($portal_stripe)) {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://*.stripe.com https://*.link.com; "
        . "script-src 'self' https://js.stripe.com https://*.js.stripe.com https://checkout.stripe.com; "
        . "frame-src https://js.stripe.com https://*.js.stripe.com https://hooks.stripe.com https://checkout.stripe.com https://link.com https://*.link.com; "
        . "connect-src 'self' https://api.stripe.com https://checkout.stripe.com https://link.com https://*.link.com");
} else {
    foreach (headers_list() as $portal_header) {
        if (stripos($portal_header, 'Content-Security-Policy:') === 0) {
            header("Content-Security-Policy: default-src 'self'; img-src 'self' data:");
            break;
        }
    }
}

$portal_page = basename($_SERVER['PHP_SELF']);
$portal_nav = portalNav();

// Dark mode is the contact's own choice, kept in a cookie by js/portal.js -
// read here so the first paint is already the right colour
$portal_dark = ($_COOKIE['itflow_portal_theme'] ?? '') === 'dark';

$portal_contact_name = (string) ($contact['contact_name'] ?? '');
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $portal_dark ? 'dark' : 'light' ?>" data-lte-color-mode="off" data-lte-print="plain">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= escapeHtml($session_company_name) ?> | Client Portal</title>

    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="<?= $portal_dark ? 'dark' : 'light' ?>">

    <!-- Favicon: If Fav Icon exists, else use the default one -->
    <?php if(file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico')) { ?>
        <link rel="icon" href="/uploads/favicon.ico">
    <?php } ?>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="/libs/fontawesome-free/css/all.min.css">

    <!-- Theme style -->
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte.min.css">
    <?php /* Opt-in AdminLTE 3 palette, new in v4.5.0. Supplies the
             --bs-<colour> tokens plus .text-bg-* / .card-* / .callout-*
             / .bg-gradient-* families. Loads BEFORE itflow_custom.css so
             our own .bg-<colour> box colours still win. */ ?>
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte-colors-v3.min.css">
    <link rel="stylesheet" href="/libs/sweetalert2/css/sweetalert2.min.css">

    <!-- ITFlow style: the AdminLTE 3 compatibility layer, the theme colours and the
         portal's own look. Must load last so it wins. -->
    <link rel="stylesheet" href="/css/itflow_custom.css">

    <?php /* Only the pages that set this flag before including inc_all.php pull
             in intl-tel-input - it is ~200KB of JS and flag sprites, and one
             page uses it. */ ?>
    <?php if (!empty($portal_load_phone_inputs)) { ?>
        <link rel="stylesheet" href="/libs/intl-tel-input/css/intlTelInput.min.css">
    <?php } ?>

    <?php if (!empty($portal_load_datatables)) { ?>
        <link rel="stylesheet" href="/libs/DataTables/datatables.min.css">
    <?php } ?>

</head>

<?php /* Same country bridge as the agent header: intl-tel-input wants an ISO2
         code, the companies table stores a country NAME. Given as a data
         attribute rather than an inline <script>, which this portal's CSP
         (default-src 'self') would block outright. Empty when the company has
         no country set, which js/phone_inputs.js reads as "let the library
         decide". */ ?>
<body class="itflow-portal d-flex flex-column min-vh-100 bg-body-tertiary theme-<?= escapeHtml($config_theme) ?>" data-lte-primary="<?= escapeHtml($config_theme) ?>"
      data-itflow-phone-country="<?= escapeHtml($country_iso2_array[$session_company_country] ?? '') ?>">

<header class="itflow-portal-header">
    <div class="container d-flex align-items-center gap-3">

        <a class="itflow-portal-brand" href="/client/index.php">
            <?php if ($session_company_logo) { ?>
                <img src="/uploads/settings/<?= escapeHtml($session_company_logo) ?>" alt="<?= escapeHtml($session_company_name) ?>">
            <?php } else { ?>
                <span class="itflow-brand-mark itflow-brand-mark-initials" aria-hidden="true"><?= escapeHtml(clientInitials('', $session_company_name)) ?></span>
                <span class="itflow-portal-brand-name"><?= escapeHtml($session_company_name) ?></span>
            <?php } ?>
        </a>

        <nav class="itflow-portal-nav d-none d-lg-flex" aria-label="Portal">
            <?php foreach ($portal_nav as $item) {
                $active = in_array($portal_page, $item['pages'], true);
                if (!empty($item['children'])) { ?>
                    <div class="dropdown">
                        <a class="itflow-portal-nav-link dropdown-toggle<?= $active ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-fw <?= escapeHtml($item['icon']) ?>"></i><?= escapeHtml($item['label']) ?>
                        </a>
                        <div class="dropdown-menu">
                            <?php foreach ($item['children'] as $child) { ?>
                                <a class="dropdown-item<?= in_array($portal_page, $child['pages'], true) ? ' active' : '' ?>" href="<?= escapeHtml($child['href']) ?>">
                                    <i class="fas fa-fw <?= escapeHtml($child['icon']) ?> me-2"></i><?= escapeHtml($child['label']) ?>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                <?php } else { ?>
                    <a class="itflow-portal-nav-link<?= $active ? ' active' : '' ?>" href="<?= escapeHtml($item['href']) ?>"<?= !empty($item['new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                        <i class="<?= escapeHtml($item['icon_class'] ?? 'fas fa-fw ' . $item['icon']) ?>"></i><?= escapeHtml($item['label']) ?>
                    </a>
                <?php } ?>
            <?php } ?>
        </nav>

        <div class="ms-auto d-flex align-items-center gap-2">
            <a class="btn btn-primary d-none d-sm-inline-flex align-items-center" href="/client/ticket_add.php"><i class="fas fa-plus me-2"></i>New ticket</a>

            <div class="dropdown">
                <button type="button" class="itflow-portal-account" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Your account">
                    <?= portalAvatarHtml($portal_contact_name, $session_contact_photo ? "/uploads/clients/$session_client_id/$session_contact_photo" : '') ?>
                    <span class="d-none d-md-inline text-truncate"><?= escapeHtml($portal_contact_name) ?></span>
                    <i class="fas fa-chevron-down small text-body-secondary d-none d-md-inline"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <div class="dropdown-header">
                        <div class="fw-semibold text-body"><?= escapeHtml($portal_contact_name) ?></div>
                        <div class="small"><?= escapeHtml($session_client_name) ?></div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item<?= $portal_page === 'profile.php' ? ' active' : '' ?>" href="/client/profile.php"><i class="fas fa-fw fa-user me-2"></i>Your account</a>
                    <a class="dropdown-item<?= $portal_page === 'activity.php' ? ' active' : '' ?>" href="/client/activity.php"><i class="fas fa-fw fa-list me-2"></i>Activity</a>
                    <button type="button" class="dropdown-item" data-portal-theme-toggle>
                        <i class="fas fa-fw <?= $portal_dark ? 'fa-sun' : 'fa-moon' ?> me-2"></i><?= $portal_dark ? 'Light mode' : 'Dark mode' ?>
                    </button>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/client/post.php?logout"><i class="fas fa-fw fa-sign-out-alt me-2"></i>Sign out</a>
                </div>
            </div>
        </div>

    </div>
</header>

<?php /* Phone: the sections along the bottom of the screen, where a thumb
         reaches them, and everything else in a menu that slides in. */ ?>
<nav class="itflow-portal-tabbar d-lg-none" aria-label="Portal">
    <a href="/client/index.php" class="<?= $portal_page === 'index.php' ? 'active' : '' ?>"><i class="fas fa-home"></i><span>Home</span></a>
    <a href="/client/tickets.php" class="<?= in_array($portal_page, $portal_nav['tickets']['pages'], true) && $portal_page !== 'ticket_add.php' ? 'active' : '' ?>"><i class="fas fa-life-ring"></i><span>Tickets</span></a>
    <a href="/client/ticket_add.php" class="itflow-portal-tabbar-new<?= $portal_page === 'ticket_add.php' ? ' active' : '' ?>"><i class="fas fa-plus"></i><span>New</span></a>
    <?php if (isset($portal_nav['billing'])) { ?>
        <a href="/client/invoices.php" class="<?= in_array($portal_page, $portal_nav['billing']['pages'], true) ? 'active' : '' ?>"><i class="fas fa-file-invoice-dollar"></i><span>Billing</span></a>
    <?php } ?>
    <a href="#portalMenu" data-bs-toggle="offcanvas" role="button" aria-controls="portalMenu"><i class="fas fa-bars"></i><span>Menu</span></a>
</nav>

<div class="offcanvas offcanvas-end itflow-portal-menu" tabindex="-1" id="portalMenu" aria-label="Menu">
    <div class="offcanvas-header">
        <div class="min-w-0">
            <div class="fw-semibold text-truncate"><?= escapeHtml($portal_contact_name) ?></div>
            <div class="small text-body-secondary text-truncate"><?= escapeHtml($session_client_name) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <?php foreach ($portal_nav as $item) { ?>
            <?php if (!empty($item['children'])) { ?>
                <div class="itflow-portal-menu-heading"><?= escapeHtml($item['label']) ?></div>
                <?php foreach ($item['children'] as $child) { ?>
                    <a class="itflow-portal-menu-link<?= in_array($portal_page, $child['pages'], true) ? ' active' : '' ?>" href="<?= escapeHtml($child['href']) ?>"><i class="fas fa-fw <?= escapeHtml($child['icon']) ?>"></i><?= escapeHtml($child['label']) ?></a>
                <?php } ?>
            <?php } else { ?>
                <a class="itflow-portal-menu-link<?= in_array($portal_page, $item['pages'], true) ? ' active' : '' ?>" href="<?= escapeHtml($item['href']) ?>"<?= !empty($item['new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><i class="<?= escapeHtml($item['icon_class'] ?? 'fas fa-fw ' . $item['icon']) ?>"></i><?= escapeHtml($item['label']) ?></a>
            <?php } ?>
        <?php } ?>
        <div class="itflow-portal-menu-heading">Your account</div>
        <a class="itflow-portal-menu-link<?= $portal_page === 'profile.php' ? ' active' : '' ?>" href="/client/profile.php"><i class="fas fa-fw fa-user"></i>Your account</a>
        <a class="itflow-portal-menu-link<?= $portal_page === 'activity.php' ? ' active' : '' ?>" href="/client/activity.php"><i class="fas fa-fw fa-list"></i>Activity</a>
        <button type="button" class="itflow-portal-menu-link" data-portal-theme-toggle><i class="fas fa-fw <?= $portal_dark ? 'fa-sun' : 'fa-moon' ?>"></i><?= $portal_dark ? 'Light mode' : 'Dark mode' ?></button>
        <a class="itflow-portal-menu-link" href="/client/post.php?logout"><i class="fas fa-fw fa-sign-out-alt"></i>Sign out</a>
    </div>
</div>

<!-- Page content container -->
<main class="itflow-portal-main container">

    <?php
    //Alert Feedback
    if (!empty($_SESSION['alert_message'])) {
        if (!isset($_SESSION['alert_type'])) {
            $_SESSION['alert_type'] = "info";
        }
        ?>
        <div class="alert alert-<?= alertStyleClass($_SESSION['alert_type'] ?? 'success') ?> alert-dismissible" id="alert">
            <?= alertMessageHtml($_SESSION['alert_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php

        unset($_SESSION['alert_type']);
        unset($_SESSION['alert_message']);

    }
    ?>
