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
    <title><?php echo nullable_htmlentities($session_company_name); ?> | Client Portal</title>

    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <!-- Favicon: If Fav Icon exists, else use the default one -->
    <?php if(file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/favicon.ico')) { ?>
        <link rel="icon" href="/uploads/favicon.ico">
    <?php } ?>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="/plugins/fontawesome-free/css/all.min.css">

    <!-- Theme style -->
    <link rel="stylesheet" href="/plugins/adminlte/css/adminlte.min.css">

</head>

<!-- Navbar -->

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php"><?php echo nullable_htmlentities($session_company_name); ?></a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item <?php if (basename($_SERVER['PHP_SELF']) == "index.php") {echo "active";} ?>">
                    <a class="nav-link" href="/client/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == "tickets.php" || basename($_SERVER['PHP_SELF']) == "ticket_add.php" || basename($_SERVER['PHP_SELF']) == "ticket.php") {echo "active";} ?>" href="/client/tickets.php">Tickets</a>
                </li>

                <?php if (($session_contact_primary == 1 || $session_contact_is_billing_contact) && $config_module_enable_accounting == 1) { ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['invoices.php', 'quotes.php', 'autopay.php']) ? 'active' : ''; ?>" href="#" id="navbarDropdown1" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
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

                <?php if ($config_module_enable_itdoc && ($session_contact_primary == 1 || $session_contact_is_technical_contact)) { ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['documents.php', 'contacts.php', 'domains.php', 'certificates.php']) ? 'active' : ''; ?>" href="#" id="navbarDropdown2" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
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
                $sql_custom_links = mysqli_query($mysqli, "SELECT * FROM custom_links WHERE custom_link_location = 3 AND custom_link_archived_at IS NULL
                    ORDER BY custom_link_order ASC, custom_link_name ASC"
                );

                while ($row = mysqli_fetch_array($sql_custom_links)) {
                    $custom_link_name = nullable_htmlentities($row['custom_link_name']);
                    $custom_link_uri = nullable_htmlentities($row['custom_link_uri']);
                    $custom_link_new_tab = intval($row['custom_link_new_tab']);
                    if ($custom_link_new_tab == 1) {
                        $target = "target='_blank' rel='noopener noreferrer'";
                    } else {
                        $target = "";
                    }

                    ?>

                    <li class="nav-item">
                        <a href="<?php echo $custom_link_uri; ?>" <?php echo $target; ?> class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == basename($custom_link_uri)) { echo "active"; } ?>"><?php echo $custom_link_name ?></a>
                    </li>

                <?php } ?>

            </ul><!-- End left nav -->

            <ul class="nav navbar-nav pull-right">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                        <?php echo stripslashes(nullable_htmlentities($session_contact_name)); ?>
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="/client/profile.php"><i class="fas fa-fw fa-user mr-2"></i>Account</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/client/post.php?logout"><i class="fas fa-fw fa-sign-out-alt mr-2"></i>Sign out</a>
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
                <img src="/uploads/clients/<?= $session_client_id ?>/<?= $session_contact_photo ?>" alt="..." height="50" width="50" class="img-circle img-responsive">

            <?php } else { ?>
                <span class="fa-stack fa-2x rounded-left">
                    <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                    <span class="fa fa-stack-1x text-white"><?php echo $session_contact_initials; ?></span>
                </span>
            <?php } ?>
        </div>

        <div class="col-md-11 p-0">
                <?php if ($session_company_logo) { ?>
                    <img height="48" width="142" class="img-fluid float-right" src="<?php echo "/uploads/settings/$session_company_logo"; ?>">
                <?php } ?>
            <h4>Welcome, <strong><?php echo stripslashes(nullable_htmlentities($session_contact_name)); ?></strong>!</h4>
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
        <div class="alert alert-<?php echo $_SESSION['alert_type']; ?>" id="alert">
            <?php echo nullable_htmlentities($_SESSION['alert_message']); ?>
            <button class='close' data-dismiss='alert'>&times;</button>
        </div>
        <?php

        unset($_SESSION['alert_type']);
        unset($_SESSION['alert_message']);

    }
    ?>
