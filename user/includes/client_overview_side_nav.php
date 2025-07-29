<?php 
// Badge Counts

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('contact_id') AS num FROM contacts LEFT JOIN clients ON contact_client_id = client_id WHERE contact_archived_at IS NULL AND client_archived_at IS NULL"));
$num_contacts = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('location_id') AS num FROM locations LEFT JOIN clients ON location_client_id = client_id WHERE location_archived_at IS NULL AND client_archived_at IS NULL"));
$num_locations = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('asset_id') AS num FROM assets LEFT JOIN clients ON asset_client_id = client_id WHERE asset_archived_at IS NULL AND client_archived_at IS NULL"));
$num_assets = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('service_id') AS num FROM services LEFT JOIN clients ON service_client_id = client_id WHERE client_archived_at IS NULL"));
$num_services = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('credential_id') AS num FROM credentials LEFT JOIN clients ON credential_client_id = client_id WHERE credential_archived_at IS NULL AND client_archived_at IS NULL"));
$num_credentials = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('network_id') AS num FROM networks LEFT JOIN clients ON network_client_id = client_id WHERE network_archived_at IS NULL AND client_archived_at IS NULL"));
$num_networks = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('domain_id') AS num FROM domains LEFT JOIN clients ON domain_client_id = client_id WHERE domain_archived_at IS NULL AND client_archived_at IS NULL"));
$num_domains = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('certificate_id') AS num FROM certificates LEFT JOIN clients ON certificate_client_id = client_id WHERE certificate_archived_at IS NULL AND client_archived_at IS NULL"));
$num_certificates = $row['num'];

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('software_id') AS num FROM software LEFT JOIN clients ON software_client_id = client_id WHERE software_archived_at IS NULL AND client_archived_at IS NULL"));
$num_software = $row['num'];

?>

<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary d-print-none">

    <a class="pb-1 mt-1 brand-link" href="clients.php">
        <p class="h6"><i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i>
            <span class="brand-text ">Back | <strong>Client Overview</strong>
        </p>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills nav-sidebar flex-column mt-2" data-widget="treeview" data-accordion="false">

                <?php  if (lookupUserPermission("module_support") >= 1) { ?>
                    <li class="nav-item">
                        <a href="contacts.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "contacts.php" || basename($_SERVER["PHP_SELF"]) == "contact_details.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-address-book"></i>
                            <p>
                                Contacts
                                <?php
                                if ($num_contacts > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_contacts; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="locations.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "locations.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-map-marker-alt"></i>
                            <p>
                                Locations
                                <?php
                                if ($num_locations > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_locations; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="assets.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "assets.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-desktop"></i>
                            <p>
                                Assets
                                <?php
                                if ($num_assets > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_assets; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="software.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "software.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-cube"></i>
                            <p>
                                Licenses
                                <?php
                                if ($num_software > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_software; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="credentials.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "credentials.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-key"></i>
                            <p>
                                Credentials
                                <?php
                                if ($num_credentials > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_credentials; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="networks.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "networks.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-network-wired"></i>
                            <p>
                                Networks
                                <?php
                                if ($num_networks > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_networks; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="certificates.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "certificates.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-lock"></i>
                            <p>
                                Certificates
                                <?php
                                if ($num_certificates > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_certificates; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="domains.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "domains.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-globe"></i>
                            <p>
                                Domains
                                <?php
                                if ($num_domains > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_domains; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="services.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "services.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-stream"></i>
                            <p>
                                Services
                                <?php
                                if ($num_services > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_services; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                <?php } ?>

            </ul>

        </nav>
        <!-- /.sidebar-menu -->

        <div class="sidebar-custom mb-3">

        </div>

    </div>
    <!-- /.sidebar -->
</aside>
