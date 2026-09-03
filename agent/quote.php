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
        "SELECT client_currency_code, client_id, client_name, client_net_terms, client_website,
            contact_email, contact_extension, contact_mobile, contact_mobile_country_code,
            contact_phone, contact_phone_country_code, location_address, location_city,
            location_country, location_state, location_zip, quote_amount, quote_category_id,
            quote_created_at, quote_currency_code, quote_date, quote_discount_amount, quote_expire,
            quote_id, quote_note, quote_number, quote_prefix, quote_scope, quote_status, quote_url_key FROM quotes
        LEFT JOIN clients ON quote_client_id = client_id
        LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE quote_id = $quote_id
        " . clientScopeSql('quote_client_id') . "
        LIMIT 1"
    );

    if (mysqli_num_rows($sql) == 0) {
        if (isset($_GET['client_id'])) {
            $backlink_append = "?client_id=$client_id";
        } else {
            $backlink_append = '';
        }
        echo "<h1 class='text-secondary pt-5' style='text-align: center'>There is no Quote here<br><small><a href='quotes.php$backlink_append'><i class='fas fa-arrow-left me-2'></i>Back to Quotes</a></small></h1>";
        require_once "../includes/footer.php";

        exit();
    }

    $row = mysqli_fetch_assoc($sql);
    $quote_id = intval($row['quote_id']);
    $quote_prefix = escapeHtml($row['quote_prefix']);
    $quote_number = intval($row['quote_number']);
    $quote_scope = escapeHtml($row['quote_scope']);
    $quote_status = escapeHtml($row['quote_status']);
    $quote_date = escapeHtml($row['quote_date']);
    $quote_expire = escapeHtml($row['quote_expire']);
    $quote_amount = floatval($row['quote_amount']);
    $quote_discount = floatval($row['quote_discount_amount']);
    $quote_currency_code = escapeHtml($row['quote_currency_code']);
    $quote_note = escapeHtml($row['quote_note']);
    $quote_url_key = escapeHtml($row['quote_url_key']);
    $quote_created_at = escapeHtml($row['quote_created_at']);
    $category_id = intval($row['quote_category_id']);
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

    // Override Tab Title // No Sanitizing needed as this var will only be used in the tab title
    $tab_title = $row['client_name'];
    $page_title = "{$row['quote_prefix']}{$row['quote_number']}";

    $sql = mysqli_query($mysqli, "SELECT company_address, company_city, company_country, company_email, settings.company_id,
        company_logo, company_name, company_phone, company_phone_country_code, company_state,
        company_website, company_zip FROM companies, settings WHERE companies.company_id = settings.company_id AND companies.company_id = 1");
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
    $company_logo = escapeHtml($row['company_logo']);

    // Must use the same rule as the Send Email picker in
    // modals/quote/quote_email.php, or the button offers a modal that then
    // reports there is nobody to send to.
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(contact_id) AS emailable_contacts FROM contacts
        WHERE contact_client_id = $client_id
        AND contact_archived_at IS NULL
        AND contact_email IS NOT NULL
        AND contact_email != ''
        " . documentContactFilterSql('quote')));
    $emailable_contacts = intval($row['emailable_contacts']);

    $sql_history = mysqli_query($mysqli, "SELECT history_created_at, history_description, history_status FROM history WHERE history_quote_id = $quote_id ORDER BY history_id DESC");

    //Set Badge color based off of quote status
    if ($quote_status == "Sent") {
        $quote_badge_color = "warning";
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
    $json_products = getProductsForAutocomplete($mysqli);

    // Quote File Attachments
    $sql_quote_files = mysqli_query(
        $mysqli,
        "SELECT file_reference_name, file_name, file_created_at FROM quote_files LEFT JOIN files ON quote_files.file_id = files.file_id WHERE quote_id = $quote_id"
    );

?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="quotes.php">All Quotes</a>
        </li>
        <li class="breadcrumb-item">
            <a href="quotes.php?client_id=<?= $client_id ?>"><?= $client_name ?> Quotes</a>
        </li>
        <li class="breadcrumb-item active"><?= "$quote_prefix$quote_number" ?></li>
    </ol>

    <div class="card mb-3">
        <div class="card-header d-print-none">

            <?php if (lookupUserPermission("module_sales") >= 2) { ?>
                <div class="row">

                <div class="col-8">
                <?php if ($quote_status == 'Draft') { ?>
                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-fw fa-paper-plane me-2"></i>Send
                    </button>
                    <div class="dropdown-menu">
                        <?php if (!empty($config_smtp_provider) && $emailable_contacts > 0) { ?>
                            <button type="submit" class="dropdown-item confirm-link" form="quickSendQuote"
                                data-confirm-title="Send this quote now?"
                                data-confirm-text="It goes to the default contacts without opening the picker."
                                data-confirm-button="Send"
                                name="quote_id" value="<?= $quote_id ?>">
                                <i class="fas fa-fw fa-bolt me-2"></i>Quick Send
                            </button>
                            <a class="dropdown-item ajax-modal" href="#"
                                data-modal-url="modals/quote/quote_email.php?quote_id=<?= $quote_id ?>">
                                <i class="fas fa-fw fa-paper-plane me-2"></i>Send Email<span class="text-muted">...</span>
                            </a>
                            <div class="dropdown-divider"></div>
                        <?php } ?>
                        <a class="dropdown-item ajax-modal" href="#"
                            data-modal-url="modals/quote/quote_mark_sent.php?quote_id=<?= $quote_id ?>">
                            <i class="fas fa-fw fa-check me-2"></i>Mark Sent
                        </a>
                    </div>
                <?php } ?>

                <?php if ($quote_status == 'Sent' || $quote_status == 'Viewed') { ?>
                    <a class="btn btn-primary confirm-link" href="post.php?accept_quote=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-thumbs-up me-2"></i>Accept
                    </a>
                    <a class="btn btn-default confirm-link" href="post.php?decline_quote=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="fas fa-thumbs-down me-2"></i>Decline
                    </a>
                <?php } ?>

                <?php if ($quote_status == 'Accepted') { ?>
                    <div class="btn-group fix-quote-dropdown">
                        <button type="button" class="btn btn-primary ajax-modal"
                            data-modal-url="modals/quote/quote_to_invoice.php?quote_id=<?= $quote_id ?>">
                            <i class="fas fa-check me-2"></i>Invoice
                        </button>
                        <button type="button" class="btn btn-primary dropdown-toggle dropdown-icon" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="post.php?mark_quote_invoiced=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                <i class="fas fa-fw fa-check me-2"></i>Mark Invoiced
                            </a>
                        </div>
                    </div>
                <?php } ?>

                </div>

                <div class="col-4">
                    <div class="dropdown dropstart text-center float-end">
                        <button class="btn btn-secondary" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item ajax-modal" href="#"
                                data-modal-url="modals/quote/quote_edit.php?id=<?= $quote_id ?>">
                                <i class="fa fa-fw fa-edit text-secondary me-2"></i>Edit
                            </a>
                            <?php if (lookupUserPermission("module_sales") >= 2) { ?>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/quote/quote_copy.php?id=<?= $quote_id ?>">
                                    <i class="fa fa-fw fa-copy text-secondary me-2"></i>Copy
                                </a>
                            <?php } ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" onclick="window.print();">
                                <i class="fa fa-fw fa-print text-secondary me-2"></i>Print
                            </a>
                            <a class="dropdown-item" href="post.php?export_quote_pdf=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" target="_blank">
                                <i class="fa fa-fw fa-download text-secondary me-2"></i>Download PDF
                            </a>
                            <?php if (!empty($config_smtp_provider) && $emailable_contacts > 0) { ?>
                                <button type="submit" class="dropdown-item confirm-link" form="quickSendQuote"
                                    data-confirm-title="Send this quote now?"
                                    data-confirm-text="It goes to the default contacts without opening the picker."
                                    data-confirm-button="Send"
                                    name="quote_id" value="<?= $quote_id ?>">
                                    <i class="fa fa-fw fa-bolt text-secondary me-2"></i>Quick Send
                                </button>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/quote/quote_email.php?quote_id=<?= $quote_id ?>">
                                    <i class="fa fa-fw fa-paper-plane text-secondary me-2"></i>Send Email<span class="text-muted">...</span>
                                </a>
                            <?php } ?>
                            <a class="dropdown-item clipboardjs" href="#" data-clipboard-text="https://<?= $config_base_url ?>/guest/guest_view_quote.php?quote_id=<?= "$quote_id&url_key=$quote_url_key" ?>">
                                <i class="fa fa-fw fa-copy text-secondary me-2"></i>Copy Guest URL
                            </a>
                            <?php if (lookupUserPermission("module_sales") >= 3) { ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_quote=<?= $quote_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fa fa-fw fa-times me-2"></i>Delete
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
                <?php if (file_exists("../uploads/settings/$company_logo")) { ?>
                <div class="col-sm-2">
                    <img class="img-fluid" src="<?= "../uploads/settings/$company_logo" ?>" alt="Company logo">
                </div>
                <?php } ?>
                <div class="col-sm-6 <?php if (!file_exists("../uploads/settings/$company_logo")) { echo "col-sm-8"; } ?>">
                    <ul class="list-unstyled">
                        <li><h4><strong><?= $company_name ?></strong></h4></li>
                        <li><?= formatAddress($company_address, $company_city, $company_state, $company_zip, $company_country, '<br>') ?></li>
                        <li><?= "$company_email | $company_phone" ?></li>
                        <li><?= $company_website ?></li>
                    </ul>
                </div>

                <div class="col-sm-4">
                    <h3 class="text-end"><strong>QUOTE</strong></h3>
                    <h5 class="badge text-bg-<?= $quote_badge_color ?> p-2 float-end">
                        <?= "$quote_status" ?>
                    </h5>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th>Quote #:</th>
                            <td class="text-end"><?= "$quote_prefix$quote_number" ?></td>
                        </tr>
                        <tr>
                            <th>Date:</th>
                            <td class="text-end"><?= $quote_date ?></td>
                        </tr>
                        <tr>
                            <th>Expires:</th>
                            <td class="text-end"><?= $quote_expire ?></td>
                        </tr>
                    </table>
                </div>

            </div>
            <div class="row mb-3 bg-light p-3">
                <div class="col">
                    <h6><strong>To:</strong></h6>
                    <ul class="list-unstyled mb-0">
                        <li><?= $client_name ?></li>
                        <li><?= formatAddress($location_address, $location_city, $location_state, $location_zip, $location_country, '<br>') ?></li>
                        <li><?= "$contact_email | $contact_phone $contact_extension" ?></li>
                    </ul>
                </div>
            </div>

            <?php $sql_items = mysqli_query($mysqli, "SELECT item_created_at, item_description, item_id, item_name, item_price, item_quantity, item_tax,
                item_tax_id, item_total FROM quote_items WHERE item_quote_id = $quote_id ORDER BY item_order ASC"); ?>

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
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Tax</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php

                                    $total_tax = 0.00;
                                    $sub_total = 0.00;

                                    while ($row = mysqli_fetch_assoc($sql_items)) {
                                        $item_id = intval($row['item_id']);
                                        $item_name = escapeHtml($row['item_name']);
                                        $item_description = escapeHtml($row['item_description']);
                                        $item_quantity = floatval($row['item_quantity']);
                                        $item_price = floatval($row['item_price']);
                                        $item_tax = floatval($row['item_tax']);
                                        $item_total = floatval($row['item_total']);
                                        $item_created_at = escapeHtml($row['item_created_at']);
                                        $tax_id = intval($row['item_tax_id']);
                                        $total_tax = $item_tax + $total_tax;
                                        $sub_total = $item_price * $item_quantity + $sub_total;
                                        ?>

                                        <tr data-item-id="<?= $item_id ?>">
                                            <td class="d-print-none">
                                                <?php if ($quote_status !== "Invoiced" && $quote_status !== "Accepted" && $quote_status !== "Declined" && lookupUserPermission("module_sales") >= 2) { ?>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-link drag-handle">
                                                            <i class="fas fa-bars text-muted"></i>
                                                        </button>

                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                                                                <i class="fas fa-ellipsis-v"></i>
                                                            </button>
                                                            <div class="dropdown-menu">
                                                                <a class="dropdown-item ajax-modal" href="#"
                                                                    data-modal-url="modals/quote/quote_item_edit.php?id=<?= $item_id ?>">
                                                                    <i class="fa fa-fw fa-edit me-2"></i>Edit
                                                                </a>
                                                                <div class="dropdown-divider"></div>
                                                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_quote_item=<?= $item_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                    <i class="fa fa-fw fa-trash me-2"></i>Delete
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                            <td><?= $item_name ?></td>
                                            <td><?= nl2br($item_description) ?></td>
                                            <td class="text-center"><?= number_format($item_quantity, 2) ?></td>
                                            <td class="text-end"><?= numfmt_format_currency($currency_format, $item_price, $quote_currency_code) ?></td>
                                            <td class="text-end"><?= numfmt_format_currency($currency_format, $item_tax, $quote_currency_code) ?></td>
                                            <td class="text-end"><?= numfmt_format_currency($currency_format, $item_total, $quote_currency_code) ?></td>
                                        </tr>

                                    <?php

                                    }

                                    ?>

                                    <tr class="d-print-none" <?php if ($quote_status == "Invoiced" || $quote_status == "Accepted" || $quote_status == "Declined" || lookupUserPermission("module_sales") <= 1) {
                                                                    echo "hidden";
                                                                } ?>>
                                        <form action="post.php" method="post" autocomplete="off">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="quote_id" value="<?= $quote_id ?>">
                                            <input type="hidden" id="product_id" name="product_id" value="0">
                                            <input type="hidden" name="item_order" value="<?php
                                            //find largest order number and add 1
                                            $sql = mysqli_query($mysqli, "SELECT MAX(item_order) AS item_order FROM quote_items WHERE item_quote_id = $quote_id");
                                            $row = mysqli_fetch_assoc($sql);
                                            $item_order = intval($row['item_order']) + 1;
                                            echo $item_order;
                                            ?>">
                                            <td></td>
                                            <td>
                                                <input type="text" class="form-control" name="name" id="name" placeholder="Item" maxlength="200" required>
                                            </td>
                                            <td>
                                                <textarea class="form-control" rows="2" name="description" id="desc" placeholder="Enter a Description"></textarea>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" id="qty" style="text-align: center;" name="qty" placeholder="Qty">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" inputmode="decimal" pattern="-?[0-9]*\.?[0-9]{0,2}" id="price" style="text-align: right;" name="price" placeholder="Price (<?= $quote_currency_code ?>)">
                                            </td>
                                            <td>
                                                <select class="form-select select2" id="tax" name="tax_id" required>
                                                    <option value="0">No Tax</option>
                                                    <?php

                                                    $taxes_sql = mysqli_query($mysqli, "SELECT tax_id, tax_name, tax_percent FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_name ASC");
                                                    while ($row = mysqli_fetch_assoc($taxes_sql)) {
                                                        $tax_id = intval($row['tax_id']);
                                                        $tax_name = escapeHtml($row['tax_name']);
                                                        $tax_percent = floatval($row['tax_percent']);
                                                    ?>
                                                        <option value="<?= $tax_id ?>"><?= "$tax_name $tax_percent%" ?></option>

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
                            <span class="d-print-none" data-note-status-for="quoteNotes"></span>
                        </div>
                        <div class="card-body p-2">
<?php if (lookupUserPermission("module_sales") >= 2) { ?>
                            <textarea class="form-control itflow-inline-note d-print-none" rows="6"
                                id="quoteNotes"
                                placeholder="Enter some notes"
                                data-endpoint="quote_set_notes"
                                data-id-field="quote_id"
                                data-id="<?= $quote_id ?>"
                                data-csrf="<?= $_SESSION['csrf_token'] ?>"><?= $quote_note ?></textarea>
<?php } else { ?>
                            <div class="d-print-none"><?= nl2br($quote_note) ?></div>
<?php } ?>
                            <!-- Printed output must be plain text, not a form control -->
                            <div class="d-none d-print-block"><?= nl2br($quote_note) ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-3 offset-sm-2">

                    <table class="table table-hover mb-0">
                        <tbody>
                            <tr>
                                <td>Subtotal:</td>
                                <td class="text-end"><?= numfmt_format_currency($currency_format, $sub_total, $quote_currency_code) ?></td>
                            </tr>
                            <?php if ($quote_discount > 0) { ?>
                                <tr>
                                    <td>Discount:</td>
                                    <td class="text-end">-<?= numfmt_format_currency($currency_format, $quote_discount, $quote_currency_code) ?></td>
                                </tr>
                            <?php } ?>
                            <?php if ($total_tax > 0) { ?>
                                <tr>
                                    <td>Tax:</td>
                                    <td class="text-end"><?= numfmt_format_currency($currency_format, $total_tax, $quote_currency_code) ?></td>
                                </tr>
                            <?php } ?>
                            <tr class="h5 text-bold">
                                <td>Total:</td>
                                <td class="text-end"><?= numfmt_format_currency($currency_format, $quote_amount, $quote_currency_code) ?></td>
                            </tr>
                        </tbody>
                    </table>

                </div>
            </div>

            <hr class="d-none d-print-block mt-5">

            <div class="d-none d-print-block text-center"><?= nl2br(escapeHtml($config_quote_footer)) ?></div>
        </div>
    </div>

    <?php if (mysqli_num_rows($sql_quote_files) > 0) { ?>
        <div class="row mb-3">
        <div class="col-sm d-print-none">
            <div class="card">
                <div class="card-header text-bold">
                    <i class="fa fa-paperclip me-2"></i>Attachments
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-tool" data-lte-toggle="card-remove">
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

                        while ($quote_file = mysqli_fetch_assoc($sql_quote_files)) {
                            $name = escapeHtml($quote_file['file_name']);
                            $ref_name = escapeHtml($quote_file['file_reference_name']);
                            $created = escapeHtml($quote_file['file_created_at']);

                            ?>
                            <tr>
                                <td><a target="_blank" href="../uploads/clients/<?= $client_id ?>/<?= $ref_name ?>"><?= $name ?></a></td>
                                <td><?= $created ?></td>
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
                    <i class="fa fa-history me-2"></i>History
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-tool" data-lte-toggle="card-remove">
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
                                $history_created_at = escapeHtml($row['history_created_at']);
                                $history_status = escapeHtml($row['history_status']);
                                $history_description = nl2br(escapeHtml($row['history_description']));

                            ?>
                                <tr>
                                    <td><?= $history_created_at ?></td>
                                    <td><?= $history_status ?></td>
                                    <td><?= $history_description ?></td>
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

        <?php if (lookupUserPermission("module_sales") >= 2 && !empty($config_smtp_provider) && $emailable_contacts > 0) { ?>
            <?php
            /*
             * One hidden form for the page, targeted by the Quick Send buttons via
             * their form="" attribute, so a button can sit inside a dropdown
             * without needing a form of its own. The button carries the id as its
             * own name/value, which a submit button contributes to the submission.
             *
             * Must stay inside this block - $emailable_contacts is only set on the
             * path where the document was found.
             */
            ?>
            <form id="quickSendQuote" action="post.php" method="post" class="d-none">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="email_quote" value="1">
                <input type="hidden" name="quick_send" value="1">
            </form>
        <?php } ?>

<?php
}

?>

<script src="/js/inline_notes.js"></script>

<?php
require_once "../includes/footer.php";

?>

<!-- Product autocomplete for the add-item row -->
<script src="/js/product_autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    initProductAutocomplete(<?= $json_products ?? '[]' ?>);
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

        itflowPostForm('ajax.php', {
            update_quote_items_order: true,
            csrf_token: '<?= $_SESSION['csrf_token'] ?>',
            quote_id: <?= $quote_id ?>,
            positions: positions
        });
    }
});
</script>
<link rel="stylesheet" href="css/quote_dropdowns_fix.css">
