<?php
/*
 * API - Recurring Tickets - Create
 * POST /api/v1/recurring_tickets/create.php
 *
 * Schedule a ticket to be raised for a client again and again, as New
 * Recurring Ticket does - from start_date, every frequency. From a ticket
 * template, its subject, details, priority, category and agent are used for
 * those not sent (and its billing, when the key's agent may set billing), its
 * tasks unless tasks is sent - the ones for the answers given - and its
 * fill-ins are answered from fill_ins. The agent it goes to is told.
 *
 * Parameters (POST, JSON body):
 *   api_key             required - Your API key
 *   client_id           required - The client
 *   subject             required, unless the template has one
 *   frequency           required - Three Days, Weekly, Biweekly, Monthly, Quarterly, Biannually or Annually
 *   start_date          optional - Its first run, Y-m-d, today or later; today if left out
 *   details             optional - HTML
 *   priority            optional - Low, Medium (the default), High or Urgent
 *   billable            optional - 1 or 0; needs Modify on Sales
 *   assigned_to         optional - The agent its tickets go to
 *   contact_id, asset_id  optional - The client's contact and asset
 *   asset_ids           optional - The client's other assets, as a list
 *   category_id         optional - A ticket category
 *   ticket_template_id  optional - A ticket template (Admin > Ticket Templates)
 *   fill_ins            optional - The template's fill-ins answered: {"Name": "answer"}
 *   tasks               optional - Its tasks: names, or {"name": "...", "estimate": minutes}
 *
 * Answer: insert_id, and next_date.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";

$insert_id = false;

if (!$client_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"))) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = 'client_id is required - the client it is for.';
    echo json_encode($return_arr);
    exit();
}

require_once 'recurring_ticket_model.php';

// From a template - what it sets, for whatever the request leaves out
if ($rt_template) {
    if (!isset($rt_set['recurring_ticket_subject'])) {
        $subject = trim((string) $rt_template['ticket_template_subject']) !== '' ? (string) $rt_template['ticket_template_subject'] : (string) $rt_template['ticket_template_name'];
        $rt_set['recurring_ticket_subject'] = "'" . escapeSql(mb_substr($subject, 0, 500)) . "'";
    }
    $rt_set += [
        'recurring_ticket_details' => "'" . mysqli_real_escape_string($mysqli, (string) $rt_template['ticket_template_details']) . "'",
        'recurring_ticket_priority' => "'" . escapeSql(ticketPriority($rt_template['ticket_template_priority'] ?? '')) . "'",
        'recurring_ticket_category' => ticketCategoryId($rt_template['ticket_template_category_id']),
        'recurring_ticket_assigned_to' => ticketAgentId($rt_template['ticket_template_assigned_to']),
    ];
    if (!isset($rt_set['recurring_ticket_billable']) && $rt_template['ticket_template_billable'] !== null && intval(lookupUserPermission('module_sales')) >= 2) {
        $rt_set['recurring_ticket_billable'] = intval($rt_template['ticket_template_billable']) ? 1 : 0;
    }
    if ($rt_tasks === null) {
        $rt_tasks = [];
        foreach (ticketTemplateTasks($rt_template['ticket_template_id']) as $task) {
            if (ticketTemplateTaskApplies($task, $rt_fill_ins ?? [])) {
                $rt_tasks[] = [$task['name'], intval($task['estimate'])];
            }
        }
    }
}

$start_date = array_key_exists('start_date', $_POST) ? recurringTicketDate($_POST['start_date']) : date('Y-m-d');
if (!$start_date || $start_date < date('Y-m-d')) {
    $rt_refuse('start_date is its first run - a date, today or later, such as ' . date('Y-m-d') . '.');
}
if (!isset($rt_set['recurring_ticket_subject'])) {
    $rt_refuse('A recurring ticket needs a subject.');
}
if (!isset($rt_set['recurring_ticket_frequency'])) {
    $rt_refuse('frequency is how often it runs, one of: ' . implode(', ', array_keys(RECURRING_TICKET_FREQUENCIES)) . '.');
}

$rt_set += [
    'recurring_ticket_details' => "''",
    'recurring_ticket_priority' => "'Medium'",
    'recurring_ticket_billable' => 0,
    'recurring_ticket_assigned_to' => 0,
    'recurring_ticket_contact_id' => 0,
    'recurring_ticket_asset_id' => 0,
    'recurring_ticket_category' => 0,
    'recurring_ticket_ticket_template_id' => 0,
];
$rt_set['recurring_ticket_start_date'] = "'$start_date'";
$rt_set['recurring_ticket_next_run'] = "'$start_date'";
$rt_set['recurring_ticket_created_by'] = intval($session_user_id);
$rt_set['recurring_ticket_client_id'] = $client_id;

mysqli_query($mysqli, "INSERT INTO recurring_tickets SET " . implode(', ', array_map(function ($column, $value) {
    return "$column = $value";
}, array_keys($rt_set), $rt_set)));

$recurring_ticket_id = intval(mysqli_insert_id($mysqli));

if ($recurring_ticket_id) {
    $insert_id = $recurring_ticket_id;

    $row = recurringTicketRow($recurring_ticket_id);
    $rt_subject = (string) $row['recurring_ticket_subject'];
    $rt_notify_agent = intval($row['recurring_ticket_assigned_to']);
    require 'recurring_ticket_save.php';

    $return_arr['next_date'] = $start_date;

    $label = escapeSql($rt_subject . ' - ' . $row['recurring_ticket_frequency']);
    logAudit("Recurring Ticket", "Create", "Created recurring ticket $label via API ($api_key_name)", $client_id, $recurring_ticket_id);
    logAudit("API", "Success", "Created recurring ticket $label via API ($api_key_name)", $client_id);
}

// Output
require_once '../create_output.php';
