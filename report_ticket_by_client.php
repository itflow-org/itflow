<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_support');

function secondsToTime($inputSeconds) {
    $inputSeconds = floor($inputSeconds);

    $secondsInAMinute = 60;
    $secondsInAnHour = 60 * $secondsInAMinute;
    $secondsInADay = 24 * $secondsInAnHour;

    // Extract days
    $days = floor($inputSeconds / $secondsInADay);

    // Extract hours
    $hourSeconds = $inputSeconds % $secondsInADay;
    $hours = floor($hourSeconds / $secondsInAnHour);

    // Extract minutes
    $minuteSeconds = $hourSeconds % $secondsInAnHour;
    $minutes = floor($minuteSeconds / $secondsInAMinute);

    // Extract the remaining seconds
    $remainingSeconds = $minuteSeconds % $secondsInAMinute;
    $seconds = ceil($remainingSeconds);

    // Format and return
    $timeParts = [];
    $sections = [
        'day' => (int)$days,
        'hour' => (int)$hours,
        'minute' => (int)$minutes,
        'second' => (int)$seconds,
    ];

    foreach ($sections as $name => $value){
        if ($value > 0){
            $timeParts[] = $value. ' '.$name.($value == 1 ? '' : 's');
        }
    }

    return implode(', ', $timeParts);
}

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

if (isset($_GET['month'])) {
    $month = intval($_GET['month']);
} else {
    $month = date('m');
}

$sql_ticket_years = mysqli_query($mysqli, "SELECT DISTINCT YEAR(ticket_created_at) AS ticket_year FROM tickets ORDER BY ticket_year DESC");

$sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-life-ring mr-2"></i>Tickets By Client</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
            </div>
        </div>
        <div class="card-body">
            <form class="mb-3">
                <select onchange="this.form.submit()" class="form-control" name="year">
                    <?php
                    while ($row = mysqli_fetch_array($sql_ticket_years)) {
                        $ticket_year = intval($row['ticket_year']); ?>
                        <option <?php if ($year == $ticket_year) { ?> selected <?php } ?> > <?php echo $ticket_year; ?></option>
                    <?php } ?>
                </select>
                <select onchange="this.form.submit()" class="form-control" name="month">
                    <option <?php if ($month == 1) { echo 'selected'; } ?> value="1">January</option>
                    <option <?php if ($month == 2) { echo 'selected'; } ?> value="2">February</option>
                    <option <?php if ($month == 3) { echo 'selected'; } ?> value="3">March</option>
                    <option <?php if ($month == 4) { echo 'selected'; } ?> value="4">April</option>
                    <option <?php if ($month == 5) { echo 'selected'; } ?> value="5">May</option>
                    <option <?php if ($month == 6) { echo 'selected'; } ?> value="6">June</option>
                    <option <?php if ($month == 7) { echo 'selected'; } ?> value="7">July</option>
                    <option <?php if ($month == 8) { echo 'selected'; } ?> value="8">August</option>
                    <option <?php if ($month == 9) { echo 'selected'; } ?> value="9">September</option>
                    <option <?php if ($month == 10) { echo 'selected'; } ?> value="10">October</option>
                    <option <?php if ($month == 11) { echo 'selected'; } ?> value="11">November</option>
                    <option <?php if ($month == 12) { echo 'selected'; } ?> value="12">December</option>
                </select>
            </form>

            <div class="card card-dark mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-fw fa-chart-area mr-2"></i>Yearly (<?php echo $year; ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Client</th>
                                <th class="text-right">Tickets raised</th>
                                <th class="text-right">By priority: Low</th>
                                <th class="text-right">By priority: Med</th>
                                <th class="text-right">By priority: High</th>
                                <th class="text-right">Tickets resolved</th>
                                <th class="text-right">Total Time worked <i>(H:M:S)</i></th>
                                <th class="text-right">Avg time to resolve</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            while ($row = mysqli_fetch_array($sql_clients)) {
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);

                                // Calculate total tickets raised in period
                                $sql_ticket_raised_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_raised_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND ticket_client_id = $client_id");
                                $row = mysqli_fetch_array($sql_ticket_raised_count);
                                $ticket_raised_count = intval($row['ticket_raised_count']);

                                // Calculate total tickets raised in period that are resolved
                                $sql_ticket_resolved_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_resolved_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND ticket_client_id = $client_id AND ticket_resolved_at IS NOT NULL");
                                $row = mysqli_fetch_array($sql_ticket_resolved_count);
                                $ticket_resolved_count = intval($row['ticket_resolved_count']);

                                // Breakdown tickets for each priority - Low
                                $sql_low_ticket_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS low_ticket_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND ticket_client_id = $client_id AND ticket_priority = 'Low'");
                                $row = mysqli_fetch_array($sql_low_ticket_count);
                                $low_ticket_count = intval($row['low_ticket_count']);

                                // Breakdown tickets for each priority - Low
                                $sql_med_ticket_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS med_ticket_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND ticket_client_id = $client_id AND ticket_priority = 'Medium'");
                                $row = mysqli_fetch_array($sql_med_ticket_count);
                                $med_ticket_count = intval($row['med_ticket_count']);

                                // Breakdown tickets for each priority - Low
                                $sql_high_ticket_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS high_ticket_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND ticket_client_id = $client_id AND ticket_priority = 'High'");
                                $row = mysqli_fetch_array($sql_high_ticket_count);
                                $high_ticket_count = intval($row['high_ticket_count']);

                                // Used to calculate average time to resolve tickets that were raised in period specified
                                $sql_tickets = mysqli_query($mysqli, "SELECT ticket_created_at, ticket_resolved_at FROM tickets WHERE YEAR(ticket_created_at) = $year AND ticket_client_id = $client_id AND ticket_resolved_at IS NOT NULL");

                                // Calculate total time tracked towards tickets in the period
                                $sql_time = mysqli_query($mysqli, "SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) as total_time FROM ticket_replies LEFT JOIN tickets ON tickets.ticket_id = ticket_replies.ticket_reply_ticket_id WHERE YEAR(ticket_created_at) = $year AND ticket_client_id = $client_id AND ticket_reply_time_worked IS NOT NULL");
                                $row = mysqli_fetch_array($sql_time);
                                $ticket_total_time_worked = nullable_htmlentities($row['total_time']);

                                if ($ticket_raised_count > 0 || $ticket_resolved_count > 0) {

                                    $avg_time_to_resolve = '-';
                                    if ($ticket_resolved_count > 0) {
                                        // Calculate average time to solve
                                        $count = 0;
                                        $total = 0;
                                        while ($row = mysqli_fetch_array($sql_tickets)) {
                                            $openedTime = new DateTime($row['ticket_created_at']);
                                            $resolvedTime = new DateTime($row['ticket_resolved_at']);

                                            $total += ($resolvedTime->getTimestamp() - $openedTime->getTimestamp());
                                            $count++;
                                        }
                                        $avg_time_to_resolve = secondsToTime($total / $count);
                                    }

                                    ?>

                                    <tr>
                                        <td><?php echo $client_name; ?></td>
                                        <td class="text-right"><?php echo $ticket_raised_count; ?></td>
                                        <td class="text-right"><?php echo $low_ticket_count; ?></td>
                                        <td class="text-right"><?php echo $med_ticket_count; ?></td>
                                        <td class="text-right"><?php echo $high_ticket_count; ?></td>
                                        <td class="text-right"><?php echo $ticket_resolved_count; ?></td>
                                        <td class="text-right"><?php echo $ticket_total_time_worked; ?></td>
                                        <td class="text-right"><?php echo $avg_time_to_resolve; ?></td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>



            <div class="card card-dark mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-fw fa-chart-area mr-2"></i>Monthly (<?php echo date("F", mktime(1, 1, 1, $month, 1)) . ' ' . $year; ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Client</th>
                                <th class="text-right">Tickets raised</th>
                                <th class="text-right">By priority: Low</th>
                                <th class="text-right">By priority: Med</th>
                                <th class="text-right">By priority: High</th>
                                <th class="text-right">Tickets resolved</th>
                                <th class="text-right">Total Time worked <i>(H:M:S)</i></th>
                                <th class="text-right">Avg time to resolve</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            mysqli_data_seek($sql_clients, 0); // Reset
                            while ($row = mysqli_fetch_array($sql_clients)) {
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);

                                // Calculate total tickets raised in period
                                $sql_ticket_raised_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_raised_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND MONTH(ticket_created_at) = $month AND ticket_client_id = $client_id");
                                $row = mysqli_fetch_array($sql_ticket_raised_count);
                                $ticket_raised_count = intval($row['ticket_raised_count']);

                                // Calculate total tickets raised in period that are resolved
                                $sql_ticket_resolved_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_resolved_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND MONTH(ticket_created_at) = $month AND ticket_client_id = $client_id AND ticket_resolved_at IS NOT NULL");
                                $row = mysqli_fetch_array($sql_ticket_resolved_count);
                                $ticket_resolved_count = intval($row['ticket_resolved_count']);

                                // Breakdown tickets for each priority - Low
                                $sql_low_ticket_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS low_ticket_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND MONTH(ticket_created_at) = $month AND ticket_client_id = $client_id AND ticket_priority = 'Low'");
                                $row = mysqli_fetch_array($sql_low_ticket_count);
                                $low_ticket_count = intval($row['low_ticket_count']);

                                // Breakdown tickets for each priority - Low
                                $sql_med_ticket_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS med_ticket_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND MONTH(ticket_created_at) = $month AND ticket_client_id = $client_id AND ticket_priority = 'Medium'");
                                $row = mysqli_fetch_array($sql_med_ticket_count);
                                $med_ticket_count = intval($row['med_ticket_count']);

                                // Breakdown tickets for each priority - Low
                                $sql_high_ticket_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS high_ticket_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND MONTH(ticket_created_at) = $month AND ticket_client_id = $client_id AND ticket_priority = 'High'");
                                $row = mysqli_fetch_array($sql_high_ticket_count);
                                $high_ticket_count = intval($row['high_ticket_count']);

                                // Used to calculate average time to resolve tickets that were raised in period specified
                                $sql_tickets = mysqli_query($mysqli, "SELECT ticket_created_at, ticket_resolved_at FROM tickets WHERE YEAR(ticket_created_at) = $year AND MONTH(ticket_created_at) = $month AND ticket_client_id = $client_id AND ticket_resolved_at IS NOT NULL");

                                // Calculate total time tracked towards tickets in the period
                                $sql_time = mysqli_query($mysqli, "SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) as total_time FROM ticket_replies LEFT JOIN tickets ON tickets.ticket_id = ticket_replies.ticket_reply_ticket_id WHERE YEAR(ticket_created_at) = $year AND MONTH(ticket_created_at) = $month AND ticket_client_id = $client_id AND ticket_reply_time_worked IS NOT NULL");
                                $row = mysqli_fetch_array($sql_time);
                                $ticket_total_time_worked = nullable_htmlentities($row['total_time']);

                                if ($ticket_raised_count > 0 || $ticket_resolved_count > 0) {

                                    $avg_time_to_resolve = '-';
                                    if ($ticket_resolved_count > 0) {
                                        // Calculate average time to solve
                                        $count = 0;
                                        $total = 0;
                                        while ($row = mysqli_fetch_array($sql_tickets)) {
                                            $openedTime = new DateTime($row['ticket_created_at']);
                                            $resolvedTime = new DateTime($row['ticket_resolved_at']);

                                            $total += ($resolvedTime->getTimestamp() - $openedTime->getTimestamp());
                                            $count++;
                                        }
                                        $avg_time_to_resolve = secondsToTime($total / $count);
                                    }

                                    ?>

                                    <tr>
                                        <td><?php echo $client_name; ?></td>
                                        <td class="text-right"><?php echo $ticket_raised_count; ?></td>
                                        <td class="text-right"><?php echo $low_ticket_count; ?></td>
                                        <td class="text-right"><?php echo $med_ticket_count; ?></td>
                                        <td class="text-right"><?php echo $high_ticket_count; ?></td>
                                        <td class="text-right"><?php echo $ticket_resolved_count; ?></td>
                                        <td class="text-right"><?php echo $ticket_total_time_worked; ?></td>
                                        <td class="text-right"><?php echo $avg_time_to_resolve; ?></td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>

<?php
require_once "includes/footer.php";

