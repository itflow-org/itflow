<?php

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_url = '';
}

// Ticket client access overide - This is the only way to show tickets without a client to agents with restricted client access
$access_permission_query_overide = '';
if ($client_access_string) {
    $access_permission_query_overide = "AND ticket_client_id IN (0,$client_access_string)";
}

// Perms
enforceUserPermission('module_support');

// Initialize the HTML Purifier to prevent XSS
require_once "../libs/htmlpurifier/HTMLPurifier.standalone.php";

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
        LEFT JOIN quotes ON ticket_quote_id = quote_id
        LEFT JOIN invoices ON ticket_invoice_id = invoice_id
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        LEFT JOIN categories ON ticket_category = category_id
        WHERE ticket_id = $ticket_id
        $access_permission_query_overide
        LIMIT 1"
    );

    if (mysqli_num_rows($sql) == 0) {
        echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='tickets.php'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

        require_once "../includes/footer.php";
    } else {

        $row = mysqli_fetch_assoc($sql);
        $client_id = intval($row['client_id']);
        $client_name = escapeHtml($row['client_name']);
        $client_type = escapeHtml($row['client_type']);
        $client_website = escapeHtml($row['client_website']);

        $client_net_terms = intval($row['client_net_terms']);
        if ($client_net_terms == 0) {
            $client_net_terms = $config_default_net_terms;
        }

        $client_rate = floatval($row['client_rate']);

        $ticket_prefix = escapeHtml($row['ticket_prefix']);
        $ticket_number = intval($row['ticket_number']);
        $ticket_source = escapeHtml($row['ticket_source']);
        $ticket_category = intval($row['ticket_category']);
        $ticket_category_display = escapeHtml($row['category_name']);
        $ticket_subject = escapeHtml($row['ticket_subject']);
        $ticket_details = $purifier->purify($row['ticket_details']);
        $ticket_priority = escapeHtml($row['ticket_priority']);
        $ticket_billable = intval($row['ticket_billable']);
        $ticket_scheduled_for = escapeHtml($row['ticket_schedule']);
        $ticket_onsite = intval($row['ticket_onsite']);
        if ($ticket_scheduled_for) {
            $ticket_scheduled_wording = "$ticket_scheduled_for";
        } else {
            $ticket_scheduled_wording = "Add";
        }

        //Set Ticket Badge Color based of priority
        if ($ticket_priority == "High") {
            $ticket_priority_display = "<span class='p-2 badge badge-pill badge-danger'>$ticket_priority</span>";
        } elseif ($ticket_priority == "Medium") {
            $ticket_priority_display = "<span class='p-2 badge badge-pill badge-warning'>$ticket_priority</span>";
        } elseif ($ticket_priority == "Low") {
            $ticket_priority_display = "<span class='p-2 badge badge-pill badge-info'>$ticket_priority</span>";
        } else {
            $ticket_priority_display = "";
        }
        $ticket_feedback = escapeHtml($row['ticket_feedback']);

        $ticket_status = intval($row['ticket_status_id']);
        $ticket_status_id = intval($row['ticket_status_id']);
        $ticket_status_name = escapeHtml($row['ticket_status_name']);
        $ticket_status_color = escapeHtml($row['ticket_status_color']);

        $ticket_vendor_ticket_number = escapeHtml($row['ticket_vendor_ticket_number']);
        $ticket_created_at = escapeHtml($row['ticket_created_at']);
        $ticket_created_at_ago = timeAgo($row['ticket_created_at']);
        $ticket_created_by = intval($row['ticket_created_by']);
        $ticket_date = date('Y-m-d', strtotime($ticket_created_at));
        $ticket_updated_at = escapeHtml($row['ticket_updated_at']);
        $ticket_updated_at_ago = timeAgo($row['ticket_updated_at']);
        $ticket_first_response_at = escapeHtml($row['ticket_first_response_at']);
        $ticket_resolved_at = escapeHtml($row['ticket_resolved_at']);
        $ticket_resolved_at_ago = timeAgo($row['ticket_resolved_at']);
        $ticket_resolved_date = date('Y-m-d', strtotime($ticket_resolved_at));
        $ticket_closed_at = escapeHtml($row['ticket_closed_at']);
        $ticket_closed_at_ago = timeAgo($row['ticket_closed_at']);
        $ticket_closed_date = date('Y-m-d', strtotime($ticket_closed_at));
        $ticket_closed_by = intval($row['ticket_closed_by']);

        $ticket_assigned_to = intval($row['ticket_assigned_to']);
        if (empty($ticket_assigned_to)) {
            $ticket_assigned_to_display = "<span class='badge badge-pill badge-light'>Unassigned</span>";
        } else {
            $ticket_assigned_to_display = escapeHtml($row['user_name']);
        }

        // Tab Title // No Sanitizing needed
        $page_title = $row['ticket_subject'];
        $tab_title = "{$row['ticket_prefix']}{$row['ticket_number']}";

        $contact_id = intval($row['contact_id']);
        $contact_name = escapeHtml($row['contact_name']);
        $contact_title = escapeHtml($row['contact_title']);
        $contact_email = escapeHtml($row['contact_email']);
        $contact_phone_country_code = escapeHtml($row['contact_phone_country_code']);
        $contact_phone = escapeHtml(formatPhoneNumber($row['contact_phone'], $contact_phone_country_code));
        $contact_extension = escapeHtml($row['contact_extension']);
        $contact_mobile_country_code = escapeHtml($row['contact_mobile_country_code']);
        $contact_mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'], $contact_mobile_country_code));

        $asset_id = intval($row['asset_id']);
        $asset_ip = escapeHtml($row['interface_ip']);
        $asset_name = escapeHtml($row['asset_name']);
        $asset_type = escapeHtml($row['asset_type']);
        $asset_uri = escapeHtml($row['asset_uri']);
        $asset_make = escapeHtml($row['asset_make']);
        $asset_model = escapeHtml($row['asset_model']);
        $asset_serial = escapeHtml($row['asset_serial']);
        $asset_os = escapeHtml($row['asset_os']);
        $asset_warranty_expire = escapeHtml($row['asset_warranty_expire']);
        $asset_icon = getAssetIcon($asset_type);

        $vendor_id = intval($row['ticket_vendor_id']);
        $vendor_name = escapeHtml($row['vendor_name']);
        $vendor_description = escapeHtml($row['vendor_description']);
        $vendor_account_number = escapeHtml($row['vendor_account_number']);
        $vendor_contact_name = escapeHtml($row['vendor_contact_name']);
        $vendor_phone_country_code = escapeHtml($row['vendor_phone_country_code']);
        $vendor_phone = escapeHtml(formatPhoneNumber($row['vendor_phone'], $vendor_phone_country_code));
        $vendor_extension = escapeHtml($row['vendor_extension']);
        $vendor_email = escapeHtml($row['vendor_email']);
        $vendor_website = escapeHtml($row['vendor_website']);
        $vendor_hours = escapeHtml($row['vendor_hours']);
        $vendor_sla = escapeHtml($row['vendor_sla']);
        $vendor_code = escapeHtml($row['vendor_code']);
        $vendor_notes = escapeHtml($row['vendor_notes']);

        $location_id = intval($row['location_id']);
        $location_name = escapeHtml($row['location_name']);
        $location_address = escapeHtml($row['location_address']);
        $location_city = escapeHtml($row['location_city']);
        $location_state = escapeHtml($row['location_state']);
        $location_zip = escapeHtml($row['location_zip']);
        $location_phone = formatPhoneNumber($row['location_phone']);

        $quote_id = intval($row['ticket_quote_id']);
        $quote_prefix = escapeHtml($row['quote_prefix']);
        $quote_number = intval($row['quote_number']);
        $quote_created_at = escapeHtml($row['quote_created_at']);

        $invoice_id = intval($row['ticket_invoice_id']);
        $invoice_prefix = escapeHtml($row['invoice_prefix']);
        $invoice_number = intval($row['invoice_number']);
        $invoice_created_at = escapeHtml($row['invoice_created_at']);

        $project_id = intval($row['project_id']);
        $project_prefix = escapeHtml($row['project_prefix']);
        $project_number = intval($row['project_number']);
        $project_name = escapeHtml($row['project_name']);
        $project_description = escapeHtml($row['project_description']);
        $project_due = escapeHtml($row['project_due']);
        $project_manager = escapeHtml($row['project_manager']);

        if($project_manager) {
            $sql_project_manager = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $project_manager");
            $row = mysqli_fetch_assoc($sql_project_manager);
            $project_manager_name = escapeHtml($row['user_name']);
        }

        if ($contact_id) {
            //Get Contact Ticket Stats
            $ticket_related_open = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_related_open FROM tickets WHERE ticket_status != 'Closed' AND ticket_contact_id = $contact_id ");
            $row = mysqli_fetch_assoc($ticket_related_open);
            $ticket_related_open = intval($row['ticket_related_open']);

            $ticket_related_closed = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_related_closed  FROM tickets WHERE ticket_status = 'Closed' AND ticket_contact_id = $contact_id ");
            $row = mysqli_fetch_assoc($ticket_related_closed);
            $ticket_related_closed = intval($row['ticket_related_closed']);

            $ticket_related_total = mysqli_query($mysqli, "SELECT COUNT(ticket_id) AS ticket_related_total FROM tickets WHERE ticket_contact_id = $contact_id ");
            $row = mysqli_fetch_assoc($ticket_related_total);
            $ticket_related_total = intval($row['ticket_related_total']);
        }

        //Get Total Ticket Time
        $ticket_total_reply_time = mysqli_query($mysqli, "SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) AS ticket_total_reply_time FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_total_reply_time);
        $ticket_total_reply_time = escapeHtml($row['ticket_total_reply_time']);

        // Get the number of ticket Responses
        $ticket_responses_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_responses FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_responses_sql);
        $ticket_responses = intval($row['ticket_responses']);

        $ticket_all_comments_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_all_comments_count FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_all_comments_sql);
        $ticket_all_comments_count = intval($row['ticket_all_comments_count']);

        $ticket_internal_notes_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_internal_notes_count FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_type = 'Internal' AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_internal_notes_sql);
        $ticket_internal_notes_count = intval($row['ticket_internal_notes_count']);

        $ticket_public_comments_sql = mysqli_query($mysqli, "SELECT COUNT(ticket_reply_id) AS ticket_public_comments_count FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND (ticket_reply_type = 'Public' OR ticket_reply_type = 'Client') AND ticket_reply_ticket_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_public_comments_sql);
        $ticket_public_comments_count = intval($row['ticket_public_comments_count']);

        $ticket_events_sql = mysqli_query($mysqli, "SELECT COUNT(log_id) AS ticket_events_count FROM logs WHERE log_type = 'Ticket' AND  log_entity_id = $ticket_id");
        $row = mysqli_fetch_assoc($ticket_events_sql);
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
        $ticket_collaborators = escapeHtml($row['user_names']);

        ?>

        <!-- Breadcrumbs-->
        <ol class="breadcrumb d-print-none">
             <li class="breadcrumb-item">
                <a href="tickets.php">All Tickets</a>
            </li>
            <?php if ($client_url) { ?>
            <li class="breadcrumb-item">
                <a href="tickets.php?client_id=<?php echo $client_id; ?>"><?= $client_name ?> Tickets</a>
            </li>
            <?php } ?>
            <li class="breadcrumb-item active"><?php echo "$ticket_prefix$ticket_number";?></li>
        </ol>

        <div class="card">
            <div class="card-header pb-2">
                <div class="card-title">
                    <div class="media">
                        <i class="fa fa-fw fa-2x fa-life-ring mr-2"></i>
                        <div class="media-body">
                            <div class="text-bold">Ticket <?= "$ticket_prefix$ticket_number" ?>
                                <span class='badge badge-pill text-light ml-1 p-2' style="background-color: <?= $ticket_status_color ?>">
                                    <?= $ticket_status_name ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (lookupUserPermission("module_support") >= 2) { ?>
                    <div class="card-tools d-print-none">
                        <div class="btn-toolbar">

                            <?php if ($config_module_enable_accounting && $ticket_billable == 1 && empty($quote_id) && empty($invoice_id) && lookupUserPermission("module_sales") >= 2) { ?>
                            <a href="#" class="btn btn-light btn-sm ml-3 ajax-modal" href="#" data-modal-url="modals/ticket/ticket_quote_add.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                                <i class="fas fa-fw fa-comment-dollar mr-2"></i>Quote
                            </a>
                            <?php }

                            if ($config_module_enable_accounting && $ticket_billable == 1 && empty($invoice_id) && lookupUserPermission("module_sales") >= 2) { ?>
                                <a href="#" class="btn btn-light btn-sm ml-3 ajax-modal" href="#" data-modal-url="modals/ticket/ticket_invoice_add.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                                    <i class="fas fa-fw fa-file-invoice mr-2"></i>Invoice
                                </a>
                            <?php }

                            if (empty($ticket_closed_at)) { ?>

                                <?php if (empty($ticket_closed_at) && !empty($ticket_resolved_at)) { ?>
                                    <a href="post.php?reopen_ticket=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-light btn-sm ml-3">
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

                                <div class="dropdown dropleft text-center ml-3 mr-2">
                                    <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                                        <i class="fas fa-fw fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_summary.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                                            <i class="fas fa-fw fa-lightbulb mr-2"></i>Summarize
                                        </a>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_merge.php?ticket_id=<?= $ticket_id ?>">
                                            <i class="fas fa-fw fa-clone mr-2"></i>Merge Ticket
                                        </a>
                                        <?php if (empty($ticket_closed_at) && $client_id) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item ajax-modal" href="#"
                                                data-modal-url="modals/ticket/ticket_contact.php?id=<?= $ticket_id ?>">
                                                <i class="fa fa-fw fa-user mr-2"></i>Add Contact
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_edit_asset.php?id=<?= $ticket_id ?>">
                                                <i class="fas fa-fw fa-desktop mr-2"></i>Add Asset
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_edit_vendor.php?ticket_id=<?= $ticket_id ?>">
                                                <i class="fas fa-fw fa-building mr-2"></i>Add Vendor
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add_watcher.php?ticket_id=<?= $ticket_id ?>">
                                                <i class="fas fa-fw fa-users mr-2"></i>Add Watcher
                                            </a>
                                        <?php } ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item ajax-modal" href="#" id="clientChangeTicketModalLoad" data-modal-url="modals/ticket/ticket_change_client.php?ticket_id=<?= $ticket_id ?>">
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
        </div> <!-- End Card -->

        <div class="card-group mb-3">

            <div class="card card-body">

                <?php if ($ticket_updated_at) { ?>
                <div title="<?= $ticket_updated_at ?>">
                    <i class="fa fa-fw fa-history text-secondary mr-2"></i>Updated: <strong><?= date('M d, Y • g:i A', strtotime($ticket_updated_at)) . "</strong> <span class='text-muted small'>($ticket_updated_at_ago)</span>" ?>
                </div>
                <?php } ?>
                <!-- Ticket assign (disable if closed -->
                <?php if (empty($ticket_closed_at)) { ?>
                    <div class="mt-1">
                        <i class="fas fa-fw fa-user-tie mr-2 text-secondary"></i>Agent:
                        <a class="ajax-modal" href="#"
                            data-modal-url="modals/ticket/ticket_assign.php?id=<?= $ticket_id ?>">
                            <?= $ticket_assigned_to_display ?>
                        </a>
                    </div>
                <?php } else { ?>
                    <div class="mt-1">
                        <i class="fas fa-fw fa-user-check mr-2 text-secondary"></i>Agent: <?php echo $ticket_assigned_to_display; ?>
                    </div>
                <?php } ?>
                <!-- End ticket assign -->
                <div class="mt-1">
                    <span class="text-info" id="ticket_collision_viewing"></span>
                </div>
            </div>

            <div class="card card-body">
                <div>
                    <a href="#" title="Priority"
                        <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_closed_at)) { ?>
                            class="ajax-modal"
                            data-modal-url="modals/ticket/ticket_priority.php?id=<?= $ticket_id ?>"
                        <?php } ?>
                    >
                        <?= $ticket_priority_display ?>
                    </a>
                </div>

                <!-- Ticket scheduling -->
                <?php if (empty ($ticket_closed_at)) { ?>
                    <div class="mt-1">
                        <i class="fa fa-fw fa-calendar-check text-secondary mr-2"></i>Scheduled: <a class='ajax-modal' href="#" data-modal-url="modals/ticket/ticket_edit_schedule.php?ticket_id=<?= $ticket_id ?>"> <?=$ticket_scheduled_wording ?> </a>
                    </div>
                <?php } ?>
                <!-- End ticket scheduling -->

                <!-- Billable -->
                <?php if ($config_module_enable_accounting && lookupUserPermission("module_sales") >= 1) { ?>

                    <?php if ($quote_id) { ?>
                        <div class="mt-1">
                            <i class="fa fa-fw fa-comment-dollar text-secondary mr-2"></i>Quoted: <a href="quote.php?quote_id=<?php echo $quote_id ?>"><?php echo "$quote_prefix$quote_number"; ?></a>
                        </div>
                    <?php } ?>

                    <?php if ($invoice_id) { ?>
                        <div class="mt-1">
                            <i class="fa fa-fw fa-dollar-sign text-secondary mr-2"></i>Invoiced: <a href="invoice.php?invoice_id=<?php echo $invoice_id ?>"><?php echo "$invoice_prefix$invoice_number"; ?></a>
                        </div>
                    <?php } else { ?>
                        <div class="mt-1">
                            <i class="fa fa-fw fa-dollar-sign text-secondary mr-2"></i>Billable:
                            <a class="ajax-modal" href="#"
                               data-modal-url="modals/ticket/ticket_billable.php?id=<?= $ticket_id ?>">
                                <?php
                                if ($ticket_billable == 1) {
                                    echo "<span class='text-bold text-dark'>Yes</span>";
                                } else {
                                    echo "<span class='text-muted'>No</span>";
                                }
                                ?>
                            </a>
                        </div>
                    <?php } ?>

                <?php } ?>
                <!-- End billable options -->

            </div>

            <div class="card card-body">
                <?php if ($task_count) { ?>
                    <div><strong>Tasks</strong> <?= "$completed_task_count/$task_count ($tasks_completed_percent%)" ?></div>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" style="width: <?php echo $tasks_completed_percent; ?>%;"></div>
                    </div>
                <?php } ?>
            </div>

        </div>

        <div class="row">

            <div class="col-md-9">

                <div class="card card-dark mb-3">

                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1"><?= $ticket_subject ?></h5>
                        <?php if (empty($ticket_closed_at)) { ?>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool ajax-modal" data-modal-url="modals/ticket/ticket_edit.php?id=<?= $ticket_id ?>" data-modal-size="lg"><i class="fas fa-edit"></i></button>
                        </div>
                        <?php } ?>
                    </div>

                    <div class="card-body p-3 prettyContent" id="ticketDetails">
                        <?php echo $ticket_details; ?>

                        <table class="table-sm">

                        <?php
                        while ($ticket_attachment = mysqli_fetch_assoc($sql_ticket_attachments)) {
                            $ticket_attachment_id = intval($ticket_attachment['ticket_attachment_id']);
                            $name = escapeHtml($ticket_attachment['ticket_attachment_name']);

                            ?>

                            <tr>
                                <td><i class='fas fa-fw fa-paperclip text-secondary mr-1'></i><?= $name ?></td>
                                <td>
                                    <a target='_blank' class='mr-1 ml-1' href='ticket_attachment.php?attachment_id=<?= $ticket_attachment_id; ?>&action=view'>[View]</a><a href='ticket_attachment.php?attachment_id=<?= $ticket_attachment_id; ?>'>[Download]</a>
                                </td>
                            </tr>
                            
                         <?php  
                        }
                        ?>
                        </table>
                    </div>

                </div>

                <!-- Only show ticket reply modal if status is not closed -->
                <?php if (lookupUserPermission("module_support") >= 2 && empty($ticket_resolved_at) && empty($ticket_closed_at)) { ?>

                        <form action="post.php" method="post" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="ticket_id" id="ticket_id" value="<?php echo $ticket_id; ?>">

                            <div class="card card-body d-print-none p-3">

                                <div class="form-group mb-0">
                                    <div class="btn-group btn-block btn-group-toggle" data-toggle="buttons">
                                        <label class="btn btn-outline-dark active">
                                            <input type="radio" name="public_reply_type" value="0" checked>Internal
                                        </label>
                                        <?php if ($contact_email) { ?>
                                        <label class="btn btn-outline-info">
                                            <input type="radio" name="public_reply_type" value="2">Public + Email
                                        </label>
                                        <?php } ?>
                                        <label class="btn btn-outline-info">
                                            <input type="radio" name="public_reply_type" value="1">Public
                                        </label>
                                    </div>
                                </div>

                            </div>

                            <div class="form-group">
                                <textarea
                                    class="form-control tinymceTicket" name="ticket_reply"
                                    placeholder="Type a response">
                                </textarea>
                            </div>

                            <div class="form-row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <div class="input-group">
                                            <select class="form-control select2" name="status" required>

                                                <!-- Show all active ticket statuses, apart from new or closed as these are system-managed -->
                                                <?php
                                                $status_snippet = '';
                                                if ($task_count !== $completed_task_count) {
                                                    $status_snippet = "AND ticket_status_id != 4";
                                                }
                                                $sql_ticket_status = mysqli_query($mysqli, "SELECT * FROM ticket_statuses WHERE ticket_status_id != 1 AND ticket_status_id != 5 AND ticket_status_active = 1 $status_snippet ORDER BY ticket_status_order");
                                                while ($row = mysqli_fetch_assoc($sql_ticket_status)) {
                                                    $ticket_status_id_select = intval($row['ticket_status_id']);
                                                    $ticket_status_name_select = escapeHtml($row['ticket_status_name']); ?>

                                                    <option value="<?php echo $ticket_status_id_select ?>" <?php if ($ticket_status == $ticket_status_id_select) { echo 'selected'; } ?>> <?php echo $ticket_status_name_select ?> </option>

                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Time Tracking -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="input-group">
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
                                </div>

                                <div class="col-md-3">
                                    <div class="btn-toolbar float-right mb-3">
                                        <button type="submit" id="ticket_add_reply" name="add_ticket_reply" class="btn btn-success ml-3"><i class="fas fa-check mr-2"></i>Submit</button>
                                    </div>
                                </div>

                            </div>

                        </form>

                    <!-- End IF for reply modal -->
                <?php } ?>

                <!-- Ticket replies -->
                <?php

                while ($row = mysqli_fetch_assoc($sql_ticket_replies)) {
                    $ticket_reply_id = intval($row['ticket_reply_id']);
                    $ticket_reply = $purifier->purify($row['ticket_reply']);
                    $ticket_reply_type = escapeHtml($row['ticket_reply_type']);
                    $ticket_reply_created_at = escapeHtml($row['ticket_reply_created_at']);
                    $ticket_reply_created_at_ago = timeAgo($row['ticket_reply_created_at']);
                    $ticket_reply_updated_at = escapeHtml($row['ticket_reply_updated_at']);
                    $ticket_reply_updated_at_ago = timeAgo($row['ticket_reply_updated_at']);
                    $ticket_reply_by = intval($row['ticket_reply_by']);

                    if ($ticket_reply_type == "Client") {
                        $ticket_reply_by_display = escapeHtml($row['contact_name']);
                        $user_initials = initials($row['contact_name']);
                        $user_avatar = escapeHtml($row['contact_photo']);
                        $avatar_link = "../uploads/clients/$client_id/$user_avatar";
                    } else {
                        $ticket_reply_by_display = escapeHtml($row['user_name']);
                        $user_id = intval($row['user_id']);
                        $user_avatar = escapeHtml($row['user_avatar']);
                        $user_initials = initials($row['user_name']);
                        $avatar_link = "../uploads/users/$user_id/$user_avatar";
                        $ticket_reply_time_worked = $row['ticket_reply_time_worked'];
                    }

                    $sql_ticket_reply_attachments = mysqli_query(
                        $mysqli,
                        "SELECT * FROM ticket_attachments
                        WHERE ticket_attachment_reply_id = $ticket_reply_id
                        AND ticket_attachment_ticket_id = $ticket_id"
                    );

                    ?>

                    <!-- Begin ticket reply card -->
                    <div class="card border-left border-<?php if ($ticket_reply_type == 'Internal') { echo "dark"; } elseif ($ticket_reply_type == 'Client') { echo "warning"; } else { echo "info"; } ?> mb-3" style="border-left-width: 8px !important;">
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

                                    <div class="ml-2">
                                        <h3 class="card-title"><?php echo $ticket_reply_by_display; ?></h3>
                                        <div>
                                            <?php if ($ticket_reply_type !== "Client" && $ticket_reply_time_worked !== "00:00:00") { ?>
                                                <div>
                                                    <br>
                                                    <small>
                                                        <i class="far fa-fw fa-clock text-secondary"></i>
                                                        Time worked:
                                                        <span class="text-muted">
                                                            <?= formatDuration($ticket_reply_time_worked) ?>
                                                        </span>
                                                    </small>
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
                                                    <a href="#" class="dropdown-item ajax-modal"
                                                       data-modal-size = "lg"
                                                       data-modal-url="modals/ticket/ticket_reply_redact.php?id=<?= $ticket_reply_id ?>">
                                                        <i class="fas fa-fw fa-pen text-danger mr-2"></i>Redact
                                                    </a>
                                                    <?php if ($ticket_reply_type !== "Client" && empty($ticket_closed_at)) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a href="#" class="dropdown-item ajax-modal"
                                                       data-modal-size = "lg"
                                                       data-modal-url="modals/ticket/ticket_reply_edit.php?id=<?=$ticket_reply_id ?>">
                                                        <i class="fas fa-fw fa-edit text-secondary mr-2"></i>Edit
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger confirm-link" href="post.php?archive_ticket_reply=<?= $ticket_reply_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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

                            <table class="table-sm">

                            <?php
                            while ($ticket_attachment = mysqli_fetch_assoc($sql_ticket_reply_attachments)) {
                                $ticket_attachment_id = intval($ticket_attachment['ticket_attachment_id']);
                                $name = escapeHtml($ticket_attachment['ticket_attachment_name']);

                                ?>

                                <tr>
                                    <td><i class='fas fa-fw fa-paperclip text-secondary mr-1'></i><?= $name ?></td>
                                    <td>
                                        <a target='_blank' class='mr-1 ml-1' href='ticket_attachment.php?attachment_id=<?= $ticket_attachment_id; ?>&action=view'>[View]</a><a href='ticket_attachment.php?attachment_id=<?= $ticket_attachment_id; ?>'>[Download]</a>
                                    </td>
                                </tr>
                                
                             <?php  
                            }
                            ?>
                            </table>
                        </div>
                    </div>
                    <!-- End ticket reply card -->

                    <?php

                }

                ?>

            </div>

            <div class="col-md-3">

                <!-- Ticket activity right card -->
                <div class="card">
                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1"><i class="fas fa-fw fa-history mr-2"></i>Activity Summary</h5>

                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-3 ">

                        <!-- Created -->
                        <div>
                            <i class="fas fa-fw fa-calendar-alt text-secondary mr-1"></i><strong class="mr-1">Created:</strong><?= date('M d, Y', strtotime($ticket_date)) ?>
                            <span class="text-muted small">(<?= $ticket_created_at_ago ?>)</span>
                        </div>

                        <!-- Created by -->
                        <?php if ($ticket_created_by) {
                            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = $ticket_created_by"));
                            $ticket_created_by_display = escapeHtml($row['user_name']);
                            ?>

                            <div class="mt-2">
                                <i class="far fa-fw fa-user text-secondary mr-1"></i><strong class="mr-1">Created by:</strong><?= $ticket_created_by_display ?>
                            </div>
                        <?php } ?>

                        <!-- Source -->
                        <?php if ($ticket_source) { ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-inbox text-secondary mr-1"></i><strong class="mr-1">Source:</strong><?= $ticket_source ?>
                            </div>
                        <?php } ?>

                        <!-- Category -->
                        <?php if ($ticket_category) { ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-layer-group text-secondary mr-1"></i><strong class="mr-1">Category:</strong><?= $ticket_category_display ?>
                            </div>
                        <?php } ?>

                        <!-- First response (for SLA) -->
                        <?php if ($ticket_first_response_at) { ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-reply-all text-secondary mr-1"></i><strong class="mr-1">1st  resp:</strong><?= date('M d • g:i A', strtotime($ticket_first_response_at)) ?>
                            </div>
                        <?php } ?>

                        <!-- Time tracking -->
                        <?php if ($ticket_total_reply_time) { ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-stopwatch text-secondary mr-1"></i><strong class="mr-1">Total time:</strong><?= formatDuration($ticket_total_reply_time) ?>
                            </div>
                        <?php } ?>

                        <!-- Internal collaborators -->
                        <!-- Commented - there is still something wrong with this -->
<!--                        --><?php //if ($ticket_collaborators) { ?>
<!--                            <div class="mt-1">-->
<!--                                <i class="fas fa-fw fa-users mr-2 text-secondary"></i><strong>Collaborators: </strong>--><?php //echo $ticket_collaborators; ?>
<!--                            </div>-->
<!--                        --><?php //} ?>

                        <!-- Resolved -->
                        <?php if ($ticket_resolved_at) { ?>
                            <hr>
                            <div class="mt-2" title="<?= $ticket_resolved_at ?>">
                                <i class="fas fa-fw fa-check text-secondary mr-1"></i><strong class="mr-1">Resolved:</strong><?= date('M d, Y • g:i A', strtotime($ticket_resolved_at)) . " ($ticket_resolved_at_ago)" ?>
                            </div>
                        <?php } ?>

                        <!-- Ticket closure info -->
                        <?php if ($ticket_closed_at) {

                            $ticket_closed_by_display = 'User';
                            if (!empty($ticket_closed_by)) {
                                $sql_closed_by = mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = $ticket_closed_by");
                                $row = mysqli_fetch_assoc($sql_closed_by);
                                $ticket_closed_by_display = escapeHtml($row['user_name']);
                            }
                            ?>
                            <div class="mt-2">
                                <i class="fas fa-fw fa-user text-secondary mr-1"></i><strong class="mr-1">Closed by:</strong><?= ucwords($ticket_closed_by_display) ?>
                            </div>

                            <div class="mt-2">
                                <i class="fas fa-fw fa-clock text-secondary mr-1"></i><strong class="mr-1">Closed:</strong><?= date('M d, Y • g:i A', strtotime($ticket_closed_at)) . " ($ticket_closed_at_ago)" ?>
                            </div>

                            <?php if ($ticket_feedback) { ?>
                                <div class="mt-2">
                                    <i class="fa fa-fw fa-comment-dots text-secondary mr-1"></i><strong>Feedback: </strong><?php echo $ticket_feedback; ?>
                                </div>
                            <?php } ?>

                        <?php } ?>
                        <!-- END Ticket closure info -->

                    </div>
                </div>
                <!-- End details card -->

                <!-- Tasks Card -->
                <?php if (empty($ticket_resolved_at) || (!empty($ticket_resolved_at) && $task_count > 0)) { ?>
                    <div class="card">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-tasks mr-2"></i>Tasks</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-tool" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item text-success" href="post.php?complete_all_tasks=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-check-double mr-2"></i>Mark All Complete
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="post.php?undo_complete_all_tasks=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="far fa-fw fa-square mr-2"></i>Mark All Incomplete
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="#">
                                            <i class="fas fa-fw fa-trash-alt mr-2"></i>Delete All
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-0">

                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                                <form action="post.php" method="post" autocomplete="off">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                                    <div class="form-group px-2 pt-3">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control" name="name" placeholder="Create Task" required maxlength="255">
                                            <div class="input-group-append">
                                                <button type="submit" name="add_task" class="btn btn-outline-primary">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            <?php } ?>

                            <table class="table table-sm" id="tasks">
                                <?php
                                while ($row = mysqli_fetch_assoc($sql_tasks)) {
                                    $task_id = intval($row['task_id']);
                                    $task_name = escapeHtml($row['task_name']);
                                    $task_completion_estimate = intval($row['task_completion_estimate']);
                                    $task_completed_at = escapeHtml($row['task_completed_at']);

                                    // Check for approvals
                                    $task_needs_approval = false;
                                    $task_needs_approval = mysqli_num_rows(mysqli_query(
                                            $mysqli,
                                            "SELECT 1 FROM task_approvals
                                                 WHERE approval_task_id = $task_id
                                                   AND approval_status IN ('pending','declined')
                                                 LIMIT 1"
                                        )) > 0;

                                    $approval_id = 0;
                                    $user_can_approve = false;
                                    $approval_rows = mysqli_query($mysqli, "
                                        SELECT approval_id, approval_scope, approval_type, approval_required_user_id, approval_created_by
                                        FROM task_approvals WHERE approval_task_id = $task_id AND approval_status = 'pending'
                                    ");

                                    while ($approval = mysqli_fetch_assoc($approval_rows)) {

                                        $scope = escapeHtml($approval['approval_scope']);
                                        $type = escapeHtml($approval['approval_type']);
                                        $required_user = intval($approval['approval_required_user_id']);
                                        $created_by = intval($approval['approval_created_by']);

                                        // Named, specific user?
                                        if ($scope == 'internal' && $type == 'specific' && $required_user == $session_user_id) {
                                            $user_can_approve = true;
                                            $approval_id = intval($approval['approval_id']);
                                            continue;
                                        }

                                        // Any internal user, but the one who created the task
                                        if ($scope == 'internal' && $type == 'any' && $created_by !== $session_user_id) {
                                            $user_can_approve = true;
                                            $approval_id = intval($approval['approval_id']);
                                            continue;
                                        }

                                    }

                                    ?>
                                    <tr data-task-id="<?= $task_id ?>">
                                        <td class="px-3">
                                            <?php if ($task_completed_at) { ?>
                                                <i class="far fa-check-square text-success"></i>
                                            <?php } elseif (lookupUserPermission("module_support") >= 2) { ?>

                                                <?php if ($task_needs_approval) { ?>
                                                    <i class="fas fa-shield-alt text-warning"
                                                       data-toggle="tooltip"
                                                       data-placement="top"
                                                       title="Approval required"></i>

                                                    <?php if ($user_can_approve) { ?>
                                                        <a class="confirm-link" href="post.php?approve_ticket_task=<?= $task_id ?>&approval_id=<?= $approval_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                            <i class="fas fa-thumbs-up text-green" title="Approve task"></i>
                                                        </a>
                                                    <?php } ?>

                                                <?php } else { ?>
                                                    <a href="post.php?complete_task=<?= $task_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="far fa-square text-dark"></i>
                                                    </a>
                                                <?php } ?>

                                            <?php } ?>
                                            <span class="text-dark ml-2"><?= $task_name ?></span>
                                        </td>
                                        <td class="px-2">
                                            <div class="float-right">

                                                <div class="btn-group">

                                                    <button class="btn btn-sm btn-link drag-handle"><i class="fas fa-bars text-muted mr-1"></i></button>

                                                    <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>

                                                        <div class="dropdown dropleft text-center">
                                                            <button class="btn btn-light text-secondary btn-sm" type="button" data-toggle="dropdown">
                                                                <i class="fas fa-ellipsis-v"></i>
                                                            </button>
                                                            <div class="dropdown-menu">
                                                                <a class="dropdown-item ajax-modal" href="#"
                                                                   data-modal-url="modals/ticket/ticket_task_edit.php?id=<?= $task_id ?>">
                                                                    <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                                </a>
                                                                <?php if (!$task_completed_at) { ?>
                                                                    <a class="dropdown-item ajax-modal" href="#"
                                                                       data-modal-url="modals/ticket/ticket_task_approver_add.php?id=<?= $task_id ?>">
                                                                        <i class="fas fa-fw fa-shield-alt mr-2"></i>Add Approvers
                                                                    </a>
                                                                <?php } ?>
                                                                <?php if ($task_completed_at) { ?>
                                                                    <a class="dropdown-item" href="post.php?undo_complete_task=<?= $task_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </table>
                        </div>
                    </div>
                <?php } ?>
                <!-- End Tasks Card -->

                <!-- Contact card -->
                <?php if ($contact_id) { ?>
                    <div class="card">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-user-check mr-2"></i>Contact</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <a class="btn btn-tool ajax-modal" href="#"
                                    data-modal-url="modals/ticket/ticket_contact.php?id=<?= $ticket_id ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">

                            <div>
                                <i class="fa fa-fw fa-user text-secondary mr-2"></i><a href="#" class="ajax-modal"
                                   data-modal-size="lg"
                                   data-modal-url="modals/contact/contact.php?id=<?= $contact_id ?>"><strong><?= $contact_name ?></strong>
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
                    </div>
                <?php } ?>
                <!-- End contact card -->

                <!-- Ticket watchers card -->
                <?php if (empty($ticket_closed_at) && mysqli_num_rows($sql_ticket_watchers) > 0) { ?>

                    <div class="card">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-eye mr-2"></i>Watchers</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <a class="btn btn-tool ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add_watcher.php?ticket_id=<?= $ticket_id ?>">
                                    <i class="fas fa-fw fa-user-plus"></i>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">

                            <?php
                            // Get Watchers
                            while ($row = mysqli_fetch_assoc($sql_ticket_watchers)) {
                                $watcher_id = intval($row['watcher_id']);
                                $ticket_watcher_email = escapeHtml($row['watcher_email']);
                                ?>
                                <div class='mt-1'>
                                    <i class="fa fa-fw fa-envelope text-secondary mr-2"></i><?php echo $ticket_watcher_email; ?>
                                    <?php if (empty($ticket_closed_at)) { ?>
                                        <a class="confirm-link float-right" href="post.php?delete_ticket_watcher=<?= $watcher_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-times text-secondary"></i>
                                        </a>
                                    <?php } ?>
                                </div>

                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
                <!-- End Ticket watchers card -->

                <!-- Asset card -->
                <?php if ($asset_id) { ?>
                    <div class="card mb-3">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-desktop mr-2"></i>Assets</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <a class="btn btn-tool ajax-modal" href="#" data-modal-url="modals/ticket/ticket_edit_asset.php?id=<?= $ticket_id ?>">
                                    <i class="fas fa-fw fa-edit"></i>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">
                            <div>
                                <a class="ajax-modal" href="#" data-modal-size="lg"
                                    data-modal-url="modals/asset/asset.php?<?= $client_url ?>&id=<?= $asset_id ?>">
                                    <i class="fa fa-fw fa-<?php echo $asset_icon; ?> text-secondary mr-2"></i><strong><?php echo $asset_name; ?></strong>
                                </a>
                            </div>
                            <?php
                            while ($row = mysqli_fetch_assoc($sql_additional_assets)) {
                                $additional_asset_id = intval($row['asset_id']);
                                $additional_asset_name = escapeHtml($row['asset_name']);
                                $additional_asset_type = escapeHtml($row['asset_type']);
                                $additional_asset_icon = getAssetIcon($additional_asset_type);
                                ?>
                                <div class="mt-1">
                                    <a class="ajax-modal" href="#" data-modal-size="lg"
                                        data-modal-url="modals/asset/asset.php?<?= $client_url ?>&id=<?= $additional_asset_id ?>">
                                        <i class="fa fa-fw fa-<?php echo $additional_asset_icon; ?> text-secondary mr-2"></i><?php echo $additional_asset_name; ?>
                                    </a>
                                    <?php if (empty($ticket_closed_at)) { ?>
                                        <a class="confirm-link float-right" href="post.php?delete_ticket_additional_asset=<?= $additional_asset_id; ?>&ticket_id=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Remove asset from ticket">
                                            <i class="fas fa-fw fa-times text-secondary"></i>
                                        </a>
                                    <?php } ?>
                                </div>
                            <?php

                            }
                            ?>
                        </div>
                    </div>
                <?php } // End if asset_id ?>
                <!-- End Asset card -->

                <!-- Vendor card -->
                <?php if ($vendor_id) { ?>
                    <div class="card mb-3">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-building mr-2"></i>Vendor</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <a class="btn btn-tool ajax-modal" href="#" data-modal-url="modals/ticket/ticket_edit_vendor.php?ticket_id=<?= $ticket_id ?>">
                                    <i class="fas fa-fw fa-edit"></i>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">

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
                    </div>
                <?php } //End Else ?>
                <!-- End Vendor card -->

                <!-- project card -->
                <?php if ($project_id) { ?>
                    <div class="card">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-project-diagram mr-2"></i>Project</h5>
                            <?php if (empty($ticket_resolved_at) && lookupUserPermission("module_support") >= 2) { ?>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool ajax-modal" data-modal-url="modals/ticket/ticket_edit_project.php?id=<?= $ticket_id ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">
                            <div>
                                <i class="fa fa-fw fa-project-diagram text-secondary mr-2"></i><a href="project.php?project_id=<?php echo $project_id; ?>" target="_blank"><strong><?= $project_name ?><i class="fa fa-fw fa-external-link-alt ml-1"></i></strong>
                                </a>
                            </div>

                            <?php if ($project_manager) { ?>
                                <div class="mt-2">
                                    <i class="fa fa-fw fa-user-tie text-secondary mr-2"></i><?= $project_manager_name ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
                <!-- End project card -->

            </div> <!-- End col-3 -->

        </div> <!-- End row -->

    <?php
    }
}

require_once "../includes/footer.php";

?>

<script src="/js/show_modals.js"></script>

<?php if (empty($ticket_closed_at)) { ?>
    <!-- create js variable related to ticket timer setting -->
    <script type="text/javascript">
        var ticketAutoStart = <?php echo json_encode($config_ticket_timer_autostart); ?>;
    </script>

    <!-- Ticket Time Tracking JS -->
    <script src="js/ticket_time_tracking.js"></script>

    <!-- Ticket collision detect JS (jQuery is called in footer, so collision detection script MUST be below it) -->
    <script src="js/ticket_collision_detection.js"></script>
<?php } ?>

<script src="/js/pretty_content.js"></script>

<script src="/libs/SortableJS/Sortable.min.js"></script>
<script>
new Sortable(document.querySelector('table#tasks tbody'), {
    handle: '.drag-handle',
    animation: 150,
    onEnd: function (evt) {
        const rows = document.querySelectorAll('table#tasks tbody tr');
        const positions = Array.from(rows).map((row, index) => ({
            id: row.dataset.taskId,
            order: index
        }));

        $.post('ajax.php', {
            update_ticket_tasks_order: true,
            csrf_token: '<?= $_SESSION['csrf_token'] ?>',
            ticket_id: <?php echo $ticket_id; ?>,
            positions: positions
        });
    }
});
</script>
