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
                <!-- ACCESS Section -->
                <li class="nav-header">ACCESS</li>
                <li class="nav-item">
                    <a href="admin_users.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_users.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Users</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_api.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_api.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-key"></i>
                        <p>API Keys</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_bulk_mail.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_bulk_mail.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-paper-plane"></i>
                        <p>Bulk Mail</p>
                    </a>
                </li>

                <!-- TAGS & CATEGORIES Section -->
                <li class="nav-item has-treeview mt-2 <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_tags.php', 'admin_categories.php', 'admin_taxes.php', 'admin_account_types.php', 'admin_ticket_statuses.php']) ? 'menu-open' : ''); ?>">
                    <a href="#" class="nav-link">
                        <p>
                            TAGS & CATEGORIES
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="admin_tags.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_tags.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-tags"></i>
                                <p>Tags</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_categories.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_categories.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-list-ul"></i>
                                <p>Categories</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_taxes.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_taxes.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-balance-scale"></i>
                                <p>Taxes</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_account_types.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_account_types.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-money-bill-wave"></i>
                                <p>Account Types</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_ticket_statuses.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_ticket_statuses.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-info-circle"></i>
                                <p>Ticket Statuses</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- TEMPLATES Section -->
                <li class="nav-item has-treeview mt-2 <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_project_templates.php', 'admin_project_template_details.php', 'admin_ticket_templates.php', 'admin_ticket_template_details.php', 'admin_vendor_templates.php', 'admin_software_templates.php', 'admin_document_templates.php', 'admin_document_template_details.php']) ? 'menu-open' : ''); ?>">
                    <a href="#" class="nav-link">
                        <p>
                            TEMPLATES
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="admin_project_templates.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_project_templates.php', 'admin_project_template_details.php']) ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-project-diagram"></i>
                                <p>Project Templates</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_ticket_templates.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_ticket_templates.php', 'admin_ticket_template_details.php']) ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-life-ring"></i>
                                <p>Ticket Templates</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_vendor_templates.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_vendor_templates.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-building"></i>
                                <p>Vendor Templates</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_software_templates.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_software_templates.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-rocket"></i>
                                <p>License Templates</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_document_templates.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_document_templates.php', 'admin_document_template_details.php']) ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-file"></i>
                                <p>Document Templates</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- MAINTENANCE Section -->
                <li class="nav-item has-treeview mt-2 <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_mail_queue.php', 'admin_mail_queue_message_view.php', 'admin_logs.php', 'admin_backup.php', 'admin_debug.php', 'admin_update.php']) ? 'menu-open' : ''); ?>">
                    <a href="#" class="nav-link">
                        <p>
                            MAINTENANCE
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="admin_mail_queue.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_mail_queue.php', 'admin_mail_queue_message_view.php']) ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-mail-bulk"></i>
                                <p>Mail Queue</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_logs.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_logs.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-history"></i>
                                <p>Audit Logs</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_backup.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_backup.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-cloud-upload-alt"></i>
                                <p>Backup</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_debug.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_debug.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-bug"></i>
                                <p>Debug</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_update.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_update.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-download"></i>
                                <p>Update</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- SETTINGS Section -->
                <li class="nav-item has-treeview mt-2 <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['settings_company.php', 'settings_localization.php', 'settings_theme.php', 'settings_security.php', 'settings_mail.php', 'settings_notifications.php', 'settings_defaults.php', 'settings_invoice.php', 'settings_quote.php', 'settings_online_payment.php', 'settings_project.php', 'settings_ticket.php', 'settings_ai.php', 'settings_integrations.php', 'settings_telemetry.php', 'settings_modules.php']) ? 'menu-open' : ''); ?>">
                    <a href="#" class="nav-link">
                        <p>
                            SETTINGS
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="settings_company.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_company.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fa fa-briefcase"></i>
                                <p>Company Details</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_localization.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_localization.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fa fa-globe"></i>
                                <p>Localization</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_theme.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_theme.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fa fa-paint-brush"></i>
                                <p>Theme</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_security.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_security.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-shield-alt"></i>
                                <p>Security</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_mail.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_mail.php' ? 'active' : ''); ?>">
                                <i class="nav-icon far fa-envelope"></i>
                                <p>Mail</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_notifications.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_notifications.php' ? 'active' : ''); ?>">
                                <i class="nav-icon far fa-bell"></i>
                                <p>Notifications</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_defaults.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_defaults.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-cogs"></i>
                                <p>Defaults</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_invoice.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_invoice.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-file-invoice"></i>
                                <p>Invoice</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_quote.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_quote.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-comment-dollar"></i>
                                <p>Quote</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_online_payment.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_online_payment.php' ? 'active' : ''); ?>">
                                <i class="nav-icon far fa-credit-card"></i>
                                <p>Online Payment</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_project.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_project.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-project-diagram"></i>
                                <p>Project</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_ticket.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_ticket.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-life-ring"></i>
                                <p>Ticket</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_ai.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_ai.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-robot"></i>
                                <p>AI</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_integrations.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_integrations.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-plug"></i>
                                <p>Integrations</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_telemetry.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_telemetry.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-satellite-dish"></i>
                                <p>Telemetry</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_modules.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_modules.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-cube"></i>
                                <p>Modules</p>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
        <div class="mb-3"></div>
    </div>
    <!-- /.sidebar -->
</aside>
