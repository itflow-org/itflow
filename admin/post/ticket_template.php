<?php

/*
 * Ticket templates - Admin > Ticket Templates. The work is in
 * functions/ticket_template.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** A task's "Only when" (ticketTemplateWhenFromPost()) as SQL for a SET clause. */
function ticketTemplateWhenSql($when) {
    return "task_template_when_fill_in = " . ($when ? "'" . escapeSql($when['fill_in']) . "'" : 'NULL') . ", task_template_when_value = " . ($when ? "'" . escapeSql($when['value']) . "'" : 'NULL');
}

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

    // Its tasks and tags go with it (its fill-ins by their foreign key), and it comes off the project templates that raise it
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
    $when_sql = ticketTemplateWhenSql(ticketTemplateWhenFromPost($_POST['when'] ?? '', ticketTemplateFillInsFor($ticket_template_id)));

    mysqli_query($mysqli, "INSERT INTO task_templates SET task_template_name = '$task_name_sql', task_template_order = $order, task_template_completion_estimate = $estimate, task_template_ticket_template_id = $ticket_template_id, $when_sql");

    logAudit("Ticket Template", "Edit", "$session_name added task $task_name_sql to ticket template " . escapeSql($row['ticket_template_name']), 0, $ticket_template_id);

    flashAlert("Task <strong>" . escapeHtml($task_name) . "</strong> added");

    redirect();

}

if (isset($_POST['edit_ticket_template_task'])) {

    validateCSRFToken();

    $task_template_id = intval($_POST['task_template_id'] ?? 0);
    $task = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT task_template_ticket_template_id, task_template_when_fill_in, task_template_when_value FROM task_templates WHERE task_template_id = $task_template_id"));
    $row = ticketTemplateForRequest($task['task_template_ticket_template_id'] ?? 0);
    $task_name = trim(mb_substr(strip_tags((string) ($_POST['name'] ?? '')), 0, 200));
    if ($task_name === '') {
        flashAlert('Give the task a name.', 'error');
        redirect();
    }
    $task_name_sql = escapeSql($task_name);
    $estimate = max(0, min(100000, intval($_POST['completion_estimate'] ?? 0)));
    $current_when = trim((string) $task['task_template_when_fill_in']) !== '' ? ['fill_in' => $task['task_template_when_fill_in'], 'value' => (string) $task['task_template_when_value']] : null;
    $when_sql = ticketTemplateWhenSql(ticketTemplateWhenFromPost($_POST['when'] ?? '', ticketTemplateFillInsFor($row['ticket_template_id']), $current_when));

    // Its place in the list stays as it is - the list is reordered by dragging
    mysqli_query($mysqli, "UPDATE task_templates SET task_template_name = '$task_name_sql', task_template_completion_estimate = $estimate, $when_sql WHERE task_template_id = $task_template_id");

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

if (isset($_POST['save_ticket_template_fill_in'])) {

    // A fill-in set up - a new one, or one in the text or already set up, changed
    validateCSRFToken();

    $row = ticketTemplateForRequest($_POST['ticket_template_id'] ?? 0);
    $ticket_template_id = intval($row['ticket_template_id']);
    $template_name = escapeSql($row['ticket_template_name']);
    $original = trim((string) ($_POST['original_name'] ?? ''));

    $name = ticketFillInName($_POST['name'] ?? '');
    if ($name === '') {
        flashAlert("A fill-in's name is what goes in square brackets - a letter first, then letters, digits, spaces, _ - or /, up to 41 in all. [Client] and the others filled in from the ticket are taken.", 'error');
        redirect();
    }
    $type = isset(TICKET_FILL_IN_TYPES[$_POST['type'] ?? '']) ? $_POST['type'] : 'text';
    $options = $type === 'list' ? ticketFillInOptions($_POST['options'] ?? '') : [];
    if ($type === 'list' && count($options) < 2) {
        flashAlert('A list needs at least two choices - one per line.', 'error');
        redirect();
    }
    $required = intval($_POST['required'] ?? 0) ? 1 : 0;
    $help = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['help'] ?? ''))), 0, 255));

    // One name, one fill-in - adding one already in the text but not set up yet sets that one up
    foreach (ticketTemplateFillInsFor($ticket_template_id) as $fill_in) {
        if ($original === '' && !$fill_in['id'] && strtolower($fill_in['name']) === strtolower($name)) {
            $original = $fill_in['name'];
            continue;
        }
        if (strtolower($fill_in['name']) === strtolower($name) && strtolower($fill_in['name']) !== strtolower($original)) {
            flashAlert("<strong>" . escapeHtml($row['ticket_template_name']) . "</strong> already asks for <strong>" . escapeHtml($fill_in['name']) . "</strong>.", 'error');
            redirect();
        }
    }

    $existing = $original !== '' ? mysqli_fetch_row(mysqli_query($mysqli, "SELECT fill_in_id FROM ticket_template_fill_ins WHERE fill_in_ticket_template_id = $ticket_template_id AND fill_in_name = '" . escapeSql($original) . "' LIMIT 1")) : null;

    // Renamed: its [Name] in the text, and the tasks for its answers, go with it
    if ($original !== '' && $original !== $name) {
        ticketTemplateRenameFillIn($ticket_template_id, $original, $name);
    }
    $set = "fill_in_name = '" . escapeSql($name) . "', fill_in_type = '$type', fill_in_options = " . ($options ? "'" . escapeSql(implode("\n", $options)) . "'" : 'NULL') . ",
        fill_in_required = $required, fill_in_help = " . ($help !== '' ? "'" . escapeSql($help) . "'" : 'NULL');
    if ($existing) {
        mysqli_query($mysqli, "UPDATE ticket_template_fill_ins SET $set WHERE fill_in_id = " . intval($existing[0]));
    } else {
        $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(fill_in_order), 0) + 1 FROM ticket_template_fill_ins WHERE fill_in_ticket_template_id = $ticket_template_id"))[0]);
        mysqli_query($mysqli, "INSERT INTO ticket_template_fill_ins SET fill_in_ticket_template_id = $ticket_template_id, fill_in_order = $order, $set");
    }
    // Only a list or yes or no has answers for a task to be for
    if ($type !== 'list' && $type !== 'yesno') {
        mysqli_query($mysqli, "UPDATE task_templates SET task_template_when_fill_in = NULL, task_template_when_value = NULL WHERE task_template_ticket_template_id = $ticket_template_id AND task_template_when_fill_in = '" . escapeSql($name) . "'");
    }

    logAudit("Ticket Template", "Edit", "$session_name " . ($original === '' ? 'added' : 'changed') . " fill-in " . escapeSql($name) . " on ticket template $template_name", 0, $ticket_template_id);

    flashAlert("Fill-in <strong>" . escapeHtml($name) . "</strong> saved" . ($original === '' ? " - put <span class='font-monospace'>[" . escapeHtml($name) . "]</span> in the text where the answer goes" : ''));

    redirect();

}

if (isset($_GET['delete_ticket_template_fill_in'])) {

    // A fill-in's setup removed - gone, or, while its [Name] is still in the text, asked as plain optional text
    validateCSRFToken();

    $fill_in_id = intval($_GET['delete_ticket_template_fill_in']);
    $fill_in = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT fill_in_name, fill_in_ticket_template_id FROM ticket_template_fill_ins WHERE fill_in_id = $fill_in_id"));
    $row = ticketTemplateForRequest($fill_in['fill_in_ticket_template_id'] ?? 0);
    $ticket_template_id = intval($row['ticket_template_id']);
    $name = (string) $fill_in['fill_in_name'];

    mysqli_query($mysqli, "DELETE FROM ticket_template_fill_ins WHERE fill_in_id = $fill_in_id");
    mysqli_query($mysqli, "UPDATE task_templates SET task_template_when_fill_in = NULL, task_template_when_value = NULL WHERE task_template_ticket_template_id = $ticket_template_id AND task_template_when_fill_in = '" . escapeSql($name) . "'");

    $still_asked = false;
    foreach (ticketTemplateFillInsFor($ticket_template_id) as $other) {
        $still_asked = $still_asked || strtolower($other['name']) === strtolower($name);
    }

    logAudit("Ticket Template", "Edit", "$session_name removed fill-in " . escapeSql($name) . " from ticket template " . escapeSql($row['ticket_template_name']), 0, $ticket_template_id);

    flashAlert($still_asked
        ? "Fill-in <strong>" . escapeHtml($name) . "</strong> reset - <span class='font-monospace'>[" . escapeHtml($name) . "]</span> is still in the text, so it is asked for as plain, optional text"
        : "Fill-in <strong>" . escapeHtml($name) . "</strong> removed", 'error');

    redirect();

}

if (isset($_POST['reorder_ticket_template_fill_ins'])) {

    // Dragged into order on the template's page - answers with JSON, as the page saves it in the background.
    // Every one dragged keeps its place from now on, so each gets a row.
    header('Content-Type: application/json');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        echo json_encode(['ok' => false, 'error' => 'The page is out of date - reload it']);
        exit;
    }
    $row = ticketTemplateRow($_POST['ticket_template_id'] ?? 0);
    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'That ticket template no longer exists']);
        exit;
    }
    $ticket_template_id = intval($row['ticket_template_id']);
    $fill_ins = [];
    foreach (ticketTemplateFillInsFor($ticket_template_id) as $fill_in) {
        $fill_ins[strtolower($fill_in['name'])] = $fill_in;
    }
    $order = 0;
    foreach ((array) ($_POST['names'] ?? []) as $name) {
        $fill_in = $fill_ins[strtolower((string) $name)] ?? null;
        if (!$fill_in) {
            continue;
        }
        $order++;
        if ($fill_in['id']) {
            mysqli_query($mysqli, "UPDATE ticket_template_fill_ins SET fill_in_order = $order WHERE fill_in_id = " . intval($fill_in['id']));
        } else {
            mysqli_query($mysqli, "INSERT INTO ticket_template_fill_ins SET fill_in_ticket_template_id = $ticket_template_id, fill_in_name = '" . escapeSql($fill_in['name']) . "', fill_in_type = 'text', fill_in_order = $order");
        }
    }
    logAudit("Ticket Template", "Edit", "$session_name reordered the fill-ins on ticket template " . escapeSql($row['ticket_template_name']), 0, $ticket_template_id);

    echo json_encode(['ok' => true]);
    exit;

}
