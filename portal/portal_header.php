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
    <title><?php echo nullable_htmlentities($company_name); ?> | Client Portal</title>

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

<!-- Navbar -->

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php"><?php echo nullable_htmlentities($company_name); ?></a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item <?php if (basename($_SERVER['PHP_SELF']) == "index.php") {echo "active";} ?>">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                    <li class="nav-item">
                        <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == "tickets.php" || basename($_SERVER['PHP_SELF']) == "ticket_add.php" || basename($_SERVER['PHP_SELF']) == "ticket.php") {echo "active";} ?>" href="tickets.php">Tickets</a>
                    </li>
                <?php if ($session_contact_id == $session_client_primary_contact_id || $session_contact_is_billing_contact) { ?>
                    <li class="nav-item">
                        <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == "invoices.php") {echo "active";} ?>" href="invoices.php">Invoices</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == "quotes.php") {echo "active";} ?>" href="quotes.php">Quotes</a>
                    </li>
                <?php } ?>
                <?php if ($session_contact_id == $session_client_primary_contact_id || $session_contact_is_technical_contact) { ?>
                    <li class="nav-item">
                        <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == "documents.php") {echo "active";} ?>" href="documents.php">Documents</a>
                    </li>
                <?php } ?>
            </ul>

            <ul class="nav navbar-nav pull-right">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                        <?php echo nullable_htmlentities($session_contact_name); ?>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item" href="profile.php">Profile</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="portal_post.php?logout">Sign out</a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<br>

<!-- Page content container -->
<div class="container">
