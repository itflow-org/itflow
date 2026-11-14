<?php

// A project's fields from POST - project_name, project_description, project_start,
// project_due, project_budget_hours, project_manager and project_contact_id - checked
// as the project form checks them, each only when sent, into $project_set as SQL values.
// On an update $project_row is the project, for what isn't sent. $client_id is the
// project's client (0: internal). $project_model_error says what was wrong.

if (!function_exists('apiDeny')) {
    http_response_code(404);
    exit();
}

$project_row = $project_row ?? null;
$project_set = [];
$project_model_error = '';

if (array_key_exists('project_name', $_POST)) {
    $name = trim((string) (is_scalar($_POST['project_name']) ? $_POST['project_name'] : ''));
    if ($name === '') {
        $project_model_error = 'A project needs a name - project_name.';
    } else {
        $project_set['project_name'] = "'" . escapeSql(mb_substr($name, 0, 255)) . "'";
    }
}

if (array_key_exists('project_description', $_POST)) {
    $project_set['project_description'] = "'" . escapeSql(trim((string) (is_scalar($_POST['project_description']) ? $_POST['project_description'] : ''))) . "'";
}

// Dates as Y-m-d, or empty for none
foreach (['project_start' => 'project_start', 'project_due' => 'project_due'] as $field => $column) {
    if (array_key_exists($field, $_POST)) {
        $value = trim((string) (is_scalar($_POST[$field]) ? $_POST[$field] : ''));
        $sql_value = projectDateSql($value);
        if ($value !== '' && $sql_value === 'NULL') {
            $project_model_error = "$field is a date - 2026-11-05 - or empty for none.";
        }
        $project_set[$column] = $sql_value;
    }
}
$start = $project_set['project_start'] ?? (!empty($project_row['project_start']) ? "'" . $project_row['project_start'] . "'" : 'NULL');
$due = $project_set['project_due'] ?? (!empty($project_row['project_due']) ? "'" . $project_row['project_due'] . "'" : 'NULL');
if ($start !== 'NULL' && $due !== 'NULL' && $due < $start) {
    $project_model_error = 'The due date is before the start date.';
}

if (array_key_exists('project_budget_hours', $_POST)) {
    $value = trim((string) (is_scalar($_POST['project_budget_hours']) ? $_POST['project_budget_hours'] : ''));
    $budget = projectBudgetHours($value);
    if ($value !== '' && $value !== '0' && $budget === null) {
        $project_model_error = 'project_budget_hours is the hours sold for it, such as 40 - or empty for no budget.';
    }
    $project_set['project_budget_hours'] = $budget === null ? 'NULL' : number_format($budget, 2, '.', '');
}

if (array_key_exists('project_manager', $_POST)) {
    $manager = ticketAgentId($_POST['project_manager']);
    if (intval($_POST['project_manager']) && !$manager) {
        $project_model_error = 'project_manager is an active agent - see technicians/read.php - or 0 for none.';
    }
    $project_set['project_manager'] = $manager;
}

if (array_key_exists('project_contact_id', $_POST)) {
    $contact = projectContactId($_POST['project_contact_id'], $client_id);
    if (intval($_POST['project_contact_id']) && !$contact) {
        $project_model_error = $client_id ? "project_contact_id isn't one of the client's contacts." : 'An internal project has no client contact.';
    }
    $project_set['project_contact_id'] = $contact;
}

if ($project_model_error !== '') {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = $project_model_error;
    echo json_encode($return_arr);
    exit();
}
