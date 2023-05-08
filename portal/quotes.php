<?php
/*
 * Client Portal
 * Quotes for PTC / billing contacts
 */

header("Content-Security-Policy: default-src 'self' https: fonts.googleapis.com");

require_once("inc_portal.php");

if ($session_contact_id !== $session_client_primary_contact_id && !$session_contact_is_billing_contact) {
    header("Location: portal_post.php?logout");
    exit();
}

$quotes_sql = mysqli_query($mysqli, "SELECT * FROM quotes WHERE quote_client_id = $session_client_id AND quote_status != 'Draft' ORDER BY quote_date DESC");
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
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>

                <?php
                while ($row = mysqli_fetch_array($quotes_sql)) {
                    $quote_id = intval($row['quote_id']);
                    $quote_prefix = htmlentities($row['quote_prefix']);
                    $quote_number = intval($row['quote_number']);
                    $quote_scope = htmlentities($row['quote_scope']);
                    $quote_status = htmlentities($row['quote_status']);
                    $quote_date = htmlentities($row['quote_date']);
                    $quote_amount = floatval($row['quote_amount']);
                    $quote_url_key = htmlentities($row['quote_url_key']);

                    if (empty($quote_scope)) {
                        $quote_scope_display = "-";
                    } else {
                        $quote_scope_display = $quote_scope;
                    }

                    if ($quote_status == "Sent") {
                        $quote_badge_color = "warning text-white";
                    } elseif ($quote_status == "Viewed") {
                        $quote_badge_color = "primary";
                    } elseif ($quote_status == "Accepted") {
                        $quote_badge_color = "success";
                    } elseif ($quote_status == "Declined") {
                        $quote_badge_color = "danger";
                    } elseif ($quote_status == "Invoiced") {
                        $quote_badge_color = "info";
                    } else {
                        $quote_badge_color = "secondary";
                    }

                    ?>

                    <tr>
                        <td><a target="_blank" href="\\<?php echo $config_base_url ?>/guest_view_quote.php?quote_id=<?php echo "$quote_id&url_key=$quote_url_key"?>"> <?php echo "$quote_prefix$quote_number"; ?></a></td>
                        <td><?php echo $quote_scope_display; ?></td>
                        <td><?php echo numfmt_format_currency($currency_format, $quote_amount, $session_company_currency); ?></td>
                        <td><?php echo $quote_date; ?></td>
                        <td>
                            <span class="p-2 badge badge-<?php echo $quote_badge_color; ?>">
                                <?php echo $quote_status; ?>
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
