<?php

/*
 * ITFlow - admin handlers for project templates: an ordered set of ticket
 * templates a new project can raise (functions/project.php,
 * projectApplyTemplate()).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** A project template by id - its name, or back with an error when it is gone. */
function projectTemplateName($project_template_id) {
    $name = getFieldById('project_templates', intval($project_template_id), 'project_template_name');
    if ($name === null || $name === '') {
        flashAlert('That project template no longer exists.', 'error');
        redirect('project_templates.php');
    }
    return $name;
}

if (isset($_POST['add_project_template'])) {

    validateCSRFToken();

    $name_input = trim((string) ($_POST['name'] ?? ''));
    if ($name_input === '') {
        flashAlert('A project template needs a name.', 'error');
        redirect();
    }
    $name = escapeSql(mb_substr($name_input, 0, 200));
    $description = escapeSql(trim((string) ($_POST['description'] ?? '')));

    mysqli_query($mysqli, "INSERT INTO project_templates SET project_template_name = '$name', project_template_description = '$description'");

    $project_template_id = mysqli_insert_id($mysqli);

    logAudit("Project Template", "Create", "$session_name created project template $name", 0, $project_template_id);

    flashAlert("Project template <strong>" . escapeHtml($name_input) . "</strong> created - now add its ticket templates");

    redirect("project_template.php?project_template_id=$project_template_id");

}

if (isset($_POST['edit_project_template'])) {

    validateCSRFToken();

    $project_template_id = intval($_POST['project_template_id'] ?? 0);
    projectTemplateName($project_template_id);

    $name_input = trim((string) ($_POST['name'] ?? ''));
    if ($name_input === '') {
        flashAlert('A project template needs a name.', 'error');
        redirect();
    }
    $name = escapeSql(mb_substr($name_input, 0, 200));
    $description = escapeSql(trim((string) ($_POST['description'] ?? '')));

    mysqli_query($mysqli, "UPDATE project_templates SET project_template_name = '$name', project_template_description = '$description' WHERE project_template_id = $project_template_id");

    logAudit("Project Template", "Edit", "$session_name edited project template $name", 0, $project_template_id);

    flashAlert("Project template <strong>" . escapeHtml($name_input) . "</strong> saved");

    redirect();

}

if (isset($_POST['edit_ticket_template_order'])) {

    validateCSRFToken();

    $ticket_template_id = intval($_POST['ticket_template_id']);
    $project_template_id = intval($_POST['project_template_id']);
    $order = intval($_POST['order']);

    mysqli_query($mysqli, "UPDATE project_template_ticket_templates SET ticket_template_order = $order WHERE ticket_template_id = $ticket_template_id AND project_template_id = $project_template_id");

    redirect();

}

/** Ticket templates into a project template - at the end, in the order picked. */
if (isset($_POST['add_ticket_template_to_project_template'])) {

    validateCSRFToken();

    $project_template_id = intval($_POST['project_template_id'] ?? 0);
    $name = escapeSql(projectTemplateName($project_template_id));

    // ticket_template_id is what the single-pick form posted
    $ids = array_map('intval', (array) ($_POST['ticket_template_ids'] ?? []));
    if (isset($_POST['ticket_template_id'])) {
        $ids[] = intval($_POST['ticket_template_id']);
    }

    $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(ticket_template_order), 0) FROM project_template_ticket_templates WHERE project_template_id = $project_template_id"))[0]);
    $added = 0;
    foreach (array_unique(array_filter($ids)) as $ticket_template_id) {
        if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM ticket_templates WHERE ticket_template_id = $ticket_template_id"))) {
            continue;
        }
        $order++;
        mysqli_query($mysqli, "INSERT IGNORE INTO project_template_ticket_templates SET project_template_id = $project_template_id, ticket_template_id = $ticket_template_id, ticket_template_order = $order");
        $added += mysqli_affected_rows($mysqli) > 0 ? 1 : 0;
    }

    logAudit("Project Template", "Edit", "$session_name added $added ticket template" . ($added === 1 ? '' : 's') . " to project template $name", 0, $project_template_id);

    flashAlert("<strong>$added</strong> ticket template" . ($added === 1 ? '' : 's') . " added");

    redirect();

}

if (isset($_POST['remove_ticket_template_from_project_template'])) {

    validateCSRFToken();

    $ticket_template_id = intval($_POST['ticket_template_id'] ?? 0);
    $project_template_id = intval($_POST['project_template_id'] ?? 0);
    $name = escapeSql(projectTemplateName($project_template_id));

    mysqli_query($mysqli, "DELETE FROM project_template_ticket_templates WHERE project_template_id = $project_template_id AND ticket_template_id = $ticket_template_id");

    logAudit("Project Template", "Edit", "$session_name removed a ticket template from project template $name", 0, $project_template_id);

    flashAlert("Ticket template taken out of the project template", 'error');

    redirect();

}

if (isset($_GET['archive_project_template']) || isset($_GET['restore_project_template'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_project_template']);
    $project_template_id = intval($archive ? $_GET['archive_project_template'] : $_GET['restore_project_template']);
    $name = projectTemplateName($project_template_id);

    mysqli_query($mysqli, "UPDATE project_templates SET project_template_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE project_template_id = $project_template_id");

    logAudit("Project Template", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " project template " . escapeSql($name), 0, $project_template_id);

    flashAlert("Project template <strong>" . escapeHtml($name) . "</strong> " . ($archive ? 'archived - it is no longer offered on New Project' : 'restored'), $archive ? 'error' : 'success');

    redirect();

}

/** A copy, with the same ticket templates in the same order - for a variation on a plan. */
if (isset($_GET['duplicate_project_template'])) {

    validateCSRFToken();

    $project_template_id = intval($_GET['duplicate_project_template']);
    $name = projectTemplateName($project_template_id);
    $copy_name = escapeSql(mb_substr("$name (copy)", 0, 200));

    mysqli_query($mysqli, "INSERT INTO project_templates (project_template_name, project_template_description)
        SELECT '$copy_name', project_template_description FROM project_templates WHERE project_template_id = $project_template_id");
    $copy_id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli, "INSERT INTO project_template_ticket_templates (project_template_id, ticket_template_id, ticket_template_order)
        SELECT $copy_id, ticket_template_id, ticket_template_order FROM project_template_ticket_templates WHERE project_template_id = $project_template_id");

    logAudit("Project Template", "Create", "$session_name duplicated project template " . escapeSql($name), 0, $copy_id);

    flashAlert("Project template <strong>" . escapeHtml($name) . "</strong> duplicated");

    redirect("project_template.php?project_template_id=$copy_id");

}

if (isset($_GET['delete_project_template'])) {

    validateCSRFToken();

    $project_template_id = intval($_GET['delete_project_template']);
    $name = projectTemplateName($project_template_id);

    mysqli_query($mysqli, "DELETE FROM project_templates WHERE project_template_id = $project_template_id");
    mysqli_query($mysqli, "DELETE FROM project_template_ticket_templates WHERE project_template_id = $project_template_id");

    logAudit("Project Template", "Delete", "$session_name deleted project template " . escapeSql($name), 0, $project_template_id);

    flashAlert("Project template <strong>" . escapeHtml($name) . "</strong> deleted - its ticket templates stay", 'error');

    // Its own page is gone
    redirect(strpos((string) ($_SERVER['HTTP_REFERER'] ?? ''), 'project_template.php') !== false ? 'project_templates.php' : null);

}
