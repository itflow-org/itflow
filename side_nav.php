<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-<?php echo nullable_htmlentities($config_theme); ?> d-print-none">

    <div class="brand-link">   
        <h3 class="brand-text text-light mb-0"><?php echo nullable_htmlentities($session_company_name); ?></h3>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills nav-sidebar flex-column mt-3" data-widget="treeview" data-accordion="false">

                <!-- Dashboard item (tech/financial) -->
                <?php if ($session_user_role == 2 || $config_module_enable_accounting == 0) { ?>

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
                            <p>Administrative Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard_technical.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "dashboard_technical.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-cogs"></i>
                            <p>Technical Dashboard</p>
                        </a>
                    </li>
                <?php } ?>
                <!-- End dashboard item (tech/financial) -->

                <li class="nav-item">
                    <a href="clients.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "clients.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-user-friends"></i>
                        <p>Client Management</p>
                    </a>
                </li>

                <?php if ($session_user_role >= 2 && $config_module_enable_ticketing == 1) { ?>

                    <li class="nav-header mt-3">SUPPORT</li>
                    <li class="nav-item">
                        <a href="tickets.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "tickets.php" || basename($_SERVER["PHP_SELF"]) == "ticket.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Support Tickets</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="scheduled_tickets.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "scheduled_tickets.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-calendar-check"></i>
                            <p>Scheduled Tickets</p>
                        </a>
                    </li>

                <?php }

                if ($config_module_enable_accounting == 1) { ?>

                    <li class="nav-header mt-3">SALES</li>
                    <li class="nav-item">
                        <a href="client_leads.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "client_leads.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-bullhorn"></i>
                            <p>Leads</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="quotes.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "quotes.php" || basename($_SERVER["PHP_SELF"]) == "quote.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-comment-dollar"></i>
                            <p>Quotes</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="invoices.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "invoices.php" || basename($_SERVER["PHP_SELF"]) == "invoice.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-file-invoice"></i>
                            <p>Invoices</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="recurring_invoices.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "recurring_invoices.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-redo-alt"></i>
                            <p>Rec. Invoices</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="revenues.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "revenues.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-hand-holding-usd"></i>
                            <p>Revenues</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="products.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "products.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-box-open"></i>
                            <p>Products</p>
                        </a>
                    </li>

                <?php }

                if ($session_user_role == 1 || $session_user_role == 3 && $config_module_enable_accounting == 1) { ?>

                    <li class="nav-header mt-3">FINANCE</li>
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
                        <a href="recurring_expenses.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "recurring_expenses.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-clock"></i>
                            <p>Rec. Expenses</p>
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
                        <a href="budget.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "budget.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-balance-scale"></i>
                            <p>Budget</p>
                        </a>
                    </li>

                <?php } ?>

                <li class="nav-header mt-3">MORE</li>

                <li class="nav-item">
                    <a href="calendar_events.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "calendar_events.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <p>Calendar</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="trips.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "trips.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-route"></i>
                        <p>Trips</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="report_income_summary.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_income_summary.php") { echo "active"; } ?>">
                        <i class="fas fa-chart-line nav-icon"></i>
                        <p>Reports</p>
                        <i class="fas fa-angle-right nav-icon float-right"></i>
                    </a>
                </li>

                <?php if ($session_user_role == 3) { ?>

                <li class="nav-item">
                    <a href="users.php" class="nav-link">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>Administration</p>
                        <i class="fas fa-angle-right nav-icon float-right"></i>
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