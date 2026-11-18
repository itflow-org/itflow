<?php

/*
 * Admin > Settings > Projects (admin/settings_project.php):
 * edit_project_settings saves the numbering - a next number already in use
 * comes back to the page saying so - and whether project tickets are kept
 * apart, with their calendar colour; switching that works out the open
 * project tickets' SLAs again.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_project_settings'])) {

    validateCSRFToken();

    $ticket_columns = projectTicketsReady() ? ['config_project_tickets_apart', 'config_project_ticket_color'] : [];
    $before = settingsCurrent(array_merge(['config_project_prefix', 'config_project_next_number'], $ticket_columns));

    [$prefix, $number, $errors] = settingsNumberingCheck('project', $_POST + array_map('strval', $before), $before);
    if ($errors) {
        settingsRefuse('project', $errors);
    }

    $after = ['config_project_prefix' => $prefix, 'config_project_next_number' => $number];
    // Project tickets kept apart, and their colour (functions/project_tickets.php) - a switch sends nothing when off
    if ($ticket_columns) {
        $after['config_project_tickets_apart'] = empty($_POST['config_project_tickets_apart']) ? '0' : '1';
        $color = calendarColor((string) ($_POST['config_project_ticket_color'] ?? ''), '');
        $after['config_project_ticket_color'] = $color === '' || strtolower($color) === PROJECT_TICKET_COLOR ? null : $color;
    }

    settingsSave('project', $before, $after);

    // Switched on, the open project tickets lose their SLA; off, they get it back counted from now
    if ($ticket_columns && $after['config_project_tickets_apart'] !== (string) intval($before['config_project_tickets_apart'])) {
        projectTicketsSwitched($after['config_project_tickets_apart'] === '1');
    }

    redirect();

}
