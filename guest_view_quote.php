<?php

require_once("guest_header.php");

if (!isset($_GET['quote_id'], $_GET['url_key'])) {
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
    require_once("guest_footer.php");
    exit();
}


$url_key = mysqli_real_escape_string($mysqli, $_GET['url_key']);
$quote_id = intval($_GET['quote_id']);

$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM quotes
    LEFT JOIN clients ON quote_client_id = client_id
    LEFT JOIN locations ON primary_location = location_id
    LEFT JOIN contacts ON primary_contact = contact_id
    LEFT JOIN companies ON quotes.company_id = companies.company_id
    LEFT JOIN settings ON settings.company_id = companies.company_id
    WHERE quote_id = $quote_id
    AND quote_url_key = '$url_key'"
);

if (mysqli_num_rows($sql) !== 1) {
    // Invalid quote/key
    echo "<br><h2>Oops, something went wrong! Please raise a ticket if you believe this is an error.</h2>";
    require_once("guest_footer.php");
    exit();
}

$row = mysqli_fetch_array($sql);

$quote_id = $row['quote_id'];
$quote_prefix = htmlentities($row['quote_prefix']);
$quote_number = htmlentities($row['quote_number']);
$quote_status = htmlentities($row['quote_status']);
$quote_date = $row['quote_date'];
$quote_amount = floatval($row['quote_amount']);
$quote_currency_code = htmlentities($row['quote_currency_code']);
$quote_note = htmlentities($row['quote_note']);
$category_id = $row['category_id'];
$client_id = $row['client_id'];
$client_name = htmlentities($row['client_name']);
$location_address = htmlentities($row['location_address']);
$location_city = htmlentities($row['location_city']);
$location_state = htmlentities($row['location_state']);
$location_zip = htmlentities($row['location_zip']);
$contact_email = htmlentities($row['contact_email']);
$contact_phone = formatPhoneNumber($row['contact_phone']);
$contact_extension = htmlentities($row['contact_extension']);
$contact_mobile = formatPhoneNumber($row['contact_mobile']);
$client_website = htmlentities($row['client_website']);
$client_currency_code = htmlentities($row['client_currency_code']);
$client_net_terms = htmlentities($row['client_net_terms']);
if ($client_net_terms == 0) {
    $client_net_terms = intval($row['config_default_net_terms']);
}
$company_id = $row['company_id'];
$company_name = htmlentities($row['company_name']);
$company_address = htmlentities($row['company_address']);
$company_city = htmlentities($row['company_city']);
$company_state = htmlentities($row['company_state']);
$company_zip = htmlentities($row['company_zip']);
$company_phone = formatPhoneNumber($row['company_phone']);
$company_email = htmlentities($row['company_email']);
$company_website = htmlentities($row['company_website']);
$company_logo = htmlentities($row['company_logo']);
if (!empty($company_logo)) {
    $company_logo_base64 = base64_encode(file_get_contents("uploads/settings/$company_id/$company_logo"));
}
$company_locale = htmlentities($row['company_locale']);
$config_quote_footer = htmlentities($row['config_quote_footer']);

//Set Currency Format
$currency_format = numfmt_create($company_locale, NumberFormatter::CURRENCY);

//Update status to Viewed only if invoice_status = "Sent"
if ($quote_status == 'Sent') {
    mysqli_query($mysqli, "UPDATE quotes SET quote_status = 'Viewed' WHERE quote_id = $quote_id");
}

//Mark viewed in history
mysqli_query($mysqli, "INSERT INTO history SET history_status = '$quote_status', history_description = 'Quote viewed - $ip - $os - $browser', history_created_at = NOW(), history_quote_id = $quote_id, company_id = $company_id");

if ($quote_status == "Draft" || $quote_status == "Sent" || $quote_status == "Viewed") {
    $client_name_escaped = mysqli_escape_string($mysqli, $row['client_name']);
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Quote Viewed', notification = 'Quote $quote_prefix$quote_number has been viewed by $client_name_escaped - $ip - $os - $browser', notification_timestamp = NOW(), notification_client_id = $client_id, company_id = $company_id");
}

?>

    <div class="card">

        <div class="card-header d-print-none">
            <div class="float-left">
                <?php
                if ($quote_status == "Draft" || $quote_status == "Sent" || $quote_status == "Viewed") {
                    ?>
                    <a class="btn btn-success" href="guest_post.php?accept_quote=<?php echo $quote_id; ?>&company_id=<?php echo $company_id; ?>&url_key=<?php echo $url_key; ?>"><i class="fa fa-fw fa-check"></i> Accept</a>
                    <a class="btn btn-danger" href="guest_post.php?decline_quote=<?php echo $quote_id; ?>&company_id=<?php echo $company_id; ?>&url_key=<?php echo $url_key; ?>"><i class="fa fa-fw fa-times"></i> Decline</a>
                <?php } ?>
            </div>

            <div class="float-right">
                <a class="btn btn-primary" href="#" onclick="window.print();"><i class="fa fa-fw fa-print"></i> Print</a>
                <a class="btn btn-primary" href="#" onclick="pdfMake.createPdf(docDefinition).download('<?php echo "$quote_date-$company_name-QUOTE-$quote_prefix$quote_number.pdf"; ?>');"><i class="fa fa-fw fa-download"></i> Download</a>
            </div>
        </div>
        <div class="card-body">

            <div class="row mb-4">
                <div class="col-sm-2">
                    <img class="img-fluid" src="<?php echo "uploads/settings/$company_id/$company_logo"; ?>">
                </div>
                <div class="col-sm-10">
                    <h3 class="text-right"><strong>Quote</strong><br><small class="text-secondary"><?php echo "$quote_prefix$quote_number"; ?></small></h3>
                </div>
            </div>

            <div class="row mb-4">

                <div class="col-sm">
                    <ul class="list-unstyled">
                        <li><h4><strong><?php echo $company_name; ?></strong></h4></li>
                        <li><?php echo $company_address; ?></li>
                        <li><?php echo "$company_city $company_state $company_zip"; ?></li>
                        <li><?php echo $company_phone; ?></li>
                        <li><?php echo $company_email; ?></li>
                    </ul>

                </div>

                <div class="col-sm">

                    <ul class="list-unstyled text-right">
                        <li><h4><strong><?php echo $client_name; ?></strong></h4></li>
                        <li><?php echo $location_address; ?></li>
                        <li><?php echo "$location_city $location_state $location_zip"; ?></li>
                        <li><?php echo "$contact_phone $contact_extension"; ?></li>
                        <li><?php echo $contact_mobile; ?></li>
                        <li><?php echo $contact_email; ?></li>
                    </ul>

                </div>
            </div>
            <div class="row mb-4">
                <div class="col-sm-8">
                </div>
                <div class="col-sm-4">
                    <table class="table">
                        <tr>
                            <td>Date</td>
                            <td class="text-right"><?php echo $quote_date; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php $sql_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_quote_id = $quote_id ORDER BY item_id ASC"); ?>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Description</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Price</th>
                                    <th class="text-right">Tax</th>
                                    <th class="text-right">Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                $total_tax = $sub_total = 0; // Default 0

                                while ($row = mysqli_fetch_array($sql_items)) {
                                    $item_id = $row['item_id'];
                                    $item_name = htmlentities($row['item_name']);
                                    $item_description = htmlentities($row['item_description']);
                                    $item_quantity = floatval($row['item_quantity']);
                                    $item_price = floatval($row['item_price']);
                                    $item_tax = floatval($row['item_tax']);
                                    $item_total = floatval($row['item_total']);
                                    $total_tax = $item_tax + $total_tax;
                                    $sub_total = $item_price * $item_quantity + $sub_total;

                                    ?>

                                    <tr>
                                        <td><?php echo $item_name; ?></td>
                                        <td><div style="white-space:pre-line"><?php echo $item_description; ?></div></td>
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

            <div class="row mb-4">
                <div class="col-sm-7">
                    <?php if (!empty($quote_note)) { ?>
                        <div class="card">
                            <div class="card-body">
                                <div style="white-space:pre-line"><?php echo $quote_note; ?></div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <div class="col-sm-3 offset-sm-2">
                    <table class="table table-borderless">
                        <tbody>
                        <tr class="border-bottom">
                            <td>Subtotal</td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $sub_total, $quote_currency_code); ?></td>
                        </tr>
                        <?php if ($total_tax > 0) { ?>
                            <tr class="border-bottom">
                                <td>Tax</td>
                                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $total_tax, $quote_currency_code); ?></td>
                            </tr>
                        <?php } ?>
                        <tr class="border-bottom">
                            <td><strong>Total</strong></td>
                            <td class="text-right"><strong><?php echo numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code); ?></strong></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <hr class="mt-5">

            <div style="white-space:pre-line; text-align: center;"><?php echo $config_quote_footer; ?></div>
        </div>
    </div>

    <script src='plugins/pdfmake/pdfmake.min.js'></script>
    <script src='plugins/pdfmake/vfs_fonts.js'></script>
    <script>

        var docDefinition = {
            info: {
                title: <?php echo json_encode(html_entity_decode($company_name) . "- Quote") ?>,
                author: <?php echo json_encode(html_entity_decode($company_name)) ?>
            },

            //watermark: {text: '<?php echo $quote_status; ?>', color: 'lightgrey', opacity: 0.3, bold: true, italics: false},

            content: [
                // Header
                {
                    columns: [
                        <?php if (!empty($company_logo_base64)) { ?>
                        {
                            image: <?php echo json_encode("data:image;base64,$company_logo_base64") ?>,
                            width: 120
                        },
                        <?php } ?>

                        [
                            {
                                text: 'Quote',
                                style: 'invoiceTitle',
                                width: '*'
                            },
                            {
                                text: <?php echo json_encode(html_entity_decode("$quote_prefix$quote_number")) ?>,
                                style: 'invoiceNumber',
                                width: '*'
                            },
                        ],
                    ],
                },
                // Billing Headers
                {
                    columns: [
                        {
                            text: <?php echo json_encode(html_entity_decode($company_name)) ?>,
                            style: 'invoiceBillingTitle'
                        },
                        {
                            text: <?php echo json_encode(html_entity_decode($client_name)) ?>,
                            style: 'invoiceBillingTitleClient'
                        },
                    ]
                },
                // Billing Address
                {
                    columns: [
                        {
                            text: <?php echo json_encode(html_entity_decode("$company_address \n $company_city $company_state $company_zip \n $company_phone \n $company_website")) ?>,
                            style: 'invoiceBillingAddress'
                        },
                        {
                            text: <?php echo json_encode(html_entity_decode("$location_address \n $location_city $location_state $location_zip \n $contact_email \n $contact_phone")) ?>,
                            style: 'invoiceBillingAddressClient'
                        },
                    ]
                },
                //Invoice Dates Table
                {
                    table: {
                        // headers are automatically repeated if the table spans over multiple pages
                        // you can declare how many rows should be treated as headers
                        headerRows: 0,
                        widths: [ '*',80, 80 ],

                        body: [
                            // Total
                            [
                                {
                                    text: '',
                                    rowSpan: 2
                                },
                                {},
                                {},
                            ],
                            [
                                {},
                                {
                                    text: 'Date',
                                    style: 'invoiceDateTitle'
                                },
                                {
                                    text: <?php echo json_encode($quote_date) ?>,
                                    style: 'invoiceDateValue'
                                },
                            ],
                        ]
                    }, // table
                    layout: 'lightHorizontalLines'
                },
                // Line breaks
                '\n\n',
                // Items
                {
                    table: {
                        // headers are automatically repeated if the table spans over multiple pages
                        // you can declare how many rows should be treated as headers
                        headerRows: 1,
                        widths: [ '*', 40, 'auto', 'auto', 80 ],

                        body: [
                            // Table Header
                            [
                                {
                                    text: 'Product',
                                    style: [ 'itemsHeader', 'left']
                                },
                                {
                                    text: 'Qty',
                                    style: [ 'itemsHeader', 'center']
                                },
                                {
                                    text: 'Price',
                                    style: [ 'itemsHeader', 'right']
                                },
                                {
                                    text: 'Tax',
                                    style: [ 'itemsHeader', 'right']
                                },
                                {
                                    text: 'Total',
                                    style: [ 'itemsHeader', 'right']
                                }
                            ],
                            // Items
                            <?php
                            $total_tax = 0;
                            $sub_total = 0;

                            $sql_invoice_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_quote_id = $quote_id ORDER BY item_id ASC");

                            while ($row = mysqli_fetch_array($sql_invoice_items)) {
                            $item_name = $row['item_name'];
                            $item_description = $row['item_description'];
                            $item_quantity = $row['item_quantity'];
                            $item_price = $row['item_price'];
                            $item_subtotal = $row['item_price'];
                            $item_tax = $row['item_tax'];
                            $item_total = $row['item_total'];
                            $tax_id = $row['item_tax_id'];
                            $total_tax = $item_tax + $total_tax;
                            $sub_total = $item_price * $item_quantity + $sub_total;
                            ?>

                            // Item
                            [
                                [
                                    {
                                        text: <?php echo json_encode($item_name) ?>,
                                        style: 'itemTitle'
                                    },
                                    {
                                        text: <?php echo json_encode($item_description) ?>,
                                        style: 'itemDescription'
                                    }
                                ],
                                {
                                    text: <?php echo $item_quantity ?>,
                                    style: 'itemQty'
                                },
                                {
                                    text: <?php echo json_encode(numfmt_format_currency($currency_format, $item_price, $quote_currency_code)) ?>,
                                    style: 'itemNumber'
                                },
                                {
                                    text: <?php echo json_encode(numfmt_format_currency($currency_format, $item_tax, $quote_currency_code)) ?>,
                                    style: 'itemNumber'
                                },
                                {
                                    text: <?php echo json_encode(numfmt_format_currency($currency_format, $item_total, $quote_currency_code)) ?>,
                                    style: 'itemNumber'
                                }
                            ],

                            <?php
                            }
                            ?>
                            // END Items
                        ]
                    }, // table
                    layout: 'lightHorizontalLines'
                },
                // TOTAL
                {
                    table: {
                        // headers are automatically repeated if the table spans over multiple pages
                        // you can declare how many rows should be treated as headers
                        headerRows: 0,
                        widths: [ '*','auto', 80 ],

                        body: [
                            // Total
                            [
                                {
                                    text: 'Notes',
                                    style:'notesTitle'
                                },
                                {},
                                {}
                            ],
                            [
                                {
                                    rowSpan: 3,
                                    text: <?php echo json_encode(html_entity_decode($quote_note)) ?>,
                                    style: 'notesText'
                                },
                                {
                                    text: 'Subtotal',
                                    style: 'itemsFooterSubTitle'
                                },
                                {
                                    text: <?php echo json_encode(numfmt_format_currency($currency_format, $sub_total, $quote_currency_code)) ?>,
                                    style: 'itemsFooterSubValue'
                                }
                            ],
                            [
                                {},
                                {
                                    text: 'Tax',
                                    style: 'itemsFooterSubTitle'
                                },
                                {
                                    text: <?php echo json_encode(numfmt_format_currency($currency_format, $total_tax, $quote_currency_code)) ?>,
                                    style: 'itemsFooterSubValue'
                                }
                            ],
                            [
                                {},
                                {
                                    text: 'Total',
                                    style: 'itemsFooterSubTitle'
                                },
                                {
                                    text: <?php echo json_encode(numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code)) ?>,
                                    style: 'itemsFooterSubValue'
                                }
                            ],
                        ]
                    }, // table
                    layout: 'lightHorizontalLines'
                },
                // TERMS / FOOTER
                {
                    text: <?php echo json_encode("$config_quote_footer"); ?>,
                    style: 'documentFooterCenter'
                }
            ], //End Content,
            styles: {
                // Document Footer
                documentFooterCenter: {
                    fontSize: 9,
                    margin: [10,50,10,10],
                    alignment: 'center'
                },
                // Invoice Title
                invoiceTitle: {
                    fontSize: 18,
                    bold: true,
                    alignment: 'right',
                    margin: [0,0,0,3]
                },
                // Invoice Number
                invoiceNumber: {
                    fontSize: 14,
                    alignment: 'right'
                },
                // Billing Headers
                invoiceBillingTitle: {
                    fontSize: 14,
                    bold: true,
                    alignment: 'left',
                    margin: [0,20,0,5]
                },
                invoiceBillingTitleClient: {
                    fontSize: 14,
                    bold: true,
                    alignment: 'right',
                    margin: [0,20,0,5]
                },
                // Billing Details
                invoiceBillingAddress: {
                    fontSize: 10,
                    lineHeight: 1.2
                },
                invoiceBillingAddressClient: {
                    fontSize: 10,
                    lineHeight: 1.2,
                    alignment: 'right',
                    margin: [0,0,0,30]
                },
                // Invoice Dates
                invoiceDateTitle: {
                    fontSize: 10,
                    alignment: 'left',
                    margin: [0,5,0,5]
                },
                invoiceDateValue: {
                    fontSize: 10,
                    alignment: 'right',
                    margin: [0,5,0,5]
                },
                // Items Header
                itemsHeader: {
                    fontSize: 10,
                    margin: [0,5,0,5],
                    bold: true,
                    alignment: 'right'
                },
                // Item Title
                itemTitle: {
                    fontSize: 10,
                    bold: true,
                    margin: [0,5,0,3]
                },
                itemDescription: {
                    italics: true,
                    fontSize: 9,
                    lineHeight: 1.1,
                    margin: [0,3,0,5]
                },
                itemQty: {
                    fontSize: 10,
                    margin: [0,5,0,5],
                    alignment: 'center'
                },
                itemNumber: {
                    fontSize: 10,
                    margin: [0,5,0,5],
                    alignment: 'right'
                },
                itemTotal: {
                    fontSize: 10,
                    margin: [0,5,0,5],
                    bold: true,
                    alignment: 'right'
                },
                // Items Footer (Subtotal, Total, Tax, etc)
                itemsFooterSubTitle: {
                    fontSize: 10,
                    margin: [0,5,0,5],
                    alignment: 'right'
                },
                itemsFooterSubValue: {
                    fontSize: 10,
                    margin: [0,5,0,5],
                    bold: false,
                    alignment: 'right'
                },
                itemsFooterTotalTitle: {
                    fontSize: 10,
                    margin: [0,5,0,5],
                    bold: true,
                    alignment: 'right'
                },
                itemsFooterTotalValue: {
                    fontSize: 10,
                    margin: [0,5,0,5],
                    bold: true,
                    alignment: 'right'
                },
                notesTitle: {
                    fontSize: 10,
                    bold: true,
                    margin: [0,5,0,5]
                },
                notesText: {
                    fontSize: 9,
                    margin: [0,5,50,5]
                },
                left: {
                    alignment: 'left'
                },
                center: {
                    alignment: 'center'
                },
            },
            defaultStyle: {
                columnGap: 20,
            }
        }
    </script>


<?php
require_once("guest_footer.php");
