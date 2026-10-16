<?php

/*
 * In-app notifications - reading and marking them.
 *
 * Writing them is appNotify() in functions/logging.php. Everything that reads
 * them goes through here: the bell panel in the top bar (js/notifications.js,
 * via agent/ajax.php) and the full page, agent/notifications.php. Both use the
 * same visibility rule, so the badge count, the panel and the page always agree.
 *
 * "Read" is notification_dismissed_at being set. The column predates the read /
 * unread wording; nightly_tasks.php still clears read notifications after 90 days.
 *
 * Values come back RAW, not HTML-escaped. The panel renders them as text, the
 * page escapes them at output.
 */

/*
 * appNotify() sends to every active agent, so a notification about a client's
 * invoice reaches people who cannot open that invoice. These are the types that
 * belong to a module; a user without read access to the module does not see them.
 * Types not listed (mail failures, backups, updates, settings) stay visible to all,
 * as before.
 */
function notificationTypeModules() {
    return [
        'module_sales' => [
            'Invoice', 'Invoice Paid', 'Invoice Viewed', 'Invoice Overdue', 'Invoice Late Charge',
            'Draft Invoices', 'Recurring Sent', 'Recurring Generated',
            'Quote Viewed', 'Quote Accepted', 'Quote Declined', 'Quote File', 'Low Stock',
        ],
        'module_financial' => ['Expense', 'Expense Created'],
        'module_support' => [
            'Ticket', 'Ticket SLA', 'Pending Tickets', 'Feedback', 'Recurring Ticket', 'Project',
            'Domain Expiring', 'Certificate Expiring', 'Asset Warranty Expiring', 'Service Review',
        ],
        'module_client' => ['Share Viewed'],
    ];
}

/**
 * The WHERE conditions for "notifications this user may see": their own, for a
 * client they can access (or no client), and of a type their role can read.
 */
function notificationVisibilitySql() {
    global $session_user_id;

    $sql = 'notification_user_id = ' . intval($session_user_id) . clientScopeSql('notification_client_id');

    foreach (notificationTypeModules() as $module => $types) {
        if (lookupUserPermission($module) < 1) {
            $sql .= " AND notification_type NOT IN ('" . implode("','", array_map('escapeSql', $types)) . "')";
        }
    }

    return $sql;
}

/** Icon and colour for a notification type, matched on its wording. */
function notificationTypeStyle($type) {
    $t = strtolower((string) $type);

    $rules = [
        // [words in the type, icon, tone]
        [['sla'], 'stopwatch', 'danger'],
        [['failed', 'fail'], 'exclamation-circle', 'danger'],
        [['declined'], 'times-circle', 'danger'],
        [['feedback'], 'thumbs-down', 'danger'],
        [['paid'], 'check-circle', 'success'],
        [['accepted'], 'check-circle', 'success'],
        [['overdue', 'late charge'], 'exclamation-triangle', 'warning'],
        [['expiring'], 'hourglass-half', 'warning'],
        [['stock'], 'boxes', 'warning'],
        [['viewed'], 'eye', 'info'],
        [['quote'], 'comment-dollar', 'primary'],
        [['invoice', 'recurring sent', 'recurring generated'], 'file-invoice-dollar', 'primary'],
        [['expense'], 'receipt', 'secondary'],
        [['ticket'], 'life-ring', 'primary'],
        [['project'], 'project-diagram', 'primary'],
        [['service'], 'clipboard-check', 'info'],
        [['mail'], 'envelope', 'danger'],
        [['backup'], 'database', 'secondary'],
        [['master key'], 'key', 'warning'],
        [['update'], 'download', 'info'],
        [['share'], 'share-square', 'info'],
        [['settings'], 'cog', 'secondary'],
    ];

    foreach ($rules as [$words, $icon, $tone]) {
        foreach ($words as $word) {
            if (strpos($t, $word) !== false) {
                return ['icon' => $icon, 'tone' => $tone];
            }
        }
    }

    return ['icon' => 'bell', 'tone' => 'secondary'];
}

/*
 * Where clicking a notification goes. Stored actions are a mix of
 * "/agent/ticket.php?..." and a bare "ticket.php?..." that only worked from
 * pages under /agent/. Only same-site paths are returned - anything with a
 * scheme or host comes back as null, which renders as "no link".
 */
function notificationActionUrl($action) {
    $action = trim((string) $action);

    if ($action === '' || $action === '#' || strtoupper($action) === 'NULL') {
        return null;
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $action) || strpos($action, '//') === 0 || strpos($action, '\\') !== false) {
        return null;
    }
    if ($action[0] !== '/') {
        $action = '/agent/' . $action;
    }

    return $action;
}

function notificationItem($row) {
    $style = notificationTypeStyle($row['notification_type']);

    // Backups, updates and the like share one type for good news and bad;
    // the wording is what says it went wrong
    if (preg_match('/\bfail(ed|ure|s)?\b/i', (string) $row['notification'])) {
        $style['tone'] = 'danger';
    }

    $timestamp = strtotime($row['notification_timestamp']);

    return [
        'id' => intval($row['notification_id']),
        'type' => $row['notification_type'],
        'text' => $row['notification'],
        'url' => notificationActionUrl($row['notification_action']),
        'client' => $row['client_name'] ?: null,
        'client_id' => intval($row['notification_client_id']),
        'unread' => $row['notification_dismissed_at'] === null,
        'time' => $timestamp ? date('c', $timestamp) : null,
        'time_full' => $timestamp ? date('D, M j Y g:i A', $timestamp) : '',
        'icon' => $style['icon'],
        'tone' => $style['tone'],
    ];
}

function notificationUnreadCount() {
    global $mysqli;

    $row = mysqli_fetch_row(mysqli_query(
        $mysqli,
        "SELECT COUNT(*) FROM notifications WHERE " . notificationVisibilitySql() . " AND notification_dismissed_at IS NULL"
    ));

    return intval($row[0]);
}

/** Newest first. $view is 'unread' or 'all'. */
function notificationsFetch($view = 'unread', $limit = 20) {
    global $mysqli;

    $limit = max(1, min(100, intval($limit)));
    $unread_only = $view === 'unread' ? 'AND notification_dismissed_at IS NULL' : '';

    $sql = mysqli_query(
        $mysqli,
        "SELECT notification_id, notification_type, notification, notification_action, notification_timestamp,
            notification_dismissed_at, notification_client_id, client_name
        FROM notifications
        LEFT JOIN clients ON notification_client_id = client_id
        WHERE " . notificationVisibilitySql() . " $unread_only
        ORDER BY notification_id DESC
        LIMIT $limit"
    );

    $items = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $items[] = notificationItem($row);
    }

    return $items;
}

/**
 * Mark the given notifications read (or unread again). Only the current user's
 * own rows are touched, whatever ids are sent. Returns the number changed.
 */
function notificationsMark($ids, $read = true) {
    global $mysqli, $session_user_id;

    $ids = array_values(array_filter(array_map('intval', (array) $ids)));
    if (!$ids) {
        return 0;
    }

    $user_id = intval($session_user_id);
    $id_list = implode(',', $ids);

    if ($read) {
        mysqli_query($mysqli, "UPDATE notifications SET notification_dismissed_at = NOW(), notification_dismissed_by = $user_id
            WHERE notification_user_id = $user_id AND notification_dismissed_at IS NULL AND notification_id IN ($id_list)");
    } else {
        mysqli_query($mysqli, "UPDATE notifications SET notification_dismissed_at = NULL, notification_dismissed_by = NULL
            WHERE notification_user_id = $user_id AND notification_dismissed_at IS NOT NULL AND notification_id IN ($id_list)");
    }

    return mysqli_affected_rows($mysqli);
}

/** Every unread notification of the current user, including any their role hides. */
function notificationsMarkAllRead() {
    global $mysqli, $session_user_id;

    $user_id = intval($session_user_id);

    mysqli_query($mysqli, "UPDATE notifications SET notification_dismissed_at = NOW(), notification_dismissed_by = $user_id
        WHERE notification_user_id = $user_id AND notification_dismissed_at IS NULL");

    return mysqli_affected_rows($mysqli);
}
