<?php

/*
 * Admin > SLAs - the response and resolution targets tickets are held to, and
 * when their clocks run.
 *
 * One list with four views: the SLAs in use and the archived ones, and the
 * closure days coming up and gone by - holidays and other days the office is
 * shut, when no clock runs. Each SLA shows its targets as business time and
 * as typed (48 hours of a 9-5 day is six business days), the priorities it is
 * the default for, and what uses it. Beside the list: the default SLA for
 * each ticket priority, the business hours the clocks keep and who is warned,
 * what is on tickets now, and how a ticket comes by its SLA.
 *
 * The engine is functions/sla.php and the handlers admin/post/sla.php. A
 * client has no SLAs of its own: its contract's come first
 * (functions/contract_billing.php), and the defaults here cover the rest.
 */

$show = (string) ($_GET['show'] ?? '');
$show = in_array($show, ['archived', 'days', 'past'], true) ? $show : '';
$on_days = $show === 'days' || $show === 'past';

// Default Column Sortby Filter - SLAs by name; closure days by date, the next one first and the last one gone first
$sort = $on_days ? "holiday_date" : "sla_name";
$order = $show === 'past' ? "DESC" : "ASC";

require_once "includes/inc_all_admin.php";

// filter_header.php fills in canned_date=custom when no range was asked for - not a filter here, so not carried into the page links
if (($_GET['canned_date'] ?? '') === 'custom' && empty($_GET['dtf'])) {
    unset($_GET['canned_date']);
}

$csrf = $_SESSION['csrf_token'];
$today = date('Y-m-d');

// Business hours, who is warned, and whether cron is there to do the warning
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_business_days, config_business_hours_start, config_business_hours_end, config_sla_warning_percent,
    config_sla_notification_email, config_enable_cron, config_cron_last_dispatch_at FROM settings WHERE company_id = 1"));
$business_days = array_values(array_filter(array_map('intval', explode(',', (string) $row['config_business_days'])), function ($day) {
    return $day >= 1 && $day <= 7;
}));
$business_hours_start = substr((string) $row['config_business_hours_start'], 0, 5);
$business_hours_end = substr((string) $row['config_business_hours_end'], 0, 5);
$warning_percent = intval($row['config_sla_warning_percent']);
$notification_email = (string) $row['config_sla_notification_email'];
$day_minutes = slaBusinessDayMinutes();
$weekdays = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

$views = [
    '' => ['label' => 'Active', 'icon' => 'fa-stopwatch', 'title' => 'The SLAs new tickets can get'],
    'archived' => ['label' => 'Archived', 'icon' => 'fa-archive', 'title' => 'Kept on the tickets that have them, not given to new ones'],
    'days' => ['label' => 'Closure days', 'icon' => 'fa-calendar-times', 'title' => 'Holidays and other days the office is shut - today and coming up'],
    'past' => ['label' => 'Past closure days', 'icon' => 'fa-history', 'title' => 'Closure days gone by - kept, as the time on older tickets is counted around them'],
];

// The search is for the side being looked at - SLAs or closure days - and leaves the other side's counts alone
$sla_q = $on_days ? '' : $q;
$day_q = $on_days ? $q : '';
$sla_search = "(sla_name LIKE '%$sla_q%' OR sla_description LIKE '%$sla_q%')";
$day_search = "(holiday_name LIKE '%$day_q%' OR holiday_date LIKE '%$day_q%' OR DATE_FORMAT(holiday_date, '%W %M %e %Y') LIKE '%$day_q%')";

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT
    (SELECT COUNT(*) FROM slas WHERE $sla_search AND sla_archived_at IS NULL),
    (SELECT COUNT(*) FROM slas WHERE $sla_search AND sla_archived_at IS NOT NULL),
    (SELECT COUNT(*) FROM business_holidays WHERE $day_search AND holiday_date >= '$today'),
    (SELECT COUNT(*) FROM business_holidays WHERE $day_search AND holiday_date < '$today')"));
$view_counts = array_combine(array_keys($views), array_map('intval', $counts));

// Only these sort - an unknown column would take the page down
$sortable_columns = $on_days ? [
    'holiday_date' => "holiday_date $order",
    'holiday_name' => "holiday_name $order, holiday_date ASC",
] : [
    'sla_name' => "sla_name $order",
    'sla_response_minutes' => "sla_response_minutes $order, sla_name ASC",
    // The ones that only time the first reply go last either way
    'sla_resolution_minutes' => "sla_resolution_minutes IS NULL, sla_resolution_minutes $order, sla_name ASC",
    'tickets' => "n_tickets $order, sla_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = $on_days ? 'holiday_date' : 'sla_name';
}

if ($on_days) {
    $sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS holiday_id, holiday_date, holiday_name FROM business_holidays
        WHERE $day_search AND holiday_date " . ($show === 'past' ? '<' : '>=') . " '$today'
        ORDER BY {$sortable_columns[$sort]}
        LIMIT $record_from, $record_to");
} else {
    // Tickets: the open ones with the SLA on them now
    $sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS sla_id, sla_name, sla_description, sla_response_minutes, sla_resolution_minutes, sla_archived_at,
            (SELECT COUNT(*) FROM tickets WHERE ticket_sla_id = sla_id AND " . TICKET_OPEN_SQL . ") AS n_tickets
        FROM slas
        WHERE $sla_search AND sla_archived_at IS " . ($show === 'archived' ? 'NOT NULL' : 'NULL') . "
        ORDER BY {$sortable_columns[$sort]}, sla_id ASC
        LIMIT $record_from, $record_to");
}
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$rows = [];
while ($list_row = mysqli_fetch_assoc($sql)) {
    $rows[] = $list_row;
}

// The default SLA for each priority (sla_assignments, client 0), and the other way round - what each SLA is the default for
$defaults = [];
$sql_defaults = mysqli_query($mysqli, "SELECT sla_assignment_priority, sla_assignment_sla_id FROM sla_assignments WHERE sla_assignment_client_id = 0");
while ($default_row = mysqli_fetch_assoc($sql_defaults)) {
    $defaults[$default_row['sla_assignment_priority']] = intval($default_row['sla_assignment_sla_id']);
}
$default_for = [];
foreach (TICKET_PRIORITIES as $priority) {
    if (!empty($defaults[$priority])) {
        $default_for[$defaults[$priority]][] = $priority;
    }
}

// The SLAs the defaults can pick - the active ones, and an archived one a priority still points at, so it shows why tickets get none
$sla_choices = contractSlaChoices(array_values($defaults));
$active_slas = count(array_filter($sla_choices, function ($choice) {
    return empty($choice['sla_archived_at']);
}));

// The contracts and contract templates that name each SLA for a priority
$sla_used = [];
if (!$on_days && $rows && function_exists('contractsReady') && contractsReady()) {
    foreach (['contracts' => 'contract', 'templates' => 'contract_template'] as $kind => $prefix) {
        $columns = array_map(function ($part) use ($prefix) {
            return "{$prefix}_sla_{$part}_id";
        }, CONTRACT_SLA_PRIORITIES);
        try {
            $sql_used = mysqli_query($mysqli, "SELECT " . implode(', ', $columns) . " FROM {$prefix}s WHERE {$prefix}_archived_at IS NULL AND " . implode(' + ', $columns) . " > 0");
            while ($used_row = mysqli_fetch_row($sql_used)) {
                foreach (array_unique(array_filter(array_map('intval', $used_row))) as $used_sla_id) {
                    $sla_used[$used_sla_id][$kind] = ($sla_used[$used_sla_id][$kind] ?? 0) + 1;
                }
            }
        } catch (Throwable $e) {
            // The contracts tables arrive with a database update - before it, nothing names an SLA
        }
    }
}

// On tickets now - the same rules the ticket list filters by, so each count matches the list its link opens
$ticket_scope = clientScopeSql('ticket_client_id');
$now_open = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(ticket_id) FROM tickets WHERE " . TICKET_OPEN_SQL . " AND ticket_sla_id > 0 $ticket_scope"))[0]);
$now_breached = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(ticket_id) FROM tickets WHERE ticket_resolved_at IS NULL AND " . dashboardSlaBreachedSql() . " $ticket_scope"))[0]);
$now_at_risk = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(ticket_id) FROM tickets LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    WHERE " . TICKET_OPEN_SQL . " AND ticket_sla_id > 0 AND COALESCE(ticket_status_pauses_sla, 0) = 0
    AND (ticket_response_sla_alert_stage = 1 OR ticket_resolution_sla_alert_stage = 1) $ticket_scope"))[0]);

// The next day the office is shut, for the business hours card
$next_closure = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT holiday_date, holiday_name, (SELECT COUNT(*) FROM business_holidays WHERE holiday_date >= '$today') AS coming
    FROM business_holidays WHERE holiday_date >= '$today' ORDER BY holiday_date ASC LIMIT 1"));

// Warnings and breaches are sent by the Ticket SLA Monitor job - nothing goes out while cron or the job is off
$alerts_job = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_enabled FROM cron_jobs WHERE cron_job_name = 'ticket_sla' LIMIT 1"));
$alerts_stopped = '';
if (intval($row['config_enable_cron']) !== 1) {
    $alerts_stopped = 'Cron is switched off';
} elseif (empty($row['config_cron_last_dispatch_at']) || time() - strtotime($row['config_cron_last_dispatch_at']) >= 300) {
    $alerts_stopped = "Cron isn't running";
} elseif ($alerts_job && intval($alerts_job['cron_job_enabled']) !== 1) {
    $alerts_stopped = 'The Ticket SLA Monitor job is turned off';
}

// An SLA's targets in a line - "Respond in 4 business hours, resolve in 6 business days"
$targets_text = function ($sla) {
    return 'Respond in ' . formatSlaMinutes($sla['sla_response_minutes'], true)
        . (intval($sla['sla_resolution_minutes'] ?? 0) > 0 ? ', resolve in ' . formatSlaMinutes($sla['sla_resolution_minutes'], true) : '');
};
// A target for the list: how long it is in business time, and under it the hours as typed once that is days
$target_html = function ($minutes) use ($day_minutes) {
    $minutes = intval($minutes);
    $html = '<span class="fw-semibold">' . escapeHtml(ucfirst(formatSlaMinutes($minutes, true))) . '</span>';
    if ($minutes >= ($day_minutes ?: 1440)) {
        $html .= '<div class="small text-body-secondary" title="As it was typed">' . escapeHtml(formatSlaClockMinutes($minutes)) . '</div>';
    }
    return $html;
};
// "32 open tickets, 10 contracts" - what has an SLA on it, and what gives it to new tickets
$used_text = function ($sla) use ($sla_used) {
    $n = [
        'open ticket' => intval($sla['n_tickets']),
        'contract' => intval($sla_used[intval($sla['sla_id'])]['contracts'] ?? 0),
        'contract template' => intval($sla_used[intval($sla['sla_id'])]['templates'] ?? 0),
    ];
    $parts = [];
    foreach (array_filter($n) as $word => $count) {
        $parts[] = number_format($count) . " $word" . ($count === 1 ? '' : 's');
    }
    return $parts;
};
// A closure day's distance - "Today", "Tomorrow", "in 12 days", "3 months ago"
$day_distance = function ($date) use ($today) {
    $days = intval(round((strtotime($date) - strtotime($today)) / 86400));
    if (abs($days) <= 1) {
        return [-1 => 'Yesterday', 0 => 'Today', 1 => 'Tomorrow'][$days];
    }
    if (abs($days) < 14) {
        return $days > 0 ? "in $days days" : abs($days) . ' days ago';
    }
    return timeAgo($date . ' ' . date('H:i:s'));
};

// A heading sorts its own way round first - names A to Z, the shortest target and the most used at the top - then the other
$sort_first = ['sla_name' => 'ASC', 'sla_response_minutes' => 'ASC', 'sla_resolution_minutes' => 'ASC', 'tickets' => 'DESC',
    'holiday_name' => 'ASC', 'holiday_date' => $show === 'past' ? 'DESC' : 'ASC'];
$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon, $sort_first) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . ($sort === $column ? $disp : $sort_first[$column]) . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
// A view keeps the search only on its own side - a name typed for an SLA is no use against closure days
$pill_url = function ($view) use ($on_days) {
    $same_side = in_array($view, ['days', 'past'], true) === $on_days;
    return '?' . http_build_query(array_filter(['q' => $same_side ? ($_GET['q'] ?? '') : '', 'show' => $view]));
};

?>

<div class="row">

    <div class="col-xl-8">

        <div class="card mb-3">
            <div class="card-header bg-dark py-2">
                <h3 class="card-title mt-2"><i class="fas fa-fw fa-stopwatch me-2"></i>SLAs</h3>
                <div class="card-tools">
                    <div class="btn-group">
                        <?php if ($on_days) { ?>
                            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/sla/holiday_add.php" data-sla-new-day>
                                <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Closure Day</span>
                            </button>
                        <?php } else { ?>
                            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/sla/sla_add.php" data-sla-new>
                                <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New SLA</span>
                            </button>
                        <?php } ?>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More"></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <?php if ($on_days) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/sla/holiday_generate.php" data-sla-us-holidays>
                                    <i class="fas fa-fw fa-magic text-secondary me-2"></i>Add US holidays
                                </a>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/sla/sla_add.php">
                                    <i class="fas fa-fw fa-stopwatch text-secondary me-2"></i>New SLA
                                </a>
                            <?php } else { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/sla/holiday_add.php">
                                    <i class="fas fa-fw fa-calendar-times text-secondary me-2"></i>New closure day
                                </a>
                                <a class="dropdown-item" href="ticket_statuses.php" title="Which statuses pause the resolution clock">
                                    <i class="fas fa-fw fa-pause-circle text-secondary me-2"></i>Ticket statuses
                                </a>
                            <?php } ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="/agent/reports/sla_summary.php">
                                <i class="fas fa-fw fa-chart-line text-secondary me-2"></i>SLA Performance report
                            </a>
                            <a class="dropdown-item" href="/agent/reports/sla_by_client.php">
                                <i class="fas fa-fw fa-users text-secondary me-2"></i>SLA by Client report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-header py-3">
                <form autocomplete="off">
                    <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= $show ?>"><?php } ?>
                    <div class="row g-2 align-items-center">
                        <div class="col-md-7">
                            <div class="input-group">
                                <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>"
                                    placeholder="<?= $on_days ? 'Search closure days...' : 'Search SLAs...' ?>" title="<?= $on_days ? 'Name, date, month or day of the week' : 'Name or description' ?>">
                                <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </form>
                <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
                    <?php foreach ($views as $view_key => $view) {
                        if ($view_key === 'days') { ?><span class="vr mx-1 my-1"></span><?php } ?>
                        <a class="nav-link text-nowrap <?= $show === (string) $view_key ? 'active' : '' ?>" href="<?= escapeHtml($pill_url($view_key)) ?>" title="<?= escapeHtml($view['title']) ?>" data-sla-view="<?= $view_key ?>">
                            <i class="fas fa-fw <?= $view['icon'] ?> me-1"></i><?= $view['label'] ?> <span class="badge rounded-pill ms-1"><?= $view_counts[$view_key] ?></span>
                        </a>
                    <?php } ?>
                </nav>
            </div>

            <div class="table-responsive">
                <?php if ($on_days) { ?>

                    <!-- Closure days: holidays and other days the office is shut -->
                    <table class="table table-hover align-middle mb-0" data-sla-days>
                        <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                        <tr>
                            <th class="ps-3"><?= $sort_link('holiday_name', 'Closure day') ?></th>
                            <th class="d-none d-sm-table-cell"><?= $sort_link('holiday_date', 'Date') ?></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $day) {
                            $holiday_id = intval($day['holiday_id']);
                            $date = $day['holiday_date'];
                            $date_text = date('M j, Y', strtotime($date));
                            $weekday = date('l', strtotime($date));
                            // Shut anyway - a day of the week the clocks don't run on
                            $shut_anyway = $business_days && $day_minutes > 0 && !in_array(intval(date('N', strtotime($date))), $business_days, true); ?>
                            <tr data-sla-day="<?= escapeHtml($date) ?>">
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw fa-calendar-times"></i></span>
                                        <div class="min-w-0">
                                            <div class="fw-semibold"><?= escapeHtml($day['holiday_name']) ?></div>
                                            <!-- On a phone the date comes along under the name -->
                                            <div class="d-sm-none small text-body-secondary"><?= $weekday ?>, <?= $date_text ?></div>
                                            <?php if ($shut_anyway) { ?>
                                                <div class="small text-body-secondary"><i class="fas fa-fw fa-info-circle me-1"></i>A <?= $weekday ?> - not one of your business days anyway</div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-sm-table-cell text-nowrap">
                                    <div><?= $date_text ?></div>
                                    <div class="small text-body-secondary"><?= $weekday ?> &middot; <?= escapeHtml($day_distance($date)) ?></div>
                                </td>
                                <td class="text-end pe-3">
                                    <form action="post.php" method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="holiday_id" value="<?= $holiday_id ?>">
                                        <button type="submit" name="delete_holiday" class="btn btn-sm btn-light text-danger confirm-link" title="Remove"
                                            data-confirm-title="Remove <?= escapeHtml($day['holiday_name']) ?>?"
                                            data-confirm-text="<?= $date_text ?> is counted as <?= $shut_anyway ? 'any other ' . $weekday : 'a business day' ?> again, and the targets on every ticket with an SLA that isn't closed yet are worked out again."
                                            data-confirm-button="Remove"><i class="fas fa-fw fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>

                <?php } else { ?>

                    <!-- The SLAs -->
                    <table class="table table-hover align-middle mb-0" data-sla-list>
                        <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                        <tr>
                            <th class="ps-3"><?= $sort_link('sla_name', 'SLA') ?></th>
                            <th class="d-none d-sm-table-cell"><?= $sort_link('sla_response_minutes', 'Response') ?></th>
                            <th class="d-none d-sm-table-cell"><?= $sort_link('sla_resolution_minutes', 'Resolution') ?></th>
                            <th class="d-none d-lg-table-cell"><?= $sort_link('tickets', 'Used by') ?></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $sla) {
                            $sla_id = intval($sla['sla_id']);
                            $name = escapeHtml($sla['sla_name']);
                            $resolution = intval($sla['sla_resolution_minutes']);
                            $used = $used_text($sla);
                            $default_priorities = $default_for[$sla_id] ?? [];
                            $edit_url = "modals/sla/sla_edit.php?id=$sla_id";
                            // What archiving it changes, for the confirmation - the part that differs from one SLA to the next first.
                            // As getTicketSlaId() has it: a default that is archived gives nothing, and a contract's falls back to the default.
                            $contracts_giving = intval($sla_used[$sla_id]['contracts'] ?? 0);
                            $default_priorities_text = implode(' and ', array_filter([implode(', ', array_slice($default_priorities, 0, -1)), end($default_priorities) ?: '']));
                            $gives = array_filter([
                                $default_priorities ? "It is the default for $default_priorities_text tickets, which get no SLA from then on." : '',
                                $contracts_giving ? number_format($contracts_giving) . ' contract' . ($contracts_giving === 1 ? ' gives' : 's give') . ' it - new tickets under ' . ($contracts_giving === 1 ? 'it' : 'them') . ' get the default for their priority instead.' : '',
                            ]);
                            $open_tickets = intval($sla['n_tickets']);
                            $archive_text = ($gives ? implode(' ', $gives) : 'New tickets no longer get it.') . ' '
                                . ($open_tickets ? 'The ' . number_format($open_tickets) . ' open ticket' . ($open_tickets === 1 ? ' on it keeps its' : 's on it keep their') . ' targets.' : 'Tickets that have it keep their targets.'); ?>
                            <tr data-sla-id="<?= $sla_id ?>">
                                <td class="ps-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw fa-stopwatch"></i></span>
                                        <div class="min-w-0">
                                            <a class="ajax-modal fw-semibold text-body" href="#" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name ?>"><?= $name ?></a>
                                            <?php if ($default_priorities && !$sla['sla_archived_at']) { ?>
                                                <span class="badge rounded-pill text-bg-primary ms-1" title="New <?= implode(', ', $default_priorities) ?> tickets get it, unless the client's contract gives another" data-sla-default-for><?= 'Default for ' . implode(', ', $default_priorities) ?></span>
                                            <?php } ?>
                                            <?php if ((string) $sla['sla_description'] !== '') { ?>
                                                <div class="small text-body-secondary"><?= escapeHtml($sla['sla_description']) ?></div>
                                            <?php } ?>
                                            <?php if ($sla['sla_archived_at']) { ?>
                                                <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($sla['sla_archived_at'])) ?><?= $default_priorities ? ' - still the default for ' . implode(', ', $default_priorities) . ', so those tickets get none' : '' ?></div>
                                            <?php } ?>
                                            <!-- On a phone the targets come along under the name -->
                                            <div class="d-sm-none small text-body-secondary mt-1"><?= escapeHtml($targets_text($sla)) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-sm-table-cell text-nowrap"><?= $target_html($sla['sla_response_minutes']) ?></td>
                                <td class="d-none d-sm-table-cell text-nowrap">
                                    <?= $resolution > 0 ? $target_html($resolution) : '<span class="text-body-secondary" title="It only times the first reply">Response only</span>' ?>
                                </td>
                                <td class="d-none d-lg-table-cell small text-nowrap">
                                    <?= $used ? implode('<br>', array_map('escapeHtml', $used)) : '<span class="text-body-secondary">Not used yet</span>' ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="dropdown dropstart">
                                        <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                            <?php if ($sla['sla_archived_at']) { ?>
                                                <a class="dropdown-item" href="post.php?unarchive_sla=<?= $sla_id ?>&amp;csrf_token=<?= $csrf ?>" data-sla-restore><i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore</a>
                                            <?php } else { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_sla=<?= $sla_id ?>&amp;csrf_token=<?= $csrf ?>" data-sla-archive
                                                    data-confirm-title="Archive <?= $name ?>?" data-confirm-text="<?= escapeHtml($archive_text) ?>" data-confirm-button="Archive">
                                                    <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                </a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>

                <?php } ?>
            </div>
            <?php
            $empty_state = $on_days ? [
                'thing' => 'closure days',
                'icon' => 'fa-calendar-times',
                'filtered' => $q !== '',
                'views' => ['show'],
                'view' => $show === 'past' ? [
                    'title' => 'No closure days gone by',
                    'icon' => 'fa-history',
                    'text' => 'A closure day stays on record here once it has passed.',
                    'actions' => [],
                ] : [],
                'title' => 'No closure days coming up',
                'text' => 'Holidays and other days the office is shut. No SLA clock runs on them.',
                'actions' => [
                    ['label' => 'New Closure Day', 'modal' => 'modals/sla/holiday_add.php'],
                    ['label' => 'Add US holidays', 'modal' => 'modals/sla/holiday_generate.php', 'icon' => 'fa-magic'],
                ],
            ] : [
                'thing' => 'SLAs',
                'icon' => 'fa-stopwatch',
                'filtered' => $q !== '',
                'archived' => $show === 'archived',
                'views' => ['show'],
                'text' => 'How long there is to first reply to a ticket, and to resolve it. SLAs are optional - with none, nothing about tickets changes.',
                'actions' => [['label' => 'New SLA', 'modal' => 'modals/sla/sla_add.php']],
            ];
            require_once "../includes/filter_footer.php";
            ?>
        </div>

        <!-- What is on tickets now - each count is the list its link opens -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-life-ring me-2"></i>On tickets now</h3>
            </div>
            <div class="row g-0 text-center" data-sla-now>
                <div class="col-4 p-3 border-end">
                    <div class="fs-4 fw-semibold"><?= number_format($now_open) ?></div>
                    <div class="small text-body-secondary">Open with an SLA</div>
                </div>
                <div class="col-4 p-3 border-end">
                    <?php if ($now_at_risk) { ?>
                        <a class="fs-4 fw-semibold text-warning-emphasis text-decoration-none" href="/agent/tickets.php?sla=at_risk" title="See them"><?= number_format($now_at_risk) ?></a>
                    <?php } else { ?>
                        <div class="fs-4 fw-semibold text-body-tertiary">0</div>
                    <?php } ?>
                    <div class="small text-body-secondary">Close to a target</div>
                </div>
                <div class="col-4 p-3">
                    <?php if ($now_breached) { ?>
                        <a class="fs-4 fw-semibold text-danger-emphasis text-decoration-none" href="/agent/tickets.php?sla=breached" title="See them"><?= number_format($now_breached) ?></a>
                    <?php } else { ?>
                        <div class="fs-4 fw-semibold text-body-tertiary">0</div>
                    <?php } ?>
                    <div class="small text-body-secondary">Missed a target</div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body small text-secondary">
                <div class="row g-3">
                    <p class="col-md-6 mb-0"><i class="fas fa-fw fa-stopwatch me-1"></i><strong>SLAs are optional.</strong> A new ticket gets the SLA its client's contract gives its priority, else the default for the priority. With neither, it has none and nothing about it changes.</p>
                    <p class="col-md-6 mb-0"><i class="fas fa-fw fa-business-time me-1"></i><strong>Targets are business time.</strong> <?= $day_minutes > 0 ? 'Four hours given an hour before the day ends runs out three hours into the next business day - and 24 hours is ' . escapeHtml(formatSlaMinutes(1440, true)) . '.' : 'With no business days picked the clocks run around the clock, so a target is plain time.' ?></p>
                    <p class="col-md-6 mb-0"><i class="fas fa-fw fa-pause-circle me-1"></i><strong>Two clocks.</strong> Response runs until the first reply. Resolution runs until the ticket is resolved, and stops while it is in a status that pauses it, like On Hold - set in <a href="ticket_statuses.php">Ticket Statuses</a>.</p>
                    <p class="col-md-6 mb-0"><i class="fas fa-fw fa-chart-line me-1"></i><strong>How you're doing.</strong> The <a href="/agent/reports/sla_summary.php">SLA Performance</a> and <a href="/agent/reports/sla_by_client.php">SLA by Client</a> reports show how often targets were met.</p>
                </div>
            </div>
        </div>

    </div>

    <div class="col-xl-4">

        <!-- Which SLA a new ticket gets, by its priority -->
        <div class="card card-dark mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-flag me-2"></i>Default SLA by priority</h3>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="card-body">
                    <?php foreach (TICKET_PRIORITIES as $priority) {
                        $selected_sla_id = intval($defaults[$priority] ?? 0);
                        // The priority's own colour, as its badge has in the ticket lists
                        $flag_tone = ticketPriorityColor($priority) === 'dark' ? 'body' : ticketPriorityColor($priority); ?>
                        <div class="input-group mb-2">
                            <span class="input-group-text" style="width: 7rem;"><i class="fa fa-fw fa-flag me-2 text-<?= $flag_tone ?>"></i><?= $priority ?></span>
                            <select class="form-select select2" name="global_sla_<?= strtolower($priority) ?>" aria-label="Default SLA for <?= $priority ?> tickets" data-sla-default="<?= $priority ?>">
                                <option value="0">No SLA</option>
                                <?php foreach ($sla_choices as $choice_id => $choice) { ?>
                                    <option value="<?= $choice_id ?>"<?= $selected_sla_id === $choice_id ? ' selected' : '' ?><?= selectOptionData(['description' => $choice['sla_archived_at'] ? 'Archived - tickets get none' : $targets_text($choice)]) ?>><?= escapeHtml($choice['sla_name']) ?><?= $choice['sla_archived_at'] ? ' (archived)' : '' ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    <?php } ?>
                    <small class="text-secondary d-block">
                        A client's contract comes first, for the priorities it gives an SLA. These cover the rest - clients with no contract in force, and priorities a contract leaves alone.
                        <?= $active_slas ? '' : ' Add an SLA to pick one here.' ?>
                    </small>
                </div>
                <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                    <button type="submit" name="save_sla_assignments" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
                    <span class="small text-secondary">Every ticket not yet closed is looked at again.</span>
                </div>
            </form>
        </div>

        <!-- When the clocks run, and who is warned -->
        <div class="card card-dark mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-business-time me-2"></i>Business hours and alerts</h3>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="card-body">
                    <label class="form-label">Business days</label>
                    <div class="btn-group btn-group-sm w-100 mb-3" role="group" aria-label="Business days">
                        <?php foreach ($weekdays as $day_number => $day_name) { ?>
                            <input type="checkbox" class="btn-check" name="business_days[]" value="<?= $day_number ?>" id="slaBusinessDay<?= $day_number ?>" autocomplete="off"<?= in_array($day_number, $business_days, true) ? ' checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="slaBusinessDay<?= $day_number ?>"><?= $day_name ?></label>
                        <?php } ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Business hours</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                            <input type="time" class="form-control" name="business_hours_start" value="<?= escapeHtml($business_hours_start) ?>" required aria-label="The business day starts">
                            <span class="input-group-text">to</span>
                            <input type="time" class="form-control" name="business_hours_end" value="<?= escapeHtml($business_hours_end) ?>" required aria-label="The business day ends">
                        </div>
                        <small class="text-secondary">
                            SLA clocks only run in these hours, on these days<?= !empty($session_timezone) ? ' - ' . escapeHtml($session_timezone) . ' time' : '' ?>. With no day picked they run around the clock.
                            <?php if ($next_closure) { ?>
                                <a href="?show=days" data-sla-closures><?= number_format($next_closure['coming']) ?> closure day<?= intval($next_closure['coming']) === 1 ? '' : 's' ?></a> <?= intval($next_closure['coming']) === 1 ? 'is' : 'are' ?> coming up - <?= escapeHtml($next_closure['holiday_name']) ?> next, <?= date('M j', strtotime($next_closure['holiday_date'])) ?>.
                            <?php } else { ?>
                                No <a href="?show=days" data-sla-closures>closure days</a> are coming up.
                            <?php } ?>
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Warn at</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                            <input type="number" class="form-control" name="warning_percent" min="1" max="99" step="1" value="<?= $warning_percent ?>" required>
                            <span class="input-group-text">% of the target</span>
                        </div>
                        <small class="text-secondary">A ticket's agent is warned when this much of a target is gone, and told again if it is missed - in ITFlow and by email.</small>
                    </div>

                    <div class="mb-1">
                        <label class="form-label">Also email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                            <input type="email" class="form-control" name="notification_email" maxlength="200" placeholder="dispatch@example.com" value="<?= escapeHtml($notification_email) ?>">
                        </div>
                        <small class="text-secondary">A shared address that gets every warning and missed target too. Leave it empty for none.</small>
                    </div>

                    <?php if ($alerts_stopped !== '' && $active_slas) { ?>
                        <div class="alert alert-warning small py-2 mt-3 mb-0" data-sla-alerts-stopped>
                            <i class="fas fa-fw fa-exclamation-circle me-1"></i><?= $alerts_stopped ?>, so no warnings go out and missed targets aren't recorded until it's back. See <a href="cron.php" class="alert-link">Maintenance &gt; Cron</a>.
                        </div>
                    <?php } ?>
                </div>
                <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                    <button type="submit" name="edit_sla_settings" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
                    <span class="small text-secondary">Targets on tickets not yet closed are worked out again.</span>
                </div>
            </form>
        </div>

    </div>

</div>

<?php
require_once "../includes/footer.php";
