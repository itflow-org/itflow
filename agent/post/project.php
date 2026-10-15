<?php

/*
 * ITFlow - GET/POST request handler for projects
 *
 * Every handler loads the project through projectForRequest() or
 * projectsForBulk() (functions/project.php): it exists, the agent has the
 * support module at the level asked, and may reach its client - a project
 * with no client is internal work any agent can reach. A ticket goes on a
 * project only when the agent may reach it and it is the project's client's
 * (an internal project takes any). A project is completed once none of its
 * tickets is open.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** What the add and edit forms post, checked - or back with an error. $client_id is the project's. */
function projectFromPost($client_id) {
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        flashAlert('A project needs a name.', 'error');
        redirect();
    }
    $start = projectDateSql($_POST['start'] ?? '');
    $due = projectDateSql($_POST['due_date'] ?? '');
    if ($start !== 'NULL' && $due !== 'NULL' && $due < $start) {
        flashAlert('The due date is before the start date.', 'error');
        redirect();
    }
    $budget = projectBudgetHours($_POST['budget_hours'] ?? '');
    return [
        'name' => escapeSql(mb_substr($name, 0, 255)),
        'description' => escapeSql(trim((string) ($_POST['description'] ?? ''))),
        'start' => $start,
        'due' => $due,
        'budget' => $budget === null ? 'NULL' : number_format($budget, 2, '.', ''),
        'manager' => ticketAgentId($_POST['project_manager'] ?? 0),
        'contact' => projectContactId($_POST['contact_id'] ?? 0, $client_id),
    ];
}

/** The project page, or the list when the project is gone - inside its client when it has one. */
function projectBackUrl($client_id, $project_id = 0) {
    return $project_id ? projectUrl($project_id, $client_id) : 'projects.php' . (intval($client_id) ? '?client_id=' . intval($client_id) : '');
}

if (isset($_POST['add_project'])) {

    validateCSRFToken();

    enforceUserPermission('module_support', 2);

    // A client of the agent's, or none - internal work
    $client_id = intval($_POST['client_id'] ?? 0);
    if ($client_id) {
        if (!getFieldById('clients', $client_id, 'client_name')) {
            flashAlert('That client no longer exists.', 'error');
            redirect();
        }
        enforceClientAccess();
    }

    $p = projectFromPost($client_id);

    $project_template_id = intval($_POST['project_template_id'] ?? 0);
    if ($project_template_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM project_templates WHERE project_template_id = $project_template_id AND project_template_archived_at IS NULL"))) {
        $project_template_id = 0;
    }

    $project_number = projectNextNumber();
    $project_prefix = escapeSql($config_project_prefix);

    mysqli_query($mysqli, "INSERT INTO projects SET project_prefix = '$project_prefix', project_number = $project_number, project_name = '{$p['name']}',
        project_description = '{$p['description']}', project_start = {$p['start']}, project_due = {$p['due']}, project_budget_hours = {$p['budget']},
        project_manager = {$p['manager']}, project_contact_id = {$p['contact']}, project_client_id = $client_id");

    $project_id = mysqli_insert_id($mysqli);
    $row = projectRow($project_id);

    // A template's tickets - to the manager when asked
    $raised = $project_template_id ? projectApplyTemplate($row, $project_template_id, intval($_POST['assign_to_manager'] ?? 0) ? $p['manager'] : 0) : 0;

    projectNotifyManager($row, $p['manager']);

    logAudit("Project", "Create", "$session_name created project " . escapeSql(projectReference($row)) . " - {$p['name']}", $client_id, $project_id);

    flashAlert("Project <strong>" . escapeHtml(projectReference($row) . ' - ' . $row['project_name']) . "</strong> created" . ($raised ? " with <strong>$raised</strong> ticket" . ($raised === 1 ? '' : 's') . " from the template" : ''));

    redirect(projectBackUrl($client_id, $project_id));

}

if (isset($_POST['edit_project'])) {

    validateCSRFToken();

    $row = projectForRequest($_POST['project_id'] ?? 0, 2);
    $project_id = intval($row['project_id']);

    if (!empty($row['project_archived_at'])) {
        flashAlert("Project <strong>" . escapeHtml(projectReference($row)) . "</strong> is archived - restore it to change it.", 'error');
        redirect();
    }

    $p = projectFromPost($client_id);

    mysqli_query($mysqli, "UPDATE projects SET project_name = '{$p['name']}', project_description = '{$p['description']}', project_start = {$p['start']},
        project_due = {$p['due']}, project_budget_hours = {$p['budget']}, project_manager = {$p['manager']}, project_contact_id = {$p['contact']}
        WHERE project_id = $project_id");

    if ($p['manager'] !== intval($row['project_manager'])) {
        projectNotifyManager($row, $p['manager']);
    }

    logAudit("Project", "Edit", "$session_name edited project " . escapeSql(projectReference($row)) . " - {$p['name']}", $client_id, $project_id);

    flashAlert("Project <strong>" . escapeHtml(projectReference($row) . ' - ' . stripslashes($p['name'])) . "</strong> saved");

    redirect();

}

// close_project is the old name, from links made before Complete
if (isset($_GET['complete_project']) || isset($_GET['close_project'])) {

    validateCSRFToken();

    $row = projectForRequest($_GET['complete_project'] ?? $_GET['close_project'], 2);

    $why_not = projectComplete($row);
    if ($why_not !== '') {
        flashAlert("Project <strong>" . escapeHtml(projectReference($row)) . "</strong> can't be completed - " . escapeHtml($why_not) . ".", 'error');
        redirect();
    }

    flashAlert("Project <strong>" . escapeHtml(projectReference($row) . ' - ' . $row['project_name']) . "</strong> completed");

    redirect();

}

if (isset($_GET['reopen_project'])) {

    validateCSRFToken();

    $row = projectForRequest($_GET['reopen_project'], 2);

    if (empty($row['project_completed_at']) || !empty($row['project_archived_at'])) {
        flashAlert("Project <strong>" . escapeHtml(projectReference($row)) . "</strong> isn't completed" . (!empty($row['project_archived_at']) ? ' - it is archived, restore it first' : '') . ".", 'error');
        redirect();
    }

    projectReopen($row);

    flashAlert("Project <strong>" . escapeHtml(projectReference($row) . ' - ' . $row['project_name']) . "</strong> reopened");

    redirect();

}

if (isset($_GET['archive_project'])) {

    validateCSRFToken();

    $row = projectForRequest($_GET['archive_project'], 2);

    projectArchive($row);

    flashAlert("Project <strong>" . escapeHtml(projectReference($row) . ' - ' . $row['project_name']) . "</strong> archived", 'error');

    redirect();

}

if (isset($_GET['restore_project'])) {

    validateCSRFToken();

    $row = projectForRequest($_GET['restore_project'], 2);

    projectRestore($row);

    flashAlert("Project <strong>" . escapeHtml(projectReference($row) . ' - ' . $row['project_name']) . "</strong> restored");

    redirect();

}

if (isset($_GET['delete_project'])) {

    validateCSRFToken();

    $row = projectForRequest($_GET['delete_project'], 3);

    projectDelete($row);

    flashAlert("Project <strong>" . escapeHtml(projectReference($row) . ' - ' . $row['project_name']) . "</strong> deleted - its tickets stay", 'error');

    // From its own page, back to the list - the page is gone
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if (preg_match('#/project\.php#', $referer)) {
        redirect(projectBackUrl(strpos($referer, 'client_id=') !== false ? $row['project_client_id'] : 0));
    }
    redirect();

}

/*
 * Tickets onto a project - picked from its client's tickets, or typed by
 * number for older ones. Moving a ticket off another project is fine; one
 * the agent can't reach, or another client's, is skipped.
 */
if (isset($_POST['link_ticket_to_project'])) {

    validateCSRFToken();

    $row = projectForRequest($_POST['project_id'] ?? 0, 2);

    if (!projectIsOpen($row)) {
        flashAlert("Project <strong>" . escapeHtml(projectReference($row)) . "</strong> isn't open - reopen it to add tickets.", 'error');
        redirect();
    }

    $ticket_ids = array_map('intval', (array) ($_POST['tickets'] ?? []));

    // Numbers typed in, with or without the prefix
    $not_found = [];
    foreach (preg_split('/[\s,;]+/', (string) ($_POST['ticket_numbers'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $typed) {
        $number = intval(preg_replace('/^\D+/', '', $typed));
        $found = $number ? mysqli_fetch_row(mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_number = $number ORDER BY ticket_id DESC LIMIT 1")) : null;
        if ($found) {
            $ticket_ids[] = intval($found[0]);
        } else {
            $not_found[] = escapeHtml($typed);
        }
    }

    $added = 0;
    $skipped = [];
    foreach (array_unique(array_filter($ticket_ids)) as $ticket_id) {
        $ticket = ticketRow($ticket_id);
        if (!$ticket) {
            continue;
        }
        if (intval($ticket['ticket_project_id']) === intval($row['project_id'])) {
            continue;
        }
        $why_not = projectAddTicket($row, $ticket);
        if ($why_not === '') {
            $added++;
        } else {
            $skipped[] = escapeHtml(ticketReference($ticket)) . " ($why_not)";
        }
    }

    if ($added) {
        logAudit("Project", "Edit", "$session_name added $added ticket" . ($added === 1 ? '' : 's') . " to project " . escapeSql(projectReference($row) . ' - ' . $row['project_name']), $client_id, intval($row['project_id']));
    }

    // One message - a second flashAlert() would replace the first
    $message = array_filter([
        $added ? "<strong>$added</strong> ticket" . ($added === 1 ? '' : 's') . " added to <strong>" . escapeHtml($row['project_name']) . "</strong>" : '',
        $skipped ? 'Skipped ' . implode(', ', $skipped) : '',
        $not_found ? 'No ticket numbered ' . implode(', ', $not_found) : '',
    ]);
    flashAlert($message ? implode('. ', $message) : 'Nothing to add - those tickets are on the project already.', $added ? 'success' : 'error');

    redirect();

}

/** Tickets off a project - from its page, ticked. Only ones on this project, and the agent may reach. */
if (isset($_POST['remove_tickets_from_project'])) {

    validateCSRFToken();

    $row = projectForRequest($_POST['project_id'] ?? 0, 2);
    $project_id = intval($row['project_id']);

    $removed = 0;
    foreach (ticketsForBulk(2) as $ticket) {
        if (intval($ticket['ticket_project_id']) !== $project_id) {
            continue;
        }
        projectRemoveTicket($ticket);
        $removed++;
    }

    if ($removed) {
        logAudit("Project", "Edit", "$session_name took $removed ticket" . ($removed === 1 ? '' : 's') . " out of project " . escapeSql(projectReference($row) . ' - ' . $row['project_name']), intval($row['project_client_id']), $project_id);
    }

    flashAlert("<strong>$removed</strong> ticket" . ($removed === 1 ? '' : 's') . " taken out of <strong>" . escapeHtml($row['project_name']) . "</strong>", 'error');

    redirect();

}

/** A project template's tickets onto a project that already exists. */
if (isset($_POST['apply_project_template'])) {

    validateCSRFToken();

    $row = projectForRequest($_POST['project_id'] ?? 0, 2);

    if (!projectIsOpen($row)) {
        flashAlert("Project <strong>" . escapeHtml(projectReference($row)) . "</strong> isn't open.", 'error');
        redirect();
    }

    $project_template_id = intval($_POST['project_template_id'] ?? 0);
    if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM project_templates WHERE project_template_id = $project_template_id AND project_template_archived_at IS NULL"))) {
        flashAlert('Pick a project template.', 'error');
        redirect();
    }

    $raised = projectApplyTemplate($row, $project_template_id, intval($_POST['assign_to_manager'] ?? 0) ? $row['project_manager'] : 0);

    logAudit("Project", "Edit", "$session_name added $raised ticket" . ($raised === 1 ? '' : 's') . " from a template to project " . escapeSql(projectReference($row) . ' - ' . $row['project_name']), intval($row['project_client_id']), intval($row['project_id']));

    flashAlert("<strong>$raised</strong> ticket" . ($raised === 1 ? '' : 's') . " added from the template");

    redirect();

}

/*
 * ---------- bulk, from the projects list ----------
 */

if (isset($_POST['bulk_complete_projects'])) {

    validateCSRFToken();

    $done = 0;
    $refused = [];
    foreach (projectsForBulk(2) as $row) {
        if (!projectIsOpen($row)) {
            continue;
        }
        $why_not = projectComplete($row);
        if ($why_not === '') {
            $done++;
        } else {
            $refused[] = escapeHtml(projectReference($row));
        }
    }

    flashAlert("<strong>$done</strong> project" . ($done === 1 ? '' : 's') . " completed"
        . ($refused ? ". Still open tickets on " . implode(', ', $refused) . " - left as they were" : ''), $refused && !$done ? 'error' : 'success');

    redirect();

}

if (isset($_POST['bulk_reopen_projects'])) {

    validateCSRFToken();

    $done = 0;
    foreach (projectsForBulk(2) as $row) {
        if (empty($row['project_completed_at']) || !empty($row['project_archived_at'])) {
            continue;
        }
        projectReopen($row);
        $done++;
    }

    flashAlert("<strong>$done</strong> project" . ($done === 1 ? '' : 's') . " reopened");

    redirect();

}

if (isset($_POST['bulk_archive_projects'])) {

    validateCSRFToken();

    $done = 0;
    foreach (projectsForBulk(2) as $row) {
        if (!empty($row['project_archived_at'])) {
            continue;
        }
        projectArchive($row);
        $done++;
    }

    flashAlert("<strong>$done</strong> project" . ($done === 1 ? '' : 's') . " archived", 'error');

    redirect();

}

if (isset($_POST['bulk_restore_projects'])) {

    validateCSRFToken();

    $done = 0;
    foreach (projectsForBulk(2) as $row) {
        if (empty($row['project_archived_at'])) {
            continue;
        }
        projectRestore($row);
        $done++;
    }

    flashAlert("<strong>$done</strong> project" . ($done === 1 ? '' : 's') . " restored");

    redirect();

}

if (isset($_POST['bulk_delete_projects'])) {

    validateCSRFToken();

    $done = 0;
    foreach (projectsForBulk(3) as $row) {
        projectDelete($row);
        $done++;
    }

    flashAlert("<strong>$done</strong> project" . ($done === 1 ? '' : 's') . " deleted - their tickets stay", 'error');

    redirect();

}

if (isset($_POST['bulk_edit_project_manager'])) {

    validateCSRFToken();

    $manager_id = ticketAgentId($_POST['project_manager'] ?? 0);
    $manager_name = $manager_id ? escapeSql((string) getFieldById('users', $manager_id, 'user_name')) : 'No One';

    $done = 0;
    foreach (projectsForBulk(2) as $row) {
        if (!projectIsOpen($row) || intval($row['project_manager']) === $manager_id) {
            continue;
        }
        mysqli_query($mysqli, "UPDATE projects SET project_manager = $manager_id WHERE project_id = " . intval($row['project_id']));
        projectNotifyManager($row, $manager_id);
        logAudit("Project", "Edit", "$session_name set the manager of project " . escapeSql(projectReference($row) . ' - ' . $row['project_name']) . " to $manager_name", intval($row['project_client_id']), intval($row['project_id']));
        $done++;
    }

    flashAlert("Manager of <strong>$done</strong> project" . ($done === 1 ? '' : 's') . " set to <strong>" . escapeHtml(stripslashes($manager_name)) . "</strong>");

    redirect();

}

if (isset($_POST['bulk_edit_project_due'])) {

    validateCSRFToken();

    $due = projectDateSql($_POST['due_date'] ?? '');

    $done = 0;
    $skipped = 0;
    foreach (projectsForBulk(2) as $row) {
        if (!projectIsOpen($row)) {
            continue;
        }
        // Not before it starts
        if ($due !== 'NULL' && !empty($row['project_start']) && trim($due, "'") < $row['project_start']) {
            $skipped++;
            continue;
        }
        mysqli_query($mysqli, "UPDATE projects SET project_due = $due WHERE project_id = " . intval($row['project_id']));
        logAudit("Project", "Edit", "$session_name set the due date of project " . escapeSql(projectReference($row) . ' - ' . $row['project_name']) . " to " . ($due === 'NULL' ? 'none' : trim($due, "'")), intval($row['project_client_id']), intval($row['project_id']));
        $done++;
    }

    flashAlert("Due date of <strong>$done</strong> project" . ($done === 1 ? '' : 's') . ($due === 'NULL' ? ' cleared' : ' set to <strong>' . escapeHtml(date('M j, Y', strtotime(trim($due, "'")))) . '</strong>')
        . ($skipped ? ", <strong>$skipped</strong> skipped - they start after it" : ''));

    redirect();

}

/*
 * Export - the projects list's filters and view, as CSV or PDF, with each
 * project's progress and hours.
 */
if (isExportRequest('export_projects')) {

    validateCSRFToken();

    enforceUserPermission('module_support');

    $format = resolveExportFormat($_POST['export_projects']);
    $filter_summary = [];

    $client_id = intval($_POST['client_id'] ?? 0);
    $where = [clientScopeSql('project_client_id')];
    if ($client_id) {
        enforceClientAccess();
        $where[] = "AND project_client_id = $client_id";
        $file_name_prepend = getFieldById('clients', $client_id, 'client_name') . '-';
        $filter_summary['Client'] = getFieldById('clients', $client_id, 'client_name');
    } else {
        $file_name_prepend = "$session_company_name-";
        $client_filter = (string) ($_POST['client'] ?? '');
        if ($client_filter === 'internal') {
            $where[] = 'AND project_client_id = 0';
            $filter_summary['Client'] = 'Internal';
        } elseif (intval($client_filter)) {
            $where[] = 'AND project_client_id = ' . intval($client_filter);
            $filter_summary['Client'] = getFieldById('clients', intval($client_filter), 'client_name');
        }
    }

    $views = projectViews();
    $show = (string) ($_POST['show'] ?? '');
    if (!isset($views[$show])) {
        $show = '';
    }
    if ($views[$show]['where'] !== '') {
        $where[] = 'AND ' . $views[$show]['where'];
    }
    $filter_summary['Showing'] = $views[$show]['label'];

    $manager = (string) ($_POST['manager'] ?? '');
    if ($manager === 'none') {
        $where[] = 'AND project_manager = 0';
        $filter_summary['Manager'] = 'None';
    } elseif (intval($manager)) {
        $where[] = 'AND project_manager = ' . intval($manager);
        $filter_summary['Manager'] = getFieldById('users', intval($manager), 'user_name');
    }

    $q = escapeSql($_POST['q'] ?? '');
    if ($q !== '') {
        $filter_summary['Search'] = $_POST['q'];
    }

    $sql = mysqli_query($mysqli, "SELECT projects.*, client_name, user_name, contact_name
        FROM projects
        LEFT JOIN clients ON client_id = project_client_id
        LEFT JOIN users ON user_id = project_manager
        LEFT JOIN contacts ON contact_id = project_contact_id AND project_contact_id > 0
        WHERE (CONCAT(project_prefix, project_number) LIKE '%$q%' OR project_name LIKE '%$q%' OR project_description LIKE '%$q%'
            OR client_name LIKE '%$q%' OR user_name LIKE '%$q%' OR contact_name LIKE '%$q%')
        " . implode(' ', $where) . "
        ORDER BY project_client_id = 0, client_name ASC, project_due IS NULL, project_due ASC, project_name ASC");

    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['project_id'])] = $row;
    }
    $num_rows = count($rows);

    if ($num_rows > 0) {

        guardExportPdfRowCount($format, $num_rows);

        $stats = projectStats(array_keys($rows));
        $export = beginExport('projects', $format, $file_name_prepend . 'Projects', 'Projects', summarizeExportFilters($filter_summary));

        foreach ($rows as $project_id => $row) {
            $s = $stats[$project_id];
            $row['project_reference'] = projectReference($row);
            $row['client_name'] = intval($row['project_client_id']) ? $row['client_name'] : 'Internal';
            $row['project_state'] = ucfirst(projectState($row));
            $row['project_completed'] = $row['project_completed_at'] ? substr($row['project_completed_at'], 0, 10) : '';
            $row['tickets_done'] = $s['done'];
            $row['tickets_total'] = $s['tickets'];
            $row['tasks_done'] = $s['tasks_done'];
            $row['tasks_total'] = $s['tasks'];
            $row['hours_logged'] = round($s['seconds'] / 3600, 2);
            $row['hours_unbilled'] = round($s['unbilled_seconds'] / 3600, 2);
            addExportRow($export, $row);
        }

        finishExport($export);

        logAudit("Project", "Export", "$session_name exported $num_rows project(s) to a " . strtoupper($format) . " file", $client_id);

        exit;
    }

    flashAlert('No projects match - nothing to export.', 'error');

    redirect();

}
