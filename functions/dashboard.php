<?php

/*
 * Dashboard data - agent/dashboard.php.
 *
 * The page renders; everything it shows is worked out here, so each number has
 * one definition. Where a number links somewhere, it is counted the way that
 * page filters, so clicking "4 unassigned" lands on a list of 4 tickets.
 *
 * Two kinds of number:
 *   - point in time ("needs attention", my tickets, receivables, recurring) -
 *     what is true right now, whatever period is picked
 *   - period (income, expenses, profit, the donuts, miles, top clients) - for
 *     the picked period, compared with the same span before it
 *
 * The cash flow chart also looks ahead: from this month on it shows what is
 * expected in and out, worked out from unpaid invoices and the recurring
 * invoices and expenses (dashboardExpectedIncome / dashboardExpectedExpenses).
 *
 * Anything tied to a client goes through clientScopeSql(), so a technician
 * restricted to some clients sees counts for those clients only.
 */

/*
 * ---------- periods ----------
 */

/** The preset periods, plus every year that has data. Key => label. */
function dashboardPeriodOptions() {
    global $mysqli;

    $options = [
        'month' => 'This month',
        '30d' => 'Last 30 days',
        'quarter' => 'This quarter',
        'ytd' => 'Year to date',
    ];

    $sql = mysqli_query($mysqli, "
        SELECT YEAR(payment_date) AS y FROM payments
        UNION SELECT YEAR(revenue_date) FROM revenues
        UNION SELECT YEAR(expense_date) FROM expenses
        UNION SELECT YEAR(invoice_date) FROM invoices
        UNION SELECT YEAR(ticket_created_at) FROM tickets
        ORDER BY y DESC
    ");
    while ($row = mysqli_fetch_assoc($sql)) {
        $year = intval($row['y']);
        if ($year > 1970 && $year <= intval(date('Y'))) {
            $options["year-$year"] = (string) $year;
        }
    }

    return $options;
}

/** $date moved by $months, keeping the day but never spilling into the next month. */
function dashboardShiftMonths($date, $months) {
    $d = new DateTime($date);
    $day = intval($d->format('j'));
    $d->modify('first day of this month')->modify(($months >= 0 ? '+' : '') . $months . ' months');
    $d->setDate(intval($d->format('Y')), intval($d->format('n')), min($day, intval($d->format('t'))));
    return $d->format('Y-m-d');
}

/**
 * Resolve a period key into dates. "To date" periods end today and compare
 * with the same number of days the period before, so day 9 of this month is
 * measured against days 1-9 of last month, not all of it.
 */
function dashboardPeriod($key) {
    $today = date('Y-m-d');
    $current_year = intval(date('Y'));

    // ?year=2025 is how the old dashboard linked to a year
    if (preg_match('/^year-(\d{4})$/', (string) $key, $m) && intval($m[1]) <= $current_year) {
        $year = intval($m[1]);
        return [
            'key' => "year-$year",
            'label' => (string) $year,
            'start' => "$year-01-01",
            'end' => "$year-12-31",
            'prev_start' => ($year - 1) . '-01-01',
            'prev_end' => ($year - 1) . '-12-31',
            'compare' => 'vs ' . ($year - 1),
            'compare_long' => 'compared with ' . ($year - 1),
            'chart_end' => "$year-12-01",
            'chart_label' => (string) $year,
        ];
    }

    switch ($key) {
        case 'month':
            $start = date('Y-m-01');
            $prev_start = dashboardShiftMonths($start, -1);
            $prev_end = dashboardShiftMonths($today, -1);
            $label = 'This month';
            $compare = 'vs last month';
            $compare_long = 'compared with the same days last month';
            break;
        case '30d':
            $start = date('Y-m-d', strtotime('-29 days'));
            $prev_start = date('Y-m-d', strtotime('-59 days'));
            $prev_end = date('Y-m-d', strtotime('-30 days'));
            $label = 'Last 30 days';
            $compare = 'vs prior 30 days';
            $compare_long = 'compared with the 30 days before';
            break;
        case 'quarter':
            $quarter_month = intval((intval(date('n')) - 1) / 3) * 3 + 1;
            $start = sprintf('%d-%02d-01', $current_year, $quarter_month);
            $prev_start = dashboardShiftMonths($start, -3);
            $prev_end = dashboardShiftMonths($today, -3);
            $label = 'This quarter';
            $compare = 'vs last quarter';
            $compare_long = 'compared with the same point last quarter';
            break;
        default:
            $key = 'ytd';
            $start = "$current_year-01-01";
            $prev_start = ($current_year - 1) . '-01-01';
            $prev_end = dashboardShiftMonths($today, -12);
            $label = 'Year to date';
            $compare = 'vs last year';
            $compare_long = 'compared with the same period last year';
    }

    return [
        'key' => $key,
        'label' => $label,
        'start' => $start,
        'end' => $today,
        'prev_start' => $prev_start,
        'prev_end' => $prev_end,
        'compare' => $compare,
        'compare_long' => $compare_long,
        'chart_end' => date('Y-m-01'),
        'chart_label' => 'Last 12 months',
    ];
}

/** The 12 months a monthly chart covers, oldest first, as Y-m. */
function dashboardChartMonths($period) {
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $months[] = substr(dashboardShiftMonths($period['chart_end'], -$i), 0, 7);
    }
    return $months;
}

/** Percentage change, or null when there is nothing to compare against. */
function dashboardChange($current, $previous) {
    if (abs($previous) < 0.005) {
        return null;
    }
    return round(($current - $previous) / abs($previous) * 100);
}

/*
 * ---------- who sees what ----------
 */

function dashboardAccess() {
    global $config_module_enable_ticketing, $config_module_enable_accounting;

    $ticketing = intval($config_module_enable_ticketing) === 1;
    $accounting = intval($config_module_enable_accounting) === 1;

    return [
        'support' => lookupUserPermission('module_support') >= 1,
        'tickets' => $ticketing && lookupUserPermission('module_support') >= 1,
        'sales' => $accounting && lookupUserPermission('module_sales') >= 1,
        'financial' => $accounting && lookupUserPermission('module_financial') >= 1,
    ];
}

function dashboardCount($sql) {
    global $mysqli;
    return intval(mysqli_fetch_row(mysqli_query($mysqli, $sql))[0]);
}

function dashboardSum($sql) {
    global $mysqli;
    return floatval(mysqli_fetch_row(mysqli_query($mysqli, $sql))[0]);
}

/** A ticket that has missed its response or resolution target - the rule tickets.php?sla=breached uses. */
function dashboardSlaBreachedSql() {
    return "(ticket_sla_id > 0 AND (ticket_response_sla_alert_stage = 2 OR ticket_resolution_sla_alert_stage = 2
        OR ticket_response_sla_met = 0 OR ticket_resolution_sla_met = 0))";
}

/** "Today", "Tomorrow", or "Monday, Oct 12" for a date or datetime. */
function dashboardDayLabel($datetime) {
    $day = substr($datetime, 0, 10);
    if ($day === date('Y-m-d')) {
        return 'Today';
    }
    if ($day === date('Y-m-d', strtotime('+1 day'))) {
        return 'Tomorrow';
    }
    return date('l, M j', strtotime($day));
}

/** Seconds as "3h 20m", "45m" or "2h". */
function dashboardDuration($seconds) {
    $minutes = intdiv(max(0, intval($seconds)) + 30, 60);
    $hours = intdiv($minutes, 60);
    $minutes %= 60;
    if (!$hours) {
        return $minutes . 'm';
    }
    return $minutes ? "{$hours}h {$minutes}m" : "{$hours}h";
}

/*
 * ---------- needs attention ----------
 */

/**
 * Things to act on now. Only ones with a count are returned, so the strip is
 * empty on a good day. Each count matches the list its link opens.
 */
function dashboardAttention($access) {
    $items = [];
    $ticket_scope = clientScopeSql('ticket_client_id');

    if ($access['tickets']) {
        // Same rule as tickets.php?sla=breached, on open tickets
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE ticket_resolved_at IS NULL
                AND " . dashboardSlaBreachedSql() . " $ticket_scope"),
            'label' => 'SLA breached', 'icon' => 'stopwatch', 'tone' => 'danger',
            'url' => '/agent/tickets.php?sla=breached',
        ];
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE ticket_resolved_at IS NULL AND ticket_assigned_to = 0 $ticket_scope"),
            'label' => 'Unassigned tickets', 'icon' => 'user-slash', 'tone' => 'warning',
            'url' => '/agent/tickets.php?assigned=unassigned',
        ];
        // The same rule as projects.php?show=overdue
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(project_id) FROM projects WHERE " . projectViews()['overdue']['where'] . ' ' . clientScopeSql('project_client_id')),
            'label' => 'Overdue projects', 'icon' => 'project-diagram', 'tone' => 'danger',
            'url' => '/agent/projects.php?show=overdue',
        ];
    }

    if ($access['sales']) {
        $invoice_scope = clientScopeSql('invoice_client_id');
        $overdue_where = "invoice_status IN ('Sent', 'Viewed', 'Partial') AND invoice_due < CURDATE() $invoice_scope";
        $overdue_count = dashboardCount("SELECT COUNT(invoice_id) FROM invoices WHERE $overdue_where");
        $overdue_amount = dashboardSum("SELECT SUM(invoice_amount) FROM invoices WHERE $overdue_where")
            - dashboardSum("SELECT SUM(payment_amount) FROM payments JOIN invoices ON payment_invoice_id = invoice_id WHERE $overdue_where");
        $items[] = [
            'count' => $overdue_count,
            'label' => 'Overdue invoices', 'icon' => 'file-invoice-dollar', 'tone' => 'danger',
            'detail' => $overdue_count ? dashboardMoney($overdue_amount) . ' outstanding' : null,
            'url' => '/agent/invoices.php?status=Overdue',
        ];
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(invoice_id) FROM invoices WHERE invoice_status = 'Draft' $invoice_scope"),
            'label' => 'Draft invoices', 'icon' => 'pen', 'tone' => 'secondary',
            'url' => '/agent/invoices.php?status=Draft',
        ];
        // The same rule as products.php?type=reorder
        $items[] = [
            'count' => productReorderCount(),
            'label' => 'Stock to reorder', 'icon' => 'shopping-basket', 'tone' => 'warning', 'detail' => 'At or under the reorder level',
            'url' => '/agent/products.php?type=reorder',
        ];
        // Lines on recurring invoices, drafts and quotes still at an old catalog price
        $reprice = productRepriceCount();
        $items[] = [
            'count' => intval($reprice['lines']),
            'label' => 'Lines at an old price', 'icon' => 'tags', 'tone' => 'warning',
            'detail' => $reprice['monthly'] > 0 ? '+' . dashboardMoney($reprice['monthly']) . ' a month on recurring' : ($reprice['lines'] ? 'On recurring invoices, drafts and quotes' : null),
            'url' => '/agent/products_reprice.php',
        ];
        // Hardware sold in the last 90 days that isn't on the client's assets yet
        if ($access['support']) {
            $hardware = productHardwarePending(90);
            $items[] = [
                'count' => $hardware['units'],
                'label' => 'Hardware not on assets', 'icon' => 'laptop', 'tone' => 'info',
                'detail' => $hardware['units'] ? 'Sold on ' . $hardware['invoices'] . ' invoice' . ($hardware['invoices'] === 1 ? '' : 's') . ' in 90 days' : null,
                'url' => '/agent/invoices.php?hardware=1&dtf=' . date('Y-m-d', strtotime('-90 days')) . '&dtt=' . date('Y-m-d'),
            ];
        }
        if ($access['tickets']) {
            $items[] = [
                'count' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE " . billingUnbilledSql() . " $ticket_scope"),
                'label' => 'Resolved, not invoiced', 'icon' => 'receipt', 'tone' => 'info',
                'url' => '/agent/unbilled_time.php',
            ];
        }
        // What we paid for clients to bill on to them, on no invoice yet
        if ($access['financial']) {
            $to_bill = expenseUnbilledCount();
            $items[] = [
                'count' => $to_bill['count'],
                'label' => 'Expenses to bill', 'icon' => 'shopping-cart', 'tone' => 'info',
                'detail' => $to_bill['count'] ? dashboardMoney($to_bill['value']) . ' to bill on' : null,
                'url' => '/agent/expenses.php?show=to_bill',
            ];
            // What agents paid personally and haven't been paid back for
            $owed = expenseToReimburseCount();
            $items[] = [
                'count' => $owed['count'],
                'label' => 'Expenses to reimburse', 'icon' => 'hand-holding-usd', 'tone' => 'warning',
                'detail' => $owed['count'] ? dashboardMoney($owed['total']) . ' owed to ' . ($owed['agents'] === 1 ? '1 agent' : $owed['agents'] . ' agents') : null,
                'url' => '/agent/expenses.php?show=to_reimburse',
            ];
        }
    }

    if ($access['support']) {
        $soon = "BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY";
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(domain_id) FROM domains WHERE domain_archived_at IS NULL AND domain_expire $soon " . clientScopeSql('domain_client_id')),
            'label' => 'Domains expiring', 'icon' => 'globe', 'tone' => 'warning', 'detail' => 'Next 30 days',
            'url' => '/agent/domains.php?expire_days=30&sort=domain_expire&order=ASC',
        ];
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(certificate_id) FROM certificates WHERE certificate_archived_at IS NULL AND " . certificateSoonSql() . ' ' . clientScopeSql('certificate_client_id')),
            'label' => 'Certificates expiring', 'icon' => 'lock', 'tone' => 'warning', 'detail' => 'Next 30 days',
            'url' => '/agent/certificates.php?show=soon&sort=certificate_expire&order=ASC',
        ];
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(software_id) FROM software WHERE software_archived_at IS NULL AND software_expire $soon " . clientScopeSql('software_client_id')),
            'label' => 'Licenses expiring', 'icon' => 'cube', 'tone' => 'warning', 'detail' => 'Next 30 days',
            'url' => '/agent/software.php?status=renewing&sort=software_expire&order=ASC',
        ];
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(asset_id) FROM assets WHERE asset_archived_at IS NULL AND asset_warranty_expire $soon " . clientScopeSql('asset_client_id')),
            'label' => 'Warranties expiring', 'icon' => 'shield-alt', 'tone' => 'warning', 'detail' => 'Next 30 days',
            'url' => '/agent/assets.php?expire_days=30&sort=asset_warranty_expire&order=ASC&show_column[]=Warranty_Expire',
        ];
        // The same rules as services.php?show=attention and ?show=review
        $service_views = serviceViews();
        $service_scope = "FROM services JOIN clients ON client_id = service_client_id WHERE client_archived_at IS NULL " . clientScopeSql('service_client_id');
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(service_id) $service_scope AND " . $service_views['attention']['where']),
            'label' => 'Services needing attention', 'icon' => 'stream', 'tone' => 'danger', 'detail' => 'What they depend on',
            'url' => '/agent/services.php?show=attention',
        ];
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(service_id) $service_scope AND " . $service_views['review']['where']),
            'label' => 'Service reviews due', 'icon' => 'clipboard-check', 'tone' => 'warning', 'detail' => 'Next ' . SERVICE_REVIEW_SOON_DAYS . ' days',
            'url' => '/agent/services.php?show=review&sort=service_review_due&order=ASC',
        ];
    }

    return array_values(array_filter($items, function ($item) {
        return $item['count'] > 0;
    }));
}

/*
 * ---------- my work ----------
 */

/** My open tickets, the ones most in need of me first: breached SLA, then priority, then waiting longest. */
function dashboardMyTickets($limit = 8) {
    global $mysqli, $session_user_id;

    $user_id = intval($session_user_id);
    $where = "ticket_assigned_to = $user_id AND ticket_resolved_at IS NULL " . clientScopeSql('ticket_client_id');

    $sql = mysqli_query($mysqli, "
        SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_client_id,
            ticket_created_at, ticket_updated_at, ticket_status_name, ticket_status_color, client_name,
            " . dashboardSlaBreachedSql() . " AS sla_breached
        FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE $where
        ORDER BY sla_breached DESC, FIELD(ticket_priority, 'Urgent', 'High', 'Medium', 'Low'),
            COALESCE(ticket_updated_at, ticket_created_at) ASC
        LIMIT " . intval($limit)
    );

    $tickets = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $tickets[] = $row;
    }

    return [
        'tickets' => $tickets,
        'total' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $where"),
        'breached' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $where AND " . dashboardSlaBreachedSql()),
    ];
}

/**
 * The next 7 days for me: open tickets scheduled with me, the projects I
 * manage starting or due, and calendar events.
 * The calendar is shared, so every event shows; repeating events are expanded
 * the way the calendar page does it. Soonest first, all-day items at the top
 * of their day.
 */
function dashboardSchedule($days = 7, $limit = 30) {
    global $mysqli, $session_user_id;

    $user_id = intval($session_user_id);
    $from = date('Y-m-d 00:00:00');
    $to = date('Y-m-d 23:59:59', strtotime('+' . ($days - 1) . ' days'));
    $items = [];

    $sql = mysqli_query($mysqli, "
        SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_schedule, ticket_onsite, ticket_client_id, client_name
        FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        WHERE ticket_assigned_to = $user_id AND ticket_resolved_at IS NULL
        AND ticket_schedule BETWEEN '$from' AND '$to' " . clientScopeSql('ticket_client_id')
    );
    while ($row = mysqli_fetch_assoc($sql)) {
        $items[] = [
            'when' => $row['ticket_schedule'],
            'all_day' => false,
            'ongoing' => false,
            'title' => $row['ticket_subject'],
            'ref' => $row['ticket_prefix'] . $row['ticket_number'],
            'detail' => $row['client_name'] ?: '',
            'onsite' => intval($row['ticket_onsite']) === 1,
            'color' => null,
            'url' => '/agent/ticket.php?' . http_build_query(array_filter([
                'client_id' => intval($row['ticket_client_id']) ?: null,
                'ticket_id' => intval($row['ticket_id']),
            ])),
        ];
    }

    // Projects I manage, starting or due - all day
    $sql = mysqli_query($mysqli, "
        SELECT project_id, project_prefix, project_number, project_name, project_start, project_due, project_client_id, client_name
        FROM projects
        LEFT JOIN clients ON project_client_id = client_id
        WHERE project_manager = $user_id AND " . PROJECT_OPEN_SQL . "
        AND (project_due BETWEEN '" . substr($from, 0, 10) . "' AND '" . substr($to, 0, 10) . "' OR project_start BETWEEN '" . substr($from, 0, 10) . "' AND '" . substr($to, 0, 10) . "') " . clientScopeSql('project_client_id')
    );
    while ($row = mysqli_fetch_assoc($sql)) {
        foreach (['project_start' => 'Project starts', 'project_due' => 'Project due'] as $column => $what) {
            if ($row[$column] && $row[$column] >= substr($from, 0, 10) && $row[$column] <= substr($to, 0, 10)) {
                $items[] = [
                    'when' => $row[$column] . ' 00:00:00',
                    'all_day' => true,
                    'ongoing' => false,
                    'title' => $row['project_name'],
                    'ref' => $row['project_prefix'] . $row['project_number'],
                    'detail' => $what . ($row['client_name'] ? ' - ' . $row['client_name'] : ''),
                    'onsite' => false,
                    'color' => null,
                    'url' => '/agent/' . projectUrl($row['project_id'], $row['project_client_id']),
                ];
            }
        }
    }

    // Events I am on or added - and the ones nobody owns (made before events had
    // attendees, or with no agent on them), which are everyone's
    $sql = mysqli_query($mysqli, "
        SELECT event_id, event_title, event_start, event_end, event_all_day, event_repeat, event_repeat_interval, event_repeat_until, event_repeat_skip,
            event_location, client_name, calendar_color, location_name
        FROM calendar_events
        JOIN calendars ON event_calendar_id = calendar_id AND calendar_archived_at IS NULL
        LEFT JOIN clients ON event_client_id = client_id AND event_client_id > 0
        LEFT JOIN locations ON location_id = event_location_id AND event_location_id > 0
        WHERE event_archived_at IS NULL AND event_start <= '$to'
        AND ((event_repeat IS NOT NULL AND event_repeat != '') OR event_start >= '$from' OR event_end >= '$from')
        AND (event_created_by = $user_id
            OR EXISTS (SELECT 1 FROM calendar_event_attendees a WHERE a.attendee_event_id = event_id AND a.attendee_user_id = $user_id)
            OR (event_created_by = 0 AND NOT EXISTS (SELECT 1 FROM calendar_event_attendees a WHERE a.attendee_event_id = event_id AND a.attendee_user_id > 0))) " . clientScopeSql('event_client_id')
    );
    while ($row = mysqli_fetch_assoc($sql)) {
        foreach (expandRecurringEvent($row, $from, $to) as $occurrence) {
            $start = $occurrence['start'];
            $end = $occurrence['end'] ?: $start;
            if ($start > $to || max($start, $end) < $from) {
                continue;
            }
            $items[] = [
                // An event already under way shows on today
                'when' => max($start, $from),
                'all_day' => !empty($row['event_all_day']),
                'ongoing' => $start < $from,
                'title' => $row['event_title'],
                'ref' => '',
                'detail' => implode(' - ', array_filter([$row['client_name'] ?? '', $row['location_name'] ?: $row['event_location']])),
                'onsite' => false,
                'color' => calendarColor($row['calendar_color']),
                'url' => '/agent/calendar.php',
                'modal' => 'modals/calendar/calendar_event.php?id=' . intval($row['event_id']) . '&occurrence=' . substr($start, 0, 10),
            ];
        }
    }

    usort($items, function ($a, $b) {
        $key = function ($item) {
            return substr($item['when'], 0, 10) . (($item['all_day'] || $item['ongoing']) ? '0' : '1') . substr($item['when'], 11);
        };
        return strcmp($key($a), $key($b));
    });

    return ['items' => array_slice($items, 0, $limit), 'total' => count($items)];
}

/**
 * Time I logged on ticket replies this week (Monday on), per day and split by
 * whether the ticket is billable, with last week's total to compare.
 */
function dashboardTimeThisWeek() {
    global $mysqli, $session_user_id;

    $user_id = intval($session_user_id);
    $monday = new DateTime('monday this week');
    $this_monday = $monday->format('Y-m-d');
    $last_monday = (clone $monday)->modify('-1 week')->format('Y-m-d');

    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $day = (clone $monday)->modify("+$i days");
        $days[$day->format('Y-m-d')] = ['label' => $day->format('D'), 'billable' => 0, 'other' => 0];
    }

    $sql = mysqli_query($mysqli, "
        SELECT DATE(ticket_reply_created_at) AS d, ticket_billable AS billable, SUM(TIME_TO_SEC(ticket_reply_time_worked)) AS s
        FROM ticket_replies
        JOIN tickets ON ticket_reply_ticket_id = ticket_id
        WHERE ticket_reply_by = $user_id AND ticket_reply_archived_at IS NULL
        AND ticket_reply_created_at >= '$this_monday'
        GROUP BY d, billable
    ");

    $total = 0;
    $billable = 0;
    while ($row = mysqli_fetch_assoc($sql)) {
        if (!isset($days[$row['d']])) {
            continue;
        }
        $seconds = intval($row['s']);
        $is_billable = intval($row['billable']) === 1;
        $days[$row['d']][$is_billable ? 'billable' : 'other'] += $seconds;
        $total += $seconds;
        $billable += $is_billable ? $seconds : 0;
    }

    return [
        // Hours, for the chart
        'days' => array_map(function ($day) {
            return ['label' => $day['label'], 'billable' => round($day['billable'] / 3600, 2), 'other' => round($day['other'] / 3600, 2)];
        }, array_values($days)),
        'total' => $total,
        'billable' => $billable,
        'last_week' => intval(dashboardSum("SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies
            WHERE ticket_reply_by = $user_id AND ticket_reply_archived_at IS NULL
            AND ticket_reply_created_at >= '$last_monday' AND ticket_reply_created_at < '$this_monday'")),
    ];
}

/**
 * Open tickets per technician, busiest first, with how many are past SLA -
 * for admins balancing the load. Unassigned tickets are counted separately.
 */
function dashboardTeamWorkload($limit = 8) {
    global $mysqli;

    $scope = clientScopeSql('ticket_client_id');
    $sql = mysqli_query($mysqli, "
        SELECT user_id, user_name, COUNT(ticket_id) AS open, SUM(" . dashboardSlaBreachedSql() . ") AS breached
        FROM tickets
        JOIN users ON ticket_assigned_to = user_id
        WHERE ticket_resolved_at IS NULL $scope
        GROUP BY user_id, user_name
        ORDER BY open DESC, user_name
        LIMIT " . intval($limit)
    );

    $techs = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $techs[] = $row;
    }

    return [
        'techs' => $techs,
        'unassigned' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE ticket_resolved_at IS NULL AND ticket_assigned_to = 0 $scope"),
    ];
}

/** Tickets opened and resolved per week, the last 12 weeks (Monday to Sunday). */
function dashboardTicketTrend() {
    global $mysqli;

    $scope = clientScopeSql('ticket_client_id');
    $this_monday = new DateTime('monday this week');
    $first_monday = (clone $this_monday)->modify('-11 weeks')->format('Y-m-d');

    $weeks = [];
    for ($i = 11; $i >= 0; $i--) {
        $monday = (clone $this_monday)->modify("-$i weeks");
        $weeks[$monday->format('Y-m-d')] = ['label' => $monday->format('M j'), 'opened' => 0, 'resolved' => 0];
    }

    foreach (['opened' => 'ticket_created_at', 'resolved' => 'ticket_resolved_at'] as $series => $column) {
        $sql = mysqli_query($mysqli, "SELECT DATE($column) AS d, COUNT(ticket_id) AS n FROM tickets
            WHERE $column >= '$first_monday' $scope GROUP BY DATE($column)");
        while ($row = mysqli_fetch_assoc($sql)) {
            $week = (new DateTime($row['d']))->modify('monday this week')->format('Y-m-d');
            if (isset($weeks[$week])) {
                $weeks[$week][$series] += intval($row['n']);
            }
        }
    }

    return array_values($weeks);
}

/*
 * ---------- money ----------
 */

function dashboardMoney($amount) {
    global $currency_format, $session_company_currency;
    return numfmt_format_currency($currency_format, floatval($amount), $session_company_currency);
}

/**
 * SQL: a revenue that is income - it has a category, and it is not the in
 * side of a transfer between accounts. The transfer handler leaves both
 * halves without a category; one that has been given one since (the demo data
 * files them as "Account Transfer") is still only money moving between
 * accounts. The out side is never spending: it has no vendor.
 */
function dashboardRevenueSql() {
    return "revenue_category_id > 0 AND revenue_id NOT IN (SELECT transfer_revenue_id FROM transfers)";
}

/** Cash basis, as the Income reports: invoice payments plus categorised revenues. */
function dashboardIncome($start, $end) {
    return dashboardSum("SELECT SUM(payment_amount) FROM payments WHERE payment_date BETWEEN '$start' AND '$end'")
        + dashboardSum("SELECT SUM(revenue_amount) FROM revenues WHERE " . dashboardRevenueSql() . " AND revenue_date BETWEEN '$start' AND '$end'");
}

/** Vendor expenses - expenses with no vendor are transfers between accounts. */
function dashboardExpenses($start, $end) {
    return dashboardSum("SELECT SUM(expense_amount) FROM expenses WHERE expense_vendor_id > 0 AND expense_date BETWEEN '$start' AND '$end'");
}

/**
 * Every unpaid invoice with what is still owed on it - the list
 * invoices.php?status=Unpaid shows. Callers keep the rows with balance > 0.
 */
function dashboardOpenInvoicesSql($scope = '') {
    return "SELECT invoice_id, invoice_client_id, invoice_due,
            invoice_amount - COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0) AS balance
        FROM invoices
        WHERE invoice_status IN ('Sent', 'Viewed', 'Partial') $scope";
}

/** The headline money cards. Change is null when the previous period had nothing to compare. */
function dashboardMoneyCards($access, $period) {
    $cards = [];

    if ($access['financial']) {
        $income = dashboardIncome($period['start'], $period['end']);
        $income_prev = dashboardIncome($period['prev_start'], $period['prev_end']);
        $expenses = dashboardExpenses($period['start'], $period['end']);
        $expenses_prev = dashboardExpenses($period['prev_start'], $period['prev_end']);

        $cards[] = ['label' => 'Income', 'value' => $income, 'change' => dashboardChange($income, $income_prev), 'good' => 'up',
            'url' => '/agent/income.php?canned_date=custom&dtf=' . $period['start'] . '&dtt=' . $period['end']];
        $cards[] = ['label' => 'Expenses', 'value' => $expenses, 'change' => dashboardChange($expenses, $expenses_prev), 'good' => 'down',
            'url' => '/agent/expenses.php?canned_date=custom&dtf=' . $period['start'] . '&dtt=' . $period['end']];
        $cards[] = ['label' => 'Profit', 'value' => $income - $expenses, 'change' => dashboardChange($income - $expenses, $income_prev - $expenses_prev), 'good' => 'up',
            'url' => '/agent/reports/profit_loss.php'];
    }

    if ($access['sales']) {
        // What is still owed on unpaid invoices - the same total as the aging card and
        // the list the card opens. Scoped to the clients this user can open.
        $invoice_scope = clientScopeSql('invoice_client_id');
        $recurring_scope = clientScopeSql('recurring_invoice_client_id');
        $receivables = dashboardSum("SELECT SUM(balance) FROM (" . dashboardOpenInvoicesSql($invoice_scope) . ") AS open_invoices WHERE balance > 0");
        $mrr = dashboardSum("SELECT SUM(" . recurringInvoiceMonthlySql() . ") FROM recurring_invoices WHERE recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL $recurring_scope");

        $cards[] = ['label' => 'Receivables', 'value' => $receivables, 'change' => null, 'note' => 'Owed to you now',
            'url' => '/agent/invoices.php?status=Unpaid'];
        $cards[] = ['label' => 'Recurring revenue', 'value' => $mrr, 'change' => null, 'note' => 'Per month',
            'url' => '/agent/recurring_invoices.php'];
    }

    if ($access['financial']) {
        $recurring_expenses = recurringExpenseMonthlyTotal();
        $cards[] = ['label' => 'Recurring expenses', 'value' => $recurring_expenses, 'change' => null, 'note' => 'Per month',
            'url' => '/agent/recurring_expenses.php'];
    }

    return $cards;
}

/** Monthly amounts from a "SELECT DATE_FORMAT(..., '%Y-%m') AS m, SUM(...) AS v ... GROUP BY m" query, laid on $months. */
function dashboardMonthly($sql, $months) {
    global $mysqli;
    $values = array_fill_keys($months, 0.0);
    $result = mysqli_query($mysqli, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($values[$row['m']])) {
            $values[$row['m']] += floatval($row['v']);
        }
    }
    return array_values($values);
}

/**
 * Income and expenses per month over the chart's 12 months, plus income for
 * the 12 before. A chart that reaches this month runs three months further,
 * and from this month on carries what is expected in and out on top of what
 * has already happened.
 */
function dashboardCashFlow($period) {
    $months = dashboardChartMonths($period);
    $this_month = date('Y-m');

    $extended = end($months) === $this_month;
    if ($extended) {
        for ($i = 1; $i <= 3; $i++) {
            $months[] = substr(dashboardShiftMonths(date('Y-m-01'), $i), 0, 7);
        }
    }

    $last_year = array_map(function ($m) {
        return substr(dashboardShiftMonths("$m-01", -12), 0, 7);
    }, $months);

    $income = function ($from, $to, $range) {
        return array_map(function ($a, $b) {
            return round($a + $b, 2);
        },
            dashboardMonthly("SELECT DATE_FORMAT(payment_date, '%Y-%m') AS m, SUM(payment_amount) AS v FROM payments
                WHERE payment_date BETWEEN '$from' AND '$to' GROUP BY m", $range),
            dashboardMonthly("SELECT DATE_FORMAT(revenue_date, '%Y-%m') AS m, SUM(revenue_amount) AS v FROM revenues
                WHERE " . dashboardRevenueSql() . " AND revenue_date BETWEEN '$from' AND '$to' GROUP BY m", $range)
        );
    };
    $round = function ($values) {
        return array_map(function ($v) {
            return round($v, 2);
        }, $values);
    };

    $from = $months[0] . '-01';
    $to = date('Y-m-t', strtotime(end($months) . '-01'));
    $ly_from = $last_year[0] . '-01';
    $ly_to = date('Y-m-t', strtotime(end($last_year) . '-01'));

    // The forecast covers today to the end of the chart, when the chart reaches that far
    $expected_income = array_fill(0, count($months), 0);
    $expected_expenses = $expected_income;
    if (end($months) >= $this_month) {
        $today = date('Y-m-d');
        $expected_income = $round(dashboardExpectedIncome($today, $to, $months));
        $expected_expenses = $round(dashboardExpectedExpenses($today, $to, $months));
    }

    // First month entirely in the future: actuals there are always nothing, so the chart hides them
    $first_future = -1;
    foreach ($months as $i => $m) {
        if ($m > $this_month) {
            $first_future = $i;
            break;
        }
    }

    return [
        'labels' => array_map(function ($m) {
            return date('M y', strtotime("$m-01"));
        }, $months),
        'income' => $income($from, $to, $months),
        'expenses' => $round(dashboardMonthly("SELECT DATE_FORMAT(expense_date, '%Y-%m') AS m, SUM(expense_amount) AS v FROM expenses
            WHERE expense_vendor_id > 0 AND expense_date BETWEEN '$from' AND '$to' GROUP BY m", $months)),
        'income_last_year' => $income($ly_from, $ly_to, $last_year),
        'expected_income' => $expected_income,
        'expected_expenses' => $expected_expenses,
        'first_future' => $first_future,
        'extended' => $extended,
    ];
}

/**
 * Money expected in between $from and $to: what is still owed on unpaid
 * invoices, in the month each is due, plus what active recurring invoices
 * will bill, in the month each runs. Recurring revenue goes by bill date
 * rather than bill date plus net terms so each month carries one month of it,
 * as the "Recurring revenue" figure does. Overdue invoices are left out -
 * when, or whether, they get paid is anyone's guess.
 */
function dashboardExpectedIncome($from, $to, $months) {
    global $mysqli;

    $values = array_fill_keys($months, 0.0);

    $sql = mysqli_query($mysqli, "SELECT DATE_FORMAT(invoice_due, '%Y-%m') AS m, SUM(balance) AS v
        FROM (" . dashboardOpenInvoicesSql() . ") AS open_invoices
        WHERE balance > 0 AND invoice_due BETWEEN '$from' AND '$to' GROUP BY m");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (isset($values[$row['m']])) {
            $values[$row['m']] += floatval($row['v']);
        }
    }

    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_next_date, recurring_invoice_frequency, recurring_invoice_amount
        FROM recurring_invoices
        WHERE recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL AND recurring_invoice_next_date <= '$to'");
    while ($row = mysqli_fetch_assoc($sql)) {
        $step = recurringInvoiceMonths($row['recurring_invoice_frequency']);
        // A bill date already passed is one the nightly job has yet to run - it goes out today
        $bill = max($row['recurring_invoice_next_date'], $from);
        for ($guard = 0; $bill <= $to && $guard < 120; $guard++) {
            if (isset($values[substr($bill, 0, 7)])) {
                $values[substr($bill, 0, 7)] += floatval($row['recurring_invoice_amount']);
            }
            $bill = dashboardShiftMonths($bill, $step);
        }
    }

    return array_values($values);
}

/** Money expected out between $from and $to: active recurring vendor expenses on their schedule. */
function dashboardExpectedExpenses($from, $to, $months) {
    global $mysqli;

    $values = array_fill_keys($months, 0.0);

    // Each run on its schedule (functions/expenses.php) - monthly, quarterly or
    // yearly on its own day, none after its end date. A missed run not yet
    // recorded still goes out, so it counts in the first month shown.
    $sql = mysqli_query($mysqli, "SELECT recurring_expenses.* FROM recurring_expenses
        WHERE " . recurringExpenseRunningSql() . " AND recurring_expense_next_date <= '$to'");
    while ($row = mysqli_fetch_assoc($sql)) {
        foreach (recurringExpenseRunsUntil($row, $to) as $date) {
            $key = substr(max($date, $from), 0, 7);
            if (isset($values[$key])) {
                $values[$key] += floatval($row['recurring_expense_amount']);
            }
        }
    }

    return array_values($values);
}

/**
 * What clients owe, split by how late it is, and the clients who owe the
 * most. Same invoices and total as the Receivables figure.
 */
function dashboardReceivablesAging($limit = 4) {
    global $mysqli;

    $open = dashboardOpenInvoicesSql(clientScopeSql('invoice_client_id'));

    $buckets = [];
    foreach (['current' => 'Not due yet', '1-30' => '1–30 days', '31-60' => '31–60 days', '61-90' => '61–90 days', '90+' => '90+ days'] as $key => $label) {
        $buckets[$key] = ['label' => $label, 'amount' => 0.0, 'count' => 0];
    }

    $sql = mysqli_query($mysqli, "
        SELECT CASE
                WHEN invoice_due >= CURDATE() THEN 'current'
                WHEN invoice_due >= CURDATE() - INTERVAL 30 DAY THEN '1-30'
                WHEN invoice_due >= CURDATE() - INTERVAL 60 DAY THEN '31-60'
                WHEN invoice_due >= CURDATE() - INTERVAL 90 DAY THEN '61-90'
                ELSE '90+'
            END AS bucket, COUNT(invoice_id) AS n, SUM(balance) AS v
        FROM ($open) AS open_invoices
        WHERE balance > 0
        GROUP BY bucket
    ");
    $total = 0.0;
    $count = 0;
    while ($row = mysqli_fetch_assoc($sql)) {
        $buckets[$row['bucket']]['amount'] = floatval($row['v']);
        $buckets[$row['bucket']]['count'] = intval($row['n']);
        $total += floatval($row['v']);
        $count += intval($row['n']);
    }

    $sql = mysqli_query($mysqli, "
        SELECT client_id, client_name, SUM(balance) AS v, COUNT(invoice_id) AS n, MAX(DATEDIFF(CURDATE(), invoice_due)) AS days_late
        FROM ($open) AS open_invoices
        JOIN clients ON invoice_client_id = client_id
        WHERE balance > 0
        GROUP BY client_id, client_name
        ORDER BY v DESC
        LIMIT " . intval($limit)
    );
    $clients = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[] = $row;
    }

    return ['buckets' => $buckets, 'total' => $total, 'count' => $count, 'clients' => $clients];
}

/** The clients who paid the most in the period, from invoice payments and client revenues. */
function dashboardTopClients($period, $limit = 8) {
    global $mysqli;

    $start = $period['start'];
    $end = $period['end'];

    $sql = mysqli_query($mysqli, "
        SELECT client_id, client_name, SUM(amount) AS v FROM (
            SELECT invoice_client_id AS client_id, payment_amount AS amount FROM payments
                JOIN invoices ON invoice_id = payment_invoice_id
                WHERE payment_date BETWEEN '$start' AND '$end' " . clientScopeSql('invoice_client_id') . "
            UNION ALL
            SELECT revenue_client_id, revenue_amount FROM revenues
                WHERE revenue_category_id > 0 AND revenue_client_id > 0 AND revenue_date BETWEEN '$start' AND '$end' " . clientScopeSql('revenue_client_id') . "
        ) AS income
        JOIN clients USING (client_id)
        GROUP BY client_id, client_name
        ORDER BY v DESC
        LIMIT " . intval($limit)
    );

    $clients = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[] = $row;
    }

    return ['clients' => $clients, 'income' => dashboardIncome($start, $end)];
}

/** Miles per month over the chart's 12 months, and the total for the period. */
function dashboardTrips($period) {
    $months = dashboardChartMonths($period);
    $from = $months[0] . '-01';
    $to = date('Y-m-t', strtotime(end($months) . '-01'));

    return [
        'labels' => array_map(function ($m) {
            return date('M y', strtotime("$m-01"));
        }, $months),
        'miles' => dashboardMonthly("SELECT DATE_FORMAT(trip_date, '%Y-%m') AS m, SUM(trip_miles) AS v FROM trips
            WHERE trip_archived_at IS NULL AND trip_date BETWEEN '$from' AND '$to' GROUP BY m", $months),
        'period_total' => dashboardSum("SELECT SUM(trip_miles) FROM trips WHERE trip_archived_at IS NULL AND trip_date BETWEEN '{$period['start']}' AND '{$period['end']}'"),
    ];
}

/** Top five of a "SELECT name, color, SUM() AS v ... GROUP BY ..." query, with the rest summed as Other. */
function dashboardTopFive($sql) {
    global $mysqli;

    $rows = [];
    $result = mysqli_query($mysqli, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        if (floatval($row['v']) > 0) {
            $rows[] = $row;
        }
    }

    $top = array_slice($rows, 0, 5);
    $other = array_sum(array_map(function ($r) {
        return floatval($r['v']);
    }, array_slice($rows, 5)));

    $out = ['labels' => [], 'values' => [], 'colors' => []];
    foreach ($top as $row) {
        $out['labels'][] = $row['name'];
        $out['values'][] = round(floatval($row['v']), 2);
        $out['colors'][] = preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($row['color'] ?? '')) ? $row['color'] : null;
    }
    if ($other > 0) {
        $out['labels'][] = 'Other';
        $out['values'][] = round($other, 2);
        $out['colors'][] = null;
    }

    return $out;
}

function dashboardBreakdowns($period) {
    $start = $period['start'];
    $end = $period['end'];

    return [
        'income_by_category' => dashboardTopFive("
            SELECT category_name AS name, category_color AS color, SUM(amount) AS v FROM (
                SELECT invoice_category_id AS category_id, payment_amount AS amount FROM payments
                    JOIN invoices ON invoice_id = payment_invoice_id
                    WHERE payment_date BETWEEN '$start' AND '$end' AND invoice_category_id > 0
                UNION ALL
                SELECT revenue_category_id, revenue_amount FROM revenues
                    WHERE revenue_date BETWEEN '$start' AND '$end' AND " . dashboardRevenueSql() . "
            ) AS income
            JOIN categories USING (category_id)
            GROUP BY category_id, category_name, category_color ORDER BY v DESC"),
        'expenses_by_category' => dashboardTopFive("
            SELECT category_name AS name, category_color AS color, SUM(expense_amount) AS v FROM expenses
            JOIN categories ON expense_category_id = category_id
            WHERE expense_vendor_id > 0 AND expense_date BETWEEN '$start' AND '$end'
            GROUP BY category_id, category_name, category_color ORDER BY v DESC"),
        'expenses_by_vendor' => dashboardTopFive("
            SELECT vendor_name AS name, NULL AS color, SUM(expense_amount) AS v FROM expenses
            JOIN vendors ON expense_vendor_id = vendor_id
            WHERE expense_date BETWEEN '$start' AND '$end'
            GROUP BY vendor_id, vendor_name ORDER BY v DESC"),
    ];
}

/** Balance of every open account: opening balance, plus money in, minus money out. */
function dashboardAccountBalances() {
    global $mysqli;

    $sql = mysqli_query($mysqli, "
        SELECT account_id, account_name, opening_balance
            + COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_account_id = account_id), 0)
            + COALESCE((SELECT SUM(revenue_amount) FROM revenues WHERE revenue_account_id = account_id), 0)
            - COALESCE((SELECT SUM(expense_amount) FROM expenses WHERE expense_account_id = account_id), 0) AS balance
        FROM accounts
        WHERE account_archived_at IS NULL
        ORDER BY account_name
    ");

    $accounts = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $accounts[] = $row;
    }
    return $accounts;
}

function dashboardLatestIncome($limit = 5) {
    global $mysqli;

    $sql = mysqli_query($mysqli, "
        SELECT payment_amount, payment_date, invoice_id, invoice_prefix, invoice_number, invoice_client_id, client_name
        FROM payments
        JOIN invoices ON payment_invoice_id = invoice_id
        JOIN clients ON invoice_client_id = client_id
        WHERE 1 = 1 " . clientScopeSql('invoice_client_id') . "
        ORDER BY payment_date DESC, payment_id DESC
        LIMIT " . intval($limit));

    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

function dashboardLatestExpenses($limit = 5) {
    global $mysqli;

    $sql = mysqli_query($mysqli, "
        SELECT expense_amount, expense_date, vendor_name, category_name
        FROM expenses
        JOIN vendors ON expense_vendor_id = vendor_id
        LEFT JOIN categories ON expense_category_id = category_id
        ORDER BY expense_date DESC, expense_id DESC
        LIMIT " . intval($limit));

    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}
