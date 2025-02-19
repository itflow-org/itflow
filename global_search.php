<?php

require_once "includes/inc_all.php";

// Initialize the HTML Purifier to prevent XSS
require "plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (isset($_GET['query'])) {

    $query = sanitizeInput($_GET['query']);

    $phone_query = preg_replace("/[^0-9]/", '', $query);
    if (empty($phone_query)) {
        $phone_query = $query;
    }

    $ticket_num_query = str_replace("$config_ticket_prefix", "", "$query");

    $sql_clients = mysqli_query($mysqli, "SELECT * FROM clients
        LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
        WHERE client_archived_at IS NULL
            AND client_name LIKE '%$query%'
            $access_permission_query
        ORDER BY client_id DESC LIMIT 5"
    );

    $sql_contacts = mysqli_query($mysqli, "SELECT * FROM contacts 
        LEFT JOIN clients ON client_id = contact_client_id
        WHERE contact_archived_at IS NULL
            AND (contact_name LIKE '%$query%'
            OR contact_title LIKE '%$query%'
            OR contact_email LIKE '%$query%'
            OR contact_phone LIKE '%$phone_query%'
            OR contact_mobile LIKE '%$phone_query%')
            $access_permission_query
        ORDER BY contact_id DESC LIMIT 5"
    );

    $sql_vendors = mysqli_query($mysqli, "SELECT * FROM vendors
        LEFT JOIN clients ON vendor_client_id = client_id
        WHERE vendor_archived_at IS NULL
            AND vendor_template = 0
            AND (vendor_name LIKE '%$query%' OR vendor_phone LIKE '%$phone_query%')
            $access_permission_query
        ORDER BY vendor_id DESC LIMIT 5"
    );

    $sql_domains = mysqli_query($mysqli, "SELECT * FROM domains
        LEFT JOIN clients ON domain_client_id = client_id
        WHERE domain_archived_at IS NULL
            AND domain_name LIKE '%$query%'
            $access_permission_query
        ORDER BY domain_id DESC LIMIT 5"
    );

    $sql_products = mysqli_query($mysqli, "SELECT * FROM products
        WHERE product_archived_at IS NULL
            AND product_name LIKE '%$query%'
        ORDER BY product_id DESC LIMIT 5"
    );

    $sql_documents = mysqli_query($mysqli, "SELECT * FROM documents
        LEFT JOIN clients on document_client_id = clients.client_id
        WHERE document_archived_at IS NULL
            AND MATCH(document_content_raw) AGAINST ('$query')
            $access_permission_query
        ORDER BY document_id DESC LIMIT 5"
    );

    $sql_files = mysqli_query($mysqli, "SELECT * FROM files
        LEFT JOIN clients ON file_client_id = client_id
        LEFT JOIN folders ON folder_id = file_folder_id
        WHERE file_archived_at IS NULL
            AND (file_name LIKE '%$query%'
            OR file_description LIKE '%$query%')
            $access_permission_query
        ORDER BY file_id DESC LIMIT 5"
    );

    $sql_tickets = mysqli_query($mysqli, "SELECT * FROM tickets
        LEFT JOIN clients on tickets.ticket_client_id = clients.client_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        WHERE ticket_archived_at IS NULL
            AND (ticket_subject LIKE '%$query%'
            OR ticket_number = '$ticket_num_query')
            $access_permission_query
        ORDER BY ticket_id DESC LIMIT 5"
    );

    $sql_recurring_tickets = mysqli_query($mysqli, "SELECT * FROM scheduled_tickets
        LEFT JOIN clients ON scheduled_ticket_client_id = client_id
        WHERE scheduled_ticket_subject LIKE '%$query%'
            OR scheduled_ticket_details LIKE '%$query%'
            $access_permission_query
        ORDER BY scheduled_ticket_id DESC LIMIT 5"
    );

    $sql_logins = mysqli_query($mysqli, "SELECT * FROM logins
        LEFT JOIN contacts ON login_contact_id = contact_id
        LEFT JOIN clients ON login_client_id = client_id
        WHERE login_archived_at IS NULL
            AND (login_name LIKE '%$query%' OR login_description LIKE '%$query%')
            $access_permission_query
        ORDER BY login_id DESC LIMIT 5"
    );

    $sql_invoices = mysqli_query($mysqli, "SELECT * FROM invoices
        LEFT JOIN clients ON invoice_client_id = client_id
        LEFT JOIN categories ON invoice_category_id = category_id
        WHERE invoice_archived_at IS NULL
            AND (CONCAT(invoice_prefix,invoice_number) LIKE '%$query%' OR invoice_scope LIKE '%$query%')
            $access_permission_query
        ORDER BY invoice_number DESC LIMIT 5"
    );

    $sql_assets = mysqli_query($mysqli,"SELECT * FROM assets
        LEFT JOIN contacts ON asset_contact_id = contact_id
        LEFT JOIN locations ON asset_location_id = location_id
        LEFT JOIN clients ON asset_client_id = client_id
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        WHERE asset_archived_at IS NULL
            AND (asset_name LIKE '%$query%' OR asset_description LIKE '%$query%' OR asset_type LIKE '%$query%' OR asset_make LIKE '%$query%' OR asset_model LIKE '%$query%' OR asset_serial LIKE '%$query%' OR asset_os LIKE '%$query%' OR interface_ip LIKE '%$query%' OR interface_nat_ip LIKE '%$query%' OR interface_mac LIKE '%$query%' OR asset_status LIKE '%$query%')
            $access_permission_query
        ORDER BY asset_name DESC LIMIT 5"
    );

    $sql_ticket_replies = mysqli_query($mysqli,"SELECT * FROM ticket_replies
        LEFT JOIN tickets ON ticket_reply_ticket_id = ticket_id
        LEFT JOIN clients ON ticket_client_id = client_id
        WHERE ticket_reply_archived_at IS NULL
            AND (ticket_reply LIKE '%$query%')
            $access_permission_query
        ORDER BY ticket_id DESC, ticket_reply_id ASC LIMIT 20"
    );

    $q = nullable_htmlentities($_GET['query']);

    ?>


    <div class="col-sm-12">
        <div class="card card-body mb-3">
            <h4 class="text-center"><i class="fas fa-fw fa-search mr-2"></i>Global Search</h4>
        </div>
    </div>


        <?php if (mysqli_num_rows($sql_clients) > 0) { ?>

            <!-- Clients-->

            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-users mr-2"></i>Clients</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_clients)) {
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);
                                $location_phone = formatPhoneNumber($row['location_phone']);
                                $client_website = nullable_htmlentities($row['client_website']);

                                ?>
                                <tr>
                                    <td><a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                                    <td><?php echo $location_phone; ?></td>
                                </tr>

                            <?php } ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_contacts) > 0) { ?>

            <!-- Contacts-->

            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-users mr-2"></i>Contacts</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Cell</th>
                                <th>Client</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_contacts)) {
                                $contact_id = intval($row['contact_id']);
                                $contact_name = nullable_htmlentities($row['contact_name']);
                                $contact_title = nullable_htmlentities($row['contact_title']);
                                $contact_phone = formatPhoneNumber($row['contact_phone']);
                                $contact_extension = nullable_htmlentities($row['contact_extension']);
                                $contact_mobile = formatPhoneNumber($row['contact_mobile']);
                                $contact_email = nullable_htmlentities($row['contact_email']);
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);
                                $contact_department = nullable_htmlentities($row['contact_department']);

                                ?>
                                <tr>
                                    <td><a href="client_contact_details.php?client_id=<?php echo $client_id; ?>&contact_id=<?php echo $contact_id; ?>"><?php echo $contact_name; ?></a>
                                        <br><small class="text-secondary"><?php echo $contact_title; ?></small>
                                    </td>
                                    <td><?php echo $contact_email; ?></td>
                                    <td><?php echo "$contact_phone $contact_extension"; ?></td>
                                    <td><?php echo $contact_mobile; ?></td>
                                    <td><a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                                </tr>

                            <?php } ?>


                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_vendors) > 0) { ?>

            <!-- Vendors -->
            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-building mr-2"></i>Vendors</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Phone</th>
                                <th>Client</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_vendors)) {
                                $vendor_name = nullable_htmlentities($row['vendor_name']);
                                $vendor_description = nullable_htmlentities($row['vendor_description']);
                                $vendor_phone = formatPhoneNumber($row['vendor_phone']);
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);

                                ?>
                                <tr>
                                    <td><a href="vendors.php?q=<?php echo $q ?>"><?php echo $vendor_name; ?></a></td>
                                    <td><?php echo $vendor_description; ?></td>
                                    <td><?php echo $vendor_phone; ?></td>
                                    <td><a href="client_vendors.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                                </tr>

                            <?php } ?>


                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_domains) > 0) { ?>

            <!-- Domains -->
            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-globe mr-2"></i>Domains</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Expiry</th>
                                <th>Client</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_domains)) {
                                $domain_name = nullable_htmlentities($row['domain_name']);
                                $domain_expiry = nullable_htmlentities($row['domain_expire']);
                                $domain_id = intval($row['domain_id']);
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);

                                ?>
                                <tr>
                                    <td><a href="client_domains.php?client_id=<?php echo $client_id; ?>&domain_id=<?php echo $domain_id; ?>"><?php echo $domain_name; ?></a>
                                    <td><?php echo $domain_expiry; ?></td>
                                    <td><a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                                </tr>

                            <?php } ?>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_products) > 0) { ?>

            <!-- Products -->
            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-box mr-2"></i>Products</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_products)) {
                                $product_name = nullable_htmlentities($row['product_name']);
                                $product_description = nullable_htmlentities($row['product_description']);
                                ?>
                                <tr>
                                    <td><a href="products.php?q=<?php echo $q ?>"><?php echo $product_name; ?></a></td>
                                    <td><?php echo $product_description; ?></td>
                                </tr>

                            <?php } ?>


                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_documents) > 0) { ?>

            <!-- Documents -->
            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-file-alt mr-2"></i>Documents</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Document</th>
                                <th>Client</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_documents)) {
                                $document_id = intval($row['document_id']);
                                $document_name = nullable_htmlentities($row['document_name']);
                                $client_id = intval($row['document_client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);

                                ?>
                                <tr>
                                    <td><a href="client_document_details.php?client_id=<?php echo $client_id ?>&document_id=<?php echo $document_id; ?>"><?php echo $document_name; ?></a></td>
                                    <td>
                                        <a href="client_documents.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
                                    </td>
                                </tr>

                            <?php } ?>


                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_files) > 0) { ?>

            <!-- Files -->
            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-paperclip mr-2"></i>Files</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Description</th>
                                <th>Client</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_files)) {
                                $file_id = intval($row['file_id']);
                                $file_name = nullable_htmlentities($row['file_name']);
                                $file_reference_name = nullable_htmlentities($row['file_reference_name']);
                                $file_description = nullable_htmlentities($row['file_description']);
                                $folder_id = intval($row['folder_id']);
                                $folder_name = nullable_htmlentities($row['folder_name']);
                                $client_id = intval($row['file_client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);

                                ?>
                                <tr>
                                    <td><a href="uploads/clients/<?php echo $client_id; ?>/<?php echo $file_reference_name; ?>" download="<?php echo $file_name; ?>"><?php echo "$folder_name/$file_name"; ?></a></td>
                                    <td><?php echo $file_description; ?></td>
                                    <td>
                                        <a href="client_files.php?client_id=<?php echo $client_id; ?>&folder_id=<?php echo $folder_id; ?>"><?php echo $client_name; ?></a>
                                    </td>
                                </tr>

                            <?php } ?>


                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_tickets) > 0) { ?>

            <!-- Tickets -->
            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-life-ring mr-2"></i>Tickets</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Client</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_tickets)) {
                                $ticket_id = intval($row['ticket_id']);
                                $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                                $ticket_number = intval($row['ticket_number']);
                                $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                                $ticket_status_name = nullable_htmlentities($row['ticket_status_name']);
                                $client_name = nullable_htmlentities($row['client_name']);
                                $client_id = intval($row['ticket_client_id']);

                                ?>
                                <tr>
                                    <td><a href="ticket.php?ticket_id=<?php echo $ticket_id ?>"><?php echo $ticket_prefix . $ticket_number; ?></a></td>
                                    <td><?php echo $ticket_subject; ?></td>
                                    <td><?php echo $ticket_status_name; ?></td>
                                    <td><a href="tickets.php?client_id=<?php echo $client_id ?>"><?php echo $client_name; ?></a></td>
                                </tr>

                            <?php } ?>


                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>


        <?php if (mysqli_num_rows($sql_recurring_tickets) > 0) { ?>

            <!-- Recurring Tickets -->
            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-undo-alt mr-2"></i>Recurring Tickets</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Frequency</th>
                                <th>Next</th>
                                <th>Client</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_recurring_tickets)) {
                                $scheduled_ticket_id = intval($row['scheduled_ticket_id']);
                                $scheduled_ticket_subject = nullable_htmlentities($row['scheduled_ticket_subject']);
                                $scheduled_ticket_frequency = nullable_htmlentities($row['scheduled_ticket_frequency']);
                                $scheduled_ticket_next_run = nullable_htmlentities($row['scheduled_ticket_next_run']);
                                $client_name = nullable_htmlentities($row['client_name']);
                                $client_id = intval($row['client_id']);

                                ?>
                                <tr>
                                    <td><a href="recurring_tickets.php"><?php echo $scheduled_ticket_subject; ?></a></td>
                                    <td><?php echo $scheduled_ticket_frequency; ?></td>
                                    <td><?php echo $scheduled_ticket_next_run; ?></td>
                                    <td><a href="recurring_tickets.php?client_id=<?php echo $client_id ?>"><?php echo $client_name; ?></a></td>
                                </tr>

                            <?php } ?>


                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>


        <?php if (mysqli_num_rows($sql_logins) > 0) { ?>

            <!-- Logins -->
            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-key mr-2"></i>Credentials</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Username</th>
                                <th>Password</th>
                                <th>Client</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_logins)) {
                                $login_name = nullable_htmlentities($row['login_name']);
                                $login_description = nullable_htmlentities($row['login_description']);
                                $login_client_id = intval($row['login_client_id']);
                                $login_username = nullable_htmlentities(decryptLoginEntry($row['login_username']));
                                $login_password = nullable_htmlentities(decryptLoginEntry($row['login_password']));
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);

                                ?>
                                <tr>
                                    <td><a href="client_logins.php?client_id=<?php echo $login_client_id ?>&q=<?php echo $q ?>"><?php echo $login_name; ?></a></td>
                                    <td><?php echo $login_description; ?></td>
                                    <td><?php echo $login_username; ?></td>
                                    <td><a tabindex="0" class="btn btn-sm" data-toggle="popover" data-trigger="focus" data-placement="left" data-content="<?php echo $login_password; ?>"><i class="far fa-eye text-secondary"></i></a><button class="btn btn-sm clipboardjs" data-clipboard-text="<?php echo $login_password; ?>"><i class="far fa-copy text-secondary"></i></button>
                                    </td>
                                    <td><a href="client_logins.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                                </tr>

                            <?php } ?>


                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_invoices) > 0) { ?>

            <!-- Contacts-->

            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-file-invoice mr-2"></i>Invoices</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Number</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Client</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_invoices)) {
                                $invoice_id = intval($row['invoice_id']);
                                $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
                                $invoice_number = intval($row['invoice_number']);
                                $invoice_amount = floatval($row['invoice_amount']);
                                $invoice_currency_code = nullable_htmlentities($row['invoice_currency_code']);
                                $invoice_status = nullable_htmlentities($row['invoice_status']);
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);

                                ?>
                                <tr>
                                    <td><a href="invoice.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a></td>
                                    <td><?php echo $invoice_status; ?></td>
                                    <td><?php echo numfmt_format_currency($currency_format, $invoice_amount, $invoice_currency_code); ?></td>
                                    <td><a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                                </tr>

                            <?php } ?>


                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_assets) > 0) { ?>

            <!-- Contacts-->

            <div class="col-sm-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-desktop mr-2"></i>Assets</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-borderless">
                            <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Type</th>
                                <th>Serial</th>
                                <th>Client</th>
                                <th>Assigned</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql_assets)) {
                                $asset_id = intval($row['asset_id']);
                                $asset_type = nullable_htmlentities($row['asset_type']);
                                $asset_name = nullable_htmlentities($row['asset_name']);
                                $asset_description = nullable_htmlentities($row['asset_description']);
                                if (empty($asset_description)) {
                                    $asset_description_display = "-";
                                } else {
                                    $asset_description_display = $asset_description;
                                }
                                $asset_make = nullable_htmlentities($row['asset_make']);
                                $asset_model = nullable_htmlentities($row['asset_model']);
                                $asset_serial = nullable_htmlentities($row['asset_serial']);
                                if (empty($asset_serial)) {
                                    $asset_serial_display = "-";
                                } else {
                                    $asset_serial_display = $asset_serial;
                                }
                                $asset_mac = nullable_htmlentities($row['asset_mac']);
                                $asset_uri = nullable_htmlentities($row['asset_uri']);
                                $asset_status = nullable_htmlentities($row['asset_status']);
                                $asset_created_at = nullable_htmlentities($row['asset_created_at']);
                                $asset_location_id = intval($row['asset_location_id']);
                                $asset_contact_id = intval($row['asset_contact_id']);
                                $device_icon = getAssetIcon($asset_type);

                                $contact_name = nullable_htmlentities($row['contact_name']);
                                $contact_id = nullable_htmlentities($row['contact_id']);
                                if (empty($contact_name)) {
                                    $contact_name_display = "-";
                                }else{
                                    $contact_name_display = "<a href='client_contact_details.php?client_id=$client_id&contact_id=$contact_id'>$contact_name</a>";
                                }
                                $contact_archived_at = nullable_htmlentities($row['contact_archived_at']);
                                if (empty($contact_archived_at)) {
                                    $contact_archived_display = "";
                                } else {
                                    $contact_archived_display = "Archived - ";
                                }

                                $client_id = intval($row['asset_client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);

                                ?>
                                <tr>
                                    <td>
                                        <i class="fa fa-fw text-secondary fa-<?php echo $device_icon; ?> mr-2"></i><?php echo $asset_name; ?>
                                        <?php if(!empty($asset_uri)){ ?>
                                        <a href="<?php echo $asset_uri; ?>" target="_blank"><i class="fas fa-fw fa-external-link-alt ml-2"></i></a>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo $asset_type; ?></td>
                                    <td><?php echo $asset_serial_display; ?></td>
                                    <td><a href="client_assets.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                                    <td><?php echo $contact_name_display; ?></td>
                                </tr>

                            <?php } ?>


                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php } ?>

        <?php if (mysqli_num_rows($sql_ticket_replies) > 0) { ?>

            <!-- Ticket Replies -->

            <div class="col-sm-6">

                <div class="card">
                    <div class="card-header">
                        <h6 class="mt-1"><i class="fas fa-fw fa-reply mr-2"></i>Ticket Replies</h6>
                    </div>
                    <div class="card-body">

                    <?php
                    $last_ticket_id = null; // Track the last ticket ID processed

                    while ($row = mysqli_fetch_array($sql_ticket_replies)) {
                        $ticket_id = intval($row['ticket_id']);

                        // Only output the ticket header if we're at a new ticket
                        if ($ticket_id !== $last_ticket_id) {
                            if ($last_ticket_id !== null) {
                                // Close the previous ticket's card (except for the very first ticket)
                                echo '</div></div>';
                            }

                            $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
                            $ticket_number = intval($row['ticket_number']);
                            $ticket_subject = nullable_htmlentities($row['ticket_subject']);
                            $client_id = intval($row['ticket_client_id']);
                            $client_name = nullable_htmlentities($row['client_name']);

                            // Output the ticket header
                            ?>
                            <div class="card card-outline">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <?php echo "$client_name - $ticket_prefix$ticket_number - $ticket_subject"; ?>
                                    </h3>
                                    <div class="card-tools">
                                        <a href="ticket.php?ticket_id=<?php echo $ticket_id; ?>" target="_blank">Open <i class="fa fa-fw fa-external-link-alt"></i></a>
                                    </div>
                                </div>
                                <div class="card-body prettyContent">
                            <?php
                        }

                        $ticket_reply = $purifier->purify($row['ticket_reply']);

                        // Output the ticket reply
                        ?>
                        <div class="media">
                            <i class="fas fa-fw fa-reply mr-3"></i>
                            <div class="media-body">
                                <?php echo $ticket_reply; ?>
                            </div>
                        </div>
                        <hr>
                        <?php

                        $last_ticket_id = $ticket_id; // Update the last ticket ID
                    }

                    if ($last_ticket_id !== null) {
                        // Close the last ticket's card
                        echo '</div></div>';
                    }
                    ?>

                    </div>

                </div>

            </div>

        <?php } ?>

    </div>

<?php

}

require_once "includes/footer.php";

?>

<script src="js/pretty_content.js"></script>
