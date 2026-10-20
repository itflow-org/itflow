<!-- Main Sidebar Container -->
<aside class="app-sidebar shadow d-print-none" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a class="brand-link" href="/agent/<?= escapeHtml($config_start_page) ?>">
            <i class="fas fa-arrow-left me-2"></i>
            <span class="brand-text h6 mb-0">Back | <strong>Administration</strong></span>
        </a>
    </div>

    <!-- Sidebar -->
    <div class="sidebar-wrapper">
        <!-- Sidebar Menu -->
        <nav>
            <ul class="nav nav-pills sidebar-menu flex-column mt-2" data-lte-toggle="treeview" data-accordion="false">
                <li class="nav-header">ACCESS</li>
                <li class="nav-item">
                    <a href="/admin/users.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "users.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Users</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/roles.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "roles.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-user-shield"></i>
                        <p>Roles</p>
                    </a>
                </li>
                <!-- 2025-12-05 JQ - Hide Permission Modules currently just shows modules
                <li class="nav-item">
                    <a href="/admin/modules.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "modules.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-puzzle-piece"></i>
                        <p>Modules</p>
                    </a>
                </li>
                -->
                <li class="nav-item">
                    <a href="/admin/api_keys.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "api_keys.php") {echo "active";} ?>">
                        <i class="nav-icon fas fa-key"></i>
                        <p>API Keys</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/sign_ins.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'sign_ins.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-sign-in-alt"></i>
                        <p>Sign-ins</p>
                    </a>
                </li>

                <li class="nav-header">LISTS</li>
                <li class="nav-item">
                    <a href="/admin/tags.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'tags.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Tags</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/categories.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-list-ul"></i>
                        <p>Categories</p>
                    </a>
                </li>
                <?php if ($config_module_enable_accounting) { ?>
                    <li class="nav-item">
                        <a href="/admin/tax_rates.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'tax_rates.php' ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-balance-scale"></i>
                            <p>Taxes</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/payment_methods.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'payment_methods.php' ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-hand-holding-usd"></i>
                            <p>Payment Methods</p>
                        </a>
                    </li>
                <?php } ?>
                <?php if ($config_module_enable_ticketing) { ?>
                    <li class="nav-item">
                        <a href="/admin/ticket_statuses.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'ticket_statuses.php' ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-info-circle"></i>
                            <p>Ticket Statuses</p>
                        </a>
                    </li>
                <?php } ?>
                <?php if ($config_module_enable_ticketing) { ?>
                    <li class="nav-item">
                        <a href="/admin/sla.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'sla.php' ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-stopwatch"></i>
                            <p>SLAs</p>
                        </a>
                    </li>
                <?php } ?>

                <li class="nav-header">INTEGRATIONS</li>
                <?php if ($config_module_enable_accounting) { ?>
                    <li class="nav-item">
                        <a href="/admin/payment_providers.php"
                           class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['payment_providers.php', 'saved_payment_methods.php']) ? 'active' : '') ?>">
                            <i class="nav-icon far fa-credit-card"></i>
                            <p>Payment Providers</p>
                        </a>
                    </li>
                <?php } ?>
                <li class="nav-item">
                    <a href="/admin/ai_providers.php"
                    class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['ai_providers.php', 'ai_models.php']) ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-robot"></i>
                        <p>AI Providers</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/custom_links.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'custom_links.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-external-link-alt"></i>
                        <p>Custom Links</p>
                    </a>
                </li>

                <?php if ($config_module_enable_itdoc || $config_module_enable_ticketing) { ?>
                    <li class="nav-header">TEMPLATES</li>
                <?php } ?>
                <?php if ($config_module_enable_ticketing) { ?>
                    <li class="nav-item">
                        <a href="/admin/project_templates.php" class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['project_templates.php', 'project_template.php']) ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-project-diagram"></i>
                            <p>Project Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/ticket_templates.php" class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['ticket_templates.php', 'ticket_template.php']) ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-life-ring"></i>
                            <p>Ticket Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/canned_responses.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'canned_responses.php' ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-comment-dots"></i>
                            <p>Canned Responses</p>
                        </a>
                    </li>
                <?php } ?>
                <?php if ($config_module_enable_itdoc) { ?>
                    <!-- 2025-11-16 JQ - Hide Contracts not yet ready
                    <li class="nav-item">
                        <a href="/admin/contract_templates.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'contract_templates.php' ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-file-contract"></i>
                            <p>
                                <span href="#" class="fas fa-plus-circle right ajax-modal" data-modal-url="/admin/modals/contract_template/contract_template_add.php" data-modal-size="lg"></span>
                                Contract Templates
                            </p>
                        </a>
                    </li>
                    -->
                    <li class="nav-item">
                        <a href="/admin/vendor_templates.php" class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['vendor_templates.php', 'vendor_template.php']) ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Vendor Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/software_templates.php" class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['software_templates.php', 'software_template.php']) ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-box-open"></i>
                            <p>License Templates</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/document_templates.php" class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['document_templates.php', 'document_template.php']) ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>Document Templates</p>
                        </a>
                    </li>
                <?php } ?>

                <li class="nav-header">MAINTENANCE</li>

                <li class="nav-item">
                    <a href="/admin/cron.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'cron.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-clock"></i>
                        <p>Cron</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/mail_queue.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'mail_queue.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-inbox"></i>
                        <p>Mail Queue</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/audit_logs.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>Audit Logs</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/app_logs.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'app_logs.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-clipboard-list"></i>
                        <p>App Logs</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/backup.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-cloud-upload-alt"></i>
                        <p>Backup</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/debug.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'debug.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-bug"></i>
                        <p>Debug</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/update.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'update.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-download"></i>
                        <p>Update</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/starter_content.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'starter_content.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-seedling"></i>
                        <p>Starter Content</p>
                    </a>
                </li>

                <!-- SETTINGS Section - the pages are listed in functions/settings.php, as the overview shows them -->
                <?php
                $settings_nav_pages = settingsPages();
                $settings_nav_current = basename($_SERVER['PHP_SELF']);
                $settings_nav_open = $settings_nav_current === 'settings.php' || in_array($settings_nav_current, array_column($settings_nav_pages, 'url'), true);
                ?>
                <li class="nav-item mt-2 <?= $settings_nav_open ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link">
                        <p>
                            SETTINGS
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/admin/settings.php" class="nav-link <?= $settings_nav_current === 'settings.php' ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-sliders-h"></i>
                                <p>Overview</p>
                            </a>
                        </li>
                        <?php foreach ($settings_nav_pages as $settings_nav_page) {
                            if (!settingsPageAvailable($settings_nav_page)) {
                                continue;
                            } ?>
                            <li class="nav-item">
                                <a href="/admin/<?= escapeHtml($settings_nav_page['url']) ?>" class="nav-link <?= $settings_nav_current === $settings_nav_page['url'] ? 'active' : '' ?>">
                                    <i class="nav-icon fas <?= escapeHtml($settings_nav_page['icon']) ?>"></i>
                                    <p><?= escapeHtml($settings_nav_page['title']) ?></p>
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>

                <?php
                $sql_custom_links = mysqli_query($mysqli, "SELECT custom_link_icon, custom_link_name, custom_link_new_tab, custom_link_uri FROM custom_links
                    WHERE custom_link_location = 4 AND custom_link_archived_at IS NULL
                    ORDER BY custom_link_order ASC, custom_link_name ASC"
                );

                while ($row = mysqli_fetch_assoc($sql_custom_links)) {
                    $custom_link_name = escapeHtml($row['custom_link_name']);
                    $custom_link_uri = escapeUrl($row['custom_link_uri']);
                    $custom_link_icon = escapeHtml($row['custom_link_icon']);
                    $custom_link_new_tab = intval($row['custom_link_new_tab']);
                    if ($custom_link_new_tab == 1) {
                        $target = "target='_blank' rel='noopener noreferrer'";
                    } else {
                        $target = "";
                    }

                    ?>

                <li class="nav-item">
                    <a href="<?= $custom_link_uri ?>" <?= $target ?> class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == basename($custom_link_uri)) { echo "active"; } ?>">
                        <i class="fas fa-<?= $custom_link_icon ?> nav-icon"></i>
                        <p><?= $custom_link_name ?></p>
                        <i class="fas fa-angle-right nav-icon float-end"></i>
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