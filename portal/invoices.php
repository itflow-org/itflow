<?php
/*
 * Client Portal
 * Invoices for PTC
 */

require_once("inc_portal.php");

if ($session_contact_id !== $session_client_primary_contact_id && !$session_contact_is_billing_contact) {
    header("Location: portal_post.php?logout");
    exit();
}

$invoices_sql = mysqli_query($mysqli, "SELECT * FROM invoices WHERE invoice_client_id = $session_client_id AND (invoice_status = 'Sent' OR invoice_status = 'Viewed' OR invoice_status = 'Paid') ORDER BY invoice_date DESC");
?>

    <div class="row">
        <div class="col-md-1 text-center">
            <?php if (!empty($session_contact_photo)) { ?>
                <img src="<?php echo "../uploads/clients/$session_client_id/$session_contact_photo"; ?>" alt="..." height="50" width="50" class="img-circle img-responsive">
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
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Due</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>

                <?php
                while ($row = mysqli_fetch_array($invoices_sql)) {
                    $invoice_id = intval($row['invoice_id']);
                    $invoice_prefix = htmlentities($row['invoice_prefix']);
                    $invoice_number = intval($row['invoice_number']);
                    $invoice_scope = htmlentities($row['invoice_scope']);
                    $invoice_status = htmlentities($row['invoice_status']);
                    $invoice_date = htmlentities($row['invoice_date']);
                    $invoice_due = htmlentities($row['invoice_due']);
                    $invoice_amount = floatval($row['invoice_amount']);
                    $invoice_url_key = htmlentities($row['invoice_url_key']);

                    if (empty($invoice_scope)) {
                        $invoice_scope_display = "-";
                    } else {
                        $invoice_scope_display = $invoice_scope;
                    }

                    $now = time();
                    if (($invoice_status == "Sent" || $invoice_status == "Partial" || $invoice_status == "Viewed") && strtotime($invoice_due) + 86400 < $now) {
                        $overdue_color = "text-danger font-weight-bold";
                    } else {
                        $overdue_color = "";
                    }

                    if ($invoice_status == "Sent") {
                        $invoice_badge_color = "warning text-white";
                    } elseif ($invoice_status == "Viewed") {
                        $invoice_badge_color = "info";
                    } elseif ($invoice_status == "Partial") {
                        $invoice_badge_color = "primary";
                    } elseif ($invoice_status == "Paid") {
                        $invoice_badge_color = "success";
                    } elseif ($invoice_status == "Cancelled") {
                        $invoice_badge_color = "danger";
                    } else{
                        $invoice_badge_color = "secondary";
                    }
                    ?>

                    <tr>
                        <td><a target="_blank" href="\\<?php echo $config_base_url ?>/guest_view_invoice.php?invoice_id=<?php echo "$invoice_id&url_key=$invoice_url_key"?>"> <?php echo "$invoice_prefix$invoice_number"; ?></a></td>
                        <td><?php echo $invoice_scope_display; ?></td>
                        <td><?php echo numfmt_format_currency($currency_format, $invoice_amount, $session_company_currency); ?></td>
                        <td><?php echo $invoice_date; ?></td>
                        <td class="<?php echo $overdue_color; ?>"><?php echo $invoice_due; ?></td>
                        <td>
                            <span class="p-2 badge badge-<?php echo $invoice_badge_color; ?>">
                                <?php echo $invoice_status; ?>
                            </span>
                        </td>

                    </tr>
                <?php } ?>

                </tbody>
            </table>

        </div>

    </div>


<?php
require_once("portal_footer.php");
