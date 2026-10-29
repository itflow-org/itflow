<?php

/*
 * Services - what a client actually runs on: email, backup, the line of
 * business app, the phones, the website, remote access. A service is the
 * map an agent wants when something breaks or someone asks what a change
 * will touch: the assets it runs on, the logins, domains and certificates it
 * needs, the people who own or use it, the vendors to call, the documents
 * that explain it, and the tickets raised about it.
 *
 * Importance is how bad an outage is. A review keeps the map honest - every
 * so many months someone checks it still matches what is out there, and the
 * next review falls due that many months later.
 *
 * Something it depends on that needs attention - a domain or certificate
 * expiring, a server or network device out of warranty or out for repair, a
 * linked record that has since been archived - shows on the service, the
 * list and the client overview rather than only on that record's own page.
 *
 * A service that is retired is archived: it drops off the lists and counts
 * and comes back when restored.
 */

/** How bad an outage is, most first. */
const SERVICE_IMPORTANCE = ['High', 'Medium', 'Low'];

/** "Review due" - within this many days. */
const SERVICE_REVIEW_SOON_DAYS = 30;

/** How often a service is reviewed, in months. */
const SERVICE_REVIEW_INTERVALS = [
    0 => 'Not on a schedule',
    1 => 'Every month',
    3 => 'Every 3 months',
    6 => 'Every 6 months',
    12 => 'Every year',
    24 => 'Every 2 years',
];

/** Categories the form suggests - anything else can be typed. */
const SERVICE_CATEGORY_SUGGESTIONS = ['Email', 'Backup', 'Network', 'Internet', 'Security', 'Web', 'Cloud', 'Phones', 'Line of business',
    'Identity', 'Remote access', 'File storage', 'Printing', 'Hosting', 'Monitoring'];

/** What a service can depend on, in the order the page lists them. */
const SERVICE_LINK_TYPES = ['asset', 'credential', 'domain', 'certificate', 'contact', 'vendor', 'document'];

/** Asset statuses that mean it is not doing its job. */
const SERVICE_ASSET_DOWN_STATUSES = ['Out for Repair', 'Lost', 'Stolen', 'Retired'];

/**
 * One person's device - a service on many of them (endpoint protection) does
 * not hinge on any one, so their warranties and repairs are not the service's
 * problem. Servers, network kit, printers and the rest are.
 */
const SERVICE_USER_DEVICE_TYPES = ['Laptop', 'Desktop', 'Mobile Phone', 'Tablet'];

/** May this agent do this with services? They are support documentation: 1 to see, 2 to change, 3 to delete. */
function serviceCan($level) {
    return lookupUserPermission('module_support') >= $level;
}

/** One service by id, with its client - raw, or null. */
function serviceRow($service_id) {
    global $mysqli;
    $service_id = intval($service_id);
    if (!$service_id) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT services.*, client_name, client_archived_at FROM services
        LEFT JOIN clients ON client_id = service_client_id
        WHERE service_id = $service_id LIMIT 1"));
    return $row ?: null;
}

/**
 * For post handlers: the service a request names, after the module and
 * client checks - or back with an error if it is gone. Sets the global $client_id.
 */
function serviceForRequest($service_id, $level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = serviceRow($service_id);
    if (!$row) {
        flashAlert('That service no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($row['service_client_id']);
    enforceClientAccess();
    return $row;
}

/**
 * For modals: the service a modal is opened for, after the same checks - or
 * the modal's error, and nothing more. $active_only refuses an archived one.
 * Sets the global $client_id.
 */
function serviceForModal($service_id, $level = 1, $active_only = false) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $row = serviceRow($service_id);
    if (!$row) {
        echo json_encode(['error' => 'That service no longer exists.']);
        exit;
    }
    $client_id = intval($row['service_client_id']);
    enforceClientAccess();
    if ($active_only && !empty($row['service_archived_at'])) {
        echo json_encode(['error' => escapeHtml($row['service_name']) . ' is archived - restore it first.']);
        exit;
    }
    return $row;
}

/** For post handlers: the services a bulk action names (service_ids[]) that this agent may act on - [id => row]. */
function servicesForBulk($level) {
    global $client_id;
    enforceUserPermission('module_support', $level);
    $rows = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['service_ids'] ?? []))) as $service_id) {
        $row = serviceRow($service_id);
        if (!$row) {
            continue;
        }
        $client_id = intval($row['service_client_id']);
        enforceClientAccess();
        $rows[$service_id] = $row;
    }
    return $rows;
}

/** Where the service page is. */
function serviceUrl($service_id, $client_id) {
    return 'service.php?client_id=' . intval($client_id) . '&service_id=' . intval($service_id);
}

/** Its icon and colour, from what its category says it is - an envelope for email, a shield for security. */
function serviceStyle($category) {
    $category = mb_strtolower(trim(html_entity_decode((string) $category, ENT_QUOTES, 'UTF-8')));
    $styles = [
        ['mail|exchange|smtp', 'fa-envelope', 'wireless'],
        ['backup|recovery|disaster|continuity', 'fa-history', 'virtual'],
        ['phone|voip|voice|telephon|pbx|fax|call', 'fa-phone', 'phone'],
        ['secur|antivirus|anti-virus|edr|xdr|firewall|mfa|soc\b|siem|filter', 'fa-shield-alt', 'firewall'],
        ['remote|vpn|rds|citrix|terminal', 'fa-plug', 'wireless'],
        ['network|internet|wan\b|lan\b|wifi|wi-fi|wireless|isp|circuit|broadband|fibre|fiber', 'fa-network-wired', 'network'],
        ['web|site|dns', 'fa-globe', 'location'],
        ['hosting|server|virtual|hypervisor|infrastructure|datacent', 'fa-server', 'server'],
        ['identity|directory|sso|entra|ldap|login|auth', 'fa-id-badge', 'virtual'],
        ['file|storage|share|nas\b|sharepoint|onedrive|drive|document', 'fa-folder-open', 'peripheral'],
        ['print|scan|copier', 'fa-print', 'peripheral'],
        ['monitor|rmm|alert|patch', 'fa-heartbeat', 'network'],
        ['cloud|saas|365|workspace|azure|aws|tenant', 'fa-cloud', 'workstation'],
        ['line of business|lob\b|erp|crm|accounting|payroll|app|database|sql|software|pos\b|point of sale|ehr|emr|practice', 'fa-briefcase', 'server'],
    ];
    foreach ($styles as [$pattern, $icon, $tone]) {
        if ($category !== '' && preg_match("/($pattern)/u", $category)) {
            return ['icon' => $icon, 'tone' => $tone];
        }
    }
    return ['icon' => 'fa-stream', 'tone' => 'other'];
}

/** A service's tile - its category's icon in the same rounded square as the other tiles. $size 'xs' | 'sm' | '' | 'lg'. */
function serviceTile($category, $size = '') {
    $style = serviceStyle($category);
    $size_class = in_array($size, ['xs', 'sm', 'lg'], true) ? " itflow-asset-icon-$size" . ($size === 'xs' ? ' itflow-vendor-tile-xs' : '') : '';
    return '<span class="itflow-asset-icon' . $size_class . ' itflow-asset-tone-' . $style['tone'] . '" aria-hidden="true"><i class="fas fa-fw ' . $style['icon'] . '"></i></span>';
}

/**
 * A service wherever a page mentions one: its tile and its name, opening
 * its page - or its quick look with 'quick' => true. 'archived' strikes it
 * through; 'class' goes on the link. Takes the name raw or already escaped.
 */
function serviceLink($service_id, $name, $client_id, $options = []) {
    $service_id = intval($service_id);
    $label = escapeHtml(html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8'));
    $archived = !empty($options['archived']);
    $class = trim('text-truncate ' . ($options['class'] ?? '') . ($archived ? ' text-decoration-line-through text-body-secondary' : ''));
    $title = $archived ? ' title="Archived service"' : '';
    if (!empty($options['quick'])) {
        $link = "<a class=\"ajax-modal $class\" href=\"#\" data-modal-size=\"lg\" data-modal-url=\"modals/service/service.php?id=$service_id\"$title>$label</a>";
    } else {
        $link = '<a class="' . $class . '" href="' . escapeHtml(serviceUrl($service_id, $client_id)) . "\"$title>$label</a>";
    }
    return '<span class="itflow-contact-ref">' . serviceTile($options['category'] ?? '', 'xs') . $link . '</span>';
}

/** A service in a picker (itflow_select.js) - its icon, and its category and importance underneath. */
function selectOptionService($row) {
    return selectOptionData(['icon' => 'fas ' . serviceStyle($row['service_category'] ?? '')['icon'],
        'description' => implode(' · ', array_filter([$row['service_category'] ?? '', ($row['service_importance'] ?? '') ? $row['service_importance'] . ' importance' : ''])),
        'keywords' => $row['service_description'] ?? '']);
}

/** Importance as a soft pill - red High, amber Medium, grey Low. $use_text says "High importance". */
function serviceImportancePill($importance, $use_text = false) {
    $importance = in_array($importance, SERVICE_IMPORTANCE, true) ? $importance : 'Medium';
    $styles = [
        'High' => ['danger', 'fa-thermometer-full', 'Down means the client cannot work'],
        'Medium' => ['warning', 'fa-thermometer-half', 'Down hurts, but there is a way round it'],
        'Low' => ['secondary', 'fa-thermometer-quarter', 'Down is an inconvenience'],
    ];
    [$tone, $icon, $title] = $styles[$importance];
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal" title="' . $title . '">'
        . '<i class="fas ' . $icon . ' me-1"></i>' . $importance . ($use_text ? ' importance' : '') . '</span>';
}

/** Whole days from today to a date: negative is past. */
function serviceDaysFromToday($date) {
    $today = new DateTime(date('Y-m-d'));
    $then = new DateTime(substr((string) $date, 0, 10));
    return intval($today->diff($then)->format('%r%a'));
}

/** The date a review is next due: $months after $from (today by default). '' for no schedule. */
function serviceNextReview($months, $from = null) {
    $months = intval($months);
    if ($months <= 0) {
        return '';
    }
    $date = new DateTime($from ? substr((string) $from, 0, 10) : date('Y-m-d'));
    // The 31st plus a month is the end of next month, not the start of the one after
    $day = intval($date->format('j'));
    $date->modify('first day of this month')->modify("+$months months");
    $date->setDate(intval($date->format('Y')), intval($date->format('n')), min($day, intval($date->format('t'))));
    return $date->format('Y-m-d');
}

/**
 * Where its review stands: ['state' => overdue | soon | scheduled | none,
 * 'tone', 'short' (for the list), 'text', 'date', 'days'].
 */
function serviceReview($row) {
    $due = $row['service_review_due'] ?? null;
    $reviewed = $row['service_reviewed_at'] ?? null;
    if (!$due || strpos((string) $due, '0000-00-00') === 0) {
        return ['state' => 'none', 'tone' => 'secondary', 'short' => 'Not scheduled', 'date' => '', 'days' => null, 'reviewed' => $reviewed,
            'text' => $reviewed ? 'Last reviewed ' . timeAgo($reviewed) . ' - no next review set' : 'No review scheduled'];
    }
    $days = serviceDaysFromToday($due);
    $date = date('M j, Y', strtotime($due));
    if ($days < 0) {
        return ['state' => 'overdue', 'tone' => 'danger', 'short' => 'Overdue', 'date' => $due, 'days' => $days, 'reviewed' => $reviewed,
            'text' => 'Review was due ' . $date . ' (' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' ago)'];
    }
    if ($days <= SERVICE_REVIEW_SOON_DAYS) {
        $when = $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in $days days");
        return ['state' => 'soon', 'tone' => 'warning', 'short' => $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : "$days days"), 'date' => $due, 'days' => $days, 'reviewed' => $reviewed,
            'text' => "Review due $when ($date)"];
    }
    return ['state' => 'scheduled', 'tone' => 'success', 'short' => date('M j, Y', strtotime($due)), 'date' => $due, 'days' => $days, 'reviewed' => $reviewed, 'text' => "Next review $date"];
}

/** The review as a soft pill. */
function serviceReviewPill($review, $use_text = false) {
    $icons = ['overdue' => 'far fa-calendar-times', 'soon' => 'fas fa-hourglass-half', 'scheduled' => 'far fa-calendar-check', 'none' => 'far fa-calendar'];
    $tone = $review['tone'];
    $title = $review['text'] . (!empty($review['reviewed']) && $review['state'] !== 'none' ? ' - last reviewed ' . date('M j, Y', strtotime($review['reviewed'])) : '');
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal" title="' . escapeHtml($title) . '">'
        . '<i class="' . $icons[$review['state']] . ' me-1"></i>' . escapeHtml($use_text ? $review['text'] : $review['short']) . '</span>';
}

/** The views along the top of the list, and what each one shows. On the services table. */
function serviceViews() {
    $active = 'services.service_archived_at IS NULL';
    return [
        '' => ['label' => 'Active', 'icon' => 'fa-stream', 'where' => $active, 'title' => 'Not archived'],
        'attention' => ['label' => 'Needs attention', 'icon' => 'fa-exclamation-triangle', 'where' => "$active AND " . serviceAttentionSql(),
            'title' => 'Something it depends on is expiring, out of warranty, out of action or archived', 'alarm' => 'danger'],
        'review' => ['label' => 'Review due', 'icon' => 'fa-clipboard-check', 'where' => "$active AND " . serviceReviewDueSql(),
            'title' => 'Due for review in the next ' . SERVICE_REVIEW_SOON_DAYS . ' days, or overdue', 'alarm' => 'warning'],
        'high' => ['label' => 'High importance', 'icon' => 'fa-thermometer-full', 'where' => "$active AND services.service_importance = 'High'", 'title' => 'Down means the client cannot work'],
        'unreviewed' => ['label' => 'No review set', 'icon' => 'fa-calendar', 'where' => "$active AND services.service_review_due IS NULL", 'title' => 'No next review date'],
        'starred' => ['label' => 'Starred', 'icon' => 'fa-star', 'where' => "$active AND services.service_favorite = 1"],
        'archived' => ['label' => 'Archived', 'icon' => 'fa-archive', 'where' => 'services.service_archived_at IS NOT NULL'],
        'all' => ['label' => 'All', 'where' => ''],
    ];
}

/** SQL: its review is due within SERVICE_REVIEW_SOON_DAYS, or overdue. */
function serviceReviewDueSql($alias = 'services') {
    return "($alias.service_review_due IS NOT NULL AND $alias.service_review_due <= CURDATE() + INTERVAL " . SERVICE_REVIEW_SOON_DAYS . " DAY)";
}

/**
 * SQL: something it depends on needs attention - the same things
 * serviceIssues() lists. On the services table as $alias.
 */
function serviceAttentionSql($alias = 'services') {
    $id = "$alias.service_id";
    $soon = DOMAIN_SOON_DAYS;
    $down = "'" . implode("', '", SERVICE_ASSET_DOWN_STATUSES) . "'";
    $user_devices = "'" . implode("', '", SERVICE_USER_DEVICE_TYPES) . "'";
    $archived = [];
    foreach (['credential', 'contact', 'vendor', 'document', 'domain', 'certificate'] as $type) {
        $relation = linkRelation('service', $type);
        $entity = LINK_ENTITIES[$type];
        $archived[] = "EXISTS (SELECT 1 FROM {$relation['table']} l JOIN {$entity['table']} t ON t.{$entity['id']} = l.{$entity['id']}
            WHERE l.service_id = $id AND t.{$entity['archived']} IS NOT NULL)";
    }
    return "(EXISTS (SELECT 1 FROM service_domains sd JOIN domains d ON d.domain_id = sd.domain_id
            WHERE sd.service_id = $id AND d.domain_archived_at IS NULL
            AND (d.domain_expire < CURDATE() OR (d.domain_auto_renew = 0 AND d.domain_expire <= CURDATE() + INTERVAL $soon DAY)))
        OR EXISTS (SELECT 1 FROM service_certificates sc JOIN certificates c ON c.certificate_id = sc.certificate_id
            WHERE sc.service_id = $id AND c.certificate_archived_at IS NULL
            AND (c.certificate_expire < CURDATE() OR " . certificateSoonSql('c') . " OR " . certificateProblemSql('c') . "))
        OR EXISTS (SELECT 1 FROM service_assets sa JOIN assets a ON a.asset_id = sa.asset_id
            WHERE sa.service_id = $id AND (a.asset_archived_at IS NOT NULL OR (a.asset_type NOT IN ($user_devices) AND (a.asset_status IN ($down)
                OR (a.asset_warranty_expire > '1000-01-01' AND a.asset_warranty_expire < CURDATE())))))
        OR " . implode("\n        OR ", $archived) . ")";
}

/** SQL for ticketRows(): the tickets raised about a service, and those on any of its assets. */
function serviceTicketsSql($service_id) {
    $service_id = intval($service_id);
    return "tickets.ticket_id IN (SELECT st.ticket_id FROM service_tickets st WHERE st.service_id = $service_id)
        OR tickets.ticket_asset_id IN (SELECT sa.asset_id FROM service_assets sa WHERE sa.service_id = $service_id)
        OR tickets.ticket_id IN (SELECT ta.ticket_id FROM ticket_assets ta JOIN service_assets sa ON sa.asset_id = ta.asset_id WHERE sa.service_id = $service_id)";
}

/**
 * How much hangs off each service, for the whole page at once:
 * [service_id => [asset, credential, domain, certificate, contact, vendor,
 * document => count of active ones, 'archived' => linked ones since archived,
 * 'open_tickets' => open tickets about it or its assets]]. Credentials only
 * count for those who may see them.
 */
function serviceCounts(array $service_ids) {
    global $mysqli;
    $service_ids = array_values(array_unique(array_filter(array_map('intval', $service_ids))));
    $blank = array_fill_keys(SERVICE_LINK_TYPES, 0) + ['archived' => 0, 'open_tickets' => 0];
    $counts = array_fill_keys($service_ids, $blank);
    if (!$service_ids) {
        return $counts;
    }
    $in = implode(',', $service_ids);
    $can_credentials = lookupUserPermission('module_credential') >= 1;
    foreach (SERVICE_LINK_TYPES as $type) {
        if ($type === 'credential' && !$can_credentials) {
            continue;
        }
        $relation = linkRelation('service', $type);
        $entity = LINK_ENTITIES[$type];
        $sql = mysqli_query($mysqli, "SELECT l.service_id, COALESCE(SUM(t.{$entity['archived']} IS NULL), 0), COALESCE(SUM(t.{$entity['archived']} IS NOT NULL), 0)
            FROM {$relation['table']} l JOIN {$entity['table']} t ON t.{$entity['id']} = l.{$entity['id']}
            WHERE l.service_id IN ($in) GROUP BY l.service_id");
        while ($row = mysqli_fetch_row($sql)) {
            $counts[intval($row[0])][$type] = intval($row[1]);
            $counts[intval($row[0])]['archived'] += intval($row[2]);
        }
    }
    $sql = mysqli_query($mysqli, "SELECT x.service_id, COUNT(DISTINCT x.ticket_id) FROM (
            SELECT st.service_id, st.ticket_id FROM service_tickets st WHERE st.service_id IN ($in)
            UNION SELECT sa.service_id, tk.ticket_id FROM service_assets sa JOIN tickets tk ON tk.ticket_asset_id = sa.asset_id WHERE sa.service_id IN ($in)
            UNION SELECT sa.service_id, ta.ticket_id FROM service_assets sa JOIN ticket_assets ta ON ta.asset_id = sa.asset_id WHERE sa.service_id IN ($in)
        ) x JOIN tickets ON tickets.ticket_id = x.ticket_id
        WHERE " . TICKET_OPEN_SQL . " AND tickets.ticket_archived_at IS NULL
        GROUP BY x.service_id");
    while ($row = mysqli_fetch_row($sql)) {
        $counts[intval($row[0])]['open_tickets'] = intval($row[1]);
    }
    return $counts;
}

/**
 * What needs attention in what each service depends on, worst first, for
 * the whole page at once: [service_id => [['tone' => danger | warning,
 * 'icon', 'text', 'type', 'id', 'name'], ...]]. The same things
 * serviceAttentionSql() finds.
 */
function serviceIssues(array $service_ids) {
    global $mysqli;
    $service_ids = array_values(array_unique(array_filter(array_map('intval', $service_ids))));
    $issues = array_fill_keys($service_ids, []);
    if (!$service_ids) {
        return $issues;
    }
    $in = implode(',', $service_ids);
    $soon = DOMAIN_SOON_DAYS;

    $sql = mysqli_query($mysqli, "SELECT sd.service_id, d.domain_id, d.domain_name, d.domain_expire, d.domain_auto_renew FROM service_domains sd
        JOIN domains d ON d.domain_id = sd.domain_id
        WHERE sd.service_id IN ($in) AND d.domain_archived_at IS NULL
        AND (d.domain_expire < CURDATE() OR (d.domain_auto_renew = 0 AND d.domain_expire <= CURDATE() + INTERVAL $soon DAY))
        ORDER BY d.domain_expire");
    while ($row = mysqli_fetch_assoc($sql)) {
        $expiry = domainExpiry($row['domain_expire'], $row['domain_auto_renew']);
        $issues[intval($row['service_id'])][] = ['tone' => $expiry['state'] === 'expired' ? 'danger' : 'warning', 'icon' => 'fa-globe',
            'text' => $row['domain_name'] . ': ' . lcfirst($expiry['text']), 'type' => 'domain', 'id' => intval($row['domain_id']), 'name' => $row['domain_name']];
    }

    $sql = mysqli_query($mysqli, "SELECT sc.service_id, c.certificate_id, c.certificate_name, c.certificate_domain, c.certificate_expire, c.certificate_issued_at,
            c.certificate_check_status, c.certificate_checked_at FROM service_certificates sc
        JOIN certificates c ON c.certificate_id = sc.certificate_id
        WHERE sc.service_id IN ($in) AND c.certificate_archived_at IS NULL
        AND (c.certificate_expire < CURDATE() OR " . certificateSoonSql('c') . " OR " . certificateProblemSql('c') . ")
        ORDER BY c.certificate_expire");
    while ($row = mysqli_fetch_assoc($sql)) {
        $expiry = certificateExpiry($row);
        $status = CERTIFICATE_CHECK_STATES[(string) $row['certificate_check_status']] ?? null;
        if ($expiry && $expiry['state'] !== 'active') {
            $text = lcfirst($expiry['text']);
            $tone = $expiry['state'] === 'expired' ? 'danger' : 'warning';
        } else {
            $text = $status ? lcfirst($status['label']) . ' - ' . lcfirst($status['text']) : 'the last check found a problem';
            $tone = $status && $status['tone'] === 'danger' ? 'danger' : 'warning';
        }
        $issues[intval($row['service_id'])][] = ['tone' => $tone, 'icon' => 'fa-lock', 'text' => $row['certificate_name'] . ': ' . $text,
            'type' => 'certificate', 'id' => intval($row['certificate_id']), 'name' => $row['certificate_name']];
    }

    $down = "'" . implode("', '", SERVICE_ASSET_DOWN_STATUSES) . "'";
    $user_devices = "'" . implode("', '", SERVICE_USER_DEVICE_TYPES) . "'";
    $sql = mysqli_query($mysqli, "SELECT sa.service_id, a.asset_id, a.asset_name, a.asset_status, a.asset_warranty_expire, a.asset_archived_at FROM service_assets sa
        JOIN assets a ON a.asset_id = sa.asset_id
        WHERE sa.service_id IN ($in) AND (a.asset_archived_at IS NOT NULL OR (a.asset_type NOT IN ($user_devices) AND (a.asset_status IN ($down)
            OR (a.asset_warranty_expire > '1000-01-01' AND a.asset_warranty_expire < CURDATE()))))
        ORDER BY a.asset_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $service_id = intval($row['service_id']);
        $base = ['type' => 'asset', 'id' => intval($row['asset_id']), 'name' => $row['asset_name'], 'icon' => 'fa-desktop'];
        if ($row['asset_archived_at']) {
            $issues[$service_id][] = $base + ['tone' => 'warning', 'text' => $row['asset_name'] . ': archived, but still listed - unlink it or link what replaced it', 'archived' => true];
        } elseif (in_array($row['asset_status'], SERVICE_ASSET_DOWN_STATUSES, true)) {
            $issues[$service_id][] = $base + ['tone' => $row['asset_status'] === 'Out for Repair' ? 'warning' : 'danger', 'text' => $row['asset_name'] . ': ' . strtolower($row['asset_status'])];
        } else {
            $warranty = assetWarranty($row['asset_warranty_expire']);
            $issues[$service_id][] = $base + ['tone' => 'warning', 'text' => $row['asset_name'] . ': warranty ' . lcfirst($warranty['text'])];
        }
    }

    // Anything else linked that has since been archived - the map no longer matches
    foreach (['credential', 'contact', 'vendor', 'document', 'domain', 'certificate'] as $type) {
        $relation = linkRelation('service', $type);
        $entity = LINK_ENTITIES[$type];
        $sql = mysqli_query($mysqli, "SELECT l.service_id, t.{$entity['id']}, t.{$entity['name']} FROM {$relation['table']} l
            JOIN {$entity['table']} t ON t.{$entity['id']} = l.{$entity['id']}
            WHERE l.service_id IN ($in) AND t.{$entity['archived']} IS NOT NULL ORDER BY t.{$entity['name']}");
        while ($row = mysqli_fetch_row($sql)) {
            $issues[intval($row[0])][] = ['tone' => 'warning', 'icon' => $entity['icon'], 'text' => $row[2] . ': ' . $entity['label'] . ' archived, but still listed - unlink it or link what replaced it',
                'type' => $type, 'id' => intval($row[1]), 'name' => $row[2], 'archived' => true];
        }
    }

    foreach ($issues as &$list) {
        usort($list, function ($a, $b) {
            return ($a['tone'] === 'danger' ? 0 : 1) <=> ($b['tone'] === 'danger' ? 0 : 1);
        });
    }
    unset($list);
    return $issues;
}

/** The issues as a list of soft lines - on the service page and its quick look. */
function serviceIssuesHtml(array $issues, $limit = 0) {
    if (!$issues) {
        return '';
    }
    $shown = $limit ? array_slice($issues, 0, $limit) : $issues;
    $html = '<ul class="list-unstyled mb-0 itflow-service-issues">';
    foreach ($shown as $issue) {
        $html .= '<li class="text-' . $issue['tone'] . '-emphasis"><i class="fas fa-fw ' . ($issue['tone'] === 'danger' ? 'fa-exclamation-circle' : 'fa-exclamation-triangle') . ' me-1"></i>'
            . '<i class="fas fa-fw ' . $issue['icon'] . ' text-body-secondary me-1"></i>' . escapeHtml($issue['text']) . '</li>';
    }
    if ($limit && count($issues) > $limit) {
        $html .= '<li class="text-body-secondary small">and ' . (count($issues) - $limit) . ' more</li>';
    }
    return $html . '</ul>';
}

/**
 * Everything of one type linked to a service, archived ones included (they
 * are flagged) - the service page's tabs. Rows as linkedRecords() gives them,
 * plus 'archived_at'.
 */
function serviceLinked($service_id, $type) {
    global $mysqli;
    $relation = linkRelation('service', $type);
    $entity = LINK_ENTITIES[$type] ?? null;
    if (!$relation || !$entity) {
        return [];
    }
    $service_id = intval($service_id);
    $extra = linkDisplayColumns($type);
    $sql = mysqli_query($mysqli, "SELECT t.{$entity['id']} AS id, t.{$entity['name']} AS name, t.{$entity['archived']} AS archived_at" . ($extra ? ", $extra" : '') . "
        FROM {$relation['table']} l
        JOIN {$entity['table']} t ON t.{$entity['id']} = l.{$entity['id']}
        WHERE l.service_id = $service_id
        ORDER BY t.{$entity['archived']} IS NOT NULL, t.{$entity['name']} ASC");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * The services an asset, contact, vendor, domain, certificate, credential or
 * document is part of - active ones, for the "Services" sections around
 * the app. Raw rows.
 */
function servicesFor($type, $id) {
    global $mysqli;
    $relation = linkRelation('service', $type);
    $entity = LINK_ENTITIES[$type] ?? null;
    if (!$relation || !$entity) {
        return [];
    }
    $id = intval($id);
    $sql = mysqli_query($mysqli, "SELECT services.service_id, service_name, service_description, service_category, service_importance,
            service_review_due, service_reviewed_at, service_client_id
        FROM {$relation['table']} l JOIN services ON services.service_id = l.service_id
        WHERE l.{$entity['id']} = $id AND service_archived_at IS NULL
        ORDER BY FIELD(service_importance, 'High', 'Medium', 'Low'), service_name");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Services as rows - tile, name opening the page, category and description,
 * importance - for the Services tabs on the asset, contact and vendor pages
 * and the domain and certificate quick looks. $unlink($row) gives the unlink
 * URL for a row, or '' for none.
 */
function serviceRowsHtml(array $rows, $unlink = null) {
    if (!$rows) {
        return '';
    }
    $html = '<ul class="list-unstyled mb-0 itflow-links">';
    foreach ($rows as $row) {
        $service_id = intval($row['service_id']);
        $detail = implode(' · ', array_filter([$row['service_category'], $row['service_description']]));
        $remove = $unlink ? $unlink($row) : '';
        $html .= '<li class="itflow-link-row">' . serviceTile($row['service_category'], 'sm')
            . '<div class="min-w-0 flex-grow-1"><div class="d-flex align-items-center gap-2 min-w-0"><a class="text-truncate fw-semibold" href="' . escapeHtml(serviceUrl($service_id, $row['service_client_id'])) . '">' . escapeHtml($row['service_name']) . '</a>'
            . '<a class="ajax-modal text-body-secondary small" href="#" data-modal-size="lg" data-modal-url="modals/service/service.php?id=' . $service_id . '" title="Quick look"><i class="fas fa-eye"></i></a></div>'
            . ($detail !== '' ? '<div class="small text-body-secondary text-truncate">' . escapeHtml($detail) . '</div>' : '') . '</div>'
            . '<span class="ms-2 text-nowrap">' . serviceImportancePill($row['service_importance']) . '</span>'
            . ($remove ? '<a class="itflow-link-remove confirm-link" href="' . escapeHtml($remove) . '" title="Unlink service" data-confirm-text="' . escapeHtml($row['service_name']) . ' stops listing it."><i class="fas fa-unlink"></i></a>' : '')
            . '</li>';
    }
    return $html . '</ul>';
}

/** Web links to it - the logins' and its assets' URLs, each once: [['url', 'label', 'icon'], ...]. Raw. */
function serviceUrls($service_id, $with_credentials) {
    global $mysqli;
    $service_id = intval($service_id);
    $urls = [];
    $sql = mysqli_query($mysqli, "SELECT asset_uri, asset_uri_2 FROM service_assets JOIN assets ON assets.asset_id = service_assets.asset_id
        WHERE service_id = $service_id AND asset_archived_at IS NULL");
    while ($row = mysqli_fetch_assoc($sql)) {
        foreach ([$row['asset_uri'], $row['asset_uri_2']] as $uri) {
            $urls[] = $uri;
        }
    }
    if ($with_credentials) {
        $sql = mysqli_query($mysqli, "SELECT credential_uri, credential_uri_2 FROM service_credentials JOIN credentials ON credentials.credential_id = service_credentials.credential_id
            WHERE service_id = $service_id AND credential_archived_at IS NULL");
        while ($row = mysqli_fetch_assoc($sql)) {
            foreach ([$row['credential_uri'], $row['credential_uri_2']] as $uri) {
                $urls[] = $uri;
            }
        }
    }
    $out = [];
    foreach ($urls as $uri) {
        $uri = trim(html_entity_decode((string) $uri, ENT_QUOTES, 'UTF-8'));
        if ($uri === '' || isset($out[$uri])) {
            continue;
        }
        $scheme = strtolower((string) parse_url($uri, PHP_URL_SCHEME));
        if ($scheme === '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(\/|$)/i', $uri)) {
            $uri = "https://$uri";
        } elseif (!in_array($scheme, ['http', 'https'], true)) {
            // rdp://, ssh:// and the rest open from the asset or login itself
            continue;
        }
        $host = preg_replace('/^www\./i', '', (string) parse_url($uri, PHP_URL_HOST));
        $out[$uri] = ['url' => $uri, 'label' => $host !== '' ? $host . rtrim((string) parse_url($uri, PHP_URL_PATH), '/') : assetLinkLabel($uri), 'icon' => assetLinkIcon($uri)];
    }
    return array_values($out);
}

/**
 * The fields of the add and edit forms, checked, as a SET clause - or ''
 * with $error. Notes are plain text. $current is the row being edited.
 */
function serviceFormSet($post, &$error = '', $current = null) {
    $name = trim((string) ($post['name'] ?? ''));
    if ($name === '') {
        $error = 'A service needs a name.';
        return '';
    }
    $importance = in_array($post['importance'] ?? '', SERVICE_IMPORTANCE, true) ? $post['importance'] : 'Medium';
    $interval = intval($post['review_interval'] ?? 0);
    if (!isset(SERVICE_REVIEW_INTERVALS[$interval])) {
        $interval = 0;
    }
    // The next review: the date given, or the schedule from the last review (or today)
    $review_due = trim((string) ($post['review_due'] ?? ''));
    if ($review_due !== '' && !DateTime::createFromFormat('!Y-m-d', $review_due)) {
        $review_due = '';
    }
    if ($review_due === '' && $interval) {
        $review_due = serviceNextReview($interval, $current['service_reviewed_at'] ?? null);
        if ($review_due < date('Y-m-d')) {
            $review_due = date('Y-m-d');
        }
    }
    return "service_name = '" . escapeSql(mb_substr($name, 0, 200)) . "',
        service_description = '" . escapeSql(mb_substr(trim((string) ($post['description'] ?? '')), 0, 200)) . "',
        service_category = '" . escapeSql(mb_substr(trim((string) ($post['category'] ?? '')), 0, 50)) . "',
        service_importance = '$importance',
        service_backup = '" . escapeSql(mb_substr(trim((string) ($post['backup'] ?? '')), 0, 500)) . "',
        service_notes = '" . escapeSql((string) ($post['notes'] ?? $post['note'] ?? '')) . "',
        service_review_interval = $interval,
        service_review_due = " . ($review_due !== '' ? "'$review_due'" : 'NULL');
}

/** Mark it reviewed now: the next review falls due on $next_due, or after its interval. Logged with $note. */
function serviceMarkReviewed($row, $next_due = '', $note = '') {
    global $mysqli, $session_name;
    $service_id = intval($row['service_id']);
    if ($next_due === '' || !DateTime::createFromFormat('!Y-m-d', $next_due)) {
        $next_due = serviceNextReview($row['service_review_interval']);
    }
    mysqli_query($mysqli, "UPDATE services SET service_reviewed_at = NOW(), service_review_due = " . ($next_due ? "'$next_due'" : 'NULL') . " WHERE service_id = $service_id");
    $note = trim(preg_replace('/\s+/', ' ', strip_tags((string) $note)));
    logAudit("Service", "Review", "$session_name reviewed service " . escapeSql($row['service_name'])
        . ($next_due ? ' - next review ' . $next_due : '') . ($note !== '' ? ': ' . escapeSql(mb_substr($note, 0, 400)) : ''), intval($row['service_client_id']), $service_id);
    return $next_due;
}

function serviceArchive($row) {
    global $mysqli, $session_name;
    $service_id = intval($row['service_id']);
    mysqli_query($mysqli, "UPDATE services SET service_archived_at = NOW() WHERE service_id = $service_id");
    logAudit("Service", "Archive", "$session_name archived service " . escapeSql($row['service_name']), intval($row['service_client_id']), $service_id);
}

function serviceRestore($row) {
    global $mysqli, $session_name;
    $service_id = intval($row['service_id']);
    mysqli_query($mysqli, "UPDATE services SET service_archived_at = NULL WHERE service_id = $service_id");
    logAudit("Service", "Restore", "$session_name restored service " . escapeSql($row['service_name']), intval($row['service_client_id']), $service_id);
}

/** Delete for good - its links and ticket links go with it (ON DELETE CASCADE); the records it linked to stay. */
function serviceDelete($row) {
    global $mysqli, $session_name;
    $service_id = intval($row['service_id']);
    mysqli_query($mysqli, "DELETE FROM services WHERE service_id = $service_id");
    logAudit("Service", "Delete", "$session_name deleted service " . escapeSql($row['service_name']), intval($row['service_client_id']), $service_id);
}

/** Link tickets to a service - the client's own only. Returns how many were new. */
function serviceLinkTickets($service_id, $client_id, array $ticket_ids) {
    global $mysqli;
    $service_id = intval($service_id);
    $client_id = intval($client_id);
    $added = 0;
    foreach (array_unique(array_filter(array_map('intval', $ticket_ids))) as $ticket_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO service_tickets (service_id, ticket_id)
            SELECT $service_id, ticket_id FROM tickets WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id");
        $added += max(0, mysqli_affected_rows($mysqli));
    }
    return $added;
}

/**
 * The services a ticket is about: the ones it was raised for (linked) and
 * the ones its assets are part of - [service_id => row + 'linked' => bool].
 */
function ticketServices($ticket_id) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $sql = mysqli_query($mysqli, "SELECT services.service_id, service_name, service_category, service_importance, service_client_id,
            MAX(x.linked) AS linked
        FROM (
            SELECT st.service_id, 1 AS linked FROM service_tickets st WHERE st.ticket_id = $ticket_id
            UNION ALL SELECT sa.service_id, 0 FROM service_assets sa JOIN tickets t ON t.ticket_asset_id = sa.asset_id WHERE t.ticket_id = $ticket_id
            UNION ALL SELECT sa.service_id, 0 FROM service_assets sa JOIN ticket_assets ta ON ta.asset_id = sa.asset_id WHERE ta.ticket_id = $ticket_id
        ) x JOIN services ON services.service_id = x.service_id
        WHERE service_archived_at IS NULL
        GROUP BY services.service_id
        ORDER BY FIELD(service_importance, 'High', 'Medium', 'Low'), service_name");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['linked'] = (bool) intval($row['linked']);
        $rows[intval($row['service_id'])] = $row;
    }
    return $rows;
}

/**
 * The details a ticket about the service starts with - what it is, what it
 * runs on, who to call, how it comes back - plain text, one fact a line.
 */
function serviceTicketDetails($row, $assets, $vendors) {
    $lines = ['Service: ' . $row['service_name'] . ' (' . $row['service_importance'] . ' importance)'];
    if ($assets) {
        $lines[] = 'Runs on: ' . implode(', ', array_column(array_filter($assets, function ($a) { return !$a['archived_at']; }), 'name'));
    }
    if ($vendors) {
        $lines[] = 'Vendors: ' . implode(', ', array_column(array_filter($vendors, function ($v) { return !$v['archived_at']; }), 'name'));
    }
    if (trim((string) $row['service_backup']) !== '') {
        $lines[] = 'Backup and recovery: ' . $row['service_backup'];
    }
    $lines[] = '';
    $lines[] = 'What is happening:';
    return implode("\n", $lines);
}
