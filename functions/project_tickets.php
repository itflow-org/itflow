<?php

/*
 * ITFlow - project tickets kept apart from the service desk (Admin > Settings >
 * Projects, Project tickets). Switched on, a ticket that is part of a project:
 *   - is left off the Tickets list's usual views - it has a Projects view of
 *     its own - and so the client's tickets, the dashboard's unassigned count
 *     and the side menu's count, and the Dispatch queue. It stays in Mine,
 *     Mentioned me, Closed and All, in Dispatch's Workload, on the calendar and
 *     Dispatch's board, and a Project filter shows a project's tickets as before;
 *   - has no SLA - the project's dates are what count. A ticket that leaves its
 *     project, or every one when this is switched off, gets its SLA back counted
 *     from that moment (tickets.ticket_sla_from), so it isn't breached at once;
 *   - is drawn on the calendar and Dispatch in the project colour, with a dot in
 *     its agent's colour beside their name.
 * Only an open ticket's SLA changes: a resolved or closed one keeps what it has.
 * Off - the default - is how ITFlow has always been.
 *
 * Settings (database 3.8.9): config_project_tickets_apart, config_project_ticket_color.
 */

/** The colour project bookings are drawn in, when none is picked. */
const PROJECT_TICKET_COLOR = '#8e44ad';

/** Whether the 3.8.9 update has run. */
function projectTicketsReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.8.9', '>=');
    }
    return $ready;
}

/** The two settings, read once a request ($fresh after saving them): [apart, color]. */
function projectTicketsSettings($fresh = false) {
    global $mysqli;
    static $settings = null;
    if ($settings === null || $fresh) {
        $row = projectTicketsReady() ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_project_tickets_apart, config_project_ticket_color FROM settings WHERE company_id = 1")) : null;
        $settings = [
            'apart' => intval($row['config_project_tickets_apart'] ?? 0) === 1,
            'color' => calendarColor((string) ($row['config_project_ticket_color'] ?? ''), PROJECT_TICKET_COLOR),
        ];
    }
    return $settings;
}

/** Whether project tickets are kept apart. */
function projectTicketsApart() {
    return projectTicketsSettings()['apart'];
}

/** " AND <column> = 0" while they are - for a WHERE that should see the service desk's tickets only. */
function projectTicketsServiceSql($column = 'ticket_project_id') {
    return projectTicketsApart() ? " AND $column = 0" : '';
}

/*
 * The Tickets list's views ($shows in agent/tickets.php): while kept apart,
 * the usual ones leave project tickets out and a Projects view lists the open
 * ones - unless a project was picked in the filters, which shows its tickets
 * as before. Mine, Mentioned me, Closed and All keep them.
 */
function projectTicketsShows(array $shows, $base_sql, $project_filter) {
    if (!projectTicketsApart() || intval($project_filter)) {
        return $shows;
    }
    $view = ['label' => 'Projects', 'icon' => 'fa-project-diagram', 'where' => "$base_sql AND tickets.ticket_project_id > 0",
        'title' => 'Tickets that are part of a project - kept off the other views, with no SLA', 'divider' => true];
    $out = [];
    foreach ($shows as $key => $show) {
        if ($key === 'closed') {
            $out['projects'] = $view;
        }
        if (!in_array($key, ['mine', 'mentioned', 'closed', 'all'], true)) {
            $show['where'] = '(' . $show['where'] . ') AND tickets.ticket_project_id = 0';
        }
        $out[$key] = $show;
    }
    if (!isset($out['projects'])) {
        $out['projects'] = $view;
    }
    return $out;
}

/*
 * For applyTicketSla(), with the ticket's row (ticket_project_id, ticket_sla_id,
 * ticket_resolved_at, ticket_closed_at): whether it is to have no SLA - an open
 * ticket in a project, or a done one in a project that has none already.
 */
function projectTicketsSlaOff(array $row) {
    if (!projectTicketsApart() || intval($row['ticket_project_id'] ?? 0) === 0) {
        return false;
    }
    $open = empty($row['ticket_resolved_at']) && empty($row['ticket_closed_at']);
    return $open || intval($row['ticket_sla_id'] ?? 0) === 0;
}

/*
 * A ticket was in project $old_project_id and its project has just been set:
 * taken out of it while kept apart, an open one gets its SLA counted from now.
 * Before applyTicketSla() works its SLA out again.
 */
function projectTicketLeft($ticket_id, $old_project_id) {
    global $mysqli;
    if (projectTicketsReady() && projectTicketsApart() && intval($old_project_id)) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_sla_from = NOW(), ticket_updated_at = ticket_updated_at
            WHERE ticket_id = " . intval($ticket_id) . " AND ticket_project_id = 0 AND " . TICKET_OPEN_SQL);
    }
}

/* A ticket's project changed from $old_project_id: projectTicketLeft(), then its SLA worked out again. */
function projectTicketMoved($ticket_id, $old_project_id) {
    projectTicketLeft($ticket_id, $old_project_id);
    if (projectTicketsReady()) {
        applyTicketSla(intval($ticket_id));
    }
}

/*
 * After Keep project tickets apart was switched: on, the open project tickets
 * lose their SLA; off, they get it back counted from now. How many changed.
 */
function projectTicketsSwitched($apart) {
    global $mysqli;
    if (!projectTicketsReady()) {
        return 0;
    }
    projectTicketsSettings(true);
    if (!$apart) {
        mysqli_query($mysqli, "UPDATE tickets SET ticket_sla_from = NOW(), ticket_updated_at = ticket_updated_at WHERE ticket_project_id > 0 AND " . TICKET_OPEN_SQL);
    }
    $ids = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM tickets WHERE ticket_project_id > 0 AND ticket_archived_at IS NULL AND " . TICKET_OPEN_SQL);
    while ($row = mysqli_fetch_row($sql)) {
        $ids[] = intval($row[0]);
    }
    foreach ($ids as $ticket_id) {
        applyTicketSla($ticket_id);
    }
    return count($ids);
}

/*
 * Calendar items (the tickets source, so Dispatch too): while kept apart, a
 * project ticket's booking - lead's and helpers' - in the project colour, its
 * agent's colour kept for a dot (agent_color) and the project named in the detail.
 */
function projectTicketsCalendarItems(array $items) {
    global $mysqli;
    if (!projectTicketsApart() || !$items) {
        return $items;
    }
    $ids = [];
    foreach ($items as $item) {
        if (in_array($item['extendedProps']['kind'] ?? '', ['ticket', 'helper'], true)) {
            $ids[] = intval($item['extendedProps']['ticket_id'] ?? 0);
        }
    }
    $ids = array_filter(array_unique($ids));
    if (!$ids) {
        return $items;
    }
    $projects = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id, project_prefix, project_number, project_name FROM tickets
        JOIN projects ON project_id = ticket_project_id WHERE ticket_id IN (" . implode(',', $ids) . ")");
    while ($row = mysqli_fetch_assoc($sql)) {
        $projects[intval($row['ticket_id'])] = projectReference($row) . ' ' . $row['project_name'];
    }
    $color = projectTicketsSettings()['color'];
    foreach ($items as &$item) {
        $project = $projects[intval($item['extendedProps']['ticket_id'] ?? 0)] ?? null;
        if ($project === null || !in_array($item['extendedProps']['kind'] ?? '', ['ticket', 'helper'], true)) {
            continue;
        }
        $item['extendedProps']['agent_color'] = $item['color'] ?? '';
        $item['extendedProps']['project'] = $project;
        $item['extendedProps']['detail'] = trim($project . ' · ' . ($item['extendedProps']['detail'] ?? ''), ' ·');
        $item['color'] = $color;
    }
    unset($item);
    return $items;
}
