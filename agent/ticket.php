<?php

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    $client_url = '';
}

// Tickets with no client stay visible to restricted agents - clientScopeSql() includes 0
$access_permission_query_overide = clientScopeSql('ticket_client_id');

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

        $ticket = mysqli_fetch_assoc($sql);

        $client_id = intval($ticket['client_id']);
        $client_name = escapeHtml($ticket['client_name']);

        $client_net_terms = intval($ticket['client_net_terms']);
        if ($client_net_terms == 0) {
            $client_net_terms = $config_default_net_terms;
        }

        $ticket_prefix = escapeHtml($ticket['ticket_prefix']);
        $ticket_number = intval($ticket['ticket_number']);
        $ticket_reference = "$ticket_prefix$ticket_number";
        $ticket_source = escapeHtml($ticket['ticket_source']);
        $ticket_category = intval($ticket['ticket_category']);
        $ticket_category_display = escapeHtml($ticket['category_name']);
        $ticket_subject = escapeHtml($ticket['ticket_subject']);
        $ticket_details = $purifier->purify($ticket['ticket_details']);
        $ticket_priority = escapeHtml($ticket['ticket_priority']);
        $ticket_billable = intval($ticket['ticket_billable']);
        $ticket_scheduled_for = escapeHtml($ticket['ticket_schedule']);
        $ticket_onsite = intval($ticket['ticket_onsite']);
        if ($ticket_scheduled_for) {
            $ticket_scheduled_wording = date('M d, Y • g:i A', strtotime($ticket_scheduled_for));
        } else {
            $ticket_scheduled_wording = "Not scheduled";
        }

        // Set Ticket Badge Color based of priority
        if ($ticket_priority == "Urgent") {
            $ticket_priority_color = "dark";
        } elseif ($ticket_priority == "High") {
            $ticket_priority_color = "danger";
        } elseif ($ticket_priority == "Medium") {
            $ticket_priority_color = "warning";
        } else {
            $ticket_priority_color = "info";
        }
        $ticket_feedback = escapeHtml($ticket['ticket_feedback']);

        $ticket_status = intval($ticket['ticket_status_id']);
        $ticket_status_id = intval($ticket['ticket_status_id']);
        $ticket_status_name = escapeHtml($ticket['ticket_status_name']);
        $ticket_status_color = escapeHtml($ticket['ticket_status_color']);

        $ticket_vendor_ticket_number = escapeHtml($ticket['ticket_vendor_ticket_number']);
        $ticket_created_at = escapeHtml($ticket['ticket_created_at']);
        $ticket_created_at_ago = timeAgo($ticket['ticket_created_at']);
        $ticket_created_by = intval($ticket['ticket_created_by']);
        $ticket_date = date('Y-m-d', strtotime($ticket_created_at));
        $ticket_updated_at = escapeHtml($ticket['ticket_updated_at']);
        $ticket_updated_at_ago = timeAgo($ticket['ticket_updated_at']);
        $ticket_first_response_at = escapeHtml($ticket['ticket_first_response_at']);
        $ticket_sla_id = intval($ticket['ticket_sla_id']);
        $ticket_response_due_at = escapeHtml($ticket['ticket_response_due_at']);
        $ticket_resolution_due_at = escapeHtml($ticket['ticket_resolution_due_at']);
        $ticket_response_sla_met = $ticket['ticket_response_sla_met'];
        $ticket_resolution_sla_met = $ticket['ticket_resolution_sla_met'];
        $ticket_sla_paused = intval($ticket['ticket_status_pauses_sla']);
        $ticket_sla_name = "None";
        if ($ticket_sla_id) {
            $sla_name_sql = mysqli_query($mysqli, "SELECT sla_name FROM slas WHERE sla_id = $ticket_sla_id");
            if (mysqli_num_rows($sla_name_sql)) {
                $ticket_sla_name = escapeHtml(mysqli_fetch_assoc($sla_name_sql)['sla_name']);
            }
        }
        $ticket_resolved_at = escapeHtml($ticket['ticket_resolved_at']);
        $ticket_resolved_at_ago = timeAgo($ticket['ticket_resolved_at']);
        $ticket_closed_at = escapeHtml($ticket['ticket_closed_at']);
        $ticket_closed_at_ago = timeAgo($ticket['ticket_closed_at']);
        $ticket_closed_by = intval($ticket['ticket_closed_by']);

        // The three states the page keys its controls off
        $ticket_is_closed = !empty($ticket_closed_at);
        $ticket_is_resolved = !empty($ticket_resolved_at);
        $ticket_is_open = !$ticket_is_resolved && !$ticket_is_closed;
        $can_edit_ticket = lookupUserPermission("module_support") >= 2;

        $ticket_assigned_to_display = formatAssignee($ticket['ticket_assigned_to'], $ticket['user_name'], $ticket['user_avatar'], $ticket_is_open);

        // Tab Title // No Sanitizing needed
        $page_title = $ticket['ticket_subject'];
        $tab_title = "{$ticket['ticket_prefix']}{$ticket['ticket_number']}";

        $contact_id = intval($ticket['contact_id']);
        $contact_client_id = intval($ticket['contact_client_id']);
        $contact_name = escapeHtml($ticket['contact_name']);
        $contact_photo = escapeHtml($ticket['contact_photo']);
        $contact_title = escapeHtml($ticket['contact_title']);
        $contact_email = escapeHtml($ticket['contact_email']);
        $contact_phone_country_code = escapeHtml($ticket['contact_phone_country_code']);
        $contact_phone = escapeHtml(formatPhoneNumber($ticket['contact_phone'], $contact_phone_country_code));
        $contact_extension = escapeHtml($ticket['contact_extension']);
        $contact_mobile_country_code = escapeHtml($ticket['contact_mobile_country_code']);
        $contact_mobile = escapeHtml(formatPhoneNumber($ticket['contact_mobile'], $contact_mobile_country_code));

        $asset_id = intval($ticket['asset_id']);
        $asset_name = escapeHtml($ticket['asset_name']);
        $asset_type = escapeHtml($ticket['asset_type']);
        $asset_icon = getAssetIcon($asset_type);

        $vendor_id = intval($ticket['ticket_vendor_id']);
        $vendor_name = escapeHtml($ticket['vendor_name']);
        $vendor_contact_name = escapeHtml($ticket['vendor_contact_name']);
        $vendor_phone_country_code = escapeHtml($ticket['vendor_phone_country_code']);
        $vendor_phone = escapeHtml(formatPhoneNumber($ticket['vendor_phone'], $vendor_phone_country_code));
        $vendor_email = escapeHtml($ticket['vendor_email']);
        $vendor_website = escapeHtml($ticket['vendor_website']);

        $location_id = intval($ticket['location_id']);
        $location_name = escapeHtml($ticket['location_name']);
        $location_phone = formatPhoneNumber($ticket['location_phone']);

        $quote_id = intval($ticket['ticket_quote_id']);
        $quote_prefix = escapeHtml($ticket['quote_prefix']);
        $quote_number = intval($ticket['quote_number']);
        // The quote row may have been deleted out from under the ticket
        $quote_reference = $quote_number ? "$quote_prefix$quote_number" : "#$quote_id";

        $invoice_id = intval($ticket['ticket_invoice_id']);
        $invoice_prefix = escapeHtml($ticket['invoice_prefix']);
        $invoice_number = intval($ticket['invoice_number']);
        // Same for the invoice - fall back to the id rather than rendering a bare 0
        $invoice_reference = $invoice_number ? "$invoice_prefix$invoice_number" : "#$invoice_id";

        $project_id = intval($ticket['project_id']);
        $project_name = escapeHtml($ticket['project_name']);
        $project_manager = intval($ticket['project_manager']);
        $project_manager_name = '';
        if ($project_manager) {
            $project_manager_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = $project_manager"));
            $project_manager_name = escapeHtml($project_manager_row['user_name']);
        }
        // How far along the project it is part of is - and where its page is
        $project_stats = $project_id ? projectStats([$project_id])[$project_id] : null;
        $project_url = $project_id ? escapeHtml(projectUrl($project_id, $ticket['project_client_id'])) : '';

        $ticket_created_by_display = '';
        if ($ticket_created_by) {
            $created_by_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = $ticket_created_by"));
            $ticket_created_by_display = escapeHtml($created_by_row['user_name']);
        }

        $ticket_closed_by_display = '';
        if ($ticket_is_closed) {
            $ticket_closed_by_display = 'User';
            if (!empty($ticket_closed_by)) {
                $closed_by_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_name FROM users WHERE user_id = $ticket_closed_by"));
                $ticket_closed_by_display = escapeHtml($closed_by_row['user_name']);
            }
        }

        /*
         * How much else this contact has open. The old queries compared the
         * integer ticket_status column against the string 'Closed', which MySQL
         * casts to 0 - so "open" matched every ticket and "closed" matched none.
         * Resolution state lives in ticket_resolved_at, same as the ticket list.
         */
        // The other open tickets for the same person or device - a duplicate, or a
        // problem with something bigger behind it, shows up while working this one
        $also_open = empty($ticket['ticket_resolved_at']) && empty($ticket['ticket_closed_at'])
            ? ticketAlsoOpen($contact_id, intval($ticket['ticket_asset_id']), $ticket_id, 6) : [];

        // Reply counts, one pass - these drive the conversation filter
        $reply_counts = array('total' => 0, 'public' => 0, 'internal' => 0);
        $sql_reply_counts = mysqli_query(
            $mysqli,
            "SELECT ticket_reply_type, COUNT(ticket_reply_id) AS reply_count
            FROM ticket_replies
            WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id
            GROUP BY ticket_reply_type"
        );
        while ($reply_count_row = mysqli_fetch_assoc($sql_reply_counts)) {
            $reply_count = intval($reply_count_row['reply_count']);
            $reply_counts['total'] += $reply_count;
            if ($reply_count_row['ticket_reply_type'] == 'Internal') {
                $reply_counts['internal'] += $reply_count;
            } else {
                $reply_counts['public'] += $reply_count;
            }
        }

        // Get Total Ticket Time
        $ticket_total_reply_time = escapeHtml(mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(ticket_reply_time_worked))) AS ticket_total_reply_time
            FROM ticket_replies WHERE ticket_reply_archived_at IS NULL AND ticket_reply_ticket_id = $ticket_id"
        ))['ticket_total_reply_time']);

        /*
         * Agents who have worked this ticket. The old query joined every reply's
         * ticket_reply_by against users, but a Client reply stores a CONTACT id
         * there - so any contact whose id collided with a user id showed up as a
         * collaborator. That is the bug the old commented-out block ran into.
         */
        $ticket_collaborators = escapeHtml(mysqli_fetch_assoc(mysqli_query(
            $mysqli,
            "SELECT GROUP_CONCAT(DISTINCT user_name ORDER BY user_name SEPARATOR ', ') AS user_names
            FROM ticket_replies
            INNER JOIN users ON ticket_reply_by = user_id
            WHERE ticket_reply_archived_at IS NULL
            AND ticket_reply_ticket_id = $ticket_id
            AND ticket_reply_type != 'Client'"
        ))['user_names']);

        // Get ticket replies
        $sql_ticket_replies = mysqli_query($mysqli, "SELECT contact_name, contact_photo, ticket_reply, ticket_reply_by, ticket_reply_created_at, ticket_reply_id,
            ticket_reply_time_worked, ticket_reply_type, ticket_reply_updated_at, user_avatar, user_id,
            user_name FROM ticket_replies
            LEFT JOIN users ON ticket_reply_by = user_id
            LEFT JOIN contacts ON ticket_reply_by = contact_id
            WHERE ticket_reply_ticket_id = $ticket_id
            AND ticket_reply_archived_at IS NULL
            ORDER BY ticket_reply_id DESC"
        );

        /*
         * Every attachment on the ticket in one query, split by reply. The page
         * used to fire one attachment query per reply as it rendered.
         */
        $ticket_attachments = array();       // attachments on the ticket itself
        $reply_attachments = array();        // attachments keyed by reply id
        $sql_all_attachments = mysqli_query(
            $mysqli,
            "SELECT ticket_attachment_id, ticket_attachment_name, ticket_attachment_reply_id
            FROM ticket_attachments WHERE ticket_attachment_ticket_id = $ticket_id"
        );
        while ($attachment_row = mysqli_fetch_assoc($sql_all_attachments)) {
            $attachment_reply_id = intval($attachment_row['ticket_attachment_reply_id']);
            if ($attachment_reply_id) {
                $reply_attachments[$attachment_reply_id][] = $attachment_row;
            } else {
                $ticket_attachments[] = $attachment_row;
            }
        }

        // Get Watchers
        $sql_ticket_watchers = mysqli_query($mysqli, "SELECT watcher_email, watcher_id FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id ORDER BY watcher_email DESC");
        $watcher_count = mysqli_num_rows($sql_ticket_watchers);

        // Get Additional Assets
        $sql_additional_assets = mysqli_query($mysqli, "SELECT assets.asset_id, asset_name, asset_type FROM assets, ticket_assets
            WHERE assets.asset_id = ticket_assets.asset_id
            AND ticket_id = $ticket_id
            AND assets.asset_id != $asset_id"
        );

        // Get Tasks
        $sql_tasks = mysqli_query($mysqli, "SELECT task_completed_at, task_completion_estimate, task_id, task_name FROM tasks WHERE task_ticket_id = $ticket_id ORDER BY task_order ASC, task_id ASC");
        $task_count = mysqli_num_rows($sql_tasks);

        $completed_task_count = intval(mysqli_fetch_row(mysqli_query(
            $mysqli,
            "SELECT COUNT(task_id) FROM tasks WHERE task_ticket_id = $ticket_id AND task_completed_at IS NOT NULL"
        ))[0]);

        // Tasks Completed Percent
        $tasks_completed_percent = 0;
        if ($task_count) {
            $tasks_completed_percent = round(($completed_task_count / $task_count) * 100);
        }

        // Tasks still open block resolving the ticket - the page says so rather than just hiding the button
        $tasks_block_resolve = $task_count !== $completed_task_count;

        /*
         * All approvals for this ticket's tasks in one query. The task loop used
         * to run two approval queries per row.
         */
        $task_approvals = array();
        if ($task_count) {
            $sql_task_approvals = mysqli_query(
                $mysqli,
                "SELECT approval_id, approval_task_id, approval_scope, approval_type,
                    approval_required_user_id, approval_created_by, approval_status
                FROM task_approvals
                INNER JOIN tasks ON approval_task_id = task_id
                WHERE task_ticket_id = $ticket_id
                AND approval_status IN ('pending','declined')"
            );
            while ($approval_row = mysqli_fetch_assoc($sql_task_approvals)) {
                $task_approvals[intval($approval_row['approval_task_id'])][] = $approval_row;
            }
        }

        /*
         * Ticket history. agent/post/ticket.php has been writing priority, SLA,
         * contact, watcher and asset changes here all along - the page fetched
         * the rows and then never rendered them.
         */
        $sql_ticket_history = mysqli_query($mysqli, "SELECT ticket_history_description, ticket_history_created_at
            FROM ticket_history
            WHERE ticket_history_ticket_id = $ticket_id
            ORDER BY ticket_history_id DESC"
        );
        $ticket_history_count = mysqli_num_rows($sql_ticket_history);

        /*
         * The single most useful thing on the page: which clock is running and
         * how long is left. Everything else about the SLA is detail.
         */
        $sla_state = '';        // breached | at_risk | paused | met | running
        $sla_label = '';
        $sla_class = '';
        $sla_icon = 'fa-stopwatch';

        /*
         * Only surface the SLA field when SLAs are actually in play - either this
         * ticket has one, or plans exist to assign. Mirrors $sla_filter_in_use on
         * agent/tickets.php so an install that does not use SLAs never sees them.
         * The count is only queried when the ticket has no SLA of its own.
         */
        $sla_in_use = $ticket_sla_id > 0;
        if (!$sla_in_use && $can_edit_ticket) {
            $sla_in_use = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(sla_id) FROM slas WHERE sla_archived_at IS NULL"))[0] > 0;
        }
        if ($ticket_sla_id) {
            $sla_next_due = empty($ticket_first_response_at) ? $ticket_response_due_at : $ticket_resolution_due_at;
            $sla_next_label = empty($ticket_first_response_at) ? 'First response due' : 'Resolution due';
            $ticket_sla_alert_stage = max(intval($ticket['ticket_response_sla_alert_stage']), intval($ticket['ticket_resolution_sla_alert_stage']));
            $sla_breached = (!is_null($ticket_response_sla_met) && !$ticket_response_sla_met)
                || (!is_null($ticket_resolution_sla_met) && !$ticket_resolution_sla_met)
                || $ticket_sla_alert_stage == 2;

            if ($sla_breached) {
                $sla_state = 'breached';
                $sla_label = 'SLA breached';
                $sla_class = 'text-bg-danger';
                $sla_icon = 'fa-exclamation-triangle';
            } elseif (!$ticket_is_open) {
                $sla_state = 'met';
                $sla_label = 'SLA met';
                $sla_class = 'text-bg-success';
                $sla_icon = 'fa-check';
            } elseif ($ticket_sla_paused) {
                $sla_state = 'paused';
                $sla_label = 'SLA paused';
                $sla_class = 'text-bg-secondary';
                $sla_icon = 'fa-pause';
            } elseif (!empty($sla_next_due)) {
                $sla_overdue = strtotime($sla_next_due) < time();
                $sla_state = $sla_overdue ? 'breached' : 'running';
                $sla_label = $sla_next_label . ' ' . timeAgo($sla_next_due);
                $sla_class = $sla_overdue ? 'text-bg-danger' : 'text-bg-light border';
            }
        }

        // The Details panel already says "SLA", so the state badge drops the word - and an
        // overdue clock says so plainly rather than "Resolution due 1 minute ago"
        $sla_state_label = str_replace(array('SLA breached', 'SLA met', 'SLA paused'), array('Breached', 'Met', 'Paused'), $sla_label);
        if ($sla_state == 'breached' && !empty($sla_overdue)) {
            $sla_state_label = (empty($ticket_first_response_at) ? 'First response' : 'Resolution') . ' overdue';
        }

        // Due date from the edit modal - it was saved and never shown anywhere
        $ticket_due_at = escapeHtml($ticket['ticket_due_at']);
        $ticket_due_overdue = $ticket_due_at && $ticket_is_open && strtotime($ticket_due_at) < time();

        // How the ticket arrived, in words rather than the raw source value ("via Agent")
        if ($ticket_source == 'Email') {
            $ticket_opened_wording = "Came in by email $ticket_created_at_ago";
        } elseif ($ticket_source == 'Portal') {
            $ticket_opened_wording = "Submitted in the client portal $ticket_created_at_ago";
        } elseif ($ticket_source == 'Recurring') {
            $ticket_opened_wording = "Created $ticket_created_at_ago from a recurring ticket";
        } elseif ($ticket_source == 'API') {
            $ticket_opened_wording = "Created $ticket_created_at_ago through the API";
        } elseif ($ticket_source && $ticket_source !== 'Agent') {
            $ticket_opened_wording = "Opened $ticket_created_at_ago via $ticket_source";
        } else {
            $ticket_opened_wording = "Opened $ticket_created_at_ago" . ($ticket_created_by_display ? " by $ticket_created_by_display" : '');
        }

        /*
         * Statuses an agent can pick by hand, shared by the status menu in the
         * Details panel and the reply form. New and Closed are system-managed.
         */
        $ticket_status_options = array();
        if ($can_edit_ticket && $ticket_is_open) {
            $sql_ticket_status_options = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name, ticket_status_color FROM ticket_statuses WHERE ticket_status_id != 1 AND ticket_status_id != 5 AND ticket_status_active = 1 ORDER BY ticket_status_order, ticket_status_id");
            while ($status_option_row = mysqli_fetch_assoc($sql_ticket_status_options)) {
                $ticket_status_options[] = $status_option_row;
            }
        }

        // An emailed reply needs an address to go to and mail set up to send it -
        // without mail configured the option used to save as public and email nobody
        $can_email_reply = !empty($contact_email) && !empty($config_smtp_provider);

        // The Resolve confirmation says what resolving does - resolve_ticket emails the
        // contact when client notifications are on, and the nightly job closes it later
        $resolve_confirm_text = '';
        if (!empty($config_smtp_provider) && $config_ticket_client_general_notifications == 1 && $contact_email) {
            $resolve_confirm_text .= "$contact_name is emailed that it is resolved. ";
        }
        if ($config_ticket_autoclose_hours > 0) {
            $resolve_confirm_text .= "It closes on its own after $config_ticket_autoclose_hours hours unless the client replies.";
        }

        // Only offer the summary when an AI model is actually set up for it
        $ai_summary_available = (bool) getAiModel('Tickets');

        // Billing actions, decided once - the header shows Invoice once the work is done
        $show_quote_button = $can_edit_ticket && $config_module_enable_accounting && $ticket_billable == 1 && empty($quote_id) && empty($invoice_id) && lookupUserPermission("module_sales") >= 2;
        $show_invoice_button = $can_edit_ticket && $config_module_enable_accounting && $ticket_billable == 1 && empty($invoice_id) && lookupUserPermission("module_sales") >= 2;

        // Trips - the driving done for it: anyone working it can log theirs (functions/trips.php)
        $show_trips = $config_module_enable_accounting && $client_id > 0;
        $ticket_trips = $show_trips ? tripsFor('trip_ticket_id', $ticket_id, 5) : [];
        [$ticket_trip_count, $ticket_trip_miles] = $show_trips ? tripTotalsFor('trip_ticket_id', $ticket_id) : [0, 0.0];

        // Values in the Details panel are links only when they can actually be changed
        $can_change_fields = $can_edit_ticket && !$ticket_is_closed;

        $ticket_tags_display = tagPills('ticket', $ticket_id, tagsForRecord('ticket', $ticket_id), $client_id ? "tickets.php?client_id=$client_id&" : 'tickets.php?', $can_change_fields);

        ?>

        <link rel="stylesheet" href="css/ticket.css">

        <!-- Collision detection reads this - keep it outside the reply form so it exists on resolved tickets too -->
        <input type="hidden" id="ticket_id" value="<?= $ticket_id ?>">

        <!--
            Two columns: the conversation on the left, everything ABOUT the ticket on
            the right. On a phone they stack as header, Details, conversation, then the
            rest of the sidebar - ticket.css pulls the Details card up above the
            conversation so status and priority are never at the bottom of the page.
        -->
        <div class="ticket-layout">

            <!-- Header - which ticket this is, and the one or two things you do to it -->
            <div class="ticket-layout-header card mb-3">
                <div class="card-body">
                    <div class="d-flex flex-wrap flex-md-nowrap align-items-start gap-3">

                        <div class="flex-grow-1" style="min-width: 0;">

                            <div class="ticket-meta mb-1">
                                <span class="badge rounded-pill text-bg-dark ticket-ref me-1"><?= $ticket_reference ?></span>
                                <?php if ($client_id) { ?>
                                    <a href="client_overview.php?client_id=<?= $client_id ?>"><?= $client_name ?></a>
                                <?php } else { ?>
                                    No client
                                <?php } ?>
                                <?php if ($contact_id) { ?>
                                    <i class="fas fa-fw fa-angle-right"></i><?= contactLink($contact_client_id, $contact_id, $contact_name, $contact_photo) ?>
                                <?php } ?>
                            </div>

                            <h4 class="ticket-subject mb-1">
                                <?= $ticket_subject ?>
                                <?php if ($can_change_fields) { ?>
                                    <a href="#" class="btn btn-tool ajax-modal" data-modal-url="modals/ticket/ticket_edit.php?id=<?= $ticket_id ?>" data-modal-size="lg" title="Edit ticket">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php } ?>
                            </h4>

                            <div class="ticket-meta">
                                <?= $ticket_opened_wording ?>
                                <?php if ($ticket_updated_at) { ?>
                                    <span class="mx-1">&middot;</span><span title="<?= $ticket_updated_at ?>">Updated <?= $ticket_updated_at_ago ?></span>
                                <?php } ?>
                                <span class="text-info ms-1" id="ticket_collision_viewing"></span>
                            </div>
                            <?php if ($ticket_tags_display) { ?>
                                <div class="mt-2 d-print-none"><?= $ticket_tags_display ?></div>
                            <?php } ?>

                        </div>

                        <!--
                            One primary action for the stage the ticket is at. Billing and
                            the rarer actions live in the menu; Invoice comes out of it once
                            the work is done, which is when it is actually wanted.
                        -->
                        <?php if ($can_edit_ticket || $ai_summary_available || $show_trips) { ?>
                            <div class="ticket-actions d-flex flex-wrap align-items-center gap-2 d-print-none">

                                <?php if ($can_edit_ticket && $ticket_is_open) { ?>
                                    <?php if ($tasks_block_resolve) {
                                        $open_task_count = $task_count - $completed_task_count;
                                        ?>
                                        <!-- A disabled button gets no pointer events, so the reason sits on a wrapper that does -->
                                        <span class="d-inline-block" tabindex="0" title="Finish the <?= $open_task_count ?> open task<?= $open_task_count == 1 ? '' : 's' ?> first">
                                            <button type="button" class="btn btn-success" disabled>
                                                <i class="fas fa-fw fa-check me-2"></i>Resolve
                                            </button>
                                        </span>
                                    <?php } else { ?>
                                        <a href="post.php?resolve_ticket=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            class="btn btn-success confirm-link" id="ticket_close"
                                            data-confirm-title="Resolve this ticket?"
                                            data-confirm-text="<?= $resolve_confirm_text ?>"
                                            data-confirm-button="Resolve">
                                            <i class="fas fa-fw fa-check me-2"></i>Resolve
                                        </a>
                                    <?php } ?>
                                <?php } ?>

                                <?php if ($can_edit_ticket && $ticket_is_resolved && !$ticket_is_closed) { ?>
                                    <a href="post.php?reopen_ticket=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-light border">
                                        <i class="fas fa-fw fa-redo me-2"></i>Reopen
                                    </a>
                                    <a href="post.php?close_ticket=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        class="btn btn-dark confirm-link" id="ticket_close"
                                        data-confirm-title="Close this ticket?"
                                        data-confirm-text="Closing is final - a closed ticket cannot be reopened."
                                        data-confirm-button="Close ticket">
                                        <i class="fas fa-fw fa-lock me-2"></i>Close
                                    </a>
                                <?php } ?>

                                <?php if ($show_invoice_button && !$ticket_is_open) { ?>
                                    <a href="#" class="btn btn-light border ajax-modal" data-modal-url="modals/ticket/ticket_invoice_add.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                                        <i class="fas fa-fw fa-file-invoice me-2"></i>Invoice
                                    </a>
                                <?php } ?>

                                <div class="dropdown">
                                    <button class="btn btn-light border" type="button" data-bs-toggle="dropdown" title="More actions">
                                        <i class="fas fa-fw fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <?php if ($ai_summary_available) { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_summary.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                                                <i class="fas fa-fw fa-magic me-2"></i>Summarize with AI
                                            </a>
                                        <?php } ?>
                                        <?php if ($show_trips) { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/trip/trip_add.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                                                <i class="fas fa-fw fa-route me-2"></i>Log a trip
                                            </a>
                                            <?php if ($can_edit_ticket) { ?>
                                                <div class="dropdown-divider"></div>
                                            <?php } ?>
                                        <?php } ?>
                                        <?php if ($can_edit_ticket) { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_merge.php?ticket_id=<?= $ticket_id ?>">
                                                <i class="fas fa-fw fa-clone me-2"></i>Merge into another ticket
                                            </a>
                                            <a class="dropdown-item ajax-modal" href="#" id="clientChangeTicketModalLoad" data-modal-url="modals/ticket/ticket_change_client.php?ticket_id=<?= $ticket_id ?>">
                                                <i class="fas fa-fw fa-people-carry me-2"></i>Move to another client
                                            </a>
                                            <?php if ($show_quote_button || $show_invoice_button) { ?>
                                                <div class="dropdown-divider"></div>
                                                <?php if ($show_quote_button) { ?>
                                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_quote_add.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                                                        <i class="fas fa-fw fa-comment-dollar me-2"></i>Create quote
                                                    </a>
                                                <?php } ?>
                                                <?php if ($show_invoice_button) { ?>
                                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_invoice_add.php?ticket_id=<?= $ticket_id ?>" data-modal-size="lg">
                                                        <i class="fas fa-fw fa-file-invoice me-2"></i>Create invoice
                                                    </a>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php if (lookupUserPermission("module_support") == 3 && !$ticket_is_closed) { ?>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_ticket=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                    data-confirm-title="Delete <?= $ticket_reference ?>?"
                                                    data-confirm-text="The ticket, its conversation and its attachments are removed for good."
                                                    data-confirm-button="Delete ticket">
                                                    <i class="fas fa-fw fa-trash me-2"></i>Delete ticket
                                                </a>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>

                            </div>
                        <?php } ?>

                    </div>
                </div>
            </div>


            <div class="ticket-layout-main">

                <!-- The original request -->
                <div class="card mb-3">
                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1"><i class="fas fa-fw fa-align-left me-2"></i>Description</h5>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" title="Show/hide" data-lte-toggle="card-collapse">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-3 prettyContent" id="ticketDetails">
                        <?= $ticket_details ?>

                        <?php if ($ticket_attachments) { ?>
                            <div class="mt-3 pt-2 border-top">
                                <?php foreach ($ticket_attachments as $ticket_attachment) {
                                    $ticket_attachment_id = intval($ticket_attachment['ticket_attachment_id']);
                                    $ticket_attachment_name = escapeHtml($ticket_attachment['ticket_attachment_name']);
                                    ?>
                                    <div class="mt-1">
                                        <span class="me-1 align-middle"><?= fileTypeTile(pathinfo($ticket_attachment['ticket_attachment_name'], PATHINFO_EXTENSION), 'xs') ?></span><?= $ticket_attachment_name ?>
                                        <a target="_blank" class="ms-2 small" href="ticket_attachment.php?attachment_id=<?= $ticket_attachment_id ?>&action=view">View</a>
                                        <a class="ms-2 small" href="ticket_attachment.php?attachment_id=<?= $ticket_attachment_id ?>">Download</a>
                                        <?php if (lookupUserPermission("module_support") >= 3) { ?>
                                            <a class="confirm-link ms-2 small text-danger" href="post.php?delete_ticket_attachment=<?= $ticket_attachment_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">Delete</a>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- Fill-ins - what its template asked for when it was raised, and the answers -->
                <?php
                $ticket_fill_ins = ticketFillInsStored('ticket', $ticket_id);
                if ($ticket_fill_ins) {
                    $fill_in_template = intval($ticket['ticket_template_id'] ?? 0) ? ticketTemplateRow($ticket['ticket_template_id']) : null;
                    ?>
                    <div class="card mb-3 itflow-ticket-fill-ins">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-pen-square me-2"></i>Fill-ins<?php if ($fill_in_template) { ?> <span class="small fw-normal text-secondary ms-1">from <?= escapeHtml($fill_in_template['ticket_template_name']) ?></span><?php } ?></h5>
                            <div class="card-tools">
                                <?php if ($can_edit_ticket && !$ticket_is_closed) { ?>
                                    <a class="btn btn-tool ajax-modal" href="#" data-modal-url="modals/ticket/ticket_fill_ins.php?id=<?= $ticket_id ?>" title="Answer or change">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php } ?>
                                <button type="button" class="btn btn-tool" title="Show/hide" data-lte-toggle="card-collapse">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="row row-cols-1 row-cols-sm-2 g-2">
                                <?php foreach ($ticket_fill_ins as $answer) {
                                    $answer_text = ticketFillInText($answer); ?>
                                    <div>
                                        <div class="small text-secondary"><?= escapeHtml($answer['name']) ?><?php if (!$answer['client']) { ?><i class="fas fa-user-lock ms-1" title="Agents only - not shown to the client"></i><?php } ?></div>
                                        <?php if ($answer_text !== '') { ?>
                                            <div class="text-break"><?= escapeHtml($answer_text) ?></div>
                                        <?php } else { ?>
                                            <div class="<?= $answer['required'] ? 'text-warning-emphasis' : 'text-secondary' ?> fst-italic">Not answered<?= $answer['required'] ? ' yet' : '' ?></div>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <!-- Reply composer - only while the ticket is still being worked -->
                <?php if ($can_edit_ticket && $ticket_is_open) { ?>

                    <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

                        <div class="card mb-3 d-print-none">
                            <div class="card-body p-3">

                                <!--
                                 * Picking who it is for is what opens the composer below. Nothing is
                                 * preselected, so a client-visible reply is always a deliberate choice.
                                 * "Public note" and "Email reply" are both client-visible - the
                                 * difference is whether an email goes out, which is the thing an agent
                                 * actually needs to know before pressing the button.
                                -->
                                <div class="btn-group w-100" role="group" id="replyTypePicker" aria-label="Reply type">
                                    <input class="btn-check" id="public_reply_type_opt0" type="radio" name="public_reply_type" value="0">
                                    <label class="btn btn-outline-dark" for="public_reply_type_opt0"><i class="fas fa-fw fa-lock me-1"></i>Internal note</label>
                                    <input class="btn-check" id="public_reply_type_opt1" type="radio" name="public_reply_type" value="1">
                                    <label class="btn btn-outline-info" for="public_reply_type_opt1"><i class="fas fa-fw fa-eye me-1"></i>Public note</label>
                                    <?php if ($can_email_reply) { ?>
                                        <input class="btn-check" id="public_reply_type_opt2" type="radio" name="public_reply_type" value="2">
                                        <label class="btn btn-outline-info" for="public_reply_type_opt2"><i class="fas fa-fw fa-paper-plane me-1"></i>Email reply</label>
                                    <?php } ?>
                                </div>

                                <div class="collapse" id="replyComposer">
                                    <div class="pt-3">

                                        <!-- Who will see this, spelled out for the type that is picked -->
                                        <div class="ticket-reply-audience small mb-3" id="replyAudience">
                                            <div data-audience="0" hidden><i class="fas fa-fw fa-lock me-1"></i>Only your team can see this.</div>
                                            <div data-audience="1" hidden><i class="fas fa-fw fa-eye me-1"></i>The client can see this in the portal. <strong>No email is sent.</strong></div>
                                            <?php if ($can_email_reply) { ?>
                                                <div data-audience="2" hidden>
                                                    <i class="fas fa-fw fa-paper-plane me-1"></i>Emails <strong><?= $contact_name ?></strong> &lt;<?= $contact_email ?>&gt;<?php if ($watcher_count) { ?> and <?= $watcher_count ?> watcher<?= $watcher_count == 1 ? '' : 's' ?><?php } ?>, and shows in the portal.
                                                </div>
                                            <?php } ?>
                                        </div>

                                        <?php

                                        /*
                                         * Canned responses offered on this ticket: the ones tied to its
                                         * category, plus the general ones that are offered everywhere.
                                         * Names only - the body is fetched when one is picked, so a
                                         * shelf of long responses does not ride along with every ticket.
                                         */
                                        $sql_canned_responses = mysqli_query($mysqli, "SELECT canned_response_id, canned_response_name, canned_response_category_id
                                            FROM canned_responses
                                            WHERE canned_response_archived_at IS NULL
                                            AND (canned_response_category_id = 0 OR canned_response_category_id = $ticket_category)
                                            ORDER BY canned_response_name ASC");

                                        $canned_responses_for_category = [];
                                        $canned_responses_general = [];

                                        while ($canned_row = mysqli_fetch_assoc($sql_canned_responses)) {
                                            if (intval($canned_row['canned_response_category_id']) === 0) {
                                                $canned_responses_general[] = $canned_row;
                                            } else {
                                                $canned_responses_for_category[] = $canned_row;
                                            }
                                        }

                                        if ($canned_responses_for_category || $canned_responses_general) { ?>

                                            <div class="mb-3">
                                                <select class="form-select" id="canned_response_picker" aria-label="Insert a canned response">
                                                    <option value="">- Insert a canned response -</option>
                                                    <?php if ($canned_responses_for_category) { ?>
                                                        <optgroup label="<?= $ticket_category_display ?>">
                                                            <?php foreach ($canned_responses_for_category as $canned_row) { ?>
                                                                <option value="<?= intval($canned_row['canned_response_id']) ?>"><?= escapeHtml($canned_row['canned_response_name']) ?></option>
                                                            <?php } ?>
                                                        </optgroup>
                                                    <?php } ?>
                                                    <?php if ($canned_responses_general) { ?>
                                                        <optgroup label="All categories">
                                                            <?php foreach ($canned_responses_general as $canned_row) { ?>
                                                                <option value="<?= intval($canned_row['canned_response_id']) ?>"><?= escapeHtml($canned_row['canned_response_name']) ?></option>
                                                            <?php } ?>
                                                        </optgroup>
                                                    <?php } ?>
                                                </select>
                                            </div>

                                        <?php } ?>

                                        <div class="mb-3">
                                            <textarea class="form-control tinymceTicket" id="ticket_reply" name="ticket_reply" placeholder="Type a response"></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small text-secondary mb-1" for="ticket_reply_attachments"><i class="fas fa-fw fa-paperclip me-1"></i>Attach files</label>
                                            <input type="file" class="form-control form-control-sm" id="ticket_reply_attachments" name="attachments[]" multiple accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .md, .doc, .docx, .odt, .csv, .xls, .xlsx, .ods, .pptx, .odp, .zip, .tar, .gz, .xml, .msg, .json, .wav, .mp3, .ogg, .mov, .mp4, .av1, .ovpn">
                                        </div>

                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label small text-secondary mb-1" for="ticket_reply_status">Status after this</label>
                                                <select class="form-select select2" name="status" id="ticket_reply_status" required>
                                                    <!-- Resolved stays on offer here (reply and resolve in one go) unless open tasks block it -->
                                                    <?php foreach ($ticket_status_options as $status_option) {
                                                        $ticket_status_id_select = intval($status_option['ticket_status_id']);
                                                        if ($ticket_status_id_select == 4 && $tasks_block_resolve) {
                                                            continue;
                                                        }
                                                        ?>
                                                        <option value="<?= $ticket_status_id_select ?>" <?php if ($ticket_status == $ticket_status_id_select) { echo 'selected'; } ?>><?= escapeHtml($status_option['ticket_status_name']) ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>

                                            <!-- Time Tracking -->
                                            <div class="col-md-5">
                                                <label class="form-label small text-secondary mb-1" for="hours">Time worked</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" inputmode="numeric" id="hours" name="hours" placeholder="Hrs" aria-label="Hours" min="0" max="23" pattern="0?[0-9]|1[0-9]|2[0-3]">
                                                    <input type="text" class="form-control" inputmode="numeric" id="minutes" name="minutes" placeholder="Mins" aria-label="Minutes" min="0" max="59" pattern="[0-5]?[0-9]">
                                                    <input type="text" class="form-control" inputmode="numeric" id="seconds" name="seconds" placeholder="Secs" aria-label="Seconds" min="0" max="59" pattern="[0-5]?[0-9]">
                                                    <button type="button" class="btn btn-light border" id="startStopTimer" title="Start / pause the timer"><i class="fas fa-play"></i></button>
                                                    <button type="button" class="btn btn-light border" id="resetTimer" title="Reset the timer"><i class="fas fa-redo-alt"></i></button>
                                                </div>
                                            </div>

                                            <div class="col-md-3">
                                                <div class="d-flex gap-2">
                                                    <button type="submit" id="ticket_add_reply" name="add_ticket_reply" class="btn btn-success flex-grow-1">
                                                        <i class="fas fa-fw fa-paper-plane me-2"></i><span id="replySubmitLabel">Send</span>
                                                    </button>
                                                    <button type="button" id="cancelReply" class="btn btn-light border" title="Close without replying">
                                                        <i class="fas fa-fw fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>
                    </form>

                <?php } ?>

                <!-- Conversation -->
                <?php if ($reply_counts['total']) { ?>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2 d-print-none">
                        <h6 class="mb-0 me-auto"><i class="fas fa-fw fa-comments me-2"></i>Conversation <span class="text-muted fw-normal small">&middot; newest first</span></h6>
                        <div class="btn-group btn-group-sm" id="replyFilter" role="group" aria-label="Show replies">
                            <button type="button" class="btn btn-dark active" data-reply-filter="all">All <?= $reply_counts['total'] ?></button>
                            <button type="button" class="btn btn-outline-dark" data-reply-filter="public">Public <?= $reply_counts['public'] ?></button>
                            <button type="button" class="btn btn-outline-dark" data-reply-filter="internal">Internal <?= $reply_counts['internal'] ?></button>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="text-center text-muted small py-4 d-print-none">No replies yet.</div>
                <?php } ?>

                <?php

                while ($reply_row = mysqli_fetch_assoc($sql_ticket_replies)) {
                    $ticket_reply_id = intval($reply_row['ticket_reply_id']);
                    $ticket_reply = $purifier->purify($reply_row['ticket_reply']);
                    $ticket_reply_type = escapeHtml($reply_row['ticket_reply_type']);
                    $ticket_reply_created_at = escapeHtml($reply_row['ticket_reply_created_at']);
                    $ticket_reply_created_at_ago = timeAgo($reply_row['ticket_reply_created_at']);
                    $ticket_reply_updated_at = escapeHtml($reply_row['ticket_reply_updated_at']);
                    $ticket_reply_time_worked = '';

                    // A client's reply wears their contact avatar - their own colour,
                    // the same as everywhere else they appear
                    $reply_contact_avatar = '';
                    if ($ticket_reply_type == "Client") {
                        $ticket_reply_by_display = escapeHtml($reply_row['contact_name']);
                        $reply_contact_avatar = contactAvatar($client_id, $reply_row['ticket_reply_by'], $reply_row['contact_name'], $reply_row['contact_photo'], 'md');
                    } else {
                        $ticket_reply_by_display = escapeHtml($reply_row['user_name']);
                        $user_id = intval($reply_row['user_id']);
                        $user_avatar = escapeHtml($reply_row['user_avatar']);
                        $user_initials = initials($reply_row['user_name']);
                        $avatar_link = "../uploads/users/$user_id/$user_avatar";
                        $ticket_reply_time_worked = $reply_row['ticket_reply_time_worked'];
                    }

                    // Internal notes are the one thing that must never be mistaken for
                    // something the client saw, so they get a label, not just a colour
                    if ($ticket_reply_type == 'Internal') {
                        $reply_border = 'dark';
                        $reply_badge = "<span class='badge text-bg-dark ticket-badge-internal' title='Only your team can see this'><i class='fas fa-fw fa-lock me-1'></i>Internal</span>";
                        $reply_group = 'internal';
                    } elseif ($ticket_reply_type == 'Client') {
                        $reply_border = 'warning';
                        $reply_badge = "<span class='badge text-bg-warning'><i class='fas fa-fw fa-reply me-1'></i>From client</span>";
                        $reply_group = 'public';
                    } else {
                        $reply_border = 'info';
                        $reply_badge = "<span class='badge text-bg-info' title='The client can see this'><i class='fas fa-fw fa-eye me-1'></i>Public</span>";
                        $reply_group = 'public';
                    }

                    ?>

                    <!-- Begin ticket reply card -->
                    <div class="card ticket-reply border-start border-<?= $reply_border ?> mb-3" data-reply-group="<?= $reply_group ?>">
                        <div class="card-header px-3 py-2">
                            <div class="d-flex justify-content-between align-items-start w-100">

                                <div class="d-flex align-items-center">
                                    <?php if ($reply_contact_avatar) { ?>
                                        <span class="me-2 d-inline-flex"><?= $reply_contact_avatar ?></span>
                                    <?php } elseif (!empty($user_avatar)) { ?>
                                        <img src="<?= $avatar_link ?>" alt="" class="img-size-32 me-2 rounded-circle">
                                    <?php } else { ?>
                                        <span class="fa-stack me-2">
                                            <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                                            <span class="fa fa-stack-1x text-white"><?= $user_initials ?></span>
                                        </span>
                                    <?php } ?>

                                    <div>
                                        <strong><?= $ticket_reply_by_display ?></strong>
                                        <?= $reply_badge ?>
                                        <div class="small text-muted" title="Created: <?= $ticket_reply_created_at ?><?php if ($ticket_reply_updated_at) { echo '. Edited: ' . $ticket_reply_updated_at; } ?>">
                                            <?= $ticket_reply_created_at_ago ?><?php if ($ticket_reply_updated_at) { echo ' <em>(edited)</em>'; } ?>
                                            <?php if ($ticket_reply_type !== "Client" && $ticket_reply_time_worked && $ticket_reply_time_worked !== "00:00:00") { ?>
                                                <span class="mx-1">&middot;</span><i class="far fa-fw fa-clock"></i> <?= formatDuration($ticket_reply_time_worked) ?>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($can_edit_ticket) { ?>
                                    <div class="card-tools d-print-none">
                                        <div class="dropdown dropstart">
                                            <button class="btn btn-sm btn-tool" title="More actions" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-fw fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <?php if ($ticket_reply_type !== "Client" && !$ticket_is_closed) { ?>
                                                    <a href="#" class="dropdown-item ajax-modal" data-modal-size="lg"
                                                        data-modal-url="modals/ticket/ticket_reply_edit.php?id=<?= $ticket_reply_id ?>">
                                                        <i class="fas fa-fw fa-edit me-2"></i>Edit
                                                    </a>
                                                <?php } ?>
                                                <a href="#" class="dropdown-item ajax-modal" data-modal-size="lg"
                                                    data-modal-url="modals/ticket/ticket_reply_redact.php?id=<?= $ticket_reply_id ?>">
                                                    <i class="fas fa-fw fa-user-secret me-2"></i>Redact
                                                </a>
                                                <?php if ($ticket_reply_type !== "Client" && !$ticket_is_closed) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger confirm-link" href="post.php?archive_ticket_reply=<?= $ticket_reply_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                        data-confirm-title="Archive this reply?"
                                                        data-confirm-text="It disappears from the conversation for everyone, including the client."
                                                        data-confirm-button="Archive">
                                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>

                            </div>
                        </div>

                        <div class="card-body p-3 prettyContent">
                            <?= $ticket_reply ?>

                            <?php if (isset($reply_attachments[$ticket_reply_id])) { ?>
                                <div class="mt-3 pt-2 border-top">
                                    <?php foreach ($reply_attachments[$ticket_reply_id] as $ticket_attachment) {
                                        $ticket_attachment_id = intval($ticket_attachment['ticket_attachment_id']);
                                        $ticket_attachment_name = escapeHtml($ticket_attachment['ticket_attachment_name']);
                                        ?>
                                        <div class="mt-1">
                                            <span class="me-1 align-middle"><?= fileTypeTile(pathinfo($ticket_attachment['ticket_attachment_name'], PATHINFO_EXTENSION), 'xs') ?></span><?= $ticket_attachment_name ?>
                                            <a target="_blank" class="ms-2 small" href="ticket_attachment.php?attachment_id=<?= $ticket_attachment_id ?>&action=view">View</a>
                                            <a class="ms-2 small" href="ticket_attachment.php?attachment_id=<?= $ticket_attachment_id ?>">Download</a>
                                            <?php if (lookupUserPermission("module_support") >= 3) { ?>
                                                <a class="confirm-link ms-2 small text-danger" href="post.php?delete_ticket_attachment=<?= $ticket_attachment_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">Delete</a>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <!-- End ticket reply card -->

                    <?php

                }

                ?>

            </div> <!-- End ticket-layout-main -->

            <div class="ticket-layout-side">

                <!--
                    Details - every property of the ticket, one per row, each one a
                    click away from being changed. Rows keep the same order on every
                    ticket so an agent's eye knows where to look.
                -->
                <div class="ticket-details card mb-3">
                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1"><i class="fas fa-fw fa-sliders-h me-2"></i>Details</h5>
                    </div>
                    <div class="card-body px-2 py-2">
                        <dl class="ticket-props mb-0">

                            <div class="ticket-prop">
                                <dt>Status</dt>
                                <dd>
                                    <?php if ($ticket_status_options) { ?>
                                        <?php /* Resolved is not offered - the Resolve button does that, and it also emails the client */ ?>
                                        <span class="d-block" data-ticket-status-for="<?= $ticket_id ?>" data-quick-style="detail" data-status-id="<?= intval($ticket_status) ?>">
                                            <?= quickPick('status', $ticket_id, (string) intval($ticket_status), ticketStatusHtml($ticket['ticket_status_name'], $ticket['ticket_status_color'], 'detail'), 'detail') ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="ticket-prop-static">
                                            <span class="badge rounded-pill text-light" style="background-color: <?= $ticket_status_color ?>"><?= $ticket_status_name ?></span>
                                        </span>
                                    <?php } ?>
                                </dd>
                            </div>

                            <div class="ticket-prop">
                                <dt>Priority</dt>
                                <dd>
                                    <?php if ($can_change_fields) { ?>
                                        <?= quickPick('priority', $ticket_id, $ticket_priority, ticketPriorityHtml($ticket_priority, 'detail'), 'detail') ?>
                                    <?php } else { ?>
                                        <span class="ticket-prop-static"><span class="badge rounded-pill text-bg-<?= $ticket_priority_color ?>"><?= $ticket_priority ?></span></span>
                                    <?php } ?>
                                </dd>
                            </div>

                            <div class="ticket-prop">
                                <dt>Assigned to</dt>
                                <dd>
                                    <?php if ($can_change_fields) { ?>
                                        <?= quickPick('assignee', $ticket_id, (string) intval($ticket['ticket_assigned_to']), $ticket_assigned_to_display, 'detail', $ticket['ticket_status']) ?>
                                    <?php } else { ?>
                                        <span class="ticket-prop-static"><?= $ticket_assigned_to_display ?></span>
                                    <?php } ?>
                                </dd>
                            </div>

                            <?php if ($sla_in_use) {
                                // What the SLA value says - the clock state when there is one, else the plan
                                if ($sla_state_label) {
                                    $sla_value = "<span class='badge rounded-pill $sla_class'><i class='fas fa-fw $sla_icon me-1'></i>$sla_state_label</span>";
                                } elseif ($ticket_sla_id) {
                                    $sla_value = $ticket_sla_name;
                                } else {
                                    $sla_value = "<span class='text-muted'>None</span>";
                                }
                                // The plan name goes underneath whenever the badge took the main line
                                $sla_plan_line = ($sla_state_label && $ticket_sla_id) ? "<span class='ticket-prop-sub'>$ticket_sla_name</span>" : '';
                                ?>
                                <div class="ticket-prop">
                                    <dt>SLA</dt>
                                    <dd>
                                        <?php if ($can_change_fields) { ?>
                                            <a href="#" class="ticket-prop-edit ajax-modal" data-modal-url="modals/ticket/ticket_sla.php?id=<?= $ticket_id ?>" title="Change SLA plan">
                                                <span><?= $sla_value ?><?= $sla_plan_line ?></span>
                                                <i class="fas fa-pen ticket-prop-edit-icon"></i>
                                            </a>
                                        <?php } else { ?>
                                            <span class="ticket-prop-static"><span><?= $sla_value ?><?= $sla_plan_line ?></span></span>
                                        <?php } ?>
                                    </dd>
                                </div>
                            <?php } ?>

                            <div class="ticket-prop">
                                <dt>Category</dt>
                                <dd>
                                    <?php $category_value = $ticket_category ? categoryMarkHtml($ticket['category_color'] ?? '', $ticket['category_icon'] ?? '', '<i class="fas fa-circle itflow-ticket-dot"%s aria-hidden="true"></i>') . $ticket_category_display : "<span class='text-muted'>None</span>"; ?>
                                    <?php if ($can_change_fields) { ?>
                                        <a href="#" class="ticket-prop-edit ajax-modal" data-modal-url="modals/ticket/ticket_edit.php?id=<?= $ticket_id ?>" data-modal-size="lg" title="Change category">
                                            <span><?= $category_value ?></span>
                                            <i class="fas fa-pen ticket-prop-edit-icon"></i>
                                        </a>
                                    <?php } else { ?>
                                        <span class="ticket-prop-static"><?= $category_value ?></span>
                                    <?php } ?>
                                </dd>
                            </div>

                            <div class="ticket-prop">
                                <dt>Scheduled</dt>
                                <dd>
                                    <?php
                                    if ($ticket_scheduled_for) {
                                        $schedule_value = $ticket_scheduled_wording . "<span class='ticket-prop-sub'>" . ($ticket_onsite ? "<i class='fas fa-fw fa-map-marker-alt me-1'></i>Onsite" : "<i class='fas fa-fw fa-headset me-1'></i>Remote") . "</span>";
                                    } else {
                                        $schedule_value = "<span class='text-muted'>Not scheduled</span>";
                                    }
                                    ?>
                                    <?php if ($can_change_fields) { ?>
                                        <a href="#" class="ticket-prop-edit ajax-modal" data-modal-url="modals/ticket/ticket_edit_schedule.php?ticket_id=<?= $ticket_id ?>" title="Change schedule">
                                            <span><?= $schedule_value ?></span>
                                            <i class="fas fa-pen ticket-prop-edit-icon"></i>
                                        </a>
                                    <?php } else { ?>
                                        <span class="ticket-prop-static"><span><?= $schedule_value ?></span></span>
                                    <?php } ?>
                                </dd>
                            </div>

                            <?php if ($ticket_due_at) {
                                $due_value = "<span class='" . ($ticket_due_overdue ? 'text-danger fw-bold' : '') . "' title='$ticket_due_at'>" . date('M d, Y • g:i A', strtotime($ticket_due_at)) . "</span>";
                                ?>
                                <div class="ticket-prop">
                                    <dt>Due</dt>
                                    <dd>
                                        <?php if ($can_change_fields) { ?>
                                            <a href="#" class="ticket-prop-edit ajax-modal" data-modal-url="modals/ticket/ticket_edit.php?id=<?= $ticket_id ?>" data-modal-size="lg" title="Change due date">
                                                <span><?= $due_value ?></span>
                                                <i class="fas fa-pen ticket-prop-edit-icon"></i>
                                            </a>
                                        <?php } else { ?>
                                            <span class="ticket-prop-static"><?= $due_value ?></span>
                                        <?php } ?>
                                    </dd>
                                </div>
                            <?php } ?>

                            <?php if ($config_module_enable_accounting && lookupUserPermission("module_sales") >= 1) { ?>
                                <div class="ticket-prop">
                                    <dt>Billing</dt>
                                    <dd>
                                        <?php if ($invoice_id) { ?>
                                            <a href="invoice.php?invoice_id=<?= $invoice_id ?>" class="ticket-prop-edit" title="Open the invoice">
                                                <span><i class="fa fa-fw fa-file-invoice text-secondary me-1"></i>Invoiced on <?= $invoice_reference ?></span>
                                            </a>
                                        <?php } elseif ($quote_id) { ?>
                                            <a href="quote.php?quote_id=<?= $quote_id ?>" class="ticket-prop-edit" title="Open the quote">
                                                <span><i class="fa fa-fw fa-comment-dollar text-secondary me-1"></i>Quoted on <?= $quote_reference ?></span>
                                            </a>
                                        <?php } elseif ($can_edit_ticket && lookupUserPermission("module_sales") >= 2) { ?>
                                            <?= quickPick('billable', $ticket_id, $ticket_billable ? '1' : '0', ticketBillableHtml($ticket_billable, 'detail'), 'detail') ?>
                                        <?php } else { ?>
                                            <span class="ticket-prop-static"><?= $ticket_billable ? 'Billable' : "<span class='text-muted'>Not billable</span>" ?></span>
                                        <?php } ?>
                                    </dd>
                                </div>
                            <?php } ?>

                            <div class="ticket-prop">
                                <dt>Time logged</dt>
                                <dd>
                                    <span class="ticket-prop-static">
                                        <?= $ticket_total_reply_time ? formatDuration($ticket_total_reply_time) : "<span class='text-muted'>None yet</span>" ?>
                                    </span>
                                </dd>
                            </div>

                        </dl>
                    </div>
                </div>

                <!-- Contact - who this is for and how to reach them -->
                <?php
                // Rendered even with no contact set, so there is somewhere to set one
                $can_set_contact = $can_edit_ticket && !$ticket_is_resolved && $client_id;
                if ($contact_id || $can_set_contact) { ?>
                    <div class="card mb-3">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-user me-2"></i>Contact</h5>
                            <?php if ($can_set_contact) { ?>
                                <div class="card-tools">
                                    <a class="btn btn-tool ajax-modal" href="#" data-modal-url="modals/ticket/ticket_contact.php?id=<?= $ticket_id ?>" title="<?= $contact_id ? 'Change contact' : 'Set contact' ?>">
                                        <i class="fas fa-<?= $contact_id ? 'edit' : 'plus' ?>"></i>
                                    </a>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">

                            <?php if (!$contact_id) { ?>
                                <div class="text-muted small">No contact on this ticket yet.</div>
                            <?php } else { ?>

                                <div class="d-flex align-items-center gap-2">
                                    <?= contactAvatar($contact_client_id, $contact_id, $contact_name, $contact_photo) ?>
                                    <div class="min-w-0">
                                        <a href="#" class="ajax-modal fw-bold text-break" data-modal-size="lg" data-modal-url="modals/contact/contact.php?id=<?= $contact_id ?>"><?= $contact_name ?></a>
                                        <?php if ($contact_title) { ?><div class="text-muted small text-truncate"><?= $contact_title ?></div><?php } ?>
                                    </div>
                                </div>

                                <?php if (!empty($contact_phone)) { ?>
                                    <div class="mt-2">
                                        <i class="fa fa-fw fa-phone text-secondary me-2"></i><a href="tel:<?= $contact_phone ?>"><?= $contact_phone ?></a>
                                        <?php if ($contact_extension) { ?><span class="text-muted small">ext. <?= $contact_extension ?></span><?php } ?>
                                    </div>
                                <?php } ?>

                                <?php if (!empty($contact_mobile)) { ?>
                                    <div class="mt-2">
                                        <i class="fa fa-fw fa-mobile-alt text-secondary me-2"></i><a href="tel:<?= $contact_mobile ?>"><?= $contact_mobile ?></a>
                                    </div>
                                <?php } ?>

                                <?php if (!empty($contact_email)) { ?>
                                    <div class="mt-2 text-truncate">
                                        <i class="fa fa-fw fa-envelope text-secondary me-2"></i><a href="mailto:<?= $contact_email ?>" title="<?= $contact_email ?>"><?= $contact_email ?></a>
                                    </div>
                                <?php } ?>

                            <?php } ?>

                        </div>
                    </div>
                <?php } ?>

                <!-- Also open for the same contact or asset -->
                <?php if ($also_open) { ?>
                    <div class="card mb-3">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-layer-group me-2"></i>Also open</h5>
                        </div>
                        <div class="list-group list-group-flush small">
                            <?php foreach ($also_open as $other) { ?>
                                <div class="list-group-item px-3 py-2">
                                    <div class="text-truncate"><?= ticketLink($other) ?></div>
                                    <div class="d-flex align-items-center gap-2 mt-1 text-body-secondary">
                                        <?= ticketStatusHtml($other['ticket_status_name'], $other['ticket_status_color'], 'detail') ?>
                                        <span>Same <?= implode(' and ', $other['shares']) ?></span>
                                        <span class="ms-auto text-nowrap" title="<?= escapeHtml($other['ticket_created_at']) ?>"><?= timeAgo($other['ticket_created_at']) ?></span>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <!-- Site - where the work is: getting there and getting in -->
                <?php if ($location_id && $location_name) {
                    $ticket_location_address = locationAddressText($ticket, true);
                    $ticket_location_directions = locationDirectionsUrl($ticket);
                    $ticket_location_phone = contactPhoneLink($ticket['location_phone'], $ticket['location_phone_country_code'], $ticket['location_phone_extension']);
                    $ticket_location_notes = trim((string) $ticket['location_notes']); ?>
                    <div class="card mb-3">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-map-marker-alt me-2"></i>Site</h5>
                            <?php if ($ticket_location_directions) { ?>
                                <div class="card-tools">
                                    <a class="btn btn-tool" href="<?= escapeHtml($ticket_location_directions) ?>" target="_blank" rel="noopener" title="Directions"><i class="fas fa-directions"></i></a>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="card-body p-3">
                            <a class="fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/location/location.php?id=<?= $location_id ?>"><?= $location_name ?></a>
                            <?php if ($ticket_location_address !== '') { ?>
                                <div class="small mt-1">
                                    <a class="text-reset" href="<?= escapeHtml(locationMapUrl($ticket)) ?>" target="_blank" rel="noopener" title="Open in maps"><?= escapeHtml($ticket_location_address) ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy address" data-clipboard-text="<?= escapeHtml($ticket_location_address) ?>"><i class="far fa-copy"></i></button>
                                </div>
                            <?php } ?>
                            <?php if ($ticket_location_phone) { ?>
                                <div class="small mt-1"><i class="fa fa-fw fa-phone text-secondary me-1"></i><?= $ticket_location_phone ?></div>
                            <?php } ?>
                            <?php if ($ticket['location_hours']) { ?>
                                <div class="small mt-1 text-body-secondary"><i class="fa fa-fw fa-clock me-1"></i><?= escapeHtml($ticket['location_hours']) ?></div>
                            <?php } ?>
                            <?php if ($ticket_location_notes !== '') { ?>
                                <div class="small mt-2 pt-2 border-top text-body-secondary itflow-ticket-site-notes" title="Access notes"><i class="fa fa-fw fa-door-open me-1"></i><?= nl2br(escapeHtml($ticket_location_notes)) ?></div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <!-- Trips - the driving done for it, for an onsite ticket or once there is some -->
                <?php if ($show_trips && ($ticket_trip_count || $ticket_onsite)) { ?>
                    <div class="card mb-3 itflow-ticket-trips">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-route me-2"></i>Trips<?php if ($ticket_trip_count) { ?> <span class="badge text-bg-secondary ms-1"><?= $ticket_trip_count ?></span><?php } ?></h5>
                            <div class="card-tools">
                                <a class="btn btn-tool ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/trip/trip_add.php?ticket_id=<?= $ticket_id ?>" title="Log a trip"><i class="fas fa-plus"></i></a>
                            </div>
                        </div>
                        <?php if ($ticket_trips) { ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($ticket_trips as $trip) { ?>
                                    <a href="#" class="list-group-item list-group-item-action px-3 py-2 ajax-modal" data-modal-size="lg" data-modal-url="modals/trip/trip.php?id=<?= intval($trip['trip_id']) ?>">
                                        <div class="d-flex justify-content-between gap-2">
                                            <span class="small text-truncate"><?= escapeHtml(date('M j', strtotime($trip['trip_date']))) ?> &middot; <?= escapeHtml($trip['driver_name'] ?? 'Unknown') ?></span>
                                            <span class="small fw-semibold text-nowrap"><?= escapeHtml(tripMilesText($trip['trip_miles'])) ?><?php if (intval($trip['round_trip'])) { ?><i class="fas fa-sync-alt text-body-secondary ms-1" title="Round trip"></i><?php } ?></span>
                                        </div>
                                        <div class="small text-body-secondary text-truncate"><?= escapeHtml($trip['trip_source']) ?> &rarr; <?= escapeHtml($trip['trip_destination']) ?></div>
                                    </a>
                                <?php } ?>
                            </div>
                            <div class="card-footer px-3 py-2 small d-flex justify-content-between">
                                <span class="text-body-secondary"><?= escapeHtml(tripMilesText($ticket_trip_miles)) ?> driven</span>
                                <?php if ($ticket_trip_count > count($ticket_trips)) { ?>
                                    <a href="trips.php?client_id=<?= $client_id ?>&amp;ticket_id=<?= $ticket_id ?>">All <?= $ticket_trip_count ?></a>
                                <?php } else { ?>
                                    <a href="trips.php?client_id=<?= $client_id ?>&amp;ticket_id=<?= $ticket_id ?>">Open in Trips</a>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="card-body p-3 small text-body-secondary">
                                An onsite ticket - drove out for it? <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/trip/trip_add.php?ticket_id=<?= $ticket_id ?>">Log the trip</a> to keep the driving with the ticket.
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <!-- Tasks - the checklist, with the one place the progress count is shown -->
                <?php if (!$ticket_is_resolved || $task_count) { ?>
                    <div class="card mb-3">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1">
                                <i class="fas fa-fw fa-tasks me-2"></i>Tasks
                                <?php if ($task_count) { ?>
                                    <span class="ticket-task-count ms-2" title="<?= $tasks_completed_percent ?>% complete">
                                        <span class="progress ticket-task-progress" role="progressbar" aria-label="Tasks complete" aria-valuenow="<?= $tasks_completed_percent ?>" aria-valuemin="0" aria-valuemax="100">
                                            <span class="progress-bar <?= $tasks_block_resolve ? 'bg-secondary' : 'bg-success' ?>" style="width: <?= $tasks_completed_percent ?>%;"></span>
                                        </span>
                                        <?= "$completed_task_count of $task_count" ?>
                                    </span>
                                <?php } ?>
                            </h5>
                            <div class="card-tools">
                                <?php if (!$ticket_is_resolved && $can_edit_ticket && $task_count) { ?>
                                    <div class="dropdown dropstart d-inline-block">
                                        <button class="btn btn-tool" title="More actions" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="post.php?complete_all_tasks=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-check-double me-2"></i>Mark all complete
                                            </a>
                                            <a class="dropdown-item" href="post.php?undo_complete_all_tasks=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                <i class="far fa-fw fa-square me-2"></i>Mark all incomplete
                                            </a>
                                        </div>
                                    </div>
                                <?php } ?>
                                <button type="button" class="btn btn-tool" title="Show/hide" data-lte-toggle="card-collapse">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">

                            <?php if (!$ticket_is_resolved && $can_edit_ticket) { ?>
                                <form action="post.php" method="post" autocomplete="off" class="px-3 pt-3 pb-2">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" name="name" placeholder="Add a task" aria-label="New task" required maxlength="255">
                                        <button type="submit" name="add_task" class="btn btn-outline-primary" title="Add task">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </form>
                            <?php } ?>

                            <?php if (!$task_count) { ?>
                                <div class="px-3 pb-3 text-muted small">No tasks on this ticket.</div>
                            <?php } ?>

                            <table class="table table-sm mb-0 ticket-task-table" id="tasks">
                                <tbody>
                                <?php
                                while ($task_row = mysqli_fetch_assoc($sql_tasks)) {
                                    $task_id = intval($task_row['task_id']);
                                    $task_name = escapeHtml($task_row['task_name']);
                                    $task_completion_estimate = intval($task_row['task_completion_estimate']);
                                    $task_completed_at = escapeHtml($task_row['task_completed_at']);

                                    // Approvals came from the single batched query above
                                    $task_needs_approval = isset($task_approvals[$task_id]);
                                    $approval_id = 0;
                                    $user_can_approve = false;

                                    if ($task_needs_approval) {
                                        foreach ($task_approvals[$task_id] as $approval) {
                                            if ($approval['approval_status'] !== 'pending') {
                                                continue;
                                            }

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
                                    }

                                    ?>
                                    <tr data-task-id="<?= $task_id ?>">
                                        <td class="ps-3 pe-0 ticket-task-check">
                                            <?php if ($task_completed_at) { ?>
                                                <i class="far fa-fw fa-check-square text-success" title="Completed <?= $task_completed_at ?>"></i>
                                            <?php } elseif ($can_edit_ticket && !$task_needs_approval) { ?>
                                                <a href="post.php?complete_task=<?= $task_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Mark complete">
                                                    <i class="far fa-fw fa-square text-body"></i>
                                                </a>
                                            <?php } elseif ($task_needs_approval) { ?>
                                                <i class="fas fa-fw fa-shield-alt text-warning" title="Waiting for approval"></i>
                                            <?php } else { ?>
                                                <i class="far fa-fw fa-square text-muted"></i>
                                            <?php } ?>
                                        </td>
                                        <td class="ps-2">
                                            <span class="<?= $task_completed_at ? 'text-muted text-decoration-line-through' : '' ?>"><?= $task_name ?></span>
                                            <?php if (!$task_completed_at && $task_needs_approval) { ?>
                                                <!-- Say it in words - a bare shield icon did not tell anyone what was holding the task -->
                                                <div class="small text-warning-emphasis">
                                                    Waiting for approval
                                                    <?php if ($user_can_approve && $can_edit_ticket) { ?>
                                                        &middot; <a class="confirm-link" href="post.php?approve_ticket_task=<?= $task_id ?>&approval_id=<?= $approval_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-confirm-title="Approve this task?" data-confirm-button="Approve">Approve</a>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        </td>
                                        <td class="pe-2 text-end text-nowrap">
                                            <button type="button" class="btn btn-sm btn-link drag-handle px-1" title="Drag to reorder"><i class="fas fa-grip-vertical text-muted"></i></button>
                                            <?php if (!$ticket_is_resolved && $can_edit_ticket) { ?>
                                                <div class="dropdown dropstart d-inline-block">
                                                    <button class="btn btn-sm btn-link text-secondary px-1" title="More actions" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-fw fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_task_edit.php?id=<?= $task_id ?>">
                                                            <i class="fas fa-fw fa-edit me-2"></i>Edit
                                                        </a>
                                                        <?php if (!$task_completed_at) { ?>
                                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_task_approver_add.php?id=<?= $task_id ?>">
                                                                <i class="fas fa-fw fa-shield-alt me-2"></i>Require approval
                                                            </a>
                                                        <?php } else { ?>
                                                            <a class="dropdown-item" href="post.php?undo_complete_task=<?= $task_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                                <i class="fas fa-fw fa-undo me-2"></i>Mark incomplete
                                                            </a>
                                                        <?php } ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger confirm-link" href="post.php?delete_task=<?= $task_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                            <i class="fas fa-fw fa-trash-alt me-2"></i>Delete
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php } ?>

                <!--
                    Everything else this ticket is attached to, in one card instead of
                    four stacked ones that were mostly empty on a typical ticket.
                -->
                <?php
                $can_link = $can_edit_ticket && !$ticket_is_closed;
                // The services it is about - raised for them, or through its assets
                $ticket_services = $client_id ? ticketServices($ticket_id) : [];
                $has_links = $asset_id || $vendor_id || $project_id || $watcher_count || $ticket_services;
                if ($has_links || $can_link) { ?>
                    <div class="card mb-3">
                        <div class="card-header px-3 py-2">
                            <h5 class="card-title mt-1"><i class="fas fa-fw fa-link me-2"></i>Linked</h5>
                            <div class="card-tools">
                                <?php if ($can_link) { ?>
                                    <div class="dropdown dropstart d-inline-block">
                                        <button class="btn btn-tool" type="button" data-bs-toggle="dropdown" title="Link something to this ticket">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <?php if ($client_id) { ?>
                                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_edit_asset.php?id=<?= $ticket_id ?>">
                                                    <i class="fas fa-fw fa-desktop me-2"></i><?= $asset_id ? 'Change assets' : 'Add asset' ?>
                                                </a>
                                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_edit_vendor.php?ticket_id=<?= $ticket_id ?>">
                                                    <i class="fas fa-fw fa-building me-2"></i><?= $vendor_id ? 'Change vendor' : 'Add vendor' ?>
                                                </a>
                                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_edit_project.php?id=<?= $ticket_id ?>">
                                                    <i class="fas fa-fw fa-project-diagram me-2"></i><?= $project_id ? 'Change project' : 'Add to a project' ?>
                                                </a>
                                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_link_service.php?id=<?= $ticket_id ?>">
                                                    <i class="fas fa-fw fa-stream me-2"></i>Link a service
                                                </a>
                                                <div class="dropdown-divider"></div>
                                            <?php } ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add_watcher.php?ticket_id=<?= $ticket_id ?>">
                                                <i class="fas fa-fw fa-eye me-2"></i>Add watcher
                                            </a>
                                        </div>
                                    </div>
                                <?php } ?>
                                <button type="button" class="btn btn-tool" title="Show/hide" data-lte-toggle="card-collapse">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-3">

                            <?php if (!$has_links) { ?>
                                <div class="text-muted small">Nothing linked yet. Use <i class="fas fa-fw fa-plus"></i> to add an asset, vendor, project, service or watcher.</div>
                            <?php } ?>

                            <?php if ($asset_id) { ?>
                                <div class="ticket-link-heading">Assets</div>
                                <div class="mt-1 text-truncate">
                                    <a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?<?= $client_url ?>&id=<?= $asset_id ?>" title="<?= $asset_name ?>">
                                        <i class="fa fa-fw fa-<?= $asset_icon ?> text-secondary me-2"></i><?= $asset_name ?>
                                    </a>
                                </div>
                                <?php while ($additional_asset_row = mysqli_fetch_assoc($sql_additional_assets)) {
                                    $additional_asset_id = intval($additional_asset_row['asset_id']);
                                    $additional_asset_name = escapeHtml($additional_asset_row['asset_name']);
                                    $additional_asset_icon = getAssetIcon(escapeHtml($additional_asset_row['asset_type']));
                                    ?>
                                    <div class="mt-1 d-flex align-items-center">
                                        <a class="ajax-modal text-truncate flex-grow-1" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?<?= $client_url ?>&id=<?= $additional_asset_id ?>" title="<?= $additional_asset_name ?>">
                                            <i class="fa fa-fw fa-<?= $additional_asset_icon ?> text-secondary me-2"></i><?= $additional_asset_name ?>
                                        </a>
                                        <?php if ($can_link) { ?>
                                            <a class="confirm-link ms-2" href="post.php?delete_ticket_additional_asset=<?= $additional_asset_id ?>&ticket_id=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Remove from this ticket">
                                                <i class="fas fa-fw fa-times text-secondary"></i>
                                            </a>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            <?php } ?>

                            <?php if ($vendor_id) {
                                // Everything for the call to them: the line, their reference, and what they will ask for
                                $vendor_phone_link = contactPhoneLink($ticket['vendor_phone'], $ticket['vendor_phone_country_code'], $ticket['vendor_extension']);
                                $vendor_account = escapeHtml($ticket['vendor_account_number']);
                                $vendor_pin = vendorSecretHtml($ticket['vendor_code']);
                                $vendor_website_url = vendorWebsiteUrl($ticket['vendor_website']);
                                $vendor_when = escapeHtml(implode(' · ', array_filter([$ticket['vendor_hours'], $ticket['vendor_sla']])));
                                ?>
                                <div class="ticket-link-heading <?= $asset_id ? 'mt-3 pt-2 border-top' : '' ?>">Vendor</div>
                                <div class="mt-1 d-flex min-w-0">
                                    <?= vendorLink($vendor_id, $ticket['vendor_name'], ['archived' => !empty($ticket['vendor_archived_at'])]) ?>
                                </div>
                                <?php if ($ticket_vendor_ticket_number) { ?>
                                    <!-- Labelled - a bare reference beside a tag icon read as nothing in particular -->
                                    <div class="mt-1 text-truncate"><i class="fa fa-fw fa-hashtag text-secondary me-2"></i><span class="text-muted">Their ticket</span> <span class="font-monospace"><?= $ticket_vendor_ticket_number ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy their ticket number" data-clipboard-text="<?= $ticket_vendor_ticket_number ?>"><i class="far fa-copy"></i></button></div>
                                <?php } ?>
                                <?php if ($vendor_phone_link) { ?>
                                    <div class="mt-1"><i class="fa fa-fw fa-phone text-secondary me-2"></i><?= $vendor_phone_link ?></div>
                                <?php } ?>
                                <?php if ($vendor_when) { ?>
                                    <div class="mt-1 small text-muted text-truncate" title="<?= $vendor_when ?>"><i class="fa fa-fw fa-clock text-secondary me-2"></i><?= $vendor_when ?></div>
                                <?php } ?>
                                <?php if ($vendor_account || $vendor_pin) { ?>
                                    <div class="mt-1 small" title="What they will ask for">
                                        <i class="fa fa-fw fa-fingerprint text-secondary me-2"></i><?php if ($vendor_account) { ?><span class="font-monospace"><?= $vendor_account ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy account number" data-clipboard-text="<?= $vendor_account ?>"><i class="far fa-copy"></i></button><?php } ?>
                                        <?php if ($vendor_pin) { ?><span class="text-muted ms-1">PIN</span> <?= $vendor_pin ?><?php } ?>
                                    </div>
                                <?php } ?>
                                <?php if ($vendor_email) { ?>
                                    <div class="mt-1 text-truncate"><i class="fa fa-fw fa-envelope text-secondary me-2"></i><a href="mailto:<?= $vendor_email ?>" title="<?= $vendor_email ?>"><?= $vendor_email ?></a></div>
                                <?php } ?>
                                <?php if ($vendor_website_url) { ?>
                                    <div class="mt-1 text-truncate"><i class="fa fa-fw fa-globe text-secondary me-2"></i><a href="<?= escapeHtml($vendor_website_url) ?>" target="_blank" rel="noopener noreferrer" title="<?= $vendor_website ?>"><?= escapeHtml(vendorWebsiteLabel($ticket['vendor_website'])) ?></a></div>
                                <?php } ?>
                            <?php } ?>

                            <?php if ($project_id) { ?>
                                <div class="ticket-link-heading <?= ($asset_id || $vendor_id) ? 'mt-3 pt-2 border-top' : '' ?>">Project</div>
                                <div class="mt-1 d-flex align-items-center gap-1 min-w-0">
                                    <a class="text-truncate" href="<?= $project_url ?>" title="<?= $project_name ?>"><i class="fa fa-fw fa-project-diagram text-secondary me-2"></i><?= $project_name ?></a>
                                    <a class="ajax-modal ms-auto text-body-secondary" href="#" data-modal-size="lg" data-modal-url="modals/project/project.php?id=<?= $project_id ?>" title="Quick look"><i class="fas fa-fw fa-eye"></i></a>
                                </div>
                                <?php if ($project_stats && $project_stats['tickets']) { ?>
                                    <div class="mt-1 small text-body-secondary"><?= projectProgressHtml($project_stats) ?></div>
                                <?php } ?>
                                <?php if ($project_manager_name) { ?>
                                    <div class="mt-1 text-truncate"><i class="fa fa-fw fa-user-tie text-secondary me-2"></i><?= $project_manager_name ?> <span class="text-muted small">(manager)</span></div>
                                <?php } ?>
                            <?php } ?>

                            <?php if ($ticket_services) { ?>
                                <div class="ticket-link-heading <?= ($asset_id || $vendor_id || $project_id) ? 'mt-3 pt-2 border-top' : '' ?>">Services</div>
                                <?php foreach ($ticket_services as $ticket_service) {
                                    $ticket_service_id = intval($ticket_service['service_id']); ?>
                                    <div class="mt-1 d-flex align-items-center gap-1 min-w-0">
                                        <span class="d-flex min-w-0 flex-grow-1"><?= serviceLink($ticket_service_id, $ticket_service['service_name'], $client_id, ['category' => $ticket_service['service_category']]) ?></span>
                                        <?php if ($ticket_service['service_importance'] === 'High') { ?><?= serviceImportancePill('High') ?><?php } ?>
                                        <?php if (!$ticket_service['linked']) { ?>
                                            <span class="small text-muted text-nowrap" title="One of its assets is on this ticket">via asset</span>
                                        <?php } elseif ($can_link) { ?>
                                            <a class="confirm-link ms-1" href="post.php?unlink_service_ticket=<?= $ticket_id ?>&service_id=<?= $ticket_service_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Not about this service">
                                                <i class="fas fa-fw fa-times text-secondary"></i>
                                            </a>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            <?php } ?>

                            <?php if ($watcher_count) { ?>
                                <div class="ticket-link-heading <?= ($asset_id || $vendor_id || $project_id || $ticket_services) ? 'mt-3 pt-2 border-top' : '' ?>">
                                    Watchers <span class="text-muted text-lowercase fw-normal">&middot; copied on emailed replies</span>
                                </div>
                                <?php while ($watcher_row = mysqli_fetch_assoc($sql_ticket_watchers)) {
                                    $watcher_id = intval($watcher_row['watcher_id']);
                                    $ticket_watcher_email = escapeHtml($watcher_row['watcher_email']);
                                    ?>
                                    <div class="mt-1 d-flex align-items-center">
                                        <span class="text-truncate flex-grow-1" title="<?= $ticket_watcher_email ?>"><i class="fa fa-fw fa-envelope text-secondary me-2"></i><?= $ticket_watcher_email ?></span>
                                        <?php if ($can_link) { ?>
                                            <a class="confirm-link ms-2" href="post.php?delete_ticket_watcher=<?= $watcher_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Stop copying <?= $ticket_watcher_email ?>">
                                                <i class="fas fa-fw fa-times text-secondary"></i>
                                            </a>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            <?php } ?>

                        </div>
                    </div>
                <?php } ?>

                <!-- History - the audit trail, plus the SLA and closure detail -->
                <div class="card mb-3 collapsed-card">
                    <div class="card-header px-3 py-2">
                        <h5 class="card-title mt-1"><i class="fas fa-fw fa-history me-2"></i>History</h5>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" title="Show/hide" data-lte-toggle="card-collapse">
                                <i class="fas fa-chevron-up"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-3" style="display: none;">

                        <dl class="ticket-history-facts mb-0">
                            <dt>Created</dt>
                            <dd title="<?= $ticket_created_at ?>"><?= date('M d, Y • g:i A', strtotime($ticket_created_at)) ?></dd>

                            <?php if ($ticket_first_response_at) { ?>
                                <dt>First response</dt>
                                <dd title="<?= $ticket_first_response_at ?>"><?= date('M d, Y • g:i A', strtotime($ticket_first_response_at)) ?></dd>
                            <?php } ?>

                            <?php if ($ticket_sla_id && $ticket_response_due_at) { ?>
                                <dt>Respond by</dt>
                                <dd>
                                    <?= date('M d, Y • g:i A', strtotime($ticket_response_due_at)) ?>
                                    <?php if (!is_null($ticket_response_sla_met)) { echo $ticket_response_sla_met ? "<i class='fas fa-fw fa-check text-success ms-1' title='Response SLA met'></i>" : "<i class='fas fa-fw fa-exclamation-triangle text-danger ms-1' title='Response SLA missed'></i>"; } ?>
                                </dd>
                            <?php } ?>

                            <?php if ($ticket_sla_id && $ticket_resolution_due_at) { ?>
                                <dt>Resolve by</dt>
                                <dd>
                                    <?php if ($ticket_sla_paused && $ticket_is_open) { ?>
                                        <span class="text-warning-emphasis"><i class="fas fa-fw fa-pause-circle me-1"></i>Paused</span>
                                    <?php } else { ?>
                                        <?= date('M d, Y • g:i A', strtotime($ticket_resolution_due_at)) ?>
                                    <?php } ?>
                                    <?php if (!is_null($ticket_resolution_sla_met)) { echo $ticket_resolution_sla_met ? "<i class='fas fa-fw fa-check text-success ms-1' title='Resolution SLA met'></i>" : "<i class='fas fa-fw fa-exclamation-triangle text-danger ms-1' title='Resolution SLA missed'></i>"; } ?>
                                </dd>
                            <?php } ?>

                            <?php if ($ticket_collaborators) { ?>
                                <dt>Worked on by</dt>
                                <dd><?= $ticket_collaborators ?></dd>
                            <?php } ?>

                            <?php if ($ticket_resolved_at) { ?>
                                <dt>Resolved</dt>
                                <dd title="<?= $ticket_resolved_at ?>"><?= date('M d, Y • g:i A', strtotime($ticket_resolved_at)) ?></dd>
                            <?php } ?>

                            <?php if ($ticket_is_closed) { ?>
                                <dt>Closed</dt>
                                <dd title="<?= $ticket_closed_at ?>">
                                    <?= date('M d, Y • g:i A', strtotime($ticket_closed_at)) ?>
                                    <?php
                                    // A zero closed_by is the client closing it from the portal or guest link,
                                    // or the nightly auto-close on an unassigned ticket - so no name is shown
                                    // rather than the old catch-all "User"
                                    if (!empty($ticket_closed_by)) { echo "<div class='small text-muted'>by " . $ticket_closed_by_display . "</div>"; }
                                    ?>
                                </dd>
                                <?php if ($ticket_feedback) { ?>
                                    <dt>Client feedback</dt>
                                    <dd><?= $ticket_feedback ?></dd>
                                <?php } ?>
                            <?php } ?>
                        </dl>

                        <?php if ($ticket_history_count) { ?>
                            <div class="mt-3 pt-2 border-top">
                                <div class="ticket-link-heading mb-2">Changes</div>
                                <?php while ($history_row = mysqli_fetch_assoc($sql_ticket_history)) {
                                    $ticket_history_description = escapeHtml($history_row['ticket_history_description']);
                                    $ticket_history_created_at = escapeHtml($history_row['ticket_history_created_at']);
                                    ?>
                                    <div class="mb-2 small">
                                        <?= $ticket_history_description ?>
                                        <div class="text-muted" title="<?= $ticket_history_created_at ?>"><?= timeAgo($ticket_history_created_at) ?></div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>

                    </div>
                </div>

            </div> <!-- End ticket-layout-side -->

        </div> <!-- End ticket-layout -->

    <?php
    }
}

require_once "../includes/footer.php";

?>

<script src="/js/show_modals.js"></script>

<?php
// The timer drives the Time worked fields in the reply form, so it only loads where that form is.
// It used to load on resolved tickets and for read-only agents too, find no fields, and throw.
if (!empty($can_edit_ticket) && !empty($ticket_is_open)) { ?>
    <!-- create js variable related to ticket timer setting -->
    <script type="text/javascript">
        var ticketAutoStart = <?= json_encode($config_ticket_timer_autostart) ?>;
    </script>

    <!-- Ticket Time Tracking JS -->
    <script src="js/ticket_time_tracking.js"></script>
<?php } ?>

<?php if (empty($ticket_closed_at)) { ?>
    <!-- Ticket collision detect JS (jQuery is called in footer, so collision detection script MUST be below it) -->
    <script src="js/ticket_collision_detection.js"></script>
<?php } ?>

<script src="/js/pretty_content.js"></script>

<script src="/libs/SortableJS/Sortable.min.js"></script>
<script>
(function () {
    // Task reordering
    const taskTable = document.querySelector('table#tasks tbody');
    if (taskTable) {
        new Sortable(taskTable, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () {
                const rows = document.querySelectorAll('table#tasks tbody tr');
                const positions = Array.from(rows).map((row, index) => ({
                    id: row.dataset.taskId,
                    order: index
                }));

                itflowPostForm('ajax.php', {
                    update_ticket_tasks_order: true,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                    ticket_id: <?= $ticket_id ?>,
                    positions: positions
                });
            }
        });
    }

    // Canned responses - insert at the cursor rather than replacing, so picking one into a
    // half-written reply adds to it. The body is fetched on demand; TinyMCE owns the field
    // by now, so setting the textarea value directly would do nothing visible.
    const cannedPicker = document.getElementById('canned_response_picker');
    if (cannedPicker) {
        cannedPicker.addEventListener('change', function () {
            const cannedId = cannedPicker.value;
            if (!cannedId) return;

            cannedPicker.disabled = true;

            fetch('ajax.php?get_canned_response=' + encodeURIComponent(cannedId))
                .then(response => response.json())
                .then(data => {
                    const editor = window.tinymce ? tinymce.get('ticket_reply') : null;

                    if (editor) {
                        editor.insertContent(data.body);
                        editor.focus();
                    } else {
                        // TinyMCE failed to load - fall back to the plain textarea
                        const textarea = document.getElementById('ticket_reply');
                        if (textarea) {
                            textarea.value += data.body;
                        }
                    }
                })
                .catch(() => {
                    alert('Could not load that canned response.');
                })
                .finally(() => {
                    cannedPicker.disabled = false;
                    cannedPicker.value = '';
                });
        });
    }

    // Reply composer - the type buttons above are the entry point. The composer stays
    // collapsed until one is picked, so the conversation is not pushed down the page and
    // a public reply is never the default.
    const replyComposer = document.getElementById('replyComposer');
    if (replyComposer) {
        const replyComposerCollapse = bootstrap.Collapse.getOrCreateInstance(replyComposer, { toggle: false });
        const replyTypes = document.querySelectorAll('input[name="public_reply_type"]');

        // Who will see it and what the button does, for whichever type is picked
        const replyAudience = document.getElementById('replyAudience');
        const replySubmitLabel = document.getElementById('replySubmitLabel');
        const replySubmitLabels = { '0': 'Add note', '1': 'Post note', '2': 'Send email' };

        function showReplyType(type) {
            if (replyAudience) {
                replyAudience.querySelectorAll('[data-audience]').forEach(line => {
                    line.hidden = line.dataset.audience !== type;
                });
            }
            if (replySubmitLabel) {
                replySubmitLabel.textContent = replySubmitLabels[type] || 'Send';
            }
        }

        // Only ever opens. Switching Internal -> Public mid-draft must not throw the draft away.
        replyTypes.forEach(radio => radio.addEventListener('change', () => {
            showReplyType(radio.value);
            replyComposerCollapse.show();
        }));

        replyComposer.addEventListener('shown.bs.collapse', function () {
            // TinyMCE's autoresize plugin measured the editor while its container was
            // display:none, so it sized to nothing. Re-measure now the box is real.
            const editor = window.tinymce ? tinymce.get('ticket_reply') : null;

            if (editor) {
                editor.execCommand('mceAutoResize');
                editor.focus();
            } else {
                const textarea = document.getElementById('ticket_reply');
                if (textarea) textarea.focus();
            }
        });

        const cancelReply = document.getElementById('cancelReply');
        if (cancelReply) {
            cancelReply.addEventListener('click', function () {
                replyComposerCollapse.hide();
                replyTypes.forEach(radio => { radio.checked = false; });
            });
        }

        // The status select is required and lives inside the collapse. A required control in a
        // display:none container blocks submission with nothing on screen to explain it, and the
        // form can still be submitted with Enter from a focused reply-type button, so refuse
        // outright while the composer is shut.
        const replyForm = replyComposer.closest('form');
        if (replyForm) {
            replyForm.addEventListener('submit', function (e) {
                if (!replyComposer.classList.contains('show')) {
                    e.preventDefault();
                }
            });
        }
    }

    // Conversation filter - show everything, only what the client can see, or only internal notes
    const replyFilter = document.getElementById('replyFilter');
    if (replyFilter) {
        replyFilter.addEventListener('click', function (e) {
            const button = e.target.closest('[data-reply-filter]');
            if (!button) return;

            const wanted = button.dataset.replyFilter;

            replyFilter.querySelectorAll('[data-reply-filter]').forEach(b => {
                b.classList.toggle('btn-dark', b === button);
                b.classList.toggle('active', b === button);
                b.classList.toggle('btn-outline-dark', b !== button);
            });

            document.querySelectorAll('.ticket-reply').forEach(card => {
                card.hidden = wanted !== 'all' && card.dataset.replyGroup !== wanted;
            });
        });
    }
})();
</script>
