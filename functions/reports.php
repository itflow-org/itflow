<?php

/*
 * Reports - agent/reports/.
 *
 * What the report pages share: the catalogue (which reports there are and who
 * may open each - the side menu and the Reports home page are both drawn from
 * it), the period picker, number formatting, the strip of figures along the
 * top of a report, charts, tables and CSV export.
 *
 * A report page reads:
 *
 *     require_once "includes/inc_report.php";        // signed in, may use reports
 *     reportEnforce('ticket_summary');                // may open this one
 *     $period = reportPeriod('last_12');
 *     ... queries, into rows ...
 *     if (reportExporting()) {                         // ?export=csv
 *         reportCsv('Tickets', $columns, $rows);       // sends the file and stops
 *     }
 *     require_once "includes/inc_report_layout.php";
 *     reportHeader('ticket_summary', ['period' => $period]);
 *     ... reportStats(), reportChart(), reportTable() ...
 *     require_once "../../includes/footer.php";
 *
 * Money is counted the way the dashboard counts it: income is what came in -
 * invoice payments, and other income that isn't a transfer, categorised or
 * not (functions/income.php); spending is expenses with a
 * vendor, as an expense with no vendor is the out side of a transfer between
 * accounts. Anything tied to a client goes through clientScopeSql(), so an
 * agent restricted to some clients only ever sees those clients' rows; the
 * company's own books (income, expenses, profit and loss, tax, budget) are
 * company-wide, as they are on the dashboard.
 */

/*
 * ---------- the catalogue ----------
 */

/**
 * Every report, in the groups the menu shows them in. Per report:
 *   'title', 'icon', 'description' - what the menu and the Reports page say
 *   'module'  - the permission it needs (read), on top of module_reporting
 *   'feature' - the parts of ITFlow that have to be turned on: accounting,
 *               ticketing and/or itdoc
 * The array key is the page's file name without .php.
 */
function reportCatalog() {
    return [
        'finance' => [
            'label' => 'Finance',
            'reports' => [
                'profit_loss' => [
                    'title' => 'Profit & Loss', 'icon' => 'fa-balance-scale', 'module' => 'module_financial', 'feature' => ['accounting'],
                    'description' => 'Income against spending by month or quarter, with the margin and last year beside it.',
                ],
                'income_summary' => [
                    'title' => 'Income', 'icon' => 'fa-hand-holding-usd', 'module' => 'module_financial', 'feature' => ['accounting'],
                    'description' => 'Money received by category and month - invoice payments and other revenue.',
                ],
                'expense_summary' => [
                    'title' => 'Expenses', 'icon' => 'fa-shopping-cart', 'module' => 'module_financial', 'feature' => ['accounting'],
                    'description' => 'Spending by category and month.',
                ],
                'budget' => [
                    'title' => 'Budget vs Actual', 'icon' => 'fa-bullseye', 'module' => 'module_financial', 'feature' => ['accounting'],
                    'description' => 'Plan what you will spend each month by category, and see how the real spending compares.',
                ],
                'expense_by_vendor' => [
                    'title' => 'Spend by Vendor', 'icon' => 'fa-building', 'module' => 'module_financial', 'feature' => ['accounting'],
                    'description' => 'Who you pay the most - and the vendors paid 600 or more, for 1099 forms.',
                ],
                'tax_summary' => [
                    'title' => 'Tax Collected', 'icon' => 'fa-percent', 'module' => 'module_financial', 'feature' => ['accounting'],
                    'description' => 'Tax collected on paid invoices, and the sales it was collected on, by month or quarter.',
                ],
                'mileage' => [
                    'title' => 'Mileage', 'icon' => 'fa-route', 'module' => 'module_financial', 'feature' => ['accounting'],
                    'description' => 'Distance driven by agent and client, with what it comes to at your mileage rate.',
                ],
            ],
        ],
        'billing' => [
            'label' => 'Sales & Billing',
            'reports' => [
                'outstanding_balances' => [
                    'title' => 'Receivables Aging', 'icon' => 'fa-hourglass-half', 'module' => 'module_sales', 'feature' => ['accounting'],
                    'description' => 'What each client owes, split by how late it is.',
                ],
                'income_by_client' => [
                    'title' => 'Income by Client', 'icon' => 'fa-users', 'module' => 'module_financial', 'feature' => ['accounting'],
                    'description' => 'What each client paid in the period, against the period before.',
                ],
                'client_profitability' => [
                    'title' => 'Client Profitability', 'icon' => 'fa-chart-line', 'module' => 'module_financial', 'feature' => ['accounting'],
                    'description' => 'Each client\'s income against the hours spent on them - your effective hourly rate.',
                ],
                'recurring_by_client' => [
                    'title' => 'Recurring Revenue', 'icon' => 'fa-sync-alt', 'module' => 'module_sales', 'feature' => ['accounting'],
                    'description' => 'Monthly and yearly recurring revenue by client and category.',
                ],
                'quote_pipeline' => [
                    'title' => 'Quotes Pipeline', 'icon' => 'fa-comment-dollar', 'module' => 'module_sales', 'feature' => ['accounting'],
                    'description' => 'Open quotes, your win rate, and the value won and lost.',
                ],
                'inventory' => [
                    'title' => 'Inventory', 'icon' => 'fa-warehouse', 'module' => 'module_sales', 'feature' => ['accounting'],
                    'description' => 'Stock on the shelf and what it is worth, what came in and went out at cost, and what to reorder.',
                ],
                'tickets_unbilled' => [
                    'title' => 'Unbilled Work', 'icon' => 'fa-receipt', 'module' => 'module_sales', 'feature' => ['accounting', 'ticketing'],
                    'description' => 'Billable tickets that are done but not invoiced yet, with the time and value on them.',
                ],
                'client_ticket_time_detail' => [
                    'title' => 'Time Detail by Client', 'icon' => 'fa-stopwatch', 'module' => 'module_sales', 'feature' => ['accounting', 'ticketing'],
                    'description' => 'Every reply with time on it, by client and ticket, rounded up to your billing increment.',
                ],
            ],
        ],
        'service' => [
            'label' => 'Service Desk',
            'reports' => [
                'ticket_summary' => [
                    'title' => 'Ticket Overview', 'icon' => 'fa-life-ring', 'module' => 'module_support', 'feature' => ['ticketing'],
                    'description' => 'Tickets opened and resolved, how fast, where they came from and when.',
                ],
                'ticket_aging' => [
                    'title' => 'Open Ticket Aging', 'icon' => 'fa-hourglass-start', 'module' => 'module_support', 'feature' => ['ticketing'],
                    'description' => 'Every open ticket by age, agent and client - the backlog, oldest first.',
                ],
                'ticket_by_client' => [
                    'title' => 'Tickets by Client', 'icon' => 'fa-building', 'module' => 'module_support', 'feature' => ['ticketing'],
                    'description' => 'Ticket volume, time worked and response times for each client.',
                ],
                'time_by_tech' => [
                    'title' => 'Technician Performance', 'icon' => 'fa-user-clock', 'module' => 'module_support', 'feature' => ['ticketing'],
                    'description' => 'Hours logged, billable share, utilization and tickets for each agent.',
                ],
                'sla_summary' => [
                    'title' => 'SLA Performance', 'icon' => 'fa-tachometer-alt', 'module' => 'module_support', 'feature' => ['ticketing'],
                    'description' => 'Response and resolution targets met, by month, priority and agent.',
                ],
                'sla_by_client' => [
                    'title' => 'SLA by Client', 'icon' => 'fa-handshake', 'module' => 'module_support', 'feature' => ['ticketing'],
                    'description' => 'Response and resolution targets met for each client.',
                ],
                'satisfaction' => [
                    'title' => 'Customer Satisfaction', 'icon' => 'fa-smile', 'module' => 'module_support', 'feature' => ['ticketing'],
                    'description' => 'How clients rated their closed tickets, by agent and client.',
                ],
            ],
        ],
        'clients' => [
            'label' => 'Clients & Documentation',
            'reports' => [
                'client_review' => [
                    'title' => 'Client Review', 'icon' => 'fa-clipboard-list', 'module' => 'module_support', 'feature' => [],
                    'description' => 'One client\'s quarter on a page - tickets, SLAs, assets, renewals and what to do next. Made to print for a business review.',
                ],
                'renewals' => [
                    'title' => 'Renewals & Expirations', 'icon' => 'fa-calendar-alt', 'module' => 'module_support', 'feature' => ['itdoc'],
                    'description' => 'Domains, certificates, licenses and warranties coming up for renewal, or already lapsed.',
                ],
                'asset_lifecycle' => [
                    'title' => 'Asset Lifecycle', 'icon' => 'fa-recycle', 'module' => 'module_support', 'feature' => ['itdoc'],
                    'description' => 'How old the hardware is, its warranty and operating system support - and what is due for replacement.',
                ],
                'documentation_coverage' => [
                    'title' => 'Documentation Coverage', 'icon' => 'fa-tasks', 'module' => 'module_support', 'feature' => ['itdoc'],
                    'description' => 'What is documented for each client, and what is missing.',
                ],
                'credential_rotation' => [
                    'title' => 'Credential Rotation', 'icon' => 'fa-key', 'module' => 'module_credential', 'feature' => ['itdoc'],
                    'description' => 'Passwords that have not changed in a while, oldest first.',
                ],
            ],
        ],
    ];
}

/** A report's catalogue entry, with its key - or null. */
function reportFind($key) {
    foreach (reportCatalog() as $group) {
        if (isset($group['reports'][$key])) {
            return $group['reports'][$key] + ['key' => $key];
        }
    }
    return null;
}

/** The user's permission on a module, looked up once per page. */
function reportPermission($module) {
    static $levels = [];
    if (!array_key_exists($module, $levels)) {
        $levels[$module] = intval(lookupUserPermission($module));
    }
    return $levels[$module];
}

/** Is that part of ITFlow turned on - accounting, ticketing or itdoc? */
function reportFeatureOn($feature) {
    global $config_module_enable_accounting, $config_module_enable_ticketing, $config_module_enable_itdoc;
    $on = [
        'accounting' => intval($config_module_enable_accounting) === 1,
        'ticketing' => intval($config_module_enable_ticketing) === 1,
        'itdoc' => intval($config_module_enable_itdoc) === 1,
    ];
    return !empty($on[$feature]);
}

/** May this user open this report? */
function reportCan(array $report) {
    foreach ($report['feature'] ?? [] as $feature) {
        if (!reportFeatureOn($feature)) {
            return false;
        }
    }
    return reportPermission('module_reporting') >= 1 && reportPermission($report['module']) >= 1;
}

/** The catalogue with only the reports this user may open, and only the groups left with any. */
function reportCatalogForUser() {
    $groups = [];
    foreach (reportCatalog() as $group_key => $group) {
        $reports = [];
        foreach ($group['reports'] as $key => $report) {
            if (reportCan($report)) {
                $reports[$key] = $report + ['key' => $key];
            }
        }
        if ($reports) {
            $groups[$group_key] = ['label' => $group['label'], 'reports' => $reports];
        }
    }
    return $groups;
}

/** Stop here unless this user may open this report. */
function reportEnforce($key) {
    $report = reportFind($key);
    if (!$report) {
        exit('No such report.');
    }
    foreach ($report['feature'] ?? [] as $feature) {
        if (!reportFeatureOn($feature)) {
            exit('This report needs the ' . ucfirst($feature === 'itdoc' ? 'documentation' : $feature) . ' module, which is turned off in Admin > Settings > Modules.');
        }
    }
    enforceUserPermission($report['module']);
    return $report;
}

/*
 * ---------- periods ----------
 */

const REPORT_PERIODS = [
    'this_month' => 'This month',
    'last_month' => 'Last month',
    'this_quarter' => 'This quarter',
    'last_quarter' => 'Last quarter',
    'this_year' => 'This year',
    'last_year' => 'Last year',
    'last_30' => 'Last 30 days',
    'last_90' => 'Last 90 days',
    'last_12' => 'Last 12 months',
    'custom' => 'Custom dates',
];

/** The URL parameters that pick this period again - for a link to another report over the same dates. */
function reportPeriodQuery(array $period) {
    if ($period['key'] === 'custom') {
        return ['period' => 'custom', 'from' => $period['start'], 'to' => $period['end']];
    }
    return ['period' => $period['key']];
}

/** A Y-m-d date from the request, or $fallback. */
function reportDateParam($name, $fallback) {
    $value = (string) ($_GET[$name] ?? '');
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : $fallback;
}

/**
 * The period picked (?period=, with ?from= and ?to= for custom dates), as
 * dates: 'start' and 'end' (Y-m-d, inclusive), the span before it to compare
 * with ('prev_start', 'prev_end' and 'compare', "last quarter"), a 'label'
 * and the dates written out ('range'). A period still running - this month,
 * quarter or year - ends today and is compared with the same stretch of the
 * one before, so day 15 is weighed against days 1-15, not a whole month.
 */
function reportPeriod($default = 'this_year') {
    $key = (string) ($_GET['period'] ?? $default);
    if (!isset(REPORT_PERIODS[$key]) && !preg_match('/^year-\d{4}$/', $key)) {
        $key = $default;
    }

    $today = date('Y-m-d');
    $year = intval(date('Y'));
    $quarter_start = sprintf('%d-%02d-01', $year, intdiv(intval(date('n')) - 1, 3) * 3 + 1);
    $month_end = function ($date) {
        return date('Y-m-t', strtotime($date));
    };

    switch ($key) {
        case 'this_month':
            $start = date('Y-m-01');
            $end = $today;
            $prev_start = dashboardShiftMonths($start, -1);
            $prev_end = dashboardShiftMonths($today, -1);
            $compare = 'the same days last month';
            break;
        case 'last_month':
            $start = dashboardShiftMonths(date('Y-m-01'), -1);
            $end = $month_end($start);
            $prev_start = dashboardShiftMonths($start, -1);
            $prev_end = $month_end($prev_start);
            $compare = 'the month before';
            break;
        case 'this_quarter':
            $start = $quarter_start;
            $end = $today;
            $prev_start = dashboardShiftMonths($start, -3);
            $prev_end = dashboardShiftMonths($today, -3);
            $compare = 'the same point last quarter';
            break;
        case 'last_quarter':
            $start = dashboardShiftMonths($quarter_start, -3);
            $end = $month_end(dashboardShiftMonths($start, 2));
            $prev_start = dashboardShiftMonths($start, -3);
            $prev_end = $month_end(dashboardShiftMonths($prev_start, 2));
            $compare = 'the quarter before';
            break;
        case 'last_year':
            $start = ($year - 1) . '-01-01';
            $end = ($year - 1) . '-12-31';
            $prev_start = ($year - 2) . '-01-01';
            $prev_end = ($year - 2) . '-12-31';
            $compare = $year - 2;
            break;
        case 'last_30':
        case 'last_90':
            $days = $key === 'last_30' ? 30 : 90;
            $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
            $end = $today;
            $prev_start = date('Y-m-d', strtotime('-' . (2 * $days - 1) . ' days'));
            $prev_end = date('Y-m-d', strtotime("-$days days"));
            $compare = "the $days days before";
            break;
        case 'last_12':
            $start = dashboardShiftMonths(date('Y-m-01'), -11);
            $end = $today;
            $prev_start = dashboardShiftMonths($start, -12);
            $prev_end = dashboardShiftMonths($today, -12);
            $compare = 'the 12 months before';
            break;
        case 'custom':
            $start = reportDateParam('from', date('Y-m-01'));
            $end = reportDateParam('to', $today);
            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }
            $span = intval((new DateTime($start))->diff(new DateTime($end))->days) + 1;
            $prev_end = date('Y-m-d', strtotime("$start -1 day"));
            $prev_start = date('Y-m-d', strtotime("$prev_end -" . ($span - 1) . ' days'));
            $compare = 'the ' . $span . ' day' . ($span === 1 ? '' : 's') . ' before';
            break;
        default:
            if (preg_match('/^year-(\d{4})$/', $key, $m)) {
                $picked = intval($m[1]);
                $start = "$picked-01-01";
                $end = "$picked-12-31";
                $prev_start = ($picked - 1) . '-01-01';
                $prev_end = ($picked - 1) . '-12-31';
                $compare = $picked - 1;
                break;
            }
            // this_year
            $key = 'this_year';
            $start = "$year-01-01";
            $end = $today;
            $prev_start = ($year - 1) . '-01-01';
            $prev_end = dashboardShiftMonths($today, -12);
            $compare = 'the same point last year';
    }

    $days = intval((new DateTime($start))->diff(new DateTime($end))->days) + 1;

    return [
        'key' => $key,
        'label' => REPORT_PERIODS[$key] ?? substr($key, 5),
        'start' => $start,
        'end' => $end,
        'prev_start' => $prev_start,
        'prev_end' => $prev_end,
        'compare' => (string) $compare,
        'range' => reportRangeText($start, $end),
        'days' => $days,
    ];
}

/** "Jul 1 - Sep 30, 2026", "Dec 1, 2025 - Jan 15, 2026" or "Oct 15, 2026". */
function reportRangeText($start, $end) {
    $s = strtotime($start);
    $e = strtotime($end);
    if ($start === $end) {
        return date('M j, Y', $s);
    }
    if (date('Y', $s) === date('Y', $e)) {
        return date('M j', $s) . ' – ' . date('M j, Y', $e);
    }
    return date('M j, Y', $s) . ' – ' . date('M j, Y', $e);
}

/** SQL: $column falls in the period (or the one before it, with $previous). Works on DATE and DATETIME columns. */
function reportRangeSql($column, $period, $previous = false) {
    $start = $previous ? $period['prev_start'] : $period['start'];
    $end = $previous ? $period['prev_end'] : $period['end'];
    return "($column >= '$start' AND $column < '$end' + INTERVAL 1 DAY)";
}

/**
 * The steps a chart over the period is drawn in - days up to a month, weeks
 * up to four months, months beyond that - as 'keys' (what reportBucketSql()
 * returns for each) and 'labels'.
 */
function reportBuckets($period) {
    $unit = $period['days'] <= 31 ? 'day' : ($period['days'] <= 120 ? 'week' : 'month');
    $cursor = new DateTime($period['start']);
    $end = new DateTime($period['end']);
    if ($unit === 'week') {
        $cursor->modify('-' . (intval($cursor->format('N')) - 1) . ' days');
    } elseif ($unit === 'month') {
        $cursor->modify('first day of this month');
    }
    $multi_year = substr($period['start'], 0, 4) !== substr($period['end'], 0, 4);

    $keys = [];
    $labels = [];
    for ($guard = 0; $cursor <= $end && $guard < 400; $guard++) {
        if ($unit === 'month') {
            $keys[] = $cursor->format('Y-m');
            $labels[] = $cursor->format($multi_year ? 'M y' : 'M');
            $cursor->modify('+1 month');
        } else {
            $keys[] = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('M j');
            $cursor->modify($unit === 'week' ? '+7 days' : '+1 day');
        }
    }

    return ['unit' => $unit, 'keys' => $keys, 'labels' => $labels];
}

/** SQL for the bucket a date falls in - the same keys reportBuckets() lays out. */
function reportBucketSql($column, $unit) {
    if ($unit === 'month') {
        return "DATE_FORMAT($column, '%Y-%m')";
    }
    if ($unit === 'week') {
        return "DATE_FORMAT(DATE($column) - INTERVAL WEEKDAY($column) DAY, '%Y-%m-%d')";
    }
    return "DATE_FORMAT($column, '%Y-%m-%d')";
}

/** A query returning "b" (bucket) and "v" (value) rows, laid on the buckets in order. */
function reportSeries($sql, array $keys) {
    global $mysqli;
    $values = array_fill_keys($keys, 0.0);
    $result = mysqli_query($mysqli, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($values[$row['b']])) {
            $values[$row['b']] += floatval($row['v']);
        }
    }
    return array_map(function ($v) {
        return round($v, 2);
    }, array_values($values));
}

/**
 * For the reports laid out as a year of months: the year picked (?year=) and
 * the years to offer - every year $sql (returning "y") finds, plus this year.
 */
function reportYear($sql) {
    global $mysqli;
    $current = intval(date('Y'));
    $years = [$current];
    $result = mysqli_query($mysqli, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $y = intval($row['y']);
        if ($y > 1970 && $y <= $current + 1) {
            $years[] = $y;
        }
    }
    $years = array_values(array_unique($years));
    rsort($years);
    $year = intval($_GET['year'] ?? $current);
    if (!in_array($year, $years, true)) {
        $year = $current;
    }
    return [$year, $years];
}

/**
 * Years before last year that $sql (returning "y") finds data in, newest
 * first - for the period picker, beside its This year and Last year.
 */
function reportPastYears($sql) {
    global $mysqli;
    $years = [];
    $result = mysqli_query($mysqli, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $y = intval($row['y']);
        if ($y > 1970 && $y < intval(date('Y')) - 1) {
            $years[] = $y;
        }
    }
    $years = array_values(array_unique($years));
    rsort($years);
    return $years;
}

/** How many months of $year have started - 12 for a past year. */
function reportMonthsElapsed($year) {
    $current = intval(date('Y'));
    if ($year < $current) {
        return 12;
    }
    return $year > $current ? 0 : intval(date('n'));
}

/*
 * ---------- request ----------
 */

/** The client picked, if this user may see it - else 0, meaning every client they can see. */
function reportClientId() {
    global $mysqli;
    $client_id = intval($_GET['client_id'] ?? 0);
    if ($client_id <= 0 || !hasClientAccess($client_id)) {
        return 0;
    }
    $exists = mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id"));
    return $exists ? $client_id : 0;
}

/** The agent picked (?agent=), or 0. */
function reportAgentId() {
    global $mysqli;
    $user_id = intval($_GET['agent'] ?? 0);
    if ($user_id <= 0) {
        return 0;
    }
    return mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM users WHERE user_id = $user_id AND user_type = 1")) ? $user_id : 0;
}

/** A client's name, for headings. */
function reportClientName($client_id) {
    global $mysqli;
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = " . intval($client_id)));
    return $row ? $row[0] : '';
}

/** SQL limiting $column to the client picked, or to every client this user may see. */
function reportClientSql($column, $client_id) {
    $client_id = intval($client_id);
    return ($client_id ? " AND $column = $client_id" : '') . clientScopeSql($column);
}

/*
 * ---------- numbers ----------
 */

function reportMoney($amount) {
    global $currency_format, $session_company_currency;
    return numfmt_format_currency($currency_format, floatval($amount), $session_company_currency);
}

/** Money to the whole unit - for the wide year-of-months grids, where cents only crowd the columns. */
function reportMoneyWhole($amount) {
    global $currency_format, $session_company_currency;
    static $formatter = null;
    if ($formatter === null) {
        $formatter = clone $currency_format;
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);
    }
    return numfmt_format_currency($formatter, round(floatval($amount)), $session_company_currency);
}

function reportCount($count) {
    return number_format(intval($count));
}

/** Seconds as decimal hours - 12.5. */
function reportHours($seconds, $decimals = 1) {
    return number_format(max(0, floatval($seconds)) / 3600, $decimals);
}

/** Seconds as a duration that fits a cell - "45m", "5h 20m", "3d 4h". '-' for none. */
function reportDuration($seconds) {
    if ($seconds === null || $seconds === '') {
        return '-';
    }
    $minutes = intval(round(floatval($seconds) / 60));
    if ($minutes < 1) {
        return '<1m';
    }
    if ($minutes < 60) {
        return $minutes . 'm';
    }
    $hours = intdiv($minutes, 60);
    if ($hours < 24) {
        $rest = $minutes % 60;
        return $hours . 'h' . ($rest ? " {$rest}m" : '');
    }
    $days = intdiv($hours, 24);
    $rest = $hours % 24;
    return $days . 'd' . ($rest ? " {$rest}h" : '');
}

/** $part of $whole as a percentage, or null when there is no whole. */
function reportShare($part, $whole, $decimals = 0) {
    return floatval($whole) != 0.0 ? round(floatval($part) / floatval($whole) * 100, $decimals) : null;
}

/** A percentage for a cell - '-' when there is none. */
function reportPercent($percent) {
    return $percent === null ? '-' : rtrim(rtrim(number_format($percent, 1), '0'), '.') . '%';
}

/** Change from $previous to $current in whole percent - null when there is nothing before to compare with. */
function reportChange($current, $previous) {
    if (abs(floatval($previous)) < 0.005) {
        return null;
    }
    return intval(round((floatval($current) - floatval($previous)) / abs(floatval($previous)) * 100));
}

/**
 * The change as an arrow and a percentage, green when it went the right way
 * ($good 'up' or 'down'), with what it is compared with after it.
 */
function reportChangeHtml($current, $previous, $good = 'up', $compare = '') {
    $change = reportChange($current, $previous);
    $against = $compare !== '' ? ' <span class="text-body-tertiary">vs ' . escapeHtml($compare) . '</span>' : '';
    if ($change === null) {
        return '<span class="text-body-tertiary">Nothing before to compare</span>';
    }
    if ($change === 0) {
        return '<span class="text-body-secondary fw-semibold">No change</span>' . $against;
    }
    $better = $good === 'up' ? $change > 0 : $change < 0;
    return '<span class="fw-semibold ' . ($better ? 'text-success-emphasis' : 'text-danger-emphasis') . '">'
        . '<i class="fas fa-arrow-' . ($change > 0 ? 'up' : 'down') . ' me-1"></i>' . abs($change) . '%</span>' . $against;
}

/** A compliance percentage coloured by how good it is - 95 and up green, 80 amber, below red. */
function reportRateHtml($percent, $good = 95, $fair = 80) {
    if ($percent === null) {
        return '<span class="text-body-tertiary">-</span>';
    }
    $tone = $percent >= $good ? 'success' : ($percent >= $fair ? 'warning' : 'danger');
    return '<span class="fw-semibold text-' . $tone . '-emphasis">' . reportPercent($percent) . '</span>';
}

/*
 * ---------- building blocks ----------
 */

/**
 * The strip of figures along the top of a report. Each is
 *   ['label' => .., 'value' => plain text, 'sub' => HTML line under it,
 *    'url' => .., 'tone' => danger | warning | success | info, 'title' => ..]
 * 'value_html' instead of 'value' for a value with markup in it.
 */
function reportStats(array $stats) {
    $html = '<div class="card itflow-report-stats">';
    foreach ($stats as $stat) {
        $tag = !empty($stat['url']) ? 'a' : 'div';
        $href = !empty($stat['url']) ? ' href="' . escapeHtml($stat['url']) . '"' : '';
        $title = !empty($stat['title']) ? ' title="' . escapeHtml($stat['title']) . '"' : '';
        $tone = !empty($stat['tone']) ? ' text-' . $stat['tone'] . '-emphasis' : '';
        $value = $stat['value_html'] ?? escapeHtml((string) ($stat['value'] ?? ''));
        $html .= "<$tag class=\"itflow-report-stat\"$href$title>"
            . '<span class="itflow-report-stat-label">' . escapeHtml($stat['label']) . '</span>'
            . '<span class="itflow-report-stat-value' . $tone . '">' . $value . '</span>'
            . (isset($stat['sub']) && $stat['sub'] !== '' ? '<span class="itflow-report-stat-sub">' . $stat['sub'] . '</span>' : '')
            . "</$tag>";
    }
    return $html . '</div>';
}

/**
 * A chart, drawn by js/reports.js. $config:
 *   'type'       bar | line | doughnut
 *   'labels'     the x axis (or the slices)
 *   'series'     [['label' => .., 'data' => [..], 'tone' => accent | success | danger |
 *                  warning | info | muted | purple | #hex, 'type' => line (on a bar
 *                  chart), 'dashed' => true, 'stack' => .., 'axis' => y2, 'ghost' => true,
 *                  'tones' => [a tone per bar, in place of 'tone']], ..]
 *   'format'     money | count | hours | percent | decimal - the axis and tooltips
 *   'format2'    the same, for a second axis
 *   'stacked'    true to stack the bars
 *   'horizontal' true for bars running left to right
 *   'legend'     false to hide it
 *   'colors'     a colour (tone or #hex) per slice, for a doughnut
 *   'max'        the top of the value axis
 * $size: '' | sm | lg | donut. A horizontal chart grows with its rows.
 */
function reportChart(array $config, $size = '', $label = '') {
    global $session_company_currency;
    $config['currency'] = $config['currency'] ?? $session_company_currency;
    $style = '';
    if (!empty($config['horizontal'])) {
        $style = ' style="height: ' . max(140, count($config['labels'] ?? []) * 30 + 50) . 'px"';
    }
    $class = 'itflow-report-chart' . ($size !== '' ? " itflow-report-chart-$size" : '');
    return '<div class="' . $class . '"' . $style . '><canvas data-report-chart="' . escapeHtml(json_encode($config)) . '"'
        . ($label !== '' ? ' role="img" aria-label="' . escapeHtml($label) . '"' : '') . '></canvas></div>';
}

/** One row of a doughnut chart's legend list: swatch, label, value and share. */
function reportLegendHtml(array $labels, array $values, array $colors, $format = 'money') {
    $total = array_sum($values);
    $html = '<ul class="list-unstyled itflow-dash-legend mb-0">';
    foreach ($labels as $i => $label) {
        $value = $values[$i] ?? 0;
        $shown = $format === 'money' ? reportMoney($value) : ($format === 'hours' ? reportHours($value * 3600) . ' h' : reportCount($value));
        $html .= '<li><span class="itflow-dash-legend-swatch" data-report-swatch="' . escapeHtml($colors[$i] ?? 'muted') . '"></span>'
            . '<span class="itflow-dash-legend-label">' . escapeHtml($label) . '</span>'
            . '<span class="itflow-dash-legend-value">' . $shown . '</span>'
            . '<span class="itflow-dash-legend-share">' . reportPercent(reportShare($value, $total)) . '</span></li>';
    }
    return $html . '</ul>';
}

/**
 * Turn rows into "top $n and the rest as Other" - for doughnuts and short
 * lists. Each row is ['label' => .., 'value' => .., 'color' => ..].
 */
function reportTopN(array $rows, $n = 6, $other_label = 'Other') {
    usort($rows, function ($a, $b) {
        return $b['value'] <=> $a['value'];
    });
    $rows = array_values(array_filter($rows, function ($r) {
        return $r['value'] > 0;
    }));
    if (count($rows) <= $n) {
        return $rows;
    }
    $top = array_slice($rows, 0, $n - 1);
    $rest = array_sum(array_column(array_slice($rows, $n - 1), 'value'));
    $top[] = ['label' => $other_label, 'value' => $rest, 'color' => 'muted'];
    return $top;
}

/** The tones a series of slices cycles through. */
function reportPalette($i) {
    $tones = ['accent', 'info', 'success', 'warning', 'purple', 'danger', 'teal', 'orange', 'indigo', 'pink'];
    return $tones[$i % count($tones)];
}

/** A category's own colour when it has a usable one, else a palette tone. */
function reportCategoryColor($color, $i) {
    $color = trim((string) $color);
    return preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : reportPalette($i);
}

/*
 * ---------- tables ----------
 *
 * $columns, keyed by the field each reads from a row:
 *   'label'   the heading
 *   'type'    text | money | count | hours (seconds) | decimal | percent | date | duration (seconds)
 *   'html'    function ($row) returning the cell's HTML, instead of the plain value
 *   'csv'     false to leave it out of the export, or function ($row) for the value
 *   'csv_label' the export heading, when it should differ from the label
 *   'screen'  false to keep it for the export only (when another cell already shows it)
 *   'class'   classes for the cell
 *   'sort'    false to make the heading not sortable
 *   'title'   a tooltip on the heading
 * Rows are plain arrays of raw values; 'totals' (same keys) goes in the footer.
 * A row ['_heading' => 'Label'] is a quiet heading over the rows after it (left out
 * of the export) - for tables that are not sortable; '_class' adds classes to a row.
 */

function reportColumnIsNumber(array $column) {
    return in_array($column['type'] ?? 'text', ['money', 'count', 'hours', 'decimal', 'percent', 'duration'], true);
}

/** A cell's value as text - for the table, when there is no 'html'. */
function reportCellText(array $column, $value) {
    if ($value === null || $value === '') {
        return '-';
    }
    switch ($column['type'] ?? 'text') {
        case 'money':
            return reportMoney($value);
        case 'count':
            return reportCount($value);
        case 'hours':
            return reportHours($value, $column['decimals'] ?? 1);
        case 'decimal':
            return number_format(floatval($value), $column['decimals'] ?? 1);
        case 'percent':
            return reportPercent($value);
        case 'duration':
            return reportDuration($value);
        case 'date':
            return date('M j, Y', strtotime($value));
    }
    return (string) $value;
}

/** A cell's value for the CSV - numbers bare, so a spreadsheet can add them up. */
function reportCsvValue(array $column, $row, $key) {
    if (isset($column['csv']) && is_callable($column['csv'])) {
        return (string) $column['csv']($row);
    }
    $value = $row[$key] ?? '';
    if ($value === null || $value === '') {
        return '';
    }
    switch ($column['type'] ?? 'text') {
        case 'money':
            return number_format(floatval($value), 2, '.', '');
        case 'hours':
        case 'duration':
            return number_format(floatval($value) / 3600, 2, '.', '');
        case 'decimal':
            return number_format(floatval($value), $column['decimals'] ?? 1, '.', '');
        case 'percent':
            return (string) round(floatval($value), 1);
        case 'count':
            return (string) intval($value);
    }
    return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'));
}

function reportTable(array $columns, array $rows, array $options = []) {
    $columns = array_filter($columns, function ($column) {
        return ($column['screen'] ?? true) !== false;
    });
    $sortable = $options['sortable'] ?? true;
    // A long list shows its first rows; the CSV always has them all
    $all = count($rows);
    $limit = intval($options['limit'] ?? 0);
    if ($limit && $all > $limit) {
        $rows = array_slice($rows, 0, $limit);
    }
    $html = '<div class="table-responsive"><table class="table table-hover align-middle mb-0 itflow-report-table ' . ($options['class'] ?? '') . '"'
        . ($sortable ? ' data-report-sort' : '') . '>';

    $html .= '<thead class="table-light"><tr>';
    foreach ($columns as $key => $column) {
        $number = reportColumnIsNumber($column);
        $attrs = ($number ? ' class="text-end"' : '');
        if ($sortable && ($column['sort'] ?? true)) {
            $attrs .= ' data-sort="' . ($number || ($column['type'] ?? '') === 'date' ? 'num' : 'text') . '"';
        }
        if (!empty($column['title'])) {
            $attrs .= ' title="' . escapeHtml($column['title']) . '"';
        }
        $html .= "<th$attrs>" . escapeHtml($column['label']) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    if (!$rows) {
        $html .= '<tr><td colspan="' . count($columns) . '" class="text-center text-body-secondary py-4">'
            . ($options['empty'] ?? 'Nothing to show for this period.') . '</td></tr>';
    }

    foreach ($rows as $row) {
        if (isset($row['_heading'])) {
            // A quiet heading over the rows that follow - for an unsorted table
            $html .= '<tr class="itflow-report-group-row"><td colspan="' . count($columns) . '">' . escapeHtml($row['_heading']) . '</td></tr>';
            continue;
        }
        $html .= '<tr' . (!empty($row['_class']) ? ' class="' . escapeHtml($row['_class']) . '"' : '') . '>';
        foreach ($columns as $key => $column) {
            $value = $row[$key] ?? null;
            $number = reportColumnIsNumber($column);
            $classes = trim(($number ? 'text-end text-nowrap ' : '') . ($column['class'] ?? ''));
            $sort_value = '';
            if ($sortable && ($column['sort'] ?? true)) {
                if (($column['type'] ?? '') === 'date') {
                    $sort_value = ' data-value="' . ($value ? strtotime($value) : 0) . '"';
                } elseif ($number) {
                    $sort_value = ' data-value="' . ($value === null || $value === '' ? '' : floatval($value)) . '"';
                } elseif (isset($column['html'])) {
                    $sort_value = ' data-value="' . escapeHtml(mb_strtolower(trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8')))) . '"';
                }
            }
            $cell = isset($column['html']) ? $column['html']($row) : escapeHtml(reportCellText($column, $value));
            $html .= '<td' . ($classes !== '' ? ' class="' . $classes . '"' : '') . $sort_value . '>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    if ($limit && $all > $limit) {
        $html .= '<tr><td colspan="' . count($columns) . '" class="text-center small text-body-secondary py-2">Showing the first ' . reportCount($limit) . ' of ' . reportCount($all)
            . ' - <a href="' . escapeHtml(reportUrl(['export' => 'csv'] + (!empty($options['export_table']) ? ['table' => $options['export_table']] : []))) . '">export them all</a></td></tr>';
    }
    $html .= '</tbody>';

    if (!empty($options['totals']) && $rows) {
        $html .= '<tfoot><tr class="fw-semibold">';
        $first = true;
        foreach ($columns as $key => $column) {
            $number = reportColumnIsNumber($column);
            if ($first) {
                $html .= '<td>' . escapeHtml($options['totals'][$key] ?? 'Total') . '</td>';
                $first = false;
                continue;
            }
            $value = $options['totals'][$key] ?? null;
            $cell = isset($options['totals_html'][$key]) ? $options['totals_html'][$key] : ($value === null ? '' : escapeHtml(reportCellText($column, $value)));
            $html .= '<td class="' . ($number ? 'text-end text-nowrap' : '') . '">' . $cell . '</td>';
        }
        $html .= '</tr></tfoot>';
    }

    return $html . '</table></div>';
}

/*
 * ---------- export ----------
 */

/** Is this a request for the CSV (?export=csv)? $table names one of several tables on the page. */
function reportExporting($table = null) {
    if (($_GET['export'] ?? '') !== 'csv') {
        return false;
    }
    return $table === null || ($_GET['table'] ?? '') === $table;
}

/** Send rows as a CSV file and stop. Numbers go out bare; text is guarded against formulas. */
function reportCsv($name, array $columns, array $rows, array $totals = null) {
    global $session_name;

    $columns = array_filter($columns, function ($column) {
        return ($column['csv'] ?? true) !== false;
    });

    $out = fopen('php://memory', 'w');
    fputcsv($out, array_map(function ($column) {
        if (isset($column['csv_label'])) {
            return $column['csv_label'];
        }
        // Times go out as decimal hours and shares as bare numbers - say so in the heading
        $label = $column['label'];
        $type = $column['type'] ?? 'text';
        if (in_array($type, ['hours', 'duration'], true) && stripos($label, 'hour') === false) {
            return "$label (hours)";
        }
        if ($type === 'percent' && strpos($label, '%') === false) {
            return "$label %";
        }
        return $label;
    }, array_values($columns)), ',', '"', '\\');
    foreach ($rows as $row) {
        if (isset($row['_heading'])) {
            continue;
        }
        $values = [];
        foreach ($columns as $key => $column) {
            $values[] = escapeCsvFormula(reportCsvValue($column, $row, $key));
        }
        fputcsv($out, $values, ',', '"', '\\');
    }
    if ($totals) {
        $values = [];
        foreach ($columns as $key => $column) {
            // A total is its value as given - a column's csv function reads a row's fields, which the totals don't have
            unset($column['csv']);
            $values[] = escapeCsvFormula(array_key_exists($key, $totals) ? reportCsvValue($column, $totals, $key) : '');
        }
        fputcsv($out, $values, ',', '"', '\\');
    }

    logAudit('Report', 'Export', escapeSql("$session_name exported the $name report"));

    fseek($out, 0);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitizeFilename($name . '-' . date('Y-m-d') . '.csv') . '"');
    fpassthru($out);
    fclose($out);
    exit;
}

/** This page's URL with some query values changed (null drops one). */
function reportUrl(array $changes = []) {
    $params = $_GET;
    foreach ($changes as $name => $value) {
        if ($value === null) {
            unset($params[$name]);
        } else {
            $params[$name] = $value;
        }
    }
    $query = http_build_query($params);
    return basename($_SERVER['PHP_SELF']) . ($query !== '' ? "?$query" : '');
}

/*
 * ---------- the page header ----------
 */

/** The period picker - the presets, the years given, and From / To for custom dates. */
function reportPeriodControl($period, array $options = []) {
    $allowed = $options['periods'] ?? array_keys(REPORT_PERIODS);
    $years = $options['years'] ?? [];
    $custom = $period['key'] === 'custom';

    $html = '<div class="col-sm-6 col-lg-auto"><div class="input-group">'
        . '<span class="input-group-text"><i class="fas fa-fw fa-calendar-alt"></i></span>'
        . '<select class="form-select" name="period" data-report-period aria-label="Period">';
    foreach ($allowed as $key) {
        if ($key === 'custom') {
            continue;
        }
        $html .= '<option value="' . $key . '"' . ($period['key'] === $key ? ' selected' : '') . '>' . escapeHtml(REPORT_PERIODS[$key]) . '</option>';
    }
    foreach ($years as $year) {
        $html .= '<option value="year-' . intval($year) . '"' . ($period['key'] === "year-$year" ? ' selected' : '') . '>' . intval($year) . '</option>';
    }
    if (in_array('custom', $allowed, true)) {
        $html .= '<option value="custom"' . ($custom ? ' selected' : '') . '>Custom dates</option>';
    }
    $html .= '</select></div></div>';

    if (in_array('custom', $allowed, true)) {
        $html .= '<div class="col-sm-6 col-lg-auto" data-report-custom' . ($custom ? '' : ' hidden') . '><div class="input-group">'
            . '<span class="input-group-text">From</span>'
            . '<input type="date" class="form-control" name="from" value="' . escapeHtml($period['start']) . '"' . ($custom ? '' : ' disabled') . '>'
            . '<span class="input-group-text">to</span>'
            . '<input type="date" class="form-control" name="to" value="' . escapeHtml($period['end']) . '"' . ($custom ? '' : ' disabled') . '>'
            . '<button class="btn btn-primary" type="submit" title="Show these dates"><i class="fas fa-check"></i></button>'
            . '</div></div>';
    }
    return $html;
}

/** A year picker, for the reports laid out as a year of months. */
function reportYearControl($year, array $years) {
    $html = '<div class="col-sm-6 col-lg-auto"><div class="input-group">'
        . '<span class="input-group-text"><i class="fas fa-fw fa-calendar-alt"></i></span>'
        . '<select class="form-select" name="year" data-report-autosubmit aria-label="Year">';
    foreach ($years as $y) {
        $html .= '<option value="' . intval($y) . '"' . (intval($y) === intval($year) ? ' selected' : '') . '>' . intval($y) . '</option>';
    }
    return $html . '</select></div></div>';
}

/** The client picker - every client this user may see, or one. */
function reportClientControl($client_id, $all_label = 'All clients', $required = false) {
    global $mysqli;
    $client_id = intval($client_id);
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
        WHERE (client_archived_at IS NULL OR client_id = $client_id) " . clientScopeSql('client_id') . "
        ORDER BY client_name ASC");
    $html = '<div class="col-sm-6 col-lg-3"><select class="form-select select2" name="client_id" data-report-autosubmit aria-label="Client">'
        . '<option value="">' . escapeHtml($required ? '- Pick a client -' : "- $all_label -") . '</option>';
    while ($row = mysqli_fetch_assoc($sql)) {
        $id = intval($row['client_id']);
        $html .= '<option value="' . $id . '"' . ($id === $client_id ? ' selected' : '') . selectOptionClient($id, $row['client_name'], $row['client_abbreviation'] ?? '') . '>'
            . escapeHtml($row['client_name']) . '</option>';
    }
    return $html . '</select></div>';
}

/** The agent picker - active agents, and whoever is picked. */
function reportAgentControl($user_id, $all_label = 'All agents') {
    global $mysqli;
    $user_id = intval($user_id);
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users
        WHERE user_type = 1 AND ((user_archived_at IS NULL AND user_status = 1) OR user_id = $user_id)
        ORDER BY user_name ASC");
    $html = '<div class="col-sm-6 col-lg-2"><select class="form-select select2" name="agent" data-report-autosubmit aria-label="Agent">'
        . '<option value="">- ' . escapeHtml($all_label) . ' -</option>';
    while ($row = mysqli_fetch_assoc($sql)) {
        $id = intval($row['user_id']);
        $html .= '<option value="' . $id . '"' . ($id === $user_id ? ' selected' : '') . selectOptionUser($id, $row['user_name'], $row['user_avatar'] ?? '') . '>'
            . escapeHtml($row['user_name']) . '</option>';
    }
    return $html . '</select></div>';
}

/** A plain select that submits when changed - $choices value => label. */
function reportSelectControl($name, $value, array $choices, $icon = '', $label = '', $col = 'col-sm-6 col-lg-auto') {
    $html = '<div class="' . $col . '"><div class="input-group">';
    if ($icon !== '') {
        $html .= '<span class="input-group-text"><i class="fas fa-fw ' . $icon . '"></i></span>';
    } elseif ($label !== '') {
        $html .= '<span class="input-group-text">' . escapeHtml($label) . '</span>';
    }
    $html .= '<select class="form-select" name="' . escapeHtml($name) . '" data-report-autosubmit' . ($label !== '' ? ' aria-label="' . escapeHtml($label) . '"' : '') . '>';
    foreach ($choices as $key => $text) {
        $html .= '<option value="' . escapeHtml((string) $key) . '"' . ((string) $key === (string) $value ? ' selected' : '') . '>' . escapeHtml($text) . '</option>';
    }
    return $html . '</select></div></div>';
}

/** A switch that submits when flipped. */
function reportSwitchControl($name, $on, $label, $col = 'col-auto') {
    $id = 'reportSwitch' . preg_replace('/[^a-z0-9]/i', '', $name);
    return '<div class="' . $col . ' d-flex align-items-center"><div class="form-check form-switch mb-0">'
        . '<input class="form-check-input" type="checkbox" role="switch" name="' . escapeHtml($name) . '" value="1" id="' . $id . '"' . ($on ? ' checked' : '') . ' data-report-autosubmit>'
        . '<label class="form-check-label" for="' . $id . '">' . escapeHtml($label) . '</label></div></div>';
}

/** Segmented choice (Monthly / Quarterly) as links that keep the other filters. */
function reportSegmentControl($name, $value, array $choices, $col = 'col-auto') {
    $html = '<div class="' . $col . '"><div class="btn-group" role="group">';
    foreach ($choices as $key => $text) {
        $active = (string) $key === (string) $value;
        $html .= '<a class="btn btn-outline-secondary' . ($active ? ' active' : '') . '"' . ($active ? ' aria-current="true"' : '') . ' href="' . escapeHtml(reportUrl([$name => $key, 'export' => null, 'table' => null])) . '">' . escapeHtml($text) . '</a>';
    }
    return $html . '</div></div>';
}

/**
 * The top of every report: its name and the dates it covers, Export and
 * Print, the filters, and one line on what it shows. $options:
 *   'period'   the reportPeriod() in use - draws the period picker
 *   'periods'  the period keys to offer (default all); 'years' years to add
 *   'year'     [year, years] - a year picker instead, for the year-of-months reports
 *   'client'   the client picked, to draw the client picker (null: none)
 *   'agent'    the agent picked, to draw the agent picker (null: none)
 *   'controls' more filter controls, HTML, in the same form
 *   'subtitle' what shows beside the name (default: the dates)
 *   'export'   false for no CSV; or [table => label] for a choice of tables
 *   'client_required' the client picker asks for a client rather than offering all
 *   'note'     replaces the description line
 *   'actions'  more buttons beside Export and Print, HTML
 *   'keep'     [name => value] hidden inputs, so the filters keep them
 *   'print_meta' a line added to the printed header (such as the client)
 */
function reportHeader($key, array $options = []) {
    global $session_company_name, $session_name;

    $report = reportFind($key);
    $period = $options['period'] ?? null;
    $subtitle = $options['subtitle'] ?? ($period ? $period['range'] : (isset($options['year']) ? (string) $options['year'][0] : ''));

    $export = $options['export'] ?? true;
    $export_html = '';
    if ($export === true) {
        $export_html = '<a class="btn btn-outline-light" href="' . escapeHtml(reportUrl(['export' => 'csv'])) . '"><i class="fas fa-fw fa-file-csv"></i><span class="d-none d-md-inline ms-2">Export</span></a>';
    } elseif (is_array($export) && $export) {
        $export_html = '<div class="dropdown"><button type="button" class="btn btn-outline-light dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-fw fa-file-csv"></i><span class="d-none d-md-inline ms-2">Export</span></button><div class="dropdown-menu dropdown-menu-end">';
        foreach ($export as $table => $label) {
            $export_html .= '<a class="dropdown-item" href="' . escapeHtml(reportUrl(['export' => 'csv', 'table' => $table])) . '"><i class="fas fa-fw fa-file-csv me-2"></i>' . escapeHtml($label) . '</a>';
        }
        $export_html .= '</div></div>';
    }

    $controls = '';
    if ($period) {
        $controls .= reportPeriodControl($period, $options);
    }
    if (isset($options['year'])) {
        $controls .= reportYearControl($options['year'][0], $options['year'][1]);
    }
    if (array_key_exists('client', $options) && $options['client'] !== null) {
        $controls .= reportClientControl($options['client'], 'All clients', !empty($options['client_required']));
    }
    if (array_key_exists('agent', $options) && $options['agent'] !== null) {
        $controls .= reportAgentControl($options['agent']);
    }
    $controls .= $options['controls'] ?? '';

    ?>
    <div class="card itflow-report-head">
        <div class="card-header bg-dark py-2 d-flex flex-wrap align-items-center gap-2">
            <h3 class="card-title mt-2 me-auto">
                <i class="fas fa-fw <?= escapeHtml($report['icon']) ?> me-2"></i><?= escapeHtml($report['title']) ?>
                <?php if ($subtitle !== '') { ?><span class="itflow-report-subtitle"><?= escapeHtml($subtitle) ?></span><?php } ?>
            </h3>
            <div class="d-flex gap-2 d-print-none">
                <?= $options['actions'] ?? '' ?>
                <?= $export_html ?>
                <button type="button" class="btn btn-primary" onclick="window.print();"><i class="fas fa-fw fa-print"></i><span class="d-none d-md-inline ms-2">Print</span></button>
            </div>
        </div>
        <?php if ($controls !== '') { ?>
            <div class="card-body py-3 d-print-none">
                <form class="row g-2 align-items-center" autocomplete="off" data-report-filters>
                    <?php foreach ($options['keep'] ?? [] as $name => $value) { ?>
                        <input type="hidden" name="<?= escapeHtml($name) ?>" value="<?= escapeHtml((string) $value) ?>">
                    <?php } ?>
                    <?= $controls ?>
                </form>
                <p class="small text-body-secondary mb-0 mt-2"><?= $options['note'] ?? escapeHtml($report['description']) ?></p>
            </div>
        <?php } else { ?>
            <div class="card-body py-2 d-print-none">
                <p class="small text-body-secondary mb-0"><?= $options['note'] ?? escapeHtml($report['description']) ?></p>
            </div>
        <?php } ?>
        <div class="itflow-report-print-meta">
            <?= escapeHtml($session_company_name) ?>
            <?php if (!empty($options['print_meta'])) { ?> · <?= escapeHtml($options['print_meta']) ?><?php } ?>
            · Printed <?= escapeHtml(date('M j, Y g:i A')) ?> by <?= escapeHtml(stripslashes($session_name)) ?>
        </div>
    </div>
    <?php
}

/** A section card's opening: a heading, with something small on the right. Close it with '</div>'. */
function reportSectionOpen($title, $meta = '', $class = '') {
    return '<div class="card itflow-report-section ' . escapeHtml($class) . '"><div class="card-header d-flex flex-wrap align-items-baseline gap-2">'
        . '<h3 class="card-title mb-0 me-auto">' . escapeHtml($title) . '</h3>'
        . ($meta !== '' ? '<span class="small text-body-secondary">' . $meta . '</span>' : '')
        . '</div>';
}

/** An empty state - an icon and a sentence. */
function reportEmpty($text, $icon = 'fa-check-circle', $tone = 'success') {
    return '<div class="text-center text-body-secondary py-5"><i class="fas ' . $icon . ' fa-2x mb-2 d-block text-' . $tone . ' opacity-75"></i>' . $text . '</div>';
}

/*
 * ---------- links into the app ----------
 *
 * Reports live a folder down from the rest of the agent pages, so every link
 * out is absolute.
 */

function reportClientLink($client_id, $client_name, $page = 'client_overview.php') {
    $client_id = intval($client_id);
    if (!$client_id) {
        return '<span class="text-body-secondary">No client</span>';
    }
    return '<a class="text-body fw-semibold" href="/agent/' . $page . '?client_id=' . $client_id . '">' . escapeHtml($client_name) . '</a>';
}

/** A client's initials tile and name, linking to it. */
function reportClientCell($client_id, $client_name, $client_abbreviation = '', $page = 'client_overview.php') {
    $client_id = intval($client_id);
    if (!$client_id) {
        return '<span class="text-body-secondary fst-italic">No client</span>';
    }
    $tone = clientInitialsTone($client_id);
    $initials = escapeHtml(clientInitials((string) $client_abbreviation, (string) $client_name));
    return '<a class="d-inline-flex align-items-center gap-2 text-body text-decoration-none" href="/agent/' . $page . '?client_id=' . $client_id . '">'
        . '<span class="itflow-client-avatar itflow-client-avatar-xs bg-' . $tone . '-subtle text-' . $tone . '-emphasis">' . $initials . '</span>'
        . '<span class="fw-semibold itflow-report-client-name">' . escapeHtml($client_name) . '</span></a>';
}

/** A ticket's reference and subject, opening the ticket. */
function reportTicketLink($row, $with_subject = true) {
    $url = '/agent/' . ticketUrl($row['ticket_id'], $row['ticket_client_id'] ?? 0);
    $html = '<a class="font-monospace text-nowrap" href="' . escapeHtml($url) . '">' . escapeHtml(ticketReference($row)) . '</a>';
    if ($with_subject && isset($row['ticket_subject'])) {
        $html .= ' <a class="text-body" href="' . escapeHtml($url) . '">' . escapeHtml($row['ticket_subject']) . '</a>';
    }
    return $html;
}

/** An agent's avatar and name. */
function reportAgentCell($user_id, $user_name, $user_avatar = '', $none = 'Unassigned') {
    return formatAssignee($user_id, $user_name, $user_avatar, false, $none);
}

/** A ticket priority's coloured dot, as on the dashboard. */
function reportPriorityDot($priority) {
    $colors = ['Urgent' => 'dark', 'High' => 'danger', 'Medium' => 'warning', 'Low' => 'info'];
    return '<span class="itflow-dash-dot d-inline-block bg-' . ($colors[$priority] ?? 'secondary') . ' me-1" title="' . escapeHtml($priority) . ' priority"></span>';
}

/*
 * ---------- money by category ----------
 */

/** Rows of cat / m / v as [category id => ['total' => .., 'months' => [1 .. 12 => ..]]]. */
function reportCategoryGrid($result) {
    $grid = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $cat = intval($row['cat']);
        $month = intval($row['m']);
        if (!isset($grid[$cat])) {
            $grid[$cat] = ['total' => 0.0, 'months' => array_fill(1, 12, 0.0)];
        }
        $grid[$cat]['months'][$month] += floatval($row['v']);
        $grid[$cat]['total'] += floatval($row['v']);
    }
    return $grid;
}

/**
 * Income between two dates by income category and month (pass dates inside
 * one year - months are by number). A payment takes the category of the
 * invoice it paid; 0 is income with no category to show.
 */
function reportIncomeByCategory($from, $to, $client_id = 0) {
    global $mysqli;
    $client_id = intval($client_id);
    $payment_client = $client_id ? " AND invoice_client_id = $client_id" : '';
    $revenue_client = $client_id ? " AND revenue_client_id = $client_id" : '';
    return reportCategoryGrid(mysqli_query($mysqli, "
        SELECT cat, m, SUM(v) AS v FROM (
            SELECT COALESCE(invoice_category_id, 0) AS cat, MONTH(payment_date) AS m, payment_amount AS v
                FROM payments LEFT JOIN invoices ON payment_invoice_id = invoice_id
                WHERE payment_date >= '$from' AND payment_date < '$to' + INTERVAL 1 DAY $payment_client
            UNION ALL
            SELECT revenue_category_id, MONTH(revenue_date), revenue_amount
                FROM revenues
                WHERE " . dashboardRevenueSql() . " AND revenue_date >= '$from' AND revenue_date < '$to' + INTERVAL 1 DAY $revenue_client
        ) AS income
        GROUP BY cat, m"));
}

/** Spending between two dates by expense category and month - vendor expenses, as the Expenses list. */
function reportExpensesByCategory($from, $to) {
    global $mysqli;
    return reportCategoryGrid(mysqli_query($mysqli, "
        SELECT expense_category_id AS cat, MONTH(expense_date) AS m, SUM(expense_amount) AS v
        FROM expenses
        WHERE expense_vendor_id > 0 AND expense_date >= '$from' AND expense_date < '$to' + INTERVAL 1 DAY
        GROUP BY cat, m"));
}

/**
 * The categories of $type to list - every one not archived, and any archived
 * one with an amount in $grid - [id => row], by name. Amounts filed under an
 * id that is not one of these (deleted, or another type) move to 0, which
 * lists as "No category".
 */
function reportCategoryRows($type, array &$grid) {
    global $mysqli;
    $type = escapeSql($type);
    $ids = array_map('intval', array_keys($grid));
    $with = $ids ? ' OR category_id IN (' . implode(',', $ids) . ')' : '';
    $sql = mysqli_query($mysqli, "SELECT category_id, category_name, category_color, category_archived_at FROM categories
        WHERE category_type = '$type' AND (category_archived_at IS NULL $with)
        ORDER BY category_name ASC");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['category_id'])] = $row;
    }
    foreach (array_keys($grid) as $cat) {
        if ($cat !== 0 && !isset($rows[$cat])) {
            if (!isset($grid[0])) {
                $grid[0] = ['total' => 0.0, 'months' => array_fill(1, 12, 0.0)];
            }
            foreach ($grid[$cat]['months'] as $m => $v) {
                $grid[0]['months'][$m] += $v;
            }
            $grid[0]['total'] += $grid[$cat]['total'];
            unset($grid[$cat]);
        }
    }
    if (isset($grid[0]) && abs($grid[0]['total']) > 0.004) {
        $rows[0] = ['category_id' => 0, 'category_name' => 'No category', 'category_color' => '', 'category_archived_at' => null];
    }
    return $rows;
}

/** A category's amounts for a year - zeros when it has none. */
function reportGridRow(array $grid, $cat) {
    return $grid[$cat] ?? ['total' => 0.0, 'months' => array_fill(1, 12, 0.0)];
}

/** Every category added together, month by month. */
function reportGridTotals(array $grid) {
    $months = array_fill(1, 12, 0.0);
    $total = 0.0;
    foreach ($grid as $row) {
        foreach ($row['months'] as $m => $v) {
            $months[$m] += $v;
        }
        $total += $row['total'];
    }
    return ['total' => $total, 'months' => $months];
}

/** The months or quarters a year-of-months report shows: [index => [label, first month, last month]]. */
function reportYearColumns($view) {
    if ($view === 'quarter') {
        return [1 => ['Q1', 1, 3], 2 => ['Q2', 4, 6], 3 => ['Q3', 7, 9], 4 => ['Q4', 10, 12]];
    }
    $columns = [];
    for ($m = 1; $m <= 12; $m++) {
        $columns[$m] = [date('M', mktime(0, 0, 0, $m, 1)), $m, $m];
    }
    return $columns;
}

/** One category's amounts summed into the report's columns. */
function reportSumColumns(array $months, array $columns) {
    $out = [];
    foreach ($columns as $i => [$label, $first, $last]) {
        $sum = 0.0;
        for ($m = $first; $m <= $last; $m++) {
            $sum += $months[$m] ?? 0;
        }
        $out[$i] = $sum;
    }
    return $out;
}

/** An amount in a year-of-months grid - a dash for nothing, a link to the list behind it when given. */
function reportGridAmount($amount, $url = '') {
    if (abs($amount) < 0.005) {
        return '<span class="itflow-report-zero">–</span>';
    }
    $text = reportMoneyWhole($amount);
    return $url !== '' ? '<a class="itflow-report-amount" href="' . escapeHtml($url) . '">' . $text . '</a>' : $text;
}

/** The first and last day of a column of a year-of-months report. */
function reportColumnDates($year, $first, $last) {
    $from = sprintf('%d-%02d-01', $year, $first);
    return [$from, date('Y-m-t', strtotime(sprintf('%d-%02d-01', $year, $last)))];
}

/** Drop the categories with nothing in $grid (or in $also, the year before). */
function reportCategoriesWithAmounts(array $categories, array $grid, array $also = []) {
    return array_filter($categories, function ($cat) use ($grid, $also) {
        return abs($grid[$cat]['total'] ?? 0) >= 0.005 || abs($also[$cat]['total'] ?? 0) >= 0.005;
    }, ARRAY_FILTER_USE_KEY);
}

/**
 * Stacked monthly bars for the biggest categories (the rest as Other), for
 * the Income and Expenses reports. Months not yet reached are left empty.
 */
function reportCategoryChartSeries(array $categories, array $grid, $months_shown, $top = 5) {
    $ranked = [];
    foreach ($categories as $cat => $category) {
        $ranked[$cat] = $grid[$cat]['total'] ?? 0;
    }
    arsort($ranked);
    $series = [];
    $other = array_fill(1, 12, 0.0);
    $i = 0;
    foreach ($ranked as $cat => $total) {
        if ($total <= 0) {
            continue;
        }
        $months = $grid[$cat]['months'];
        if ($i < $top) {
            $data = [];
            for ($m = 1; $m <= 12; $m++) {
                $data[] = $m <= $months_shown ? round($months[$m], 2) : null;
            }
            $series[] = ['label' => html_entity_decode($categories[$cat]['category_name'], ENT_QUOTES, 'UTF-8'), 'data' => $data, 'tone' => reportPalette($i), 'stack' => 'year'];
        } else {
            foreach ($months as $m => $v) {
                $other[$m] += $v;
            }
        }
        $i++;
    }
    if (array_sum($other) > 0) {
        $data = [];
        for ($m = 1; $m <= 12; $m++) {
            $data[] = $m <= $months_shown ? round($other[$m], 2) : null;
        }
        $series[] = ['label' => 'Other', 'data' => $data, 'tone' => 'faint', 'stack' => 'year'];
    }
    return $series;
}

/**
 * A year of a category grid as a table - a row per category with each month,
 * the year and its share, a totals row, and each amount opening the list it
 * came from ($list: income.php or expenses.php).
 */
function reportCategoryYearTable(array $categories, array $grid, $year, $list, $total_label, $uncategorised = '') {
    $columns = reportYearColumns('month');
    $totals = reportGridTotals($grid);
    $html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0 itflow-report-table itflow-report-grid" data-report-sort>'
        . '<thead class="table-light"><tr><th data-sort="text">Category</th>';
    foreach ($columns as [$label]) {
        $html .= '<th class="text-end" data-sort="num">' . $label . '</th>';
    }
    $html .= '<th class="text-end" data-sort="num">' . intval($year) . '</th><th class="text-end" data-sort="num">Share</th></tr></thead><tbody>';
    if (!$categories) {
        $html .= '<tr><td colspan="15" class="text-center text-body-secondary py-4">Nothing in ' . intval($year) . '.</td></tr>';
    }
    foreach ($categories as $cat => $category) {
        $row = reportGridRow($grid, $cat);
        $html .= '<tr><td data-value="' . escapeHtml(mb_strtolower($category['category_name'])) . '">' . escapeHtml($category['category_name'])
            . (!empty($category['category_archived_at']) ? ' <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal">Archived</span>' : '') . '</td>';
        foreach ($columns as $i => [$label, $first, $last]) {
            [$dtf, $dtt] = reportColumnDates($year, $first, $last);
            $url = $cat || $uncategorised !== '' ? "/agent/$list?" . ($cat ? "category=$cat" : $uncategorised) . "&canned_date=custom&dtf=$dtf&dtt=$dtt" : '';
            $html .= '<td class="text-end" data-value="' . round($row['months'][$i], 2) . '">' . reportGridAmount($row['months'][$i], $url) . '</td>';
        }
        $url = $cat || $uncategorised !== '' ? "/agent/$list?" . ($cat ? "category=$cat" : $uncategorised) . "&canned_date=custom&dtf=$year-01-01&dtt=$year-12-31" : '';
        $share = reportShare($row['total'], $totals['total'], 1);
        $html .= '<td class="text-end fw-semibold" data-value="' . round($row['total'], 2) . '">' . reportGridAmount($row['total'], $url) . '</td>'
            . '<td class="text-end text-body-secondary" data-value="' . ($share ?? 0) . '">' . reportPercent($share) . '</td></tr>';
    }
    $html .= '</tbody><tfoot><tr class="fw-semibold"><td>' . escapeHtml($total_label) . '</td>';
    foreach ($columns as $i => [$label, $first, $last]) {
        [$dtf, $dtt] = reportColumnDates($year, $first, $last);
        $html .= '<td class="text-end">' . reportGridAmount($totals['months'][$i], "/agent/$list?canned_date=custom&dtf=$dtf&dtt=$dtt") . '</td>';
    }
    $html .= '<td class="text-end">' . reportGridAmount($totals['total'], "/agent/$list?canned_date=custom&dtf=$year-01-01&dtt=$year-12-31") . '</td><td class="text-end">' . ($totals['total'] ? '100%' : '') . '</td></tr></tfoot></table></div>';
    return $html;
}

/** The same grid as CSV rows: category, each month, the year. */
function reportCategoryYearCsv(array $categories, array $grid, $year, $name, $total_label) {
    $columns = ['name' => ['label' => 'Category']];
    foreach (reportYearColumns('month') as $i => [$label]) {
        $columns["m$i"] = ['label' => $label, 'type' => 'money'];
    }
    $columns['total'] = ['label' => (string) $year, 'type' => 'money'];
    $rows = [];
    foreach ($categories as $cat => $category) {
        $line = reportGridRow($grid, $cat);
        $row = ['name' => $category['category_name'], 'total' => $line['total']];
        foreach ($line['months'] as $m => $v) {
            $row["m$m"] = $v;
        }
        $rows[] = $row;
    }
    $totals = reportGridTotals($grid);
    $total_row = ['name' => $total_label, 'total' => $totals['total']];
    foreach ($totals['months'] as $m => $v) {
        $total_row["m$m"] = $v;
    }
    reportCsv($name, $columns, $rows, $total_row);
}

/*
 * ---------- tickets ----------
 */

/** SQL on tickets for the filters: archived left out, the clients this user may see, and the client and agent picked. */
function reportTicketSql($client_id = 0, $agent_id = 0) {
    $agent_id = intval($agent_id);
    return "tickets.ticket_archived_at IS NULL" . reportClientSql('tickets.ticket_client_id', $client_id)
        . ($agent_id ? " AND tickets.ticket_assigned_to = $agent_id" : '');
}

/** The middle value - half are above it - or null for none. Steadier than an average when a few tickets sat open for weeks. */
function reportMedian(array $values) {
    $values = array_values(array_filter($values, function ($v) {
        return $v !== null;
    }));
    $count = count($values);
    if (!$count) {
        return null;
    }
    sort($values);
    $mid = intdiv($count, 2);
    return $count % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
}

/** A column of numbers from a query - for medians. */
function reportColumn($sql) {
    global $mysqli;
    $values = [];
    $result = mysqli_query($mysqli, $sql);
    while ($row = mysqli_fetch_row($result)) {
        if ($row[0] !== null) {
            $values[] = floatval($row[0]);
        }
    }
    return $values;
}

/** Ticket categories by id - every one, archived included, as old tickets carry them. */
function reportTicketCategories() {
    global $mysqli;
    $categories = [];
    $sql = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket'");
    while ($row = mysqli_fetch_assoc($sql)) {
        $categories[intval($row['category_id'])] = $row['category_name'];
    }
    return $categories;
}

/** How long open tickets have been open - the buckets the aging report counts in, with the most days each takes. */
const REPORT_AGE_BUCKETS = [
    'day' => ['Under a day', 0],
    '3' => ['1–3 days', 3],
    '7' => ['4–7 days', 7],
    '30' => ['8–30 days', 30],
    '90' => ['31–90 days', 90],
    'old' => ['Over 90 days', null],
];

/** The bucket for a ticket open $days days. */
function reportAgeBucket($days) {
    if ($days < 1) {
        return 'day';
    }
    foreach (REPORT_AGE_BUCKETS as $key => [$label, $max]) {
        if ($max !== null && $max > 0 && $days <= $max) {
            return $key;
        }
    }
    return 'old';
}

/**
 * How tickets with an SLA did, from a WHERE on tickets: how many, and met /
 * missed / still open for the response and the resolution targets. The
 * percentages count only tickets whose target has been judged - one still
 * waiting is neither a hit nor a miss.
 */
function reportSlaCompliance($where) {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
        COUNT(ticket_id) AS tickets,
        SUM(ticket_response_sla_met = 1) AS response_met,
        SUM(ticket_response_sla_met = 0) AS response_missed,
        SUM(ticket_resolution_sla_met = 1) AS resolution_met,
        SUM(ticket_resolution_sla_met = 0) AS resolution_missed,
        SUM(ticket_resolution_sla_met IS NULL AND ticket_resolution_due_at IS NOT NULL) AS resolution_pending
        FROM tickets WHERE ticket_sla_id > 0 AND $where"));
    return reportSlaFigures($row);
}

/** The percentages for a row of met / missed counts (as reportSlaCompliance() selects them). */
function reportSlaFigures($row) {
    $out = [];
    foreach (['tickets', 'response_met', 'response_missed', 'resolution_met', 'resolution_missed', 'resolution_pending'] as $key) {
        $out[$key] = intval($row[$key] ?? 0);
    }
    $out['response_percent'] = reportShare($out['response_met'], $out['response_met'] + $out['response_missed'], 1);
    $out['resolution_percent'] = reportShare($out['resolution_met'], $out['resolution_met'] + $out['resolution_missed'], 1);
    return $out;
}

/** " · 3 late" after an SLA share, or nothing when none were late. */
function reportLateHtml($late) {
    $late = intval($late);
    return $late ? ' <span class="small text-body-tertiary fw-normal">· ' . reportCount($late) . ' late</span>' : '';
}

/** The SELECT list reportSlaFigures() reads - for grouped queries. */
const REPORT_SLA_COLUMNS = "COUNT(ticket_id) AS tickets,
    SUM(ticket_response_sla_met = 1) AS response_met, SUM(ticket_response_sla_met = 0) AS response_missed,
    SUM(ticket_resolution_sla_met = 1) AS resolution_met, SUM(ticket_resolution_sla_met = 0) AS resolution_missed,
    SUM(ticket_resolution_sla_met IS NULL AND ticket_resolution_due_at IS NOT NULL) AS resolution_pending";

/**
 * Working days between two dates (up to today): the business days set in
 * Admin > SLA, less closure days. Monday to Friday when none are set.
 */
function reportWorkingDays($start, $end) {
    $end = min($end, date('Y-m-d'));
    $settings = getSlaSettings();
    $days = $settings['business_days'] ?: [1, 2, 3, 4, 5];
    $holidays = getBusinessHolidays();
    $count = 0;
    $cursor = new DateTime($start);
    $last = new DateTime($end);
    for ($guard = 0; $cursor <= $last && $guard < 4000; $guard++) {
        if (in_array(intval($cursor->format('N')), $days, true) && !isset($holidays[$cursor->format('Y-m-d')])) {
            $count++;
        }
        $cursor->modify('+1 day');
    }
    return $count;
}

/** Hours in a working day, from the business hours set for SLAs - 8 when they are not set. */
function reportWorkdayHours() {
    $settings = getSlaSettings();
    $start = strtotime('1970-01-01 ' . ($settings['business_hours_start'] ?: '00:00:00') . ' UTC');
    $end = strtotime('1970-01-01 ' . ($settings['business_hours_end'] ?: '00:00:00') . ' UTC');
    $hours = ($end - $start) / 3600;
    return $hours > 0 && $hours <= 24 ? round($hours, 2) : 8.0;
}

/**
 * When tickets come in - a weekday by hour grid, darker for more. $counts
 * is [ISO weekday 1-7][hour 0-23] => count.
 */
function reportHeatmap(array $counts, $noun = 'ticket') {
    $max = 1;
    foreach ($counts as $hours) {
        $max = max($max, ...array_values($hours ?: [0]));
    }
    $html = '<div class="table-responsive"><table class="itflow-report-heat" aria-label="When ' . $noun . 's came in, by weekday and hour"><thead><tr><th></th>';
    for ($h = 0; $h < 24; $h++) {
        $html .= '<th scope="col">' . ($h % 3 === 0 ? date('ga', mktime($h, 0, 0)) : '') . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    for ($d = 1; $d <= 7; $d++) {
        $day = date('D', strtotime("Monday this week +" . ($d - 1) . " days"));
        $html .= '<tr><th scope="row">' . $day . '</th>';
        for ($h = 0; $h < 24; $h++) {
            $n = intval($counts[$d][$h] ?? 0);
            $html .= '<td style="--heat: ' . round($n / $max, 3) . '" title="' . $day . ' ' . date('g A', mktime($h, 0, 0)) . ': ' . $n . ' ' . $noun . ($n === 1 ? '' : 's') . '"></td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/*
 * ---------- assets, renewals and documentation ----------
 */

/** Statuses that mean the asset is gone - left out of lifecycle figures. */
const REPORT_ASSET_GONE = ['Lost', 'Stolen', 'Retired'];

/**
 * Operating systems past their maker's end of support, as [pattern, name],
 * matched against an asset's OS. Only versions whose support has plainly
 * ended (late 2026) - long-term-servicing editions are left alone. Add to it
 * as more versions reach the end.
 */
const REPORT_OS_END_OF_LIFE = [
    ['/\bwindows\s+(xp|vista)\b/i', 'Windows XP / Vista'],
    ['/\bwindows\s+(7|8(\.1)?)\b/i', 'Windows 7 / 8'],
    ['/\bwindows\s+10\b(?!.*\bltsc\b)/i', 'Windows 10'],
    ['/\bserver\s+(2000|2003|2008|2012)\b/i', 'Windows Server 2012 or older'],
    ['/\b(mac\s?os|os\s?x)\s*(x\s*)?(10\.\d+|11|12|13)\b/i', 'macOS 13 or older'],
    ['/\bcentos\b(?!\s*stream\s*(9|10))/i', 'CentOS'],
    ['/\bubuntu\b\D*(1\d|20)\.\d\d\b/i', 'Ubuntu 20.04 or older'],
    ['/\bdebian\b\D*\b([6-9]|1[01])\b/i', 'Debian 11 or older'],
];

/** The end-of-support name for an asset's OS ('Windows 10'), or '' while it is supported or unknown. */
function reportOsEndOfLife($os) {
    $os = trim((string) $os);
    if ($os === '') {
        return '';
    }
    foreach (REPORT_OS_END_OF_LIFE as [$pattern, $name]) {
        if (preg_match($pattern, $os)) {
            return $name;
        }
    }
    return '';
}

/**
 * Assets still in use (not archived, not lost, stolen or retired) at active
 * clients, with what their lifecycle needs: 'age_days' from purchase (else
 * install), 'warranty' (assetWarranty()) and 'os_eol' (reportOsEndOfLife()).
 * A virtual machine has no hardware to age or cover, so neither is set.
 */
function reportLifecycleAssets($client_id = 0, $type = '') {
    global $mysqli;
    $gone = "'" . implode("', '", REPORT_ASSET_GONE) . "'";
    $type_sql = $type !== '' ? " AND asset_type = '" . escapeSql($type) . "'" : '';
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_os, asset_status, asset_purchase_date, asset_install_date,
            asset_warranty_expire, asset_client_id, client_name, client_abbreviation, contact_name, location_name
        FROM assets
        JOIN clients ON asset_client_id = client_id
        LEFT JOIN contacts ON asset_contact_id = contact_id
        LEFT JOIN locations ON asset_location_id = location_id
        WHERE asset_archived_at IS NULL AND client_archived_at IS NULL AND COALESCE(asset_status, '') NOT IN ($gone)"
        . reportClientSql('asset_client_id', $client_id) . $type_sql . "
        ORDER BY client_name, asset_name");
    $today = new DateTime('today');
    while ($row = mysqli_fetch_assoc($sql)) {
        $physical = $row['asset_type'] !== 'Virtual Machine';
        $row['age_date'] = null;
        $row['age_days'] = null;
        foreach (['asset_purchase_date', 'asset_install_date'] as $column) {
            $date = $physical ? DateTime::createFromFormat('!Y-m-d', substr((string) $row[$column], 0, 10)) : false;
            if ($date && $date->format('Y') > 1900) {
                $row['age_date'] = $date->format('Y-m-d');
                $row['age_days'] = max(0, (int) $date->diff($today)->format('%r%a'));
                break;
            }
        }
        $row['warranty'] = $physical ? assetWarranty($row['asset_warranty_expire']) : null;
        $row['os_eol'] = reportOsEndOfLife($row['asset_os']);
        $rows[] = $row;
    }
    return $rows;
}

/** An asset's type tile and name, opening its page. */
function reportAssetCell($row) {
    return '<a class="d-inline-flex align-items-center gap-2 text-body text-decoration-none" href="/agent/asset.php?client_id=' . intval($row['asset_client_id']) . '&asset_id=' . intval($row['asset_id']) . '">'
        . '<span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-' . assetTypeTone($row['asset_type']) . '" aria-hidden="true"><i class="fas fa-fw fa-' . getAssetIcon($row['asset_type']) . '"></i></span>'
        . '<span class="fw-semibold text-nowrap">' . escapeHtml($row['asset_name']) . '</span></a>';
}

/** "3.4 yrs" from days, or '' without. */
function reportYears($days, $decimals = 1) {
    return $days === null ? '' : number_format($days / 365.25, $decimals) . ' yrs';
}

/** What comes up for renewal, and how each kind is shown. */
const REPORT_RENEWAL_KINDS = [
    'domain' => ['label' => 'Domains', 'one' => 'Domain', 'tone' => 'accent'],
    'certificate' => ['label' => 'Certificates', 'one' => 'Certificate', 'tone' => 'purple'],
    'license' => ['label' => 'Licenses', 'one' => 'License', 'tone' => 'teal'],
    'warranty' => ['label' => 'Warranties', 'one' => 'Warranty', 'tone' => 'orange'],
];

/** How far back a lapsed renewal still shows - older ones are on each list's Expired view. */
const REPORT_RENEWAL_LAPSED_DAYS = 90;

/**
 * Domains, certificates, licenses and warranties due in the next $ahead days,
 * or lapsed in the last REPORT_RENEWAL_LAPSED_DAYS, soonest first. Each row:
 * kind, id, name, detail, client_id, client, abbr, date, days (negative once
 * lapsed), auto (renews itself), url, tile. Archived items, archived clients
 * and assets that are gone are left out.
 */
function reportRenewals($client_id, $ahead, array $kinds = []) {
    global $mysqli;
    $ahead = intval($ahead);
    $back = REPORT_RENEWAL_LAPSED_DAYS;
    $kinds = $kinds ?: array_keys(REPORT_RENEWAL_KINDS);
    $window = function ($column) use ($ahead, $back) {
        return "$column BETWEEN CURDATE() - INTERVAL $back DAY AND CURDATE() + INTERVAL $ahead DAY";
    };
    $today = new DateTime('today');
    $rows = [];
    $add = function ($kind, $id, $name, $detail, $row, $date, $auto, $url, $tile) use (&$rows, $today) {
        $due = DateTime::createFromFormat('!Y-m-d', substr((string) $date, 0, 10));
        if (!$due) {
            return;
        }
        $rows[] = [
            'kind' => $kind, 'id' => intval($id), 'name' => (string) $name, 'detail' => trim((string) $detail, " ·"),
            'client_id' => intval($row['client_id']), 'client' => $row['client_name'], 'abbr' => $row['client_abbreviation'],
            'date' => $due->format('Y-m-d'), 'days' => (int) $today->diff($due)->format('%r%a'), 'auto' => (bool) $auto, 'url' => $url, 'tile' => $tile,
        ];
    };

    if (in_array('domain', $kinds, true)) {
        $sql = mysqli_query($mysqli, "SELECT domain_id, domain_name, domain_expire, domain_auto_renew, vendor_name, client_id, client_name, client_abbreviation
            FROM domains JOIN clients ON domain_client_id = client_id LEFT JOIN vendors ON domain_registrar = vendor_id
            WHERE domain_archived_at IS NULL AND client_archived_at IS NULL AND " . $window('domain_expire') . reportClientSql('domain_client_id', $client_id));
        while ($row = mysqli_fetch_assoc($sql)) {
            $auto = intval($row['domain_auto_renew']) === 1;
            $add('domain', $row['domain_id'], $row['domain_name'], ($auto ? 'Auto-renew' : 'Manual renewal') . ($row['vendor_name'] ? ' · ' . $row['vendor_name'] : ''),
                $row, $row['domain_expire'], $auto, '/agent/domains.php?client_id=' . intval($row['client_id']) . '&open=' . intval($row['domain_id']), domainTile('sm'));
        }
    }
    if (in_array('certificate', $kinds, true)) {
        $sql = mysqli_query($mysqli, "SELECT certificate_id, certificate_name, certificate_domain, certificate_issued_by, certificate_issued_at, certificate_expire, client_id, client_name, client_abbreviation
            FROM certificates JOIN clients ON certificate_client_id = client_id
            WHERE certificate_archived_at IS NULL AND client_archived_at IS NULL AND " . $window('certificate_expire') . reportClientSql('certificate_client_id', $client_id));
        while ($row = mysqli_fetch_assoc($sql)) {
            $auto = certificateShortLived($row);
            $add('certificate', $row['certificate_id'], $row['certificate_name'], ($auto ? 'Short-lived, renews itself' : 'Manual renewal') . ($row['certificate_issued_by'] ? ' · ' . $row['certificate_issued_by'] : ''),
                $row, $row['certificate_expire'], $auto, '/agent/certificates.php?client_id=' . intval($row['client_id']) . '&open=' . intval($row['certificate_id']), certificateTile('sm'));
        }
    }
    if (in_array('license', $kinds, true)) {
        $sql = mysqli_query($mysqli, "SELECT software_id, software_name, software_version, software_type, software_license_type, software_seats, software_expire, vendor_name, client_id, client_name, client_abbreviation
            FROM software JOIN clients ON software_client_id = client_id LEFT JOIN vendors ON software_vendor_id = vendor_id
            WHERE software_archived_at IS NULL AND client_archived_at IS NULL AND " . $window('software_expire') . reportClientSql('software_client_id', $client_id));
        while ($row = mysqli_fetch_assoc($sql)) {
            $seats = intval($row['software_seats']);
            $add('license', $row['software_id'], trim($row['software_name'] . ' ' . $row['software_version']),
                implode(' · ', array_filter([$row['software_license_type'], $seats ? $seats . ' seat' . ($seats === 1 ? '' : 's') : '', $row['vendor_name']])),
                $row, $row['software_expire'], false, '/agent/license.php?client_id=' . intval($row['client_id']) . '&software_id=' . intval($row['software_id']), softwareTile($row['software_type'], 'sm'));
        }
    }
    if (in_array('warranty', $kinds, true)) {
        $gone = "'" . implode("', '", REPORT_ASSET_GONE) . "'";
        $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_warranty_expire, client_id, client_name, client_abbreviation
            FROM assets JOIN clients ON asset_client_id = client_id
            WHERE asset_archived_at IS NULL AND client_archived_at IS NULL AND asset_type != 'Virtual Machine' AND COALESCE(asset_status, '') NOT IN ($gone)
            AND " . $window('asset_warranty_expire') . reportClientSql('asset_client_id', $client_id));
        while ($row = mysqli_fetch_assoc($sql)) {
            $add('warranty', $row['asset_id'], $row['asset_name'], $row['asset_type'] . ' · ' . trim($row['asset_make'] . ' ' . $row['asset_model']),
                $row, $row['asset_warranty_expire'], false, '/agent/asset.php?client_id=' . intval($row['client_id']) . '&asset_id=' . intval($row['asset_id']),
                '<span class="itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-' . assetTypeTone($row['asset_type']) . '" aria-hidden="true"><i class="fas fa-fw fa-' . getAssetIcon($row['asset_type']) . '"></i></span>');
        }
    }

    usort($rows, function ($a, $b) {
        return $a['days'] <=> $b['days'] ?: strcasecmp($a['client'], $b['client']) ?: strcasecmp($a['name'], $b['name']);
    });
    return $rows;
}

/** When a renewal falls due, as a soft pill - red lapsed or within a week, amber within 30 days, blue when it renews itself. */
function reportDuePill($days, $auto = false) {
    $days = intval($days);
    if ($days < 0) {
        $ago = -$days;
        $tone = 'danger';
        $icon = 'fa-calendar-times';
        $text = 'Lapsed ' . ($ago === 1 ? 'yesterday' : "$ago days ago");
    } else {
        $text = $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : ($days <= 60 ? "In $days days" : 'In ' . round($days / 30.4) . ' months'));
        $tone = $auto ? 'info' : ($days <= 7 ? 'danger' : ($days <= 30 ? 'warning' : 'secondary'));
        $icon = $auto ? 'fa-sync-alt' : ($days <= 30 ? 'fa-hourglass-half' : 'fa-calendar');
    }
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal text-nowrap"'
        . ($auto ? ' title="Renews itself"' : '') . '><i class="fas ' . $icon . ' me-1"></i>' . $text . '</span>';
}

/** Placed and owned pass when at least this share of assets has a location / an owner. */
const REPORT_COVERAGE_SHARE = 80;

/**
 * What documentation coverage checks: each with its label, the client page
 * to add it on and, where it needs one, a line on how it passes. Credentials
 * only for agents who may see them.
 */
function reportCoverageChecks() {
    $share = REPORT_COVERAGE_SHARE;
    $checks = [
        'primary_contact' => ['label' => 'Primary contact', 'page' => 'contacts.php'],
        'billing_contact' => ['label' => 'Billing contact', 'page' => 'contacts.php'],
        'primary_location' => ['label' => 'Primary location', 'page' => 'locations.php'],
        'assets' => ['label' => 'Assets', 'page' => 'assets.php'],
        'asset_locations' => ['label' => 'Assets placed', 'page' => 'assets.php', 'title' => "At least $share% of assets have a location"],
        'device_owners' => ['label' => 'Device owners', 'page' => 'assets.php', 'title' => "At least $share% of laptops, desktops, phones and tablets have a contact"],
        'networks' => ['label' => 'Networks', 'page' => 'networks.php'],
        'credentials' => ['label' => 'Credentials', 'page' => 'credentials.php'],
        'documents' => ['label' => 'Documents', 'page' => 'files.php'],
        'domains' => ['label' => 'Domains', 'page' => 'domains.php'],
        'services' => ['label' => 'Services', 'page' => 'services.php'],
        'licenses' => ['label' => 'Licenses', 'page' => 'software.php'],
    ];
    if (reportPermission('module_credential') < 1) {
        unset($checks['credentials']);
    }
    return $checks;
}

/**
 * Documentation coverage for active clients (or one), keyed by client id:
 * the counts behind each check, 'checks' (true passes, false is missing,
 * null does not apply - no user devices to give owners), 'missing', and
 * 'score', the share of the checks that apply which pass.
 */
function reportCoverage($client_id = 0) {
    global $mysqli;
    $checks = reportCoverageChecks();
    $client_id = intval($client_id);

    $clients = [];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL" . reportClientSql('client_id', $client_id) . " ORDER BY client_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[intval($row['client_id'])] = ['client_id' => intval($row['client_id']), 'client' => $row['client_name'], 'abbr' => $row['client_abbreviation'],
            'contacts' => 0, 'primary' => 0, 'billing' => 0, 'locations' => 0, 'primary_loc' => 0, 'assets' => 0, 'placed' => 0, 'devices' => 0, 'owned' => 0,
            'networks' => 0, 'credentials' => 0, 'documents' => 0, 'domains' => 0, 'services' => 0, 'licenses' => 0];
    }
    if (!$clients) {
        return [];
    }
    $only = $client_id ? " = $client_id" : ' > 0';

    $fill = function ($query, array $fields) use (&$clients, $mysqli) {
        $sql = mysqli_query($mysqli, $query);
        while ($row = mysqli_fetch_assoc($sql)) {
            $id = intval($row['c']);
            if (isset($clients[$id])) {
                foreach ($fields as $field) {
                    $clients[$id][$field] = intval($row[$field]);
                }
            }
        }
    };
    $devices = "'" . implode("', '", SERVICE_USER_DEVICE_TYPES) . "'";
    $gone = "'" . implode("', '", REPORT_ASSET_GONE) . "'";
    $fill("SELECT contact_client_id AS c, COUNT(*) AS contacts, SUM(contact_primary = 1) AS `primary`, SUM(contact_billing = 1) AS billing
        FROM contacts WHERE contact_archived_at IS NULL AND contact_client_id $only GROUP BY contact_client_id", ['contacts', 'primary', 'billing']);
    $fill("SELECT location_client_id AS c, COUNT(*) AS locations, SUM(location_primary = 1) AS primary_loc
        FROM locations WHERE location_archived_at IS NULL AND location_client_id $only GROUP BY location_client_id", ['locations', 'primary_loc']);
    $fill("SELECT asset_client_id AS c, COUNT(*) AS assets, SUM(asset_location_id > 0) AS placed,
            SUM(asset_type IN ($devices)) AS devices, SUM(asset_type IN ($devices) AND asset_contact_id > 0) AS owned
        FROM assets WHERE asset_archived_at IS NULL AND COALESCE(asset_status, '') NOT IN ($gone) AND asset_client_id $only GROUP BY asset_client_id", ['assets', 'placed', 'devices', 'owned']);
    foreach (['networks' => ['networks', 'network'], 'documents' => ['documents', 'document'], 'domains' => ['domains', 'domain'], 'services' => ['services', 'service'],
        'licenses' => ['software', 'software'], 'credentials' => ['credentials', 'credential']] as $field => [$table, $prefix]) {
        if (isset($checks[$field])) {
            $fill("SELECT {$prefix}_client_id AS c, COUNT(*) AS $field FROM $table WHERE {$prefix}_archived_at IS NULL AND {$prefix}_client_id $only GROUP BY {$prefix}_client_id", [$field]);
        }
    }

    foreach ($clients as &$client) {
        $client['placed_share'] = $client['assets'] ? $client['placed'] / $client['assets'] * 100 : null;
        $client['owned_share'] = $client['devices'] ? $client['owned'] / $client['devices'] * 100 : null;
        $result = [
            'primary_contact' => $client['primary'] > 0,
            'billing_contact' => $client['billing'] > 0,
            'primary_location' => $client['primary_loc'] > 0,
            'assets' => $client['assets'] > 0,
            'asset_locations' => $client['placed_share'] === null ? null : $client['placed_share'] >= REPORT_COVERAGE_SHARE,
            'device_owners' => $client['owned_share'] === null ? null : $client['owned_share'] >= REPORT_COVERAGE_SHARE,
            'networks' => $client['networks'] > 0,
            'credentials' => $client['credentials'] > 0,
            'documents' => $client['documents'] > 0,
            'domains' => $client['domains'] > 0,
            'services' => $client['services'] > 0,
            'licenses' => $client['licenses'] > 0,
        ];
        $client['checks'] = array_intersect_key($result, $checks);
        $applies = array_filter($client['checks'], function ($v) { return $v !== null; });
        $client['passed'] = count(array_filter($applies));
        $client['applies'] = count($applies);
        $client['score'] = reportShare($client['passed'], $client['applies'], 0);
        $client['missing'] = array_keys(array_filter($client['checks'], function ($v) { return $v === false; }));
    }
    unset($client);
    return $clients;
}

/** What a client is missing, as pills that open the page to add each on. */
function reportCoverageMissingHtml($client) {
    $checks = reportCoverageChecks();
    if (!$client['missing']) {
        return '<span class="text-success-emphasis small"><i class="fas fa-check-circle me-1"></i>Nothing</span>';
    }
    $html = '<span class="d-inline-flex flex-wrap gap-1">';
    foreach ($client['missing'] as $key) {
        $html .= '<a class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal text-decoration-none" href="/agent/' . $checks[$key]['page'] . '?client_id=' . intval($client['client_id']) . '"'
            . (!empty($checks[$key]['title']) ? ' title="' . escapeHtml($checks[$key]['title']) . '"' : '') . '>' . escapeHtml($checks[$key]['label']) . '</a>';
    }
    return $html . '</span>';
}
