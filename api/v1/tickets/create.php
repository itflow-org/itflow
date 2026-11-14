<?php

/*
 * API - raise a ticket. ticket_template_id raises it from a ticket template
 * (Admin > Ticket Templates): the template's subject and details for those not
 * sent, its category, priority, billing and agent for those not sent, its
 * tags, due time and tasks, and its fill-ins answered from ticket_fill_ins
 * ({"Name": "answer"}). ticket_notify = 1 tells people as a ticket raised in
 * ITFlow does - the contact and watchers (when ticket emails to clients are
 * on), the new-ticket address and the agent it is assigned to; "agents" tells
 * only your team, the new-ticket address and the agent. ticket_status
 * starts it in an open status of your choosing - On Hold, say - and
 * ticket_invoice_id ties it to one of the client's invoices: once that is paid
 * in full the ticket gets a note, goes from On Hold back to Open, and its agent
 * is told (invoicePaidFollowUp()). The answer has the ticket's number as people
 * see it and where its contact opens it.
 */

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Ticket-related settings
require_once "../../../includes/load_global_settings.php";

$sql = mysqli_query($mysqli, "SELECT company_name, company_phone FROM companies WHERE company_id = 1");
$row = mysqli_fetch_assoc($sql);
$company_name = $row['company_name'];
$company_phone = formatPhoneNumber($row['company_phone']);

// Parse Info
$ticket_row = false; // Creation, not an update
require_once 'ticket_model.php';

// Default
$insert_id = false;

/* A request that can't be raised as sent - 400 and why, in plain text */
$ticket_refuse = function ($message) use (&$return_arr) {
    $return_arr['success'] = "False";
    $return_arr['message'] = html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    header("HTTP/1.1 400 Bad Request");
    echo json_encode($return_arr);
    exit();
};

// From a template - what it sets, for whatever the request leaves out, and its fill-ins answered
$template = null;
$fill_in_answers = null;
if (!empty($_POST['ticket_template_id'])) {
    $template = ticketTemplateRow($_POST['ticket_template_id']);
    if (!$template || !empty($template['ticket_template_archived_at'])) {
        $ticket_refuse("There is no ticket template " . intval($_POST['ticket_template_id']) . " - it may have been archived. See Admin > Ticket Templates.");
    }
    $posted_fill_ins = $_POST['ticket_fill_ins'] ?? [];
    if (!is_array($posted_fill_ins)) {
        $ticket_refuse("ticket_fill_ins is a JSON object of the template's fill-ins and their answers - {\"Name\": \"answer\"}.");
    }
    $fill_in_answers = ticketFillInAnswers(ticketTemplateFillInsFor($template['ticket_template_id']), $posted_fill_ins, $fill_in_error);
    if ($fill_in_answers === null) {
        $ticket_refuse($fill_in_error);
    }

    if (!isset($_POST['ticket_subject'])) {
        $template_subject = trim((string) $template['ticket_template_subject']) !== '' ? (string) $template['ticket_template_subject'] : (string) $template['ticket_template_name'];
        $subject = escapeSql(mb_substr($template_subject, 0, 500));
    }
    if (!isset($_POST['ticket_details'])) {
        $details = mysqli_real_escape_string($mysqli, (string) $template['ticket_template_details']);
    }
    if (!isset($_POST['ticket_priority'])) {
        $priority = escapeSql(ticketPriority($template['ticket_template_priority'] ?? ''));
    }
    if (!isset($_POST['ticket_billable']) && $template['ticket_template_billable'] !== null) {
        $billable = intval($template['ticket_template_billable']) ? 1 : 0;
    }
    if (!isset($_POST['ticket_assigned_to'])) {
        $assigned_to = ticketAgentId($template['ticket_template_assigned_to']);
    }
}
$category_id = $template ? ticketCategoryId($template['ticket_template_category_id']) : 0;
// 1: everyone, as a ticket raised in ITFlow; 'agents': only your team - the new-ticket address and its agent
$notify_posted = $_POST['ticket_notify'] ?? 0;
$notify_team = in_array($notify_posted, [1, '1', true, 'agents'], true);
$notify = $notify_team && $notify_posted !== 'agents';

// Its status - one still open, never Resolved or Closed (those are reached by resolving and closing it)
$status_posted = intval($_POST['ticket_status'] ?? 0);
if ($status_posted && ($status_posted === TICKET_STATUS_RESOLVED || $status_posted === TICKET_STATUS_CLOSED
    || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM ticket_statuses WHERE ticket_status_id = $status_posted AND ticket_status_active = 1")))) {
    $ticket_refuse("ticket_status $status_posted isn't a status a new ticket can have - an active one other than Resolved and Closed. See Admin > Ticket Statuses.");
}

// The invoice it waits on - the client's own. When that invoice is paid in full, the ticket is told (invoicePaidFollowUp()).
$invoice_link = intval($_POST['ticket_invoice_id'] ?? 0);
if ($invoice_link && (!$client_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM invoices WHERE invoice_id = $invoice_link AND invoice_client_id = " . intval($client_id))))) {
    $ticket_refuse("ticket_invoice_id $invoice_link isn't one of this client's invoices.");
}

// A client that exists, or none (internal work)
$client_exists = !$client_id || mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = " . intval($client_id)));

if (!empty($subject) && $client_exists) {

    $client_id = intval($client_id);
    $ticket_prefix = escapeSql($config_ticket_prefix);

    // If no contact is selected automatically choose the primary contact for the client (if client set)
    if ($contact == 0 && $client_id != 0) {
        $sql = mysqli_query($mysqli,"SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_primary = 1");
        $row = mysqli_fetch_assoc($sql);
        $contact = intval($row['contact_id'] ?? 0);
    }

    $ticket_status = 1; // Default
    if ($assigned_to > 0) {
        $ticket_status = 2; // Set to open if we've auto-assigned an agent
    }
    if ($status_posted) {
        $ticket_status = $status_posted;
    }

    // Atomically increment and get the new ticket number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number),
            config_ticket_next_number = config_ticket_next_number + 1
        WHERE company_id = 1
    ");

    $ticket_number = mysqli_insert_id($mysqli);

    // Insert ticket
    $url_key = randomString(32);
    $insert_sql = mysqli_query($mysqli,"INSERT INTO tickets SET ticket_prefix = '$ticket_prefix', ticket_number = $ticket_number, ticket_source = 'API', ticket_category = $category_id, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = $ticket_status, ticket_billable = $billable, ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_vendor_id = $vendor_id, ticket_created_by = 0, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact, ticket_asset_id = $asset, ticket_url_key = '$url_key', ticket_invoice_id = $invoice_link, ticket_project_id = $ticket_project_id, ticket_client_id = $client_id");

    // Check insert & get insert ID
    if ($insert_sql) {
        $insert_id = mysqli_insert_id($mysqli);
        applyTicketSla($insert_id);

        // Raised from a template - its tags, due time and tasks, its fill-ins kept and its placeholders filled in
        if ($template) {
            ticketTemplateFinish($insert_id, $template['ticket_template_id'], true, true, $fill_in_answers, "Raised by the API ($api_key_name) from template " . $template['ticket_template_name']);
        }

        // Part of a project
        $project = $ticket_project_id ? projectRow($ticket_project_id) : null;
        if ($project) {
            logTicketHistory($insert_id, "Added to project " . escapeSql(projectReference($project) . ' - ' . $project['project_name']) . " via the API ($api_key_name)");
        }

        // Who hears about it, as when it's raised in ITFlow - the contact too, unless it's for the team only
        if ($notify_team) {
            if ($notify && intval($config_ticket_client_general_notifications) === 1) {
                ticketMailCreated($insert_id, 'Ticket Created');
                ticketMailColleaguesNew($insert_id);
            }
            ticketMailNewTicketAddress($insert_id, 'api');
            if ($assigned_to) {
                $new_row = ticketRow($insert_id);
                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '" . escapeSql("Ticket " . ticketReference($new_row) . " - " . $new_row['ticket_subject'] . " has been assigned to you by the API ($api_key_name)") . "',
                    notification_action = '" . escapeSql('/agent/' . ticketUrl($insert_id, $client_id)) . "', notification_client_id = $client_id, notification_user_id = " . intval($assigned_to));
                ticketMailAssigned($assigned_to, [$insert_id], html_entity_decode($api_key_name, ENT_QUOTES, 'UTF-8'));
            }
        }

        // The number people see, and where its contact opens it
        $new_row = ticketRow($insert_id);
        $return_arr['ticket_reference'] = ticketReference($new_row);
        $return_arr['ticket_url'] = ticketEmailUrl($new_row);
        if ($template) {
            $return_arr['ticket_tasks'] = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tasks WHERE task_ticket_id = " . intval($insert_id)))[0]);
        }

        // Logging
        logAudit("Ticket", "Create", "Created ticket $ticket_prefix$ticket_number $subject via API ($api_key_name)" . ($template ? " from template " . escapeSql($template['ticket_template_name']) : ''), $client_id, $insert_id);
        logAudit("API", "Success", "Created ticket $ticket_prefix$ticket_number $subject via API ($api_key_name)", $client_id);
    }

}

// Output
require_once '../create_output.php';
