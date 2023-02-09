<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary d-print-none">

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav class="mt-2">

            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" data-accordion="false">

                <li class="nav-item mb-2">
                    <a href="dashboard_financial.php" class="nav-link">
                        <i class="nav-icon fas fa-arrow-left"></i>
                        <p class="h5">Back | <strong>Reports</strong></p>
                    </a>
                </li>

                <?php  if ($session_user_role == 1 || $session_user_role == 3) { ?>
                    <li class="nav-header">FINANCIAL</li>

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
                        <a href="report_tax_summary.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_tax_summary.php") { echo "active"; } ?>">
                            <i class="fas fa-percent nav-icon"></i>
                            <p>Tax Summary</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="report_profit_loss.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "report_profit_loss.php") { echo "active"; } ?>">
                            <i class="fas fa-balance-scale nav-icon"></i>
                            <p>Profit & Loss</p>
                        </a>
                    </li>
                <?php } // End financial reports IF statement ?>

                <?php  if ($session_user_role == 2 || $session_user_role == 3) { ?>
                <li class="nav-header">TECHNICAL</li>
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

                <?php } // End technical reports IF statement ?>

            </ul>

        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
