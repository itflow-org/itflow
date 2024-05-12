<?php

require_once "inc_all_reports.php";

validateTechRole();

function secondsToTime($inputSeconds) {
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
            </form>

            <div class="table-responsive-sm">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Client</th>
                        <th class="text-right">Tickets raised</th>
                        <th class="text-right">Tickets closed</th>
                        <th class="text-right">Time worked <i>(H:M:S)</i></th>
                        <th class="text-right">Avg time to close</th>
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

                        // Calculate total tickets raised in period that are closed
                        $sql_ticket_closed_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_closed_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND ticket_client_id = $client_id AND ticket_closed_at IS NOT NULL");
                        $row = mysqli_fetch_array($sql_ticket_closed_count);
                        $ticket_closed_count = intval($row['ticket_closed_count']);

                        // Used to calculate average time to close tickets that were raised in period specified
                        $sql_tickets = mysqli_query($mysqli, "SELECT ticket_created_at, ticket_closed_at FROM tickets WHERE YEAR(ticket_created_at) = $year AND ticket_client_id = $client_id AND ticket_closed_at IS NOT NULL");

                        // Calculate total time tracked towards tickets in the period
                        $sql_time = mysqli_query($mysqli, "SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) as total_time FROM ticket_replies LEFT JOIN tickets ON tickets.ticket_id = ticket_replies.ticket_reply_ticket_id WHERE YEAR(ticket_created_at) = $year AND ticket_client_id = $client_id AND ticket_reply_time_worked IS NOT NULL");
                        $row = mysqli_fetch_array($sql_time);
                        $ticket_total_time_worked = nullable_htmlentities($row['total_time']);

                        if ($ticket_raised_count > 0) {

                            // Calculate average time to solve
                            $count = 0;
                            $total = 0;
                            while ($row = mysqli_fetch_array($sql_tickets)) {
                                $openedTime = new DateTime($row['ticket_created_at']);
                                $closedTime = new DateTime($row['ticket_closed_at']);

                                $total += ($closedTime->getTimestamp() - $openedTime->getTimestamp());
                                $count++;
                            }

                            ?>

                            <tr>
                                <td><?php echo $client_name; ?></td>
                                <td class="text-right"><?php echo $ticket_raised_count; ?></td>
                                <td class="text-right"><?php echo $ticket_closed_count; ?></td>
                                <td class="text-right"><?php echo $ticket_total_time_worked; ?></td>
                                <td class="text-right"><?php echo secondsToTime($total); ?></td>
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

<?php
require_once "footer.php";

