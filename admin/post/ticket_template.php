<?php

/*
 * Ticket templates - Admin > Ticket Templates. The work is in
 * functions/ticket_template.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The template a request names - back to the list with an error when it is gone. */
function ticketTemplateForRequest($ticket_template_id) {
    $row = ticketTemplateRow($ticket_template_id);
    if (!$row) {
        flashAlert('That ticket template no longer exists.', 'error');
        redirect('ticket_templates.php');
    }
    return $row;
}

if (isset($_POST['add_ticket_template']) || isset($_POST['edit_ticket_template'])) {

    validateCSRFToken();

    $editing = isset($_POST['edit_ticket_template']);
    $ticket_template_id = 0;
    if ($editing) {
        $ticket_template_id = intval(ticketTemplateForRequest($_POST['ticket_template_id'] ?? 0)['ticket_template_id']);
    }

    $fields = ticketTemplateFields($_POST, $error);
    if (!$fields) {
        flashAlert($error, 'error');
        redirect();
    }
    $ticket_template_id = ticketTemplateSave($ticket_template_id, $fields);
    $name = escapeSql($fields['name']);

    // A new one can go at the end of a project template's tickets
    $project_template_id = intval($_POST['project_template'] ?? 0);
    if (!$editing && $project_template_id && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM project_templates WHERE project_template_id = $project_template_id"))) {
        $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(ticket_template_order), 0) + 1 FROM project_template_ticket_templates WHERE project_template_id = $project_template_id"))[0]);
        mysqli_query($mysqli, "INSERT INTO project_template_ticket_templates SET project_template_id = $project_template_id, ticket_template_id = $ticket_template_id, ticket_template_order = $order");
    }

    logAudit("Ticket Template", $editing ? "Edit" : "Create", "$session_name " . ($editing ? 'edited' : 'created') . " ticket template $name", 0, $ticket_template_id);

    flashAlert("Ticket template <strong>" . escapeHtml($fields['name']) . "</strong> " . ($editing ? 'saved' : 'created'));

    // A new one opens, to add its tasks
    redirect($editing ? null : "ticket_template.php?ticket_template_id=$ticket_template_id");

}

if (isset($_GET['copy_ticket_template'])) {

    validateCSRFToken();

    $row = ticketTemplateForRequest($_GET['copy_ticket_template']);
    $new_id = ticketTemplateCopy($row['ticket_template_id']);

    logAudit("Ticket Template", "Create", "$session_name copied ticket template " . escapeSql($row['ticket_template_name']), 0, $new_id);

    flashAlert("Ticket template <strong>" . escapeHtml($row['ticket_template_name']) . "</strong> copied - this is the copy");

    redirect("ticket_template.php?ticket_template_id=$new_id");

}

if (isset($_GET['archive_ticket_template']) || isset($_GET['restore_ticket_template'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_ticket_template']);
    $row = ticketTemplateForRequest($archive ? $_GET['archive_ticket_template'] : $_GET['restore_ticket_template']);
    $ticket_template_id = intval($row['ticket_template_id']);
    $name = escapeSql($row['ticket_template_name']);

    // Archived, it isn't offered for new tickets - recurring tickets and project templates that use it keep it, so restoring it is all it takes
    mysqli_query($mysqli, "UPDATE ticket_templates SET ticket_template_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE ticket_template_id = $ticket_template_id");

    logAudit("Ticket Template", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " ticket template $name", 0, $ticket_template_id);

    flashAlert("Ticket template <strong>" . escapeHtml($row['ticket_template_name']) . "</strong> " . ($archive ? 'archived' : 'restored'), $archive ? 'error' : 'success');

    redirect();

}

if (isset($_GET['delete_ticket_template'])) {

    validateCSRFToken();

    $row = ticketTemplateForRequest($_GET['delete_ticket_template']);
    $ticket_template_id = intval($row['ticket_template_id']);
    $ticket_template_name = escapeSql($row['ticket_template_name']);

    mysqli_query($mysqli, "DELETE FROM ticket_templates WHERE ticket_template_id = $ticket_template_id");

    // Its tasks and tags go with it, and it comes off the project templates that raise it
    mysqli_query($mysqli, "DELETE FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id");
    mysqli_query($mysqli, "DELETE FROM ticket_template_tags WHERE ticket_template_id = $ticket_template_id");
    mysqli_query($mysqli, "DELETE FROM project_template_ticket_templates WHERE ticket_template_id = $ticket_template_id");

    // Unlink from recurring tickets rather than deleting them - the schedule is still
    // wanted, it just stops contributing tasks to the tickets it raises. Tickets raised
    // from it keep everything they were given, but not the link.
    mysqli_query($mysqli, "UPDATE recurring_tickets SET recurring_ticket_ticket_template_id = 0 WHERE recurring_ticket_ticket_template_id = $ticket_template_id");
    mysqli_query($mysqli, "UPDATE tickets SET ticket_template_id = 0 WHERE ticket_template_id = $ticket_template_id");

    logAudit("Ticket Template", "Delete", "$session_name deleted ticket template $ticket_template_name");

    flashAlert("Ticket template <strong>" . escapeHtml($row['ticket_template_name']) . "</strong> and its tasks deleted", 'error');

    redirect(isset($_GET['from_page']) ? 'ticket_templates.php' : null);

}

if (isset($_POST['add_ticket_template_task'])) {

    validateCSRFToken();

    $row = ticketTemplateForRequest($_POST['ticket_template_id'] ?? 0);
    $ticket_template_id = intval($row['ticket_template_id']);
    $task_name = trim(mb_substr(strip_tags((string) ($_POST['task_name'] ?? '')), 0, 200));
    if ($task_name === '') {
        flashAlert('Give the task a name.', 'error');
        redirect();
    }
    $estimate = max(0, min(100000, intval($_POST['task_estimate'] ?? 0)));
    $task_name_sql = escapeSql($task_name);
    $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(task_template_order), 0) + 1 FROM task_templates WHERE task_template_ticket_template_id = $ticket_template_id"))[0]);

    mysqli_query($mysqli, "INSERT INTO task_templates SET task_template_name = '$task_name_sql', task_template_order = $order, task_template_completion_estimate = $estimate, task_template_ticket_template_id = $ticket_template_id");

    logAudit("Ticket Template", "Edit", "$session_name added task $task_name_sql to ticket template " . escapeSql($row['ticket_template_name']), 0, $ticket_template_id);

    flashAlert("Task <strong>" . escapeHtml($task_name) . "</strong> added");

    redirect();

}

if (isset($_POST['edit_ticket_template_task'])) {

    validateCSRFToken();

    $task_template_id = intval($_POST['task_template_id'] ?? 0);
    $task = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT task_template_ticket_template_id FROM task_templates WHERE task_template_id = $task_template_id"));
    $row = ticketTemplateForRequest($task['task_template_ticket_template_id'] ?? 0);
    $task_name = trim(mb_substr(strip_tags((string) ($_POST['name'] ?? '')), 0, 200));
    if ($task_name === '') {
        flashAlert('Give the task a name.', 'error');
        redirect();
    }
    $task_name_sql = escapeSql($task_name);
    $estimate = max(0, min(100000, intval($_POST['completion_estimate'] ?? 0)));

    // Its place in the list stays as it is - the list is reordered by dragging
    mysqli_query($mysqli, "UPDATE task_templates SET task_template_name = '$task_name_sql', task_template_completion_estimate = $estimate WHERE task_template_id = $task_template_id");

    logAudit("Ticket Template", "Edit", "$session_name edited task $task_name_sql on ticket template " . escapeSql($row['ticket_template_name']), 0, intval($row['ticket_template_id']));

    flashAlert("Task <strong>" . escapeHtml($task_name) . "</strong> saved");

    redirect();

}

if (isset($_GET['delete_task_template'])) {

    validateCSRFToken();

    $task_template_id = intval($_GET['delete_task_template']);
    $task = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT task_template_name, task_template_ticket_template_id FROM task_templates WHERE task_template_id = $task_template_id"));
    $row = ticketTemplateForRequest($task['task_template_ticket_template_id'] ?? 0);

    mysqli_query($mysqli, "DELETE FROM task_templates WHERE task_template_id = $task_template_id");

    logAudit("Ticket Template", "Edit", "$session_name deleted task " . escapeSql($task['task_template_name']) . " from ticket template " . escapeSql($row['ticket_template_name']), 0, intval($row['ticket_template_id']));

    flashAlert("Task <strong>" . escapeHtml($task['task_template_name']) . "</strong> deleted", 'error');

    redirect();

}
