<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-<?php echo nullable_htmlentities($config_theme); ?> d-print-none">
    <a class="brand-link pb-1 mt-1" href="clients.php">
        <p class="h6">
            <i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i>
            <span class="brand-text">
                Back | <strong>Administration</strong>
            </span>
        </p>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar Menu -->
        <nav>
            <ul class="nav nav-pills nav-sidebar flex-column mt-2" data-widget="treeview" data-accordion="false">
                <!-- ACCESS Section -->
                <li class="nav-item">
                    <a href="admin_user.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_user.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Users</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_role.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_role.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-user-shield"></i>
                        <p>Roles</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_api.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_api.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-key"></i>
                        <p>API Keys</p>
                    </a>
                </li>
                <li class="nav-header">TAGS & CATEGORIES</li>

                <li class="nav-item">
                    <a href="admin_tag.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_tag.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Tags</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_category.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_category.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-list-ul"></i>
                        <p>Categories</p>
                    </a>
                </li>
                <?php if ($config_module_enable_accounting) { ?>
                    <li class="nav-item">
                        <a href="admin_tax.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_tax.php' ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-balance-scale"></i>
                            <p>Taxes</p>
                        </a>
                    </li>
                     <li class="nav-item">
                        <a href="admin_payment_provider.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_payment_provider.php' ? 'active' : ''); ?>">
                            <i class="nav-icon far fa-credit-card"></i>
                            <p>Payment Providers</p>
                        </a>
                    </li>
                <?php } ?>
                <?php if ($config_module_enable_ticketing) { ?>
                    <li class="nav-item">
                        <a href="admin_ticket_status.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_ticket_status.php' ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-info-circle"></i>
                            <p>Ticket Statuses</p>
                        </a>
                    </li>
                <?php } ?>
                <li class="nav-item">
                    <a href="admin_custom_link.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_custom_link.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-external-link-alt"></i>
                        <p>Custom Links</p>
                    </a>
                </li>

                <?php if ($config_module_enable_itdoc) { ?>
                    <li class="nav-header">TEMPLATES</li>

                    <li class="nav-item">
                        <a href="admin_project_template.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_project_template.php', 'admin_project_template_details.php']) ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-project-diagram"></i>
                            <p>Project Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin_ticket_template.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_ticket_template.php', 'admin_ticket_template_details.php']) ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Ticket Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin_vendor_template.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_vendor_template.php' ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Vendor Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin_software_template.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_software_template.php' ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-rocket"></i>
                            <p>License Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin_document_template.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_document_template.php', 'admin_document_template_details.php']) ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-file"></i>
                            <p>Document Templates</p>
                        </a>
                    </li>
                <?php } ?>

                <li class="nav-header">MAINTENANCE</li>

                <li class="nav-item">
                    <a href="admin_mail_queue.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_mail_queue.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-mail-bulk"></i>
                        <p>Mail Queue</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_audit_log.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_audit_log.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>Audit Logs</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_app_log.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_app_log.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>App Logs</p>
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

                <!-- SETTINGS Section -->
                <li class="nav-item has-treeview mt-2 <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['admin_settings_company.php', 'admin_settings_localization.php', 'admin_settings_theme.php', 'admin_settings_security.php', 'admin_settings_mail.php', 'admin_settings_notification.php', 'admin_settings_default.php', 'admin_settings_invoice.php', 'admin_settings_quote.php', 'admin_settings_online_payment.php', 'admin_settings_online_payment_clients.php', 'admin_settings_project.php', 'admin_settings_ticket.php', 'admin_settings_ai.php', 'admin_identity_provider.php', 'admin_settings_telemetry.php', 'admin_settings_module.php']) ? 'menu-open' : ''); ?>">
                    <a href="#" class="nav-link">
                        <p>
                            SETTINGS
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="admin_settings_company.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_company.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fa fa-briefcase"></i>
                                <p>Company Details</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_settings_localization.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_localization.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fa fa-globe"></i>
                                <p>Localization</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_settings_theme.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_theme.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fa fa-paint-brush"></i>
                                <p>Theme</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_settings_security.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_security.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-shield-alt"></i>
                                <p>Security</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_settings_mail.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_mail.php' ? 'active' : ''); ?>">
                                <i class="nav-icon far fa-envelope"></i>
                                <p>Mail</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_settings_notification.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_notification.php' ? 'active' : ''); ?>">
                                <i class="nav-icon far fa-bell"></i>
                                <p>Notifications</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_settings_default.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_default.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-cogs"></i>
                                <p>Defaults</p>
                            </a>
                        </li>
                        <?php if ($config_module_enable_accounting) { ?>
                            <li class="nav-item">
                                <a href="admin_settings_invoice.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_invoice.php' ? 'active' : ''); ?>">
                                    <i class="nav-icon fas fa-file-invoice"></i>
                                    <p>Invoice</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="admin_settings_quote.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_quote.php' ? 'active' : ''); ?>">
                                    <i class="nav-icon fas fa-comment-dollar"></i>
                                    <p>Quote</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="admin_settings_online_payment.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_online_payment.php' ? 'active' : ''); ?>">
                                    <i class="nav-icon far fa-credit-card"></i>
                                    <p>Online Payment</p>
                                </a>
                            </li>
                            <?php if ($config_stripe_enable) { ?>
                                <li class="nav-item">
                                    <a href="admin_settings_online_payment_clients.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_online_payment_clients.php' ? 'active' : ''); ?>">
                                        <i class="nav-icon far fa-credit-card"></i>
                                        <p>Payment/Stripe Clients</p>
                                    </a>
                                </li>
                            <?php } ?>
                        <?php } ?>
                        <?php if ($config_module_enable_ticketing) { ?>
                            <li class="nav-item">
                                <a href="admin_settings_project.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_project.php' ? 'active' : ''); ?>">
                                    <i class="nav-icon fas fa-project-diagram"></i>
                                    <p>Project</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="admin_settings_ticket.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_ticket.php' ? 'active' : ''); ?>">
                                    <i class="nav-icon fas fa-life-ring"></i>
                                    <p>Ticket</p>
                                </a>
                            </li>
                        <?php } ?>
                        <li class="nav-item">
                            <a href="admin_settings_ai.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_ai.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-robot"></i>
                                <p>AI</p>
                            </a>
                        </li>
                        <!-- Currently the only integration is the client portal SSO -->
                        <?php if ($config_client_portal_enable) { ?>
                            <li class="nav-item">
                                <a href="admin_identity_provider.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_identity_provider.php' ? 'active' : ''); ?>">
                                    <i class="nav-icon fas fa-fingerprint"></i>
                                    <p>Identity Provider</p>
                                </a>
                            </li>
                        <?php } ?>
                        <li class="nav-item">
                            <a href="admin_settings_telemetry.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_telemetry.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-satellite-dish"></i>
                                <p>Telemetry</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_settings_module.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_settings_module.php' ? 'active' : ''); ?>">
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
