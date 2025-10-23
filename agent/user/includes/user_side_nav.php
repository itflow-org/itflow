<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-<?php echo nullable_htmlentities($config_theme); ?> d-print-none">

    <a class="brand-link pb-1 mt-1" href="/agent/<?php echo $config_start_page ?>">
        <p class="h5">
            <i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i> 
            <span class="brand-text">
                Back | <strong>Account</strong>
            </span>
        </p>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills nav-sidebar flex-column mt-2" data-widget="treeview" role="menu" data-accordion="false">

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

