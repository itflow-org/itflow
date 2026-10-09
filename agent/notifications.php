<?php

/*
 * All notifications - where "View all notifications" in the bell panel lands.
 *
 * Same rows, same rules and same row behaviour as the panel (functions/
 * notifications.php, js/notifications.js), with paging, search, a type filter
 * and a date range on top. Unread by default; "All" includes the read ones,
 * which nightly_tasks.php clears after 90 days.
 */

// Newest first, always - filter_header.php reads this before the include
$sort = "notification_id";
$order = "DESC";

require_once "includes/inc_all.php";

// ?dismissed is the old "Dismissed notifications" link; those are the read ones now
$view = (($_GET['view'] ?? '') === 'all' || isset($_GET['dismissed'])) ? 'all' : 'unread';

$type_filter = trim((string) ($_GET['type'] ?? ''));
$type_query = $type_filter !== '' ? "AND notification_type = '" . escapeSql($type_filter) . "'" : '';
$unread_query = $view === 'unread' ? 'AND notification_dismissed_at IS NULL' : '';

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS notification_id, notification_type, notification, notification_action,
        notification_timestamp, notification_dismissed_at, notification_client_id, client_name
    FROM notifications
    LEFT JOIN clients ON notification_client_id = client_id
    WHERE " . notificationVisibilitySql() . "
    $unread_query
    $type_query
    AND (notification_type LIKE '%$q%' OR notification LIKE '%$q%' OR client_name LIKE '%$q%')
    AND DATE(notification_timestamp) BETWEEN '$dtf' AND '$dtt'
    ORDER BY notification_id DESC
    LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$unread_count = notificationUnreadCount();

$sql_types = mysqli_query($mysqli, "SELECT DISTINCT notification_type FROM notifications WHERE " . notificationVisibilitySql() . " ORDER BY notification_type");

// Links that keep every other filter and change one thing
function notificationsPageUrl($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page'], $params['dismissed']);
    $params = array_filter($params, function ($value) {
        return $value !== null && $value !== '';
    });
    return 'notifications.php' . ($params ? '?' . http_build_query($params) : '');
}

function notificationsDayLabel($timestamp) {
    $day = date('Y-m-d', $timestamp);
    if ($day === date('Y-m-d')) {
        return 'Today';
    }
    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday';
    }
    return date('l, F j' . (date('Y', $timestamp) === date('Y') ? '' : ', Y'), $timestamp);
}

$notifications_date_filtered = $dtf !== '1970-01-01' || $dtt !== '2099-12-31';
$notifications_filtered = $q !== '' || $type_filter !== '' || $notifications_date_filtered;

// This page draws its own empty state - "all caught up" is not "nothing added yet"
$empty_state_skip = true;

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-bell me-2"></i>Notifications</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-light" data-notify-all data-notify-reload <?= $unread_count ? '' : 'disabled' ?>>
                <i class="fas fa-check-double me-2"></i>Mark all as read
            </button>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="view" value="<?= $view ?>">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?= escapeHtml(stripslashes($q)) ?>" placeholder="Search notifications">
                        <button class="btn btn-primary" title="Search"><i class="fa fa-search"></i></button>
                        <button class="btn btn-secondary" title="More filters" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="type" onchange="this.form.submit()" aria-label="Notification type">
                        <option value="">All types</option>
                        <?php while ($row = mysqli_fetch_assoc($sql_types)) {
                            $type_option = escapeHtml($row['notification_type']); ?>
                            <option <?php if ($type_filter === $row['notification_type']) { echo "selected"; } ?>><?= $type_option ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="btn-group float-end" role="group" aria-label="Show">
                        <a href="<?= escapeHtml(notificationsPageUrl(['view' => null])) ?>" class="btn <?= $view === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>">
                            Unread <span class="badge rounded-pill text-bg-light ms-1" data-notify-count <?= $unread_count ? '' : 'hidden' ?>><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                        </a>
                        <a href="<?= escapeHtml(notificationsPageUrl(['view' => 'all'])) ?>" class="btn <?= $view === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
                    </div>
                </div>
            </div>
            <div class="collapse mt-3 <?php if ($notifications_date_filtered) { echo "show"; } ?>" id="advancedFilter">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date range</label>
                        <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                        <input type="hidden" name="canned_date" id="canned_date" value="<?= escapeHtml($_GET['canned_date'] ?? '') ?>">
                        <input type="hidden" name="dtf" id="dtf" value="<?= escapeHtml($dtf ?? '') ?>">
                        <input type="hidden" name="dtt" id="dtt" value="<?= escapeHtml($dtt ?? '') ?>">
                    </div>
                </div>
            </div>
        </form>
    </div>

    <?php if ($num_rows[0] == 0) { ?>

        <div class="itflow-notify-empty py-5">
            <?php if ($notifications_filtered) { ?>
                <i class="fas fa-filter fa-3x mb-3 d-block" aria-hidden="true"></i>
                <h5>No notifications match the current filters</h5>
                <a href="<?= escapeHtml('notifications.php' . ($view === 'all' ? '?view=all' : '')) ?>" class="btn btn-sm btn-outline-secondary mt-2">
                    <i class="fas fa-times me-2"></i>Clear filters
                </a>
            <?php } elseif ($view === 'unread') { ?>
                <i class="fas fa-check-circle fa-3x mb-3 d-block text-success" aria-hidden="true"></i>
                <h5>You're all caught up</h5>
                <p class="mb-2">Nothing new since you last looked.</p>
                <a href="notifications.php?view=all" class="btn btn-sm btn-outline-secondary">See all notifications</a>
            <?php } else { ?>
                <i class="fas fa-bell-slash fa-3x mb-3 d-block" aria-hidden="true"></i>
                <h5>No notifications yet</h5>
            <?php } ?>
        </div>

    <?php } else { ?>

        <div class="itflow-notify-page" role="list" aria-label="Notifications">
            <?php
            $notification_day = null;
            while ($row = mysqli_fetch_assoc($sql)) {
                $item = notificationItem($row);
                $item_timestamp = strtotime($row['notification_timestamp']);
                $item_day = notificationsDayLabel($item_timestamp);
                $item_tone = in_array($item['tone'], ['primary', 'secondary', 'success', 'danger', 'warning', 'info'], true) ? $item['tone'] : 'secondary';

                if ($item_day !== $notification_day) {
                    $notification_day = $item_day; ?>
                    <div class="itflow-notify-group" role="presentation"><?= escapeHtml($item_day) ?></div>
                <?php } ?>

                <div class="itflow-notify-item <?= $item['unread'] ? 'is-unread' : '' ?>" role="listitem" data-id="<?= $item['id'] ?>">
                    <a class="itflow-notify-link" href="<?= $item['url'] ? escapeHtml($item['url']) : '#' ?>"<?= $item['url'] ? '' : ' data-no-link="1"' ?>>
                        <span class="itflow-notify-icon bg-<?= $item_tone ?>-subtle text-<?= $item_tone ?>-emphasis"><i class="fas fa-fw fa-<?= escapeHtml($item['icon']) ?>" aria-hidden="true"></i></span>
                        <span class="itflow-notify-body">
                            <span class="itflow-notify-top">
                                <span class="itflow-notify-type"><?= escapeHtml($item['type']) ?></span>
                                <time class="itflow-notify-time" datetime="<?= escapeHtml($item['time']) ?>" title="<?= escapeHtml($item['time_full']) ?>"><?= date('g:i A', $item_timestamp) ?></time>
                            </span>
                            <span class="itflow-notify-text"><?= escapeHtml($item['text']) ?></span>
                            <?php if ($item['client']) { ?>
                                <span class="itflow-notify-client"><i class="fas fa-fw fa-building" aria-hidden="true"></i><?= escapeHtml($item['client']) ?></span>
                            <?php } ?>
                        </span>
                    </a>
                    <button type="button" class="btn btn-sm itflow-notify-toggle" title="<?= $item['unread'] ? 'Mark as read' : 'Mark as unread' ?>" aria-label="<?= $item['unread'] ? 'Mark as read' : 'Mark as unread' ?>">
                        <i class="fas fa-fw fa-<?= $item['unread'] ? 'check' : 'undo' ?>" aria-hidden="true"></i>
                    </button>
                </div>

            <?php } ?>
        </div>

    <?php } ?>

    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<?php
require_once "../includes/footer.php";
