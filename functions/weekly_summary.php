<?php

/*
 * ITFlow - the weekly summary email to the owners: how last week went beside
 * the week before - tickets opened and resolved, SLAs missed, ratings, time
 * logged, new clients, and (with invoicing on) what was invoiced and paid -
 * and what needs a look now: open tickets nobody has, overdue invoices,
 * domains and certificates running out. Set in Admin > Settings >
 * Notifications (database 4.0.7); sent by the nightly job on the day picked,
 * to every admin or the addresses given, once. Send me one now sends it to the
 * admin asking, for the seven days to yesterday.
 */

/** The days it can go on - 1 Monday to 7 Sunday, as date('N'). */
const WEEKLY_SUMMARY_DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

/** Whether the database has the settings (4.0.7). */
function weeklySummaryReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            // The nightly job, where it isn't defined
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '4.0.7', '>=');
    }
    return $ready;
}

/** The settings - ['on', 'day', 'to', 'sent_at']. */
function weeklySummarySettings() {
    global $mysqli;
    if (!weeklySummaryReady()) {
        return ['on' => 0, 'day' => 1, 'to' => '', 'sent_at' => null];
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_weekly_summary, config_weekly_summary_day, config_weekly_summary_to, config_weekly_summary_sent_at FROM settings WHERE company_id = 1"));
    return [
        'on' => intval($row['config_weekly_summary'] ?? 0),
        'day' => isset(WEEKLY_SUMMARY_DAYS[intval($row['config_weekly_summary_day'] ?? 1)]) ? intval($row['config_weekly_summary_day']) : 1,
        'to' => (string) ($row['config_weekly_summary_to'] ?? ''),
        'sent_at' => $row['config_weekly_summary_sent_at'] ?? null,
    ];
}

/** The addresses typed in - valid ones, each once. */
function weeklySummaryAddresses($text) {
    $out = [];
    foreach (preg_split('/[\s,;]+/', (string) $text) as $address) {
        $address = trim($address);
        if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $out[strtolower($address)] = $address;
        }
    }
    return array_values($out);
}

/** Who it goes to - [['email', 'name'], ...]: the addresses set, else every active admin. */
function weeklySummaryRecipients($to) {
    global $mysqli;
    $addresses = weeklySummaryAddresses($to);
    if ($addresses) {
        return array_map(function ($email) {
            return ['email' => $email, 'name' => ''];
        }, $addresses);
    }
    $out = [];
    $sql = mysqli_query($mysqli, "SELECT user_name, user_email FROM users JOIN user_roles ON role_id = user_role_id
        WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL AND role_is_admin = 1 ORDER BY user_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (filter_var($row['user_email'], FILTER_VALIDATE_EMAIL)) {
            $out[] = ['email' => $row['user_email'], 'name' => $row['user_name']];
        }
    }
    return $out;
}

/*
 * The numbers for the days from $from up to (not including) $to, both 'Y-m-d':
 * opened, resolved, sla_missed, good, bad, seconds, billable_seconds,
 * new_clients - and with invoicing on, invoiced and paid.
 */
function weeklySummaryNumbers($from, $to) {
    global $mysqli, $config_module_enable_ticketing, $config_module_enable_accounting;
    $from = escapeSql($from);
    $to = escapeSql($to);
    $one = function ($sql) use ($mysqli) {
        $row = mysqli_fetch_row(mysqli_query($mysqli, $sql));
        return $row ? $row[0] : 0;
    };
    $in = function ($column) use ($from, $to) {
        return "$column >= '$from' AND $column < '$to'";
    };
    $n = ['new_clients' => intval($one("SELECT COUNT(*) FROM clients WHERE " . $in('client_created_at')))];
    if ($config_module_enable_ticketing) {
        $n['opened'] = intval($one("SELECT COUNT(*) FROM tickets WHERE ticket_archived_at IS NULL AND " . $in('ticket_created_at')));
        $n['resolved'] = intval($one("SELECT COUNT(*) FROM tickets WHERE ticket_archived_at IS NULL AND " . $in('ticket_resolved_at')));
        $n['sla_missed'] = intval($one("SELECT COUNT(*) FROM tickets WHERE ticket_archived_at IS NULL AND ((ticket_response_sla_met = 0 AND " . $in('ticket_response_due_at') . ")
            OR (ticket_resolution_sla_met = 0 AND " . $in('ticket_resolution_due_at') . "))"));
        $n['good'] = intval($one("SELECT COUNT(*) FROM tickets WHERE ticket_feedback = 'Good' AND " . $in('ticket_closed_at')));
        $n['bad'] = intval($one("SELECT COUNT(*) FROM tickets WHERE ticket_feedback = 'Bad' AND " . $in('ticket_closed_at')));
        $time = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(TIME_TO_SEC(ticket_reply_time_worked)), 0),
                COALESCE(SUM(IF(ticket_billable = 1, TIME_TO_SEC(ticket_reply_time_worked), 0)), 0)
            FROM ticket_replies JOIN tickets ON ticket_id = ticket_reply_ticket_id
            WHERE ticket_reply_archived_at IS NULL AND ticket_reply_type != 'Client' AND " . $in('ticket_reply_created_at')));
        $n['seconds'] = intval($time[0] ?? 0);
        $n['billable_seconds'] = intval($time[1] ?? 0);
    }
    if ($config_module_enable_accounting) {
        $n['invoiced'] = floatval($one("SELECT COALESCE(SUM(invoice_amount), 0) FROM invoices WHERE invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable') AND " . $in('invoice_date')));
        $n['paid'] = floatval($one("SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE " . $in('payment_date')));
    }
    return $n;
}

/** What needs a look now - [text, ...]: open tickets nobody has, overdue invoices, domains and certificates running out in 30 days. */
function weeklySummaryNeeds() {
    global $mysqli, $config_module_enable_ticketing, $config_module_enable_accounting;
    $needs = [];
    if ($config_module_enable_ticketing) {
        $unassigned = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM tickets WHERE ticket_archived_at IS NULL AND ticket_assigned_to = 0 AND " . TICKET_OPEN_SQL))[0]);
        if ($unassigned) {
            $needs[] = "$unassigned open ticket" . ($unassigned === 1 ? '' : 's') . ' with nobody on ' . ($unassigned === 1 ? 'it' : 'them');
        }
    }
    if ($config_module_enable_accounting) {
        $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*), COALESCE(SUM(balance), 0) FROM (SELECT invoice_amount - COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0) AS balance
            FROM invoices WHERE invoice_status IN ('Sent', 'Viewed', 'Partial') AND invoice_due < CURDATE()) AS overdue WHERE balance > 0"));
        if (intval($row[0])) {
            $needs[] = intval($row[0]) . ' invoice' . (intval($row[0]) === 1 ? '' : 's') . ' overdue, ' . billingMoney($row[1]) . ' owed';
        }
    }
    $domains = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM domains WHERE domain_archived_at IS NULL AND domain_expire IS NOT NULL AND domain_expire BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY"))[0]);
    if ($domains) {
        $needs[] = "$domains domain" . ($domains === 1 ? '' : 's') . ' running out in the next 30 days';
    }
    $certificates = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM certificates WHERE certificate_archived_at IS NULL AND certificate_expire IS NOT NULL AND certificate_expire BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY"))[0]);
    if ($certificates) {
        $needs[] = "$certificates certificate" . ($certificates === 1 ? '' : 's') . ' running out in the next 30 days';
    }
    return $needs;
}

/** The clients with the most tickets opened from $from to $to - [[name, count], ...], five at most. */
function weeklySummaryBusiest($from, $to) {
    global $mysqli, $config_module_enable_ticketing;
    if (!$config_module_enable_ticketing) {
        return [];
    }
    $out = [];
    $sql = mysqli_query($mysqli, "SELECT client_name, COUNT(*) AS n FROM tickets JOIN clients ON client_id = ticket_client_id
        WHERE ticket_archived_at IS NULL AND ticket_created_at >= '" . escapeSql($from) . "' AND ticket_created_at < '" . escapeSql($to) . "'
        GROUP BY client_id, client_name ORDER BY n DESC, client_name ASC LIMIT 5");
    while ($row = mysqli_fetch_assoc($sql)) {
        $out[] = [$row['client_name'], intval($row['n'])];
    }
    return $out;
}

/*
 * The email for the seven days before $until ('Y-m-d', not included), beside the seven
 * before those - its subject and emailHtml() parts.
 */
function weeklySummaryEmail($until) {
    global $config_base_url;
    $to = date('Y-m-d', strtotime($until));
    $from = date('Y-m-d', strtotime("$to -7 days"));
    $before = date('Y-m-d', strtotime("$to -14 days"));
    $now = weeklySummaryNumbers($from, $to);
    $then = weeklySummaryNumbers($before, $from);
    $span = date('M j', strtotime($from)) . ' - ' . date('M j', strtotime("$to -1 day"));

    $hours = function ($seconds) {
        return number_format($seconds / 3600, 1) . ' h';
    };
    $rows = [];
    $add = function ($label, $key, $format = null) use (&$rows, $now, $then) {
        if (!array_key_exists($key, $now)) {
            return;
        }
        $show = $format ?: function ($v) {
            return number_format($v);
        };
        $rows[] = [$label, $show($now[$key]), $show($then[$key])];
    };
    $add('Tickets opened', 'opened');
    $add('Tickets resolved', 'resolved');
    $add('SLAs missed', 'sla_missed');
    $add('Rated good', 'good');
    $add('Rated not good', 'bad');
    $add('Time logged', 'seconds', $hours);
    $add('Billable time', 'billable_seconds', $hours);
    $add('New clients', 'new_clients');
    $add('Invoiced', 'invoiced', 'billingMoney');
    $add('Payments received', 'paid', 'billingMoney');

    $summary = [];
    if (isset($now['opened'])) {
        $summary['Tickets'] = $now['opened'] . ' opened, ' . $now['resolved'] . ' resolved';
        $summary['Time logged'] = $hours($now['seconds']);
    }
    if (isset($now['paid'])) {
        $summary['Payments received'] = billingMoney($now['paid']);
    }
    $busiest = weeklySummaryBusiest($from, $to);
    if ($busiest) {
        $summary['Busiest clients'] = implode(', ', array_map(function ($b) {
            return $b[0] . ' (' . $b[1] . ')';
        }, $busiest));
    }
    $needs = weeklySummaryNeeds();

    return [
        'subject' => "Your week: $span",
        'email' => [
            'kind' => 'Weekly summary',
            'tone' => 'primary',
            'preview' => implode(' · ', array_map(function ($k, $v) {
                return "$k: $v";
            }, array_keys($summary), $summary)),
            'title' => "Your week, $span",
            'intro' => ['How the last seven days went, beside the seven before.'],
            'summary' => $summary,
            'lines' => ['head' => ['', 'This week', 'The week before'], 'align' => ['left', 'right', 'right'], 'rows' => $rows],
            'callout' => $needs ? 'Needs a look: ' . implode('; ', $needs) . '.' : '',
            'button' => ['label' => 'Open ITFlow', 'url' => "https://$config_base_url/agent/dashboard.php"],
            'footnote' => 'The weekly summary is set in Admin > Settings > Notifications.',
        ],
    ];
}

/** Send it to $recipients for the seven days before $until. How many it was queued for. */
function weeklySummarySend(array $recipients, $until) {
    $mail = weeklySummaryEmail($until);
    return count(emailQueue($recipients, $mail['subject'], $mail['email']));
}

/*
 * From the nightly job: on the day picked, once, the week to yesterday to whoever it is
 * set to go to. How many it went to, or 0.
 */
function weeklySummaryRun() {
    global $mysqli;
    $settings = weeklySummarySettings();
    $today = date('Y-m-d');
    if (!$settings['on'] || intval(date('N')) !== $settings['day'] || $settings['sent_at'] === $today) {
        return 0;
    }
    mysqli_query($mysqli, "UPDATE settings SET config_weekly_summary_sent_at = '$today' WHERE company_id = 1");
    $recipients = weeklySummaryRecipients($settings['to']);
    return $recipients ? weeklySummarySend($recipients, $today) : 0;
}
