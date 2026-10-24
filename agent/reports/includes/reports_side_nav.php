<?php
/*
 * The Reports menu - drawn from reportCatalog() (functions/reports.php), so it
 * lists exactly the reports this user may open, in the same groups as the
 * Reports home page. Custom links placed in Reports come last.
 */
$reports_menu = reportCatalogForUser();
$reports_current = basename($_SERVER['PHP_SELF'], '.php');
?>
<!-- Main Sidebar Container -->
<aside class="app-sidebar shadow d-print-none" data-bs-theme="dark">

    <div class="sidebar-brand">
        <a class="brand-link" href="/agent/<?= escapeHtml($config_start_page) ?>">
            <i class="fas fa-arrow-left me-2"></i>
            <span class="brand-text h5 mb-0">Back | <strong>Reports</strong></span>
        </a>
    </div>

    <!-- Sidebar -->
    <div class="sidebar-wrapper">

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills sidebar-menu flex-column mt-2" data-lte-toggle="treeview" data-accordion="false">

                <li class="nav-item">
                    <a href="/agent/reports/index.php" class="nav-link <?php if ($reports_current === 'index') { echo "active"; } ?>">
                        <i class="nav-icon fas fa-th-large"></i>
                        <p>All Reports</p>
                    </a>
                </li>

                <?php foreach ($reports_menu as $group) { ?>
                    <li class="nav-header"><?= escapeHtml(strtoupper($group['label'])) ?></li>
                    <?php foreach ($group['reports'] as $menu_key => $menu_report) { ?>
                        <li class="nav-item">
                            <a href="/agent/reports/<?= $menu_key ?>.php" class="nav-link <?php if ($reports_current === $menu_key) { echo "active"; } ?>">
                                <i class="nav-icon fas <?= escapeHtml($menu_report['icon']) ?>"></i>
                                <p><?= escapeHtml($menu_report['title']) ?></p>
                            </a>
                        </li>
                    <?php } ?>
                <?php } ?>

                <?php
                // Your own links (Admin > Custom Links)
                $custom_links = customLinksFor(5);
                if ($custom_links) { ?>
                    <li class="nav-header">MORE</li>
                <?php }
                foreach ($custom_links as $custom_link) {
                    echo customLinkNavItemHtml($custom_link);
                } ?>

            </ul>

        </nav>
        <!-- /.sidebar-menu -->

    </div>
    <!-- /.sidebar -->
</aside>
