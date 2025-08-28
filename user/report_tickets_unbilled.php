<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_sales', 1);

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

$sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients ORDER BY client_name ASC");

$rows = 0;


?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-life-ring mr-2"></i>Unbilled Tickets By Client</h3>
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
                        <th class="text-right">Tickets Raised</th>
                        <th class="text-right">Billable Tickets</th>
                        <th class="text-right">Unbilled Tickets</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    while ($row = mysqli_fetch_array($sql_clients)) {
                        $client_id = intval($row['client_id']);
                        $client_name = nullable_htmlentities($row['client_name']);

                        // Calculate total tickets raised in period
                        $sql_ticket_raised_count = mysqli_query(
                            $mysqli,
                            "SELECT
                                COUNT(ticket_id) AS ticket_raised_count
                            FROM
                                tickets
                            WHERE
                                YEAR(ticket_created_at) = $year
                            AND
                                ticket_client_id = $client_id"
                        );
                        $row = mysqli_fetch_array($sql_ticket_raised_count);
                        $ticket_raised_count = intval($row['ticket_raised_count']);

                        // Calculate total tickets raised in period that are closed and billable
                        $sql_ticket_closed_count = mysqli_query(
                            $mysqli,
                            "SELECT
                                COUNT(ticket_id) AS ticket_closed_count
                            FROM
                                tickets
                            WHERE
                                YEAR(ticket_created_at) = $year
                            AND
                                ticket_client_id = $client_id
                            AND
                                ticket_closed_at IS NOT NULL
                            AND
                                ticket_billable = 1
                        ");
                        $row = mysqli_fetch_array($sql_ticket_closed_count);
                        $ticket_closed_count = intval($row['ticket_closed_count']);

                        // Calculate total tickets raised in period that are closed and billable, but not invoiced
                        $sql_ticket_unbilled_count = mysqli_query(
                            $mysqli,
                            "SELECT
                                COUNT(ticket_id) AS ticket_unbilled_count
                            FROM
                                tickets
                            WHERE
                                YEAR(ticket_created_at) = $year
                            AND
                                ticket_client_id = $client_id
                            AND
                                ticket_closed_at IS NOT NULL
                            AND
                                ticket_billable = 1
                            AND
                                ticket_invoice_id = 0");
                        $row = mysqli_fetch_array($sql_ticket_unbilled_count);
                        $ticket_unbilled_count = intval($row['ticket_unbilled_count']);

                        if ($ticket_unbilled_count > 0) {
                            $rows = $rows + 1;

                            ?>

                            <tr>
                                <td>
                                    <a href="tickets.php?client_id=<?php echo $client_id; ?>&billable=1&unbilled"><?php echo $client_name; ?></a>
                                </td>
                                <td class="text-right"><?php echo $ticket_raised_count; ?></td>
                                <td class="text-right"><?php echo $ticket_closed_count; ?></td>
                                <td class="text-right"><?php echo $ticket_unbilled_count; ?></td>
                            </tr>
                            <?php
                        }
                    }

                    if ($rows == 0) {
                        ?>
                        <tr>
                            <td colspan="4">You are all caught up! There are no unbilled tickets for this year.
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php
require_once "../includes/footer.php";

