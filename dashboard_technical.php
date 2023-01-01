<?php include("inc_all.php"); ?>

<?php

if(isset($_GET['year'])){
$year = intval($_GET['year']);
}else{
$year = date('Y');
}

//GET unique years from expenses, payments and revenues
$sql_payment_years = mysqli_query($mysqli,"SELECT YEAR(expense_date) AS all_years FROM expenses WHERE company_id = $session_company_id UNION DISTINCT SELECT YEAR(payment_date) FROM payments WHERE company_id = $session_company_id UNION DISTINCT SELECT YEAR(revenue_date) FROM revenues WHERE company_id = $session_company_id ORDER BY all_years DESC");

// Get Total Clients added
$sql_clients = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT('client_id') AS clients_added FROM clients WHERE YEAR(client_created_at) = $year AND company_id = $session_company_id"));
$clients_added = $sql_clients['clients_added'];

// Ticket count
$sql_tickets = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ticket_id') AS active_tickets FROM tickets WHERE ticket_status != 'Closed'"));
$active_tickets = $sql_tickets['active_tickets'];

?>

<form class="mb-3">
    <select onchange="this.form.submit()" class="form-control" name="year">
        <?php

        while($row = mysqli_fetch_array($sql_payment_years)){
            $payment_year = $row['all_years'];
            if(empty($payment_year)){
                $payment_year = date('Y');
            }
            ?>
            <option <?php if($year == $payment_year){ echo "selected"; } ?> > <?php echo $payment_year; ?></option>

            <?php
        }
        ?>

    </select>
</form>

<!-- Icon Cards-->
<div class="row">

    <div class="col-lg-4 col-6">
        <!-- small box -->
        <a class="small-box bg-secondary" href="clients.php?date_from=<?php echo $year; ?>-01-01&date_to=<?php echo $year; ?>-12-31">
            <div class="inner">
                <h3><?php echo $clients_added; ?></h3>
                <p>New Clients</p>
            </div>
            <div class="icon">
                <i class="fa fa-users"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-6">
        <!-- small box -->
        <a class="small-box bg-danger" href="tickets.php">
            <div class="inner">
                <h3><?php echo $active_tickets; ?></h3>
                <p>Active Tickets</p>
            </div>
            <div class="icon">
                <i class="fa fa-ticket-alt"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

</div> <!-- row -->

<?php include("footer.php"); ?>