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
                <li class="nav-header mt-3">TAGS & CATEGORIES</li>
                <li class="nav-item">
                    <a href="admin_tags.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_tags.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Tags</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_categories.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_categories.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-list-ul"></i>
                        <p>Categories</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_taxes.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_taxes.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-balance-scale"></i>
                        <p>Taxes</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_account_types.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_account_types.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-money-bill-wave"></i>
                        <p>Account Types</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_ticket_statuses.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_ticket_statuses.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-info-circle"></i>
                        <p>Ticket Statuses</p>
                    </a>
                </li>
                <li class="nav-header mt-3">TEMPLATES</li>
                <li class="nav-item">
                    <a href="admin_project_templates.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_project_templates.php" || basename($_SERVER["PHP_SELF"]) == "admin_project_template_details.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-project-diagram"></i>
                        <p>Project Templates</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_ticket_templates.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_ticket_templates.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-life-ring"></i>
                        <p>Ticket Templates</p>
                    </a>
                </li>
                <?php if (basename($_SERVER["PHP_SELF"]) == "admin_ticket_template_details.php") { ?>
                    <li class="nav-item ">
                        
                        <div class="nav-link active"> 
                            <i class="nav-icon fas fa-circle"></i>
                            <p class="nav-child-indent">Details</p>
                        </div>
                    </li>
                <?php } ?>
                <li class="nav-item">
                    <a href="admin_vendor_templates.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_vendor_templates.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-building"></i>
                        <p>Vendor Templates</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_software_templates.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_software_templates.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-rocket"></i>
                        <p>License Templates</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_document_templates.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_document_templates.php" || basename($_SERVER["PHP_SELF"]) == "admin_document_template_details.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-file"></i>
                        <p>Document Templates</p>
                    </a>
                </li>
                <li class="nav-header mt-3">MAINTENANCE</li>
                <li class="nav-item">
                    <a href="admin_mail_queue.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_mail_queue.php" || basename($_SERVER["PHP_SELF"]) == "admin_mail_queue_message_view.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-mail-bulk"></i>
                        <p>Mail Queue</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_logs.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_logs.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>Audit Logs</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_backup.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_backup.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-cloud-upload-alt"></i>
                        <p>Backup</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_debug.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_debug.php") {echo "active";} ?>">
                        <i class="nav-icon fa fa-bug"></i>
                        <p>Debug</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin_update.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_update.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-download"></i>
                        <p>Update</p>
                    </a>
                </li>
                <li class="nav-header">Other</li>
                <li class="nav-item">
                    <a href="admin_bulk_mail.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "admin_bulk_mail.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-envelope"></i>
                        <p>Bulk Mail</p>
                    </a>
                </li>

                <li class="nav-header mt-3">SETTINGS</li>

                <li class="nav-item">
                  <a href="settings_company.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_company.php") { echo "active"; } ?>">
                    <i class="nav-icon fa fa-briefcase"></i>
                    <p>Company Details</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="settings_localization.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_localization.php") { echo "active"; } ?>">
                    <i class="nav-icon fa fa-globe"></i>
                    <p>Localization</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_theme.php") { echo "active"; } ?>"
                    href="settings_theme.php">
                    <i class="nav-icon fa fa-paint-brush"></i>
                    <p>Theme</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_security.php") { echo "active"; } ?>"
                     href="settings_security.php">
                    <i class="nav-icon fas fa-shield-alt"></i>
                    <p>Security</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_mail.php") { echo "active"; } ?>"
                    href="settings_mail.php">
                    <i class="nav-icon far fa-envelope"></i>
                    <p>Mail</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_notifications.php") { echo "active"; } ?>"
                    href="settings_notifications.php">
                    <i class="nav-icon far fa-bell"></i>
                    <p>Notifications</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_defaults.php") { echo "active"; } ?>"
                    href="settings_defaults.php">
                    <i class="nav-icon fas fa-cogs"></i>
                    <p>Defaults</p>
                  </a>
                </li>

                <?php if ($config_module_enable_accounting) { ?>

                <li class="nav-item">
                  <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_invoice.php") { echo "active"; } ?>"
                    href="settings_invoice.php">
                    <i class="nav-icon fas fa-file-invoice"></i>
                    <p>Invoice</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_quote.php") { echo "active"; } ?>"
                    href="settings_quote.php">
                    <i class="nav-icon fas fa-comment-dollar"></i>
                    <p>Quote</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_online_payment.php") { echo "active"; } ?>"
                    href="settings_online_payment.php">
                    <i class="nav-icon far fa-credit-card"></i>
                    <p>Online Payment</p>
                  </a>
                </li>

                <?php } ?>

                <?php if ($config_module_enable_ticketing) { ?>
                <li class="nav-item">
                  <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_ticket.php") { echo "active"; } ?>"
                    href="settings_ticket.php">
                    <i class="nav-icon fas fa-life-ring"></i>
                    <p>Ticket</p>
                  </a>
                </li>
                <?php } ?>

                <li class="nav-item">
                  <a href="settings_ai.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_ai.php") { echo "active"; } ?>">
                    <i class="nav-icon fas fa-robot"></i>
                    <p>AI</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="settings_integrations.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_integrations.php") { echo "active"; } ?>">
                    <i class="nav-icon fas fa-plug"></i>
                    <p>Integrations</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_telemetry.php") { echo "active"; } ?>"
                    href="settings_telemetry.php">
                    <i class="nav-icon fas fa-satellite-dish"></i>
                    <p>Telemetry</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "settings_modules.php") { echo "active"; } ?>"
                     href="settings_modules.php">
                      <i class="nav-icon fas fa-cube"></i>
                      <p>Modules</p>
                  </a>
                </li>

            </ul>
        </nav>
        <!-- /.sidebar-menu -->
        <div class="mb-3"></div>
    </div>
    <!-- /.sidebar -->
</aside>
