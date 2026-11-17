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

        <?php $cron_problems = !empty($session_is_admin) ? cronHealthProblems() : []; ?>
        <?php if ($cron_problems) { ?>
            <!-- Cron health - admins only, and only while something is wrong (functions/cron_health.php) -->
            <li class="nav-item dropdown" id="itflowCronHealth">
                <a class="nav-link" href="/admin/cron.php" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Cron needs attention"
                    aria-label="Cron needs attention, <?= count($cron_problems) ?> problem<?= count($cron_problems) === 1 ? '' : 's' ?>">
                    <i class="fas fa-heartbeat text-warning" aria-hidden="true"></i>
                    <span class="badge rounded-pill text-bg-danger navbar-badge"><?= count($cron_problems) ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end p-0 itflow-notify-panel" data-bs-theme="<?= $user_config_theme_dark ? 'dark' : 'light' ?>">
                    <div class="itflow-notify-head pb-2 border-bottom">
                        <span class="fw-semibold"><i class="fas fa-heartbeat me-2" aria-hidden="true"></i>Cron needs attention</span>
                    </div>
                    <div class="itflow-notify-list">
                        <?php foreach ($cron_problems as $cron_problem) { ?>
                            <div class="itflow-cron-row small" data-cron-problem="<?= escapeHtml($cron_problem['kind']) ?>">
                                <i class="fas fa-fw <?= in_array($cron_problem['kind'], ['not_running', 'failed'], true) ? 'fa-times-circle text-danger' : 'fa-exclamation-circle text-warning' ?> me-1" aria-hidden="true"></i><?= escapeHtml($cron_problem['text']) ?>
                                <?php if ($cron_problem['detail'] !== '') { ?>
                                    <div class="text-body-secondary text-truncate" title="<?= escapeHtml($cron_problem['detail']) ?>"><?= escapeHtml($cron_problem['detail']) ?></div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <a class="dropdown-item text-center small py-2 border-top" href="/admin/cron.php">Open Maintenance &gt; Cron</a>
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
