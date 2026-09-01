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

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills sidebar-menu flex-column mt-2" data-lte-toggle="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="/agent/user/user_details.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "user_details.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-user"></i>
                        <p>Details</p>
                    </a>
                </li>

               <li class="nav-item mt-2">
                    <a href="/agent/user/user_security.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "user_security.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-shield-alt"></i>
                        <p>Security</p>
                    </a>
                </li>

                <li class="nav-item mt-2">
                    <a href="/agent/user/user_preferences.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "user_preferences.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>Preferences</p>
                    </a>
                </li>

                <li class="nav-item mt-2">
                    <a href="/agent/user/user_activity.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "user_activity.php") { echo "active"; } ?>">
                        <i class="nav-icon fas fa-clock"></i>
                        <p>Activity</p>
                    </a>
                </li>

            </ul>
        </nav>
        <!-- /.sidebar-menu -->

        <div class="mb-3"></div>
        
    </div>
    <!-- /.sidebar -->
</aside>

