<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary d-print-none">

    <a class="pb-1 mt-1 brand-link" href="clients.php">
        <p class="h5"><i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i>
            <span class="brand-text ">Back | <strong>Reports</strong>
        </p>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills nav-sidebar flex-column mt-2" data-widget="treeview" data-accordion="false">

                <?php  if ($config_module_enable_accounting == 1) { ?>
                    <li class="nav-header">FINANCIAL</li>

                    <?php if (lookupUserPermission("module_financial") >= 1) { ?>

                        <li class="nav-item">
                            <a href="report_income_summary.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_income_summary.php") { echo "active"; } ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Income</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="report_income_by_client.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_income_by_client.php") { echo "active"; } ?>">
                                <i class="far fa-user nav-icon"></i>
                                <p>Income By Client</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="report_recurring_by_client.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_recurring_by_client.php") { echo "active"; } ?>">
                                <i class="fa fa-sync nav-icon"></i>
                                <p>Recurring Income By Client</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="report_clients_with_balance.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_clients_with_balance.php") { echo "active"; } ?>">
                                <i class="fa fa-exclamation-triangle nav-icon"></i>
                                <p>Clients with a Balance</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="report_expense_summary.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_expense_summary.php") { echo "active"; } ?>">
                                <i class="far fa-credit-card nav-icon"></i>
                                <p>Expense</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="report_expense_by_vendor.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_expense_by_vendor.php") { echo "active"; } ?>">
                                <i class="far fa-building nav-icon"></i>
                                <p>Expense By Vendor</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="report_budget.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_budget.php") { echo "active"; } ?>">
                                <i class="fas fa-list nav-icon"></i>
                                <p>Budget</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="report_tax_summary.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_tax_summary.php") { echo "active"; } ?>">
                                <i class="fas fa-percent nav-icon"></i>
                                <p>Tax Summary</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="report_profit_loss.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_profit_loss.php") { echo "active"; } ?>">
                                <i class="fas fa-file-invoice-dollar nav-icon"></i>
                                <p>Profit & Loss</p>
                            </a>
                        </li>

                    <?php } ?>

                    <?php if (lookupUserPermission("module_sales") >= 1) { ?>
                        <li class="nav-item">
                            <a href="report_tickets_unbilled.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_tickets_unbilled.php") { echo "active"; } ?>">
                                <i class="nav-icon fas fa-life-ring"></i>
                                <p>Unbilled Tickets</p>
                            </a>
                        </li>
                    <?php } ?>
                <?php } // End financial reports IF statement ?>


                <li class="nav-header">TECHNICAL</li>
                <?php  if ($config_module_enable_ticketing) { ?>
                    <li class="nav-item">
                        <a href="report_ticket_summary.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_ticket_summary.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Tickets</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="report_ticket_by_client.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_ticket_by_client.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Tickets by Client</p>
                        </a>
                    </li>
                <?php } ?>
                <?php if (lookupUserPermission("module_credential") >= 1) { ?>
                    <li class="nav-item">
                        <a href="report_password_rotation.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_password_rotation.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Password rotation</p>
                        </a>
                    </li>
                <?php } ?>

                <li class="nav-header">OVERVIEWS</li>

                <li class="nav-item">
                    <a href="report_contacts.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_contacts.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-address-book"></i>
                        <p>All Contacts</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="report_assets.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_assets.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-desktop"></i>
                        <p>All Assets</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="report_domains.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_domains.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-globe"></i>
                        <p>All Domains</p>
                    </a>
                </li>

            </ul>

        </nav>
        <!-- /.sidebar-menu -->

        <div class="sidebar-custom mb-3">

        </div>

    </div>
    <!-- /.sidebar -->
</aside>
