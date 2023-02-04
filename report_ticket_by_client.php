<?php

require_once("inc_all_reports.php");
validateTechRole();

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

$sql_ticket_years = mysqli_query($mysqli,"SELECT DISTINCT YEAR(ticket_created_at) AS ticket_year FROM tickets WHERE company_id = $session_company_id ORDER BY ticket_year DESC");

$sql_clients = mysqli_query($mysqli,"SELECT client_id, client_name FROM clients WHERE company_id = $session_company_id ORDER BY client_name ASC");

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-life-ring"></i> Tickets By Client</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print"></i> Print</button>
            </div>
        </div>
        <div class="card-body">
            <form class="mb-3">
                <select onchange="this.form.submit()" class="form-control" name="year">
                    <?php
                    while ($row = mysqli_fetch_array($sql_ticket_years)) {
                        $ticket_year = $row['ticket_year']; ?>
                        <option <?php if ($year == $ticket_year) { ?> selected <?php } ?> > <?php echo $ticket_year; ?></option>
                    <?php } ?>
                </select>
            </form>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Client</th>
                        <th class="text-right">Ticket Count</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    while ($row = mysqli_fetch_array($sql_clients)) {
                        $client_id = $row['client_id'];
                        $client_name = htmlentities($row['client_name']);

                        $sql_ticket_count = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_count FROM tickets WHERE YEAR(ticket_created_at) = $year AND ticket_client_id = '$client_id'");
                        $row = mysqli_fetch_array($sql_ticket_count);

                        $ticket_count = intval($row['ticket_count']);

                        if ($ticket_count > 0) {

                            ?>

                            <tr>
                                <td><?php echo $client_name; ?></td>
                                <td class="text-right"><?php echo $ticket_count; ?></td>
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
require_once("footer.php");
