<?php

/*
 * Client overview data - agent/client_overview.php.
 *
 * Everything the overview shows is worked out here, one function per card,
 * all for a single client. Where a figure links to a list, it is counted the
 * way that list filters, so "3 unassigned" opens a list of 3 tickets.
 *
 * The page has already run enforceClientAccess() for $client_id (via
 * inc_all_client.php), so nothing here re-checks client scope.
 */

/** Which parts of the overview this agent can see. */
function clientOverviewAccess() {
    global $config_module_enable_ticketing, $config_module_enable_accounting, $config_module_enable_itdoc;

    $accounting = intval($config_module_enable_accounting) === 1;

    return [
        'tickets' => intval($config_module_enable_ticketing) === 1 && lookupUserPermission('module_support') >= 1,
        'docs' => intval($config_module_enable_itdoc) === 1 && lookupUserPermission('module_support') >= 1,
        'credentials' => intval($config_module_enable_itdoc) === 1 && lookupUserPermission('module_credential') >= 1,
        'sales' => $accounting && lookupUserPermission('module_sales') >= 1,
        'financial' => $accounting && lookupUserPermission('module_financial') >= 1,
        'edit' => lookupUserPermission('module_client') >= 2,
        'add_ticket' => intval($config_module_enable_ticketing) === 1 && lookupUserPermission('module_support') >= 2,
    ];
}

/** Open tickets that are past SLA - the rule tickets.php?sla=breached uses. */
function clientOverviewSlaBreachedSql() {
    return "(ticket_sla_id > 0 AND (ticket_response_sla_alert_stage = 2 OR ticket_resolution_sla_alert_stage = 2
        OR ticket_response_sla_met = 0 OR ticket_resolution_sla_met = 0))";
}

/**
 * Things to act on for this client. Only items with a count come back, so a
 * client in good shape shows nothing here.
 */
function clientOverviewAttention($client_id, $access, $renewals) {
    $items = [];
    $open = "ticket_client_id = $client_id AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL";

    if ($access['tickets']) {
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $open AND " . clientOverviewSlaBreachedSql()),
            'label' => 'Past SLA', 'icon' => 'stopwatch', 'tone' => 'danger',
            'url' => "/agent/tickets.php?client_id=$client_id&sla=breached",
        ];
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $open AND ticket_assigned_to = 0"),
            'label' => 'Unassigned tickets', 'icon' => 'user-slash', 'tone' => 'warning',
            'url' => "/agent/tickets.php?client_id=$client_id&assigned=unassigned",
        ];
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $open AND ticket_updated_at < NOW() - INTERVAL 7 DAY"),
            'label' => 'No update in 7 days', 'icon' => 'hourglass-half', 'tone' => 'warning',
            'url' => "/agent/tickets.php?client_id=$client_id&sort=ticket_updated_at&order=ASC",
        ];
        // The same rule as projects.php?show=overdue
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(project_id) FROM projects WHERE project_client_id = $client_id AND " . projectViews()['overdue']['where']),
            'label' => 'Overdue projects', 'icon' => 'project-diagram', 'tone' => 'danger',
            'url' => "/agent/projects.php?client_id=$client_id&show=overdue",
        ];
    }

    if ($access['sales']) {
        $overdue_where = "invoice_client_id = $client_id AND invoice_status IN ('Sent', 'Viewed', 'Partial') AND invoice_due < CURDATE()";
        $overdue_count = dashboardCount("SELECT COUNT(invoice_id) FROM invoices WHERE $overdue_where");
        $overdue_amount = dashboardSum("SELECT SUM(invoice_amount) FROM invoices WHERE $overdue_where")
            - dashboardSum("SELECT SUM(payment_amount) FROM payments JOIN invoices ON payment_invoice_id = invoice_id WHERE $overdue_where");
        $items[] = [
            'count' => $overdue_count,
            'label' => 'Overdue invoices', 'icon' => 'file-invoice-dollar', 'tone' => 'danger',
            'detail' => $overdue_count ? dashboardMoney($overdue_amount) . ' outstanding' : null,
            'url' => "/agent/invoices.php?client_id=$client_id&status=Overdue",
        ];
        if ($access['tickets']) {
            $items[] = [
                'count' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE ticket_client_id = $client_id
                    AND ticket_resolved_at IS NOT NULL AND ticket_billable = 1 AND ticket_invoice_id = 0"),
                'label' => 'Resolved, not invoiced', 'icon' => 'receipt', 'tone' => 'info',
                'url' => "/agent/tickets.php?client_id=$client_id&billing=unbilled&state=closed",
            ];
        }
    }

    if ($access['docs']) {
        // More devices and users on a license than seats bought - a true-up to bill, or seats to free
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(software_id) FROM software WHERE software_client_id = $client_id AND software_archived_at IS NULL
                AND " . softwareStatusFilters()['over']['sql']),
            'label' => 'Licenses over seats', 'icon' => 'cube', 'tone' => 'danger',
            'url' => "/agent/software.php?client_id=$client_id&status=over",
        ];
        // What the last check of a host found wrong - the same rule as certificates.php?show=problems
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(certificate_id) FROM certificates WHERE certificate_client_id = $client_id AND certificate_archived_at IS NULL
                AND " . certificateProblemSql()),
            'label' => 'Certificate problems', 'icon' => 'lock', 'tone' => 'warning',
            'detail' => 'Name mismatch, untrusted, expired or unreachable',
            'url' => "/agent/certificates.php?client_id=$client_id&show=problems",
        ];
    }

    if ($access['credentials']) {
        // Logins nobody has changed in a while - the same rule as credentials.php?show=due
        $items[] = [
            'count' => dashboardCount("SELECT COUNT(credential_id) FROM credentials WHERE credential_client_id = $client_id AND credential_archived_at IS NULL
                AND (credential_password_changed_at IS NULL OR credential_password_changed_at < NOW() - INTERVAL " . CREDENTIAL_ROTATE_DAYS . " DAY)"),
            'label' => 'Passwords due for a change', 'icon' => 'history', 'tone' => 'warning',
            'detail' => 'Not changed in ' . CREDENTIAL_ROTATE_DAYS . ' days',
            'url' => "/agent/credentials.php?client_id=$client_id&show=due&sort=credential_password_changed_at&order=ASC",
        ];
    }

    if ($access['docs'] && $renewals) {
        $expired = count(array_filter($renewals['items'], function ($r) { return $r['days'] < 0; }));
        $soon = count($renewals['items']) - $expired;
        $items[] = ['count' => $expired, 'label' => 'Expired', 'icon' => 'calendar-times', 'tone' => 'danger',
            'detail' => 'Warranties, licenses, domains, certificates', 'url' => '#clientRenewals'];
        $items[] = ['count' => $soon, 'label' => 'Expiring soon', 'icon' => 'calendar-alt', 'tone' => 'warning',
            'detail' => 'Next ' . $renewals['days'] . ' days', 'url' => '#clientRenewals'];
    }

    return array_values(array_filter($items, function ($item) {
        return $item['count'] > 0;
    }));
}

/** Open tickets, the ones most in need of attention first: past SLA, then priority, then waiting longest. */
function clientOverviewOpenTickets($client_id, $limit = 8) {
    global $mysqli;

    $where = "ticket_client_id = $client_id AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL";
    $sql = mysqli_query($mysqli, "
        SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_created_at, ticket_updated_at,
            ticket_status_name, ticket_status_color, user_name, contact_name,
            " . clientOverviewSlaBreachedSql() . " AS sla_breached
        FROM tickets
        LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
        LEFT JOIN users ON ticket_assigned_to = user_id
        LEFT JOIN contacts ON ticket_contact_id = contact_id
        WHERE $where
        ORDER BY sla_breached DESC, FIELD(ticket_priority, 'Urgent', 'High', 'Medium', 'Low'), COALESCE(ticket_updated_at, ticket_created_at) ASC
        LIMIT " . intval($limit));

    $tickets = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $tickets[] = $row;
    }

    return [
        'tickets' => $tickets,
        'total' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $where"),
        'resolved_30' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE ticket_client_id = $client_id AND ticket_resolved_at >= NOW() - INTERVAL 30 DAY"),
        'opened_30' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE ticket_client_id = $client_id AND ticket_created_at >= NOW() - INTERVAL 30 DAY"),
    ];
}

/** The contacts worth having to hand: primary first, then anyone flagged important, billing or technical. */
function clientOverviewContacts($client_id, $limit = 6) {
    global $mysqli;

    $sql = mysqli_query($mysqli, "
        SELECT contact_id, contact_name, contact_title, contact_email, contact_phone, contact_phone_country_code, contact_extension,
            contact_mobile, contact_mobile_country_code, contact_photo, contact_primary, contact_important, contact_billing, contact_technical
        FROM contacts
        WHERE contact_client_id = $client_id AND contact_archived_at IS NULL
            AND (contact_primary = 1 OR contact_important = 1 OR contact_billing = 1 OR contact_technical = 1)
        ORDER BY contact_primary DESC, contact_important DESC, contact_name ASC
        LIMIT " . intval($limit));

    $contacts = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $contacts[] = $row;
    }
    return $contacts;
}

/** Starred assets, licenses and credentials. */
function clientOverviewFavorites($client_id, $access) {
    global $mysqli;

    $favorites = ['assets' => [], 'licenses' => [], 'credentials' => []];

    if ($access['docs']) {
        $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, interface_ip
            FROM assets
            LEFT JOIN asset_interfaces ON interface_asset_id = asset_id AND interface_primary = 1
            WHERE asset_client_id = $client_id AND asset_favorite = 1 AND asset_archived_at IS NULL
            ORDER BY asset_type, asset_name");
        while ($row = mysqli_fetch_assoc($sql)) {
            $favorites['assets'][] = $row;
        }

        $sql = mysqli_query($mysqli, "SELECT software_id, software_name, software_type, software_license_type, software_key, software_expire
            FROM software
            WHERE software_client_id = $client_id AND software_favorite = 1 AND software_archived_at IS NULL
            ORDER BY software_name");
        while ($row = mysqli_fetch_assoc($sql)) {
            $favorites['licenses'][] = $row;
        }
    }

    if ($access['credentials']) {
        $sql = mysqli_query($mysqli, "SELECT credential_id, credential_name, credential_description, credential_username, credential_otp_secret, credential_uri, credential_password_changed_at
            FROM credentials
            WHERE credential_client_id = $client_id AND credential_favorite = 1 AND credential_archived_at IS NULL
            ORDER BY credential_name");
        while ($row = mysqli_fetch_assoc($sql)) {
            $favorites['credentials'][] = $row;
        }
    }

    return $favorites;
}

/**
 * The client's open projects, the ones needing a look first (overdue, then
 * soonest due), with their numbers - [rows, stats, total].
 */
function clientOverviewProjects($client_id, $limit = 5) {
    global $mysqli;
    $client_id = intval($client_id);
    $where = "project_client_id = $client_id AND " . PROJECT_OPEN_SQL;
    $sql = mysqli_query($mysqli, "SELECT projects.*, user_name, user_avatar FROM projects LEFT JOIN users ON user_id = project_manager
        WHERE $where ORDER BY project_due IS NULL, project_due ASC, project_id DESC LIMIT " . intval($limit));
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['project_id'])] = $row;
    }
    return ['projects' => $rows, 'stats' => projectStats(array_keys($rows)), 'total' => dashboardCount("SELECT COUNT(project_id) FROM projects WHERE $where")];
}

/**
 * The next $days days for this client: scheduled tickets, calendar events
 * (repeating ones expanded the way the calendar does it), recurring
 * tickets due to be raised, and projects starting or due. Soonest first.
 */
function clientOverviewUpcoming($client_id, $access, $days = 14) {
    global $mysqli;

    $from = date('Y-m-d 00:00:00');
    $to = date('Y-m-d 23:59:59', strtotime('+' . ($days - 1) . ' days'));
    $items = [];

    if ($access['tickets']) {
        $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_schedule, ticket_onsite, user_name
            FROM tickets LEFT JOIN users ON ticket_assigned_to = user_id
            WHERE ticket_client_id = $client_id AND ticket_resolved_at IS NULL AND ticket_closed_at IS NULL
            AND ticket_schedule BETWEEN '$from' AND '$to'");
        while ($row = mysqli_fetch_assoc($sql)) {
            $items[] = [
                'when' => $row['ticket_schedule'], 'all_day' => false, 'kind' => 'ticket',
                'title' => $row['ticket_subject'], 'ref' => $row['ticket_prefix'] . $row['ticket_number'],
                'detail' => trim(($row['ticket_onsite'] ? 'Onsite' : '') . ($row['user_name'] ? ($row['ticket_onsite'] ? ', ' : '') . $row['user_name'] : '')),
                'url' => "/agent/ticket.php?client_id=$client_id&ticket_id=" . intval($row['ticket_id']),
            ];
        }

        // Projects starting or due - all day
        $sql = mysqli_query($mysqli, "SELECT project_id, project_prefix, project_number, project_name, project_start, project_due
            FROM projects
            WHERE project_client_id = $client_id AND " . PROJECT_OPEN_SQL . "
            AND (project_due BETWEEN '" . substr($from, 0, 10) . "' AND '" . substr($to, 0, 10) . "' OR project_start BETWEEN '" . substr($from, 0, 10) . "' AND '" . substr($to, 0, 10) . "')");
        while ($row = mysqli_fetch_assoc($sql)) {
            foreach (['project_start' => 'Project starts', 'project_due' => 'Project due'] as $column => $what) {
                if ($row[$column] && $row[$column] >= substr($from, 0, 10) && $row[$column] <= substr($to, 0, 10)) {
                    $items[] = [
                        'when' => $row[$column] . ' 00:00:00', 'all_day' => true, 'kind' => 'project',
                        'title' => $row['project_name'], 'ref' => $row['project_prefix'] . $row['project_number'],
                        'detail' => $what,
                        'url' => '/agent/' . projectUrl($row['project_id'], $client_id),
                    ];
                }
            }
        }

        $sql = mysqli_query($mysqli, "SELECT recurring_ticket_id, recurring_ticket_subject, recurring_ticket_next_run, recurring_ticket_frequency
            FROM recurring_tickets
            WHERE recurring_ticket_client_id = $client_id AND recurring_ticket_next_run BETWEEN '" . substr($from, 0, 10) . "' AND '" . substr($to, 0, 10) . "'");
        while ($row = mysqli_fetch_assoc($sql)) {
            $items[] = [
                'when' => $row['recurring_ticket_next_run'] . ' 00:00:00', 'all_day' => true, 'kind' => 'recurring',
                'title' => $row['recurring_ticket_subject'], 'ref' => '',
                'detail' => 'Recurring ticket, ' . strtolower($row['recurring_ticket_frequency']),
                'url' => "/agent/recurring_tickets.php?client_id=$client_id",
            ];
        }
    }

    $sql = mysqli_query($mysqli, "SELECT event_id, event_title, event_start, event_end, event_all_day, event_repeat, event_location
        FROM calendar_events
        WHERE event_client_id = $client_id AND event_archived_at IS NULL AND event_start <= '$to'
        AND ((event_repeat IS NOT NULL AND event_repeat != '') OR event_start >= '$from' OR event_end >= '$from')");
    while ($row = mysqli_fetch_assoc($sql)) {
        foreach (expandRecurringEvent($row, $from, $to) as $occurrence) {
            $start = $occurrence['start'];
            $end = $occurrence['end'] ?: $start;
            if ($start > $to || max($start, $end) < $from) {
                continue;
            }
            $items[] = [
                'when' => max($start, $from), 'all_day' => !empty($row['event_all_day']) || $start < $from, 'kind' => 'event',
                'title' => $row['event_title'], 'ref' => '', 'detail' => (string) $row['event_location'],
                'url' => "/agent/calendar.php?client_id=$client_id",
            ];
        }
    }

    usort($items, function ($a, $b) {
        return strcmp(substr($a['when'], 0, 10) . ($a['all_day'] ? '0' : '1') . substr($a['when'], 11),
            substr($b['when'], 0, 10) . ($b['all_day'] ? '0' : '1') . substr($b['when'], 11));
    });

    return $items;
}

/**
 * Renewals: domains, certificates, licenses and asset warranties that have
 * expired or expire in the next $days days, plus assets reaching seven years
 * since install (the old overview's retirement rule). Expiring soonest first,
 * then the expired ones, most recent first.
 */
function clientOverviewRenewals($client_id, $days = 45) {
    global $mysqli;

    $window = "CURRENT_DATE + INTERVAL $days DAY";
    $sources = [
        ['domain', 'globe', "SELECT domain_id AS id, domain_name AS name, domain_expire AS d FROM domains
            WHERE domain_client_id = $client_id AND domain_archived_at IS NULL AND domain_expire IS NOT NULL AND domain_expire <= $window",
            'Domain', "/agent/domains.php?client_id=$client_id&open="],
        ['certificate', 'lock', "SELECT certificate_id AS id, certificate_name AS name, certificate_expire AS d FROM certificates
            WHERE certificate_client_id = $client_id AND certificate_archived_at IS NULL AND certificate_expire IS NOT NULL AND certificate_expire <= $window",
            'Certificate', "/agent/certificates.php?client_id=$client_id&open="],
        ['software', 'cube', "SELECT software_id AS id, software_name AS name, software_expire AS d FROM software
            WHERE software_client_id = $client_id AND software_archived_at IS NULL AND software_expire IS NOT NULL AND software_expire <= $window",
            'License', "/agent/license.php?client_id=$client_id&software_id="],
        ['warranty', 'shield-alt', "SELECT asset_id AS id, asset_name AS name, asset_warranty_expire AS d FROM assets
            WHERE asset_client_id = $client_id AND asset_archived_at IS NULL AND asset_warranty_expire IS NOT NULL AND asset_warranty_expire <= $window",
            'Warranty', "/agent/asset.php?client_id=$client_id&asset_id="],
        ['retire', 'recycle', "SELECT asset_id AS id, asset_name AS name, asset_install_date + INTERVAL 7 YEAR AS d FROM assets
            WHERE asset_client_id = $client_id AND asset_archived_at IS NULL AND asset_install_date IS NOT NULL AND asset_install_date + INTERVAL 7 YEAR <= $window",
            '7 years old', "/agent/asset.php?client_id=$client_id&asset_id="],
    ];

    $items = [];
    $today = new DateTime('today');
    foreach ($sources as [$kind, $icon, $sql, $label, $url]) {
        $result = mysqli_query($mysqli, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $date = new DateTime($row['d']);
            $items[] = [
                'kind' => $kind, 'icon' => $icon, 'label' => $label,
                'name' => $row['name'], 'date' => $row['d'],
                'days' => (int) $today->diff($date)->format('%r%a'),
                'url' => in_array($kind, ['domain', 'certificate', 'warranty', 'retire', 'software'], true) ? $url . intval($row['id']) : $url . rawurlencode($row['name']),
            ];
        }
    }

    usort($items, function ($a, $b) {
        $a_soon = $a['days'] >= 0;
        $b_soon = $b['days'] >= 0;
        if ($a_soon !== $b_soon) {
            return $a_soon ? -1 : 1;
        }
        return $a_soon ? $a['days'] <=> $b['days'] : $b['days'] <=> $a['days'];
    });

    return ['items' => $items, 'days' => $days];
}

/** Assets by kind - the same groups as the assets page's type filter - and warranty state. */
function clientOverviewAssets($client_id) {
    global $mysqli;

    $groups = [
        'workstation' => ['label' => 'Workstations', 'icon' => 'desktop', 'count' => 0],
        'server' => ['label' => 'Servers', 'icon' => 'server', 'count' => 0],
        'virtual' => ['label' => 'Virtual machines', 'icon' => 'cloud', 'count' => 0],
        'network' => ['label' => 'Network', 'icon' => 'network-wired', 'count' => 0],
        'other' => ['label' => 'Other', 'icon' => 'cube', 'count' => 0],
    ];

    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COUNT(asset_id) AS total,
            COALESCE(SUM(asset_type IN ('Desktop', 'Laptop')), 0) AS workstation,
            COALESCE(SUM(asset_type = 'Server'), 0) AS server,
            COALESCE(SUM(asset_type = 'Virtual Machine'), 0) AS virtual,
            COALESCE(SUM(asset_type IN ('Firewall/Router', 'Switch', 'Access Point')), 0) AS network,
            COALESCE(SUM(asset_warranty_expire IS NOT NULL AND asset_warranty_expire < CURRENT_DATE), 0) AS warranty_expired,
            COALESCE(SUM(asset_warranty_expire IS NOT NULL AND asset_warranty_expire >= CURRENT_DATE), 0) AS warranty_active
        FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL"));

    $total = intval($row['total']);
    $grouped = 0;
    foreach (['workstation', 'server', 'virtual', 'network'] as $key) {
        $groups[$key]['count'] = intval($row[$key]);
        $grouped += intval($row[$key]);
    }
    $groups['other']['count'] = $total - $grouped;

    return [
        'total' => $total,
        'groups' => $groups,
        'warranty_expired' => intval($row['warranty_expired']),
        'warranty_active' => intval($row['warranty_active']),
        'warranty_unknown' => $total - intval($row['warranty_expired']) - intval($row['warranty_active']),
    ];
}

/** Active share links for this client's items. */
function clientOverviewSharedItems($client_id) {
    global $mysqli;

    $sql = mysqli_query($mysqli, "SELECT item_id, item_type, item_related_id, item_recipient, item_views, item_view_limit, item_expire_at,
            COALESCE(credential_name, document_name, file_name) AS item_name, file_ext, credential_uri, credential_description
        FROM shared_items
        LEFT JOIN credentials ON item_type = 'Credential' AND credential_id = item_related_id AND credential_client_id = $client_id
        LEFT JOIN documents ON item_type = 'Document' AND document_id = item_related_id AND document_client_id = $client_id
        LEFT JOIN files ON item_type = 'File' AND file_id = item_related_id AND file_client_id = $client_id
        WHERE item_client_id = $client_id AND item_active = 1
        -- Still openable: not expired, views not used up (functions/credential.php credentialShares)
        AND item_expire_at > NOW() AND (COALESCE(item_view_limit, 0) = 0 OR item_views < item_view_limit)
        ORDER BY item_created_at DESC
        LIMIT 10");

    $items = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $items[] = $row;
    }
    return $items;
}

/**
 * The latest changes for this client from the audit log. Views (opening a
 * credential, a page) are left out - they would push every change off the
 * card - and stay in the full audit log.
 */
function clientOverviewActivity($client_id, $limit = 6) {
    global $mysqli;

    $sql = mysqli_query($mysqli, "SELECT log_created_at, log_description, user_name FROM logs
        LEFT JOIN users ON log_user_id = user_id
        WHERE log_client_id = $client_id AND log_action != 'View'
        ORDER BY log_id DESC
        LIMIT " . intval($limit));

    $items = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $items[] = $row;
    }
    return $items;
}

/**
 * The money figures. Balance is invoiced minus paid, all time - the same as
 * the clients list and the old client header. Paid this year is cash in
 * (invoice payments plus client revenues), compared with the same days last
 * year. Unbilled is resolved billable tickets with no invoice, and the time
 * logged on them.
 */
function clientOverviewMoney($client_id, $balance, $recurring_monthly) {
    $year_start = date('Y-01-01');
    $today = date('Y-m-d');
    $last_year_start = (intval(date('Y')) - 1) . '-01-01';
    $last_year_today = dashboardShiftMonths($today, -12);

    $paid = function ($from, $to) use ($client_id) {
        return dashboardSum("SELECT SUM(payment_amount) FROM payments JOIN invoices ON payment_invoice_id = invoice_id
                WHERE invoice_client_id = $client_id AND payment_date BETWEEN '$from' AND '$to'")
            + dashboardSum("SELECT SUM(revenue_amount) FROM revenues WHERE revenue_client_id = $client_id
                AND revenue_category_id > 0 AND revenue_date BETWEEN '$from' AND '$to'");
    };
    $paid_ytd = $paid($year_start, $today);
    $paid_last_ytd = $paid($last_year_start, $last_year_today);

    $overdue_where = "invoice_client_id = $client_id AND invoice_status IN ('Sent', 'Viewed', 'Partial') AND invoice_due < CURDATE()";
    $overdue = dashboardSum("SELECT SUM(invoice_amount) FROM invoices WHERE $overdue_where")
        - dashboardSum("SELECT SUM(payment_amount) FROM payments JOIN invoices ON payment_invoice_id = invoice_id WHERE $overdue_where");

    $unbilled_where = "ticket_client_id = $client_id AND ticket_resolved_at IS NOT NULL AND ticket_billable = 1 AND ticket_invoice_id = 0";

    return [
        'balance' => $balance,
        'overdue' => max(0, $overdue),
        'paid_ytd' => $paid_ytd,
        'paid_change' => dashboardChange($paid_ytd, $paid_last_ytd),
        'recurring' => $recurring_monthly,
        'unbilled_tickets' => dashboardCount("SELECT COUNT(ticket_id) FROM tickets WHERE $unbilled_where"),
        'unbilled_seconds' => intval(dashboardSum("SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies
            JOIN tickets ON ticket_reply_ticket_id = ticket_id
            WHERE ticket_reply_archived_at IS NULL AND $unbilled_where")),
    ];
}

/** Unpaid invoices with what is still owed on each, oldest due first. */
function clientOverviewOpenInvoices($client_id, $limit = 6) {
    global $mysqli;

    $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_date, invoice_due, invoice_status, invoice_amount,
            invoice_amount - COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0) AS balance
        FROM invoices
        WHERE invoice_client_id = $client_id AND invoice_status IN ('Sent', 'Viewed', 'Partial')
        ORDER BY invoice_due ASC, invoice_id ASC
        LIMIT " . intval($limit));

    $invoices = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $invoices[] = $row;
    }

    return [
        'invoices' => $invoices,
        'total' => dashboardCount("SELECT COUNT(invoice_id) FROM invoices WHERE invoice_client_id = $client_id AND invoice_status IN ('Sent', 'Viewed', 'Partial')"),
    ];
}

/** Active recurring invoices, next to bill first. */
function clientOverviewRecurringInvoices($client_id) {
    global $mysqli;

    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope,
            recurring_invoice_frequency, recurring_invoice_next_date, recurring_invoice_amount
        FROM recurring_invoices
        WHERE recurring_invoice_client_id = $client_id AND recurring_invoice_status = 1 AND recurring_invoice_archived_at IS NULL
        ORDER BY recurring_invoice_next_date ASC");

    $items = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $items[] = $row;
    }
    return $items;
}

/** Invoiced (by invoice date, drafts and cancelled left out) and paid, per month for the last 12 months. */
function clientOverviewMonthly($client_id) {
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $months[] = substr(dashboardShiftMonths(date('Y-m-01'), -$i), 0, 7);
    }
    $from = $months[0] . '-01';
    $to = date('Y-m-t');

    $paid = array_map(function ($a, $b) {
        return round($a + $b, 2);
    },
        dashboardMonthly("SELECT DATE_FORMAT(payment_date, '%Y-%m') AS m, SUM(payment_amount) AS v FROM payments
            JOIN invoices ON payment_invoice_id = invoice_id
            WHERE invoice_client_id = $client_id AND payment_date BETWEEN '$from' AND '$to' GROUP BY m", $months),
        dashboardMonthly("SELECT DATE_FORMAT(revenue_date, '%Y-%m') AS m, SUM(revenue_amount) AS v FROM revenues
            WHERE revenue_client_id = $client_id AND revenue_category_id > 0 AND revenue_date BETWEEN '$from' AND '$to' GROUP BY m", $months)
    );

    return [
        'labels' => array_map(function ($m) {
            return date('M y', strtotime("$m-01"));
        }, $months),
        'invoiced' => array_map(function ($v) {
            return round($v, 2);
        }, dashboardMonthly("SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS m, SUM(invoice_amount) AS v FROM invoices
            WHERE invoice_client_id = $client_id AND invoice_status NOT IN ('Draft', 'Cancelled', 'Non-Billable')
            AND invoice_date BETWEEN '$from' AND '$to' GROUP BY m", $months)),
        'paid' => $paid,
    ];
}

