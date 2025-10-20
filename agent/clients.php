<?php

// Default Column Sortby/Order Filter
$sort = "client_accessed_at";
$order = "DESC";

require_once "includes/inc_all.php";

// Perms
enforceUserPermission('module_client');

// Leads Filter
if (isset($_GET['leads']) && $_GET['leads'] == 1) {
    $leads_filter = 1;
    $leads_query = "AND client_lead = 1";
} else {
    $leads_filter = 0;
    $leads_query = "AND client_lead = 0";
}

// Tags Filter
if (isset($_GET['tags']) && is_array($_GET['tags']) && !empty($_GET['tags'])) {
    // Sanitize each element of the tags array
    $sanitizedTags = array_map('intval', $_GET['tags']);
    // Convert the sanitized tags into a comma-separated string
    $tag_filter = implode(",", $sanitizedTags);
    $tag_query = "AND tags.tag_id IN ($tag_filter)";
} else {
    $tag_filter = 0;
    $tag_query = '';
}

// Industry Filter
if (isset($_GET['industry']) & !empty($_GET['industry'])) {
    $industry_query = "AND (clients.client_type  = '" . sanitizeInput($_GET['industry']) . "')";
    $industry_filter = nullable_htmlentities($_GET['industry']);
} else {
    // Default - any
    $industry_query = '';
    $industry_filter = '';
}

// Referral Filter
if (isset($_GET['referral']) & !empty($_GET['referral'])) {
    $referral_query = "AND (clients.client_referral  = '" . sanitizeInput($_GET['referral']) . "')";
    $referral_filter = nullable_htmlentities($_GET['referral']);
} else {
    // Default - any
    $referral_query = '';
    $referral_filter = '';
}

$sql = mysqli_query(
    $mysqli,
    "
    SELECT SQL_CALC_FOUND_ROWS clients.*, contacts.*, locations.*, GROUP_CONCAT(tag_name)
    FROM clients
    LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1
    LEFT JOIN locations ON clients.client_id = locations.location_client_id AND location_primary = 1
    LEFT JOIN client_tags ON client_tags.client_id = clients.client_id
    LEFT JOIN tags ON tags.tag_id = client_tags.tag_id
    WHERE (client_name LIKE '%$q%' OR client_abbreviation LIKE '%$q%' OR client_type LIKE '%$q%' OR client_referral LIKE '%$q%'
           OR contact_email LIKE '%$q%' OR contact_name LIKE '%$q%' OR contact_phone LIKE '%$phone_query%'
           OR contact_mobile LIKE '%$phone_query%' OR location_address LIKE '%$q%'
           OR location_city LIKE '%$q%' OR location_state LIKE '%$q%' OR location_zip LIKE '%$q%' OR location_country LIKE '%$q%'
           OR tag_name LIKE '%$q%' OR client_tax_id_number LIKE '%$q%')
      AND client_$archive_query
      AND DATE(client_created_at) BETWEEN '$dtf' AND '$dtt'
      $leads_query
      $access_permission_query
      $tag_query
      $industry_query
      $referral_query
    GROUP BY client_id
    ORDER BY $sort $order
    LIMIT $record_from, $record_to
");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-user-friends mr-2"></i><?php if($leads_filter == 0){ echo "Clients"; } else { echo "Leads"; } ?></h3>
        <div class="card-tools">
            <?php if (lookupUserPermission("module_client") >= 2) { ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addClientModal">
                        <i class="fas fa-plus mr-2"></i>New
                        <?php if ($leads_filter == 0) { echo "Client"; } else { echo "Lead"; } ?>
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#importClientModal">
                            <i class="fa fa-fw fa-upload mr-2"></i>Import
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#exportClientModal">
                            <i class="fa fa-fw fa-download mr-2"></i>Export
                        </a>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
    <div class="card-header pb-2 pt-3">
        <form autocomplete="off">
            <input type="hidden" name="leads" value="<?php echo $leads_filter; ?>">
            <input type="hidden" name="archived" value="<?php echo $archived; ?>">
            <div class="row">
                <div class="col-md-5">
                    <div class="form-group">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search <?php if($leads_filter == 0){ echo "clients"; } else { echo "leads"; } ?>" autofocus>
                            <div class="input-group-append">
                                <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#advancedFilter"><i class="fas fa-filter"></i></button>
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="btn-toolbar form-group float-right">
                        <div class="btn-group mr-2">
                            <a href="?leads=0" class="btn btn-<?php if ($leads_filter == 0){ echo "primary"; } else { echo "default"; } ?>" title="Clients"><i class="fa fa-fw fa-user-friends"></i><span class="d-none d-sm-inline ml-2">Clients</span></a>
                            <a href="?leads=1" class="btn btn-<?php if ($leads_filter == 1){ echo "primary"; } else { echo "default"; } ?>"><i class="fa fa-fw fa-bullhorn"></i><span class="d-none d-sm-inline ml-2">Leads</span></a>
                        </div>

                        <div class="btn-group">
                            <a href="?<?php echo $url_query_strings_sort ?>&archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>" 
                                class="btn btn-<?php if ($archived == 1) { echo "primary"; } else { echo "default"; } ?>">
                                <i class="fa fa-fw fa-archive mr-2"></i>Archived
                            </a>
                            <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group"></i><span class="d-none d-sm-inline ml-2">Action</span> (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditHourlyRateModal">
                                        <i class="fas fa-fw fa-clock mr-2"></i>Set Hourly Rate
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditIndustryModal">
                                        <i class="fas fa-fw fa-briefcase mr-2"></i>Set Industry
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkEditReferralModal">
                                        <i class="fas fa-fw fa-link mr-2"></i>Set Referral
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkAssignTagsModal">
                                        <i class="fas fa-fw fa-tags mr-2"></i>Assign Tags
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkSendEmailModal">
                                        <i class="fas fa-fw fa-paper-plane mr-2"></i>Send Email
                                    </a>
                                    <?php if ($archived) { ?>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-info"
                                        type="submit" form="bulkActions" name="bulk_unarchive_clients">
                                        <i class="fas fa-fw fa-redo mr-2"></i>Restore
                                    </button>
                                    <?php } else { ?>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger confirm-link"
                                        type="submit" form="bulkActions" name="bulk_archive_clients">
                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                    </button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div 
                class="collapse 
                    <?php 
                    if (isset($_GET['dtf']) && $_GET['dtf'] !== '1970-01-01'
                        || $industry_filter
                        || $referral_filter
                        || (isset($_GET['tags']) && is_array($_GET['tags']))
                    )
                    { echo "show"; } 
                    ?>
                "
                id="advancedFilter"
            >
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Date range</label>
                            <input type="text" id="dateFilter" class="form-control" autocomplete="off">
                            <input type="hidden" name="canned_date" id="canned_date" value="<?php echo nullable_htmlentities($_GET['canned_date']) ?? ''; ?>">
                            <input type="hidden" name="dtf" id="dtf" value="<?php echo nullable_htmlentities($dtf ?? ''); ?>">
                            <input type="hidden" name="dtt" id="dtt" value="<?php echo nullable_htmlentities($dtt ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Tag</label>
                            <select onchange="this.form.submit()" class="form-control select2" name="tags[]" data-placeholder="- Select Tags -" multiple>
                                <?php 
                                $sql_tags_filter = mysqli_query($mysqli, "
                                    SELECT tags.tag_id, tags.tag_name
                                    FROM tags 
                                    LEFT JOIN client_tags ON client_tags.tag_id = tags.tag_id
                                    WHERE tag_type = 1
                                    GROUP BY tags.tag_id
                                    HAVING COUNT(client_tags.client_id) > 0 OR tags.tag_id IN ($tag_filter)
                                ");
                                while ($row = mysqli_fetch_array($sql_tags_filter)) {
                                    $tag_id = intval($row['tag_id']);
                                    $tag_name = nullable_htmlentities($row['tag_name']); ?>

                                    <option value="<?php echo $tag_id ?>" <?php if (isset($_GET['tags']) && is_array($_GET['tags']) && in_array($tag_id, $_GET['tags'])) { echo 'selected'; } ?>> <?php echo $tag_name ?> </option>

                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label>Industry</label>
                            <select class="form-control select2" name="industry" onchange="this.form.submit()">
                                <option value="">- All Industries -</option>

                                <?php
                                $sql_industries_filter = mysqli_query($mysqli, "SELECT DISTINCT client_type FROM clients WHERE 1 = 1 AND client_$archive_query AND client_type != '' $leads_query ORDER BY client_type ASC");
                                while ($row = mysqli_fetch_array($sql_industries_filter)) {
                                    $industry_name = nullable_htmlentities($row['client_type']);
                                ?>
                                    <option <?php if ($industry_name == $industry_filter) { echo "selected"; } ?>><?php echo $industry_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label>Referral</label>
                            <select class="form-control select2" name="referral" onchange="this.form.submit()">
                                <option value="">- All Referrals -</option>

                                <?php
                                $sql_referrals_filter = mysqli_query($mysqli, "SELECT DISTINCT client_referral FROM clients WHERE 1 = 1 AND client_$archive_query AND client_referral != '' $leads_query ORDER BY client_referral ASC");
                                while ($row = mysqli_fetch_array($sql_referrals_filter)) {
                                    $referral_name = nullable_htmlentities($row['client_referral']);
                                ?>
                                    <option <?php if ($referral_name == $referral_filter) { echo "selected"; } ?>><?php echo $referral_name; ?></option>
                                <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <form id="bulkActions" action="post.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
        <div class="table-responsive-sm">
            <table class="table table-hover mb-0 text-nowrap">
                <thead class="<?php if ($num_rows[0] == 0) { echo "d-none"; } ?> bg-light">
                <tr>
                    <td class="pr-0">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                        </div>
                    </td>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=client_name&order=<?php echo $disp; ?>">
                            Client Name <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=location_city&order=<?php echo $disp; ?>">
                            Primary Location <?php if ($sort == 'location_city') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=contact_name&order=<?php echo $disp; ?>">
                            Primary Contact
                            <?php if ($sort == 'contact_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th></th>
                    <?php if ((lookupUserPermission("module_financial") >= 1) && $config_module_enable_accounting == 1) { ?>
                    <th>Billing</th>
                    <?php } ?>
                    <?php if (lookupUserPermission("module_client") >= 2) { ?>
                    <th class="text-center">Action</th>
                    <?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $client_id = intval($row['client_id']);
                    $client_name = nullable_htmlentities($row['client_name']);
                    $client_type = nullable_htmlentities($row['client_type']);
                    $location_id = intval($row['location_id']);
                    $location_country = nullable_htmlentities($row['location_country']);
                    $location_address = nullable_htmlentities($row['location_address']);
                    $location_city = nullable_htmlentities($row['location_city']);
                    $location_state = nullable_htmlentities($row['location_state']);
                    $location_zip = nullable_htmlentities($row['location_zip']);
                    if (empty($location_address) && empty($location_city) && empty($location_state) && empty($location_zip)) {
                        $location_address_display = "-";
                    } else {
                        $location_address_display = "<div class='media'><i class='fa fa-fw fa-map-marker-alt text-secondary mt-1 mr-2'></i><div class='media-body'>$location_address<div>$location_city $location_state $location_zip</div><div><small>$location_country</small></div></div></div>";
                    }
                    $contact_id = intval($row['contact_id']);
                    $contact_name = nullable_htmlentities($row['contact_name']);
                    $contact_title = nullable_htmlentities($row['contact_title']);
                    $contact_phone_country_code = nullable_htmlentities($row['contact_phone_country_code']);
                    $contact_phone = nullable_htmlentities(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
                    $contact_extension = nullable_htmlentities($row['contact_extension']);
                    $contact_mobile_country_code = nullable_htmlentities($row['contact_mobile_country_code']);
                    $contact_mobile = nullable_htmlentities(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));
                    $contact_email = nullable_htmlentities($row['contact_email']);
                    $client_website = nullable_htmlentities($row['client_website']);
                    $client_rate = floatval($row['client_rate']);
                    $client_currency_code = nullable_htmlentities($row['client_currency_code']);
                    $client_net_terms = intval($row['client_net_terms']);
                    $client_tax_id_number = nullable_htmlentities($row['client_tax_id_number']);
                    $client_referral = nullable_htmlentities($row['client_referral']);
                    $client_abbreviation = nullable_htmlentities($row['client_abbreviation']);
                    $client_notes = nullable_htmlentities($row['client_notes']);
                    $client_created_at = date('Y-m-d', strtotime($row['client_created_at']));
                    $client_updated_at = nullable_htmlentities($row['client_updated_at']);
                    $client_archived_at = nullable_htmlentities($row['client_archived_at']);
                    $client_is_lead = intval($row['client_lead']);

                    // Abbreviation
                    if (empty($client_abbreviation)) {
                        $client_abbreviation = shortenClient($client_name);
                    }

                    // Counts
                    
                    // Contact Count
                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('contact_id') AS num FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL"));
                    $contact_count = $row['num'];
                    if ($contact_count) { 
                        $contact_count_display = "<a href='contacts.php?client_id=$client_id' class='mr-2 mb-1 badge badge-pill badge-dark p-2' title='Contacts ($contact_count)'><i class='fas fa-fw fa-users mr-2'></i>$contact_count</a>";
                    } else {
                        $contact_count_display = '';
                    }

                    // Vendors Count
                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('vendor_id') AS num FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL"));
                    $vendor_count = $row['num'];
                    if ($vendor_count) { 
                        $vendor_count_display = "<a href='vendors.php?client_id=$client_id' class='mr-2 mb-1 badge badge-pill badge-dark p-2' title='Vendors ($vendor_count)'><i class='fas fa-fw fa-building mr-2'></i>$vendor_count</a>";
                    } else {
                        $vendor_count_display = '';
                    }
                    
                    // Asset Count
                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('asset_id') AS num FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL"));
                    $asset_count = $row['num'];
                    if ($asset_count) { 
                        $asset_count_display = "<a href='assets.php?client_id=$client_id' class='mr-2 mb-1 badge badge-pill badge-secondary p-2' title='Assets ($asset_count)'><i class='fas fa-fw fa-desktop mr-2'></i>$asset_count</a>";
                    } else {
                        $asset_count_display = '';
                    }
                    
                    // Credential Count
                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('credential_id') AS num FROM credentials WHERE credential_client_id = $client_id AND credential_archived_at IS NULL"));
                    $credential_count = $row['num'];
                    if ($credential_count) { 
                        $credential_count_display = "<a href='credentials.php?client_id=$client_id' class='mr-2 mb-1 badge badge-pill badge-secondary p-2' title='Credentials ($credential_count)'><i class='fas fa-fw fa-key mr-2'></i>$credential_count</a>";
                    } else {
                        $credential_count_display = '';
                    }
                    
                    // Software Count
                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('software_id') AS num FROM software WHERE software_client_id = $client_id AND software_archived_at IS NULL"));
                    $software_count = $row['num'];
                    if ($software_count) { 
                        $software_count_display = "<a href='software.php?client_id=$client_id' class='mr-2 mb-1 badge badge-pill badge-secondary p-2' title='Licenses ($software_count)'><i class='fas fa-fw fa-cube mr-2'></i>$software_count</a>";
                    } else {
                        $software_count_display = '';
                    }

                    // Ticket Count
                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('ticket_id') AS num FROM tickets WHERE ticket_client_id = $client_id AND ticket_archived_at IS NULL"));
                    $ticket_count = $row['num'];
                    if ($ticket_count) { 
                        $ticket_count_display = "<a href='tickets.php?client_id=$client_id' class='mr-2 mb-1 badge badge-pill badge-secondary p-2' title='Tickets ($ticket_count)'><i class='fas fa-fw fa-life-ring mr-2'></i>$ticket_count</a>";
                    } else {
                        $ticket_count_display = '';
                    }

                    // Client Tags

                    $client_tag_name_display_array = array();
                    $client_tag_id_array = array();
                    $sql_client_tags = mysqli_query($mysqli, "SELECT * FROM client_tags LEFT JOIN tags ON client_tags.tag_id = tags.tag_id WHERE client_id = $client_id ORDER BY tag_name ASC");
                    while ($row = mysqli_fetch_array($sql_client_tags)) {

                        $client_tag_id = intval($row['tag_id']);
                        $client_tag_name = nullable_htmlentities($row['tag_name']);
                        $client_tag_color = nullable_htmlentities($row['tag_color']);
                        if (empty($client_tag_color)) {
                            $client_tag_color = "dark";
                        }
                        $client_tag_icon = nullable_htmlentities($row['tag_icon']);
                        if (empty($client_tag_icon)) {
                            $client_tag_icon = "tag";
                        }

                        $client_tag_id_array[] = $client_tag_id;
                        $client_tag_name_display_array[] = "<a href='clients.php?tags[]=$client_tag_id'><span class='badge text-light p-1 mr-1' style='background-color: $client_tag_color;'><i class='fa fa-fw fa-$client_tag_icon mr-2'></i>$client_tag_name</span></a>";
                    }
                    $client_tags_display = implode('', $client_tag_name_display_array);

                    //Add up all the payments for the invoice and get the total amount paid to the invoice
                    $sql_invoice_amounts = mysqli_query($mysqli, "SELECT SUM(invoice_amount) AS invoice_amounts FROM invoices WHERE invoice_client_id = $client_id AND invoice_status != 'Draft' AND invoice_status != 'Cancelled' AND invoice_status != 'Non-Billable' ");
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

                    // Get Credit Balance
                    $sql_credit_balance = mysqli_query($mysqli, "SELECT SUM(credit_amount) AS credit_balance FROM credits WHERE credit_client_id = $client_id");
                    $row = mysqli_fetch_array($sql_credit_balance);

                    $credit_balance = floatval($row['credit_balance']);

                    //Get Monthly Recurring Total
                    $sql_recurring_monthly_total = mysqli_query($mysqli, "SELECT SUM(recurring_invoice_amount) AS recurring_monthly_total FROM recurring_invoices WHERE recurring_invoice_status = 1 AND recurring_invoice_frequency = 'month' AND recurring_invoice_client_id = $client_id");
                    $row = mysqli_fetch_array($sql_recurring_monthly_total);

                    $recurring_monthly_total = floatval($row['recurring_monthly_total']);

                    //Get Yearly Recurring Total
                    $sql_recurring_yearly_total = mysqli_query($mysqli, "SELECT SUM(recurring_invoice_amount) AS recurring_yearly_total FROM recurring_invoices WHERE recurring_invoice_status = 1 AND recurring_invoice_frequency = 'year' AND recurring_invoice_client_id = $client_id");
                    $row = mysqli_fetch_array($sql_recurring_yearly_total);

                    $recurring_yearly_total = floatval($row['recurring_yearly_total']) / 12;

                    $recurring_monthly = $recurring_monthly_total + $recurring_yearly_total;

                    ?>
                    <tr>
                        <td class="pr-0 bg-light">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="client_ids[]" value="<?php echo $client_id ?>">
                            </div>
                        </td>
                        <td>
                            <a data-toggle="tooltip" data-placement="right" title="Client ID: <?php echo $client_id; ?>" class="font-weight-bold h6" href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>

                            <?php
                            if (!empty($client_type)) {
                            ?>
                                <div class="text-secondary mt-1">
                                    <?php echo $client_type; ?>
                                </div>
                            <?php } ?>
                            <?php
                            if (!empty($client_tags_display)) { ?>
                                <div class="mt-1 text-wrap">
                                    <?php echo $client_tags_display; ?>
                                </div>
                            <?php } ?>
                            <div class="mt-1 text-secondary">
                                <small><strong>Abbreviation: </strong> <?php echo $client_abbreviation; ?></small><br>
                                <small><strong>Created: </strong> <?php echo $client_created_at; ?></small><br>
                            </div>

                        </td>
                        <td><?php echo $location_address_display; ?></td>
                        <td>
                            <?php
                            if (empty($contact_name) && empty($contact_phone) && empty($contact_mobile) && empty($client_email)) {
                                echo "-";
                            }

                            if (!empty($contact_name)) { ?>
                                <div class="text-bold">   
                                    <i class="fa fa-fw fa-user text-secondary mr-2 mb-2"></i><a class="ajax-modal" href="#"
                                        data-modal-url="modals/contact/contact_details.php?client_id=<?= $client_id ?>&id=<?= $contact_id ?>" data-modal-size="lg"><?= $contact_name; ?>
                                        
                                     </a>
                                </div>
                            <?php } else {
                                echo "-";
                            }

                            if (!empty($contact_phone)) { ?>
                                <div class="mt-1">
                                    <i class="fa fa-fw fa-phone text-secondary mr-2 mb-2"></i><?php echo $contact_phone; ?> <?php if (!empty($contact_extension)) { echo "x$contact_extension"; } ?>
                                </div>
                            <?php }

                            if (!empty($contact_mobile)) { ?>
                                <div class="mt-1">
                                    <i class="fa fa-fw fa-mobile-alt text-secondary mr-2"></i><?php echo $contact_mobile; ?>
                                </div>
                            <?php }

                            if (!empty($contact_email)) { ?>
                                <div class="mt-1">
                                    <i class="fa fa-fw fa-envelope text-secondary mr-2"></i><a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a><button class='btn btn-sm clipboardjs' type="button" data-clipboard-text='<?php echo $contact_email; ?>'><i class='far fa-copy text-secondary'></i></button>
                                </div>
                            <?php } ?>
                        </td>

                        <td class="text-wrap">
                            <?php echo "$contact_count_display$vendor_count_display$asset_count_display$credential_count_display$software_count_display$ticket_count_display"; ?>
                        </td>

                        <!-- Show Billing if perms & if accounting module is enabled -->
                        <?php if ((lookupUserPermission("module_financial") >= 1) && $config_module_enable_accounting == 1) { ?>
                            <td class="text-right">
                                <div class="d-flex justify-content-between">
                                    <span class="text-secondary">Balance</span>
                                    <span class="<?php echo $balance_text_color; ?>"><?php echo numfmt_format_currency($currency_format, $balance, $session_company_currency); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-secondary">Paid</span>
                                    <span><?php echo numfmt_format_currency($currency_format, $amount_paid, $session_company_currency); ?></span>
                                </div>
                                <?php if ($credit_balance > 0) { ?>
                                <div class="d-flex justify-content-between">
                                    <span class="text-secondary">Credit</span>
                                    <span class="text-success"><?php echo numfmt_format_currency($currency_format, $credit_balance, $session_company_currency); ?></span>
                                </div>
                                <?php } ?>
                                <div class="d-flex justify-content-between">
                                    <span class="text-secondary">Monthly</span>
                                    <span><?php echo numfmt_format_currency($currency_format, $recurring_monthly, $session_company_currency); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-secondary">Hourly Rate</span>
                                    <span><?php echo numfmt_format_currency($currency_format, $client_rate, $session_company_currency); ?></span>
                                </div>
                            </td>
                        <?php } ?>

                        <!-- Actions -->
                        <?php if (lookupUserPermission("module_client") >= 2) { ?>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/client/client_edit.php?id=<?= $client_id ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>

                                        <?php if ($client_archived_at) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-info confirm-link" href="post.php?restore_client=<?php echo $client_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-redo mr-2"></i>Restore
                                        </a>
                                        <?php } else { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_client=<?php echo $client_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                        </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>
                        <?php } ?>
                    </tr>

                    <?php
                } ?>

                </tbody>
            </table>
        </div>
        <?php 
            require_once "modals/client/client_bulk_edit_industry.php";
            require_once "modals/client/client_bulk_edit_referral.php";
            require_once "modals/client/client_bulk_edit_hourly_rate.php";
            require_once "modals/client/client_bulk_assign_tags.php"; 
            require_once "modals/client/client_bulk_email.php";
        ?>
    </form>
     <!-- Ends Card Body -->
    <?php require_once "../includes/filter_footer.php"; ?>
    
</div> <!-- End Card -->

<script src="../js/bulk_actions.js"></script>

<?php
require_once "modals/client/client_add.php";
require_once "modals/client/client_import.php";
require_once "modals/client/client_export.php";
require_once "../includes/footer.php";
