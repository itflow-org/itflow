<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/guest/includes/inc_all_guest.php';


if (!isset($_GET['quote_id'], $_GET['url_key'])) {
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';

    exit();
}


$url_key = escapeSql($_GET['url_key']);
$quote_id = intval($_GET['quote_id']);

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM quotes
    LEFT JOIN clients ON quote_client_id = client_id
    LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
    LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
    WHERE quote_id = $quote_id
    AND quote_url_key = '$url_key'"
);

if (mysqli_num_rows($sql) !== 1) {
    // Invalid quote/key
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';

    exit();
}

$row = mysqli_fetch_assoc($sql);

$quote_id = intval($row['quote_id']);
$quote_prefix = escapeHtml($row['quote_prefix']);
$quote_number = intval($row['quote_number']);
$quote_status = escapeHtml($row['quote_status']);
$quote_date = escapeHtml($row['quote_date']);
$quote_expire = escapeHtml($row['quote_expire']);
$quote_discount = floatval($row['quote_discount_amount']);
$quote_amount = floatval($row['quote_amount']);
$quote_currency_code = escapeHtml($row['quote_currency_code']);
$quote_note = escapeHtml($row['quote_note']);
$client_id = intval($row['client_id']);
$client_name = escapeHtml($row['client_name']);
$client_name_escaped = escapeSql($row['client_name']);
$location_address = escapeHtml($row['location_address']);
$location_city = escapeHtml($row['location_city']);
$location_state = escapeHtml($row['location_state']);
$location_zip = escapeHtml($row['location_zip']);
$location_country = escapeHtml($row['location_country']);
$contact_email = escapeHtml($row['contact_email']);
$contact_phone_country_code = escapeHtml($row['contact_phone_country_code']);
$contact_phone = escapeHtml(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
$contact_extension = escapeHtml($row['contact_extension']);
$contact_mobile_country_code = escapeHtml($row['contact_mobile_country_code']);
$contact_mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
$client_website = escapeHtml($row['client_website']);
$client_currency_code = escapeHtml($row['client_currency_code']);

$sql = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
$row = mysqli_fetch_assoc($sql);
$company_name = escapeHtml($row['company_name']);
$company_address = escapeHtml($row['company_address']);
$company_city = escapeHtml($row['company_city']);
$company_state = escapeHtml($row['company_state']);
$company_zip = escapeHtml($row['company_zip']);
$company_country = escapeHtml($row['company_country']);
$company_phone_country_code = escapeHtml($row['company_phone_country_code']);
$company_phone = escapeHtml(formatPhoneNumber($row['company_phone'], $company_phone_country_code));
$company_email = escapeHtml($row['company_email']);
$company_website = escapeHtml($row['company_website']);
$company_logo = escapeHtml($row['company_logo']);
if (!empty($company_logo)) {
    $company_logo_base64 = base64_encode(file_get_contents("../uploads/settings/$company_logo"));
}
$company_locale = escapeHtml($row['company_locale']);
$config_quote_footer = escapeHtml($row['config_quote_footer']);

//Set Currency Format
$currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

//Set Badge color based off of quote status
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

//Update status to Viewed only if invoice_status = "Sent"
if ($quote_status == 'Sent') {
    mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Viewed' WHERE quote_id = $quote_id");
}

//Mark viewed in history
mysqli_query($mysqli, "INSERT INTO history SET history_status = '$quote_status', history_description = 'Quote viewed - $ip - $os - $browser', history_quote_id = $quote_id");

if ($quote_status == "Draft" || $quote_status == "Sent" || $quote_status == "Viewed") {

    appNotify("Quote Viewed", "Quote $quote_prefix$quote_number has been viewed by $client_name_escaped - $ip - $os - $browser", "/agent/quote.php?quote_id=$quote_id", $client_id);
}

?>

<div class="card">

    <div class="card-header d-print-none">

        <div class="float-right">
            <a class="btn btn-primary" href="#" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</a>
            <a class="btn btn-primary" href="guest_post.php?export_quote_pdf=<?php echo $quote_id; ?>&url_key=<?php echo $url_key; ?>">
                <i class="fa fa-fw fa-download mr-2"></i>Download
            </a>
        </div>
    </div>
    <div class="card-body">

        <div class="row mb-3">
            <?php if (file_exists("../uploads/settings/$company_logo")) { ?>
            <div class="col-sm-2">
                <img class="img-fluid" src="<?php echo "/uploads/settings/$company_logo"; ?>" alt="Company logo">
            </div>
            <?php } ?>
            <div class="col-sm-6 <?php if (!file_exists("../uploads/settings/$company_logo")) { echo "col-sm-8"; } ?>">
                <ul class="list-unstyled">
                    <li><h4><strong><?php echo $company_name; ?></strong></h4></li>
                    <li><?php echo $company_address; ?></li>
                    <li><?php echo "$company_city $company_state $company_zip, $company_country"; ?></li>
                    <li><?php echo "$company_email | $company_phone"; ?></li>
                    <li><?php echo $company_website; ?></li>
                </ul>
            </div>

            <div class="col-sm-4">
                <h3 class="text-right"><strong>QUOTE</strong></h3>
                <h5 class="badge badge-<?php echo $quote_badge_color; ?> p-2 float-right">
                    <?php echo "$quote_status"; ?>
                </h5>
                <table class="table table-sm table-borderless">
                    <tr>
                        <th>Quote #:</th>
                        <td class="text-right"><?php echo "$quote_prefix$quote_number"; ?></td>
                    </tr>
                    <tr>
                        <th>Date:</th>
                        <td class="text-right"><?php echo $quote_date; ?></td>
                    </tr>
                    <tr>
                        <th>Expires:</th>
                        <td class="text-right"><?php echo $quote_expire; ?></td>
                    </tr>
                </table>
            </div>

        </div>
        <div class="row mb-3 bg-light p-3">
            <div class="col">
                <h6><strong>To:</strong></h6>
                <ul class="list-unstyled mb-0">
                    <li><?php echo $client_name; ?></li>
                    <li><?php echo $location_address; ?></li>
                    <li><?php echo "$location_city $location_state $location_zip, $location_country"; ?></li>
                    <li><?php echo "$contact_email | $contact_phone $contact_extension"; ?></li>
                </ul>
            </div>
        </div>

        <?php $sql_items = mysqli_query($mysqli, "SELECT * FROM quote_items WHERE item_quote_id = $quote_id ORDER BY item_order ASC"); ?>

        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                            <tr>
                                <th>Item</th>
                                <th>Description</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Tax</th>
                                <th class="text-right">Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            $total_tax = $sub_total = 0; // Default 0

                            while ($row = mysqli_fetch_assoc($sql_items)) {
                                $item_id = intval($row['item_id']);
                                $item_name = escapeHtml($row['item_name']);
                                $item_description = escapeHtml($row['item_description']);
                                $item_quantity = floatval($row['item_quantity']);
                                $item_price = floatval($row['item_price']);
                                $item_tax = floatval($row['item_tax']);
                                $item_total = floatval($row['item_total']);
                                $total_tax = $item_tax + $total_tax;
                                $sub_total = $item_price * $item_quantity + $sub_total;

                                ?>

                                <tr>
                                    <td><?php echo $item_name; ?></td>
                                    <td><?php echo nl2br($item_description); ?></td>
                                    <td class="text-center"><?php echo $item_quantity; ?></td>
                                    <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_price, $quote_currency_code); ?></td>
                                    <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_tax, $quote_currency_code); ?></td>
                                    <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_total, $quote_currency_code); ?></td>
                                </tr>

                                <?php

                            }

                            ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-sm-7">
                <?php if (!empty($quote_note)) { ?>
                    <div class="card">
                        <div class="card-body">
                            <?php echo nl2br($quote_note); ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="col-sm-3 offset-sm-2">
                <table class="table table-hover mb-0">
                    <tbody>
                    <tr>
                        <td>Subtotal:</td>
                        <td class="text-right"><?php echo numfmt_format_currency($currency_format, $sub_total, $quote_currency_code); ?></td>
                    </tr>
                    <?php if ($quote_discount > 0) { ?>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, -$quote_discount, $quote_currency_code); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if ($total_tax > 0) { ?>
                        <tr>
                            <td>Tax:</td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $total_tax, $quote_currency_code); ?></td>
                        </tr>
                    <?php } ?>
                    <tr class="border-top h5 text-bold">
                        <td><strong>Total:</strong></td>
                        <td class="text-right"><strong><?php echo numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code); ?></strong></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <hr class="mt-5">

        <div class="text-center"><?php echo nl2br($config_quote_footer); ?></div>
        <div class="">
            <?php
                if ($quote_status == "Sent" || $quote_status == "Viewed" && strtotime($quote_expire) > strtotime("now")) { ?>
                    <a class="btn btn-success confirm-link" href="guest_post.php?accept_quote=<?php echo $quote_id; ?>&url_key=<?php echo $url_key; ?>">
                        <i class="fas fa-fw fa-thumbs-up mr-2"></i>Accept
                    </a>
                    <a class="btn btn-danger confirm-link" href="guest_post.php?decline_quote=<?php echo $quote_id; ?>&url_key=<?php echo $url_key; ?>">
                        <i class="fas fa-fw fa-thumbs-down mr-2"></i>Decline
                    </a>
                <?php } ?>
                <?php if ($quote_status == "Accepted") { ?>
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#uploadFileModal">
                        <i class="fas fa-fw fa-cloud-upload-alt mr-2"></i>Upload File
                    </button>
                <?php } ?>
        </div>

    </div>
</div>

<?php
require_once "guest_quote_upload_file_modal.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
