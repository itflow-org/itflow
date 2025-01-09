<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-<?php echo nullable_htmlentities($config_theme); ?> navbar-dark">

    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" data-enable-remember="TRUE" href="#"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <!-- Center navbar links -->
    <ul class="navbar-nav ml-auto">

        <!-- SEARCH FORM -->
        <form class="form-inline" action="global_search.php">
            <div class="input-group input-group-sm">
                <input class="form-control form-control-navbar" type="search" placeholder="Search everywhere" name="query"
                    value="<?php if (isset($_GET['query'])) { echo nullable_htmlentities($_GET['query']); } ?>">
                <div class="input-group-append">
                    <button class="btn btn-navbar" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">

        <?php if(CURRENT_DATABASE_VERSION > '1.4.5' ) { // Check DB Version REMOVE on Decemeber 1st 2024 -Johnny ?>

            <!--Custom Nav Link -->
            <?php
            $sql_custom_links = mysqli_query($mysqli, "SELECT * FROM custom_links WHERE custom_link_location = 2 AND custom_link_archived_at IS NULL
                ORDER BY custom_link_order ASC, custom_link_name ASC"
            );

            while ($row = mysqli_fetch_array($sql_custom_links)) {
                $custom_link_name = nullable_htmlentities($row['custom_link_name']);
                $custom_link_uri = nullable_htmlentities($row['custom_link_uri']);
                $custom_link_icon = nullable_htmlentities($row['custom_link_icon']);
                $custom_link_new_tab = intval($row['custom_link_new_tab']);
                if ($custom_link_new_tab == 1) {
                    $target = "target='_blank' rel='noopener noreferrer'";
                } else {
                    $target = "";
                }

                ?>

            <li class="nav-item" title="<?php echo $custom_link_name; ?>">
                <a href="<?php echo $custom_link_uri; ?>" <?php echo $target; ?> class="nav-link">
                    <i class="fas fa-<?php echo $custom_link_icon; ?> nav-icon"></i>
                </a>
            </li>

            <?php } ?>
            <!-- End Custom Nav Links -->

        <?php } // End DB Check ?>

        <!-- New Notifications Dropdown -->
        <?php
        $sql_notifications = mysqli_query($mysqli, "SELECT * FROM notifications 
            LEFT JOIN clients ON notification_client_id = client_id 
            WHERE notification_dismissed_at IS NULL 
            AND notification_user_id = $session_user_id 
            ORDER BY notification_id DESC LIMIT 8"
        );
        ?>

        <?php if ($num_notifications > 0) { ?>
        <li class="nav-item dropdown" title="Notifications">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="fas fa-bell"></i>
                <span class="badge badge-light badge-pill navbar-badge position-absolute" style="top: 1px; right: 3px;"><?php echo $num_notifications; ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-xlg dropdown-menu-right">
                <a href="notifications.php" class="dropdown-item dropdown-header">
                    <i class="fas fa-bell mr-2"></i>
                    <strong><?php echo $num_notifications; ?></strong>
                    Notifications
                </a>
                <div class="dropdown-divider"></div>
                <?php
                while ($row = mysqli_fetch_array($sql_notifications)) {
                    $notification_id = intval($row['notification_id']);
                    $notification_type = nullable_htmlentities($row['notification_type']);
                    $notification = nullable_htmlentities($row['notification']);
                    $notification_action = nullable_htmlentities($row['notification_action']);
                    $notification_timestamp = date('M d g:ia',strtotime($row['notification_timestamp']));
                    $notification_client_id = intval($row['notification_client_id']);
                    if(empty($notification_action)) { $notification_action = "#"; }
                ?>
                <div class="dropdown-item">
                    <a class="text-dark" href="<?php echo $notification_action; ?>">
                        <p class="mb-1">
                            <span class="text-bold"><i
                                    class="fas fa-bullhorn mr-2"></i><?php echo $notification_type; ?></span>
                            <small class="text-muted mt-1 float-right"><?php echo $notification_timestamp; ?></small>
                        </p>
                        <small class="text-secondary"><?php echo $notification; ?></small>
                    </a>
                </div>

                <?php } ?>

                <div class="dropdown-divider"></div>
                <a href="post.php?dismiss_all_notifications"
                    class="dropdown-item dropdown-footer text-secondary text-bold"><i
                        class="fas fa-check-double mr-2"></i>Dismiss all Notifications</a>
            </div>
        </li>
        <?php } else { ?>

        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="false">
                <i class="fas fa-bell"></i>
            </a>
            <div class="dropdown-menu dropdown-menu dropdown-menu-right" style="left: inherit; right: 0px;">
                <span class="dropdown-item dropdown-header">No Notifications</span>
                <div class="dropdown-divider"></div>
                <div class="text-center text-secondary p-3">
                    <i class='far fa-4x fa-bell-slash'></i>
                </div>
                <div class="dropdown-divider"></div>
                <a href="notifications_dismissed.php" class="dropdown-item dropdown-footer">See Dismissed
                    Notifications</a>
            </div>
        </li>

        <?php } ?>
        <!-- End New Notifications Dropdown -->


        <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link" data-toggle="dropdown">
                <?php if (empty($session_avatar)) { ?>
                <i class="fas fa-user"></i>
                <?php }else{ ?>
                <img src="<?php echo "uploads/users/$session_user_id/$session_avatar"; ?>"
                    class="user-image img-circle">
                <?php } ?>
                <span
                    class="d-none d-md-inline dropdown-toggle"><?php echo stripslashes(nullable_htmlentities($session_name)); ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <!-- User image -->
                <li class="user-header bg-gray-dark">
                    <?php if (empty($session_avatar)) { ?>
                    <i class="fas fa-user-circle fa-6x"></i>
                    <?php }else{ ?>

                    <img src="<?php echo "uploads/users/$session_user_id/$session_avatar"; ?>" class="img-circle">
                    <?php } ?>
                    <p>
                        <?php echo stripslashes(nullable_htmlentities($session_name)); ?>
                        <small><?php echo nullable_htmlentities($session_user_role_display); ?></small>
                    </p>
                </li>
                <!-- Menu Footer-->
                <li class="user-footer">
                    <?php if ($session_is_admin) { ?>
                        <a href="admin_user.php" class="btn btn-default btn-block btn-flat mb-2"><i class="fas fa-user-shield mr-2"></i>Administration</a>
                    <?php } ?>
                    <a href="user_details.php" class="btn btn-default btn-flat"><i class="fas fa-user-cog mr-2"></i>Account</a>
                    <a href="post.php?logout" class="btn btn-default btn-flat float-right"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
                </li>
            </ul>
        </li>

    </ul>
</nav>

<?php if ($config_module_enable_ticketing == 1) {
    include_once "top_nav_tickets_modal.php";
    } ?>
<!-- /.navbar -->
