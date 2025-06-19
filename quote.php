<?php

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
} else {
    require_once "includes/inc_all.php";
}

// Perms
enforceUserPermission('module_sales');

if (isset($_GET['quote_id'])) {

    $quote_id = intval($_GET['quote_id']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM quotes
        LEFT JOIN clients ON quote_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE quote_id = $quote_id
        $access_permission_query
        LIMIT 1"
    );

    if (mysqli_num_rows($sql) == 0) {
        if (isset($_GET['client_id'])) {
            $backlink_append = "?client_id=$client_id";
        } else {
            $backlink_append = '';
        }
        echo "<h1 class='text-secondary pt-5' style='text-align: center'>There is no Quote here<br><small><a href='quotes.php$backlink_append'><i class='fas fa-arrow-left mr-2'></i>Back to Quotes</a></small></h1>";
        require_once "includes/footer.php";

        exit();
    }

    $row = mysqli_fetch_array($sql);
    $quote_id = intval($row['quote_id']);
    $quote_prefix = nullable_htmlentities($row['quote_prefix']);
    $quote_number = intval($row['quote_number']);
    $quote_scope = nullable_htmlentities($row['quote_scope']);
    $quote_status = nullable_htmlentities($row['quote_status']);
    $quote_date = nullable_htmlentities($row['quote_date']);
    $quote_expire = nullable_htmlentities($row['quote_expire']);
    $quote_amount = floatval($row['quote_amount']);
    $quote_discount = floatval($row['quote_discount_amount']);
    $quote_currency_code = nullable_htmlentities($row['quote_currency_code']);
    $quote_note = nullable_htmlentities($row['quote_note']);
    $quote_url_key = nullable_htmlentities($row['quote_url_key']);
    $quote_created_at = nullable_htmlentities($row['quote_created_at']);
    $category_id = intval($row['quote_category_id']);
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

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $tab_title = $row['client_name'];
    $page_title = "{$row['quote_prefix']}{$row['quote_number']}";

    $sql = mysqli_query($mysqli, "SELECT * FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
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

    $sql_history = mysqli_query($mysqli, "SELECT * FROM history WHERE history_quote_id = $quote_id ORDER BY history_id DESC");

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

    //Product autocomplete
    $products_sql = mysqli_query($mysqli, "SELECT product_name AS label, product_description AS description, product_price AS price, product_tax_id AS tax FROM products WHERE product_archived_at IS NULL");

    if (mysqli_num_rows($products_sql) > 0) {
        while ($row = mysqli_fetch_array($products_sql)) {
            $products[] = $row;
        }
        $json_products = json_encode($products);
    }

    // Quote File Attachments
    $sql_quote_files = mysqli_query(
        $mysqli,
        "SELECT file_reference_name, file_name, file_created_at FROM quote_files LEFT JOIN files ON quote_files.file_id = files.file_id WHERE quote_id = $quote_id"
    );

?>

    <ol class="breadcrumb d-print-none">
        <?php if (isset($_GET['client_id'])) { ?>
        <li class="breadcrumb-item">
            <a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="quotes.php?client_id=<?php echo $client_id; ?>">Quotes</a>
        </li>
        <?php } else { ?>
        <li class="breadcrumb-item">
            <a href="quotes.php">Global Quotes</a>
        </li>
        <li class="breadcrumb-item">
            <a href="quotes.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?> Quotes</a>
        </li>
        <?php } ?>
        <li class="breadcrumb-item active"><?php echo "$quote_prefix$quote_number"; ?></li>
    </ol>

    <div class="card">
        <div class="card-header d-print-none">

            <?php if (lookupUserPermission("module_sales") >= 2) { ?>
                <div class="row">

                <div class="col-8">
                <?php if ($quote_status == 'Draft') { ?>
                    <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                        <i class="fas fa-fw fa-paper-plane mr-2"></i>Send
                    </button>
                    <div class="dropdown-menu">
                        <?php if (!empty($config_smtp_host) && !empty($contact_email)) { ?>
                            <a class="dropdown-item" href="post.php?email_quote=<?php echo $quote_id; ?>">
                                <i class="fas fa-fw fa-paper-plane mr-2"></i>Send Email
                            </a>
                            <div class="dropdown-divider"></div>
                        <?php } ?>
                        <a class="dropdown-item" href="post.php?mark_quote_sent=<?php echo $quote_id; ?>">
                            <i class="fas fa-fw fa-check mr-2"></i>Mark Sent
                        </a>
                    </div>
                <?php } ?>

                <?php if ($quote_status == 'Sent' || $quote_status == 'Viewed') { ?>
                    <a class="btn btn-primary confirm-link" href="post.php?accept_quote=<?php echo $quote_id; ?>">
                        <i class="fas fa-thumbs-up mr-2"></i>Accept
                    </a>
                    <a class="btn btn-default confirm-link" href="post.php?decline_quote=<?php echo $quote_id; ?>">
                        <i class="fas fa-thumbs-down mr-2"></i>Decline
                    </a>
                <?php } ?>

                <?php if ($quote_status == 'Accepted') { ?>
                    <div class="btn-group fix-quote-dropdown">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addQuoteToInvoiceModal<?php echo $quote_id; ?>">
                            <i class="fas fa-check mr-2"></i>Invoice
                        </button>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-icon" data-toggle="dropdown" aria-expanded="false">
                            <span class="sr-only">Toggle Dropdown</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="post.php?mark_quote_invoiced=<?php echo $quote_id; ?>">
                                <i class="fas fa-fw fa-check mr-2"></i>Mark Invoiced
                            </a>
                        </div>
                    </div>
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
                                data-ajax-url = "ajax/ajax_quote_edit.php"
                                data-ajax-id = "<?php echo $quote_id; ?>"
                                >
                                <i class="fa fa-fw fa-edit text-secondary mr-2"></i>Edit
                            </a>
                            <?php if (lookupUserPermission("module_sales") >= 2) { ?>
                                <a class="dropdown-item" href="#"
                                    data-toggle = "ajax-modal"
                                    data-ajax-url = "ajax/ajax_quote_copy.php"
                                    data-ajax-id = "<?php echo $quote_id; ?>"
                                    >
                                    <i class="fa fa-fw fa-copy text-secondary mr-2"></i>Copy
                                </a>
                            <?php } ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" onclick="window.print();">
                                <i class="fa fa-fw fa-print text-secondary mr-2"></i>Print
                            </a>
                            <a class="dropdown-item" href="post.php?export_quote_pdf=<?php echo $quote_id; ?>" target="_blank">
                                <i class="fa fa-fw fa-download text-secondary mr-2"></i>Download PDF
                            </a>
                            <?php if (!empty($config_smtp_host) && !empty($contact_email)) { ?>
                                <a class="dropdown-item" href="post.php?email_quote=<?php echo $quote_id; ?>">
                                    <i class="fa fa-fw fa-paper-plane text-secondary mr-2"></i>Send Email
                                </a>
                            <?php } ?>
                            <a class="dropdown-item" target="_blank" href="guest/guest_view_quote.php?quote_id=<?php echo "$quote_id&url_key=$quote_url_key"; ?>">
                                <i class="fa fa-fw fa-link text-secondary mr-2"></i>Guest URL
                            </a>
                            <?php if (lookupUserPermission("module_sales") >= 3) { ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_quote=<?php echo $quote_id; ?>">
                                    <i class="fa fa-fw fa-times mr-2"></i>Delete
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <div class="card-body">

            <div class="row mb-3">
                <?php if (file_exists("uploads/settings/$company_logo")) { ?>
                <div class="col-sm-2">
                    <img class="img-fluid" src="<?php echo "uploads/settings/$company_logo"; ?>" alt="Company logo">
                </div>
                <?php } ?>
                <div class="col-sm-6 <?php if (!file_exists("uploads/settings/$company_logo")) { echo "col-sm-8"; } ?>">
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

            <?php $sql_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_quote_id = $quote_id ORDER BY item_order ASC"); ?>

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table table-borderless" id="items">
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

                                    while ($row = mysqli_fetch_array($sql_items)) {
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
                                                <?php if ($quote_status !== "Invoiced" && $quote_status !== "Accepted" && $quote_status !== "Declined" && lookupUserPermission("module_sales") >= 2) { ?>
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
                                                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_quote_item=<?php echo $item_id; ?>">
                                                                    <i class="fa fa-fw fa-trash mr-2"></i>Delete
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                            <td><?php echo $item_name; ?></td>
                                            <td><?php echo nl2br($item_description); ?></td>
                                            <td class="text-center"><?php echo number_format($item_quantity, 2); ?></td>
                                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_price, $quote_currency_code); ?></td>
                                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_tax, $quote_currency_code); ?></td>
                                            <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_total, $quote_currency_code); ?></td>
                                        </tr>

                                    <?php

                                    }

                                    ?>

                                    <tr class="d-print-none" <?php if ($quote_status == "Invoiced" || $quote_status == "Accepted" || $quote_status == "Declined" || lookupUserPermission("module_sales") <= 1) {
                                                                    echo "hidden";
                                                                } ?>>
                                        <form action="post.php" method="post" autocomplete="off">
                                            <input type="hidden" name="quote_id" value="<?php echo $quote_id; ?>">
                                            <input type="hidden" name="item_order" value="<?php
                                            //find largest order number and add 1
                                            $sql = mysqli_query($mysqli, "SELECT MAX(item_order) AS item_order FROM invoice_items WHERE item_quote_id = $quote_id");
                                            $row = mysqli_fetch_array($sql);
                                            $item_order = intval($row['item_order']) + 1;
                                            echo $item_order;
                                            ?>">
                                            <td></td>
                                            <td>
                                                <input type="text" class="form-control" name="name" id="name" placeholder="Item" required>
                                            </td>
                                            <td>
                                                <textarea class="form-control" rows="2" name="description" id="desc" placeholder="Enter a Description"></textarea>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" id="qty" style="text-align: center;" name="qty" placeholder="Qty">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" id="price" style="text-align: right;" name="price" placeholder="Price (<?php echo $quote_currency_code; ?>)">
                                            </td>
                                            <td>
                                                <select class="form-control select2" id="tax" name="tax_id" required>
                                                    <option value="0">No Tax</option>
                                                    <?php

                                                    $taxes_sql = mysqli_query($mysqli, "SELECT tax_id, tax_name, tax_percent FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");
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
                                                <button class="btn btn-light text-success" type="submit" name="add_quote_item">
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
                                <?php if (lookupUserPermission("module_sales") >= 2) { ?>
                                    <a href="#" class="btn btn-light btn-tool" data-toggle="modal" data-target="#quoteNoteModal">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php echo nl2br($quote_note); ?>
                        </div>
                    </div>
                </div>

                <div class="col-sm-3 offset-sm-2">
                    <table class="table table-borderless">
                        <tbody>
                            <tr>
                                <td>Subtotal:</td>
                                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $sub_total, $quote_currency_code); ?></td>
                            </tr>
                            <?php if ($quote_discount > 0) { ?>
                                <tr>
                                    <td>Discount:</td>
                                    <td class="text-right">-<?php echo numfmt_format_currency($currency_format, $quote_discount, $quote_currency_code); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if ($total_tax > 0) { ?>
                                <tr>
                                    <td>Tax:</td>
                                    <td class="text-right"><?php echo numfmt_format_currency($currency_format, $total_tax, $quote_currency_code); ?></td>
                                </tr>
                            <?php } ?>
                            <tr class="h5 text-bold border-top">
                                <td>Total:</td>
                                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <hr class="d-none d-print-block mt-5">

            <div class="d-none d-print-block text-center"><?php echo nl2br(nullable_htmlentities($config_quote_footer)); ?></div>
        </div>
    </div>

    <?php if (mysqli_num_rows($sql_quote_files) > 0) { ?>
        <div class="row mb-3">
        <div class="col-sm d-print-none">
            <div class="card">
                <div class="card-header text-bold">
                    <i class="fa fa-paperclip mr-2"></i>Attachments
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
                            <th>File Name</th>
                            <th>Upload date</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($quote_file = mysqli_fetch_array($sql_quote_files)) {
                            $name = nullable_htmlentities($quote_file['file_name']);
                            $ref_name = nullable_htmlentities($quote_file['file_reference_name']);
                            $created = nullable_htmlentities($quote_file['file_created_at']);

                            ?>
                            <tr>
                                <td><a target="_blank" href="/uploads/clients/<?php echo $client_id ?>/<?php echo $ref_name ?>"><?php echo $name; ?></a></td>
                                <td><?php echo $created; ?></td>
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
    <?php } ?>

    <div class="row mb-3">
        <div class="col-sm d-print-none">
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
                                $history_created_at = nullable_htmlentities($row['history_created_at']);
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
    </div>

<?php
    require_once "modals/quote_to_invoice_modal.php";
    require_once "modals/quote_note_modal.php";
}

require_once "includes/footer.php";

?>

<!-- JSON Autocomplete / type ahead -->
<!-- //TODO: Move to js/ -->
<link rel="stylesheet" href="plugins/jquery-ui/jquery-ui.min.css">
<script src="plugins/jquery-ui/jquery-ui.min.js"></script>
<script>
    $(function() {
        var availableProducts = <?php echo $json_products ?? '""' ?>;

        $("#name").autocomplete({
            source: availableProducts,
            select: function(event, ui) {
                $("#name").val(ui.item.label); // Product name field - this seemingly has to referenced as label
                $("#desc").val(ui.item.description); // Product description field
                $("#qty").val(1); // Product quantity field automatically make it a 1
                $("#price").val(ui.item.price); // Product price field
                $("#tax").val(ui.item.tax); // Tax field
                return false;
            }
        });
    });
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
            update_quote_items_order: true,
            quote_id: <?php echo $quote_id; ?>,
            positions: positions
        });
    }
});
</script>
<link rel="stylesheet" href="css/quote_dropdowns_fix.css">