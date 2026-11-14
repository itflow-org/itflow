<?php

// A recurring ticket's fields from POST - subject, details, priority, frequency,
// billable, assigned_to, contact_id, asset_id, asset_ids, category_id,
// ticket_template_id, fill_ins and tasks - checked as the recurring ticket form
// checks them, each only when sent. $rt_row is the recurring ticket on an update
// (null for a new one), and $client_id its client.
//
//   $rt_set       column => SQL value, for what was sent
//   $rt_assets    its other assets (ids, the client's own), or null to leave them
//   $rt_tasks     its tasks ([name, estimate]), or null to leave them
//   $rt_fill_ins  the answers to its template's fill-ins (ticketFillInAnswers()), or null to leave them
//   $rt_template  the template it is raised from, when one was sent (its row)
//
// Anything that won't do is turned down with 400 and why.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$rt_row = $rt_row ?? null;
$rt_set = [];
$rt_assets = null;
$rt_tasks = null;
$rt_fill_ins = null;
$rt_template = null;

$rt_refuse = function ($message) use (&$return_arr) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $message;
    echo json_encode($return_arr);
    exit();
};
$rt_text = function ($field) {
    return trim((string) (is_scalar($_POST[$field] ?? '') ? ($_POST[$field] ?? '') : ''));
};

if (array_key_exists('subject', $_POST)) {
    if ($rt_text('subject') === '') {
        $rt_refuse('A recurring ticket needs a subject.');
    }
    $rt_set['recurring_ticket_subject'] = "'" . escapeSql(mb_substr($rt_text('subject'), 0, 500)) . "'";
}
if (array_key_exists('details', $_POST)) {
    $rt_set['recurring_ticket_details'] = "'" . mysqli_real_escape_string($mysqli, (string) (is_scalar($_POST['details']) ? $_POST['details'] : '')) . "'";
}
if (array_key_exists('priority', $_POST)) {
    if (!in_array($_POST['priority'], TICKET_PRIORITIES, true)) {
        $rt_refuse('priority is one of: ' . implode(', ', TICKET_PRIORITIES) . '.');
    }
    $rt_set['recurring_ticket_priority'] = "'" . escapeSql($_POST['priority']) . "'";
}
if (array_key_exists('frequency', $_POST)) {
    $frequency = recurringTicketFrequency($rt_text('frequency'));
    if ($frequency === '') {
        $rt_refuse('frequency is how often it runs, one of: ' . implode(', ', array_keys(RECURRING_TICKET_FREQUENCIES)) . '.');
    }
    $rt_set['recurring_ticket_frequency'] = "'" . escapeSql($frequency) . "'";
}

// Billing is the sales module's to change, as in the form
if (array_key_exists('billable', $_POST)) {
    if (intval(lookupUserPermission('module_sales')) < 2) {
        apiDeny(apiPermissionText('module_sales', 2));
    }
    $rt_set['recurring_ticket_billable'] = intval($_POST['billable']) ? 1 : 0;
}

if (array_key_exists('assigned_to', $_POST)) {
    $assigned_to = ticketAgentId($_POST['assigned_to']);
    if (intval($_POST['assigned_to']) && !$assigned_to) {
        $rt_refuse('assigned_to is an active agent - see technicians/read.php - or 0 for nobody.');
    }
    $rt_set['recurring_ticket_assigned_to'] = $assigned_to;
}

// What it links to has to be the client's own
foreach (['contact_id' => ['contact', 'recurring_ticket_contact_id'], 'asset_id' => ['asset', 'recurring_ticket_asset_id']] as $field => [$kind, $column]) {
    if (array_key_exists($field, $_POST)) {
        $id = ticketClientOwnedId($kind, $_POST[$field], $client_id);
        if (intval($_POST[$field]) && !$id) {
            $rt_refuse("$field isn't one of the client's {$kind}s.");
        }
        $rt_set[$column] = $id;
    }
}
if (array_key_exists('asset_ids', $_POST)) {
    $primary = intval($rt_set['recurring_ticket_asset_id'] ?? ($rt_row['recurring_ticket_asset_id'] ?? 0));
    $wanted = array_values(array_diff(array_unique(array_filter(array_map('intval', (array) $_POST['asset_ids']))), [$primary]));
    $rt_assets = ticketClientOwnedAssetIds($wanted, $client_id, $primary);
    if (count($rt_assets) !== count($wanted)) {
        $rt_refuse("asset_ids are the client's own other assets - these aren't: " . implode(', ', array_diff($wanted, $rt_assets)) . '.');
    }
}

if (array_key_exists('category_id', $_POST)) {
    $category_id = ticketCategoryId($_POST['category_id']);
    if (intval($_POST['category_id']) && !$category_id) {
        $rt_refuse('category_id is a ticket category - see categories/read.php.');
    }
    $rt_set['recurring_ticket_category'] = $category_id;
}

// The template - its fill-ins answered, from what it keeps for those not sent
$template_id = array_key_exists('ticket_template_id', $_POST) ? intval($_POST['ticket_template_id']) : intval($rt_row['recurring_ticket_ticket_template_id'] ?? 0);
if (array_key_exists('ticket_template_id', $_POST)) {
    if ($template_id) {
        $rt_template = ticketTemplateRow($template_id);
        if (!$rt_template || !empty($rt_template['ticket_template_archived_at'])) {
            $rt_refuse("There is no ticket template $template_id - it may have been archived. See Admin > Ticket Templates.");
        }
    }
    $rt_set['recurring_ticket_ticket_template_id'] = $template_id;
}
if ($template_id && (array_key_exists('ticket_template_id', $_POST) || array_key_exists('fill_ins', $_POST))) {
    $posted = $_POST['fill_ins'] ?? [];
    if (!is_array($posted)) {
        $rt_refuse("fill_ins is a JSON object of the template's fill-ins and their answers - {\"Name\": \"answer\"}.");
    }
    $given = [];
    if ($rt_row && intval($rt_row['recurring_ticket_ticket_template_id']) === $template_id) {
        foreach (ticketFillInsStored('recurring_ticket', $rt_row['recurring_ticket_id']) as $stored) {
            $given[$stored['name']] = $stored['value'];
        }
    }
    foreach ($posted as $name => $value) {
        $given[$name] = $value;
    }
    $rt_fill_ins = ticketFillInAnswers(ticketTemplateFillInsFor($template_id), $given, $fill_in_error);
    if ($rt_fill_ins === null) {
        $rt_refuse(html_entity_decode(strip_tags($fill_in_error), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}

// Its tasks - names, or {"name": ..., "estimate": minutes}
if (array_key_exists('tasks', $_POST)) {
    if (!is_array($_POST['tasks']) || count($_POST['tasks']) > 100) {
        $rt_refuse('tasks is a list of up to 100 - each a name, or {"name": "...", "estimate": 15}.');
    }
    $rt_tasks = [];
    foreach (array_values($_POST['tasks']) as $task) {
        $name = trim((string) (is_array($task) ? ($task['name'] ?? '') : (is_scalar($task) ? $task : '')));
        if ($name !== '') {
            $rt_tasks[] = [mb_substr($name, 0, 255), max(0, intval(is_array($task) ? ($task['estimate'] ?? 0) : 0))];
        }
    }
}
