<?php

require_once "includes/inc_all_admin.php";

$stripe_clients_sql = mysqli_query($mysqli, "SELECT * FROM client_stripe LEFT JOIN clients ON client_stripe.client_id = clients.client_id");
?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-credit-card mr-2"></i>Online Payment - Client info</h3>
        </div>

        <div class="card-body">

            <table class="table border border-dark">
                <thead class="thead-dark">
                <tr>
                    <th>Client</th>
                    <th>Stripe Customer ID</th>
                    <th>Stripe Payment ID</th>
                    <th>Payment Details</th>
                    <th>Created</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>

                <?php
                while ($row = mysqli_fetch_array($stripe_clients_sql)) {
                    $client_id = intval($row['client_id']);
                    $client_name = nullable_htmlentities($row['client_name']);
                    $stripe_id = nullable_htmlentities($row['stripe_id']);
                    $stripe_pm = nullable_htmlentities($row['stripe_pm']);
                    $stripe_pm_details = nullable_htmlentities($row['stripe_pm_details']);
                    $stripe_pm_created_at = nullable_htmlentities($row['stripe_pm_created_at']);

                    ?>

                    <tr>
                        <td><?php echo "$client_name ($client_id)"; ?></td>
                        <td><?php echo $stripe_id; ?></td>
                        <td><?php echo $stripe_pm; ?></td>
                        <td><?php echo $stripe_pm_details; ?></td>
                        <td><?php echo $stripe_pm_created_at; ?></td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <?php if (!empty($stripe_pm)) { ?>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?stripe_remove_pm&client_id=<?php echo $client_id ?>&pm=<?php echo $stripe_pm ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-credit-card mr-2"></i>Delete payment method
                                        </a>
                                    <?php } else { ?>
                                        <a data-toggle="tooltip" data-placement="left" title="May result in duplicate customer records in Stripe" class="dropdown-item text-danger confirm-link" href="post.php?stripe_reset_customer&client_id=<?php echo $client_id ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Reset Stripe
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>

                    </tr>

                <?php } ?>

                </tbody>
            </table>

        </div>

    </div>

<?php
require_once "includes/footer.php";
