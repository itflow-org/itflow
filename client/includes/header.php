<?php
/*
 * Client Portal
 * HTML Header
 */

header("X-Frame-Options: DENY"); // Legacy
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= escapeHtml($session_company_name) ?> | Client Portal</title>

    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

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
    <link rel="stylesheet" href="/libs/adminlte/css/adminlte-colors-v3.css">
    <link rel="stylesheet" href="/libs/sweetalert2/css/sweetalert2.min.css">

    <!-- ITFlow style: the AdminLTE 3 compatibility layer and the theme colours. Must load
         last so it wins, and must load HERE too - it used to be on the agent header only,
         which left this portal without .text-bold, the bg-dark text pairing or any theme. -->
    <link rel="stylesheet" href="/css/itflow_custom.css">

</head>

<body class="theme-<?= escapeHtml($config_theme) ?>">

<!-- Navbar -->

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php"><?= escapeHtml($session_company_name) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto">
                <li class="nav-item <?php if (basename($_SERVER['PHP_SELF']) == "index.php") {echo "active";} ?>">
                    <a class="nav-link" href="/client/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == "tickets.php" || basename($_SERVER['PHP_SELF']) == "ticket_add.php" || basename($_SERVER['PHP_SELF']) == "ticket.php") {echo "active";} ?>" href="/client/tickets.php">Tickets</a>
                </li>

                <?php if (contactCan('accounting') && $config_module_enable_accounting == 1) { ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['invoices.php', 'quotes.php', 'autopay.php']) ? 'active' : '' ?>" href="#" id="navbarDropdown1" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Finance
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarDropdown1">
                            <a class="dropdown-item" href="/client/invoices.php">Invoices</a>
                            <a class="dropdown-item" href="/client/recurring_invoices.php">Recurring Invoices</a>
                            <a class="dropdown-item" href="/client/quotes.php">Quotes</a>
                            <a class="dropdown-item" href="/client/saved_payment_methods.php">Saved Payments</a>
                        </div>
                    </li>
                <?php } ?>

                <?php if ($config_module_enable_itdoc && contactCan('itdoc')) { ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['documents.php', 'contacts.php', 'domains.php', 'certificates.php']) ? 'active' : '' ?>" href="#" id="navbarDropdown2" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Technical
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarDropdown2">
                            <a class="dropdown-item" href="/client/contacts.php">Contacts</a>
                            <a class="dropdown-item" href="/client/assets.php">Assets</a>
                            <a class="dropdown-item" href="/client/documents.php">Documents</a>
                            <a class="dropdown-item" href="/client/domains.php">Domains</a>
                            <a class="dropdown-item" href="/client/certificates.php">Certificates</a>
                            <a class="dropdown-item" href="/client/ticket_view_all.php">All tickets</a>
                        </div>
                    </li>
                <?php } ?>

                <?php
                $sql_custom_links = mysqli_query($mysqli, "SELECT custom_link_name, custom_link_new_tab, custom_link_uri FROM custom_links WHERE custom_link_location = 3 AND custom_link_archived_at IS NULL
                    ORDER BY custom_link_order ASC, custom_link_name ASC"
                );

                while ($row = mysqli_fetch_assoc($sql_custom_links)) {
                    $custom_link_name = escapeHtml($row['custom_link_name']);
                    $custom_link_uri = escapeHtml($row['custom_link_uri']);
                    $custom_link_new_tab = intval($row['custom_link_new_tab']);
                    if ($custom_link_new_tab == 1) {
                        $target = "target='_blank' rel='noopener noreferrer'";
                    } else {
                        $target = "";
                    }

                    ?>

                    <li class="nav-item">
                        <a href="<?= $custom_link_uri ?>" <?= $target ?> class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == basename($custom_link_uri)) { echo "active"; } ?>"><?= $custom_link_name ?></a>
                    </li>

                <?php } ?>

            </ul><!-- End left nav -->

            <ul class="nav navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <?= stripslashes(escapeHtml($session_contact_name)) ?>
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/client/profile.php"><i class="fas fa-fw fa-user me-2"></i>Account</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/client/post.php?logout"><i class="fas fa-fw fa-sign-out-alt me-2"></i>Sign out</a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<br>

<!-- Page content container -->
<div class="container">

    <div class="row mb-3">
        <div class="col-md-1 text-center">
            <?php if (!empty($session_contact_photo)) { ?>
                <img src="/uploads/clients/<?= $session_client_id ?>/<?= $session_contact_photo ?>" alt="..." height="50" width="50" class="rounded-circle img-fluid">

            <?php } else { ?>
                <span class="fa-stack fa-2x rounded-start">
                    <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                    <span class="fa fa-stack-1x text-white"><?= $session_contact_initials ?></span>
                </span>
            <?php } ?>
        </div>

        <div class="col-md-11 p-0">
                <?php if ($session_company_logo) { ?>
                    <img height="48" width="142" class="img-fluid float-end" src="<?= "/uploads/settings/$session_company_logo" ?>">
                <?php } ?>
            <h4>Welcome, <strong><?= stripslashes(escapeHtml($session_contact_name)) ?></strong>!</h4>
        </div>
    </div>
    <hr>

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
