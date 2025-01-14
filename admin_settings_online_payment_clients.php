<?php

require_once "includes/inc_all_admin.php";

$stripe_clients_sql = mysqli_query($mysqli, "SELECT * FROM client_stripe LEFT JOIN clients ON client_stripe.client_id = clients.client_id");
?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-credit-card mr-2"></i>Online Payment - Client info</h3>
        </div>

        <div class="card-body">

            <table class="table tabled-bordered border border-dark">
                <thead class="thead-dark">
                <tr>
                    <th>Client</th>
                    <th>Stripe Customer ID</th>
                    <th>Stripe Payment ID</th>
                </tr>
                </thead>
                <tbody>

                <?php
                while ($row = mysqli_fetch_array($stripe_clients_sql)) {
                    $client_id = intval($row['client_id']);
                    $client_name = sanitizeInput($row['client_name']);
                    $stripe_id = sanitizeInput($row['stripe_id']);
                    $stripe_pm = sanitizeInput($row['stripe_pm']);

                    ?>

                    <tr>
                        <td><?php echo "$client_name ($client_id)" ?></td>
                        <td><?php echo $stripe_id; ?></td>
                        <td><?php echo $stripe_pm ?></td>
                    </tr>

                <?php } ?>

                </tbody>
            </table>

        </div>

    </div>

<?php
require_once "includes/footer.php";

