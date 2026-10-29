<?php

/*
 * ITFlow - the app logs, read: what ITFlow itself does and runs into - cron
 * runs, mail going out and coming in, payment providers, the API, the AI
 * providers, requests no handler took - written with logApp()
 * (functions/logging.php). Admin > App Logs lists them (admin/app_logs.php)
 * by level, searched and filtered by category and date, the same way they
 * are exported and cleared (appLogFiltersSql()), each with a quick look
 * (admin/modals/app_log/app_log.php). Nightly keeps them for their own number
 * of days (Admin > Settings > Logs, appLogRetention()).
 */

/** The levels, worst first - each with its label, the word for one, icon and tone. */
function appLogLevels() {
    return [
        'error' => ['label' => 'Errors', 'one' => 'Error', 'icon' => 'fa-times-circle', 'tone' => 'danger', 'title' => 'Something failed'],
        'warning' => ['label' => 'Warnings', 'one' => 'Warning', 'icon' => 'fa-exclamation-triangle', 'tone' => 'warning', 'title' => 'Something to look at, that carried on'],
        'info' => ['label' => 'Info', 'one' => 'Info', 'icon' => 'fa-info-circle', 'tone' => 'info', 'title' => 'What was done - a cron run, mail sent'],
        'debug' => ['label' => 'Debug', 'one' => 'Debug', 'icon' => 'fa-bug', 'tone' => 'secondary', 'title' => 'Detail for tracking a problem down'],
    ];
}

/** A level, as one of appLogLevels() - 'info' for anything else. */
function appLogLevel($level) {
    $level = strtolower((string) $level);
    return isset(appLogLevels()[$level]) ? $level : 'info';
}

/** A level as a soft pill, in its tone. */
function appLogLevelPill($level) {
    $level = appLogLevel($level);
    $style = appLogLevels()[$level];
    $tone = $style['tone'];
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal text-nowrap" data-app-log-level="' . $level . '">'
        . '<i class="fas ' . $style['icon'] . ' me-1"></i>' . $style['one'] . '</span>';
}

/*
 * A category's icon, as the full class - by its name, or the part before a
 * dash (Cron-Mail-Queue is mail, Cron-Domain-Refresher a domain).
 */
function appLogCategoryIcon($category) {
    $icons = [
        'Cron-Email-Parser' => 'fa-inbox', 'Cron-Mail-Queue' => 'fa-paper-plane', 'Cron-Ticket-SLA' => 'fa-stopwatch',
        'Cron-Domain-Refresher' => 'fa-globe', 'Cron-Certificate-Refresher' => 'fa-lock', 'Cron' => 'fa-clock',
        'Stripe' => 'fa-credit-card', 'Mail' => 'fa-envelope', 'API' => 'fa-plug', 'AI' => 'fa-robot', 'Request' => 'fa-exchange-alt',
        'Quote' => 'fa-comment-dollar', 'Invoice' => 'fa-file-invoice', 'Guest' => 'fa-user', 'Backup' => 'fa-cloud-upload-alt',
        'Update' => 'fa-download', LOG_FORWARD_CATEGORY => 'fa-share-square', 'Database' => 'fa-database',
    ];
    $category = (string) $category;
    $icon = $icons[$category] ?? $icons[strtok($category, '-')] ?? 'fa-clipboard-list';
    return "fas $icon";
}

/*
 * The list's views - one a level, and All - key => label, icon, title and
 * the SQL on app_logs that picks them.
 */
function appLogViews() {
    $views = ['' => ['label' => 'All', 'icon' => 'fa-list', 'title' => 'Everything', 'where' => '', 'tone' => '']];
    foreach (appLogLevels() as $level => $style) {
        $views[$level] = ['label' => $style['label'], 'icon' => $style['icon'], 'title' => $style['title'], 'where' => "app_log_type = '$level'", 'tone' => $style['tone']];
    }
    return $views;
}

/*
 * The app logs' filters as SQL on app_logs - from the list page's GET, or
 * the export and clear windows' POST: q (the category or details), category,
 * view (a level, appLogViews()) and dtf / dtt. $summary gets them in words,
 * for an export or the audit log entry for a clear.
 */
function appLogFiltersSql(array $src, &$summary = []) {
    $summary = [];
    $sql = '1 = 1';
    // One value each - anything else (a list in the query string) is no filter
    $src = array_map(function ($value) {
        return is_scalar($value) ? trim((string) $value) : '';
    }, $src);

    $q = $src['q'] ?? '';
    if ($q !== '') {
        $like = escapeSql($q);
        $sql .= " AND (app_log_category LIKE '%$like%' OR app_log_details LIKE '%$like%')";
        $summary['Search'] = $q;
    }
    $category = $src['category'] ?? '';
    if ($category !== '') {
        $sql .= " AND app_log_category = '" . escapeSql($category) . "'";
        $summary['Category'] = $category;
    }
    $views = appLogViews();
    $view = $src['view'] ?? '';
    if (isset($views[$view]) && $views[$view]['where'] !== '') {
        $sql .= " AND {$views[$view]['where']}";
        $summary['Showing'] = $views[$view]['label'];
    }
    // A half-open range on the column itself, so KEY app_log_created_at serves it
    $dtf = $src['dtf'] ?? '';
    $dtt = $src['dtt'] ?? '';
    $dtf = appLogDate($dtf) ? $dtf : '1970-01-01';
    $dtt = appLogDate($dtt) ? $dtt : '2099-12-31';
    if ($dtf !== '1970-01-01' || $dtt !== '2099-12-31') {
        $sql .= " AND app_log_created_at >= '$dtf 00:00:00' AND app_log_created_at < '$dtt' + INTERVAL 1 DAY";
        $range = formatExportDateRange($dtf, $dtt);
        if ($range) {
            $summary['Dated'] = $range;
        }
    }
    return $sql;
}

/** The filters' summary (appLogFiltersSql()) in a phrase - "Errors from Stripe matching "card", 2026-10-01 to 2026-10-19". */
function appLogFiltersText(array $summary) {
    $text = isset($summary['Showing']) ? $summary['Showing'] : 'Entries';
    if (isset($summary['Category'])) {
        $text .= ' from ' . $summary['Category'];
    }
    if (isset($summary['Search'])) {
        $text .= ' matching "' . $summary['Search'] . '"';
    }
    if (isset($summary['Dated'])) {
        $text .= ', ' . $summary['Dated'];
    }
    return $text;
}

/** Is this a real Y-m-d date? */
function appLogDate($value) {
    $date = DateTime::createFromFormat('!Y-m-d', (string) $value);
    return $date && $date->format('Y-m-d') === (string) $value;
}

/** The categories there are, with how many entries each has: name => count. */
function appLogCategories() {
    global $mysqli;
    $categories = [];
    $sql = mysqli_query($mysqli, "SELECT app_log_category, COUNT(*) FROM app_logs WHERE app_log_category IS NOT NULL AND app_log_category <> '' GROUP BY app_log_category ORDER BY app_log_category ASC");
    while ($row = mysqli_fetch_row($sql)) {
        $categories[$row[0]] = intval($row[1]);
    }
    return $categories;
}

/*
 * How many days each log is kept - 'audit' (and API key use) and 'app' - 0
 * for keep forever. Read apart from the settings every page loads, so a
 * page still opens before the update that adds the app logs' own.
 */
function appLogRetention() {
    global $mysqli;
    $row = [];
    try {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_log_retention, config_app_log_retention FROM settings WHERE company_id = 1")) ?: [];
    } catch (Throwable $e) {
        $result = mysqli_query($mysqli, "SELECT config_log_retention FROM settings WHERE company_id = 1");
        $row = $result ? (mysqli_fetch_assoc($result) ?: []) : [];
        $row['config_app_log_retention'] = $row['config_log_retention'] ?? 90;
    }
    return [
        'audit' => max(0, intval($row['config_log_retention'] ?? 90)),
        'app' => max(0, intval($row['config_app_log_retention'] ?? 90)),
    ];
}

/** A retention in words - "90 days", "Forever". */
function appLogRetentionText($days) {
    $days = intval($days);
    if ($days <= 0) {
        return 'Forever';
    }
    if ($days % 365 === 0) {
        return ($days / 365) . ' year' . ($days === 365 ? '' : 's');
    }
    return number_format($days) . ' day' . ($days === 1 ? '' : 's');
}

/** How much a log holds - its entries and the oldest one: ['count', 'oldest']. */
function appLogSize($table) {
    global $mysqli;
    $columns = ['logs' => 'log_created_at', 'app_logs' => 'app_log_created_at'];
    if (!isset($columns[$table])) {
        return ['count' => 0, 'oldest' => null];
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), MIN({$columns[$table]}) FROM $table"));
    return ['count' => intval($row[0] ?? 0), 'oldest' => $row[1] ?? null];
}
