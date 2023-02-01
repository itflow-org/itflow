<?php
/*
 * Client Portal
 * Invoices for PTC
 */

/*
TODO:
    - Allow accounting contacts to see this page
    - Tidy styling and add currency codes
    - Add links to see the invoice in full (similar to invoice guest view)
*/

require_once("inc_portal.php");

if ($session_contact_id !== $session_client_primary_contact_id) {
    header("Location: portal_post.php?logout");
    exit();
}

$invoices_sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_client_id = $session_client_id AND invoice_status = 'Paid' ORDER BY invoice_date DESC");
?>

    <div class="row">
        <div class="col-md-1 text-center">
            <?php if (!empty($session_contact_photo)) { ?>
                <img src="<?php echo "../uploads/clients/$session_company_id/$session_client_id/$session_contact_photo"; ?>" alt="..." height="50" width="50" class="img-circle img-responsive">

            <?php } else { ?>

                <span class="fa-stack fa-2x rounded-left">
                <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                <span class="fa fa-stack-1x text-white"><?php echo $session_contact_initials; ?></span>
            </span>
            <?php } ?>
        </div>

        <div class="col-md-11 p-0">
            <h4>Welcome, <strong><?php echo $session_contact_name ?></strong>!</h4>
            <hr>
        </div>

    </div>

    <br>

    <div class="row">

        <div class="col-md-10">

            <table class="table tabled-bordered border border-dark">
                <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>Scope</th>
                    <th>Date</th>
                    <th>Amount</th>
                </tr>
                </thead>
                <tbody>

                <?php
                while ($row = mysqli_fetch_array($invoices_sql)) {
                    $invoice_id = $row['invoice_id'];
                    $invoice_prefix = htmlentities($row['invoice_prefix']);
                    $invoice_number = htmlentities($row['invoice_number']);
                    $invoice_scope = htmlentities($row['invoice_scope']);
                    $invoice_date = $row['invoice_date'];
                    $invoice_amount = floatval($row['invoice_amount']);
                    ?>

                    <tr>
                        <td><?php echo "$invoice_prefix$invoice_number"; ?></a></td>
                        <td><?php echo $invoice_scope; ?></td>
                        <td><?php echo $invoice_date; ?></td>
                        <td><?php echo $invoice_amount; ?></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>

        </div>

    </div>


<?php
require_once("portal_footer.php");
