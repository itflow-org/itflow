<?php

/*
 * Dashboard.
 *
 * Today's work first: what needs attention right now, then my open tickets,
 * then the money for the chosen period. Each section shows to anyone whose
 * role can read its module - there are no per-user switches any more.
 *
 * The numbers come from functions/dashboard.php; the charts are drawn by
 * js/dashboard.js from the data handed over at the bottom of this page.
 */

require_once "includes/inc_all.php";

$access = dashboardAccess();
$show_money = $access['financial'] || $access['sales'];

// ?year=2025 is how the old dashboard picked a year
$period_options = dashboardPeriodOptions();
$period_key = $_GET['period'] ?? (isset($_GET['year']) ? 'year-' . intval($_GET['year']) : 'ytd');
if (!isset($period_options[$period_key])) {
    $period_key = 'ytd';
}
$period = dashboardPeriod($period_key);

$attention = ($access['tickets'] || $access['sales'] || $access['support']) ? dashboardAttention($access) : null;
$my_tickets = $access['tickets'] ? dashboardMyTickets(8) : null;
$money_cards = $show_money ? dashboardMoneyCards($access, $period) : [];
$schedule = $access['tickets'] ? dashboardSchedule() : null;
$time_week = $access['tickets'] ? dashboardTimeThisWeek() : null;
$team = ($access['tickets'] && $session_is_admin) ? dashboardTeamWorkload() : null;
$aging = $access['sales'] ? dashboardReceivablesAging() : null;
$top_clients = $access['financial'] ? dashboardTopClients($period) : null;

$chart_data = [];
if ($access['tickets']) {
    $chart_data['ticket_trend'] = dashboardTicketTrend();
    $chart_data['time_week'] = $time_week['days'];
}
if ($access['financial']) {
    $chart_data['cash_flow'] = dashboardCashFlow($period);
    $chart_data['breakdowns'] = dashboardBreakdowns($period);
    $chart_data['trips'] = dashboardTrips($period);
    $chart_data['currency'] = $session_company_currency;
    $accounts = dashboardAccountBalances();
    $latest_income = dashboardLatestIncome(5);
    $latest_expenses = dashboardLatestExpenses(5);
}

$hour = intval(date('G'));
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$first_name = explode(' ', trim(stripslashes($session_name)))[0];

$priority_colors = ['Urgent' => 'dark', 'High' => 'danger', 'Medium' => 'warning', 'Low' => 'info'];

// One plain sentence on the state of the day, under the greeting
$summary = 'It\'s ' . date('l, F j') . '.';
if ($my_tickets !== null) {
    $open = $my_tickets['total'];
    if ($open === 0) {
        $summary .= ' No open tickets are assigned to you.';
    } else {
        $summary .= " You have $open open ticket" . ($open === 1 ? '' : 's');
        $breached = $my_tickets['breached'];
        $summary .= $breached ? ', ' . ($breached === $open ? ($open === 1 ? 'and it is' : 'all') : "$breached of them") . ' past SLA.' : '.';
    }
}

// Light text colours for the severity of each attention item
$tone_text = ['danger' => 'text-danger-emphasis', 'warning' => 'text-warning-emphasis', 'info' => 'text-info-emphasis', 'secondary' => 'text-body', 'success' => 'text-success-emphasis'];

?>

<div class="itflow-dash">

<div class="d-flex flex-wrap align-items-end gap-3 mb-4">
    <div class="me-auto">
        <h1 class="itflow-dash-greeting mb-1"><?= escapeHtml("$greeting, $first_name") ?></h1>
        <p class="text-body-secondary mb-0"><?= escapeHtml($summary) ?></p>
    </div>
    <?php if ($access['financial']) { ?>
        <form class="d-flex align-items-center gap-2">
            <label for="dashboardPeriod" class="small text-body-secondary mb-0">Period</label>
            <select id="dashboardPeriod" name="period" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <?php foreach ($period_options as $option_key => $option_label) { ?>
                    <option value="<?= escapeHtml($option_key) ?>" <?php if ($option_key === $period['key']) { echo 'selected'; } ?>><?= escapeHtml($option_label) ?></option>
                <?php } ?>
            </select>
        </form>
    <?php } ?>
</div>

<?php if ($attention !== null) { ?>

    <section class="mb-4" aria-labelledby="dashAttentionHeading">
        <h2 id="dashAttentionHeading" class="itflow-dash-section mb-2">Needs attention</h2>
        <div class="card mb-0 overflow-hidden">
            <div class="itflow-dash-rail">
                <?php if ($attention) { ?>
                    <?php foreach ($attention as $item) {
                        $tone = $item['tone']; ?>
                        <a href="<?= escapeHtml($item['url']) ?>" class="itflow-dash-rail-item" style="--itflow-dash-tone: var(--bs-<?= $tone ?>)">
                            <span class="itflow-dash-rail-count <?= $tone_text[$tone] ?? 'text-body' ?>"><?= number_format(intval($item['count'])) ?></span>
                            <span class="itflow-dash-rail-label">
                                <i class="fas fa-fw fa-<?= $item['icon'] ?> <?= $tone_text[$tone] ?? '' ?>" aria-hidden="true"></i><?= escapeHtml($item['label']) ?>
                            </span>
                            <?php if (!empty($item['detail'])) { ?>
                                <span class="itflow-dash-rail-detail"><?= escapeHtml($item['detail']) ?></span>
                            <?php } ?>
                        </a>
                    <?php } ?>
                <?php } else { ?>
                    <div class="itflow-dash-rail-item itflow-dash-rail-clear" style="--itflow-dash-tone: var(--bs-success)">
                        <span class="itflow-dash-rail-label text-body fw-semibold">
                            <i class="fas fa-fw fa-check-circle text-success-emphasis" aria-hidden="true"></i>Nothing needs attention
                        </span>
                        <span class="itflow-dash-rail-detail">No breached SLAs, unassigned tickets, overdue invoices, or anything expiring in the next 30 days.</span>
                    </div>
                <?php } ?>
            </div>
        </div>
    </section>

<?php } ?>

<?php if ($my_tickets !== null) { ?>

    <h2 class="itflow-dash-section mb-2">Your day</h2>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="card h-100 mb-0 bg-dark">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto">Open and assigned to you</h3>
                    <?php if ($my_tickets['total']) { ?>
                        <a class="small text-light" href="/agent/tickets.php?assigned=<?= intval($session_user_id) ?>">View all <?= $my_tickets['total'] ?></a>
                    <?php } ?>
                </div>

                <?php if ($my_tickets['tickets']) { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($my_tickets['tickets'] as $ticket) {
                            $ticket_priority = $ticket['ticket_priority'];
                            $ticket_url = '/agent/ticket.php?' . http_build_query(array_filter([
                                'client_id' => intval($ticket['ticket_client_id']) ?: null,
                                'ticket_id' => intval($ticket['ticket_id']),
                            ]));
                            $status_color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $ticket['ticket_status_color']) ? $ticket['ticket_status_color'] : '#6c757d';
                            $last_activity = $ticket['ticket_updated_at'] ?: $ticket['ticket_created_at'];
                            ?>
                            <a href="<?= escapeHtml($ticket_url) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2">
                                <span class="itflow-dash-dot bg-<?= $priority_colors[$ticket_priority] ?? 'secondary' ?>" title="<?= escapeHtml($ticket_priority) ?> priority"></span>
                                <span class="flex-grow-1 overflow-hidden">
                                    <span class="d-block text-truncate">
                                        <span class="small text-body-secondary me-2"><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) ?></span><?= escapeHtml($ticket['ticket_subject']) ?>
                                    </span>
                                    <span class="d-block small text-body-secondary text-truncate"><?= escapeHtml($ticket['client_name'] ?: 'No client') ?></span>
                                </span>
                                <?php if ($ticket['sla_breached']) { ?>
                                    <span class="badge rounded-pill text-bg-danger" title="SLA breached"><i class="fas fa-stopwatch me-1" aria-hidden="true"></i>SLA</span>
                                <?php } ?>
                                <span class="badge rounded-pill text-white d-none d-sm-inline" style="background-color: <?= $status_color ?>"><?= escapeHtml($ticket['ticket_status_name']) ?></span>
                                <span class="small text-body-secondary text-nowrap d-none d-md-inline" title="Last activity <?= escapeHtml($last_activity) ?>"><?= escapeHtml(timeAgo($last_activity)) ?></span>
                            </a>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="card-body text-center text-body-secondary py-5">
                        <i class="fas fa-mug-hot fa-2x mb-2 d-block" aria-hidden="true"></i>
                        No open tickets assigned to you.
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto">Your schedule</h3>
                    <a class="small" href="/agent/calendar.php">Open calendar</a>
                </div>
                <?php if ($schedule['items']) { ?>
                    <div class="itflow-dash-schedule" tabindex="0" aria-label="Your schedule for the next 7 days">
                        <?php
                        $schedule_day = null;
                        foreach ($schedule['items'] as $item) {
                            $item_day = dashboardDayLabel($item['when']);
                            if ($item_day !== $schedule_day) {
                                $schedule_day = $item_day; ?>
                                <div class="itflow-dash-schedule-day"><?= escapeHtml($item_day) ?></div>
                            <?php } ?>
                            <a class="itflow-dash-schedule-item<?= (!$item['all_day'] && !$item['ongoing'] && strtotime($item['when']) < time()) ? ' is-past' : '' ?><?= !empty($item['modal']) ? ' ajax-modal' : '' ?>" href="<?= escapeHtml($item['url']) ?>"<?php if (!empty($item['modal'])) { ?> data-modal-size="lg" data-modal-url="<?= escapeHtml($item['modal']) ?>"<?php } ?>>
                                <span class="itflow-dash-schedule-time">
                                    <?= $item['ongoing'] ? 'Ongoing' : ($item['all_day'] ? 'All day' : escapeHtml(date('g:i A', strtotime($item['when'])))) ?>
                                </span>
                                <span class="itflow-dash-schedule-mark" style="background-color: <?= $item['color'] ? escapeHtml($item['color']) : 'var(--itflow-accent)' ?>"></span>
                                <span class="flex-grow-1 overflow-hidden">
                                    <span class="d-block text-truncate">
                                        <?php if ($item['ref']) { ?><span class="small text-body-secondary me-2"><?= escapeHtml($item['ref']) ?></span><?php } ?><?= escapeHtml($item['title']) ?>
                                    </span>
                                    <?php if ($item['detail'] || $item['onsite']) { ?>
                                        <span class="d-block small text-body-secondary text-truncate">
                                            <?php if ($item['onsite']) { ?><span class="badge rounded-pill text-bg-secondary me-1">Onsite</span><?php } ?><?= escapeHtml($item['detail']) ?>
                                        </span>
                                    <?php } ?>
                                </span>
                            </a>
                        <?php } ?>
                    </div>
                    <?php if ($schedule['total'] > count($schedule['items'])) { ?>
                        <a class="card-footer small text-decoration-none py-2" href="/agent/calendar.php"><?= $schedule['total'] - count($schedule['items']) ?> more this week</a>
                    <?php } ?>
                <?php } else { ?>
                    <div class="card-body text-center text-body-secondary py-5">
                        <i class="far fa-calendar-check fa-2x mb-2 d-block" aria-hidden="true"></i>
                        Nothing scheduled in the next 7 days.
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto">Your time this week</h3>
                    <span class="small text-body-secondary">Last week <?= dashboardDuration($time_week['last_week']) ?></span>
                </div>
                <?php if ($time_week['total'] > 0) { ?>
                <div class="card-body pt-2 d-flex flex-column">
                    <span class="d-block itflow-dash-money-value mb-0"><?= dashboardDuration($time_week['total']) ?></span>
                    <span class="d-flex flex-wrap column-gap-3 small text-body-secondary mb-2">
                        <span><span class="itflow-dash-legend-swatch d-inline-block me-1" style="background-color: var(--itflow-accent)"></span>Billable <?= dashboardDuration($time_week['billable']) ?></span>
                        <span><span class="itflow-dash-legend-swatch d-inline-block me-1" style="background-color: var(--bs-tertiary-color)"></span>Not billable <?= dashboardDuration($time_week['total'] - $time_week['billable']) ?></span>
                    </span>
                    <div class="itflow-dash-chart itflow-dash-chart-sm flex-grow-1"><canvas id="dashTimeWeek" aria-label="Hours logged per day this week"></canvas></div>
                </div>
                <?php } else { ?>
                    <div class="card-body text-center text-body-secondary py-5">
                        <i class="far fa-clock fa-2x mb-2 d-block" aria-hidden="true"></i>
                        No time logged on tickets yet this week.
                    </div>
                <?php } ?>
            </div>
        </div>

        <?php if ($team !== null) {
            $team_max = max(1, intval($team['unassigned']), ...array_map('intval', array_column($team['techs'], 'open') ?: [0])); ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">Open tickets by tech</h3>
                    </div>
                    <div class="card-body pt-2">
                        <?php if ($team['techs'] || $team['unassigned']) { ?>
                            <div class="itflow-dash-bars">
                                <?php foreach ($team['techs'] as $tech) {
                                    $tech_open = intval($tech['open']);
                                    $tech_breached = intval($tech['breached']); ?>
                                    <a class="itflow-dash-bar" href="/agent/tickets.php?assigned=<?= intval($tech['user_id']) ?>">
                                        <span class="itflow-dash-bar-label"><?= escapeHtml($tech['user_name']) ?></span>
                                        <span class="itflow-dash-bar-value">
                                            <?php if ($tech_breached) { ?><span class="small fw-normal text-danger-emphasis me-2"><?= $tech_breached ?> past SLA</span><?php } ?><?= $tech_open ?>
                                        </span>
                                        <span class="itflow-dash-bar-track">
                                            <span class="itflow-dash-bar-fill" style="width: <?= round(($tech_open - $tech_breached) / $team_max * 100, 1) ?>%"></span><?php if ($tech_breached) { ?><span class="itflow-dash-bar-fill bg-danger" style="width: <?= round($tech_breached / $team_max * 100, 1) ?>%"></span><?php } ?>
                                        </span>
                                    </a>
                                <?php } ?>
                                <?php if ($team['unassigned']) { ?>
                                    <a class="itflow-dash-bar" href="/agent/tickets.php?assigned=unassigned">
                                        <span class="itflow-dash-bar-label fst-italic">Unassigned</span>
                                        <span class="itflow-dash-bar-value"><?= intval($team['unassigned']) ?></span>
                                        <span class="itflow-dash-bar-track"><span class="itflow-dash-bar-fill bg-warning" style="width: <?= round($team['unassigned'] / $team_max * 100, 1) ?>%"></span></span>
                                    </a>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="text-center text-body-secondary py-5">No open tickets.</div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <div class="<?= $team !== null ? 'col-xl-4' : 'col-md-6 col-xl-8' ?>">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto">Opened and resolved</h3>
                    <span class="small text-body-secondary">Last 12 weeks</span>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="itflow-dash-chart flex-grow-1"><canvas id="dashTicketTrend" aria-label="Tickets opened and resolved per week"></canvas></div>
                </div>
            </div>
        </div>
    </div>

<?php } ?>

<?php if ($show_money) { ?>

    <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
        <h2 class="itflow-dash-section mb-0"><?= $access['financial'] ? 'Finance' : 'Billing' ?></h2>
        <?php if ($access['financial']) { ?>
            <span class="small text-body-secondary"><?= escapeHtml($period['label']) ?>, <?= escapeHtml($period['compare_long']) ?></span>
        <?php } ?>
    </div>

    <?php
    $period_cards = array_values(array_filter($money_cards, function ($card) { return isset($card['good']); }));
    $now_cards = array_values(array_filter($money_cards, function ($card) { return !isset($card['good']); }));
    ?>

    <div class="card mb-3 overflow-hidden">
        <div class="row g-0">
            <?php if ($period_cards) { ?>
                <div class="<?= $now_cards ? 'col-lg-8' : 'col-12' ?>">
                    <div class="row g-0 h-100">
                        <?php foreach ($period_cards as $card) {
                            $change = $card['change'];
                            $better = $change !== null && ($card['good'] === 'up' ? $change >= 0 : $change <= 0);
                            ?>
                            <div class="col-md-4 itflow-dash-money">
                                <a href="<?= escapeHtml($card['url']) ?>" class="d-flex flex-column justify-content-center h-100 p-3 text-decoration-none">
                                    <span class="d-block small text-body-secondary"><?= escapeHtml($card['label']) ?></span>
                                    <span class="d-block itflow-dash-money-value text-body text-truncate" title="<?= escapeHtml(dashboardMoney($card['value'])) ?>"><?= escapeHtml(dashboardMoney($card['value'])) ?></span>
                                    <?php if ($change !== null) { ?>
                                        <span class="d-block small text-truncate">
                                            <span class="<?= $better ? 'text-success-emphasis' : 'text-danger-emphasis' ?> fw-semibold">
                                                <i class="fas fa-arrow-<?= $change >= 0 ? 'up' : 'down' ?> me-1" aria-hidden="true"></i><?= abs($change) ?>%
                                            </span>
                                            <span class="text-body-tertiary"><?= escapeHtml($period['compare']) ?></span>
                                        </span>
                                    <?php } else { ?>
                                        <span class="d-block small text-body-tertiary text-truncate">No earlier data to compare</span>
                                    <?php } ?>
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
            <?php if ($now_cards && !$period_cards) {
                // Billing only (no finance access): the "right now" figures get the big cells to themselves
                foreach ($now_cards as $card) { ?>
                    <div class="col-md itflow-dash-money">
                        <a href="<?= escapeHtml($card['url']) ?>" class="d-flex flex-column justify-content-center h-100 p-3 text-decoration-none">
                            <span class="d-block small text-body-secondary"><?= escapeHtml($card['label']) ?></span>
                            <span class="d-block itflow-dash-money-value text-body text-truncate"><?= escapeHtml(dashboardMoney($card['value'])) ?></span>
                            <span class="d-block small text-body-tertiary text-truncate"><?= escapeHtml(ucfirst($card['note'] ?? '')) ?></span>
                        </a>
                    </div>
                <?php }
            } elseif ($now_cards) { ?>
                <div class="col-lg-4 itflow-dash-now">
                    <div class="px-3 pt-3 pb-1 small fw-semibold text-body-secondary">Right now</div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($now_cards as $card) { ?>
                            <a href="<?= escapeHtml($card['url']) ?>" class="list-group-item list-group-item-action border-0 d-flex justify-content-between align-items-baseline gap-3 py-1">
                                <span class="text-truncate"><?= escapeHtml($card['label']) ?> <span class="small text-body-tertiary"><?= escapeHtml(strtolower($card['note'] ?? '')) ?></span></span>
                                <span class="fw-semibold text-nowrap"><?= escapeHtml(dashboardMoney($card['value'])) ?></span>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <?php if ($access['financial']) { ?>

        <div class="row g-3 mb-3">
            <div class="col-xl-8">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">Cash flow</h3>
                        <span class="small text-body-secondary"><?= escapeHtml($chart_data['cash_flow']['extended'] ? 'Last 12 months and next 3' : $period['chart_label']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="itflow-dash-chart itflow-dash-chart-lg"><canvas id="dashCashFlow" aria-label="Income and expenses per month, with what is expected in the months ahead"></canvas></div>
                        <?php if (array_sum($chart_data['cash_flow']['expected_income']) + array_sum($chart_data['cash_flow']['expected_expenses']) > 0) { ?>
                            <p class="small text-body-tertiary mt-2 mb-0">
                                Lighter bars are expected: what's still owed on unpaid invoices, by due date, plus recurring invoices and expenses in the month they next run. Overdue invoices aren't counted.
                            </p>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">Accounts</h3>
                        <a class="small" href="/agent/accounts.php">View accounts</a>
                    </div>
                    <?php if ($accounts) {
                        $accounts_total = array_sum(array_map('floatval', array_column($accounts, 'balance'))); ?>
                        <div class="card-body flex-grow-0 pt-2 pb-3">
                            <span class="d-block small text-body-secondary">Total balance</span>
                            <span class="d-block itflow-dash-money-value mb-0 <?= $accounts_total < 0 ? 'text-danger' : '' ?>"><?= escapeHtml(dashboardMoney($accounts_total)) ?></span>
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($accounts as $account) { ?>
                                <li class="list-group-item d-flex justify-content-between gap-3">
                                    <a class="text-truncate text-reset" href="/agent/transactions.php?account=<?= intval($account['account_id']) ?>"><?= escapeHtml($account['account_name']) ?></a>
                                    <span class="text-nowrap <?= floatval($account['balance']) < 0 ? ($account['type'] === 3 ? 'text-warning-emphasis' : 'text-danger') : '' ?>"><?= escapeHtml($account['type'] === 3 && floatval($account['balance']) < 0 ? accountBalanceText($account) : dashboardMoney($account['balance'])) ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } else { ?>
                        <div class="card-body text-center text-body-secondary">No accounts yet.</div>
                    <?php } ?>
                </div>
            </div>
        </div>

    <?php } ?>

    <?php if ($aging !== null || $top_clients !== null) { ?>

        <div class="row g-3 mb-3">
            <?php if ($aging !== null) { ?>
                <div class="<?= $top_clients !== null ? 'col-lg-6' : 'col-12' ?>">
                    <div class="card h-100 mb-0">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title mb-0 me-auto">Receivables aging</h3>
                            <a class="small" href="/agent/invoices.php?status=Unpaid">View unpaid invoices</a>
                        </div>
                        <?php if ($aging['total'] > 0) { ?>
                            <div class="card-body flex-grow-0 pt-2">
                                <div class="d-flex flex-wrap align-items-baseline column-gap-2 mb-2">
                                    <span class="itflow-dash-money-value mb-0"><?= escapeHtml(dashboardMoney($aging['total'])) ?></span>
                                    <span class="small text-body-secondary">owed on <?= $aging['count'] ?> invoice<?= $aging['count'] === 1 ? '' : 's' ?></span>
                                </div>
                                <div class="itflow-dash-aging mb-3" role="img" aria-label="Receivables by days past due">
                                    <?php foreach ($aging['buckets'] as $bucket_key => $bucket) {
                                        if ($bucket['amount'] > 0) { ?>
                                            <span class="itflow-dash-age-<?= str_replace('+', 'plus', $bucket_key) ?>" style="width: <?= round($bucket['amount'] / $aging['total'] * 100, 2) ?>%"
                                                title="<?= escapeHtml($bucket['label'] . ': ' . dashboardMoney($bucket['amount'])) ?>"></span>
                                        <?php }
                                    } ?>
                                </div>
                                <div class="row row-cols-2 row-cols-sm-5 g-2 small">
                                    <?php foreach ($aging['buckets'] as $bucket_key => $bucket) { ?>
                                        <div>
                                            <span class="d-block text-body-secondary text-nowrap"><span class="itflow-dash-legend-swatch d-inline-block me-1 itflow-dash-age-<?= str_replace('+', 'plus', $bucket_key) ?>"></span><?= escapeHtml($bucket['label']) ?></span>
                                            <span class="d-block fw-semibold <?= $bucket['amount'] > 0 ? '' : 'text-body-tertiary' ?>"><?= escapeHtml(dashboardMoney($bucket['amount'])) ?></span>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="px-3 pb-1 small fw-semibold text-body-secondary">Owe the most</div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($aging['clients'] as $row) {
                                    $days_late = intval($row['days_late']); ?>
                                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3"
                                        href="/agent/invoices.php?client_id=<?= intval($row['client_id']) ?>&status=Unpaid">
                                        <span class="overflow-hidden">
                                            <span class="d-block text-truncate"><?= escapeHtml($row['client_name']) ?></span>
                                            <span class="d-block small <?= $days_late > 0 ? 'text-danger-emphasis' : 'text-body-secondary' ?>">
                                                <?= intval($row['n']) ?> invoice<?= intval($row['n']) === 1 ? '' : 's' ?>,
                                                <?= $days_late > 0 ? "oldest $days_late day" . ($days_late === 1 ? '' : 's') . ' late' : 'none late' ?>
                                            </span>
                                        </span>
                                        <span class="text-nowrap fw-semibold"><?= escapeHtml(dashboardMoney($row['v'])) ?></span>
                                    </a>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="card-body text-center text-body-secondary py-5">
                                <i class="fas fa-check-circle fa-2x mb-2 d-block text-success" aria-hidden="true"></i>
                                Nothing is owed on unpaid invoices.
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <?php if ($top_clients !== null) {
                $top_max = max(1, floatval($top_clients['clients'][0]['v'] ?? 0)); ?>
                <div class="<?= $aging !== null ? 'col-lg-6' : 'col-12' ?>">
                    <div class="card h-100 mb-0">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title mb-0 me-auto">Top clients</h3>
                            <span class="small text-body-secondary">Paid, <?= escapeHtml(strtolower($period['label'])) ?></span>
                        </div>
                        <div class="card-body pt-2">
                            <?php if ($top_clients['clients']) { ?>
                                <div class="itflow-dash-bars">
                                    <?php foreach ($top_clients['clients'] as $row) {
                                        $client_paid = floatval($row['v']); ?>
                                        <a class="itflow-dash-bar" href="/agent/client_overview.php?client_id=<?= intval($row['client_id']) ?>">
                                            <span class="itflow-dash-bar-label"><?= escapeHtml($row['client_name']) ?></span>
                                            <span class="itflow-dash-bar-value">
                                                <?php if ($top_clients['income'] > 0) { ?><span class="text-body-tertiary me-2"><?= round($client_paid / $top_clients['income'] * 100) ?>%</span><?php } ?><?= escapeHtml(dashboardMoney($client_paid)) ?>
                                            </span>
                                            <span class="itflow-dash-bar-track"><span class="itflow-dash-bar-fill" style="width: <?= round($client_paid / $top_max * 100, 1) ?>%"></span></span>
                                        </a>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="text-center text-body-secondary py-5">No client payments in this period.</div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

    <?php } ?>

    <?php if ($access['financial']) { ?>

        <div class="row g-3 mb-3">
            <?php foreach ([
                ['dashIncomeByCategory', 'Income by category', 'income_by_category'],
                ['dashExpensesByCategory', 'Expenses by category', 'expenses_by_category'],
                ['dashExpensesByVendor', 'Expenses by vendor', 'expenses_by_vendor'],
            ] as [$canvas_id, $title, $key]) { ?>
                <div class="col-lg-4">
                    <div class="card h-100 mb-0">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title mb-0 me-auto"><?= $title ?></h3>
                            <span class="small text-body-secondary">Top 5</span>
                        </div>
                        <div class="card-body">
                            <?php if ($chart_data['breakdowns'][$key]['values']) { ?>
                                <div class="itflow-dash-chart itflow-dash-chart-donut">
                                    <canvas id="<?= $canvas_id ?>" aria-label="<?= $title ?>"></canvas>
                                    <div class="itflow-dash-donut-total" aria-hidden="true">
                                        <span class="small text-body-secondary">Total</span>
                                        <span class="fw-semibold" data-donut-total="<?= $canvas_id ?>"></span>
                                    </div>
                                </div>
                                <ul class="itflow-dash-legend list-unstyled mb-0 mt-3" data-donut-legend="<?= $canvas_id ?>"></ul>
                            <?php } else { ?>
                                <div class="text-center text-body-secondary py-5">Nothing in this period.</div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">Latest income</h3>
                        <a class="small" href="/agent/income.php">View all income</a>
                    </div>
                    <?php if ($latest_income) { ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($latest_income as $row) { ?>
                                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3"
                                    href="/agent/invoice.php?client_id=<?= intval($row['invoice_client_id']) ?>&invoice_id=<?= intval($row['invoice_id']) ?>">
                                    <span class="overflow-hidden">
                                        <span class="d-block text-truncate"><?= escapeHtml($row['client_name']) ?></span>
                                        <span class="d-block small text-body-secondary"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?> · <?= escapeHtml(date('M j', strtotime($row['payment_date']))) ?></span>
                                    </span>
                                    <span class="text-nowrap text-success"><?= escapeHtml(dashboardMoney($row['payment_amount'])) ?></span>
                                </a>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="card-body text-center text-body-secondary">No payments yet.</div>
                    <?php } ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">Latest expenses</h3>
                        <a class="small" href="/agent/expenses.php">View all expenses</a>
                    </div>
                    <?php if ($latest_expenses) { ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($latest_expenses as $row) { ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                    <span class="overflow-hidden">
                                        <span class="d-block text-truncate"><?= escapeHtml($row['vendor_name']) ?></span>
                                        <span class="d-block small text-body-secondary text-truncate"><?= escapeHtml($row['category_name'] ?: 'Uncategorised') ?> · <?= escapeHtml(date('M j', strtotime($row['expense_date']))) ?></span>
                                    </span>
                                    <span class="text-nowrap"><?= escapeHtml(dashboardMoney($row['expense_amount'])) ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } else { ?>
                        <div class="card-body text-center text-body-secondary">No expenses yet.</div>
                    <?php } ?>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">Trips</h3>
                        <span class="small text-body-secondary"><?= number_format($chart_data['trips']['period_total'], 1) ?> <?= tripUnit(true) ?> <?= escapeHtml(strtolower($period['label'])) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="itflow-dash-chart"><canvas id="dashTrips" aria-label="<?= ucfirst(tripUnit()) ?> per month"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

    <?php } ?>

<?php } ?>

<?php if ($attention === null && $my_tickets === null && !$show_money) { ?>
    <div class="card">
        <div class="card-body text-center text-body-secondary py-5">
            <i class="fas fa-compass fa-3x mb-3 d-block" aria-hidden="true"></i>
            Your role has no dashboard sections. Use the menu or <kbd>Ctrl</kbd> + <kbd>K</kbd> to find what you need.
        </div>
    </div>
<?php } ?>

</div>

<?php require_once "../includes/footer.php"; ?>

<?php if ($chart_data) { ?>
    <script>window.itflowDashboardData = <?= json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;</script>
    <script src="/js/dashboard.js"></script>
<?php } ?>
