<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary d-print-none">

    <a class="pb-1 mt-1 brand-link" href="../<?php echo $config_start_page ?>">
        <p class="h5"><i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i>
            <span class="brand-text ">Back | <strong>Reports</strong>
        </p>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills nav-sidebar flex-column mt-2" data-widget="treeview" data-accordion="false">

                <li class="nav-header">FINANCIAL</li>
                <?php if ($config_module_enable_accounting == 1 && lookupUserPermission("module_financial") >= 1) { ?>
                    <li class="nav-item">
                        <a href="income_summary.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "income_summary.php") { echo "active"; } ?>">
                            <i class="far fa-circle nav-icon"></i>
                            <p>Income</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="income_by_client.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "income_by_client.php") { echo "active"; } ?>">
                            <i class="far fa-user nav-icon"></i>
                            <p>Income By Client</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="recurring_by_client.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "recurring_by_client.php") { echo "active"; } ?>">
                            <i class="fa fa-sync nav-icon"></i>
                            <p>Recurring Income By Client</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="clients_with_balance.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "clients_with_balance.php") { echo "active"; } ?>">
                            <i class="fa fa-exclamation-triangle nav-icon"></i>
                            <p>Clients with a Balance</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="expense_summary.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "expense_summary.php") { echo "active"; } ?>">
                            <i class="far fa-credit-card nav-icon"></i>
                            <p>Expense</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="expense_by_vendor.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "expense_by_vendor.php") { echo "active"; } ?>">
                            <i class="far fa-building nav-icon"></i>
                            <p>Expense By Vendor</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="tax_summary.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "tax_summary.php") { echo "active"; } ?>">
                            <i class="fas fa-percent nav-icon"></i>
                            <p>Tax Summary</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profit_loss.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "profit_loss.php") { echo "active"; } ?>">
                            <i class="fas fa-file-invoice-dollar nav-icon"></i>
                            <p>Profit & Loss</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="tickets_unbilled.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "tickets_unbilled.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Unbilled Tickets</p>
                        </a>
                    </li>

                <?php } // End financial reports IF statement ?>


                <li class="nav-header">TECHNICAL</li>
                <?php  if ($config_module_enable_ticketing && lookupUserPermission("module_support") >= 1) { ?>
                    <li class="nav-item">
                        <a href="ticket_summary.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "ticket_summary.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Tickets</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="ticket_by_client.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "ticket_by_client.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Tickets by Client</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="time_by_tech.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "time_by_tech.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Time by Technician</p>
                        </a>
                    </li>
                <?php } ?>
                <?php if (lookupUserPermission("module_credential") >= 1) { ?>
                    <li class="nav-item">
                        <a href="credential_rotation.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "credential_rotation.php") { echo "active"; } ?>">
                            <i class="nav-icon fas fa-key"></i>
                            <p>Credential rotation</p>
                        </a>
                    </li>
                <?php } ?>

                <?php
                $sql_custom_links = mysqli_query($mysqli, "SELECT * FROM custom_links 
                    WHERE custom_link_location = 5 AND custom_link_archived_at IS NULL
                    ORDER BY custom_link_order ASC, custom_link_name ASC"
                );

                while ($row = mysqli_fetch_array($sql_custom_links)) {
                    $custom_link_name = nullable_htmlentities($row['custom_link_name']);
                    $custom_link_uri = sanitize_url($row['custom_link_uri']);
                    $custom_link_icon = nullable_htmlentities($row['custom_link_icon']);
                    $custom_link_new_tab = intval($row['custom_link_new_tab']);
                    if ($custom_link_new_tab == 1) {
                        $target = "target='_blank' rel='noopener noreferrer'";
                    } else {
                        $target = "";
                    }

                    ?>

                <li class="nav-item">
                    <a href="<?php echo $custom_link_uri; ?>" <?php echo $target; ?> class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == basename($custom_link_uri)) { echo "active"; } ?>">
                        <i class="fas fa-<?php echo $custom_link_icon; ?> nav-icon"></i>
                        <p><?php echo $custom_link_name; ?></p>
                        <i class="fas fa-angle-right nav-icon float-right"></i>
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
