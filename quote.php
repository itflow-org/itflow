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
        echo '<h1 class="text-secondary mt-5" style="text-align: center">Nothing to see here</h1>';
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
    $contact_email = nullable_htmlentities($row['contact_email']);
    $contact_phone = formatPhoneNumber($row['contact_phone']);
    $contact_extension = nullable_htmlentities($row['contact_extension']);
    $contact_mobile = formatPhoneNumber($row['contact_mobile']);
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
    $company_phone = formatPhoneNumber($row['company_phone']);
    $company_email = nullable_htmlentities($row['company_email']);
    $company_website = nullable_htmlentities($row['company_website']);
    $company_logo = nullable_htmlentities($row['company_logo']);
    if (!empty($company_logo)) {
        $company_logo_base64 = base64_encode(file_get_contents("uploads/settings/$company_logo"));
    }

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
<link rel="stylesheet" href="plugins/dragula/dragula.min.css">

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
            <a href="quotes.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?>Quotes</a>
        </li>
        <?php } ?>
        <li class="breadcrumb-item active"><?php echo "$quote_prefix$quote_number"; ?></li>
    </ol>

    <div class="card">
        <div class="card-header d-print-none">

            <div class="row">

                <div class="col-8">
                    <?php if ($quote_status == 'Draft' && lookupUserPermission("module_sales") >= 2) { ?>
                        <button class="btn btn-primary dropdown-toggle" type="button" data-toggle="dropdown">
                            <i class="fas fa-paper-plane mr-2"></i>Send
                        </button>
                        <div class="dropdown-menu">

                            <a class="dropdown-item" href="post.php?mark_quote_sent=<?php echo $quote_id; ?>">
                                <i class="fas fa-fw fa-check mr-2"></i>Mark Sent
                            </a>
                        </div>
                    <?php } ?>

                    <?php if ($quote_status == 'Sent' || $quote_status == 'Viewed') { ?>
                        <a class="btn btn-primary" href="post.php?accept_quote=<?php echo $quote_id; ?>">
                            <i class="fas fa-thumbs-up mr-2"></i>Accept
                        </a>
                        <a class="btn btn-default" href="post.php?decline_quote=<?php echo $quote_id; ?>">
                            <i class="fas fa-thumbs-down mr-2"></i>Decline
                        </a>
                    <?php } ?>

                    <?php if ($quote_status == 'Accepted') { ?>
                        <a class="btn btn-primary" href="#" data-toggle="modal" data-target="#addQuoteToInvoiceModal<?php echo $quote_id; ?>">
                            <i class="fas fa-check mr-2"></i>Invoice
                        </a>
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
                            <a class="dropdown-item" href="#" onclick="pdfMake.createPdf(docDefinition).download('<?php echo strtoAZaz09(html_entity_decode("$quote_date-$company_name-$client_name-$config_quote_localization_title-$quote_prefix$quote_number")); ?>');">
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
        </div>

        <div class="card-body">

            <div class="row mb-4">
                <div class="col-2">
                    <img class="img-fluid" src="<?php echo "uploads/settings/$company_logo"; ?>" alt="Company logo">
                </div>
                <div class="col-10">
                    <div class="ribbon-wrapper">
                        <div class="ribbon bg-<?php echo $quote_badge_color; ?>">
                            <?php echo $quote_status; ?>
                        </div>
                    </div>
                    <h3 class="text-right mt-5"><strong><?php echo $config_quote_localization_title; ?></strong><br><small class="text-secondary"><?php echo "$quote_prefix$quote_number"; ?></small></h3>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col">
                    <ul class="list-unstyled">
                        <li>
                            <h4><strong><?php echo $company_name; ?></strong></h4>
                        </li>
                        <li><?php echo $company_address; ?></li>
                        <li><?php echo "$company_city $company_state $company_zip"; ?></li>
                        <li><?php echo $company_phone; ?></li>
                        <li><?php echo $company_email; ?></li>
                    </ul>
                </div>
                <div class="col">
                    <ul class="list-unstyled text-right">
                        <li>
                            <h4><strong><?php echo $client_name; ?></strong></h4>
                        </li>
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
                        <tr class="text-bold">
                            <td>Expire</td>
                            <td class="text-right"><?php echo $quote_expire; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php $sql_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_quote_id = $quote_id ORDER BY item_order ASC"); ?>

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
                                                <?php } ?>
                                            </td>
                                            <td class="grab-cursor"><?php echo $item_name; ?></td>
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
                                                <input type="text" class="form-control" inputmode="numeric" pattern="-?[0-9]*\.?[0-9]{0,2}" id="qty" style="text-align: center;" name="qty" placeholder="Quantity">
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

            <div class="row mb-4">
                <div class="col-sm-7">
                    <div class="card">
                        <div class="card-header text-bold">
                            Notes
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
                            <tr class="border-bottom">
                                <td>Subtotal</td>
                                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $sub_total, $quote_currency_code); ?></td>
                            </tr>
                            <?php if ($quote_discount > 0) { ?>
                                <tr class="border-bottom">
                                    <td>Discount</td>
                                    <td class="text-right">-<?php echo numfmt_format_currency($currency_format, $quote_discount, $quote_currency_code); ?></td>
                                </tr>
                            <?php } ?>
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

<script src='plugins/pdfmake/pdfmake.min.js'></script>
<script src='plugins/pdfmake/vfs_fonts.js'></script>
<script>
    var docDefinition = {
        info: {
            title: <?php echo json_encode(html_entity_decode($company_name) . "- " . $config_quote_localization_title) ?>,
            author: <?php echo json_encode(html_entity_decode($company_name)) ?>
        },

        //watermark: {text: '<?php echo $quote_status; ?>', color: 'lightgrey', opacity: 0.3, bold: true, italics: false},

        content: [
            // Header
            {
                columns: [
                    <?php if (!empty($company_logo_base64)) { ?> {
                            image: <?php echo json_encode("data:image;base64,$company_logo_base64") ?>,
                            width: 120
                        },
                    <?php } ?>

                    [{
                        text: '<?php echo $config_quote_localization_title; ?>',
                        style: 'invoiceTitle',
                        width: '*'
                    }, {
                        text: <?php echo json_encode("$quote_prefix$quote_number") ?>,
                        style: 'invoiceNumber',
                        width: '*'
                    }, ],
                ],
            },
            // Billing Headers
            {
                columns: [{
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
                columns: [{
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
                    widths: ['*', 80, 80],

                    body: [
                        // Total
                        [{
                                text: '',
                                rowSpan: 3
                            },
                            {},
                            {},
                        ],
                        [{},
                            {
                                text: 'Date',
                                style: 'invoiceDateTitle'
                            },
                            {
                                text: <?php echo json_encode(html_entity_decode($quote_date)) ?>,
                                style: 'invoiceDateValue'
                            },
                        ],
                        [{},
                            {
                                text: 'Expire',
                                style: 'invoiceDueDateTitle'
                            },
                            {
                                text: <?php echo json_encode(html_entity_decode($quote_expire)) ?>,
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
                    widths: ['*', 40, 'auto', 'auto', 80],

                    body: [
                        // Table Header
                        [{
                                text: 'Product',
                                style: ['itemsHeader', 'left']
                            },
                            {
                                text: 'Qty',
                                style: ['itemsHeader', 'center']
                            },
                            {
                                text: 'Price',
                                style: ['itemsHeader', 'right']
                            },
                            {
                                text: 'Tax',
                                style: ['itemsHeader', 'right']
                            },
                            {
                                text: 'Total',
                                style: ['itemsHeader', 'right']
                            }
                        ],
                        // Items
                        <?php
                        $total_tax = 0.00;
                        $sub_total = 0.00;

                        $sql_invoice_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_quote_id = $quote_id ORDER BY item_order ASC");

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
                                [{
                                        text: <?php echo json_encode($item_name) ?>,
                                        style: 'itemTitle'
                                    },
                                    {
                                        text: <?php echo json_encode($item_description) ?>,
                                        style: 'itemDescription'
                                    }
                                ], {
                                    text: <?php echo json_encode($item_quantity) ?>,
                                    style: 'itemQty'
                                }, {
                                    text: <?php echo json_encode(numfmt_format_currency($currency_format, $item_price, $quote_currency_code)) ?>,
                                    style: 'itemNumber'
                                }, {
                                    text: <?php echo json_encode(numfmt_format_currency($currency_format, $item_tax, $quote_currency_code)) ?>,
                                    style: 'itemNumber'
                                }, {
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
                    widths: ['*', 'auto', 80],

                    body: [
                        // Total
                        [{
                                text: 'Notes',
                                style: 'notesTitle'
                            },
                            {},
                            {}
                        ],
                        [{
                                rowSpan: '*',
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
                        <?php if ($quote_discount > 0) { ?>[{}, {
                                text: 'Discount',
                                style: 'itemsFooterSubTitle'
                            }, {
                                text: <?php echo json_encode(numfmt_format_currency($currency_format, -$quote_discount, $quote_currency_code)) ?>,
                                style: 'itemsFooterSubValue'
                            }],
                        <?php } ?>
                        <?php if ($total_tax > 0) { ?>[{}, {
                                text: 'Tax',
                                style: 'itemsFooterSubTitle'
                            }, {
                                text: <?php echo json_encode(numfmt_format_currency($currency_format, $total_tax, $quote_currency_code)) ?>,
                                style: 'itemsFooterSubValue'
                            }],
                        <?php } ?>[{}, {
                            text: 'Total',
                            style: 'itemsFooterTotalTitle'
                        }, {
                            text: <?php echo json_encode(numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code)) ?>,
                            style: 'itemsFooterTotalValue'
                        }],
                    ]
                }, // table
                layout: 'lightHorizontalLines'
            },
            // TERMS / FOOTER
            {
                text: <?php echo json_encode($config_quote_footer) ?>,
                style: 'documentFooterCenter'
            }
        ], //End Content,
        styles: {
            // Document Footer
            documentFooterCenter: {
                fontSize: 9,
                margin: [10, 50, 10, 10],
                alignment: 'center'
            },
            // Invoice Title
            invoiceTitle: {
                fontSize: 18,
                bold: true,
                alignment: 'right',
                margin: [0, 0, 0, 3]
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
                margin: [0, 20, 0, 5]
            },
            invoiceBillingTitleClient: {
                fontSize: 14,
                bold: true,
                alignment: 'right',
                margin: [0, 20, 0, 5]
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
                margin: [0, 0, 0, 30]
            },
            // Invoice Dates
            invoiceDateTitle: {
                fontSize: 10,
                alignment: 'left',
                margin: [0, 5, 0, 5]
            },
            invoiceDateValue: {
                fontSize: 10,
                alignment: 'right',
                margin: [0, 5, 0, 5]
            },
            // Invoice Due Dates
            invoiceDueDateTitle: {
                fontSize: 10,
                bold: true,
                alignment: 'left',
                margin: [0, 5, 0, 5]
            },
            invoiceDueDateValue: {
                fontSize: 10,
                bold: true,
                alignment: 'right',
                margin: [0, 5, 0, 5]
            },
            // Items Header
            itemsHeader: {
                fontSize: 10,
                margin: [0, 5, 0, 5],
                bold: true,
                alignment: 'right'
            },
            // Item Title
            itemTitle: {
                fontSize: 10,
                bold: true,
                margin: [0, 5, 0, 3]
            },
            itemDescription: {
                italics: true,
                fontSize: 9,
                lineHeight: 1.1,
                margin: [0, 3, 0, 5]
            },
            itemQty: {
                fontSize: 10,
                margin: [0, 5, 0, 5],
                alignment: 'center'
            },
            itemNumber: {
                fontSize: 10,
                margin: [0, 5, 0, 5],
                alignment: 'right'
            },
            itemTotal: {
                fontSize: 10,
                margin: [0, 5, 0, 5],
                bold: true,
                alignment: 'right'
            },
            // Items Footer (Subtotal, Total, Tax, etc)
            itemsFooterSubTitle: {
                fontSize: 10,
                margin: [0, 5, 0, 5],
                alignment: 'right'
            },
            itemsFooterSubValue: {
                fontSize: 10,
                margin: [0, 5, 0, 5],
                bold: false,
                alignment: 'right'
            },
            itemsFooterTotalTitle: {
                fontSize: 10,
                margin: [0, 5, 0, 5],
                bold: true,
                alignment: 'right'
            },
            itemsFooterTotalValue: {
                fontSize: 10,
                margin: [0, 5, 0, 5],
                bold: true,
                alignment: 'right'
            },
            notesTitle: {
                fontSize: 10,
                bold: true,
                margin: [0, 5, 0, 5]
            },
            notesText: {
                fontSize: 9,
                margin: [0, 5, 50, 5]
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

<script src="plugins/dragula/dragula.min.js"></script>
<script>
$(document).ready(function() {
    var container = $('table#items tbody')[0];

    dragula([container])
        .on('drop', function (el, target, source, sibling) {
            // Handle the drop event to update the order in the database
            var rows = $(container).children();
            var positions = rows.map(function(index, row) {
                return {
                    id: $(row).data('itemId'),
                    order: index
                };
            }).get();

            // Send the new order to the server
            $.ajax({
                url: 'ajax.php',
                method: 'POST',
                data: {
                    update_quote_items_order: true,
                    quote_id: <?php echo $quote_id; ?>,
                    positions: positions
                },
                success: function(data) {
                    // Handle success
                },
                error: function(error) {
                    console.error('Error updating order:', error);
                }
            });
        });
});
</script>
