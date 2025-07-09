<?php
/*
 * Client Portal
 * Invoices for PTC
 */

// header("Content-Security-Policy: default-src 'self'"); -- JQ 2025-07-09 - BREAKS onchange(submit)

require_once "includes/inc_all.php";


if ($session_contact_primary == 0 && !$session_contact_is_billing_contact) {
    header("Location: post.php?logout");
    exit();
}

$recurring_invoices_sql = mysqli_query($mysqli, "SELECT * FROM recurring_invoices 
    LEFT JOIN recurring_payments ON recurring_payment_recurring_invoice_id = recurring_invoice_id
    WHERE recurring_invoice_client_id = $session_client_id
    AND recurring_invoice_status = 1
    ORDER BY recurring_invoice_next_date DESC"
);

?>

<h3>Recurring Invoices</h3>
<div class="row">

    <div class="col-md-10">

        <table class="table tabled-bordered border border-dark">
            <thead class="thead-dark">
            <tr>
                <th>Scope</th>
                <th>Amount</th>
                <th>Next Bill Date</th>
                <th>Frequency</th>
                <?php if ($config_stripe_enable) { ?>
                <th>Auto Pay</th>
                <?php } ?>
            </tr>
            </thead>
            <tbody>

            <?php
            while ($row = mysqli_fetch_array($recurring_invoices_sql)) {
                $recurring_invoice_id = intval($row['recurring_invoice_id']);
                $recurring_invoice_prefix = nullable_htmlentities($row['recurring_invoice_prefix']);
                $recurring_invoice_number = intval($row['recurring_invoice_number']);
                $recurring_invoice_scope = nullable_htmlentities($row['recurring_invoice_scope']);
                $recurring_invoice_status = nullable_htmlentities($row['recurring_invoice_status']);
                $recurring_invoice_next_date = nullable_htmlentities($row['recurring_invoice_next_date']);
                $recurring_invoice_frequency = nullable_htmlentities($row['recurring_invoice_frequency']);
                $recurring_invoice_amount = floatval($row['recurring_invoice_amount']);
                $recurring_payment_id = intval($row['recurring_payment_id']);
                $recurring_payment_recurring_invoice_id = intval($row['recurring_payment_recurring_invoice_id']);
                $recurring_payment_saved_payment_id = intval($row['recurring_payment_saved_payment_id']);
                
                if (empty($recurring_invoice_scope)) {
                    $recurring_invoice_scope_display = "-";
                } else {
                    $recurring_invoice_scope_display = $recurring_invoice_scope;
                }
                ?>

                <tr>
                    <td><?php echo $recurring_invoice_scope_display; ?></td>
                    <td><?php echo numfmt_format_currency($currency_format, $recurring_invoice_amount, $session_company_currency); ?></td>
                    <td><?php echo $recurring_invoice_next_date; ?></td>
                    <td><?php echo ucwords($recurring_invoice_frequency); ?>ly</td>
                    <?php if ($config_stripe_enable) { ?>
                    <td>
                        <?php $sql = mysqli_query($mysqli, "SELECT * FROM client_saved_payment_methods WHERE saved_payment_client_id = $session_client_id");
                        if (mysqli_num_rows($sql) > 0) { ?>
                            <form class="form" action="post.php" method="post">
                                <input type="hidden" name="set_recurring_payment" value="1">
                                <input type="hidden" name="recurring_invoice_id" value="<?php echo $recurring_invoice_id; ?>">
                                <select class="form-control select2" name="saved_payment_id" onchange="this.form.submit()">
                                    <option value="0">Disabled</option>
                                    <?php
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $saved_payment_id = intval($row['saved_payment_id']);
                                            $saved_payment_description = nullable_htmlentities($row['saved_payment_description']);

                                        ?>
                                        <option <?php if ($recurring_payment_saved_payment_id == $saved_payment_id) { echo "selected"; } ?> value="<?php echo $saved_payment_id; ?>"><?php echo $saved_payment_description; ?></option>
                                    <?php } ?>
                                </select>
                            </form>
                        <?php } else { ?>
                            <a href="saved_payment_method.php">Add a Payment Method</a>
                        <?php } ?>
                    </td>
                    <?php } ?>
                </tr>
            <?php } ?>

            </tbody>
        </table>

    </div>

</div>

<?php
require_once "includes/footer.php";
