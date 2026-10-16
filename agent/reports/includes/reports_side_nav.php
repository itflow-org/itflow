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
        <a class="brand-link" href="/agent/<?= $config_start_page ?>">
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
                $sql_custom_links = mysqli_query($mysqli, "SELECT custom_link_icon, custom_link_name, custom_link_new_tab, custom_link_uri FROM custom_links
                    WHERE custom_link_location = 5 AND custom_link_archived_at IS NULL
                    ORDER BY custom_link_order ASC, custom_link_name ASC"
                );

                if (mysqli_num_rows($sql_custom_links)) { ?>
                    <li class="nav-header">MORE</li>
                <?php }

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

    </div>
    <!-- /.sidebar -->
</aside>
