<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_support');

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

if (isset($_GET['month'])) {
    $month = intval($_GET['month']);
} else {
    $month = 0; // 0 = whole year
}

$period_query = "YEAR(ticket_created_at) = $year";
$period_label = $year;
if ($month) {
    $period_query .= " AND MONTH(ticket_created_at) = $month";
    $period_label = date("F", mktime(1, 1, 1, $month, 1)) . " $year";
}

$sql_ticket_years = mysqli_query($mysqli, "SELECT DISTINCT YEAR(ticket_created_at) AS ticket_year FROM tickets ORDER BY ticket_year DESC");

$sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");


// Clock time spent on resolved tickets, gathered for every client at once
// rather than per ticket. Tickets with no interval rows - response-only plans,
// or anything resolved before SLA pausing existed - fall back to the business
// time that elapsed between being raised and resolved.
$resolve_totals = [];
$sql_resolve_times = mysqli_query($mysqli, "SELECT ticket_client_id, ticket_id, ticket_created_at, ticket_resolved_at, SUM(sla_history_minutes) AS logged_minutes
    FROM tickets
    LEFT JOIN sla_history ON sla_history_ticket_id = ticket_id
    WHERE ticket_sla_id > 0
    AND ticket_resolved_at IS NOT NULL
    AND $period_query
    GROUP BY ticket_id, ticket_client_id, ticket_created_at, ticket_resolved_at"
);
while ($resolve_row = mysqli_fetch_assoc($sql_resolve_times)) {
    $resolve_client_id = intval($resolve_row['ticket_client_id']);
    $resolve_minutes = is_null($resolve_row['logged_minutes'])
        ? businessMinutesBetween($resolve_row['ticket_created_at'], $resolve_row['ticket_resolved_at'])
        : intval($resolve_row['logged_minutes']);

    if (!isset($resolve_totals[$resolve_client_id])) {
        $resolve_totals[$resolve_client_id] = ['minutes' => 0, 'count' => 0];
    }
    $resolve_totals[$resolve_client_id]['minutes'] += $resolve_minutes;
    $resolve_totals[$resolve_client_id]['count']++;
}

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-stopwatch me-2"></i>SLA by Client</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print me-2"></i>Print</button>
            </div>
        </div>
        <div class="card-body">
            <form class="mb-3">
                <select onchange="this.form.submit()" class="form-control" name="year">
                    <?php
                    while ($row = mysqli_fetch_assoc($sql_ticket_years)) {
                        $ticket_year = intval($row['ticket_year']); ?>
                        <option <?php if ($year == $ticket_year) { ?> selected <?php } ?> > <?= $ticket_year ?></option>
                    <?php } ?>
                </select>
                <select onchange="this.form.submit()" class="form-control" name="month">
                    <option <?php if ($month == 0) { echo 'selected'; } ?> value="0">Whole year</option>
                    <?php for ($m = 1; $m <= 12; $m++) { ?>
                        <option <?php if ($month == $m) { echo 'selected'; } ?> value="<?= $m ?>"><?= date("F", mktime(1, 1, 1, $m, 1)) ?></option>
                    <?php } ?>
                </select>
            </form>

            <div class="card card-dark mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-fw fa-chart-area me-2"></i><?= $period_label ?></h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Client</th>
                                <th class="text-end">Tickets</th>
                                <th class="text-end">Response met</th>
                                <th class="text-end">Response missed</th>
                                <th class="text-end">Response %</th>
                                <th class="text-end">Resolution met</th>
                                <th class="text-end">Resolution missed</th>
                                <th class="text-end">Resolution %</th>
                                <th class="text-end">Avg time to respond</th>
                                <th class="text-end">Avg clock to resolve</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $any_rows = false;
                            while ($row = mysqli_fetch_assoc($sql_clients)) {
                                $client_id = intval($row['client_id']);
                                $client_name = escapeHtml($row['client_name']);

                                $stats = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
                                    COUNT(ticket_id) AS ticket_count,
                                    SUM(ticket_response_sla_met = 1) AS response_met,
                                    SUM(ticket_response_sla_met = 0) AS response_missed,
                                    SUM(ticket_resolution_sla_met = 1) AS resolution_met,
                                    SUM(ticket_resolution_sla_met = 0) AS resolution_missed,
                                    AVG(CASE WHEN ticket_first_response_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_first_response_at) END) AS avg_response_seconds
                                    FROM tickets
                                    WHERE ticket_sla_id > 0 AND ticket_client_id = $client_id AND $period_query"
                                ));

                                $ticket_count = intval($stats['ticket_count']);
                                if ($ticket_count == 0) {
                                    continue;
                                }
                                $any_rows = true;

                                $response_met = intval($stats['response_met']);
                                $response_missed = intval($stats['response_missed']);
                                $resolution_met = intval($stats['resolution_met']);
                                $resolution_missed = intval($stats['resolution_missed']);

                                $response_judged = $response_met + $response_missed;
                                $response_percent = $response_judged ? round($response_met / $response_judged * 100, 1) : null;

                                $resolution_judged = $resolution_met + $resolution_missed;
                                $resolution_percent = $resolution_judged ? round($resolution_met / $resolution_judged * 100, 1) : null;

                                $avg_time_to_respond = is_null($stats['avg_response_seconds']) ? '-' : secondsToTime($stats['avg_response_seconds']);

                                // Clock time actually spent - paused spells excluded, which is
                                // what the SLA itself judged on
                                $avg_time_to_resolve = '-';
                                if (!empty($resolve_totals[$client_id]['count'])) {
                                    $avg_time_to_resolve = secondsToTime(($resolve_totals[$client_id]['minutes'] / $resolve_totals[$client_id]['count']) * 60);
                                }
                                ?>
                                <tr>
                                    <td><?= $client_name ?></td>
                                    <td class="text-end"><?= $ticket_count ?></td>
                                    <td class="text-end"><?= $response_met ?></td>
                                    <td class="text-end"><?= $response_missed ?></td>
                                    <td class="text-end"><?= slaPercentDisplay($response_percent) ?></td>
                                    <td class="text-end"><?= $resolution_met ?></td>
                                    <td class="text-end"><?= $resolution_missed ?></td>
                                    <td class="text-end"><?= slaPercentDisplay($resolution_percent) ?></td>
                                    <td class="text-end"><?= $avg_time_to_respond ?></td>
                                    <td class="text-end"><?= $avg_time_to_resolve ?></td>
                                </tr>
                            <?php }
                            if (!$any_rows) { ?>
                                <tr><td colspan="10" class="text-secondary">No tickets with an SLA in this period.</td></tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <small class="text-muted">Time to respond is wall-clock from ticket creation. Time to resolve counts only business hours with the SLA clock running, so paused time is excluded.</small>

                </div>
            </div>

        </div>
    </div>

<?php
require_once "../../includes/footer.php";
