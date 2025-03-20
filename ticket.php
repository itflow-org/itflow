<?php

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

// Initialize the HTML Purifier to prevent XSS
require_once "plugins/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

if (isset($_GET['ticket_id'])) {
    $ticket_id = intval($_GET['ticket_id']);

    $sql = mysqli_query(
        $mysqli,
        "SELECT * FROM tickets
        LEFT JOIN clients ON ticket_client_id = client_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        LEFT JOIN users ON ticket_assigned_to = user_id
        LEFT JOIN locations ON ticket_location_id = location_id
        LEFT JOIN assets ON ticket_asset_id = asset_id
        LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
        LEFT JOIN vendors ON ticket_vendor_id = vendor_id
        LEFT JOIN projects ON ticket_project_id = project_id
        LEFT JOIN invoices ON ticket_invoice_id = invoice_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        LEFT JOIN categories ON ticket_category = category_id
        WHERE ticket_id = $ticket_id
        $access_permission_query
        LIMIT 1"
    );

    if (mysqli_num_rows($sql) == 0) {
        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='tickets.php'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

        include_once "includes/footer.php";
    } else {

        $row = mysqli_fetch_array($sql);
        $client_id = intval($row['client_id']);
        $client_name = nullable_htmlentities($row['client_name']);
        $client_type = nullable_htmlentities($row['client_type']);
        $client_website = nullable_htmlentities($row['client_website']);

        $client_net_terms = intval($row['client_net_terms']);
        if ($client_net_terms == 0) {
            $client_net_terms = $config_default_net_terms;
        }

        $client_rate = floatval($row['client_rate']);

        $ticket_prefix = nullable_htmlentities($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_category = intval($row['ticket_category']);
        $ticket_category_display = nullable_htmlentities($row['category_name']);
        $ticket_subject = nullable_htmlentities($row['ticket_subject']);
        $ticket_details = $purifier->purify($row['ticket_details']);
        $ticket_priority = nullable_htmlentities($row['ticket_priority']);
        $ticket_billable = intval($row['ticket_billable']);
        $ticket_scheduled_for = nullable_htmlentities($row['ticket_schedule']);
        $ticket_onsite = intval($row['ticket_onsite']);
        if ($ticket_scheduled_for) {
            $ticket_scheduled_wording = "$ticket_scheduled_for";
        } else {
            $ticket_scheduled_wording = "Add";
        }

        //Set Ticket Badge Color based of priority
        if ($ticket_priority == "High") {
            $ticket_priority_display = "<span class='p-2 badge badge-danger'>$ticket_priority</span>";
        } elseif ($ticket_priority == "Medium") {
            $ticket_priority_display = "<span class='p-2 badge badge-warning'>$ticket_priority</span>";
        } elseif ($ticket_priority == "Low") {
            $ticket_priority_display = "<span class='p-2 badge badge-info'>$ticket_priority</span>";
        } else {
            $ticket_priority_display = "";
        }
        $ticket_feedback = nullable_htmlentities($row['ticket_feedback']);

        $ticket_status = intval($row['ticket_status_id']);
        $ticket_status_id = intval($row['ticket_status_id']);
        $ticket_status_name = nullable_htmlentities($row['ticket_status_name']);
        $ticket_status_color = nullable_htmlentities($row['ticket_status_color']);

        $ticket_vendor_ticket_number = nullable_htmlentities($row['ticket_vendor_ticket_number']);
        $ticket_created_at = nullable_htmlentities($row['ticket_created_at']);
        $ticket_created_at_ago = timeAgo($row['ticket_created_at']);
        $ticket_date = date('Y-m-d', strtotime($ticket_created_at));
        $ticket_updated_at = nullable_htmlentities($row['ticket_updated_at']);
        $ticket_updated_at_ago = timeAgo($row['ticket_updated_at']);
        $ticket_resolved_at = nullable_htmlentities($row['ticket_resolved_at']);
        $ticket_resolved_at_ago = timeAgo($row['ticket_resolved_at']);
        $ticket_closed_at = nullable_htmlentities($row['ticket_closed_at']);
        $ticket_closed_at_ago = timeAgo($row['ticket_closed_at']);
        $ticket_closed_by = intval($row['ticket_closed_by']);

        $ticket_assigned_to = intval($row['ticket_assigned_to']);
        if (empty($ticket_assigned_to)) {
            $ticket_assigned_to_display = "<span class='text-danger'>Not Assigned</span>";
        } else {
            $ticket_assigned_to_display = nullable_htmlentities($row['user_name']);
        }

        // Tab Title // No Sanitizing needed
        $page_title = $row['ticket_subject'];
        $tab_title = "{$row['ticket_prefix']}{$row['ticket_number']}";

        $contact_id = intval($row['contact_id']);
        $contact_name = nullable_htmlentities($row['contact_name']);
        $contact_title = nullable_htmlentities($row['contact_title']);
        $contact_email = nullable_htmlentities($row['contact_email']);
        $contact_phone = formatPhoneNumber($row['contact_phone']);
        $contact_extension = nullable_htmlentities($row['contact_extension']);
        $contact_mobile = formatPhoneNumber($row['contact_mobile']);

        $asset_id = intval($row['asset_id']);
        $asset_ip = nullable_htmlentities($row['interface_ip']);
        $asset_name = nullable_htmlentities($row['asset_name']);
        $asset_type = nullable_htmlentities($row['asset_type']);
        $asset_uri = nullable_htmlentities($row['asset_uri']);
        $asset_make = nullable_htmlentities($row['asset_make']);
        $asset_model = nullable_htmlentities($row['asset_model']);
        $asset_serial = nullable_htmlentities($row['asset_serial']);
        $asset_os = nullable_htmlentities($row['asset_os']);
        $asset_warranty_expire = nullable_htmlentities($row['asset_warranty_expire']);
        $asset_icon = getAssetIcon($asset_type);

        $vendor_id = intval($row['ticket_vendor_id']);
        $vendor_name = nullable_htmlentities($row['vendor_name']);
        $vendor_description = nullable_htmlentities($row['vendor_description']);
        $vendor_account_number = nullable_htmlentities($row['vendor_account_number']);
        $vendor_contact_name = nullable_htmlentities($row['vendor_contact_name']);
        $vendor_phone = formatPhoneNumber($row['vendor_phone']);
        $vendor_extension = nullable_htmlentities($row['vendor_extension']);
        $vendor_email = nullable_htmlentities($row['vendor_email']);
        $vendor_website = nullable_htmlentities($row['vendor_website']);
        $vendor_hours = nullable_htmlentities($row['vendor_hours']);
        $vendor_sla = nullable_htmlentities($row['vendor_sla']);
        $vendor_code = nullable_htmlentities($row['vendor_code']);
        $vendor_notes = nullable_htmlentities($row['vendor_notes']);

        $location_id = intval($row['location_id']);
        $location_name = nullable_htmlentities($row['location_name']);
        $location_address = nullable_htmlentities($row['location_address']);
        $location_city = nullable_htmlentities($row['location_city']);
        $location_state = nullable_htmlentities($row['location_state']);
        $location_zip = nullable_htmlentities($row['location_zip']);
        $location_phone = formatPhoneNumber($row['location_phone']);

        $project_id = intval($row['project_id']);
        $project_prefix = nullable_htmlentities($row['project_prefix']);
        $project_number = intval($row['project_number']);
        $project_name = nullable_htmlentities($row['project_name']);
        $project_description = nullable_htmlentities($row['project_description']);
        $project_due = nullable_htmlentities($row['project_due']);
        $project_manager = nullable_htmlentities($row['project_manager']);

        if($project_manager) {
            $sql_project_manager = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $project_manager");
            $row = mysqli_fetch_array($sql_project_manager);
            $project_manager_name = nullable_htmlentities($row['user_name']);
        }

        $invoice_id = intval($row['ticket_invoice_id']);
        $invoice_prefix = nullable_htmlentities($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_created_at = nullable_htmlentities($row['invoice_created_at']);

        if ($contact_id) {
            //Get Contact Ticket Stats
            $ticket_related_open = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_related_open FROM tickets WHERE ticket_status != 'Closed' AND ticket_contact_id = $contact_id ");
            $row = mysqli_fetch_array($ticket_related_open);
            $ticket_related_open = intval($row['ticket_related_open']);

            $ticket_related_closed = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_related_closed  FROM tickets WHERE ticket_status = 'Closed' AND ticket_contact_id = $contact_id ");
            $row = mysqli_fetch_array($ticket_related_closed);
            $ticket_related_closed = intval($row['ticket_related_closed']);

            $ticket_related_total = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_related_total FROM tickets WHERE ticket_contact_id = $contact_id ");
            $row = mysqli_fetch_array($ticket_related_total);
            $ticket_related_total = intval($row['ticket_related_total']);
        }

        //Get Total Ticket Time
        $ticket_total_reply_time = mysqli_query($mysqli, "SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) AS ticket_total_reply_time FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_array($ticket_total_reply_time);
        $ticket_total_reply_time = nullable_htmlentities($row['ticket_total_reply_time']);


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
            $client_tag_name_display_array[] = "<span class='badge text-light p-1 mr-1' style='background-color: $client_tag_color;'><i class='fa fa-fw fa-$client_tag_icon mr-2'></i>$client_tag_name</span>";
        }
        $client_tags_display = implode(' ', $client_tag_name_display_array);


        // Get the number of ticket Responses
        $ticket_responses_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_responses FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_array($ticket_responses_sql);
        $ticket_responses = intval($row['ticket_responses']);

        $ticket_all_comments_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_all_comments_count FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_array($ticket_all_comments_sql);
        $ticket_all_comments_count = intval($row['ticket_all_comments_count']);

        $ticket_internal_notes_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_internal_notes_count FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_type = 'Internal' AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_array($ticket_internal_notes_sql);
        $ticket_internal_notes_count = intval($row['ticket_internal_notes_count']);

        $ticket_public_comments_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_public_comments_count FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND (ticket_reply_type = 'Public' OR ticket_reply_type = 'Client') AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_array($ticket_public_comments_sql);
        $ticket_public_comments_count = intval($row['ticket_public_comments_count']);

        $ticket_events_sql = mysqli_query($mysqli, "SELECT COUNT(log_id) AS ticket_events_count FROM logs WHERE log_type = 'Ticket' AND  log_entity_id = $ticket_id");
        $row = mysqli_fetch_array($ticket_events_sql);
        $ticket_events_count = intval($row['ticket_events_count']);


        // Get & format asset warranty expiry
        $date = date('Y-m-d H:i:s');
        $dt_value = $asset_warranty_expire; //sample date
        $warranty_check = date('m/d/Y', strtotime('-8 hours'));
        if ($dt_value <= $date) {
            $dt_value = "Expired on $asset_warranty_expire";
            $warranty_status_color = 'red';
        } else {
            $warranty_status_color = 'green';
        }

        if ($asset_warranty_expire == "NULL") {
            $dt_value = "None";
            $warranty_status_color = 'red';
        }


        // Get ticket replies
        $sql_ticket_replies = mysqli_query($mysqli, "SELECT * FROM ticket_replies 
            LEFT JOIN users ON ticket_reply_by = user_id
            LEFT JOIN contacts ON ticket_reply_by = contact_id
            WHERE ticket_reply_ticket_id = $ticket_id
            AND ticket_reply_archived_at IS NULL
            ORDER BY ticket_reply_id DESC"
        );

        // Get ticket Events
        $sql_ticket_events = mysqli_query($mysqli, "SELECT * FROM ticket_history
            WHERE ticket_history_ticket_id = $ticket_id
            ORDER BY ticket_history_id DESC"
        );

        // Get Technicians to assign the ticket to
        $sql_assign_to_select = mysqli_query(
            $mysqli,
            "SELECT user_id, user_name FROM users
            WHERE user_role_id > 1
            AND user_type = 1
            AND user_status = 1
            AND user_archived_at IS NULL
            ORDER BY user_name ASC"
        );


        // Get Watchers
        $sql_ticket_watchers = mysqli_query($mysqli, "SELECT * FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id ORDER BY watcher_email DESC");

        // Get Additional Assets
        $sql_additional_assets = mysqli_query($mysqli, "SELECT * FROM assets, ticket_assets
            WHERE assets.asset_id = ticket_assets.asset_id
            AND ticket_id = $ticket_id
            AND assets.asset_id != $asset_id"
        );

        // Get Ticket Attachments
        $sql_ticket_attachments = mysqli_query(
            $mysqli,
            "SELECT * FROM ticket_attachments
            WHERE ticket_attachment_reply_id IS NULL
            AND ticket_attachment_ticket_id = $ticket_id"
        );


        // Get Tasks
        $sql_tasks = mysqli_query( $mysqli, "SELECT * FROM tasks WHERE task_ticket_id = $ticket_id ORDER BY task_order ASC, task_id ASC");
        $task_count = mysqli_num_rows($sql_tasks);

        // Get Completed Task Count
        $sql_tasks_completed = mysqli_query($mysqli,
            "SELECT * FROM tasks
            WHERE task_ticket_id = $ticket_id
            AND task_completed_at IS NOT NULL"
        );
        $completed_task_count = mysqli_num_rows($sql_tasks_completed);

        // Tasks Completed Percent
        if ($task_count) {
            $tasks_completed_percent = round(($completed_task_count / $task_count) * 100);
        }

        // Get all Assigned ticket Users as a comma-separated string
        $sql_ticket_collaborators = mysqli_query($mysqli, "
            SELECT GROUP_CONCAT(DISTINCT user_name SEPARATOR ', ') AS user_names
            FROM users
            LEFT JOIN ticket_replies ON user_id = ticket_reply_by 
            WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id
        ");

        // Fetch the result
        $row = mysqli_fetch_assoc($sql_ticket_collaborators);

        // The user names in a comma-separated string
        $ticket_collaborators = nullable_htmlentities($row['user_names']);

        ?>
        <link rel="stylesheet" href="plugins/dragula/dragula.min.css">

        <!-- Breadcrumbs-->
        <ol class="breadcrumb d-print-none">
            <?php if (isset($_GET['client_id'])) { ?>
                <li class="breadcrumb-item">
                    <a href="client_overview.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
                </li>
                <li class="breadcrumb-item">
                    <a href="tickets.php?client_id=<?php echo $client_id; ?>">Tickets</a>
                </li>
            <?php } else { ?>
                <li class="breadcrumb-item">
                    <a href="tickets.php">Tickets</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="tickets.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a>
                </li>
            <?php } ?>
            <li class="breadcrumb-item active"><i class="fas fa-life-ring mr-1"></i><?php echo "$ticket_prefix$ticket_number";?></li>
        </ol>

        <div class="card">

            <div class="card-header">

                <div class="card-title">
                    <i class="fa fa-2x fa-fw fa fa-life-ring text-secondary mr-2"></i>
                    <span class="h3">
                        <?php echo "$ticket_prefix$ticket_number"; ?>
                        <span class='badge badge-pill text-light ml-1' style="background-color: <?php echo $ticket_status_color; ?>">
                            <?php echo $ticket_status_name; ?>
                        </span>
                    </span>
                </div>

                <?php if (lookupUserPermission("module_support") >= 2) { ?>
                    <div class="card-tools d-print-none">
                        <div class="btn-toolbar">

                            <?php if ($config_ai_enable == 1) { ?>
                                <button class="btn btn-info btn-sm ml-3" data-toggle="modal" data-target="#summaryModal">
                                    <i class="fas fa-fw fa-lightbulb mr-2"></i>Summary
                                </button>
                            <?php } ?>

                            <?php if ($config_module_enable_accounting && $ticket_billable == 1 && empty($invoice_id) && lookupUserPermission("module_sales") >= 2) { ?>
                                <a href="#" class="btn btn-light btn-sm ml-3" href="#" data-toggle="modal" data-target="#addInvoiceFromTicketModal">
                                    <i class="fas fa-fw fa-file-invoice mr-2"></i>Invoice
                                </a>
                            <?php }

                            if (!empty($ticket_closed_at) && isset($session_is_admin) && $session_is_admin) { ?>
                                <a href="ticket_redact.php?ticket_id=<?php echo $ticket_id; ?>" class="btn btn-danger btn-sm ml-3">
                                    <i class="fas fa-fw fa-marker mr-2"></i>Redact
                                </a>
                            <?php }

                            if (empty($ticket_closed_at)) { ?>

                                <?php if (empty($ticket_closed_at) && !empty($ticket_resolved_at)) { ?>
                                    <a href="post.php?reopen_ticket=<?php echo $ticket_id; ?>" class="btn btn-light btn-sm ml-3">
                                        <i class="fas fa-fw fa-redo mr-2"></i>Reopen
                                    </a>
                                <?php } ?>

                                <?php if (empty($ticket_resolved_at) && $task_count == $completed_task_count) { ?>
                                    <a href="post.php?resolve_ticket=<?php echo $ticket_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>" class="btn btn-dark btn-sm confirm-link ml-3" id="ticket_close">
                                        <i class="fas fa-fw fa-check mr-2"></i>Resolve
                                    </a>
                                <?php } ?>

                                <?php if (!empty($ticket_resolved_at) && $task_count == $completed_task_count) { ?>
                                    <a href="post.php?close_ticket=<?php echo $ticket_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>" class="btn btn-dark btn-sm confirm-link ml-3" id="ticket_close">
                                        <i class="fas fa-fw fa-gavel mr-2"></i>Close
                                    </a>
                                <?php } ?>

                                <div class="dropdown dropleft text-center ml-3">
                                    <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                                        <i class="fas fa-fw fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#"
                                           data-toggle = "ajax-modal"
                                           data-modal-size = "lg"
                                           data-ajax-url = "ajax/ajax_ticket_edit.php"
                                           data-ajax-id = "<?php echo $ticket_id; ?>"
                                        >
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#mergeTicketModal<?php echo $ticket_id; ?>">
                                            <i class="fas fa-fw fa-clone mr-2"></i>Merge
                                        </a>
                                        <?php if (empty($ticket_closed_at)) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item"
                                               data-toggle = "ajax-modal"
                                               data-ajax-url = "ajax/ajax_ticket_contact.php"
                                               data-ajax-id = "<?php echo $ticket_id; ?>"
                                            >
                                                <i class="fa fa-fw fa-user mr-2"></i>Add Contact
                                            </a>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTicketAssetModal<?php echo $ticket_id; ?>">
                                                <i class="fas fa-fw fa-desktop mr-2"></i>Add Asset
                                            </a>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTicketVendorModal<?php echo $ticket_id; ?>">
                                                <i class="fas fa-fw fa-building mr-2"></i>Add Vendor
                                            </a>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addTicketWatcherModal">
                                                <i class="fas fa-fw fa-users mr-2"></i>Add Watcher
                                            </a>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#" data-toggle="modal" id="clientChangeTicketModalLoad" data-target="#clientChangeTicketModal">
                                            <i class="fas fa-fw fa-people-carry mr-2"></i>Change Client
                                        </a>
                                        <?php if (lookupUserPermission("module_support") == 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_ticket=<?php echo $ticket_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

            </div> <!-- Card Header -->

            <div class="card-body pt-3 pb-0">
                <div class="row">
                    <div class="col-sm-4">
                        <h5><?php echo $client_name; ?></h5>
                        <div title="<?php echo $ticket_created_at; ?>">
                            <i class="fa fa-fw fa-calendar text-secondary mr-2"></i><?php echo $ticket_created_at_ago; ?>
                        </div>
                        <div class="mt-1" title="<?php echo $ticket_updated_at; ?>">
                            <i class="fa fa-fw fa-history text-secondary mr-2"></i>Updated: <strong><?php echo $ticket_updated_at_ago; ?></strong>
                        </div>

                        <!-- Ticket closure info -->
                        <?php
                        if (!empty($ticket_closed_at)) {

                            $ticket_closed_by_display = 'User';
                            if (!empty($ticket_closed_by)) {
                                $sql_closed_by = mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = $ticket_closed_by");
                                $row = mysqli_fetch_array($sql_closed_by);
                                $ticket_closed_by_display = nullable_htmlentities($row['user_name']);
                            }
                            ?>
                            <div class="mt-1">
                                <i class="fa fa-fw fa-user text-secondary mr-2"></i>Closed by: <?php echo ucwords($ticket_closed_by_display); ?>
                            </div>
                            <div class="mt-1">
                                <i class="fa fa-fw fa-clock text-secondary mr-2"></i>Closed at: <?php echo $ticket_closed_at; ?>
                            </div>
                            <div class="mt-1">
                                <i class="fas fa-fw fa-user mr-2 text-secondary"></i><?php echo $ticket_assigned_to_display; ?>
                            </div>
                            <?php if($ticket_feedback) { ?>
                                <div class="mt-1">
                                    <i class="fa fa-fw fa-comment-dots text-secondary mr-2"></i>Feedback: <?php echo $ticket_feedback; ?>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="mt-1">
                                <a href="#"
                                   data-toggle = "ajax-modal"
                                   data-ajax-url = "ajax/ajax_ticket_assign.php"
                                   data-ajax-id = "<?php echo $ticket_id; ?>">
                                    <i class="fas fa-fw fa-user mr-2 text-secondary"></i><?php echo $ticket_assigned_to_display; ?>
                                </a>
                            </div>
                        <?php } ?>
                        <!-- END Ticket closure info -->
                    </div>

                    <div class="col-sm-4">
                        <div>
                            <i class="fa fa-fw fa-thermometer-half text-secondary mr-2"></i>
                            <a href="#"
                                <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_closed_at)) { ?>
                                    data-toggle = "ajax-modal"
                                    data-ajax-url = "ajax/ajax_ticket_priority.php"
                                    data-ajax-id = "<?php echo $ticket_id; ?>"
                                <?php } ?>
                            >
                                <?php echo $ticket_priority_display; ?>
                            </a>
                        </div>
                        <?php
                        // Ticket scheduling
                        if (empty ($ticket_closed_at)) { ?>
                            <div class="mt-1">
                                <i class="fa fa-fw fa-calendar-check text-secondary mr-2"></i>Scheduled: <a href="#" data-toggle="modal" data-target="#editTicketScheduleModal"> <?php echo $ticket_scheduled_wording ?> </a>
                            </div>
                        <?php }

                        // Billable
                        if ($config_module_enable_accounting) { ?>
                            <?php if ($invoice_id && lookupUserPermission("module_sales") >= 1) { ?>
                                <div class="mt-1">
                                    <i class="fa fa-fw fa-dollar-sign text-secondary mr-2"></i>Invoiced: <?php echo "$invoice_prefix$invoice_number"; ?>
                                </div>
                            <?php } elseif (lookupUserPermission("module_sales") >= 1) { ?>
                                <div class="mt-1">
                                    <i class="fa fa-fw fa-dollar-sign text-secondary mr-2"></i>Ticket is
                                    <a href="#"
                                       data-toggle = "ajax-modal"
                                       data-ajax-url = "ajax/ajax_ticket_billable.php"
                                       data-ajax-id = "<?php echo $ticket_id; ?>"
                                    >
                                        <?php
                                        if ($ticket_billable == 1) {
                                            echo "<span class='text-bold text-dark'>Billable</span>";
                                        } else {
                                            echo "<span class='text-muted'>Not Billable</span>";
                                        }
                                        ?>
                                    </a>
                                </div>
                            <?php } // End if Invoice ?>
                        <?php } // End If Accounting mod enabled ?>
                    </div>

                    <div class="col-sm-4">
                        <?php if ($task_count) { ?>
                            Tasks Completed<span class="float-right text-bold"><?php echo $tasks_completed_percent; ?>%</span>
                            <div class="progress mt-2" style="height: 20px;">
                                <div class="progress-bar" style="width: <?php echo $tasks_completed_percent; ?>%;"><?php echo $completed_task_count; ?> / <?php echo $task_count; ?></div>
                            </div>
                        <?php } ?>

                        <?php
                        // Time tracking
                        if ($ticket_total_reply_time) { ?>
                            <div class="mt-1">
                                <i class="far fa-fw fa-clock text-secondary mr-2"></i>Total time worked: <?php echo $ticket_total_reply_time; ?>
                            </div>
                        <?php } ?>

                        <?php if ($ticket_collaborators) { ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-users mr-2 text-secondary"></i><?php echo $ticket_collaborators; ?>
                            </div>
                        <?php } ?>

                        <?php if ($ticket_category > 0) { ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-layer-group mr-2 text-secondary"></i><?php echo $ticket_category_display; ?>
                            </div>
                        <?php } ?>

                        <div class="mt-2">
                            <span class="text-info" id="ticket_collision_viewing"></span>
                        </div>
                    </div>

                </div>
                <br>
            </div>

        </div>

        <div class="row">

            <div class="col-md-9">

                <div class="card card-dark mb-3">

                    <div class="card-header">
                        <h3 class="card-title">
                            Ticket Details
                        </h3>
                    </div>

                    <div class="card-header bg-light">
                        <h3 class="card-title">
                            <span class="text-muted">Subject:</span> <span><?php echo $ticket_subject; ?></span>
                        </h3>
                    </div>

                    <div class="card-body prettyContent" id="ticketDetails">
                        <?php echo $ticket_details; ?>

                        <?php
                        while ($ticket_attachment = mysqli_fetch_array($sql_ticket_attachments)) {
                            $name = nullable_htmlentities($ticket_attachment['ticket_attachment_name']);
                            $ref_name = nullable_htmlentities($ticket_attachment['ticket_attachment_reference_name']);
                            echo "<hr class=''><i class='fas fa-fw fa-paperclip text-secondary mr-1'></i>$name | <a href='uploads/tickets/$ticket_id/$ref_name' download='$name'><i class='fas fa-fw fa-download mr-1'></i>Download</a> | <a target='_blank' href='uploads/tickets/$ticket_id/$ref_name'><i class='fas fa-fw fa-external-link-alt mr-1'></i>View</a>";
                        }
                        ?>
                    </div>

                </div>

                <!-- Only show ticket reply modal if status is not closed -->
                <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_resolved_at) && empty($ticket_closed_at)) { ?>

                    <div class="card card-body d-print-none pb-0">

                        <form action="post.php" method="post" autocomplete="off">
                            <input type="hidden" name="ticket_id" id="ticket_id" value="<?php echo $ticket_id; ?>">
                            <input type="hidden" name="client_id" id="client_id" value="<?php echo $client_id; ?>">

                            <div class="form-group">
                                <div class="btn-group btn-block btn-group-toggle" data-toggle="buttons">
                                    <label class="btn btn-outline-secondary active">
                                        <input type="radio" name="public_reply_type" value="0" checked>Internal Note
                                    </label>
                                    <label class="btn btn-outline-secondary">
                                        <input type="radio" name="public_reply_type" value="2">Public Comment & Email
                                    </label>
                                    <label class="btn btn-outline-secondary">
                                        <input type="radio" name="public_reply_type" value="1">Public Comment
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <textarea class="form-control tinymceTicket<?php if ($config_ai_enable) { echo "AI"; } ?>" id="textInput" name="ticket_reply" placeholder="Type a response"></textarea>
                            </div>

                            <div class="form-row">
                                <div class="col-md-3">
                                    <div class="input-group mb-3">
                                        <select class="form-control select2" name="status" required>

                                            <!-- Show all active ticket statuses, apart from new or closed as these are system-managed -->
                                            <?php
                                            $status_snippet = '';
                                            if ($task_count !== $completed_task_count) {
                                                $status_snippet = "AND ticket_status_id != 4";
                                            }
                                            $sql_ticket_status = mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_id != 1 AND ticket_status_id != 5 AND ticket_status_active = 1 $status_snippet");
                                            while ($row = mysqli_fetch_array($sql_ticket_status)) {
                                                $ticket_status_id_select = intval($row['ticket_status_id']);
                                                $ticket_status_name_select = nullable_htmlentities($row['ticket_status_name']); ?>

                                                <option value="<?php echo $ticket_status_id_select ?>" <?php if ($ticket_status == $ticket_status_id_select) { echo 'selected'; } ?>> <?php echo $ticket_status_name_select ?> </option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Time Tracking -->
                                <div class="col-md-6">
                                    <div class="input-group mb-3">
                                        <div class="input-group-prepend px-0 col-2">
                                            <input type="text" class="form-control" inputmode="numeric" id="hours" name="hours" placeholder="Hrs" min="0" max="23" pattern="0?[0-9]|1[0-9]|2[0-3]">
                                        </div>

                                        <div class="px-0 col-2">
                                            <input type="text" class="form-control" inputmode="numeric" id="minutes" name="minutes" placeholder="Mins" min="0" max="59" pattern="[0-5]?[0-9]">
                                        </div>

                                        <div class="input-group-append px-0 col-2">
                                            <input type="text" class="form-control" inputmode="numeric" id="seconds" name="seconds" placeholder="Secs" min="0" max="59" pattern="[0-5]?[0-9]">
                                        </div>

                                        <div class="btn-group">
                                            <button type="button" class="btn btn-light" id="startStopTimer"><i class="fas fa-play"></i></button>
                                            <button type="button" class="btn btn-light" id="resetTimer"><i class="fas fa-redo-alt"></i></button>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="btn-toolbar float-right">
                                        <button type="submit" id="ticket_add_reply" name="add_ticket_reply" class="btn btn-success ml-3"><i class="fas fa-check mr-2"></i>Submit</button>
                                    </div>
                                </div>

                            </div>

                        </form>
                    </div>
                    <!-- End IF for reply modal -->
                <?php } ?>

                <!-- Ticket Responses -->
                <h6>Responses: <?php echo $ticket_all_comments_count; ?></h6>

                <!-- Ticket replies -->
                <?php

                while ($row = mysqli_fetch_array($sql_ticket_replies)) {
                    $ticket_reply_id = intval($row['ticket_reply_id']);
                    $ticket_reply = $purifier->purify($row['ticket_reply']);
                    $ticket_reply_type = nullable_htmlentities($row['ticket_reply_type']);
                    $ticket_reply_created_at = nullable_htmlentities($row['ticket_reply_created_at']);
                    $ticket_reply_created_at_ago = timeAgo($row['ticket_reply_created_at']);
                    $ticket_reply_updated_at = nullable_htmlentities($row['ticket_reply_updated_at']);
                    $ticket_reply_updated_at_ago = timeAgo($row['ticket_reply_updated_at']);
                    $ticket_reply_by = intval($row['ticket_reply_by']);

                    if ($ticket_reply_type == "Client") {
                        $ticket_reply_by_display = nullable_htmlentities($row['contact_name']);
                        $user_initials = initials($row['contact_name']);
                        $user_avatar = nullable_htmlentities($row['contact_photo']);
                        $avatar_link = "uploads/clients/$client_id/$user_avatar";
                    } else {
                        $ticket_reply_by_display = nullable_htmlentities($row['user_name']);
                        $user_id = intval($row['user_id']);
                        $user_avatar = nullable_htmlentities($row['user_avatar']);
                        $user_initials = initials($row['user_name']);
                        $avatar_link = "uploads/users/$user_id/$user_avatar";
                        $ticket_reply_time_worked = date_create($row['ticket_reply_time_worked']);
                    }

                    $sql_ticket_reply_attachments = mysqli_query(
                        $mysqli,
                        "SELECT * FROM ticket_attachments
                        WHERE ticket_attachment_reply_id = $ticket_reply_id
                        AND ticket_attachment_ticket_id = $ticket_id"
                    );

                    ?>

                    <!-- Begin ticket reply card -->
                    <div class="card border-left border-<?php if ($ticket_reply_type == 'Internal') { echo "dark"; } elseif ($ticket_reply_type == 'Client') { echo "warning"; } else { echo "info"; } ?> mb-3">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center w-100">
                                <!-- Left side content -->
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($user_avatar)) { ?>
                                        <img src="<?php echo $avatar_link; ?>" alt="User Avatar" class="img-size-50 mr-3 img-circle">
                                    <?php } else { ?>
                                        <span class="fa-stack fa-2x">
                                            <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                            <span class="fa fa-stack-1x text-white"><?php echo $user_initials; ?></span>
                                        </span>
                                    <?php } ?>

                                    <div class="ml-3">
                                        <h3 class="card-title"><?php echo $ticket_reply_by_display; ?></h3>
                                        <div>
                                            <?php if ($ticket_reply_type !== "Client") { ?>
                                                <div>
                                                    <br><small class="text-muted">Time worked: <?php echo date_format($ticket_reply_time_worked, 'H:i:s'); ?></small>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right-side content -->
                                <div class="text-right d-flex flex-column align-items-end">
                                    <div class="card-tools d-print-none mb-2">
                                        <div class="dropdown dropleft">
                                            <?php if (lookupUserPermission("module_support") >= 2) { ?>
                                                <button class="btn btn-sm btn-tool" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                                                    <i class="fas fa-fw fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a href="#" class="dropdown-item"
                                                       data-toggle = "ajax-modal"
                                                       data-modal-size = "lg"
                                                       data-ajax-url = "ajax/ajax_ticket_reply_redact.php"
                                                       data-ajax-id = "<?php echo $ticket_reply_id; ?>"
                                                    >
                                                        <i class="fas fa-fw fa-pen text-danger mr-2"></i>Redact
                                                    </a>
                                                    <?php if ($ticket_reply_type !== "Client" && empty($ticket_closed_at)) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a href="#" class="dropdown-item"
                                                       data-toggle = "ajax-modal"
                                                       data-modal-size = "lg"
                                                       data-ajax-url = "ajax/ajax_ticket_reply_edit.php"
                                                       data-ajax-id = "<?php echo $ticket_reply_id; ?>"
                                                    >
                                                        <i class="fas fa-fw fa-edit text-secondary mr-2"></i>Edit
                                                    </a>                                                    
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger confirm-link" href="post.php?archive_ticket_reply=<?php echo $ticket_reply_id; ?>">
                                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                    </a>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <small class="text-muted">
                                        <div title="Created: <?php echo $ticket_reply_created_at; if ($ticket_reply_updated_at) { echo '. Edited: ' . $ticket_reply_updated_at; } ?>">
                                            <?php echo $ticket_reply_type . " - " .  $ticket_reply_created_at_ago; if ($ticket_reply_updated_at) { echo '*'; } ?>
                                        </div>
                                    </small>

                                </div>
                            </div>
                        </div>

                        <div class="card-body prettyContent">
                            <?php echo $ticket_reply; ?>

                            <?php
                            while ($ticket_attachment = mysqli_fetch_array($sql_ticket_reply_attachments)) {
                                $name = nullable_htmlentities($ticket_attachment['ticket_attachment_name']);
                                $ref_name = nullable_htmlentities($ticket_attachment['ticket_attachment_reference_name']);
                                echo "<hr><i class='fas fa-fw fa-paperclip text-secondary mr-1'></i>$name | <a href='uploads/tickets/$ticket_id/$ref_name' download='$name'><i class='fas fa-fw fa-download mr-1'></i>Download</a> | <a target='_blank' href='uploads/tickets/$ticket_id/$ref_name'><i class='fas fa-fw fa-external-link-alt mr-1'></i>View</a>";
                            }
                            ?>
                        </div>
                    </div>
                    <!-- End ticket reply card -->

                    <?php

                }

                ?>

            </div>

            <div class="col-md-3">

                <!-- Contact card -->
                <?php if ($contact_id) { ?>
                    <div class="card card-body mb-3">
                        <h5 class="text-secondary">Contact</h5>
                        <div>
                            <i class="fa fa-fw fa-user text-secondary mr-2"></i><a href="#" data-toggle="ajax-modal"
                                                                                   data-modal-size="lg"
                                                                                   data-ajax-url="ajax/ajax_contact_details.php"
                                                                                   data-ajax-id="<?php echo $contact_id; ?>"><strong><?php echo $contact_name; ?></strong>
                            </a>
                        </div>

                        <?php

                        if (!empty($location_name)) { ?>
                            <div class="mt-2">
                                <i class="fa fa-fw fa-map-marker-alt text-secondary mr-2"></i><?php echo $location_name; ?>
                            </div>
                        <?php }

                        if (!empty($contact_email)) { ?>
                            <div class="mt-2">
                                <i class="fa fa-fw fa-envelope text-secondary mr-2"></i><a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a>
                            </div>
                        <?php }

                        if (!empty($contact_phone)) { ?>
                            <div class="mt-2">
                                <i class="fa fa-fw fa-phone text-secondary mr-2"></i><a href="tel:<?php echo $contact_phone; ?>"><?php echo $contact_phone; ?></a>
                            </div>
                        <?php }

                        if (!empty($contact_mobile)) { ?>
                            <div class="mt-2">
                                <i class="fa fa-fw fa-mobile-alt text-secondary mr-2"></i><a href="tel:<?php echo $contact_mobile; ?>"><?php echo $contact_mobile; ?></a>
                            </div>
                        <?php } ?>

                    </div>
                <?php } else { ?>
                    <div class="card card-body mb-3">
                        <h5 class="text-secondary">Contact</h5>
                        <div>
                            <i class="fa fa-fw fa-user text-secondary mr-2"></i>
                            <a href="#"
                                <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_closed_at)) { ?>
                                    data-toggle = "ajax-modal"
                                    data-ajax-url = "ajax/ajax_ticket_contact.php"
                                    data-ajax-id = "<?php echo $ticket_id; ?>"
                                <?php } ?>
                            >
                                <i>No One</i>
                            </a>
                        </div>
                    </div>
                <?php } ?>
                <!-- End contact card -->


                <!-- Tasks Card -->
                <?php if (empty($ticket_resolved_at) || (!empty($ticket_resolved_at) && $task_count > 0)) { ?>
                    <div class="card card-body">

                        <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <form action="post.php" method="post" autocomplete="off">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                                <div class="form-group">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" name="name" placeholder="Create Task">
                                        <div class="input-group-append">
                                            <button type="submit" name="add_task" class="btn btn-secondary">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        <?php } ?>

                        <table class="table table-sm">
                            <?php
                            while($row = mysqli_fetch_array($sql_tasks)){
                                $task_id = intval($row['task_id']);
                                $task_name = nullable_htmlentities($row['task_name']);
                                //$task_description = nullable_htmlentities($row['task_description']); // not in db yet
                                $task_completion_estimate = intval($row['task_completion_estimate']);
                                $task_completed_at = nullable_htmlentities($row['task_completed_at']);
                                ?>
                                <tr data-task-id="<?php echo $task_id; ?>">
                                    <td>
                                        <?php if ($task_completed_at) { ?>
                                            <i class="far fa-fw fa-check-square text-primary"></i>
                                        <?php } elseif (lookupUserPermission("module_support") >= 2) { ?>
                                            <a href="post.php?complete_task=<?php echo $task_id; ?>">
                                                <i class="far fa-fw fa-square text-secondary"></i>
                                            </a>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <a href="#" class="grab-cursor">
                                            <span class="text-secondary"><?php echo $task_completion_estimate; ?>m</span>
                                            <span class="text-dark"> - <?php echo $task_name; ?></span>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="float-right">
                                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                                                <div class="dropdown dropleft text-center">
                                                    <button class="btn btn-link text-secondary btn-sm" type="button" data-toggle="dropdown">
                                                        <i class="fas fa-fw fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="#"
                                                           data-toggle = "ajax-modal"
                                                           data-ajax-url = "ajax/ajax_ticket_task_edit.php"
                                                           data-ajax-id = "<?php echo $task_id; ?>"
                                                        >
                                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                        </a>
                                                        <?php if ($task_completed_at) { ?>
                                                            <a class="dropdown-item" href="post.php?undo_complete_task=<?php echo $task_id; ?>">
                                                                <i class="fas fa-fw fa-arrow-circle-left mr-2"></i>Mark incomplete
                                                            </a>
                                                        <?php } ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger confirm-link" href="post.php?delete_task=<?php echo $task_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                            <i class="fas fa-fw fa-trash-alt mr-2"></i>Delete
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </table>
                    </div>
                <?php } ?>
                <!-- End Tasks Card -->


                <!-- Ticket watchers card -->
                <?php if (empty($ticket_closed_at) && mysqli_num_rows($sql_ticket_watchers) > 0) { ?>

                    <div class="card card-body card-outline card-dark mb-3">
                        <h5 class="text-secondary">Watchers</h5>

                        <?php
                        // Get Watchers
                        while ($row = mysqli_fetch_array($sql_ticket_watchers)) {
                            $watcher_id = intval($row['watcher_id']);
                            $ticket_watcher_email = nullable_htmlentities($row['watcher_email']);
                            ?>
                            <div class='mt-1'>
                                <i class="fa fa-fw fa-eye text-secondary mr-2"></i><?php echo $ticket_watcher_email; ?>
                                <?php if (empty($ticket_closed_at)) { ?>
                                    <a class="confirm-link float-right" href="post.php?delete_ticket_watcher=<?php echo $watcher_id; ?>">
                                        <i class="fas fa-fw fa-trash-alt text-secondary"></i>
                                    </a>
                                <?php } ?>
                            </div>

                        <?php } ?>
                    </div>
                <?php } ?>
                <!-- End Ticket watchers card -->

                <!-- Asset card -->
                <?php if ($asset_id) { ?>
                    <div class="card card-body mb-3">
                        <h5 class="text-secondary">Asset(s)</h5>
                        <div>
                            <a href="#"
                                data-toggle="ajax-modal"
                                data-modal-size="lg"
                                data-ajax-url="ajax/ajax_asset_details.php?<?php echo $client_url; ?>"
                                data-ajax-id="<?php echo $asset_id; ?>">
                                <i class="fa fa-fw fa-<?php echo $asset_icon; ?> text-secondary mr-2"></i><strong><?php echo $asset_name; ?></strong>
                            </a>
                        </div>
                        <?php
                        while ($row = mysqli_fetch_array($sql_additional_assets)) {
                            $additional_asset_id = intval($row['asset_id']);
                            $additional_asset_name = nullable_htmlentities($row['asset_name']);
                            $additional_asset_type = nullable_htmlentities($row['asset_type']);
                            $additional_asset_icon = getAssetIcon($additional_asset_type);
                            ?>
                            <div class="mt-1">
                                <a href="#"
                                    data-toggle="ajax-modal"
                                    data-modal-size="lg"
                                    data-ajax-url="ajax/ajax_asset_details.php?<?php echo $client_url; ?>"
                                    data-ajax-id="<?php echo $additional_asset_id; ?>">
                                    <i class="fa fa-fw fa-<?php echo $additional_asset_icon; ?> text-secondary mr-2"></i><?php echo $additional_asset_name; ?>
                                </a>
                            </div>
                        <?php

                        }
                        ?>   
                    </div>
                <?php } // End if asset_id ?>
                <!-- End Asset card -->


                <!-- Vendor card -->
                <?php if ($vendor_id) { ?>
                    <div class="card card-body mb-3">
                        <h5 class="text-secondary">Vendor</h5>

                        <div>
                            <i class="fa fa-fw fa-building text-secondary mr-2"></i><strong><?php echo $vendor_name; ?></strong>
                        </div>
                        <?php

                        if (!empty($vendor_contact_name)) { ?>
                            <div class="mt-1">
                                <i class="fa fa-fw fa-user text-secondary mr-2"></i><?php echo $vendor_contact_name; ?>
                            </div>
                        <?php }

                        if (!empty($ticket_vendor_ticket_number)) { ?>
                            <div class="mt-1">
                                <i class="fa fa-fw fa-tag text-secondary mr-2"></i><?php echo $ticket_vendor_ticket_number; ?>
                            </div>
                        <?php }

                        if (!empty($vendor_email)) { ?>
                            <div class="mt-1">
                                <i class="fa fa-fw fa-envelope text-secondary mr-2"></i><a href="mailto:<?php echo $vendor_email; ?>"><?php echo $vendor_email; ?></a>
                            </div>
                        <?php }

                        if (!empty($vendor_phone)) { ?>
                            <div class="mt-1">
                                <i class="fa fa-fw fa-phone text-secondary mr-2"></i><?php echo $vendor_phone; ?>
                            </div>
                        <?php }

                        if (!empty($vendor_website)) { ?>
                            <div class="mt-1">
                                <i class="fa fa-fw fa-globe text-secondary mr-2"></i><?php echo $vendor_website; ?>
                            </div>
                        <?php } ?>

                    </div>
                <?php } //End Else ?>
                <!-- End Vendor card -->

                <!-- project card -->
                <?php if ($project_id) { ?>
                    <div class="card card-body mb-3">
                        <h5 class="text-secondary">Project</h5>
                        <div>
                            <i class="fa fa-fw fa-project-diagram text-secondary mr-3"></i><a href="project_details.php?project_id=<?php echo $project_id; ?>" target="_blank"><strong><?php echo $project_name; ?><i class="fa fa-fw fa-external-link-alt text-secondary ml-2"></i></strong>
                            </a>
                        </div>

                        <?php if ($project_manager) { ?>
                            <div class="mt-2">
                                <i class="fa fa-fw fa-user-tie text-secondary mr-3"></i><?php echo $project_manager_name; ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
                <!-- End project card -->

            </div> <!-- End col-3 -->

        </div> <!-- End row -->

        <?php
        if (lookupUserPermission("module_support") >= 2 && empty($ticket_closed_at)) {
            require_once "modals/ticket_edit_asset_modal.php";
            require_once "modals/ticket_edit_vendor_modal.php";
            require_once "modals/ticket_add_watcher_modal.php";
            require_once "modals/ticket_change_client_modal.php";
            require_once "modals/ticket_edit_schedule_modal.php";
            require_once "modals/ticket_merge_modal.php";
        }

        if (lookupUserPermission("module_support") >= 2 && lookupUserPermission("module_sales") >= 2 && $config_module_enable_accounting) {
            require_once "modals/ticket_invoice_add_modal.php";
        }
    }
}

require_once "includes/footer.php";

?>

<!-- Summary Modal -->
<div class="modal fade" id="summaryModal" tabindex="-1">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title" id="summaryModalTitle">Ticket Summary</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">
                <div id="summaryContent" class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> Generating summary...
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/show_modals.js"></script>

<?php if (empty($ticket_closed_at)) { ?>
    <!-- Ticket Time Tracking JS -->
    <script src="js/ticket_time_tracking.js"></script>

    <!-- Ticket collision detect JS (jQuery is called in footer, so collision detection script MUST be below it) -->
    <script src="js/ticket_collision_detection.js"></script>
<?php } ?>

<script src="js/pretty_content.js"></script>

<script>
    $('#summaryModal').on('shown.bs.modal', function (e) {
        // Perform AJAX request to get the summary
        $.ajax({
            url: 'post.php?ai_ticket_summary',
            method: 'POST',
            data: { ticket_id: <?php echo $ticket_id; ?> },
            success: function(response) {
                $('#summaryContent').html(response);
            },
            error: function() {
                $('#summaryContent').html('Error generating summary.');
            }
        });
    });
</script>


<script src="plugins/dragula/dragula.min.js"></script>
<script>
    $(document).ready(function() {
        var container = $('.table tbody')[0];

        dragula([container])
            .on('drop', function (el, target, source, sibling) {
                // Handle the drop event to update the order in the database
                var rows = $(container).children();
                var positions = rows.map(function(index, row) {
                    return {
                        id: $(row).data('taskId'),
                        order: index
                    };
                }).get();

                //console.log('New positions:', positions);

                // Send the new order to the server (example using fetch)
                $.ajax({
                    url: 'ajax.php',
                    method: 'POST',
                    data: {
                        update_ticket_tasks_order: true,
                        ticket_id: <?php echo $ticket_id; ?>,
                        positions: positions
                    },
                    success: function(data) {
                        //console.log('Order updated:', data);
                    },
                    error: function(error) {
                        console.error('Error updating order:', error);
                    }
                });
            });
    });
</script>
