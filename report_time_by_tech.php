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

$sql_users = mysqli_query($mysqli, "
                SELECT users.user_id, user_name FROM users
                LEFT JOIN user_settings on users.user_id = user_settings.user_id
                WHERE user_type = 1
                    AND user_status = 1
                    AND user_archived_at IS NULL 
                ORDER BY user_name DESC"
);
// TODO: Maybe try and filter this to just users with the support module perm

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-life-ring mr-2"></i>Time Logged By Technician</h3>
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

            <div class="card card-dark mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-fw fa-chart-area mr-2"></i>Yearly (<?php echo $year; ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive-sm">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Technician</th>
                                <th class="text-right">Tickets assigned</th>
                                <th class="text-right">Tickets touched</th>
                                <th class="text-right">Total time worked <i>(H:M:S)</i></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($agent_row = mysqli_fetch_array($sql_users)) {
                                $user_id = intval($agent_row['user_id']);
                                $user_name = nullable_htmlentities($agent_row['user_name']);

                                // Get tickets in period that are still assigned to the technician/agent
                                $sql_ticket_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND ticket_assigned_to = $user_id");
                                $row = mysqli_fetch_array($sql_ticket_count);
                                $ticket_raised_count = intval($row['ticket_count']);

                                // Get unique tickets in period that the agent replied to/touched
                                $sql_tickets_touched = mysqli_query($mysqli, "
                                    SELECT COUNT(DISTINCT ticket_id) AS tickets_touched
                                    FROM (
                                        -- Tickets the agent replied to
                                        SELECT ticket_reply_ticket_id AS ticket_id 
                                        FROM ticket_replies 
                                        WHERE YEAR(ticket_reply_created_at) = $year AND ticket_reply_by = $user_id
                                        
                                        UNION
                                        
                                        -- Tickets the agent opened
                                        SELECT ticket_id 
                                        FROM tickets 
                                        WHERE YEAR(ticket_created_at) = $year AND ticket_created_by = $user_id
                                        
                                        UNION
                                        
                                        -- Tickets the agent closed
                                        SELECT ticket_id 
                                        FROM tickets 
                                        WHERE YEAR(ticket_created_at) = $year AND ticket_closed_by = $user_id
                                    )
                                    AS tickets_touched
                                ");

                                $row = mysqli_fetch_array($sql_tickets_touched);
                                $tickets_touched = intval($row['tickets_touched']);


                                // Calculate total time tracked towards tickets in the period (for this agent)
                                $sql_time = mysqli_query($mysqli, "SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) as total_time FROM ticket_replies LEFT JOIN tickets ON tickets.ticket_id = ticket_replies.ticket_reply_ticket_id WHERE YEAR(ticket_created_at) = $year AND ticket_reply_by = $user_id AND ticket_reply_time_worked IS NOT NULL");
                                $row = mysqli_fetch_array($sql_time);
                                $ticket_total_time_worked = nullable_htmlentities($row['total_time']);

                                ?>

                                <tr>
                                    <td><?php echo $user_name; ?></td>
                                    <td class="text-right"><?php echo $ticket_raised_count; ?></td>
                                    <td class="text-right"><?php echo $tickets_touched; ?></td>
                                    <td class="text-right"><?php echo $ticket_total_time_worked; ?></td>
                                </tr>

                                <?php

                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TODO: Monthly version of this report -->

        </div>
    </div>

<?php
require_once "includes/footer.php";

