<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-<?php echo htmlentities($config_theme); ?> d-print-none">

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav class="">

            <div class="dropdown brand-link">
                <a class="" href="#">
                    <h3 class="brand-text text-light mb-0"><?php echo htmlentities($session_company_name); ?></h3>
                </a>
            </div>

            <ul class="nav nav-pills nav-sidebar flex-column mt-3" data-widget="treeview" data-accordion="false">

                <!-- Dashboard item (tech/financial) -->
                <?php if ($session_user_role == 2) { ?>

                    <li class="nav-item">
                        <a href="dashboard_technical.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "dashboard_technical.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                <?php } else { ?>

                    <li class="nav-item">
                        <a href="dashboard_financial.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "dashboard_financial.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                <?php } ?>
                <!-- End dashboard item (tech/financial) -->

                <li class="nav-item">
                    <a href="clients.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "clients.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Clients</p>
                    </a>
                </li>

                <?php if ($session_user_role >= 2 && $config_module_enable_ticketing == 1) { ?>

                    <li class="nav-header mt-3">SUPPORT</li>
                    <li class="nav-item">
                        <a href="tickets.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "tickets.php" || basename($_SERVER["PHP_SELF"]) == "ticket.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Tickets</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="scheduled_tickets.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "scheduled_tickets.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-clock"></i>
                            <p>Schedule Ticket</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="calendar_events.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "calendar_events.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-calendar"></i>
                            <p>Calendar</p>
                        </a>
                    </li>

                <?php }

                if ($config_module_enable_accounting == 1) { ?>

                    <li class="nav-header mt-3">SALES</li>
                    <li class="nav-item">
                        <a href="quotes.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "quotes.php" || basename($_SERVER["PHP_SELF"]) == "quote.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-file-invoice"></i>
                            <p>Quotes</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="invoices.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "invoices.php" || basename($_SERVER["PHP_SELF"]) == "invoice.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-file-invoice-dollar"></i>
                            <p>Invoices</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="revenues.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "revenues.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-credit-card"></i>
                            <p>Revenues</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="recurring_invoices.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "recurring_invoices.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-sync-alt"></i>
                            <p>Recurring</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="products.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "products.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-box"></i>
                            <p>Products</p>
                        </a>
                    </li>

                <?php }

                if ($session_user_role == 1 || $session_user_role == 3 && $config_module_enable_accounting == 1) { ?>

                    <li class="nav-header mt-3">ACCOUNTING</li>
                    <li class="nav-item">
                        <a href="payments.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "payments.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-credit-card"></i>
                            <p>Payments</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="vendors.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "vendors.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Vendors</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="expenses.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "expenses.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-shopping-cart"></i>
                            <p>Expenses</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="trips.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "trips.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-route"></i>
                            <p>Trips</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="accounts.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "accounts.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-piggy-bank"></i>
                            <p>Accounts</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="transfers.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "transfers.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-exchange-alt"></i>
                            <p>Transfers</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="report_income_summary.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_income_summary.php") { echo "active"; } ?>">
                            <i class="fas fa-chart-bar nav-icon"></i>
                            <p>Reports</p>
                            <i class="fas fa-angle-right nav-icon float-right"></i>
                        </a>
                    </li>

                <?php }

                if ($session_user_role == 3) { ?>

                    <li class="nav-item mt-3">
                        <a href="users.php" class="nav-link">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>Settings</p>
                            <i class="fas fa-angle-right nav-icon float-right"></i>
                        </a>
                    </li>

                <?php } ?>

            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->

</aside>
