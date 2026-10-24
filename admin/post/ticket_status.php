<?php

/*
 * ITFlow - Admin > Ticket Statuses: add and change a status, put them in
 * order (dragged, or up and down), switch one off or on, and delete one of
 * your own - moving its tickets first. The five ITFlow has keep their names,
 * stay on, and have fixed SLA and client-reply behaviour, whatever a request
 * says. The work is in functions/ticket_status.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_ticket_status']) || isset($_POST['edit_ticket_status'])) {

    validateCSRFToken();

    $row = isset($_POST['edit_ticket_status']) ? ticketStatusRow($_POST['ticket_status_id'] ?? 0) : null;
    if (isset($_POST['edit_ticket_status']) && !$row) {
        flashAlert("That status is gone - it may have been deleted.", 'error');
        redirect();
    }

    $fields = ticketStatusFromInput($_POST, $row);
    $problem = ticketStatusProblem($fields, $row ? $row['ticket_status_id'] : 0);
    if ($problem !== '') {
        flashAlert(escapeHtml($problem) . ' The status was not saved.', 'error');
        redirect();
    }

    if ($row) {
        $ticket_status_id = intval($row['ticket_status_id']);
        $changes = ticketStatusUpdate($row, $fields);
        if ($changes) {
            logAudit("Ticket Status", "Edit", "$session_name changed ticket status " . escapeSql($row['ticket_status_name']) . ": " . escapeSql(implode('; ', $changes)), 0, $ticket_status_id);
        }
        flashAlert("Status <strong>" . escapeHtml($fields['name']) . "</strong> saved");
    } else {
        $ticket_status_id = ticketStatusCreate($fields);
        logAudit("Ticket Status", "Create", "$session_name added ticket status " . escapeSql($fields['name']) . ($fields['pauses_sla'] ? ' (pauses the resolution clock)' : ''), 0, $ticket_status_id);
        flashAlert("Status <strong>" . escapeHtml($fields['name']) . "</strong> added - at the end of the order");
    }

    redirect();

}

if (isset($_GET['move_ticket_status'])) {

    validateCSRFToken();

    // One place up or down among the ones in use
    $row = ticketStatusRow($_GET['move_ticket_status']);
    if ($row && intval($row['ticket_status_active']) === 1) {
        $ids = array_keys(array_filter(ticketStatusesAll(true), function ($status) {
            return intval($status['ticket_status_active']) === 1;
        }));
        $at = array_search(intval($row['ticket_status_id']), $ids, true);
        $to = ($_GET['direction'] ?? '') === 'up' ? $at - 1 : $at + 1;
        if ($at !== false && isset($ids[$to])) {
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            ticketStatusReorder($ids);
            logAudit("Ticket Status", "Edit", "$session_name moved ticket status " . escapeSql($row['ticket_status_name']) . " " . (($_GET['direction'] ?? '') === 'up' ? 'up' : 'down'), 0, intval($row['ticket_status_id']));
        }
    }

    redirect();

}

if (isset($_POST['reorder_ticket_statuses'])) {

    // Dragged into order on Admin > Ticket Statuses - answers with JSON, as the page saves it in the background
    header('Content-Type: application/json');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        echo json_encode(['ok' => false, 'error' => 'The page is out of date - reload it']);
        exit;
    }

    // The ones dragged, a page of the ones in use, in their place among the others in use - offset is how many come before them
    $current = array_keys(array_filter(ticketStatusesAll(true), function ($status) {
        return intval($status['ticket_status_active']) === 1;
    }));
    $ids = array_values(array_intersect(array_unique(array_map('intval', (array) ($_POST['ticket_status_ids'] ?? []))), $current));
    $new_order = array_values(array_diff($current, $ids));
    array_splice($new_order, min(max(0, intval($_POST['offset'] ?? 0)), count($new_order)), 0, $ids);
    if (ticketStatusReorder($new_order)) {
        logAudit("Ticket Status", "Edit", "$session_name reordered the ticket statuses");
    }

    echo json_encode(['ok' => true]);
    exit;

}

if (isset($_GET['switch_on_ticket_status'])) {

    validateCSRFToken();

    $row = ticketStatusRow($_GET['switch_on_ticket_status']);
    if ($row && intval($row['ticket_status_active']) !== 1) {
        ticketStatusSetActive($row, true);
        logAudit("Ticket Status", "Edit", "$session_name switched on ticket status " . escapeSql($row['ticket_status_name']), 0, intval($row['ticket_status_id']));
        flashAlert("Status <strong>" . escapeHtml($row['ticket_status_name']) . "</strong> switched on - it can be picked again");
    }

    redirect();

}

if (isset($_POST['switch_off_ticket_status'])) {

    validateCSRFToken();

    $row = ticketStatusRow($_POST['ticket_status_id'] ?? 0);
    if (!$row) {
        flashAlert("That status is gone - it may have been deleted.", 'error');
        redirect();
    }
    $name = $row['ticket_status_name'];
    $move_to = intval($_POST['move_to'] ?? 0);
    if ($move_to && !isset(ticketStatusMoveTargets($row['ticket_status_id'])[$move_to])) {
        flashAlert("Its tickets can't be moved there. Nothing was changed.", 'error');
        redirect();
    }

    [$ok, $moved] = ticketStatusSetActive($row, false, $move_to);
    if (!$ok) {
        flashAlert("ITFlow's own statuses can't be switched off.", 'error');
        redirect();
    }

    $to_name = $moved ? ticketStatusesAll()[$move_to]['ticket_status_name'] : '';
    logAudit("Ticket Status", "Edit", "$session_name switched off ticket status " . escapeSql($name) . ($moved ? ", moving $moved tickets to " . escapeSql($to_name) : ''), 0, intval($row['ticket_status_id']));
    flashAlert("Status <strong>" . escapeHtml($name) . "</strong> switched off" . ($moved ? " - $moved " . ($moved === 1 ? 'ticket' : 'tickets') . " moved to <strong>" . escapeHtml($to_name) . "</strong>" : ''));

    redirect();

}

if (isset($_POST['delete_ticket_status'])) {

    validateCSRFToken();

    $row = ticketStatusRow($_POST['ticket_status_id'] ?? 0);
    if (!$row) {
        flashAlert("That status is gone - it may have been deleted.", 'error');
        redirect();
    }
    if (ticketStatusIsBuiltIn($row['ticket_status_id'])) {
        flashAlert("ITFlow's own statuses can't be deleted.", 'error');
        redirect();
    }

    $name = $row['ticket_status_name'];
    $move_to = intval($_POST['move_to'] ?? 0);
    [$ok, $moved, $canned] = ticketStatusDelete($row, $move_to);
    if (!$ok) {
        flashAlert("Pick where its tickets go. Nothing was deleted.", 'error');
        redirect();
    }

    $to_name = $moved ? (ticketStatusesAll()[$move_to]['ticket_status_name'] ?? '') : '';
    logAudit("Ticket Status", "Delete", "$session_name deleted ticket status " . escapeSql($name) . ($moved ? ", moving $moved tickets to " . escapeSql($to_name) : '') . ($canned ? "; $canned canned responses no longer set a status" : ''), 0, intval($row['ticket_status_id']));
    flashAlert("Status <strong>" . escapeHtml($name) . "</strong> deleted" . ($moved ? " - $moved " . ($moved === 1 ? 'ticket' : 'tickets') . " moved to <strong>" . escapeHtml($to_name) . "</strong>" : ''), 'error');

    redirect();

}
