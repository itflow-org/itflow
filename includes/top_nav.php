<!-- Navbar -->
<nav class="app-header navbar navbar-expand d-print-none" data-bs-theme="dark">

    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-lte-toggle="sidebar" data-enable-remember="TRUE" href="#"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <!-- SEARCH - js/global_search.js turns this box into the opener for the
         search palette (also on Ctrl+K). Without the script it is still
         a plain form posting to the full results page. -->
    <form class="navbar-search ms-auto" role="search" action="/agent/global_search.php">
        <label for="navbar-search-input" class="visually-hidden">Search everywhere</label>
        <div class="navbar-search-field">
            <input class="form-control" type="search" id="navbar-search-input" name="query"
                placeholder="Search everywhere" autocomplete="off" aria-keyshortcuts="Control+K Meta+K"
                value="<?php if (isset($_GET['query'])) { echo escapeHtml($_GET['query']); } ?>">
            <button class="navbar-search-submit" type="submit" aria-label="Submit search">
                <i class="fas fa-search" aria-hidden="true"></i>
            </button>
            <kbd class="navbar-search-kbd d-none d-lg-inline" aria-hidden="true">Ctrl K</kbd>
        </div>
    </form>

    <!-- Right navbar links -->
    <ul class="navbar-nav ms-auto">

        <!--Custom Nav Link -->
        <?php
        $sql_custom_links = mysqli_query($mysqli, "SELECT custom_link_icon, custom_link_name, custom_link_new_tab, custom_link_uri FROM custom_links WHERE custom_link_location = 2 AND custom_link_archived_at IS NULL
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

        <li class="nav-item" title="<?= $custom_link_name ?>">
            <a href="<?= $custom_link_uri ?>" <?= $target ?> class="nav-link">
                <i class="fas fa-<?= $custom_link_icon ?> nav-icon"></i>
            </a>
        </li>

        <?php } ?>
        <!-- End Custom Nav Links -->

        <!-- Notifications - js/notifications.js fills the panel when the bell is
             opened and keeps the badge current. Without the script the bell is a
             plain link to the full page. The panel follows the page theme, not
             the always-dark navbar it sits in. -->
        <?php $num_notifications = notificationUnreadCount(); ?>

        <li class="nav-item dropdown" id="itflowNotify" data-csrf="<?= $_SESSION['csrf_token'] ?>">
            <a class="nav-link" href="/agent/notifications.php" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                aria-expanded="false" title="Notifications"
                aria-label="Notifications<?= $num_notifications ? ", $num_notifications unread" : '' ?>">
                <i class="fas fa-bell" aria-hidden="true"></i>
                <span class="badge bg-light text-dark rounded-pill navbar-badge" data-notify-badge <?= $num_notifications ? '' : 'hidden' ?>><?= $num_notifications > 99 ? '99+' : $num_notifications ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-end p-0 itflow-notify-panel" data-bs-theme="<?= $user_config_theme_dark ? 'dark' : 'light' ?>"></div>
        </li>

        <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link" data-bs-toggle="dropdown">
                <?php if (empty($session_avatar)) { ?>
                <i class="fas fa-user-circle me-1"></i>
                <?php }else{ ?>
                <img src="<?= "/uploads/users/$session_user_id/$session_avatar" ?>"
                    class="user-image rounded-circle">
                <?php } ?>
                <span class="d-none d-md-inline dropdown-toggle"><?= stripslashes(escapeHtml($session_name)) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <!-- User image -->
                <li class="user-header bg-dark">
                    <?php if (empty($session_avatar)) { ?>
                    <i class="fas fa-user-circle fa-6x"></i>
                    <?php }else{ ?>

                    <img src="<?= "/uploads/users/$session_user_id/$session_avatar" ?>" class="rounded-circle">
                    <?php } ?>
                    <p>
                        <?= stripslashes(escapeHtml($session_name)) ?>
                        <small><?= escapeHtml($session_user_role_display) ?></small>
                    </p>
                </li>
                <!-- Menu Footer-->
                <li class="user-footer bg-light">
                    <?php if ($session_is_admin) { ?>
                        <a href="/admin" class="btn btn-default w-100 btn-flat mb-2"><i class="fas fa-user-shield me-2"></i>Administration</a>
                    <?php } ?>
                    <a href="/agent/user/user_details.php" class="btn btn-default btn-flat"><i class="fas fa-user-cog me-2"></i>Account</a>
                    <a href="/agent/post.php?logout" class="btn btn-default btn-flat float-end"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </li>
            </ul>
        </li>

    </ul>
</nav>
<!-- /.navbar -->
