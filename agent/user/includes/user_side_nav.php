<!-- Main Sidebar Container -->
<aside class="app-sidebar shadow d-print-none" data-bs-theme="dark">

    <div class="sidebar-brand">
        <a class="brand-link" href="/agent/<?= $config_start_page ?>">
            <i class="fas fa-arrow-left me-2"></i>
            <span class="brand-text h5 mb-0">Back | <strong>Account</strong></span>
        </a>
    </div>

    <!-- Sidebar -->
    <div class="sidebar-wrapper">

        <!-- Who is signed in -->
        <div class="d-flex align-items-center gap-2 px-3 pt-3 pb-1 text-white-50 small min-w-0">
            <?= userAvatar($session_user_id, stripslashes($session_name), $session_avatar, 'md') ?>
            <div class="min-w-0">
                <div class="text-white text-truncate"><?= stripslashes(escapeHtml($session_name)) ?></div>
                <div class="text-truncate"><?= escapeHtml($session_email) ?></div>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav>
            <ul class="nav nav-pills sidebar-menu flex-column mt-2" data-lte-toggle="treeview" role="menu" data-accordion="false">
                <?php
                $account_pages = [
                    'user_details.php' => ['fa-id-badge', 'Profile'],
                    'user_security.php' => ['fa-shield-alt', 'Security'],
                    'user_preferences.php' => ['fa-sliders-h', 'Preferences'],
                    'user_activity.php' => ['fa-history', 'Activity'],
                ];
                foreach ($account_pages as $page => [$icon, $label]) { ?>
                    <li class="nav-item">
                        <a href="/agent/user/<?= $page ?>" class="nav-link <?= basename($_SERVER["PHP_SELF"]) === $page ? 'active' : '' ?>">
                            <i class="nav-icon fas <?= $icon ?>"></i>
                            <p><?= $label ?></p>
                        </a>
                    </li>
                <?php } ?>
                <?php if ($session_is_admin) { ?>
                    <li class="nav-header">ADMINISTRATION</li>
                    <li class="nav-item">
                        <a href="/admin/users.php" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Users</p>
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
