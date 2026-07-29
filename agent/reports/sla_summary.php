<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_support');

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

$sql_ticket_years = mysqli_query($mysqli, "SELECT DISTINCT YEAR(ticket_created_at) AS ticket_year FROM tickets ORDER BY ticket_year DESC");

// Compliance figures for a slice of tickets carrying an SLA
function getSlaCompliance($where)
{
    global $mysqli;

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
        COUNT(ticket_id) AS ticket_count,
        SUM(ticket_response_sla_met = 1) AS response_met,
        SUM(ticket_response_sla_met = 0) AS response_missed,
        SUM(ticket_response_sla_met IS NULL) AS response_pending,
        SUM(ticket_resolution_sla_met = 1) AS resolution_met,
        SUM(ticket_resolution_sla_met = 0) AS resolution_missed,
        SUM(ticket_resolution_sla_met IS NULL AND ticket_resolution_due_at IS NOT NULL) AS resolution_pending
        FROM tickets
        WHERE ticket_sla_id > 0 $where"
    ));

    $compliance = [
        'ticket_count' => intval($row['ticket_count']),
        'response_met' => intval($row['response_met']),
        'response_missed' => intval($row['response_missed']),
        'response_pending' => intval($row['response_pending']),
        'resolution_met' => intval($row['resolution_met']),
        'resolution_missed' => intval($row['resolution_missed']),
        'resolution_pending' => intval($row['resolution_pending']),
    ];

    // Percentages count only judged tickets - a ticket still in flight is
    // neither a hit nor a miss
    $response_judged = $compliance['response_met'] + $compliance['response_missed'];
    $compliance['response_percent'] = $response_judged ? round($compliance['response_met'] / $response_judged * 100, 1) : null;

    $resolution_judged = $compliance['resolution_met'] + $compliance['resolution_missed'];
    $compliance['resolution_percent'] = $resolution_judged ? round($compliance['resolution_met'] / $resolution_judged * 100, 1) : null;

    return $compliance;
}


$overall = getSlaCompliance("AND YEAR(ticket_created_at) = $year");

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-stopwatch mr-2"></i>SLA Summary</h3>
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
            </form>

            <?php if ($overall['ticket_count'] == 0) { ?>
                <p class="text-secondary">No tickets raised in <?= $year ?> carried an SLA. Assign SLAs under Admin &gt; SLAs to start tracking.</p>
            <?php } else { ?>

                <div class="row mb-3">
                    <div class="col-sm-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-secondary"><i class="fas fa-life-ring"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Tickets with an SLA</span>
                                <span class="info-box-number"><?= $overall['ticket_count'] ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-secondary"><i class="fas fa-reply"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Response compliance</span>
                                <span class="info-box-number"><?= slaPercentDisplay($overall['response_percent']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-secondary"><i class="fas fa-flag-checkered"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Resolution compliance</span>
                                <span class="info-box-number"><?= slaPercentDisplay($overall['resolution_percent']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-dark mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-fw fa-chart-area mr-2"></i>By Priority (<?= $year ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive-sm">
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Priority</th>
                                    <th class="text-right">Tickets</th>
                                    <th class="text-right">Response met</th>
                                    <th class="text-right">Response missed</th>
                                    <th class="text-right">Response %</th>
                                    <th class="text-right">Resolution met</th>
                                    <th class="text-right">Resolution missed</th>
                                    <th class="text-right">Resolution %</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach (['Urgent', 'High', 'Medium', 'Low'] as $priority) {
                                    $stats = getSlaCompliance("AND YEAR(ticket_created_at) = $year AND ticket_priority = '$priority'");
                                    if ($stats['ticket_count'] == 0) {
                                        continue;
                                    }
                                    ?>
                                    <tr>
                                        <td><?= $priority ?></td>
                                        <td class="text-right"><?= $stats['ticket_count'] ?></td>
                                        <td class="text-right"><?= $stats['response_met'] ?></td>
                                        <td class="text-right"><?= $stats['response_missed'] ?></td>
                                        <td class="text-right"><?= slaPercentDisplay($stats['response_percent']) ?></td>
                                        <td class="text-right"><?= $stats['resolution_met'] ?></td>
                                        <td class="text-right"><?= $stats['resolution_missed'] ?></td>
                                        <td class="text-right"><?= slaPercentDisplay($stats['resolution_percent']) ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card card-dark mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-fw fa-calendar mr-2"></i>By Month (<?= $year ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive-sm">
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th>Month</th>
                                    <th class="text-right">Tickets</th>
                                    <th class="text-right">Response %</th>
                                    <th class="text-right">Resolution %</th>
                                    <th class="text-right">Still open</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php for ($month = 1; $month <= 12; $month++) {
                                    $stats = getSlaCompliance("AND YEAR(ticket_created_at) = $year AND MONTH(ticket_created_at) = $month");
                                    if ($stats['ticket_count'] == 0) {
                                        continue;
                                    }
                                    ?>
                                    <tr>
                                        <td><?= date("F", mktime(1, 1, 1, $month, 1)) ?></td>
                                        <td class="text-right"><?= $stats['ticket_count'] ?></td>
                                        <td class="text-right"><?= slaPercentDisplay($stats['response_percent']) ?></td>
                                        <td class="text-right"><?= slaPercentDisplay($stats['resolution_percent']) ?></td>
                                        <td class="text-right"><?= $stats['resolution_pending'] ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <small class="text-muted">Percentages count only tickets whose targets have been judged - tickets still awaiting a response or resolution are excluded until their outcome is known. Tickets raised before SLAs were assigned carry no SLA and are not counted.</small>

            <?php } ?>

        </div>
    </div>

<?php
require_once "../../includes/footer.php";
