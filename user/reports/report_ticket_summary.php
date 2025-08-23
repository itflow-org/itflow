<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_support');

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

$sql_ticket_years = mysqli_query($mysqli, "SELECT DISTINCT YEAR(ticket_created_at) AS ticket_year FROM tickets ORDER BY ticket_year DESC");

$sql_tickets = mysqli_query($mysqli, "SELECT ticket_id FROM tickets");

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-life-ring mr-2"></i>Ticket Summary</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body p-0">
        <form class="p-3">
            <select onchange="this.form.submit()" class="form-control" name="year">
                <?php
                while ($row = mysqli_fetch_array($sql_ticket_years)) {
                    $ticket_year = intval($row['ticket_year']); ?>
                    <option <?php if ($year == $ticket_year) { ?> selected <?php } ?> > <?php echo $ticket_year; ?></option>
                <?php } ?>
            </select>
        </form>

        <canvas id="tickets" width="100%" height="20"></canvas>

        <div class="table-responsive-sm">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th class="text-right">January</th>
                    <th class="text-right">February</th>
                    <th class="text-right">March</th>
                    <th class="text-right">April</th>
                    <th class="text-right">May</th>
                    <th class="text-right">June</th>
                    <th class="text-right">July</th>
                    <th class="text-right">August</th>
                    <th class="text-right">September</th>
                    <th class="text-right">October</th>
                    <th class="text-right">November</th>
                    <th class="text-right">December</th>
                    <th class="text-right">Total</th>
                </tr>
                </thead>
                <tbody>

                <?php

                $total_tickets_for_all_months = 0;

                for ($month = 1; $month<=12; $month++) {

                    $sql_tickets = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS tickets_for_month FROM tickets WHERE YEAR(ticket_created_at) = $year AND MONTH(ticket_created_at) = $month");
                    $row = mysqli_fetch_array($sql_tickets);
                    $tickets_for_month = intval($row['tickets_for_month']);

                    $total_tickets_for_all_months = $tickets_for_month + $total_tickets_for_all_months;
                    ?>

                    <td class="text-right"><?php echo $tickets_for_month; ?></td>

                <?php } ?>


                <td class="text-right"><b><?php echo $total_tickets_for_all_months; ?></b></td>

                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php";
 ?>

<script>
    // Set new default font family and font color to mimic Bootstrap's default styling
    Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
    Chart.defaults.global.defaultFontColor = '#292b2c';

    // Area Chart Example
    var ctx = document.getElementById("tickets");
    var myLineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
            datasets: [{
                label: "Tickets Raised",
                fill: false,
                borderColor: "#007bff",
                pointBackgroundColor: "#007bff",
                pointBorderColor: "#007bff",
                pointHoverRadius: 5,
                pointHoverBackgroundColor: "#007bff",
                pointHitRadius: 50,
                pointBorderWidth: 2,
                data: [
                    <?php

                    $largest_ticket_month = 0;

                    for ($month = 1; $month<=12; $month++) {

                        $sql_tickets = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS tickets_for_month FROM tickets WHERE YEAR(ticket_created_at) = $year AND MONTH(ticket_created_at) = $month");
                        $row = mysqli_fetch_array($sql_tickets);
                        $tickets_for_month = intval($row['tickets_for_month']);

                        if ($tickets_for_month > 0 && $tickets_for_month > $largest_ticket_month) {
                            $largest_ticket_month = $tickets_for_month;
                        }

                        echo "$tickets_for_month,";
                    }
                    ?>

                ],
            }],
        },
        options: {
            scales: {
                xAxes: [{
                    time: {
                        unit: 'date'
                    },
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        maxTicksLimit: 12
                    }
                }],
                yAxes: [{
                    ticks: {
                        min: 0,
                        max: <?php echo $largest_ticket_month ?>,
                        maxTicksLimit: 5
                    },
                    gridLines: {
                        color: "rgba(0, 0, 0, .125)",
                    }
                }],
            },
            legend: {
                display: false
            }
        }
    });
</script>
