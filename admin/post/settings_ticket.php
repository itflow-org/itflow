<?php

/*
 * Admin > Settings > Tickets (admin/settings_ticket.php):
 * edit_ticket_settings saves the numbering, email to ticket, who hears of a
 * new ticket and whether AI triages it, the timer and billing, closing and the
 * tickets page. A next
 * number already in use, an email address that isn't one or a closing time
 * out of range comes back to the page saying so - nothing is saved. Billable
 * by default is only on the page with accounting on, so is kept as it is
 * without it. A setting not sent at all keeps what it is; a switch not sent
 * is off.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_ticket_settings'])) {

    validateCSRFToken();

    $before = settingsCurrent(array_merge(['config_ticket_prefix', 'config_ticket_next_number', 'config_ticket_email_parse', 'config_ticket_email_parse_unknown_senders',
        'config_ticket_new_ticket_notification_email', 'config_ticket_timer_autostart', 'config_ticket_default_billable', 'config_ticket_autoclose_hours',
        'config_ticket_default_view', 'config_ticket_ordering', 'config_ticket_moving_columns'], aiTriageReady() ? ['config_ticket_ai_triage'] : []));

    // What was sent, and what is saved for anything that wasn't - a switch not sent is off
    $in = $_POST + array_map(function ($value) { return (string) $value; }, $before);

    [$prefix, $number, $errors] = settingsNumberingCheck('ticket', $in, $before);

    $after = [
        'config_ticket_prefix' => $prefix,
        'config_ticket_next_number' => $number,
        'config_ticket_new_ticket_notification_email' => trim((string) $in['config_ticket_new_ticket_notification_email']),
        'config_ticket_autoclose_hours' => intval($in['config_ticket_autoclose_hours']),
        'config_ticket_default_view' => intval($in['config_ticket_default_view']),
    ];
    foreach (['config_ticket_email_parse', 'config_ticket_email_parse_unknown_senders', 'config_ticket_timer_autostart', 'config_ticket_ordering', 'config_ticket_moving_columns'] as $switch) {
        $after[$switch] = empty($_POST[$switch]) ? 0 : 1;
    }
    if ($config_module_enable_accounting) {
        $after['config_ticket_default_billable'] = empty($_POST['config_ticket_default_billable']) ? 0 : 1;
    }

    if ($problem = settingsEmailProblem($after['config_ticket_new_ticket_notification_email'])) {
        $errors['config_ticket_new_ticket_notification_email'] = $problem;
    }
    $hours = $after['config_ticket_autoclose_hours'];
    if ($hours !== intval($before['config_ticket_autoclose_hours']) && ($hours < 24 || $hours > 8760)) {
        $errors['config_ticket_autoclose_hours'] = 'From 24 hours (a day) to 8760 (a year).';
    }
    if (!in_array($after['config_ticket_default_view'], [0, 1, 2], true)) {
        $errors['config_ticket_default_view'] = 'Pick how it opens from the list.';
    }
    // AI triage - once the database has it
    if (aiTriageReady()) {
        $after['config_ticket_ai_triage'] = intval($in['config_ticket_ai_triage']);
        if (!isset(aiTriageModes()[$after['config_ticket_ai_triage']])) {
            $errors['config_ticket_ai_triage'] = 'Pick how new tickets are triaged from the list.';
        }
    }
    if ($errors) {
        settingsRefuse('ticket', $errors);
    }

    settingsSave('ticket', $before, $after);

    redirect();

}
