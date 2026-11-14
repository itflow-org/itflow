<?php
/*
 * API - Projects - Create
 * POST /api/v1/projects/create.php
 *
 * Start a project, as New Project does - the next project number, for a client
 * or (client_id 0) internal work. A project template raises its tickets on it,
 * to the manager when assign_to_manager is 1, else to each template's agent.
 * The manager is told, unless it is the key's own agent.
 *
 * Parameters (POST, JSON body):
 *   api_key               required - Your API key
 *   project_name          required
 *   client_id             optional - The client; 0 or left out for internal work
 *   project_description   optional
 *   project_start         optional - Y-m-d
 *   project_due           optional - Y-m-d, not before the start
 *   project_budget_hours  optional - The hours sold for it
 *   project_manager       optional - An agent's user id
 *   project_contact_id    optional - One of the client's contacts
 *   project_template_id   optional - A project template (Admin > Project Templates) to raise tickets from
 *   assign_to_manager     optional - 1: the template's tickets go to the manager
 *
 * Answer: insert_id, and project_reference and tickets_raised.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";

$insert_id = false;

$client_ok = !$client_id || mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id AND client_archived_at IS NULL"));

if (!$client_ok) {
    http_response_code(400);
    $return_arr['success'] = "False";
    $return_arr['message'] = "There is no client $client_id - it may be archived. Leave client_id out for internal work.";
    echo json_encode($return_arr);
    exit();
}

if ($client_ok) {

    require_once 'project_model.php';

    $refuse = '';
    if (!isset($project_set['project_name'])) {
        $refuse = 'A project needs a name - project_name.';
    }
    $project_template_id = intval($_POST['project_template_id'] ?? 0);
    if ($project_template_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM project_templates WHERE project_template_id = $project_template_id AND project_template_archived_at IS NULL"))) {
        $refuse = "There is no project template $project_template_id - it may be archived. See Admin > Project Templates.";
    }
    if ($refuse !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $refuse;
        echo json_encode($return_arr);
        exit();
    }

    $project_number = projectNextNumber();
    $project_prefix = escapeSql($config_project_prefix);
    $manager = intval($project_set['project_manager'] ?? 0);

    mysqli_query($mysqli, "INSERT INTO projects SET project_prefix = '$project_prefix', project_number = $project_number, project_name = {$project_set['project_name']},
        project_description = " . ($project_set['project_description'] ?? "''") . ", project_start = " . ($project_set['project_start'] ?? 'NULL') . ",
        project_due = " . ($project_set['project_due'] ?? 'NULL') . ", project_budget_hours = " . ($project_set['project_budget_hours'] ?? 'NULL') . ",
        project_manager = $manager, project_contact_id = " . intval($project_set['project_contact_id'] ?? 0) . ", project_client_id = $client_id");

    $project_id = intval(mysqli_insert_id($mysqli));

    if ($project_id) {
        $insert_id = $project_id;
        $row = projectRow($project_id);

        // A template's tickets - to the manager when asked
        $raised = $project_template_id ? projectApplyTemplate($row, $project_template_id, intval($_POST['assign_to_manager'] ?? 0) ? $manager : 0) : 0;

        projectNotifyManager($row, $manager);

        $reference = projectReference($row);
        $return_arr['project_reference'] = $reference;
        $return_arr['tickets_raised'] = $raised;

        $label = escapeSql($reference . ' - ' . $row['project_name']);
        logAudit("Project", "Create", "Created project $label via API ($api_key_name)", $client_id, $project_id);
        logAudit("API", "Success", "Created project $label via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../create_output.php';
