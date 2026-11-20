<?php

/*
 * Quick ticket - the + in the top bar (includes/top_nav.php), for a call or a
 * walk-up: a small window (agent/modals/ticket/ticket_quick.php) with the
 * client, the contact and what the issue is. Picking the contact shows their
 * tickets still open, so the same problem isn't raised twice, with "Work on
 * this one" to go to one of those instead.
 *
 * It posts New Ticket's own add_ticket (agent/post/ticket.php) with
 * quick_ticket set, so the ticket is numbered, gets its SLA, emails and
 * assignment as any other. What it starts with - who has it, its status,
 * priority, category, billing, the timer and the email to the contact - is in
 * Admin > Settings > Tickets, Quick tickets (config_quick_ticket_*, 3.7.6).
 *
 * The timer is the ticket page's own (agent/js/ticket_time_tracking.js), kept
 * in the browser. The window sends when it should count from - Make ticket
 * pressed, or the window opened, by the browser's clock - and the ticket
 * page's first load starts it from then (quickTicketTimerScript()).
 */

/** When a quick ticket's timer counts from. */
const QUICK_TICKET_TIMER = [
    'make' => 'From when Make ticket is pressed',
    'open' => 'From when the window was opened - the start of the call',
    'off' => 'Don\'t start it - as for any ticket',
];

/** Whether the 3.7.6 update has run - the Quick tickets settings. Until it has, the button shows with the built-in ones. */
function quickTicketReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.7.6', '>=');
    }
    return $ready;
}

/** The Quick tickets settings - as saved, or the built-in ones. */
function quickTicketSettings() {
    global $mysqli;
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }
    $row = [];
    if (quickTicketReady()) {
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_quick_ticket_on, config_quick_ticket_assign, config_quick_ticket_status, config_quick_ticket_priority,
            config_quick_ticket_category, config_quick_ticket_billable, config_quick_ticket_timer, config_quick_ticket_primary, config_quick_ticket_open_tickets,
            config_quick_ticket_email_client FROM settings WHERE company_id = 1")) ?: [];
    }
    $priority = (string) ($row['config_quick_ticket_priority'] ?? 'Medium');
    $timer = (string) ($row['config_quick_ticket_timer'] ?? 'make');
    $settings = [
        'on' => intval($row['config_quick_ticket_on'] ?? 1) === 1,
        // 'me' - the agent making it, 'nobody', or an agent's id
        'assign' => (string) ($row['config_quick_ticket_assign'] ?? 'me'),
        // 0 - New, or Open once someone has it; else one an agent can pick (ticketPickableStatuses())
        'status' => intval($row['config_quick_ticket_status'] ?? 0),
        'priority' => in_array($priority, ['Low', 'Medium', 'High', 'Urgent'], true) ? $priority : 'Medium',
        'category' => intval($row['config_quick_ticket_category'] ?? 0),
        // -1 - as Time and billing says a new ticket starts; else 1 or 0
        'billable' => in_array(intval($row['config_quick_ticket_billable'] ?? -1), [0, 1], true) ? intval($row['config_quick_ticket_billable']) : -1,
        'timer' => isset(QUICK_TICKET_TIMER[$timer]) ? $timer : 'make',
        'primary' => intval($row['config_quick_ticket_primary'] ?? 1) === 1,
        'open_tickets' => intval($row['config_quick_ticket_open_tickets'] ?? 1) === 1,
        'email_client' => intval($row['config_quick_ticket_email_client'] ?? 1) === 1,
    ];
    return $settings;
}

/** Whether the signed-in agent gets the button - ticketing on, Support edit (as New Ticket), and Quick tickets on. */
function quickTicketCan() {
    global $config_module_enable_ticketing, $session_user_id;
    return !empty($session_user_id) && !empty($config_module_enable_ticketing) && lookupUserPermission('module_support') >= 2 && quickTicketSettings()['on'];
}

/** Who a quick ticket goes to, from the setting - 0 for nobody. */
function quickTicketAssignee() {
    global $session_user_id;
    $assign = quickTicketSettings()['assign'];
    if ($assign === 'me') {
        return intval($session_user_id);
    }
    return $assign === 'nobody' ? 0 : ticketAgentId(intval($assign));
}

/** Whether a quick ticket starts billable - the setting, else what a new ticket starts as. */
function quickTicketBillable() {
    global $config_ticket_default_billable;
    $billable = quickTicketSettings()['billable'];
    return $billable === -1 ? intval($config_ticket_default_billable) === 1 : $billable === 1;
}

/** What the window's "More detail" box says, as a ticket's details keep it - HTML, its lines kept. */
function quickTicketDetailsHtml($text) {
    $text = trim(str_replace("\r\n", "\n", mb_substr((string) $text, 0, 20000)));
    return $text === '' ? '' : '<p>' . nl2br(escapeHtml($text), false) . '</p>';
}

/*
 * After add_ticket made a quick ticket: a line in its history, and when its
 * timer counts from - kept for the ticket page's first load. $start is the
 * browser's clock in milliseconds.
 */
function quickTicketAfterCreate($ticket_id, $start, $paused = '', $from_call = false) {
    global $session_name;
    $ticket_id = intval($ticket_id);
    logTicketHistory($ticket_id, "$session_name raised it with Quick ticket");
    $start = (string) $start;
    $paused = (string) $paused;
    // From the top bar's timer started before there was a ticket - its time carries on here,
    // running or paused, whatever the setting, and that timer is cleared (js/ticket_timers.js)
    if ($from_call) {
        if (preg_match('/^\d{12,14}$/', $start)) {
            $_SESSION['quick_ticket_timer'][$ticket_id] = ['start' => $start, 'call' => true];
        } elseif (preg_match('/^\d{1,7}$/', $paused)) {
            $_SESSION['quick_ticket_timer'][$ticket_id] = ['paused' => $paused, 'call' => true];
        }
        return;
    }
    if (quickTicketSettings()['timer'] !== 'off' && preg_match('/^\d{12,14}$/', $start)) {
        $_SESSION['quick_ticket_timer'][$ticket_id] = $start;
    }
}

/*
 * The ticket page's first load after a quick ticket - the timer started from
 * when the window said, before agent/js/ticket_time_tracking.js reads it.
 * Only a timer that isn't there yet. '' when there's none to start.
 */
function quickTicketTimerScript($ticket_id) {
    $ticket_id = intval($ticket_id);
    $timer = $_SESSION['quick_ticket_timer'][$ticket_id] ?? '';
    if ($timer === '' || $timer === []) {
        return '';
    }
    unset($_SESSION['quick_ticket_timer'][$ticket_id]);
    // A timer started before there was a ticket: running from its start, or paused at its seconds - then cleared
    $start = is_array($timer) ? (string) ($timer['start'] ?? '') : (string) $timer;
    $paused = is_array($timer) ? (string) ($timer['paused'] ?? '') : '';
    $call = is_array($timer) && !empty($timer['call']);
    return '<script>(function () { try { var id = ' . json_encode((string) $ticket_id) . ';'
        . ' if (!localStorage.getItem(id + "-startTime") && !localStorage.getItem(id + "-pausedTime")) {'
        . ($start !== ''
            ? ' localStorage.setItem(id + "-startTime", ' . json_encode($start) . '); localStorage.setItem("ticket-timer-running-" + id, "true"); }'
            : ' localStorage.setItem(id + "-pausedTime", ' . json_encode($paused) . '); localStorage.setItem("ticket-timer-running-" + id, "false"); }')
        . ($call ? ' localStorage.removeItem("call-startTime"); localStorage.removeItem("call-pausedTime");' : '')
        . ' } catch (e) {} })();</script>';
}

/** The agents a quick ticket can go to, for the setting - 'me', 'nobody' and each agent by id. */
function quickTicketAssignChoices() {
    global $mysqli;
    $choices = ['me' => 'The agent making it', 'nobody' => 'Nobody'];
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1" . serviceAccountsOutSql() . " AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $choices[(string) intval($row['user_id'])] = (string) $row['user_name'];
    }
    return $choices;
}

/** Whether a quick ticket starts billable, for the setting. */
const QUICK_TICKET_BILLABLE = [-1 => 'As a new ticket starts - Time and billing, above', 1 => 'Billable', 0 => 'Not billable'];

/** The statuses a quick ticket can start in, for the setting. */
function quickTicketStatusChoices() {
    $choices = [0 => 'New, or Open once someone has it'];
    foreach (ticketPickableStatuses() as $status_id => $status) {
        $choices[$status_id] = $status['ticket_status_name'];
    }
    return $choices;
}
