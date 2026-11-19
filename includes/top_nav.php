<!-- Navbar -->
<nav class="app-header navbar navbar-expand d-print-none" data-bs-theme="dark">

    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-lte-toggle="sidebar" data-enable-remember="TRUE" href="#"><i class="fas fa-bars"></i></a>
        </li>
        <?php
        // Across clients - the contacts, locations and documentation of every client together. Once one is open,
        // agent/includes/client_overview_side_nav.php is the side menu, and inc_client_overview_all.php sets $nav_across_clients.
        if (lookupUserPermission('module_client') >= 1 && lookupUserPermission('module_support') >= 1) {
            $across_docs = intval($config_module_enable_itdoc) === 1; // as each client's menu shows them
            $across_groups = [
                '' => [['Contacts', 'contacts.php', 'fa-address-book', true], ['Locations', 'locations.php', 'fa-map-marker-alt', true]],
                'Infrastructure' => [['Assets', 'assets.php', 'fa-desktop', $across_docs], ['Networks', 'networks.php', 'fa-network-wired', $across_docs], ['Racks', 'racks.php', 'fa-server', $across_docs]],
                'Web & Security' => [['Credentials', 'credentials.php', 'fa-key', $across_docs && lookupUserPermission('module_credential') >= 1], ['Certificates', 'certificates.php', 'fa-lock', $across_docs],
                    ['Domains', 'domains.php', 'fa-globe', $across_docs], ['Shared Links', 'shares.php', 'fa-share-alt', $across_docs]],
                'Documentation' => [['Licenses', 'software.php', 'fa-cube', $across_docs], ['Vendors', 'vendors.php?view=clients', 'fa-building', true], ['Services', 'services.php', 'fa-stream', $across_docs]],
            ];
            $across_here = !empty($nav_across_clients) ? basename($_SERVER['PHP_SELF']) : ''; ?>
            <li class="nav-item dropdown">
                <a class="nav-link<?= $across_here ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"
                    title="Contacts, locations and documentation of every client together" aria-label="Across clients">
                    <i class="fas fa-layer-group" aria-hidden="true"></i>
                    <span class="d-none d-md-inline ms-2 dropdown-toggle">Across clients</span>
                </a>
                <div class="dropdown-menu" data-bs-theme="<?= !empty($user_config_theme_dark) ? 'dark' : 'light' ?>">
                    <h6 class="dropdown-header d-md-none">Across clients</h6>
                    <?php foreach ($across_groups as $across_group => $across_pages) {
                        $across_pages = array_filter($across_pages, function ($page) { return $page[3]; });
                        if (!$across_pages) {
                            continue;
                        }
                        if ($across_group !== '') { ?>
                            <h6 class="dropdown-header text-start text-uppercase small"><?= escapeHtml($across_group) ?></h6>
                        <?php }
                        foreach ($across_pages as [$across_label, $across_url, $across_icon]) {
                            $across_file = strtok($across_url, '?'); // its list, or one of its records (assets.php, asset.php)
                            $across_active = in_array($across_here, [$across_file, preg_replace('/s\.php$/', '.php', $across_file)], true); ?>
                            <a class="dropdown-item<?= $across_active ? ' active' : '' ?>" href="/agent/<?= $across_url ?>"<?= $across_active ? ' aria-current="page"' : '' ?>>
                                <i class="fas fa-fw <?= $across_icon ?> me-2" aria-hidden="true"></i><?= escapeHtml($across_label) ?>
                            </a>
                        <?php }
                    } ?>
                </div>
            </li>
        <?php } ?>
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

        <?php if (devModeOn()) { ?>
            <!-- Development mode - a test copy, where nothing reaches clients or other systems (functions/dev_mode.php) -->
            <li class="nav-item d-flex align-items-center me-2">
                <?php if (!empty($session_is_admin)) { ?>
                    <a href="/admin/backup.php#dev-mode" class="badge text-bg-danger text-decoration-none" title="<?= escapeHtml(devModeSummary()) ?>" data-dev-mode><i class="fas fa-flask me-1" aria-hidden="true"></i>DEVELOPMENT</a>
                <?php } else { ?>
                    <span class="badge text-bg-danger" title="<?= escapeHtml(devModeSummary()) ?>" data-dev-mode><i class="fas fa-flask me-1" aria-hidden="true"></i>DEVELOPMENT</span>
                <?php } ?>
            </li>
        <?php } ?>

        <?php // Your own links (Admin > Custom Links) - an icon each, the name and what it is for on hover ?>
        <?php foreach (customLinksFor(2) as $custom_link) {
            $custom_link_title = $custom_link['custom_link_name'] . (trim((string) $custom_link['custom_link_description']) !== '' ? ' - ' . trim($custom_link['custom_link_description']) : ''); ?>
            <li class="nav-item">
                <a href="<?= escapeHtml($custom_link['href']) ?>" class="nav-link" title="<?= escapeHtml($custom_link_title) ?>" aria-label="<?= escapeHtml($custom_link['custom_link_name']) ?>"<?= intval($custom_link['custom_link_new_tab']) === 1 ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                    <i class="fas fa-<?= escapeHtml($custom_link['icon']) ?>" aria-hidden="true"></i>
                </a>
            </li>
        <?php } ?>

        <?php if (quickTicketCan()) { ?>
            <!-- Quick ticket - the client, the contact and the issue, for a call or a walk-up (functions/quick_ticket.php).
                 On a client's page it starts on that client. -->
            <li class="nav-item">
                <a class="nav-link ajax-modal" href="#" data-modal-url="/agent/modals/ticket/ticket_quick.php<?= intval($_GET['client_id'] ?? 0) ? '?client_id=' . intval($_GET['client_id']) : '' ?>"
                    role="button" title="Quick ticket" aria-label="Quick ticket">
                    <i class="fas fa-plus-circle" aria-hidden="true"></i>
                </a>
            </li>
        <?php } ?>

        <?php if (!empty($config_module_enable_ticketing)) { ?>
            <!-- Ticket timers - the ones running or paused in this browser, so one isn't lost on a ticket
                 you left. js/ticket_timers.js shows it once it finds one. -->
            <li class="nav-item dropdown" id="itflowTimers" hidden>
                <a class="nav-link" href="#" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Ticket timers" aria-label="Ticket timers">
                    <i class="fas fa-stopwatch" aria-hidden="true"></i>
                    <span class="badge rounded-pill navbar-badge" data-timers-badge></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end p-0 itflow-notify-panel itflow-timers-panel" data-bs-theme="<?= $user_config_theme_dark ? 'dark' : 'light' ?>">
                    <div class="itflow-notify-head pb-2 border-bottom">
                        <span class="fw-semibold"><i class="fas fa-stopwatch me-2" aria-hidden="true"></i>Ticket timers</span>
                        <small class="text-body-secondary ms-auto">In this browser</small>
                    </div>
                    <div class="itflow-notify-list" data-timers-list></div>
                </div>
            </li>
        <?php } ?>

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
                <span class="me-md-2" data-bs-theme="light"><?= userAvatar($session_user_id, stripslashes($session_name), $session_avatar, 'sm') ?></span>
                <span class="d-none d-md-inline dropdown-toggle"><?= stripslashes(escapeHtml($session_name)) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <!-- User image -->
                <li class="user-header bg-dark itflow-user-header">
                    <span class="d-inline-block" data-bs-theme="light"><?= userAvatar($session_user_id, stripslashes($session_name), $session_avatar, 'xl') ?></span>
                    <p>
                        <?= stripslashes(escapeHtml($session_name)) ?>
                        <small><?= escapeHtml($session_email) ?> · <?= escapeHtml(stripslashes($session_user_role_display)) ?></small>
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
