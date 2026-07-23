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
        LEFT JOIN contacts ON client_id = contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON client_id = location_client_id AND location_primary = 1
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
        require_once "../includes/footer.php";

        exit();
    }

    $row = mysqli_fetch_assoc($sql);
    $invoice_id = intval($row['invoice_id']);
    $invoice_prefix = escapeHtml($row['invoice_prefix']);
    $invoice_number = intval($row['invoice_number']);
    $invoice_scope = escapeHtml($row['invoice_scope']);
    $invoice_status = escapeHtml($row['invoice_status']);
    $invoice_date = escapeHtml($row['invoice_date']);
    $invoice_due = escapeHtml($row['invoice_due']);
    $invoice_amount = floatval($row['invoice_amount']);
    $invoice_discount = floatval($row['invoice_discount_amount']);
    $invoice_credit = floatval($row['invoice_credit_amount']);
    $invoice_currency_code = escapeHtml($row['invoice_currency_code']);
    $invoice_note = escapeHtml($row['invoice_note']);
    $invoice_url_key = escapeHtml($row['invoice_url_key']);
    $invoice_created_at = escapeHtml($row['invoice_created_at']);
    $category_id = intval($row['invoice_category_id']);
    $client_id = intval($row['client_id']);
    $client_name = escapeHtml($row['client_name']);
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
    $client_net_terms = intval($row['client_net_terms']);
    if ($client_net_terms == 0) {
        $client_net_terms = $config_default_net_terms;
    }

    // Override Tab Title // No Sanitizing needed as this var will opnly be used in the tab title
    $tab_title = $row['client_name'];
    $page_title = "{$row['invoice_prefix']}{$row['invoice_number']}";

    $sql = mysqli_query($mysqli, "SELECT * FROM companies WHERE company_id = 1");
    $row = mysqli_fetch_assoc($sql);
    $company_id = intval($row['company_id']);
    $company_name = escapeHtml($row['company_name']);
    $company_country = escapeHtml($row['company_country']);
    $company_address = escapeHtml($row['company_address']);
    $company_city = escapeHtml($row['company_city']);
    $company_state = escapeHtml($row['company_state']);
    $company_zip = escapeHtml($row['company_zip']);
    $company_phone_country_code = escapeHtml($row['company_phone_country_code']);
    $company_phone = escapeHtml(formatPhoneNumber($row['company_phone'], $company_phone_country_code));
    $company_email = escapeHtml($row['company_email']);
    $company_website = escapeHtml($row['company_website']);
    $company_tax_id = escapeHtml($row['company_tax_id']);
    if ($config_invoice_show_tax_id && !empty($company_tax_id)) {
        $company_tax_id_display = "Tax ID: $company_tax_id";
    } else {
        $company_tax_id_display = "";
    }
    $company_logo = escapeHtml($row['company_logo']);

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
    $row = mysqli_fetch_assoc($sql_amount_paid);
    $amount_paid = floatval($row['amount_paid']);

    $balance = $invoice_amount - $amount_paid;

    // Get Credit Balance
    $sql_credit_balance = mysqli_query($mysqli, "SELECT SUM(credit_amount) AS credit_balance FROM credits WHERE credit_client_id = $client_id");
    $row = mysqli_fetch_assoc($sql_credit_balance);

    $credit_balance = floatval($row['credit_balance']);

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
    $products_sql = mysqli_query($mysqli, "
        SELECT
            CONCAT(product_code, ' - ', product_name) AS label,
            product_name,
            product_code,
            product_type AS type,
            product_description AS description,
            product_price AS price,
            product_tax_id AS tax,
            tax_percent,
            product_id AS prod_id,
            COALESCE(SUM(product_stock.stock_qty), 0) AS available_stock
        FROM products
        LEFT JOIN product_stock ON product_id = stock_product_id
        LEFT JOIN taxes ON product_tax_id = tax_id
        WHERE product_archived_at IS NULL
        GROUP BY product_id
    ");

    if (mysqli_num_rows($products_sql) > 0) {
        while ($row = mysqli_fetch_assoc($products_sql)) {
            $products[] = $row;
        }
        $json_products = json_encode($products);
    }

    // Saved Payment Methods
    $sql_saved_payment_methods = mysqli_query($mysqli, "
        SELECT * FROM client_saved_payment_methods
        LEFT JOIN payment_providers
            ON client_saved_payment_methods.saved_payment_provider_id = payment_providers.payment_provider_id
        WHERE saved_payment_client_id = $client_id
        AND payment_provider_active = 1;
    ");

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="invoices.php">All Invoices</a>
        </li>
        <li class="breadcrumb-item">
            <a href="invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?> Invoices</a>
        </li>
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
                                    <?php if (!empty($config_smtp_provider) && !empty($contact_email)) { ?>
                                        <a class="dropdown-item" href="post.php?email_invoice=<?= $invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-paper-plane mr-2"></i>Send Email
                                        </a>
                                        <div class="dropdown-divider"></div>
                                    <?php } ?>
                                    <a class="dropdown-item" href="post.php?mark_invoice_sent=<?= $invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-check mr-2"></i>Mark Sent
                                    </a>
                                </div>
                            <?php } ?>

                            <?php if ($invoice_status !== 'Paid' && $invoice_status !== 'Cancelled' && $invoice_status !== 'Draft' && $invoice_status !== 'Non-Billable' && $invoice_amount != 0) { ?>

                                <div class="btn-group">
                                    <button type="button" class="btn btn-success ajax-modal" data-modal-url="modals/payment/payment_add.php?id=<?= $invoice_id ?>"><i class="fa fa-fw fa-credit-card mr-2"></i>Add Payment</button>

                                    <?php if (mysqli_num_rows($sql_saved_payment_methods) > 0 && ($invoice_status === 'Sent' || $invoice_status === 'Viewed')) { ?>
                                    <button type="button" class="btn btn-success dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/payment/payment_saved_method_add.php?id=<?= $invoice_id ?>"><i class="fas fa-fw fa-wallet mr-2"></i>Pay with Saved Card</a>
                                    </div>
                                    <?php } ?>

                                </div>

                            <?php } ?>

                            <?php if (($invoice_status == 'Sent' || $invoice_status == 'Viewed') && $invoice_amount == 0 && $invoice_status !== 'Non-Billable') { ?>
                                <a class="btn btn-dark" href="post.php?mark_invoice_non-billable=<?= $invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/invoice/invoice_edit.php?id=<?= $invoice_id ?>">
                                    <i class="fa fa-fw fa-edit text-secondary mr-2"></i>Edit
                                </a>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/invoice/invoice_copy.php?id=<?= $invoice_id ?>">
                                    <i class="fa fa-fw fa-copy text-secondary mr-2"></i>Copy
                                </a>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/invoice/invoice_recurring_add.php?invoice_id=<?= $invoice_id ?>">
                                    <i class="fa fa-fw fa-sync-alt text-secondary mr-2"></i>Recurring
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" onclick="window.print();">
                                    <i class="fa fa-fw fa-print text-secondary mr-2"></i>Print
                                </a>
                                <a class="dropdown-item" href="post.php?export_invoice_pdf=<?= $invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" target="_blank">
                                    <i class="fa fa-fw fa-download text-secondary mr-2"></i>Download PDF
                                </a>
                                <a class="dropdown-item" href="post.php?export_invoice_packing_slip=<?= $invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" target="_blank">
                                    <i class="fa fa-fw fa-box-open text-secondary mr-2"></i>Packing Slip
                                </a>
                                <?php if (!empty($config_smtp_provider) && !empty($contact_email)) { ?>
                                    <a class="dropdown-item" href="post.php?email_invoice=<?= $invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fa fa-fw fa-paper-plane text-secondary mr-2"></i>Send Email
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item clipboardjs" href="#" data-clipboard-text="https://<?= $config_base_url ?>/guest/guest_view_invoice.php?invoice_id=<?php echo "$invoice_id&url_key=$invoice_url_key"; ?>">
                                    <i class="fa fa-fw fa-copy text-secondary mr-2"></i>Copy Guest URL
                                </a>
                                <?php if ($invoice_status !== 'Cancelled' && $invoice_status !== 'Paid' && $invoice_status !== 'Non-Billable') { ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?cancel_invoice=<?= $invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fa fa-fw fa-times mr-2"></i>Cancel
                                    </a>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_invoice=<?= $invoice_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                </a>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        <div class="card-body">

            <div class="row mb-3">
                <?php if (file_exists("../uploads/settings/$company_logo")) { ?>
                <div class="col-sm-2">
                    <img class="img-fluid" src="<?php echo "../uploads/settings/$company_logo"; ?>" alt="Company logo">
                </div>
                <?php } ?>
                <div class="col-sm-6 <?php if (!file_exists("../uploads/settings/$company_logo")) { echo "col-sm-8"; } ?>">
                    <ul class="list-unstyled">
                        <li><h4><strong><?php echo $company_name; ?></strong></h4></li>
                        <li><?php echo formatAddress($company_address, $company_city, $company_state, $company_zip, $company_country, '<br>'); ?></li>
                        <li><?php echo "$company_email | $company_phone"; ?></li>
                        <li><?php echo $company_website; ?></li>
                        <?php if ($company_tax_id_display) { ?>
                        <li><?php echo $company_tax_id_display; ?></li>
                        <?php } ?>
                    </ul>
                </div>

                <div class="col-sm-4">
                    <h3 class="text-right"><strong>INVOICE</strong></h3>
                    <h5 class="badge badge-<?php echo $invoice_badge_color; ?> p-2 float-right">
                        <?php echo "$invoice_status"; ?>
                    </h5>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th>Invoice #:</th>
                            <td class="text-right"><?php echo "$invoice_prefix$invoice_number"; ?></td>
                        </tr>
                        <tr>
                            <th>Date:</th>
                            <td class="text-right"><?php echo $invoice_date; ?></td>
                        </tr>
                        <tr>
                            <th>Due:</th>
                            <td class="text-right"><?php echo $invoice_due; ?></td>
                        </tr>
                    </table>
                </div>

            </div>
            <div class="row mb-3 bg-light p-3">
                <div class="col">
                    <h6><strong>Bill To:</strong></h6>
                    <ul class="list-unstyled mb-0">
                        <li><?php echo $client_name; ?></li>
                        <li><?php echo formatAddress($location_address, $location_city, $location_state, $location_zip, $location_country, '<br>'); ?></li>
                        <li><?php echo "$contact_email | $contact_phone $contact_extension"; ?></li>
                    </ul>
                </div>
            </div>

            <?php $sql_invoice_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_invoice_id = $invoice_id ORDER BY item_order ASC"); ?>

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="items">
                                <thead class="bg-light">
                                <tr>
                                    <th class="d-print-none"></th>
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

                                $total_tax = 0.00;
                                $sub_total = 0.00;

                                while ($row = mysqli_fetch_assoc($sql_invoice_items)) {
                                    $item_id = intval($row['item_id']);
                                    $item_name = escapeHtml($row['item_name']);
                                    $item_description = escapeHtml($row['item_description']);
                                    $item_quantity = floatval($row['item_quantity']);
                                    $item_price = floatval($row['item_price']);
                                    $item_tax = floatval($row['item_tax']);
                                    $item_total = floatval($row['item_total']);
                                    $item_created_at = escapeHtml($row['item_created_at']);
                                    $tax_id = intval($row['item_tax_id']);
                                    $item_product_id = intval($row['item_product_id']);
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
                                                            <a class="dropdown-item ajax-modal" href="#"
                                                                data-modal-url="modals/invoice/invoice_item_edit.php?id=<?= $item_id ?>">
                                                                <i class="fa fa-fw fa-edit mr-2"></i>Edit
                                                            </a>
                                                            <div class="dropdown-divider"></div>
                                                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_invoice_item=<?= $item_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fa fa-fw fa-trash mr-2"></i>Delete</a>
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
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
                                        <input type="hidden" id="product_id" name="product_id" value="<?= $item_product_id ?? 0 ?>">
                                        <input type="hidden" name="item_order" value="<?php echo mysqli_num_rows($sql_invoice_items) + 1; ?>">
                                        <td></td>
                                        <td>
                                            <input type="text" class="form-control" id="name" name="name" placeholder="Item" required>
                                        </td>
                                        <td>
                                            <textarea class="form-control" rows="2" id="desc" name="description" placeholder="Enter a Description"></textarea>
                                        </td>
                                        <td>
                                            <input type="text" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" class="form-control" style="text-align: center;" id="qty" name="qty" placeholder="Qty">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" style="text-align: right;" id="price" name="price" placeholder="Price (<?php echo $invoice_currency_code; ?>)">
                                        </td>
                                        <td>
                                            <select class="form-control select2" name="tax_id" id="tax" required>
                                                <option value="0">No Tax</option>
                                                <?php
                                                $taxes_sql = mysqli_query($mysqli, "SELECT * FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");
                                                while ($row = mysqli_fetch_assoc($taxes_sql)) {
                                                    $tax_id = intval($row['tax_id']);
                                                    $tax_name = escapeHtml($row['tax_name']);
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
            <div class="row mb-3">
                <div class="col-sm-7">
                    <div class="card">
                        <div class="card-header text-bold">
                            Notes:
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
                    <table class="table table-hover mb-0">
                        <tbody>

                        <tr>
                            <td>Subtotal:</td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $sub_total, $invoice_currency_code); ?></td>
                        </tr>
                        <?php
                        if ($invoice_discount > 0) {
                            ?>
                            <tr>
                                <td>Discount:</td>
                                <td class="text-right">-<?php echo numfmt_format_currency($currency_format, $invoice_discount, $invoice_currency_code); ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                        <?php
                        if ($invoice_credit > 0) {
                            ?>
                            <tr>
                                <td>Credit:</td>
                                <td class="text-right">-<?php echo numfmt_format_currency($currency_format, $invoice_credit, $invoice_currency_code); ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                        <?php if ($total_tax > 0) { ?>
                            <tr>
                                <td>Tax:</td>
                                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $total_tax, $invoice_currency_code); ?></td>
                            </tr>
                        <?php } ?>
                        <tr>
                            <td>Total:</td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code); ?></td>
                        </tr>
                        <?php
                        if ($amount_paid > 0) { ?>
                            <tr>
                                <td><div class="text-success">Paid:</div></td>
                                <td class="text-right text-success"><?php echo numfmt_format_currency($currency_format, $amount_paid, $invoice_currency_code); ?></td>
                            </tr>
                        <?php } ?>

                        <tr class="h5 text-bold">
                            <td>Balance:</td>
                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $balance, $invoice_currency_code); ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <hr class="d-none d-print-block mt-5">
            <div class="d-none d-print-block text-center text-secondary"><?php echo nl2br(escapeHtml($config_invoice_footer)); ?></div>
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

                        while ($row = mysqli_fetch_assoc($sql_history)) {
                            $history_created_at = $row['history_created_at'];
                            $history_status = escapeHtml($row['history_status']);
                            $history_description = escapeHtml($row['history_description']);

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
        <div class="col-sm d-print-none <?php if (mysqli_num_rows($sql_payments) == 0) { echo "d-none"; } ?>">
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
                            <thead>
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

                            while ($row = mysqli_fetch_assoc($sql_payments)) {
                                $payment_id = intval($row['payment_id']);
                                $payment_date = escapeHtml($row['payment_date']);
                                $payment_amount = floatval($row['payment_amount']);
                                $payment_currency_code = escapeHtml($row['payment_currency_code']);
                                $payment_reference = escapeHtml($row['payment_reference']);
                                $account_name = escapeHtml($row['account_name']);

                                ?>
                                <tr>
                                    <td><?php echo $payment_date; ?></td>
                                    <td class="text-right"><?php echo numfmt_format_currency($currency_format, $payment_amount, $payment_currency_code); ?></td>
                                    <td><?php echo $payment_reference; ?></td>
                                    <td><?php echo $account_name; ?></td>
                                    <td class="text-center"><a class="btn btn-light text-danger confirm-link" href="post.php?delete_payment=<?= $payment_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fa fa-times"></i></a></td>
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
        <div class="col-sm d-print-none <?php if (mysqli_num_rows($sql_tickets) == 0) { echo "d-none"; } ?>">
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
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th class="text-right">Time Worked</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql_tickets)) {
                                $ticket_id = intval($row['ticket_id']);
                                $ticket_created_at = escapeHtml($row['ticket_created_at']);
                                $ticket_subject = escapeHtml($row['ticket_subject']);
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
        </div>
    <?php
    include_once "modals/invoice/invoice_add_ticket.php";
    include_once "modals/invoice/invoice_note.php";

}

require_once "../includes/footer.php";

?>

<!-- JSON Autocomplete / type ahead -->
<link rel="stylesheet" href="../libs/jquery-ui/jquery-ui.min.css">
<script src="../libs/jquery-ui/jquery-ui.min.js"></script>
<script>

$(function() {

    var availableProducts = <?php echo $json_products ?? '[]'?>;

    $("#name").autocomplete({
        minLength: 1,
        delay: 0,
        source: function(request, response) {
            var term = $.ui.autocomplete.escapeRegex(request.term.toLowerCase());
            var matcher = new RegExp(term, "i");
            var matches = $.grep(availableProducts, function(item) {
                return matcher.test(item.label) || matcher.test(item.product_name) || matcher.test(item.product_code);
            });
            response(matches);
        },
        select: function (event, ui) {
            $("#name").val(ui.item.label);
            $("#desc").val(ui.item.description);
            $("#qty").val(1);
            $("#price").val(ui.item.price);
            $("#tax").val(ui.item.tax);
            $("#product_id").val(ui.item.prod_id);
            return false;
        }
    });

    // Keep it simple: default jQuery UI look, just richer content
    $("#name").autocomplete("instance")._renderItem = function(ul, item) {
        var typeText = item.type ? item.type.charAt(0).toUpperCase() + item.type.slice(1).toLowerCase() : "";
        var showStock = (typeText.toLowerCase() !== "service");

        var taxText = (item.tax_percent != null) ? (parseFloat(item.tax_percent) + "%") : "No tax";
        var priceText = (item.price != null && item.price !== "") ? String(item.price) : "";

        var infoLeft =
            "<div class='d-flex justify-content-between align-items-start'>" +
                "<div class='flex-fill pr-2'>" +
                    "<div class='font-weight-bold'>" + (item.label || "") +
                        (typeText ? " <small class='text-muted'>(" + typeText + ")</small>" : "") +
                    "</div>" +
                    "<div class='small text-muted'>" + (item.description || "") + "</div>" +
                    "<div class='mt-1'>" +
                        "<span class='badge badge-secondary mr-1'>Tax: " + taxText + "</span>" +
                        (showStock ? "<span class='badge " + ((item.available_stock ?? 0) > 0 ? "badge-success" : "badge-danger") + "'>Stock: " + (item.available_stock ?? 0) + "</span>" : "") +
                    "</div>" +
                "</div>" +
                "<div class='text-right'>" +
                    "<div class='font-weight-bold'>" + priceText + "</div>" +
                "</div>" +
            "</div>";

        // Use the jQuery UI wrapper so default hover/focus styles apply
        return $("<li>")
            .append($("<div class='ui-menu-item-wrapper'>").append(infoLeft))
            .appendTo(ul);
    };
});

</script>

<script src="../libs/SortableJS/Sortable.min.js"></script>
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
            csrf_token: '<?= $_SESSION['csrf_token'] ?>',
            invoice_id: <?php echo $invoice_id; ?>,
            positions: positions
        });
    }
});
</script>
