<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-<?php echo nullable_htmlentities($config_theme); ?> d-print-none">

    <a class="brand-link pb-1 mt-1" href="clients.php">    
        <p class="h5"><i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i> Back | <strong><?php echo shortenClient($client_name); ?></strong></p>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <li class="nav-item mt-3">
                    <a href="client_overview.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_overview.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Overview</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="client_contacts.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_contacts.php" || basename($_SERVER["PHP_SELF"]) == "client_contact_details.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-users"></i>
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
                    <a href="client_locations.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_locations.php") { echo "active"; } ?>">
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

                
                <?php if ($config_module_enable_ticketing == 1) { ?>
                <li class="nav-header mt-3">SUPPORT</li>

                <li class="nav-item">
                    <a href="client_tickets.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_tickets.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-life-ring"></i>
                        <p>
                            Tickets
                            <?php
                            if ($num_active_tickets > 0) { ?>
                                <span class="right badge <?php if ($num_active_tickets > 0) { ?> badge-danger <?php } ?> text-light"><?php echo $num_active_tickets; ?></span>
                            <?php } ?>

                        </p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="client_scheduled_tickets.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_scheduled_tickets.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-calendar-check"></i>
                        <p>
                            Schedule Ticket
                            <?php
                            if ($num_scheduled_tickets > 0) { ?>
                                <span class="right badge text-light"><?php echo $num_scheduled_tickets; ?></span>
                            <?php } ?>

                        </p>
                    </a>
                </li>

                <?php } ?>

                <li class="nav-item">
                    <a href="client_vendors.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_vendors.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-building"></i>
                        <p>
                            Vendors
                            <?php
                            if ($num_vendors > 0) { ?>
                                <span class="right badge text-light"><?php echo $num_vendors; ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="client_events.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_events.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <p>
                            Calendar
                            <?php
                            if ($num_events > 0) { ?>
                                <span class="right badge text-light"><?php echo $num_events; ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

                <?php if ($config_module_enable_itdoc == 1) { ?>

                <li class="nav-header mt-3">DOCUMENTATION</li>

                <li class="nav-item">
                    <a href="client_assets.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_assets.php" || basename($_SERVER["PHP_SELF"]) == "client_asset_details.php") { echo "active"; } ?>">
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
                    <a href="client_software.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_software.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-certificate"></i>
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
                    <a href="client_logins.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_logins.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-key"></i>
                        <p>
                            Passwords
                            <?php
                            if ($num_logins > 0) { ?>
                                <span class="right badge text-light"><?php echo $num_logins; ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="client_networks.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_networks.php") { echo "active"; } ?>">
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
                    <a href="client_certificates.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_certificates.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-lock"></i>
                        <p>
                            Certificates

                            <?php
                            if ($num_certificates > 0) { ?>
                                <span class="right badge <?php if ($num_certs_expiring > 0) { ?> badge-warning <?php } ?> text-light"><?php echo $num_certificates; ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="client_domains.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_domains.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-globe"></i>
                        <p>
                            Domains

                            <?php
                            if ($num_domains > 0) { ?>
                                <span class="right badge <?php if ($num_domains_expiring > 0) { ?> badge-warning <?php } ?> text-light"><?php echo $num_domains; ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="client_services.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_services.php") { echo "active"; } ?>">
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

                <li class="nav-item">
                    <a href="client_documents.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_documents.php" || basename($_SERVER["PHP_SELF"]) == "client_document_details.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-folder"></i>
                        <p>
                            Documents
                            <?php
                            if ($num_documents > 0) { ?>
                                <span class="right badge text-light"><?php echo $num_documents; ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>


                <li class="nav-item">
                    <a href="client_files.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_files.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-paperclip"></i>
                        <p>
                            Files
                            <?php
                            if ($num_files > 0) { ?>
                                <span class="right badge text-light"><?php echo $num_files; ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

                <?php } ?>

                <?php if ($session_user_role == 1 || $session_user_role > 2 && $config_module_enable_accounting == 1) { ?>

                    <li class="nav-header mt-3">FINANCE</li>

                    <li class="nav-item">
                        <a href="client_invoices.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_invoices.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-file-invoice"></i>
                            <p>
                                Invoices
                                <?php
                                if ($num_invoices > 0) { ?>
                                    <span class="right badge <?php if ($num_invoices_open > 0) { ?> badge-danger <?php } ?> text-light"><?php echo $num_invoices; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="client_recurring_invoices.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_recurring_invoices.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-redo-alt"></i>
                            <p>
                                Rec. Invoices
                                <?php
                                if ($num_recurring > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_recurring; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="client_quotes.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_quotes.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-comment-dollar"></i>
                            <p>
                                Quotes
                                <?php
                                if ($num_quotes > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_quotes; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="client_payments.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_payments.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-credit-card"></i>
                            <p>
                                Payments
                                <?php
                                if ($num_payments > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_payments; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="client_trips.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_trips.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-route"></i>
                            <p>
                                Trips
                                <?php
                                if ($num_trips > 0) { ?>
                                    <span class="right badge text-light"><?php echo $num_trips; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>

                <?php } ?>

                <li class="nav-header mt-3">MORE</li>

                <li class="nav-item">
                    <a href="client_shared_items.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_shared_items.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-share"></i>
                        <p>
                            Shared Links
                            <?php
                            if ($num_shared_links > 0) { ?>
                                <span class="right badge text-light"><?php echo $num_shared_links; ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="client_logs.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_logs.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>
                            Audit Logs
                            <?php
                            if ($num_logs > 0) { ?>
                                <span class="right badge text-light"><?php echo $num_logs; ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

            </ul>
        </nav>
        <!-- /.sidebar-menu -->

        <div class="mb-3"></div>
        
    </div>
    <!-- /.sidebar -->
</aside>
