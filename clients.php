<?php

// Default Column Sortby/Order Filter
$sb = "client_accessed_at";
$o = "DESC";

require_once("inc_all.php");

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM clients
    LEFT JOIN contacts ON clients.primary_contact = contacts.contact_id AND contact_archived_at IS NULL
    LEFT JOIN locations ON clients.primary_location = locations.location_id AND location_archived_at IS NULL
    LEFT JOIN client_tags on client_tags.client_tags_client_id = clients.client_id
    LEFT JOIN tags on tags.tag_id = client_tags.client_tags_tag_id
    WHERE (client_name LIKE '%$q%' OR client_type LIKE '%$q%' OR client_referral LIKE '%$q%' OR contact_email LIKE '%$q%' OR contact_name LIKE '%$q%' OR contact_phone LIKE '%$phone_query%'
    OR contact_mobile LIKE '%$phone_query%' OR location_address LIKE '%$q%' OR location_city LIKE '%$q%' OR location_state LIKE '%$q%' OR location_zip LIKE '%$q%')
    OR tag_name LIKE '%$q%'
    AND client_archived_at IS NULL
    AND DATE(client_created_at) BETWEEN '$dtf' AND '$dtt'
    AND clients.company_id = $session_company_id
    GROUP BY client_id
    ORDER BY $sb $o LIMIT $record_from, $record_to
");

var_dump(mysqli_error($mysqli));

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-users mr-2"></i>Clients</h3>
            <div class="card-tools">
                <?php if ($session_user_role == 3) { ?>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addClientModal"><i class="fas fa-plus mr-2"></i>New Client</button>
                <?php } ?>
            </div>
        </div>

        <div class="card-body">
            <form class="mb-4" autocomplete="off">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(htmlentities($q)); } ?>" placeholder="Search Clients" autofocus>
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="collapse mt-3 <?php if (!empty($_GET['dtf']) || $_GET['canned_date'] !== "custom" ) { echo "show"; } ?>" id="advancedFilter">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Canned Date</label>
                                <select class="form-control select2" name="canned_date">
                                    <option <?php if ($_GET['canned_date'] == "custom") { echo "selected"; } ?> value="custom">Custom</option>
                                    <option <?php if ($_GET['canned_date'] == "today") { echo "selected"; } ?> value="today">Today</option>
                                    <option <?php if ($_GET['canned_date'] == "yesterday") { echo "selected"; } ?> value="yesterday">Yesterday</option>
                                    <option <?php if ($_GET['canned_date'] == "thisweek") { echo "selected"; } ?> value="thisweek">This Week</option>
                                    <option <?php if ($_GET['canned_date'] == "lastweek") { echo "selected"; } ?> value="lastweek">Last Week</option>
                                    <option <?php if ($_GET['canned_date'] == "thismonth") { echo "selected"; } ?> value="thismonth">This Month</option>
                                    <option <?php if ($_GET['canned_date'] == "lastmonth") { echo "selected"; } ?> value="lastmonth">Last Month</option>
                                    <option <?php if ($_GET['canned_date'] == "thisyear") { echo "selected"; } ?> value="thisyear">This Year</option>
                                    <option <?php if ($_GET['canned_date'] == "lastyear") { echo "selected"; } ?> value="lastyear">Last Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" class="form-control" name="dtf" max="2999-12-31" value="<?php echo htmlentities($dtf); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" class="form-control" name="dtt" max="2999-12-31" value="<?php echo htmlentities($dtt); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <hr>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-borderless">
                    <thead class="<?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=client_name&o=<?php echo $disp; ?>">Name</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=location_city&o=<?php echo $disp; ?>">Primary Address </a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=contact_name&o=<?php echo $disp; ?>">Primary Contact</a></th>
                        <?php if ($session_user_role == 3 || $session_user_role == 1 && $config_module_enable_accounting == 1) { ?> <th class="text-right">Billing</th> <?php } ?>
                        <?php if ($session_user_role == 3) { ?> <th class="text-center">Action</th> <?php } ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $client_id = intval($row['client_id']);
                        $client_name = htmlentities($row['client_name']);
                        $client_type = htmlentities($row['client_type']);
                        $location_id = intval($row['location_id']);
                        $location_country = htmlentities($row['location_country']);
                        $location_address = htmlentities($row['location_address']);
                        $location_city = htmlentities($row['location_city']);
                        $location_state = htmlentities($row['location_state']);
                        $location_zip = htmlentities($row['location_zip']);
                        if (empty($location_address) && empty($location_city) && empty($location_state) && empty($location_zip)) {
                            $location_address_display = "-";
                        } else {
                            $location_address_display = "$location_address<br>$location_city $location_state $location_zip";
                        }
                        $contact_id = intval($row['contact_id']);
                        $contact_name = htmlentities($row['contact_name']);
                        $contact_title = htmlentities($row['contact_title']);
                        $contact_phone = formatPhoneNumber($row['contact_phone']);
                        $contact_extension = htmlentities($row['contact_extension']);
                        $contact_mobile = formatPhoneNumber($row['contact_mobile']);
                        $contact_email = htmlentities($row['contact_email']);
                        $client_website = htmlentities($row['client_website']);
                        $client_currency_code = htmlentities($row['client_currency_code']);
                        $client_net_terms = intval($row['client_net_terms']);
                        $client_referral = htmlentities($row['client_referral']);
                        $client_notes = htmlentities($row['client_notes']);
                        $client_created_at = date('Y-m-d', strtotime($row['client_created_at']));
                        $client_updated_at = htmlentities($row['client_updated_at']);
                        $client_archive_at = htmlentities($row['client_archived_at']);

                        // Client Tags

                        $client_tag_name_display_array = array();
                        $client_tag_id_array = array();
                        $sql_client_tags = mysqli_query($mysqli, "SELECT * FROM client_tags LEFT JOIN tags ON client_tags.client_tags_tag_id = tags.tag_id WHERE client_tags.client_tags_client_id = $client_id");
                        while ($row = mysqli_fetch_array($sql_client_tags)) {

                            $client_tag_id = intval($row['tag_id']);
                            $client_tag_name = htmlentities($row['tag_name']);
                            $client_tag_color = htmlentities($row['tag_color']);
                            $client_tag_icon = htmlentities($row['tag_icon']);
                            if (empty($client_tag_icon)) {
                                $client_tag_icon = "tag";
                            }

                            $client_tag_id_array[] = $client_tag_id;
                            if (empty($client_tag_color)) {
                                $client_tag_name_display_array[] = "<small class='text-secondary'>$client_tag_name</small> ";
                            } else {
                                $client_tag_name_display_array[] = "<span class='badge bg-$client_tag_color'><i class='fa fa-fw fa-$client_tag_icon'></i> $client_tag_name</span> ";
                            }
                        }
                        $client_tags_display = implode('', $client_tag_name_display_array);

                        //Add up all the payments for the invoice and get the total amount paid to the invoice
                        $sql_invoice_amounts = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE invoice_client_id = $client_id AND invoice_status NOT LIKE 'Draft' AND invoice_status NOT LIKE 'Cancelled' ");
                        $row = mysqli_fetch_array($sql_invoice_amounts);

                        $invoice_amounts = floatval($row['invoice_amounts']);

                        $sql_amount_paid = mysqli_query($mysqli, "SELECT SUM(payment_amount) AS amount_paid FROM payments, invoices WHERE payment_invoice_id = invoice_id AND invoice_client_id = $client_id");
                        $row = mysqli_fetch_array($sql_amount_paid);

                        $amount_paid = floatval($row['amount_paid']);

                        $balance = $invoice_amounts - $amount_paid;
                        //set Text color on balance
                        if ($balance > 0) {
                            $balance_text_color = "text-danger font-weight-bold";
                        } else {
                            $balance_text_color = "";
                        }

                        //Get Monthly Recurring Total
                        $sql_recurring_monthly_total = mysqli_query($mysqli, "SELECT SUM(recurring_amount) AS recurring_monthly_total FROM recurring WHERE recurring_status = 1 AND recurring_frequency = 'month' AND recurring_client_id = $client_id AND company_id = $session_company_id");
                        $row = mysqli_fetch_array($sql_recurring_monthly_total);

                        $recurring_monthly_total = floatval($row['recurring_monthly_total']);

                        //Get Yearly Recurring Total
                        $sql_recurring_yearly_total = mysqli_query($mysqli, "SELECT SUM(recurring_amount) AS recurring_yearly_total FROM recurring WHERE recurring_status = 1 AND recurring_frequency = 'year' AND recurring_client_id = $client_id AND company_id = $session_company_id");
                        $row = mysqli_fetch_array($sql_recurring_yearly_total);

                        $recurring_yearly_total = floatval($row['recurring_yearly_total']) / 12;

                        $recurring_monthly = $recurring_monthly_total + $recurring_yearly_total;

                        ?>
                        <tr>
                            <td>
                                <strong><a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></strong>
                                <?php
                                if (!empty($client_type)) {
                                    ?>
                                    <br>
                                    <small class="text-secondary"><?php echo $client_type; ?></small>
                                <?php } ?>
                                <?php
                                if (!empty($client_tags_display)) { ?>
                                    <br>
                                    <?php echo $client_tags_display; ?>
                                <?php } ?>
                                <br>
                                <small class="text-secondary"><strong>Created:</strong> <?php echo $client_created_at; ?></small>
                            </td>
                            <td><?php echo $location_address_display; ?></td>
                            <td>
                                <?php
                                if (empty($contact_name) && empty($contact_phone) && empty($contact_mobile) && empty($client_email)) {
                                    echo "-";
                                }

                                if (!empty($contact_name)) { ?>
                                    <i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i><strong><?php echo $contact_name; ?></strong>
                                    <br>
                                <?php } else {
                                    echo "-";
                                }

                                if (!empty($contact_phone)) { ?>
                                    <i class="fa fa-fw fa-phone text-secondary mr-2 mb-2"></i><?php echo $contact_phone; ?> <?php if (!empty($contact_extension)) { echo "x$contact_extension"; } ?>
                                    <br>
                                <?php }

                                if (!empty($contact_mobile)) { ?>
                                    <i class="fa fa-fw fa-mobile-alt text-secondary mr-2"></i><?php echo $contact_mobile; ?>
                                    <br>
                                <?php }

                                if (!empty($contact_email)) { ?>
                                    <i class="fa fa-fw fa-envelope text-secondary mr-2"></i><a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a><button class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $contact_email; ?>'><i class='far fa-copy text-secondary'></i></button>
                                <?php } ?>
                            </td>

                            <!-- Show Billing for Admin/Accountant roles only and if accounting module is enabled -->
                            <?php if ($session_user_role == 3 || $session_user_role == 1 && $config_module_enable_accounting == 1) { ?>
                                <td class="text-right">
                                    <span class="text-secondary">Balance</span> <span class="<?php echo $balance_text_color; ?>"><?php echo numfmt_format_currency($currency_format, $balance, $session_company_currency); ?></span>
                                    <br>
                                    <span class="text-secondary">Paid</span> <?php echo numfmt_format_currency($currency_format, $amount_paid, $session_company_currency); ?>
                                    <br>
                                    <span class="text-secondary">Monthly</span> <?php echo numfmt_format_currency($currency_format, $recurring_monthly, $session_company_currency); ?>
                                </td>
                            <?php } ?>

                            <!-- Show actions for Admin role only -->
                            <?php if ($session_user_role == 3) { ?>
                                <td>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientModal<?php echo $client_id; ?>">
                                                <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="post.php?archive_client=<?php echo $client_id; ?>">
                                                <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold" href="#" data-toggle="modal" data-target="#deleteClientModal<?php echo $client_id; ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            <?php } ?>
                        </tr>

                        <?php

                        require("client_edit_modal.php");
                        require("client_delete_modal.php");

                    } ?>

                    </tbody>
                </table>
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>

<?php
require_once("client_add_modal.php");
require_once("category_quick_add_modal.php");
require_once("footer.php");
