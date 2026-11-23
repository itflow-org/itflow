<!-- Main Sidebar Container -->
<aside class="app-sidebar shadow d-print-none" data-bs-theme="dark">

    <div class="sidebar-brand">
        <a class="brand-link" href="/agent/clients.php<?= $client_is_lead ? '?leads=1' : '' ?>">
            <i class="fas fa-arrow-left me-2"></i>
            <span class="brand-text fs-6 text-body-secondary"><?= $client_is_lead ? 'All Leads' : 'All Clients' ?></span>
        </a>
    </div>

    <!-- Sidebar -->
    <div class="sidebar-wrapper">

        <?php
        /*
         * Which client this is - same initials and colour as on the clients
         * list - and a click opens a switcher to jump to another one
         * (js/client_switcher.js). Switching keeps you on the same kind of
         * page: a list stays that list, and a single record (a ticket, an
         * invoice) goes to its list, since that record belongs to this client.
         */
        $client_switch_pages = [
            'asset.php' => 'assets.php',
            'contact.php' => 'contacts.php',
            'document.php' => 'files.php',
            'invoice.php' => 'invoices.php',
            'network.php' => 'networks.php',
            'project.php' => 'projects.php',
            'quote.php' => 'quotes.php',
            'rack.php' => 'racks.php',
            'recurring_invoice.php' => 'recurring_invoices.php',
            'contract.php' => 'contracts.php',
            'contract_document.php' => 'contracts.php',
            'ticket.php' => 'tickets.php',
        ];
        $client_switch_page = basename($_SERVER['PHP_SELF']);
        $client_switch_page = $client_switch_pages[$client_switch_page] ?? $client_switch_page;
        if (!preg_match('/^[a-z_]+\.php$/', $client_switch_page) || !is_file(__DIR__ . "/../$client_switch_page")) {
            $client_switch_page = 'client_overview.php';
        }
        ?>
        <div class="dropdown itflow-client-switcher" data-client-switcher data-client-id="<?= $client_id ?>" data-switch-url="/agent/<?= $client_switch_page ?>?client_id=">
            <button type="button" class="itflow-client-nav-identity" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-display="static" aria-expanded="false" title="<?= $client_name ?>">
                <span class="itflow-client-avatar itflow-client-avatar-sm text-bg-<?= $client_initials_tone ?>" aria-hidden="true"><?= $client_initials ?></span>
                <span class="overflow-hidden flex-grow-1">
                    <?php if ($client_is_lead || $client_archived_at) { ?>
                        <span class="d-block itflow-client-nav-identity-label"><?= ($client_archived_at ? 'Archived ' : '') . ($client_is_lead ? 'lead' : 'client') ?></span>
                    <?php } ?>
                    <span class="d-block itflow-client-nav-identity-name"><?= $client_name ?></span>
                </span>
                <i class="fas fa-fw fa-sort itflow-client-switcher-caret" aria-hidden="true"></i>
                <span class="visually-hidden">Switch client</span>
            </button>
            <div class="dropdown-menu itflow-client-switcher-menu">
                <div class="px-2 pb-2">
                    <input type="search" class="form-control form-control-sm" placeholder="Switch to client" aria-label="Find a client" autocomplete="off" data-client-switcher-search>
                </div>
                <div class="itflow-client-switcher-list" data-client-switcher-list></div>
            </div>
        </div>
        <script src="<?= staticFileUrl('/js/client_switcher.js') ?>" defer></script>

        <?php
        // Pages few clients use hide while this client has nothing there - unless it's the page open - and More, at the end,
        // shows them (js/app.js). Only when that tucks away two or more: More for one page would take as much room as the page.
        $client_nav_page = basename($_SERVER['PHP_SELF']);
        $client_nav_quiet = [];
        $client_nav_docs = $config_module_enable_itdoc == 1 && lookupUserPermission("module_support") >= 1; // as the menu below shows them
        foreach (array_filter([
            'Calendar' => moduleOn('calendar') ? [$num_calendar_events + intval($num_calendar_bookings ?? 0), ['calendar.php']] : null,
            'Racks' => $client_nav_docs ? [$num_racks, ['racks.php', 'rack.php']] : null,
            'Certificates' => $client_nav_docs ? [$num_certificates, ['certificates.php']] : null,
            'Shared Links' => $client_nav_docs ? [$num_shares ?? 0, ['shares.php']] : null,
            'Services' => $client_nav_docs ? [$num_services, ['services.php']] : null,
            'Trips' => moduleOn('trips') ? [$num_trips, ['trips.php']] : null,
        ]) as $client_nav_label => [$client_nav_count, $client_nav_pages]) {
            if (intval($client_nav_count) <= 0 && !in_array($client_nav_page, $client_nav_pages, true)) {
                $client_nav_quiet[] = $client_nav_label;
            }
        }
        if (count($client_nav_quiet) < 2) {
            $client_nav_quiet = [];
        }
        $client_nav_quiet_attr = function ($label) use ($client_nav_quiet) {
            return in_array($label, $client_nav_quiet, true) ? ' data-nav-quiet hidden' : '';
        };
        ?>

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="/agent/client_overview.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_overview.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Overview</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="/agent/contacts.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "contacts.php" || basename($_SERVER["PHP_SELF"]) == "contact.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-address-book"></i>
                        <p>
                            Contacts
                            <?php
                            if ($num_contacts > 0) { ?>
                                <span class="right badge rounded-pill text-light"><?= number_format(intval($num_contacts)) ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="/agent/locations.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "locations.php" || basename($_SERVER["PHP_SELF"]) == "location.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-map-marker-alt"></i>
                        <p>
                            Locations
                            <?php
                            if ($num_locations > 0) { ?>
                                <span class="right badge rounded-pill text-light"><?= number_format(intval($num_locations)) ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="/agent/vendors.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "vendors.php" || basename($_SERVER["PHP_SELF"]) == "vendor.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-building"></i>
                        <p>
                            Vendors
                            <?php
                            if ($num_vendors > 0) { ?>
                                <span class="right badge rounded-pill text-light"><?= number_format(intval($num_vendors)) ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>

                <?php if (moduleOn('calendar')) { ?>
                    <li class="nav-item"<?= $client_nav_quiet_attr('Calendar') ?>>
                        <a href="/agent/calendar.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "calendar.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <p>
                                Calendar
                                <?php
                                if ($num_calendar_events > 0) { ?>
                                    <span class="right badge rounded-pill text-light"><?= number_format(intval($num_calendar_events)) ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                <?php } ?>

                <li class="nav-item">
                    <a href="/agent/audit_log.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "audit_log.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>Audit Log</p>
                    </a>
                </li>

                <?php if ($config_module_enable_ticketing == 1 && lookupUserPermission("module_support") >= 1) { ?>
                    <li class="nav-header mt-3">SUPPORT</li>

                    <li class="nav-item">
                        <a href="/agent/tickets.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "tickets.php" || basename($_SERVER["PHP_SELF"]) == "ticket.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>
                                Tickets
                                <?php
                                if ($num_active_tickets > 0) { ?>
                                    <span class="right badge rounded-pill <?= $num_active_tickets > 0 ? 'text-bg-danger' : 'text-light' ?>"><?= $num_active_tickets ?></span>
                                <?php } ?>

                            </p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/agent/recurring_tickets.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "recurring_tickets.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-redo-alt"></i>
                            <p>
                                Recurring Tickets
                                <?php
                                if ($num_recurring_tickets) { ?>
                                    <span class="right badge rounded-pill text-light"><?= number_format(intval($num_recurring_tickets)) ?></span>
                                <?php } ?>

                            </p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="/agent/projects.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "projects.php" || basename($_SERVER["PHP_SELF"]) == "project.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-project-diagram"></i>
                            <p>
                                Projects
                                <?php if ($num_active_projects) { ?>
                                    <span class="right badge rounded-pill text-light" title="Open Projects"><?= number_format(intval($num_active_projects)) ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>

                    <?php // Calls from the phone systems (functions/phone.php) - once there are some, or while open ?>
                    <?php if (!empty($num_phone_calls) || basename($_SERVER["PHP_SELF"]) == "calls.php") { ?>
                        <li class="nav-item">
                            <a href="/agent/calls.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "calls.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-phone-volume"></i>
                                <p>
                                    Calls
                                    <?php if (!empty($num_phone_calls)) { ?>
                                        <span class="right badge rounded-pill text-light" title="Calls in the last 30 days"><?= number_format(intval($num_phone_calls)) ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>
                    <?php } ?>

                <?php } ?>

                <?php if ($config_module_enable_itdoc == 1) { ?>

                    <li class="nav-header mt-3">INFRASTRUCTURE</li>

                    <?php if (lookupUserPermission("module_support") >= 1) { ?>
                        <li class="nav-item">
                            <a href="/agent/assets.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "assets.php" || basename($_SERVER["PHP_SELF"]) == "asset.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-desktop"></i>
                                <p>
                                    Assets
                                    <?php
                                    if ($num_assets > 0) { ?>
                                        <span class="right badge rounded-pill text-light"><?= number_format(intval($num_assets)) ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="/agent/networks.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "networks.php" || basename($_SERVER["PHP_SELF"]) == "network.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-network-wired"></i>
                                <p>
                                    Networks
                                    <?php
                                    if ($num_networks > 0) { ?>
                                        <span class="right badge rounded-pill text-light"><?= number_format(intval($num_networks)) ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-item"<?= $client_nav_quiet_attr('Racks') ?>>
                            <a href="/agent/racks.php?client_id=<?= $client_id ?>" class="nav-link <?php if (in_array(basename($_SERVER["PHP_SELF"]), ["racks.php", "rack.php"])) { echo "active"; } ?>">
                                <i class="nav-icon fas fa-server"></i>
                                <p>
                                    Racks
                                    <?php
                                    if ($num_racks > 0) { ?>
                                        <span class="right badge rounded-pill text-light"><?= number_format(intval($num_racks)) ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-header mt-3">WEB & SECURITY</li>

                        <?php if (lookupUserPermission("module_credential") >= 1) { ?>
                            <li class="nav-item">
                                <a href="/agent/credentials.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "credentials.php") { echo "active"; } ?>">
                                    <i class="nav-icon fas fa-key"></i>
                                    <p>
                                        Credentials
                                        <?php
                                        if ($num_credentials > 0) { ?>
                                            <span class="right badge rounded-pill text-light"><?= number_format(intval($num_credentials)) ?></span>
                                        <?php } ?>
                                    </p>
                                </a>
                            </li>
                        <?php } ?>

                        <li class="nav-item"<?= $client_nav_quiet_attr('Certificates') ?>>
                            <a href="/agent/certificates.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "certificates.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-lock"></i>
                                <p>
                                    Certificates
                                    <?php
                                    if ($num_certificates > 0) { ?>
                                        <span class="right badge rounded-pill <?= $num_certificates_expired > 0 ? 'text-bg-danger' : ($num_certificates_expiring > 0 ? 'text-bg-warning' : 'text-light') ?>"><?= $num_certificates ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="/agent/domains.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "domains.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-globe"></i>
                                <p>
                                    Domains
                                    <?php
                                    if ($num_domains > 0) { ?>
                                        <span class="right badge rounded-pill <?= $num_domains_urgent > 0 ? 'text-bg-danger' : ($num_domains_expiring_warning > 0 ? 'text-bg-warning' : 'text-light') ?>"><?= $num_domains ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-item"<?= $client_nav_quiet_attr('Shared Links') ?>>
                            <a href="/agent/shares.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "shares.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-share-alt"></i>
                                <p>
                                    Shared Links
                                    <?php if (!empty($num_shares)) { ?>
                                        <span class="right badge rounded-pill <?= !empty($num_shares_received) ? 'text-bg-warning' : 'text-light' ?>" <?= !empty($num_shares_received) ? 'title="A login was sent back - save it"' : '' ?>><?= $num_shares ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-header mt-3">DOCUMENTATION</li>

                        <li class="nav-item">
                            <a href="/agent/software.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "software.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-cube"></i>
                                <p>
                                    Licenses
                                    <?php
                                    if ($num_software > 0) { ?>
                                        <span class="right badge rounded-pill <?= $num_software_expired > 0 ? 'text-bg-danger' : ($num_software_expiring > 0 ? 'text-bg-warning' : 'text-light') ?>"><?= $num_software ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-item"<?= $client_nav_quiet_attr('Services') ?>>
                            <a href="/agent/services.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "services.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-stream"></i>
                                <p>
                                    Services
                                    <?php
                                    if ($num_services > 0) { ?>
                                        <span class="right badge rounded-pill text-light"><?= number_format(intval($num_services)) ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                    <?php } ?>

                    <!-- Allow files even without module_support for things like contracts, etc. ) -->
                    <li class="nav-item">
                        <a href="/agent/files.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "files.php" || basename($_SERVER["PHP_SELF"]) == "document.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-folder"></i>
                            <p>
                                Files
                                <?php
                                if ($num_files > 0) { ?>
                                    <span class="right badge rounded-pill text-light"><?= number_format(intval($num_files)) ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>

                <?php } ?>

                <?php if ($config_module_enable_accounting == 1) { ?>

                    <li class="nav-header mt-3">BILLING</li>

                    <?php if (lookupUserPermission("module_sales") >= 1) { ?>

                        <li class="nav-item">
                            <a href="/agent/quotes.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "quotes.php" || basename($_SERVER["PHP_SELF"]) == "quote.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-comment-dollar"></i>
                                <p>
                                    Quotes
                                    <?php
                                    if ($num_quotes > 0) { ?>
                                        <span class="right badge rounded-pill text-light"><?= number_format(intval($num_quotes)) ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="/agent/invoices.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "invoices.php" || basename($_SERVER["PHP_SELF"]) == "invoice.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-file-invoice-dollar"></i>
                                <p>
                                    Invoices
                                    <?php
                                    if ($num_invoices > 0) { ?>
                                        <span class="right badge rounded-pill <?= $num_invoices_open > 0 ? 'text-bg-danger' : 'text-light' ?>"><?= $num_invoices ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <?php if ($config_module_enable_ticketing == 1 || lookupUserPermission('module_financial') >= 1) { ?>
                            <li class="nav-item">
                                <a href="/agent/unbilled_time.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "unbilled_time.php") { echo "active"; } ?>">
                                    <i class="nav-icon fas fa-user-clock"></i>
                                    <p>
                                        Unbilled Time
                                        <?php if ($num_unbilled_tickets || !empty($num_unbilled_expenses) || !empty($num_unbilled_trips)) { ?>
                                            <span class="right badge rounded-pill text-light"><?= $num_unbilled_tickets + intval($num_unbilled_expenses ?? 0) + intval($num_unbilled_trips ?? 0) ?></span>
                                        <?php } ?>
                                    </p>
                                </a>
                            </li>
                        <?php } ?>

                        <li class="nav-item">
                            <a href="/agent/recurring_invoices.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "recurring_invoices.php" || basename($_SERVER["PHP_SELF"]) == "recurring_invoice.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-redo-alt"></i>
                                <p>
                                    Recurring Invoices
                                    <?php
                                    if ($num_recurring_invoices) { ?>
                                        <span class="right badge rounded-pill text-light"><?= number_format(intval($num_recurring_invoices)) ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="/agent/contracts.php?client_id=<?= $client_id ?>" class="nav-link <?php if (in_array(basename($_SERVER["PHP_SELF"]), ['contracts.php', 'contract.php', 'contract_document.php'], true)) { echo "active"; } ?>">
                                <i class="nav-icon fas fa-file-contract"></i>
                                <p>
                                    Contracts
                                    <?php if (!empty($num_contracts)) { ?>
                                        <span class="right badge rounded-pill text-light" title="In force"><?= intval($num_contracts) ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>

                    <?php } ?>

                    <?php if (lookupUserPermission("module_financial") >= 1) { ?>
                        <li class="nav-item">
                            <a href="/agent/income.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "income.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-hand-holding-usd"></i>
                                <p>
                                    Income
                                    <?php
                                    if ($num_income > 0) { ?>
                                        <span class="right badge rounded-pill text-light"><?= number_format(intval($num_income)) ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>
                    <?php } ?>

                    <?php if (moduleOn('trips')) { ?>
                        <li class="nav-item"<?= $client_nav_quiet_attr('Trips') ?>>
                            <a href="/agent/trips.php?client_id=<?= $client_id ?>" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "trips.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-route"></i>
                                <p>
                                    Trips
                                    <?php
                                    if ($num_trips > 0) { ?>
                                        <span class="right badge rounded-pill text-light"><?= number_format(intval($num_trips)) ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>
                    <?php } ?>

                <?php } ?>

                <?php if ($client_nav_quiet) { ?>
                    <li class="nav-item mt-2">
                        <a href="#" class="nav-link" data-nav-more title="None for this client yet: <?= escapeHtml(implode(', ', $client_nav_quiet)) ?>">
                            <i class="nav-icon fas fa-ellipsis-v"></i>
                            <p>More <span class="right badge rounded-pill text-light"><?= count($client_nav_quiet) ?></span></p>
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
