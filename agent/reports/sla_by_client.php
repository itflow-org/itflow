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

function slaPercentDisplay($percent)
{
    if (is_null($percent)) {
        return "<span class='text-secondary'>-</span>";
    }
    if ($percent >= 95) {
        return "<span class='text-success text-bold'>$percent%</span>";
    }
    if ($percent >= 80) {
        return "<span class='text-warning text-bold'>$percent%</span>";
    }
    return "<span class='text-danger text-bold'>$percent%</span>";
}

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-stopwatch mr-2"></i>SLA by Client</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
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
                    <h3 class="card-title"><i class="fas fa-fw fa-chart-area mr-2"></i><?= $period_label ?></h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Client</th>
                                <th class="text-right">Tickets</th>
                                <th class="text-right">Response met</th>
                                <th class="text-right">Response missed</th>
                                <th class="text-right">Response %</th>
                                <th class="text-right">Resolution met</th>
                                <th class="text-right">Resolution missed</th>
                                <th class="text-right">Resolution %</th>
                                <th class="text-right">Avg time to respond</th>
                                <th class="text-right">Avg clock to resolve</th>
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

                                // Resolution time is measured in clock time actually spent -
                                // paused spells are excluded, which is what the SLA judged on
                                $avg_time_to_resolve = '-';
                                $resolved_minutes_total = 0;
                                $resolved_count = 0;
                                $sql_resolved = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_sla_id > 0 AND ticket_client_id = $client_id AND ticket_resolved_at IS NOT NULL AND $period_query");
                                while ($resolved_row = mysqli_fetch_assoc($sql_resolved)) {
                                    $resolved_minutes_total += getTicketSlaConsumedMinutes($resolved_row['ticket_id']);
                                    $resolved_count++;
                                }
                                if ($resolved_count > 0) {
                                    $avg_time_to_resolve = secondsToTime(($resolved_minutes_total / $resolved_count) * 60);
                                }
                                ?>
                                <tr>
                                    <td><?= $client_name ?></td>
                                    <td class="text-right"><?= $ticket_count ?></td>
                                    <td class="text-right"><?= $response_met ?></td>
                                    <td class="text-right"><?= $response_missed ?></td>
                                    <td class="text-right"><?= slaPercentDisplay($response_percent) ?></td>
                                    <td class="text-right"><?= $resolution_met ?></td>
                                    <td class="text-right"><?= $resolution_missed ?></td>
                                    <td class="text-right"><?= slaPercentDisplay($resolution_percent) ?></td>
                                    <td class="text-right"><?= $avg_time_to_respond ?></td>
                                    <td class="text-right"><?= $avg_time_to_resolve ?></td>
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
