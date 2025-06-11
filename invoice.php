<?php

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
} else {
    require_once "includes/inc_all.php";
}

// Perms
enforceUserPermission('module_sales');

if (isset($_GET['invoice_id'])) {

    $invoice_id = intval($_GET['invoice_id']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE invoice_id = $invoice_id
        $access_permission_query
        LIMIT 1"
    );

    if (mysqli_num_rows($sql) == 0) {
        if (isset($_GET['client_id'])) {
            $backlink_append = "?client_id=$client_id";
        } else {
            $backlink_append = '';
        }
        echo "<h1 class='text-secondary pt-5' style='text-align: center'>There is no Invoice here<br><small><a href='invoices.php$backlink_append'><i class='fas fa-arrow-left mr-2'></i>Back to Invoices</a></small></h1>";
        require_once "includes/footer.php";

        exit();
    }

    $row = mysqli_fetch_array($sql);
    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_scope = nullable_htmlentities($row['invoice_scope']);
    $invoice_status = nullable_htmlentities($row['invoice_status']);
    $invoice_date = nullable_htmlentities($row['invoice_date']);
    $invoice_due = nullable_htmlentities($row['invoice_due']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_discount = floatval($row['invoice_discount_amount']);
    $invoice_currency_code = nullable_htmlentities($row['invoice_currency_code']);
    $invoice_note = nullable_htmlentities($row['invoice_note']);
    $invoice_url_key = nullable_htmlentities($row['invoice_url_key']);
    $invoice_created_at = nullable_htmlentities($row['invoice_created_at']);
    $category_id = intval($row['invoice_category_id']);
    $client_id = intval($row['client_id']);
    $client_name = nullable_htmlentities($row['client_name']);
    $location_address = nullable_htmlentities($row['location_address']);
    $location_city = nullable_htmlentities($row['location_city']);
    $location_state = nullable_htmlentities($row['location_state']);
    $location_zip = nullable_htmlentities($row['location_zip']);
    $location_country = nullable_htmlentities($row['location_country']);
    $contact_email = nullable_htmlentities($row['contact_email']);
    $contact_phone_country_code = nullable_htmlentities($row['contact_phone_country_code']);
    $contact_phone = nullable_htmlentities(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
    $contact_extension = nullable_htmlentities($row['contact_extension']);
    $contact_mobile_country_code = nullable_htmlentities($row['contact_mobile_country_code']);
    $contact_mobile = nullable_htmlentities(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
    $client_website = nullable_htmlentities($row['client_website']);
    $client_currency_code = nullable_htmlentities($row['client_currency_code']);
    $client_net_terms = intval($row['client_net_terms']);
    if ($client_net_terms == 0) {
        $client_net_terms = $config_default_net_terms;
    }

    // Override Tab Title // No Sanitizing needed as this var will opnly be used in the tab title
    $tab_title = $row['client_name'];
    $page_title = "{$row['invoice_prefix']}{$row['invoice_number']}";

    $sql = mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_array($sql);
    $company_id = intval($row['company_id']);
    $company_name = nullable_htmlentities($row['company_name']);
    $company_country = nullable_htmlentities($row['company_country']);
    $company_address = nullable_htmlentities($row['company_address']);
    $company_city = nullable_htmlentities($row['company_city']);
    $company_state = nullable_htmlentities($row['company_state']);
    $company_zip = nullable_htmlentities($row['company_zip']);
    $company_phone_country_code = nullable_htmlentities($row['company_phone_country_code']);
    $company_phone = nullable_htmlentities(formatPhoneNumber($row['company_phone'], $company_phone_country_code));
    $company_email = nullable_htmlentities($row['company_email']);
    $company_website = nullable_htmlentities($row['company_website']);
    $company_logo = nullable_htmlentities($row['company_logo']);
    if (!empty($company_logo)) {
        $company_logo_base64 = base64_encode(file_get_contents("uploads/settings/$company_logo"));
    }
    $sql_history = mysqli_query($mysqli, "SELECT * FROM history WHERE history_invoice_id = $invoice_id ORDER BY history_id DESC");

    $sql_payments = mysqli_query($mysqli, "SELECT * FROM payments, accounts WHERE payment_account_id = account_id AND payment_invoice_id = $invoice_id ORDER BY payments.payment_id DESC");

    $sql_tickets = mysqli_query($mysqli, "
        SELECT
            tickets.*,
            SEC_TO_TIME(SUM(TIME_TO_SEC(STR_TO_DATE(ticket_reply_time_worked, '%H:%i:%s')))) AS 'total_time_worked'
        FROM
            tickets
        LEFT JOIN
            ticket_replies ON tickets.ticket_id = ticket_replies.ticket_reply_ticket_id
        WHERE
            ticket_invoice_id = $invoice_id
        GROUP BY
            tickets.ticket_id
        ORDER BY
            ticket_id DESC
    ");

    //Get billable, and unbilled tickets to add to invoice
    $sql_tickets_billable = mysqli_query(
        $mysqli, "
        SELECT
            *
        FROM
            tickets
        WHERE
            ticket_client_id = $client_id
        AND
            ticket_billable = 1
        AND
            ticket_invoice_id = 0
        AND
            ticket_status = 5;
    ");


    //Add up all the payments for the invoice and get the total amount paid to the invoice
    $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments WHERE payment_invoice_id = $invoice_id");
    $row = mysqli_fetch_array($sql_amount_paid);
    $amount_paid = floatval($row['amount_paid']);

    $balance = $invoice_amount - $amount_paid;

    //check to see if overdue
    if ($invoice_status !== "Paid" && $invoice_status !== "Draft" && $invoice_status !== "Cancelled" && $invoice_status !== "Non-Billable") {
        $unixtime_invoice_due = strtotime($invoice_due) + 86400;
        if ($unixtime_invoice_due < time()) {
            $invoice_overdue = "Overdue";
        }
    }

    //Set Badge color based off of invoice status
    $invoice_badge_color = getInvoiceBadgeColor($invoice_status);

    //Product autocomplete
    $products_sql = mysqli_query($mysqli, "SELECT product_name AS label, product_description AS description, product_price AS price, product_tax_id AS tax FROM products WHERE product_archived_at IS NULL");

    if (mysqli_num_rows($products_sql) > 0) {
        while ($row = mysqli_fetch_array($products_sql)) {
            $products[] = $row;
        }
        $json_products = json_encode($products);
    }

    // Payment with saved card (auto-pay)
    if ($config_stripe_enable) {
        $stripe_client_details = mysqli_fetch_array(mysqli_query($mysqli, "SELECT * FROM client_stripe WHERE client_id = $client_id LIMIT 1"));
        if ($stripe_client_details) {
            $stripe_id = sanitizeInput($stripe_client_details['stripe_id']);
            $stripe_pm = sanitizeInput($stripe_client_details['stripe_pm']);
        }
    }



    ?>

    <ol class="breadcrumb d-print-none">
        <?php if (isset($_GET['client_id'])) { ?>
        <li class="breadcrumb-item">
            <a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="invoices.php?client_id=<?php echo $client_id; ?>">Invoices</a>
        </li>
        <?php } else { ?>
        <li class="breadcrumb-item">
            <a href="invoices.php">Invoices</a>
        </li>
        <li class="breadcrumb-item">
            <a href="invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
        </li>
        <?php } ?>
        <li class="breadcrumb-item active"><?php echo "$invoice_prefix$invoice_number"; ?></li>
        <?php if (isset($invoice_overdue)) { ?>
            <span class="p-2 ml-2 badge badge-danger"><?php echo $invoice_overdue; ?></span>
        <?php } ?>
    </ol>

    <div class="card">

            <div class="card-header d-print-none">

                <div class="row">

                    <div class="col-8">
                        <?php if (lookupUserPermission("module_sales") >= 2) { ?>

                            <?php if ($invoice_status == 'Draft') { ?>
                                <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-fw fa-paper-plane mr-2"></i>Send
                                </button>
                                <div class="dropdown-menu">
                                    <?php if (!empty($config_smtp_host) && !empty($contact_email)) { ?>
                                        <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">
                                            <i class="fas fa-fw fa-paper-plane mr-2"></i>Send Email
                                        </a>
                                        <div class="dropdown-divider"></div>
                                    <?php } ?>
                                    <a class="dropdown-item" href="post.php?mark_invoice_sent=<?php echo $invoice_id; ?>">
                                        <i class="fas fa-fw fa-check mr-2"></i>Mark Sent
                                    </a>
                                </div>
                            <?php } ?>

                            <?php if ($invoice_status !== 'Paid' && $invoice_status !== 'Cancelled' && $invoice_status !== 'Draft' && $invoice_status !== 'Non-Billable' && $invoice_amount != 0) { ?>
                                <a class="btn btn-success" href="#"
                                    data-toggle = "ajax-modal"
                                    data-ajax-url = "ajax/ajax_invoice_pay.php"
                                    data-ajax-id = "<?php echo $invoice_id; ?>"
                                    >
                                    <i class="fa fa-fw fa-credit-card mr-2"></i>Add Payment
                                </a>
                                <?php if ($invoice_status !== 'Partial' && $config_stripe_enable && $stripe_id && $stripe_pm) { ?>
                                    <a class="btn btn-primary confirm-link" href="post.php?add_payment_stripe&invoice_id=<?php echo $invoice_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>">
                                        <i class="fa fa-fw fa-credit-card mr-2"></i>Pay via saved card
                                    </a>
                                <?php } ?>
                            <?php } ?>

                            <?php if (($invoice_status == 'Sent' || $invoice_status == 'Viewed') && $invoice_amount == 0 && $invoice_status !== 'Non-Billable') { ?>
                                <a class="btn btn-dark" href="post.php?mark_invoice_non-billable=<?php echo $invoice_id; ?>">
                                    Mark Non-Billable
                                </a>
                            <?php } ?>

                        <?php } ?>
                    </div>

                    <div class="col-4">

                        <div class="dropdown dropleft text-center float-right">
                            <button class="btn btn-secondary" type="button" data-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="#"
                                    data-toggle = "ajax-modal"
                                    data-ajax-url = "ajax/ajax_invoice_edit.php"
                                    data-ajax-id = "<?php echo $invoice_id; ?>"
                                    >
                                    <i class="fa fa-fw fa-edit text-secondary mr-2"></i>Edit
                                </a>
                                <a class="dropdown-item" href="#"
                                    data-toggle = "ajax-modal"
                                    data-ajax-url = "ajax/ajax_invoice_copy.php"
                                    data-ajax-id = "<?php echo $invoice_id; ?>"
                                    >
                                    <i class="fa fa-fw fa-copy text-secondary mr-2"></i>Copy
                                </a>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addInvoiceRecurringModal<?php echo $invoice_id; ?>">
                                    <i class="fa fa-fw fa-sync-alt text-secondary mr-2"></i>Recurring
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" onclick="window.print();">
                                    <i class="fa fa-fw fa-print text-secondary mr-2"></i>Print
                                </a>
                                <a class="dropdown-item" href="#" onclick="pdfMake.createPdf(docDefinition).download('<?php echo strtoAZaz09(html_entity_decode("$invoice_date-$company_name-$client_name-Invoice-$invoice_prefix$invoice_number")); ?>');">
                                    <i class="fa fa-fw fa-download text-secondary mr-2"></i>Download PDF
                                </a>
                                <?php if (!empty($config_smtp_host) && !empty($contact_email)) { ?>
                                    <a class="dropdown-item" href="post.php?email_invoice=<?php echo $invoice_id; ?>">
                                        <i class="fa fa-fw fa-paper-plane text-secondary mr-2"></i>Send Email
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item" target="_blank" href="guest/guest_view_invoice.php?invoice_id=<?php echo "$invoice_id&url_key=$invoice_url_key"; ?>">
                                    <i class="fa fa-fw fa-link text-secondary mr-2"></i>Guest URL
                                </a>
                                <?php if ($invoice_status !== 'Cancelled' && $invoice_status !== 'Paid' && $invoice_status !== 'Non-Billable') { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?cancel_invoice=<?php echo $invoice_id; ?>">
                                        <i class="fa fa-fw fa-times mr-2"></i>Cancel
                                    </a>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_invoice=<?php echo $invoice_id; ?>">
                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                </a>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        <div class="card-body">

            <div class="row mb-4">
                <div class="col-sm-2">
                    <img class="img-fluid" src="<?php echo "uploads/settings/$company_logo"; ?>" alt="Company logo">
                </div>
                <div class="col-sm-10">
                    <div class="ribbon-wrapper">
                        <div class="ribbon bg-<?php echo $invoice_badge_color; ?>">
                            <?php echo "$invoice_status"; ?>
                        </div>
                    </div>
                    <h3 class="text-right mt-5"><strong>Invoice</strong><br><small class="text-secondary"><?php echo "$invoice_prefix$invoice_number"; ?></small></h3>
                </div>

            </div>
            <div class="row mb-4">
                <div class="col">
                    <ul class="list-unstyled">
                        <li><h4><strong><?php echo $company_name; ?></strong></h4></li>
                        <li><?php echo $company_address; ?></li>
                        <li><?php echo "$company_city $company_state $company_zip"; ?></li>
                        <li><small><?php echo $company_country; ?></small></li>
                        <li><?php echo $company_phone; ?></li>
                        <li><?php echo $company_email; ?></li>
                        <li><?php echo $company_website; ?></li>
                    </ul>
                </div>
                <div class="col">
                    <ul class="list-unstyled text-right">
                        <li><h4><strong><?php echo $client_name; ?></strong></h4></li>
                        <li><?php echo $location_address; ?></li>
                        <li><?php echo "$location_city $location_state $location_zip"; ?></li>
                        <li><small><?php echo $location_country; ?></small></li>
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
                            <td class="text-right"><?php echo $invoice_date; ?></td>
                        </tr>
                        <tr class="text-bold">
                            <td>Due</td>
                            <td class="text-right"><?php echo $invoice_due; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php $sql_invoice_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_order ASC"); ?>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table" id="items">
                                <thead>
                                <tr>
                                    <th class="d-print-none"></th>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-right">Price</th>
                                    <th class="text-right">Tax</th>
                                    <th class="text-right">Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                $total_tax = 0.00;
                                $sub_total = 0.00;

                                while ($row = mysqli_fetch_array($sql_invoice_items)) {
                                    $item_id = intval($row['item_id']);
                                    $item_name = nullable_htmlentities($row['item_name']);
                                    $item_description = nullable_htmlentities($row['item_description']);
                                    $item_quantity = floatval($row['item_quantity']);
                                    $item_price = floatval($row['item_price']);
                                    $item_tax = floatval($row['item_tax']);
                                    $item_total = floatval($row['item_total']);
                                    $item_created_at = nullable_htmlentities($row['item_created_at']);
                                    $tax_id = intval($row['item_tax_id']);
                                    $total_tax = $item_tax + $total_tax;
                                    $sub_total = $item_price * $item_quantity + $sub_total;
                                    ?>
                                    <tr data-item-id="<?php echo $item_id; ?>">
                                        <td class="d-print-none">
                                            <?php if ($invoice_status !== "Paid" && $invoice_status !== "Cancelled") { ?>
                                                
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-link drag-handle">
                                                        <i class="fas fa-bars text-muted"></i>
                                                    </button>
                                            
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-light" type="button" data-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu">
                                                            <a class="dropdown-item" href="#"
                                                                data-toggle="ajax-modal"
                                                                data-ajax-url="ajax/ajax_item_edit.php"
                                                                data-ajax-id="<?php echo $item_id; ?>"
                                                                >
                                                                <i class="fa fa-fw fa-edit mr-2"></i>Edit
                                                            </a>
                                                            <div class="dropdown-divider"></div>
                                                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_invoice_item=<?php echo $item_id; ?>"><i class="fa fa-fw fa-trash mr-2"></i>Delete</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            
                                            <?php } ?>
                                        </td>
                                        <td><?php echo $item_name; ?></td>
                                        <td><?php echo nl2br($item_description); ?></td>
                                        <td class="text-center"><?php echo number_format($item_quantity, 2); ?></td>
                                        <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_price, $invoice_currency_code); ?></td>
                                        <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_tax, $invoice_currency_code); ?></td>
                                        <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_total, $invoice_currency_code); ?></td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                <tr class="d-print-none" <?php if ($invoice_status == "Paid" || $invoice_status == "Cancelled" || lookupUserPermission("module_sales") <= 1) { echo "hidden"; } ?>>
                                    <form action="post.php" method="post" autocomplete="off">
                                        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                                        <input type="hidden" name="item_order" value="<?php echo mysqli_num_rows($sql_invoice_items) + 1; ?>">
                                        <td></td>
                                        <td>
                                            <input type="text" class="form-control" id="name" name="name" placeholder="Item" required>
                                        </td>
                                        <td>
                                            <textarea class="form-control" rows="2" id="desc" name="description" placeholder="Enter a Description"></textarea>
                                        </td>
                                        <td>
                                            <input type="text" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" class="form-control" style="text-align: center;" id="qty" name="qty" placeholder="Quantity">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" style="text-align: right;" id="price" name="price" placeholder="Price (<?php echo $invoice_currency_code; ?>)">
                                        </td>
                                        <td>
                                            <select class="form-control select2" name="tax_id" id="tax" required>
                                                <option value="0">No Tax</option>
                                                <?php
                                                $taxes_sql = mysqli_query($mysqli, "SELECT * FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");
                                                while ($row = mysqli_fetch_array($taxes_sql)) {
                                                    $tax_id = intval($row['tax_id']);
                                                    $tax_name = nullable_htmlentities($row['tax_name']);
                                                    $tax_percent = floatval($row['tax_percent']);
                                                    ?>
                                                    <option value="<?php echo $tax_id; ?>"><?php echo "$tax_name $tax_percent%"; ?></option>
                                                    <?php
                                                }
                                                ?>
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-light text-success" type="submit" name="add_invoice_item">
                                                <i class="fa fa-check"></i>
                                            </button>
                                        </td>
                                    </form>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-sm-7">
                    <div class="card">
                        <div class="card-header text-bold">
                            Notes
                            <div class="card-tools d-print-none">
                                <a href="#" class="btn btn-light btn-tool" data-toggle="modal" data-target="#invoiceNoteModal">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php echo nl2br($invoice_note); ?>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3 offset-sm-2">
                    <table class="table table-borderless">
                        <tbody>

                        <tr class="border-bottom">
                            <td>Subtotal</td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $sub_total, $invoice_currency_code); ?></td>
                        </tr>
                        <?php
                        if ($invoice_discount > 0) {
                            ?>
                            <tr class="border-bottom">
                                <td>Discount</td>
                                <td class="text-right">-<?php echo numfmt_format_currency($currency_format, $invoice_discount, $invoice_currency_code); ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                        <?php if ($total_tax > 0) { ?>
                            <tr class="border-bottom">
                                <td>Tax</td>
                                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $total_tax, $invoice_currency_code); ?></td>
                            </tr>
                        <?php } ?>
                        <tr class="border-bottom">
                            <td>Total</td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code); ?></td>
                        </tr>
                        <?php
                        if ($amount_paid > 0) { ?>
                            <tr class="border-bottom">
                                <td><div class="text-success">Paid</div></td>
                                <td class="text-right text-success"><?php echo numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code); ?></td>
                            </tr>
                        <?php } ?>

                        <tr class="border-bottom">
                            <td><strong>Balance</strong></td>
                            <td class="text-right"><strong><?php echo numfmt_format_currency($currency_format, $balance, $invoice_currency_code); ?></strong></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <hr class="d-none d-print-block mt-5">
            <div class="d-none d-print-block text-center"><?php echo nl2br(nullable_htmlentities($config_invoice_footer)); ?></div>
        </div>
    </div>
    <div class="row d-print-none mb-3">
        <div class="col-sm">
            <div class="card">
                <div class="card-header text-bold">
                    <i class="fa fa-history mr-2"></i>History
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-tool" data-card-widget="remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql_history)) {
                            $history_created_at = $row['history_created_at'];
                            $history_status = nullable_htmlentities($row['history_status']);
                            $history_description = nullable_htmlentities($row['history_description']);

                            ?>
                            <tr>
                                <td><?php echo $history_created_at; ?></td>
                                <td><?php echo $history_status; ?></td>
                                <td><?php echo $history_description; ?></td>
                            </tr>
                            <?php
                        }
                        ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-sm d-print-none">
            <div class="card">
                <div class="card-header text-bold">
                    <i class="fa fa-credit-card mr-2"></i>Payments
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-tool" data-card-widget="remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="<?php if (mysqli_num_rows($sql_payments) == 0) { echo "d-none"; } ?>">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-right">Amount</th>
                                    <th>Reference</th>
                                    <th>Account</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_payments)) {
                                $payment_id = intval($row['payment_id']);
                                $payment_date = nullable_htmlentities($row['payment_date']);
                                $payment_amount = floatval($row['payment_amount']);
                                $payment_currency_code = nullable_htmlentities($row['payment_currency_code']);
                                $payment_reference = nullable_htmlentities($row['payment_reference']);
                                $account_name = nullable_htmlentities($row['account_name']);

                                ?>
                                <tr>
                                    <td><?php echo $payment_date; ?></td>
                                    <td class="text-right"><?php echo numfmt_format_currency($currency_format, $payment_amount, $payment_currency_code); ?></td>
                                    <td><?php echo $payment_reference; ?></td>
                                    <td><?php echo $account_name; ?></td>
                                    <td class="text-center"><a class="btn btn-light text-danger confirm-link" href="post.php?delete_payment=<?php echo $payment_id; ?>"><i class="fa fa-times"></i></a></td>
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
        <div class="col-sm d-print-none">
            <div class="card">
                <div class="card-header text-bold">
                    <i class="fa fa-life-ring mr-2"></i>Tickets
                    <div class="card-tools">
                        <?php if (mysqli_num_rows($sql_tickets_billable) > 0) { ?>
                        <a class="btn btn-tool" href="#" data-toggle="modal" data-target="#addTicketModal">
                            <i class="fas fa-plus"></i>
                        </a>
                        <?php } ?>


                        <a class="btn btn-tool" href="tickets.php?client_id=<?php echo $client_id; ?>">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>

                        </button>
                        <button type="button" class="btn btn-tool" data-card-widget="remove">
                            <i class="fas fa-times"></i>

                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="<?php if (mysqli_num_rows($sql_tickets) == 0) { echo "d-none"; } ?>">
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th class="text-right">Time Worked</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_tickets)) {
                                $ticket_id = intval($row['ticket_id']);
                                $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
                                $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                                $ticket_total_time_worked = floatval($row['total_time_worked']);

                                ?>
                                <tr>
                                    <td><?php echo $ticket_created_at; ?></td>
                                    <td><?php echo $ticket_subject; ?></td>
                                    <td class="text-right"><?php echo $ticket_total_time_worked; ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    <?php
    include_once "modals/invoice_add_ticket_modal.php";
    include_once "modals/invoice_recurring_add_modal.php";
    include_once "modals/invoice_note_modal.php";

}

require_once "includes/footer.php";

?>

<!-- JSON Autocomplete / type ahead -->
<link rel="stylesheet" href="plugins/jquery-ui/jquery-ui.min.css">
<script src="plugins/jquery-ui/jquery-ui.min.js"></script>
<script>
    $(function() {
        var availableProducts = <?php echo $json_products ?? '""'?>;

        $("#name").autocomplete({
            source: availableProducts,
            select: function (event, ui) {
                $("#name").val(ui.item.label); // Product name field - this seemingly has to referenced as label
                $("#desc").val(ui.item.description); // Product description field
                $("#qty").val(1); // Product quantity field automatically make it a 1
                $("#price").val(ui.item.price); // Product price field
                $("#tax").val(ui.item.tax); // Product tax field
                return false;
            }
        });
    });
</script>

<script src='plugins/pdfmake/pdfmake.min.js'></script>
<script src='plugins/pdfmake/vfs_fonts.js'></script>
<script>

    var docDefinition = {
        info: {
            title: <?php echo json_encode(html_entity_decode($company_name) . "- Invoice") ?>,
            author: <?php echo json_encode(html_entity_decode($company_name)) ?>
        },

        //watermark: {text: '<?php echo $invoice_status; ?>', color: 'lightgrey', opacity: 0.3, bold: true, italics: false},

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
                            text: 'Invoice',
                            style: 'invoiceTitle',
                            width: '*'
                        },
                        {
                            text: <?php echo json_encode(html_entity_decode("$invoice_prefix$invoice_number")) ?>,
                            style: 'invoiceNumber',
                            width: '*'
                        },
                        <?php if ($invoice_status == "Paid") { ?>
                        {
                            text: 'PAID',
                            style: 'invoicePaid',
                            width: '*'
                        },
                        <?php } ?>
                    ],
                ],
            },
            // Billing Headers
            {
                columns: [
                    {
                        text: <?php echo json_encode(html_entity_decode($company_name)) ?>,
                        style: 'invoiceBillingTitle',
                    },
                    {
                        text: <?php echo json_encode(html_entity_decode($client_name)) ?>,
                        style: 'invoiceBillingTitleClient',
                    },
                ]
            },
            // Billing Address
            {
                columns: [
                    {
                        text: <?php echo json_encode(html_entity_decode("$company_address \n $company_city $company_state $company_zip \n $company_country \n $company_phone \n $company_website")) ?>,
                        style: 'invoiceBillingAddress'
                    },
                    {
                        text: <?php echo json_encode(html_entity_decode("$location_address \n $location_city $location_state $location_zip \n $location_country \n $contact_email \n $contact_phone")) ?>,
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
                                rowSpan: 3
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
                                text: <?php echo json_encode($invoice_date) ?>,
                                style: 'invoiceDateValue'
                            },
                        ],
                        [
                            {},
                            {
                                text: 'Due',
                                style: 'invoiceDueDateTitle'
                            },
                            {
                                text: <?php echo json_encode($invoice_due) ?>,
                                style: 'invoiceDueDateValue'
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
                        $total_tax = 0.00;
                        $sub_total = 0.00;

                        $sql_invoice_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_order ASC");

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
                                text: <?php echo json_encode($item_quantity) ?>,
                                style: 'itemQty'
                            },
                            {
                                text: <?php echo json_encode(numfmt_format_currency($currency_format, $item_price, $invoice_currency_code)) ?>,
                                style: 'itemNumber'
                            },
                            {
                                text: <?php echo json_encode(numfmt_format_currency($currency_format, $item_tax, $invoice_currency_code)) ?>,
                                style: 'itemNumber'
                            },
                            {
                                text: <?php echo json_encode(numfmt_format_currency($currency_format, $item_total, $invoice_currency_code)) ?>,
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
                                style: 'notesTitle'
                            },
                            {},
                            {}
                        ],
                        [
                            {
                                rowSpan: '*',
                                text: <?php echo json_encode(html_entity_decode($invoice_note)) ?>,
                                style: 'notesText'
                            },
                            {
                                text: 'Subtotal',
                                style: 'itemsFooterSubTitle'
                            },
                            {
                                text: <?php echo json_encode(numfmt_format_currency($currency_format, $sub_total, $invoice_currency_code)) ?>,
                                style: 'itemsFooterSubValue'
                            }
                        ],
                        <?php if ($invoice_discount > 0) { ?>
                        [
                            {},
                            {
                                text: 'Discount',
                                style: 'itemsFooterSubTitle'
                            },
                            {
                                text: <?php echo json_encode(numfmt_format_currency($currency_format, -$invoice_discount, $invoice_currency_code)) ?>,
                                style: 'itemsFooterSubValue'
                            }
                        ],
                        <?php } ?>
                        <?php if ($total_tax > 0) { ?>
                        [
                            {},
                            {
                                text: 'Tax',
                                style: 'itemsFooterSubTitle'
                            },
                            {
                                text: <?php echo json_encode(numfmt_format_currency($currency_format, $total_tax, $invoice_currency_code)) ?>,
                                style: 'itemsFooterSubValue'
                            }
                        ],
                        <?php } ?>
                        [
                            {},
                            {
                                text: 'Total',
                                style: 'itemsFooterSubTitle'
                            },
                            {
                                text: <?php echo json_encode(numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code)) ?>,
                                style: 'itemsFooterSubValue'
                            }
                        ],
                        <?php if ($amount_paid > 0) { ?>
                        [
                            {},
                            {
                                text: 'Paid',
                                style: 'itemsFooterSubTitle'
                            },
                            {
                                text: <?php echo json_encode(numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code)) ?>,
                                style: 'itemsFooterSubValue'
                            }
                        ],
                        <?php } ?>
                        [
                            {},
                            {
                                text: 'Balance',
                                style: 'itemsFooterTotalTitle'
                            },
                            {
                                text: <?php echo json_encode(numfmt_format_currency($currency_format, $balance, $invoice_currency_code)) ?>,

                                style: 'itemsFooterTotalValue'
                            }
                        ],
                    ]
                }, // table
                layout: 'lightHorizontalLines'
            },
            // TERMS / FOOTER
            {
                text: <?php echo json_encode($config_invoice_footer) ?>,
                style: 'documentFooterCenter'
            }
        ], //End Content,
        styles: {
            // Document Footer
            documentFooterCenter: {
                fontSize: 9,
                margin: [10,50,10,10],
                alignment: 'center',
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
            // Invoice Paid
            invoicePaid: {
                fontSize: 13,
                bold: true,
                margin: [0,5,0,0],
                alignment: 'right',
                color: 'green'
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
            // Invoice Date
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
            // Invoice Due Date
            invoiceDueDateTitle: {
                fontSize: 10,
                bold: true,
                alignment: 'left',
                margin: [0,5,0,5]
            },
            invoiceDueDateValue: {
                fontSize: 10,
                bold: true,
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
                alignment:'right'
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
            columnGap: 20
        }
    }
</script>

<script src="plugins/SortableJS/Sortable.min.js"></script>
<script>
new Sortable(document.querySelector('table#items tbody'), {
    handle: '.drag-handle',
    animation: 150,
    onEnd: function (evt) {
        const rows = document.querySelectorAll('table#items tbody tr');
        const positions = Array.from(rows).map((row, index) => ({
            id: row.dataset.itemId,
            order: index
        }));

        $.post('ajax.php', {
            update_invoice_items_order: true,
            invoice_id: <?php echo $invoice_id; ?>,
            positions: positions
        });
    }
});
</script>
