<?php
/*
 * API - Projects - Apply a template
 * POST /api/v1/projects/apply_template.php
 *
 * Raise a project template's tickets on an open project, as Tickets from a
 * template does on its page - each set up as its ticket template says, with
 * its tasks, to the project's manager when assign_to_manager is 1, else to the
 * template's agent. The client isn't emailed.
 *
 * Parameters (POST, JSON body):
 *   api_key              required - Your API key
 *   project_id           required - The project
 *   client_id            its client - needed when the key's user is limited to certain clients
 *   project_template_id  required - The project template (Admin > Project Templates)
 *   assign_to_manager    optional - 1: the tickets go to the project's manager
 *
 * Answer: tickets_raised.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';
require_once "../../../includes/load_global_settings.php";

$project_id = intval($_POST['project_id'] ?? 0);

$row = $project_id ? projectRow($project_id) : null;
if ($row && (!apiUserCanAccessClient($row['project_client_id']) || ($client_id && intval($row['project_client_id']) !== $client_id))) {
    $row = null;
}

$update_count = false;

if ($row) {

    $client_id = intval($row['project_client_id']);
    $reference = projectReference($row);

    $refuse = '';
    $project_template_id = intval($_POST['project_template_id'] ?? 0);
    if (!projectIsOpen($row)) {
        $refuse = "Project $reference isn't open - " . (!empty($row['project_archived_at']) ? 'it is archived.' : 'it is completed, reopen it first.');
    } elseif (!$project_template_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM project_templates WHERE project_template_id = $project_template_id AND project_template_archived_at IS NULL"))) {
        $refuse = 'project_template_id is one of your project templates - see Admin > Project Templates.';
    }
    if ($refuse !== '') {
        http_response_code(400);
        $return_arr['success'] = "False";
        $return_arr['message'] = $refuse;
        echo json_encode($return_arr);
        exit();
    }

    $raised = projectApplyTemplate($row, $project_template_id, intval($_POST['assign_to_manager'] ?? 0) ? $row['project_manager'] : 0);

    $update_count = 1;
    $return_arr['tickets_raised'] = $raised;

    $label = escapeSql($reference . ' - ' . $row['project_name']);
    logAudit("Project", "Edit", "Added $raised ticket" . ($raised === 1 ? '' : 's') . " from a template to project $label via API ($api_key_name)", $client_id, intval($row['project_id']));
    logAudit("API", "Success", "Applied a template to project $label via API ($api_key_name)", $client_id);
}

// Output
require_once '../update_output.php';
