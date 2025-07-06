<?php

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
} else { 
    require_once "includes/inc_all.php";
}

if (isset($_GET['recurring_invoice_id'])) {

    $recurring_invoice_id = intval($_GET['recurring_invoice_id']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM recurring_invoices
        LEFT JOIN clients ON recurring_invoice_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        LEFT JOIN recurring_payments ON recurring_payment_recurring_invoice_id = recurring_invoice_id
        WHERE recurring_invoice_id = $recurring_invoice_id LIMIT 1"
    );

    if (mysqli_num_rows($sql) == 0) {
        if (isset($_GET['client_id'])) {
            $backlink_append = "?client_id=$client_id";
        } else {
            $backlink_append = '';
        }
        echo "<h1 class='text-secondary pt-5' style='text-align: center'>There is no Recurring Invoice here<br><small><a href='recurring_invoices.php$backlink_append'><i class='fas fa-arrow-left mr-2'></i>Back to Recurring Invoices</a></small></h1>";
        require_once "includes/footer.php";

        exit();
    }

    $row = mysqli_fetch_array($sql);
    $recurring_invoice_prefix = nullable_htmlentities($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $recurring_invoice_scope = nullable_htmlentities($row['recurring_invoice_scope']);
    $recurring_invoice_frequency = nullable_htmlentities($row['recurring_invoice_frequency']);
    $recurring_invoice_status = nullable_htmlentities($row['recurring_invoice_status']);
    $recurring_invoice_created_at = date('Y-m-d', strtotime($row['recurring_invoice_created_at']));
    $recurring_invoice_last_sent = nullable_htmlentities($row['recurring_invoice_last_sent']);
    if ($recurring_invoice_last_sent == 0) {
        $recurring_invoice_last_sent = '-';
    }
    $recurring_invoice_next_date = nullable_htmlentities($row['recurring_invoice_next_date']);
    $recurring_invoice_amount = floatval($row['recurring_invoice_amount']);
    $recurring_invoice_discount = floatval($row['recurring_invoice_discount_amount']);
    $recurring_invoice_currency_code = nullable_htmlentities($row['recurring_invoice_currency_code']);
    $recurring_invoice_note = nullable_htmlentities($row['recurring_invoice_note']);
    $recurring_invoice_email_notify = intval($row['recurring_invoice_email_notify']);
    $category_id = intval($row['recurring_invoice_category_id']);
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

    if ($recurring_invoice_status == 1) {
        $status = "Active";
        $status_badge_color = "success";
    } else {
        $status = "Inactive";
        $status_badge_color = "secondary";
    }
    $recurring_payment_id = intval($row['recurring_payment_id']);
    $recurring_payment_recurring_invoice_id = intval($row['recurring_payment_recurring_invoice_id']);
    $recurring_payment_method = nullable_htmlentities($row['recurring_payment_method']);

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $tab_title = $row['client_name'];
    $page_title = "{$row['recurring_invoice_prefix']}{$row['recurring_invoice_number']}";

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

    $sql_history = mysqli_query($mysqli, "SELECT * FROM history WHERE history_recurring_invoice_id = $recurring_invoice_id ORDER BY history_id DESC");

    //Product autocomplete
    $products_sql = mysqli_query($mysqli, "SELECT product_name AS label, product_description AS description, product_price AS price, product_tax_id AS tax FROM products WHERE product_archived_at IS NULL");

    if (mysqli_num_rows($products_sql) > 0) {
        while ($row = mysqli_fetch_array($products_sql)) {
            $products[] = $row;
        }
        $json_products = json_encode($products);
    }

    ?>

    <ol class="breadcrumb d-print-none">
        <?php if (isset($_GET['client_id'])) { ?>
        <li class="breadcrumb-item">
            <a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="recurring_invoices.php?client_id=<?php echo $client_id; ?>">Recurring Invoices</a>
        </li>
        <?php } else { ?>
        <li class="breadcrumb-item">
            <a href="recurring_invoices.php">Recurring Invoices</a>
        </li>
        <li class="breadcrumb-item">
            <a href="recurring_invoices.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
        </li>
        <?php } ?>
        <li class="breadcrumb-item active"><?php echo "$recurring_invoice_prefix$recurring_invoice_number"; ?></li>
    </ol>

    <div class="card">
        <div class="card-header d-print-none">

            <div class="row">

                <div class="col-8">
                    <?php if ($config_recurring_invoice_auto_send_invoice) { ?>
                        <?php if ($recurring_invoice_email_notify) { ?>
                            <a href="post.php?recurring_invoice_email_notify=0&recurring_invoice_id=<?php echo $recurring_invoice_id; ?>" class="btn btn-primary"><i class="fas fa-fw fa-bell mr-2"></i>Email Notify</a>
                        <?php } else { ?>
                            <a href="post.php?recurring_invoice_email_notify=1&recurring_invoice_id=<?php echo $recurring_invoice_id; ?>" class="btn btn-outline-danger"><i class="fas fa-fw fa-bell-slash mr-2"></i>Email Notify</a>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($recurring_payment_recurring_invoice_id) { ?>
                        <a class="btn btn-outline-secondary" href="post.php?delete_recurring_payment=<?php echo $recurring_payment_id; ?>">
                            <i class="fas fa-fw fa-times-circle mr-2"></i>Disable AutoPay (<?php echo $recurring_payment_method ?>)
                        </a>
                    <?php } else { ?>
                        <a class="btn btn-secondary" href='#' data-toggle="modal" data-target="#addRecurringPaymentModal<?php echo $recurring_invoice_id; ?>">
                            <i class="fas fa-fw fa-redo-alt mr-2"></i>Create AutoPay
                        </a>
                        <?php require_once "modals/recurring_payment_add_modal.php"; ?>

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
                                data-ajax-url = "ajax/ajax_recurring_invoice_edit.php"
                                data-ajax-id = "<?php echo $recurring_invoice_id; ?>"
                                >
                                <i class="fa fa-fw fa-edit text-secondary mr-2"></i>Edit
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="post.php?force_recurring=<?php echo $recurring_invoice_id; ?>">
                                <i class="fa fa-fw fa-paper-plane text-secondary mr-2"></i>Force Send
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_recurring_invoice=<?php echo $recurring_invoice_id; ?>">
                                <i class="fa fa-fw fa-trash mr-2"></i>Delete
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">

            <div class="row mb-3">
                <div class="col-sm-2">
                    <img class="img-fluid" src="<?php echo "uploads/settings/$company_logo"; ?>" alt="Company logo">
                </div>
                <div class="col-sm-6">
                    <ul class="list-unstyled">
                        <li><h4><strong><?php echo $company_name; ?></strong></h4></li>
                        <li><?php echo $company_address; ?></li>
                        <li><?php echo "$company_city $company_state $company_zip, $company_country"; ?></li>
                        <li><?php echo "$company_email | $company_phone"; ?></li>
                        <li><?php echo $company_website; ?></li>
                    </ul>
                </div>

                <div class="col-sm-4">
                    <h3 class="text-right"><strong>RECURRING INVOICE</strong></h3>
                    <h5 class="badge badge-<?php echo $status_badge_color; ?> p-2 float-right">
                        <?php echo $status; ?>
                    </h5>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th>Frequency:</th>
                            <th class="text-right"><?php echo ucwords($recurring_invoice_frequency); ?>ly</th>
                        </tr>
                        <tr>
                            <th>Next Date:</th>
                            <td class="text-right"><?php echo $recurring_invoice_next_date; ?></td>
                        </tr>
                        <tr>
                            <th>Last Sent:</th>
                            <td class="text-right"><?php echo $recurring_invoice_last_sent; ?></td>
                        </tr>
                        <tr>
                            <th>Created:</th>
                            <td class="text-right"><?php echo $recurring_invoice_created_at; ?></td>
                        </tr>
                    </table>
                </div>

            </div>
            <div class="row mb-3 bg-light p-3">
                <div class="col">
                    <h6><strong>Bill To:</strong></h6>
                    <ul class="list-unstyled mb-0">
                        <li><?php echo $client_name; ?></li>
                        <li><?php echo $location_address; ?></li>
                        <li><?php echo "$location_city $location_state $location_zip, $location_country"; ?></li>
                        <li><?php echo "$contact_email | $contact_phone $contact_extension"; ?></li>
                    </ul>
                </div>
            </div>

            <?php $sql_items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id ORDER BY item_order ASC"); ?>

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
                                        <th class="text-right">Price</th>
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
                                    $item_quantity = number_format(floatval($row['item_quantity']),2);
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
                                                        <a class="dropdown-item text-danger confirm-link" href="post.php?delete_recurring_invoice_item=<?php echo $item_id; ?>"><i class="fa fa-fw fa-trash mr-2"></i>Delete</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $item_name; ?></td>
                                        <td><?php echo nl2br($item_description); ?></td>
                                        <td class="text-center"><?php echo $item_quantity; ?></td>
                                        <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_price, $recurring_invoice_currency_code); ?></td>
                                        <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_tax, $recurring_invoice_currency_code); ?></td>
                                        <td class="text-right"><?php echo numfmt_format_currency($currency_format, $item_total, $recurring_invoice_currency_code); ?></td>
                                    </tr>

                                    <?php

                                    }

                                    ?>

                                    <tr class="d-print-none">
                                        <form action="post.php" method="post">
                                            <input type="hidden" name="recurring_invoice_id" value="<?php echo $recurring_invoice_id; ?>">
                                            <input type="hidden" name="item_order" value="<?php
                                                //find largest order number and add 1
                                                $sql = mysqli_query($mysqli, "SELECT MAX(item_order) AS item_order FROM invoice_items WHERE item_recurring_invoice_id = $recurring_invoice_id");
                                                $row = mysqli_fetch_array($sql);
                                                $item_order = intval($row['item_order']) + 1;
                                                echo $item_order;
                                                ?>">
                                            <td></td>
                                            <td>
                                                <input type="text" class="form-control" id="name" name="name" placeholder="Item" required>
                                            </td>
                                            <td>
                                                <textarea class="form-control"  rows="2" id="desc" name="description" placeholder="Enter a Description"></textarea>
                                            </td>
                                            <td>
                                                <input type="text" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" class="form-control" style="text-align: center;" id="qty" name="qty" placeholder="Qty">
                                            </td>
                                            <td>
                                                <input type="text" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" class="form-control" style="text-align: right;" id="price" name="price" placeholder="Price (<?php echo $recurring_invoice_currency_code; ?>)">
                                            </td>
                                            <td>
                                                <select class="form-control" name="tax_id" id="tax" required>
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
                                                <button class="btn btn-light text-success" type="submit" name="add_recurring_invoice_item">
                                                    <i class="fa fa-fw fa-check"></i>
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
                            Notes
                            <div class="card-tools d-print-none">
                                <a href="#" class="btn btn-light btn-tool" data-toggle="modal" data-target="#recurringInvoiceNoteModal">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php echo nl2br($recurring_invoice_note); ?>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3 offset-sm-2">
                    <table class="table table-borderless">
                        <tbody>
                            <tr>
                                <td>Subtotal</td>
                                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $sub_total, $recurring_invoice_currency_code); ?></td>
                            </tr>
                            <?php if ($recurring_invoice_discount > 0) { ?>
                                <tr>
                                    <td>Discount</td>
                                    <td class="text-right">-<?php echo numfmt_format_currency($currency_format, $recurring_invoice_discount, $recurring_invoice_currency_code); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if ($total_tax > 0) { ?>
                                <tr>
                                    <td>Tax</td>
                                    <td class="text-right"><?php echo numfmt_format_currency($currency_format, $total_tax, $recurring_invoice_currency_code); ?></td>
                                </tr>
                            <?php } ?>
                            <tr class="border-top h5 text-bold">
                                <td>Total</td>
                                <td class="text-right"><?php echo numfmt_format_currency($currency_format, $recurring_invoice_amount, $recurring_invoice_currency_code); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-sm d-print-none">
            <div class="card">
                <div class="card-header text-bold">
                    <i class="fas fa-fw fa-history mr-2"></i>History
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
                            <th>Event</th>
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

    require_once "modals/recurring_invoice_note_modal.php";

}

require_once "includes/footer.php";

?>

<!-- JSON Autocomplete / type ahead -->
<link rel="stylesheet" href="plugins/jquery-ui/jquery-ui.min.css">
<script src="plugins/jquery-ui/jquery-ui.min.js"></script>
<script>
    $(function() {
        var availableProducts = <?php echo $json_products?>;

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
            update_recurring_invoice_items_order: true,
            recurring_invoice_id: <?php echo $recurring_invoice_id; ?>,
            positions: positions
        });
    }
});
</script>
