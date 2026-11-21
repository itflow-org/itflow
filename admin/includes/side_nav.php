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
                <?php if (gettingStartedReady()) {
                    $nav_getting_started = gettingStartedProgress(); ?>
                    <li class="nav-item">
                        <a href="/admin/getting_started.php" class="nav-link" data-nav-getting-started>
                            <i class="nav-icon fas fa-flag-checkered"></i>
                            <p>
                                Get Started
                                <?php if (!$nav_getting_started['finished']) { ?>
                                    <span class="right badge text-light" title="Steps done or skipped"><?= $nav_getting_started['done'] ?>/<?= $nav_getting_started['total'] ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                <?php } ?>
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

                <li class="nav-header">LISTS & TEMPLATES</li>
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
                    <li class="nav-item">
                        <a href="/admin/labor_costs.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'labor_costs.php' ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-user-tag"></i>
                            <p>Labor Costs</p>
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
                <li class="nav-item">
                    <a href="/admin/agent_banners.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'agent_banners.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-bullhorn"></i>
                        <p>Agent Banners</p>
                    </a>
                </li>
                <?php // Every kind of template, and canned responses, as tabs (adminNavGroups()) ?>
                <?= adminNavGroupItemHtml('templates') ?>

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
                    class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['ai_providers.php', 'ai_models.php', 'ai_usage.php']) ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-robot"></i>
                        <p>AI Providers</p>
                    </a>
                </li>
                <?php // Its setup, and the texts sent and received once it's set up (adminNavGroups()) ?>
                <?= adminNavGroupItemHtml('text') ?>
                <li class="nav-item">
                    <a href="/admin/rmm_providers.php"
                    class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['rmm_providers.php', 'rmm_clients.php']) ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-laptop-medical"></i>
                        <p>RMM Providers</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/custom_links.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'custom_links.php' ? 'active' : '') ?>">
                        <i class="nav-icon fas fa-external-link-alt"></i>
                        <p>Custom Links</p>
                    </a>
                </li>

                <!-- One ITFlow managing others (functions/managed.php) - each side only when config.php turns it on -->
                <?php if (managerEnabled() || managedEnabled()) { ?>
                    <li class="nav-header">MANAGEMENT</li>
                <?php } ?>

                <?php if (managerEnabled()) { ?>
                    <li class="nav-item">
                        <a href="/admin/managed_instances.php" class="nav-link <?= (in_array(basename($_SERVER['PHP_SELF']), ['managed_instances.php', 'managed_instance.php']) ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-network-wired"></i>
                            <p>
                                Managed Instances
                                <?php $nav_managed_offline = managedOfflineCount(); ?>
                                <?php if ($nav_managed_offline) { ?>
                                    <span class="right badge text-bg-danger" title="Not checking in" data-nav-managed-offline><?= $nav_managed_offline ?></span>
                                <?php } ?>
                            </p>
                        </a>
                    </li>
                <?php } ?>
                <?php if (managedEnabled()) { ?>
                    <li class="nav-item">
                        <a href="/admin/managed_by.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'managed_by.php' ? 'active' : '') ?>">
                            <i class="nav-icon fas fa-handshake"></i>
                            <p>Managed By</p>
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
                        <p>
                            Mail Queue
                            <?php $nav_mail_failed = mailQueueSummary()['failed_week']; ?>
                            <?php if ($nav_mail_failed) { ?>
                                <span class="right badge text-bg-danger" title="Failed to send in the last week" data-nav-mail-failed><?= $nav_mail_failed ?></span>
                            <?php } ?>
                        </p>
                    </a>
                </li>
                <?php // Audit logs, sign-ins and app logs, as tabs (adminNavGroups()) ?>
                <?= adminNavGroupItemHtml('logs') ?>
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
                // Your own links (Admin > Custom Links), under a heading of their own
                $custom_links = customLinksFor(4);
                if ($custom_links) { ?>
                    <li class="nav-header">LINKS</li>
                <?php }
                foreach ($custom_links as $custom_link) {
                    echo customLinkNavItemHtml($custom_link);
                } ?>

            </ul>
        </nav>
        <!-- /.sidebar-menu -->
        <div class="mb-3"></div>
    </div>
    <!-- /.sidebar -->
</aside>