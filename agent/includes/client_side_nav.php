<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-<?php if (isset($_GET['client_id'])) { echo "gray"; } else { echo nullable_htmlentities($config_theme); } ?> d-print-none">

    <a class="brand-link pb-1 mt-1" href="/agent/clients.php">
        <p class="h5">
            <i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i>
             <span class="brand-text">
                 Back | <strong><?php echo $client_abbreviation; ?></strong>
            </span>
        </p>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <li class="nav-item mt-3">
                    <a href="/agent/client_overview.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_overview.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Overview</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="/agent/contacts.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "contacts.php" || basename($_SERVER["PHP_SELF"]) == "contact_details.php") { echo "active"; } ?>">
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
                    <a href="/agent/locations.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "locations.php") { echo "active"; } ?>">
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

                <?php if ($config_module_enable_ticketing == 1 && lookupUserPermission("module_support") >= 1) { ?>
                    <li class="nav-header mt-3">SUPPORT</li>

                    <li class="nav-item">
                        <a href="/agent/tickets.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "tickets.php" || basename($_SERVER["PHP_SELF"]) == "ticket.php") { echo "active"; } ?>">
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
                        <a href="/agent/recurring_tickets.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "recurring_tickets.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-redo-alt"></i>
                            <p>
                                Recurring Tickets
                                <?php
                                if ($num_recurring_tickets) { ?>
                                    <span class="right badge"><?php echo $num_recurring_tickets; ?></span>
                                <?php } ?>

                            </p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/agent/projects.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "projects.php" || basename($_SERVER["PHP_SELF"]) == "project_details.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-project-diagram"></i>
                            <p>
                                Projects
                                <?php if ($num_active_projects) { ?>
                                    <span class="right badge text-light" data-toggle="tooltip" title="Open Projects"><?php echo $num_active_projects; ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>

                <?php } ?>

                <li class="nav-item">
                    <a href="/agent/vendors.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "vendors.php") { echo "active"; } ?>">
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
                    <a href="/agent/calendar.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "calendar.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <p>
                            Calendar
                            <?php
                            if ($num_calendar_events > 0) { ?>
                                <span class="right badge text-light"><?php echo $num_calendar_events; ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

                <?php if ($config_module_enable_itdoc == 1) { ?>

                    <li class="nav-header mt-3">DOCUMENTATION</li>

                    <?php if (lookupUserPermission("module_support") >= 1) { ?>
                        <li class="nav-item">
                            <a href="/agent/assets.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "assets.php" || basename($_SERVER["PHP_SELF"]) == "client_asset_details.php") { echo "active"; } ?>">
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
                            <a href="/agent/software.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "software.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-cube"></i>
                                <p>
                                    Licenses
                                    <?php
                                    if ($num_software > 0) { ?>
                                        <span class="right badge <?php if ($num_software_expiring > 0) { ?> badge-warning text-dark <?php } ?> <?php if ($num_software_expired > 0) { ?> badge-danger <?php } ?> text-white"><?php echo $num_software; ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <?php if (lookupUserPermission("module_credential") >= 1) { ?>
                            <li class="nav-item">
                                <a href="/agent/credentials.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "credentials.php") { echo "active"; } ?>">
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
                        <?php } ?>

                        <li class="nav-item">
                            <a href="/agent/networks.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "networks.php") { echo "active"; } ?>">
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
                            <a href="/agent/racks.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "racks.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-server"></i>
                                <p>
                                    Racks
                                    <?php
                                    if ($num_racks > 0) { ?>
                                        <span class="right badge text-light"><?php echo $num_racks; ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="/agent/certificates.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "certificates.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-lock"></i>
                                <p>
                                    Certificates

                                    <?php
                                    if ($num_certificates > 0) { ?>
                                        <span class="right badge <?php if ($num_certificates_expiring > 0) { ?> badge-warning text-dark <?php } ?> <?php if ($num_certificates_expired > 0) { ?> badge-danger <?php } ?> text-white"><?php echo $num_certificates; ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="/agent/domains.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "domains.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-globe"></i>
                                <p>
                                    Domains

                                    <?php
                                    if ($num_domains > 0) { ?>
                                        <span class="right badge <?php if (isset($num_domains_expiring)) { ?> badge-warning text-dark<?php } ?> <?php if (isset($num_domains_expired)) { ?> badge-danger <?php } ?> text-white"><?php echo $num_domains; ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="/agent/services.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "services.php") { echo "active"; } ?>">
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
                            <a href="/agent/documents.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "documents.php" || basename($_SERVER["PHP_SELF"]) == "document_details.php") { echo "active"; } ?>">
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

                    <?php } ?>

                    <!-- Allow files even without module_support for things like contracts, etc. ) -->
                    <li class="nav-item">
                        <a href="/agent/files.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "files.php") { echo "active"; } ?>">
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

                <?php if ($config_module_enable_accounting == 1) { ?>

                    <li class="nav-header mt-3">BILLING</li>

                    <?php if (lookupUserPermission("module_sales") >= 1) { ?>

                        <li class="nav-item">
                            <a href="/agent/invoices.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "invoices.php" || basename($_SERVER["PHP_SELF"]) == "invoice.php") { echo "active"; } ?>">
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
                            <a href="/agent/recurring_invoices.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "recurring_invoices.php" || basename($_SERVER["PHP_SELF"]) == "recurring_invoice.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-redo-alt"></i>
                                <p>
                                    Recurring Invoices
                                    <?php
                                    if ($num_recurring_invoices) { ?>
                                        <span class="right badge"><?php echo $num_recurring_invoices; ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="/agent/quotes.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "quotes.php" || basename($_SERVER["PHP_SELF"]) == "quote.php") { echo "active"; } ?>">
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

                    <?php } ?>

                    <?php if (lookupUserPermission("module_financial") >= 1) { ?>
                        <li class="nav-item">
                            <a href="/agent/payments.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "payments.php") { echo "active"; } ?>">
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
                    <?php } ?>

                    <li class="nav-item">
                        <a href="/agent/trips.php?client_id=<?php echo $client_id; ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "trips.php") { echo "active"; } ?>">
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

            </ul>
        </nav>
        <!-- /.sidebar-menu -->

        <div class="mb-3"></div>

    </div>
    <!-- /.sidebar -->
</aside>