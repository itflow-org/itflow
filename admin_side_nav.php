<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-<?php echo nullable_htmlentities($config_theme); ?> d-print-none">

    <a class="brand-link pb-1 mt-1" href="clients.php">
        <p class="h6"><i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i> Back | <strong>Administration</strong></p>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" data-accordion="false">

                <li class="nav-header">ACCESS</li>

                <li class="nav-item">
                    <a href="admin_users.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_users.php") {
                                                                    echo "active";
                                                                } ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Users</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_api.php") {
                                            echo "active";
                                        } ?>" href="admin_api.php">
                        <i class="nav-icon fas fa-key"></i>
                        <p>API Keys</p>
                    </a>
                </li>

                <li class="nav-header mt-3">TAGS & CATEGORIES</li>

                <li class="nav-item">
                    <a href="admin_tags.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_tags.php") {
                                                                    echo "active";
                                                                } ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Tags</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="admin_categories.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_categories.php") {
                                                                        echo "active";
                                                                    } ?>">
                        <i class="nav-icon fas fa-list-ul"></i>
                        <p>Categories</p>
                    </a>
                </li>
                <!-- ---------------------- TODO: Custom Fields ----------------------
        <li class="nav-item">
          <a href="settings_custom_fields.php" class="nav-link <?php //if (basename($_SERVER["PHP_SELF"]) == "settings_custom_fields.php") { echo "active"; } 
                                                                ?>">
            <i class="nav-icon fas fa-th-list"></i>
            <p>Custom Fields</p>
          </a>
        </li>
        -->

                <li class="nav-item">
                    <a href="admin_taxes.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_taxes.php") {
                                                                    echo "active";
                                                                } ?>">
                        <i class="nav-icon fas fa-balance-scale"></i>
                        <p>Taxes</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="admin_account_types.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_account_types.php") {
                                                                            echo "active";
                                                                        } ?>">
                        <i class="nav-icon fas fa-money-bill-wave"></i>
                        <p>Account Types</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="admin_inventory_locations.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_inventory_locations.php") {
                                                                                echo "active";
                                                                            } ?>">
                        <i class="nav-icon fas fa-map-marker-alt"></i>
                        <p>Inventory Locations</p>
                    </a>
                </li>
                
                <li class="nav-header mt-3">TEMPLATES</li>

                <li class="nav-item">
                    <a href="admin_vendor_templates.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_vendor_templates.php") {
                                                                                echo "active";
                                                                            } ?>">
                        <i class="nav-icon fas fa-building"></i>
                        <p>Vendor Templates</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="admin_software_templates.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_software_templates.php") {
                                                                                echo "active";
                                                                            } ?>">
                        <i class="nav-icon fas fa-rocket"></i>
                        <p>License Templates</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="admin_document_templates.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_document_templates.php" || basename($_SERVER["PHP_SELF"]) == "admin_document_template_details.php") {
                                                                                echo "active";
                                                                            } ?>">
                        <i class="nav-icon fas fa-file"></i>
                        <p>Document Templates</p>
                    </a>
                </li>

                <li class="nav-header mt-3">MAINTENANCE</li>

                <li class="nav-item">
                    <a href="admin_mail_queue.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_mail_queue.php" || basename($_SERVER["PHP_SELF"]) == "admin_mail_queue_message_view.php") {
                                                                        echo "active";
                                                                    } ?>">
                        <i class="nav-icon fas fa-mail-bulk"></i>
                        <p>Mail Queue</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="admin_logs.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_logs.php") {
                                                                    echo "active";
                                                                } ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>Audit Logs</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_backup.php") {
                                            echo "active";
                                        } ?>" href="admin_backup.php">
                        <i class="nav-icon fas fa-cloud-upload-alt"></i>
                        <p>Backup</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="admin_debug.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_debug.php") {
                                                                    echo "active";
                                                                } ?>">
                        <i class="nav-icon fa fa-bug"></i>
                        <p>Debug</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_update.php") {
                                            echo "active";
                                        } ?>" href="admin_update.php">
                        <i class="nav-icon fas fa-download"></i>
                        <p>Update</p>
                    </a>
                </li>

                <li class="nav-header"> Other</li>

                <li class="nav-item">
                    <a href="admin_bulk_mail.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_bulk_mail.php") {
                                                                        echo "active";
                                                                    } ?>">
                        <i class="nav-icon fas fa-envelope"></i>
                        <p>Bulk Mail</p>
                    </a>

            </ul>
        </nav>
        <!-- /.sidebar-menu -->

        <div class="mb-3"></div>

    </div>
    <!-- /.sidebar -->
</aside>