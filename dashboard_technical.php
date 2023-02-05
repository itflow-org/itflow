<?php

require_once("inc_all.php");

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

// GET unique years from expenses, payments and revenues
$sql_payment_years = mysqli_query($mysqli, "SELECT YEAR(expense_date) AS all_years FROM expenses
    WHERE company_id = $session_company_id
    UNION DISTINCT SELECT YEAR(payment_date) FROM payments WHERE company_id = $session_company_id
    UNION DISTINCT SELECT YEAR(revenue_date) FROM revenues WHERE company_id = $session_company_id
    ORDER BY all_years DESC"
);

// Get Total Clients added
$sql_clients = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('client_id') AS clients_added FROM clients
    WHERE YEAR(client_created_at) = $year
    AND company_id = $session_company_id"
));
$clients_added = $sql_clients['clients_added'];

// Get Total contacts added
$sql_contacts = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('contact_id') AS contacts_added FROM contacts
    WHERE YEAR(contact_created_at) = $year
    AND company_id = $session_company_id"
));
$contacts_added = $sql_contacts['contacts_added'];

// Get Total assets added
$sql_assets = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('asset_id') AS assets_added FROM assets
    WHERE YEAR(asset_created_at) = $year
    AND company_id = $session_company_id"
));
$assets_added = $sql_assets['assets_added'];

// Ticket count
$sql_tickets = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ticket_id') AS active_tickets
    FROM tickets
    WHERE ticket_status != 'Closed'
    AND company_id = $session_company_id"
));
$active_tickets = $sql_tickets['active_tickets'];

// Expiring domains (but not ones that have already expired)
$sql_domains_expiring = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('domain_id') as expiring_domains
    FROM domains
    WHERE domain_expire != '0000-00-00'
    AND domain_expire > CURRENT_DATE
    AND domain_expire < CURRENT_DATE + INTERVAL 30 DAY
    AND domain_archived_at IS NULL
    AND company_id = $session_company_id"
));
$expiring_domains = $sql_domains_expiring['expiring_domains'];

// Expiring Certificates (but not ones that have already expired)
$sql_certs_expiring = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('certificate_id') as expiring_certs
    FROM certificates
    WHERE certificate_expire != '0000-00-00'
    AND certificate_expire > CURRENT_DATE
    AND certificate_expire < CURRENT_DATE + INTERVAL 30 DAY
    AND certificate_archived_at IS NULL
    AND company_id = $session_company_id"
));
$expiring_certificates = $sql_certs_expiring['expiring_certs'];

?>

<form class="mb-3">
    <select onchange="this.form.submit()" class="form-control" name="year">
        <?php

        while ($row = mysqli_fetch_array($sql_payment_years)) {
            $payment_year = $row['all_years'];
            if (empty($payment_year)) {
                $payment_year = date('Y');
            }
            ?>
            <option <?php if ($year == $payment_year) { echo "selected"; } ?> > <?php echo $payment_year; ?></option>

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
        <a class="small-box bg-success"">
        <div class="inner">
            <h3><?php echo $contacts_added; ?></h3>
            <p>New Contacts</p>
        </div>
        <div class="icon">
            <i class="fa fa-user"></i>
        </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-6">
        <a class="small-box bg-info"">
        <div class="inner">
            <h3><?php echo $assets_added; ?></h3>
            <p>New Assets</p>
        </div>
        <div class="icon">
            <i class="fa fa-desktop"></i>
        </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-6">
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

    <div class="col-lg-4 col-6">
        <a class="small-box bg-warning">
            <div class="inner">
                <h3><?php echo $expiring_domains; ?></h3>
                <p>Expiring Domains</p>
            </div>
            <div class="icon">
                <i class="fa fa-globe"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

    <div class="col-lg-4 col-6">
        <a class="small-box bg-primary">
            <div class="inner">
                <h3><?php echo $expiring_certificates; ?></h3>
                <p>Expiring Certificates</p>
            </div>
            <div class="icon">
                <i class="fa fa-lock"></i>
            </div>
        </a>
    </div>
    <!-- ./col -->

</div> <!-- rows -->

<?php
require_once("footer.php");

