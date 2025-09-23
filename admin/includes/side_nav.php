<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-<?php echo nullable_htmlentities($config_theme); ?> d-print-none">
    <a class="brand-link pb-1 mt-1" href="../agent/<?php echo $config_start_page ?>">
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
                    <a href="users.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "users.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Users</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="roles.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "roles.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-user-shield"></i>
                        <p>Roles</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="api_keys.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "api_keys.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-key"></i>
                        <p>API Keys</p>
                    </a>
                </li>
                <li class="nav-header">TAGS & CATEGORIES</li>

                <li class="nav-item">
                    <a href="tag.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'tag.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Tags</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="category.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'category.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-list-ul"></i>
                        <p>Categories</p>
                    </a>
                </li>
                <?php if ($config_module_enable_accounting) { ?>
                    <li class="nav-item">
                        <a href="tax.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'tax.php' ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-balance-scale"></i>
                            <p>Taxes</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="payment_method.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'payment_method.php' ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-hand-holding-usd"></i>
                            <p>Payment Methods</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="payment_provider.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'payment_provider.php' ? 'active' : ''); ?>">
                            <i class="nav-icon far fa-credit-card"></i>
                            <p>Payment Providers</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="saved_payment_method.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'saved_payment_method.php' ? 'active' : ''); ?>">
                            <i class="nav-icon far fa-credit-card"></i>
                            <p>Saved Payments</p>
                        </a>
                    </li>
                <?php } ?>
                    <li class="nav-item">
                        <a href="ai_provider.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'ai_provider.php' ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-robot"></i>
                            <p>AI Providers</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="ai_model.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'ai_model.php' ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-robot"></i>
                            <p>AI Models</p>
                        </a>
                    </li>
                
                <?php if ($config_module_enable_ticketing) { ?>
                    <li class="nav-item">
                        <a href="ticket_status.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'ticket_status.php' ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-info-circle"></i>
                            <p>Ticket Statuses</p>
                        </a>
                    </li>
                <?php } ?>
                <li class="nav-item">
                    <a href="custom_link.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'custom_link.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-external-link-alt"></i>
                        <p>Custom Links</p>
                    </a>
                </li>

                <?php if ($config_module_enable_itdoc) { ?>
                    <li class="nav-header">TEMPLATES</li>

                    <li class="nav-item">
                        <a href="project_template.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['project_template.php', 'project_template_details.php']) ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-project-diagram"></i>
                            <p>Project Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="ticket_template.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['ticket_template.php', 'ticket_template_details.php']) ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Ticket Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="vendor_template.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'vendor_template.php' ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Vendor Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="software_template.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'software_template.php' ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-rocket"></i>
                            <p>License Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="document_template.php" class="nav-link <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['document_template.php', 'document_template_details.php']) ? 'active' : ''); ?>">
                            <i class="nav-icon fas fa-file"></i>
                            <p>Document Templates</p>
                        </a>
                    </li>
                <?php } ?>

                <li class="nav-header">MAINTENANCE</li>

                <li class="nav-item">
                    <a href="mail_queue.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'mail_queue.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-mail-bulk"></i>
                        <p>Mail Queue</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="audit_log.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'audit_log.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>Audit Logs</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="app_log.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'app_log.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>App Logs</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="backup.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-cloud-upload-alt"></i>
                        <p>Backup</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="debug.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'debug.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-bug"></i>
                        <p>Debug</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="update.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'update.php' ? 'active' : ''); ?>">
                        <i class="nav-icon fas fa-download"></i>
                        <p>Update</p>
                    </a>
                </li>

                <!-- SETTINGS Section -->
                <li class="nav-item has-treeview mt-2 <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['settings_company.php', 'settings_localization.php', 'settings_theme.php', 'settings_security.php', 'settings_mail.php', 'settings_notification.php', 'settings_default.php', 'settings_invoice.php', 'settings_quote.php', 'settings_online_payment.php', 'settings_online_payment_clients.php', 'settings_project.php', 'settings_ticket.php', 'settings_ai.php', 'identity_provider.php', 'settings_telemetry.php', 'settings_module.php']) ? 'menu-open' : ''); ?>">
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
                            <a href="settings_notification.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_notification.php' ? 'active' : ''); ?>">
                                <i class="nav-icon far fa-bell"></i>
                                <p>Notifications</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_default.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_default.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-cogs"></i>
                                <p>Defaults</p>
                            </a>
                        </li>
                        <?php if ($config_module_enable_accounting) { ?>
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
                        <?php } ?>
                        <?php if ($config_module_enable_ticketing) { ?>
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
                        <?php } ?>
                        <!-- Currently the only integration is the client portal SSO -->
                        <?php if ($config_client_portal_enable) { ?>
                            <li class="nav-item">
                                <a href="identity_provider.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'identity_provider.php' ? 'active' : ''); ?>">
                                    <i class="nav-icon fas fa-fingerprint"></i>
                                    <p>Identity Provider</p>
                                </a>
                            </li>
                        <?php } ?>
                        <li class="nav-item">
                            <a href="settings_telemetry.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_telemetry.php' ? 'active' : ''); ?>">
                                <i class="nav-icon fas fa-satellite-dish"></i>
                                <p>Telemetry</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings_module.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings_module.php' ? 'active' : ''); ?>">
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
