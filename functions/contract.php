<?php

/*
 * Client contracts - the agreement with each client: what kind it is, when
 * it starts and ends, whether it renews itself and how much notice ending it
 * takes, the SLA for each ticket priority (from Admin > SLAs), the hourly
 * rates, net terms and support hours, and who looks after it. A contract is
 * a Draft until it is made Active; the nightly job (contractsNightly())
 * renews an auto-renewing one when its term ends, ends one that doesn't, and
 * reminds its owner before the notice date comes. Cancelled ones are kept.
 *
 * Templates (Admin > Contract Templates) hold the same terms, to start a
 * contract from. agent/contracts.php, agent/contract.php and their windows
 * and handlers (agent/post/contract.php) are built on these.
 */

/** The kinds of contract - key => label, icon and what it means. */
const CONTRACT_TYPES = [
    'managed' => ['label' => 'Fully managed', 'icon' => 'fa-shield-alt', 'about' => 'A fixed monthly fee covers support and looking after everything'],
    'comanaged' => ['label' => 'Co-managed', 'icon' => 'fa-handshake', 'about' => 'Shared with the client\'s own IT people'],
    'block' => ['label' => 'Block hours', 'icon' => 'fa-hourglass-half', 'about' => 'Hours paid for up front, drawn down as work is done'],
    'time_materials' => ['label' => 'Time and materials', 'icon' => 'fa-clock', 'about' => 'Billed for the time and parts each job takes'],
    'break_fix' => ['label' => 'Break/fix', 'icon' => 'fa-wrench', 'about' => 'Fixed when something breaks, billed per job'],
    'project' => ['label' => 'Project', 'icon' => 'fa-project-diagram', 'about' => 'One piece of work, with a start and an end'],
    'other' => ['label' => 'Other', 'icon' => 'fa-file-contract', 'about' => ''],
];

/** The term lengths offered, in months. Any other number can be typed. */
const CONTRACT_TERMS = [1 => 'Monthly', 3 => '3 months', 6 => '6 months', 12 => '1 year', 24 => '2 years', 36 => '3 years', 60 => '5 years'];

/** How many days before the notice date the owner is reminded. */
const CONTRACT_REMINDER_DAYS = 30;

/** The ticket priorities a contract sets an SLA for - priority => column part. */
const CONTRACT_SLA_PRIORITIES = ['Low' => 'low', 'Medium' => 'medium', 'High' => 'high', 'Urgent' => 'urgent'];

/** Whether the 3.4.2 update has run - the contracts tables as these functions read them. */
function contractsReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        // The pages define the version; the nightly job and other scripts don't, so read it
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.4.2', '>=');
    }
    return $ready;
}

/** A contract with its client, contact, owner and template names - or null. */
function contractRow($contract_id) {
    global $mysqli;
    $contract_id = intval($contract_id);
    if ($contract_id <= 0 || !contractsReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contracts.*, client_name, client_rate, client_net_terms, client_currency_code, contact_name, contact_title, contact_email, user_name AS owner_name, user_email AS owner_email,
            contract_template_name
        FROM contracts
        LEFT JOIN clients ON contract_client_id = client_id
        LEFT JOIN contacts ON contract_contact_id = contact_id
        LEFT JOIN users ON contract_owner_id = user_id
        LEFT JOIN contract_templates ON contracts.contract_template_id = contract_templates.contract_template_id
        WHERE contract_id = $contract_id LIMIT 1")) ?: null;
}

/** A template, or null. */
function contractTemplateRow($contract_template_id) {
    global $mysqli;
    $contract_template_id = intval($contract_template_id);
    if ($contract_template_id <= 0 || !contractsReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM contract_templates WHERE contract_template_id = $contract_template_id LIMIT 1")) ?: null;
}

/* The templates in use - id => row, by name. */
function contractTemplates() {
    global $mysqli;
    $templates = [];
    if (!contractsReady()) {
        return $templates;
    }
    $sql = mysqli_query($mysqli, "SELECT * FROM contract_templates WHERE contract_template_archived_at IS NULL ORDER BY contract_template_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $templates[intval($row['contract_template_id'])] = $row;
    }
    return $templates;
}

/** A type's label - the key's, or the text itself for one ITFlow doesn't know. */
function contractTypeLabel($type) {
    return CONTRACT_TYPES[(string) $type]['label'] ?? ((string) $type !== '' ? (string) $type : 'Contract');
}

/** A term in words - "1 year", "18 months". */
function contractTermLabel($months) {
    $months = intval($months);
    if (isset(CONTRACT_TERMS[$months])) {
        return CONTRACT_TERMS[$months];
    }
    if ($months > 0 && $months % 12 === 0) {
        return ($months / 12) . ' years';
    }
    return $months === 1 ? '1 month' : "$months months";
}

/** The last day of a term that starts on $start and runs $months months - 'Y-m-d'. */
function contractTermEnd($start, $months) {
    $start = strtotime((string) $start);
    if (!$start || intval($months) <= 0) {
        return '';
    }
    return date('Y-m-d', strtotime('+' . intval($months) . ' months -1 day', $start));
}

/** The day after the last day of the term following one that ends on $end - its new last day. */
function contractNextEnd($end, $months) {
    $end = strtotime((string) $end);
    if (!$end || intval($months) <= 0) {
        return '';
    }
    return contractTermEnd(date('Y-m-d', strtotime('+1 day', $end)), $months);
}

/** The last day notice can be given to stop it renewing (or to end it) - '' when it has no end date. */
function contractNoticeDate(array $row) {
    if (empty($row['contract_end_date'])) {
        return '';
    }
    return date('Y-m-d', strtotime('-' . max(0, intval($row['contract_notice_days'])) . ' days', strtotime($row['contract_end_date'])));
}

/** When the owner is reminded - CONTRACT_REMINDER_DAYS before the notice date. */
function contractReminderDate(array $row) {
    $notice = contractNoticeDate($row);
    return $notice !== '' ? date('Y-m-d', strtotime('-' . CONTRACT_REMINDER_DAYS . ' days', strtotime($notice))) : '';
}

/*
 * Where a contract stands - key, label, Bootstrap tone, icon and a line
 * saying when: draft, upcoming (active but not started), active, renewing
 * (auto-renews soon), ending (ends soon and won't renew), ended, cancelled.
 */
function contractState(array $row, $today = null) {
    $today = $today ?? date('Y-m-d');
    $status = (string) $row['contract_status'];
    $start = (string) ($row['contract_start_date'] ?? '');
    $end = (string) ($row['contract_end_date'] ?? '');
    $day = function ($date) {
        return date('M j, Y', strtotime($date));
    };
    if ($status === 'Cancelled') {
        return ['key' => 'cancelled', 'label' => 'Cancelled', 'tone' => 'secondary', 'icon' => 'fa-ban',
            'when' => $end !== '' && $end >= $today ? 'Cancelled - in force until ' . $day($end) : 'Cancelled' . (!empty($row['contract_cancelled_at']) ? ' ' . $day($row['contract_cancelled_at']) : '')];
    }
    if ($status === 'Draft') {
        return ['key' => 'draft', 'label' => 'Draft', 'tone' => 'secondary', 'icon' => 'fa-pencil-alt', 'when' => 'Not in force yet'];
    }
    if ($status === 'Ended' || ($end !== '' && $end < $today && !intval($row['contract_auto_renew']))) {
        return ['key' => 'ended', 'label' => 'Ended', 'tone' => 'dark', 'icon' => 'fa-flag-checkered', 'when' => $end !== '' ? 'Ended ' . $day($end) : 'Ended'];
    }
    if ($start !== '' && $start > $today) {
        return ['key' => 'upcoming', 'label' => 'Starts soon', 'tone' => 'info', 'icon' => 'fa-hourglass-start', 'when' => 'Starts ' . $day($start)];
    }
    if ($end === '') {
        return ['key' => 'active', 'label' => 'Active', 'tone' => 'success', 'icon' => 'fa-check-circle', 'when' => 'No end date'];
    }
    $remind = contractReminderDate($row);
    if (intval($row['contract_auto_renew'])) {
        if ($remind !== '' && $today >= $remind) {
            return ['key' => 'renewing', 'label' => 'Renews soon', 'tone' => 'primary', 'icon' => 'fa-sync-alt',
                'when' => 'Renews ' . $day(date('Y-m-d', strtotime('+1 day', strtotime($end))))];
        }
        return ['key' => 'active', 'label' => 'Active', 'tone' => 'success', 'icon' => 'fa-check-circle', 'when' => 'Renews ' . $day(date('Y-m-d', strtotime('+1 day', strtotime($end))))];
    }
    if ($remind !== '' && $today >= $remind) {
        return ['key' => 'ending', 'label' => 'Ends soon', 'tone' => 'warning', 'icon' => 'fa-exclamation-triangle', 'when' => 'Ends ' . $day($end)];
    }
    return ['key' => 'active', 'label' => 'Active', 'tone' => 'success', 'icon' => 'fa-check-circle', 'when' => 'Ends ' . $day($end)];
}

/** A contract's state as a badge. */
function contractStateHtml(array $row) {
    $state = contractState($row);
    return '<span class="badge rounded-pill text-bg-' . $state['tone'] . '" title="' . escapeHtml($state['when']) . '"><i class="fas fa-fw ' . $state['icon'] . ' me-1"></i>' . escapeHtml($state['label']) . '</span>';
}

/*
 * The list's tabs - key => label, icon and SQL. Due soon is active, with an
 * end date within its notice period and a month before it; Signing is out
 * for signature or waiting to be countersigned (once 3.4.3 has run).
 */
function contractViews() {
    $soon = "contract_status = 'Active' AND contract_end_date IS NOT NULL AND contract_end_date <= CURDATE() + INTERVAL (contract_notice_days + " . CONTRACT_REMINDER_DAYS . ") DAY";
    $views = [
        'active' => ['label' => 'Active', 'icon' => 'fa-check-circle', 'where' => "contract_status = 'Active'", 'title' => 'In force, or starting soon'],
        'soon' => ['label' => 'Due soon', 'icon' => 'fa-hourglass-end', 'where' => $soon, 'title' => 'Renewing or ending - within a month of their notice date'],
    ];
    // Out for signature, or signed by the client and waiting for us - a request not withdrawn and not countersigned (3.4.3 on)
    if (function_exists('contractSignaturesReady') && contractSignaturesReady()) {
        $views['signing'] = ['label' => 'Signing', 'icon' => 'fa-file-signature', 'title' => 'Out for signature, or waiting for you to countersign',
            'where' => "EXISTS (SELECT 1 FROM contract_signatures WHERE contract_signature_contract_id = contract_id AND contract_signature_sent_at IS NOT NULL
                AND contract_signature_withdrawn_at IS NULL AND contract_signature_agent_signed_at IS NULL AND contract_signature_url_key <> '')"];
    }
    return $views + [
        'draft' => ['label' => 'Drafts', 'icon' => 'fa-pencil-alt', 'where' => "contract_status = 'Draft'", 'title' => 'Not in force yet'],
        'ended' => ['label' => 'Ended', 'icon' => 'fa-flag-checkered', 'where' => "contract_status IN ('Ended', 'Cancelled')", 'title' => 'Ended or cancelled'],
        'all' => ['label' => 'All', 'icon' => '', 'where' => '1 = 1'],
    ];
}

/* The SLAs a contract can pick - id => row, the ones in use and any it already has. */
function contractSlaChoices(array $keep = []) {
    global $mysqli;
    $keep = array_filter(array_map('intval', $keep));
    $choices = [];
    $sql = mysqli_query($mysqli, "SELECT sla_id, sla_name, sla_response_minutes, sla_resolution_minutes, sla_archived_at FROM slas
        WHERE sla_archived_at IS NULL" . ($keep ? " OR sla_id IN (" . implode(',', $keep) . ")" : '') . " ORDER BY sla_response_minutes ASC, sla_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $choices[intval($row['sla_id'])] = $row;
    }
    return $choices;
}

/** An SLA's targets in words - "Respond in 1 hr, resolve in 8 hrs". */
function contractSlaTargets(array $sla) {
    $text = 'Respond in ' . slaMinutesText($sla['sla_response_minutes']);
    if (intval($sla['sla_resolution_minutes'] ?? 0) > 0) {
        $text .= ', resolve in ' . slaMinutesText($sla['sla_resolution_minutes']);
    }
    return $text;
}

/** Minutes in words - "45 min", "4 hrs", "2 days". */
function slaMinutesText($minutes) {
    $minutes = intval($minutes);
    if ($minutes > 0 && $minutes % 1440 === 0 && $minutes >= 2880) {
        return ($minutes / 1440) . ' days';
    }
    if ($minutes >= 60 && $minutes % 60 === 0) {
        return ($minutes / 60) . ($minutes === 60 ? ' hr' : ' hrs');
    }
    if ($minutes >= 60) {
        return round($minutes / 60, 1) . ' hrs';
    }
    return "$minutes min";
}

/*
 * The fields of a contract or a template, from a form - [$fields, $error].
 * $kind 'contract' or 'template'. Money and days that are left empty are
 * null: the client's own rate and terms apply.
 */
function contractFromInput(array $in, $kind = 'contract') {
    global $mysqli;
    $text = function ($key, $max) use ($in) {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($in[$key] ?? '')))), 0, $max);
    };
    $money = function ($key) use ($in) {
        $value = trim((string) ($in[$key] ?? ''));
        if ($value === '') {
            return null;
        }
        $number = function_exists('billingNumberInput') ? billingNumberInput($value) : (is_numeric($value) ? floatval($value) : null);
        return $number === null ? false : round($number, 2);
    };
    $hours = function ($key) use ($in) {
        $value = trim(str_replace(',', '', (string) ($in[$key] ?? '')));
        return $value === '' ? 0.0 : (is_numeric($value) ? round(floatval($value), 2) : false);
    };
    $f = [
        'name' => $text('name', 255),
        'type' => isset(CONTRACT_TYPES[(string) ($in['type'] ?? '')]) ? (string) $in['type'] : 'managed',
        'description' => $text('description', 1000),
        'term_months' => intval($in['term_months'] ?? 12),
        'auto_renew' => !empty($in['auto_renew']) ? 1 : 0,
        'notice_days' => intval($in['notice_days'] ?? 30),
        'rate_standard' => $money('rate_standard'),
        'rate_after_hours' => $money('rate_after_hours'),
        'net_terms' => trim((string) ($in['net_terms'] ?? '')) === '' ? null : intval($in['net_terms']),
        'support_hours' => $text('support_hours', 100),
        'details' => isset($in['details']) ? documentHtmlPurify((string) $in['details']) : null,
        'sla' => [],
        // Prepaid hours (functions/contract_hours.php) - included each month, and how few left is low
        'monthly_hours' => $hours('monthly_hours'),
        'hours_alert' => $hours('hours_alert'),
    ];
    foreach (CONTRACT_SLA_PRIORITIES as $priority => $part) {
        $f['sla'][$part] = intval($in["sla_$part"] ?? 0);
    }
    if ($kind === 'contract') {
        $f += [
            'client_id' => intval($in['client_id'] ?? 0),
            'contact_id' => intval($in['contact_id'] ?? 0),
            'owner_id' => intval($in['owner_id'] ?? 0),
            'template_id' => intval($in['template_id'] ?? 0),
            'status' => in_array($in['status'] ?? '', ['Draft', 'Active'], true) ? $in['status'] : 'Draft',
            'start_date' => trim((string) ($in['start_date'] ?? '')),
            'end_date' => trim((string) ($in['end_date'] ?? '')),
            'notes' => mb_substr(trim((string) ($in['notes'] ?? '')), 0, 10000),
        ];
    }

    if ($f['name'] === '') {
        return [$f, 'Give it a name.'];
    }
    if ($f['term_months'] < 1 || $f['term_months'] > 120) {
        return [$f, 'A term is 1 to 120 months.'];
    }
    if ($f['notice_days'] < 0 || $f['notice_days'] > 365) {
        return [$f, 'Notice is 0 to 365 days.'];
    }
    if ($f['rate_standard'] === false || $f['rate_after_hours'] === false || ($f['rate_standard'] ?? 0) < 0 || ($f['rate_after_hours'] ?? 0) < 0) {
        return [$f, 'A rate is an amount, such as 125.00 - or leave it empty for the client\'s own.'];
    }
    if ($f['net_terms'] !== null && ($f['net_terms'] < 0 || $f['net_terms'] > 365)) {
        return [$f, 'Net terms are 0 to 365 days - or leave them empty for the client\'s own.'];
    }
    if ($f['monthly_hours'] === false || $f['monthly_hours'] < 0 || $f['monthly_hours'] >= 1000) {
        return [$f, 'Hours each month are a number of hours under 1,000, such as 10 or 7.5 - or leave it empty for none.'];
    }
    if ($f['hours_alert'] === false || $f['hours_alert'] < 0 || $f['hours_alert'] >= 10000) {
        return [$f, 'The hours to be told at are a number of hours, such as 2 - or leave it empty to not be told.'];
    }
    $slas = contractSlaChoices(array_values($f['sla']));
    foreach ($f['sla'] as $part => $sla_id) {
        if ($sla_id && !isset($slas[$sla_id])) {
            return [$f, 'Pick an SLA from the list.'];
        }
    }
    if ($kind === 'contract') {
        if (!$f['client_id'] || !mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = {$f['client_id']} AND client_archived_at IS NULL" . clientScopeSql('client_id')))) {
            return [$f, 'Pick a client you can open.'];
        }
        foreach (['start_date', 'end_date'] as $date) {
            if ($f[$date] !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$date]) || !strtotime($f[$date]))) {
                return [$f, 'A date is a day, like 2026-11-01.'];
            }
        }
        if ($f['start_date'] !== '' && $f['end_date'] !== '' && $f['end_date'] < $f['start_date']) {
            return [$f, 'It ends before it starts.'];
        }
        if ($f['status'] === 'Active' && $f['start_date'] === '') {
            return [$f, 'An active contract needs a start date.'];
        }
        if ($f['contact_id'] && !mysqli_num_rows(mysqli_query($mysqli, "SELECT contact_id FROM contacts WHERE contact_id = {$f['contact_id']} AND contact_client_id = {$f['client_id']}"))) {
            return [$f, 'Pick one of the client\'s contacts.'];
        }
        if ($f['owner_id'] && !mysqli_num_rows(mysqli_query($mysqli, "SELECT user_id FROM users WHERE user_id = {$f['owner_id']} AND user_type = 1 AND user_archived_at IS NULL"))) {
            return [$f, 'Pick an agent to look after it.'];
        }
        if ($f['template_id'] && !contractTemplateRow($f['template_id'])) {
            $f['template_id'] = 0;
        }
    }
    return [$f, ''];
}

/** The SET clause shared by contracts and templates - $prefix 'contract' or 'contract_template'. */
function contractTermsSetSql(array $f, $prefix) {
    global $mysqli;
    $money = function ($value) {
        return $value === null ? 'NULL' : sprintf('%.2f', $value);
    };
    $sql = [
        "{$prefix}_name = '" . escapeSql($f['name']) . "'",
        "{$prefix}_type = '" . escapeSql($f['type']) . "'",
        "{$prefix}_term_months = {$f['term_months']}",
        "{$prefix}_auto_renew = {$f['auto_renew']}",
        "{$prefix}_notice_days = {$f['notice_days']}",
        "{$prefix}_rate_standard = " . $money($f['rate_standard']),
        "{$prefix}_rate_after_hours = " . $money($f['rate_after_hours']),
        "{$prefix}_net_terms = " . ($f['net_terms'] === null ? 'NULL' : intval($f['net_terms'])),
        "{$prefix}_support_hours = " . ($f['support_hours'] !== '' ? "'" . escapeSql($f['support_hours']) . "'" : 'NULL'),
    ];
    foreach ($f['sla'] as $part => $sla_id) {
        $sql[] = "{$prefix}_sla_{$part}_id = " . intval($sla_id);
    }
    if (function_exists('contractHoursReady') && contractHoursReady()) {
        $sql[] = "{$prefix}_monthly_hours = " . sprintf('%.2f', $f['monthly_hours'] ?? 0);
        $sql[] = "{$prefix}_hours_alert = " . sprintf('%.2f', $f['hours_alert'] ?? 0);
    }
    if ($f['details'] !== null) {
        // Purified HTML - escapeSql() would strip its tags
        $sql[] = "{$prefix}_details = '" . mysqli_real_escape_string($mysqli, $f['details']) . "'";
    }
    return implode(', ', $sql);
}

/** The SET clause for a contract's own fields. */
function contractSetSql(array $f) {
    global $mysqli;
    // The notes are plain text, shown escaped - kept as written, < and all
    return contractTermsSetSql($f, 'contract') . ",
        contract_client_id = {$f['client_id']}, contract_contact_id = {$f['contact_id']}, contract_owner_id = {$f['owner_id']}, contract_template_id = {$f['template_id']},
        contract_start_date = " . ($f['start_date'] !== '' ? "'" . escapeSql($f['start_date']) . "'" : 'NULL') . ",
        contract_end_date = " . ($f['end_date'] !== '' ? "'" . escapeSql($f['end_date']) . "'" : 'NULL') . ",
        contract_notes = " . ($f['notes'] !== '' ? "'" . mysqli_real_escape_string($mysqli, $f['notes']) . "'" : 'NULL');
}

/** A new contract - its id. */
function contractCreate(array $f) {
    global $mysqli, $session_user_id;
    mysqli_query($mysqli, "INSERT INTO contracts SET " . contractSetSql($f) . ", contract_status = '" . escapeSql($f['status']) . "', contract_created_by = " . intval($session_user_id ?? 0));
    return intval(mysqli_insert_id($mysqli));
}

/*
 * Save a contract's fields - what changed, as phrases for the audit log. A
 * new end date means a new term, so the owner is reminded again.
 */
function contractUpdate(array $row, array $f) {
    global $mysqli;
    $id = intval($row['contract_id']);
    $reset = (string) $row['contract_end_date'] !== $f['end_date'] ? ', contract_reminded_at = NULL' : '';
    $hours = function_exists('contractHoursReady') && contractHoursReady() && array_key_exists('contract_hours_alert', $row);
    // A new level to be told at is told again at it
    $reset .= $hours && round(floatval($row['contract_hours_alert']), 2) !== round($f['hours_alert'], 2) ? ', contract_hours_alerted_at = NULL' : '';
    mysqli_query($mysqli, "UPDATE contracts SET " . contractSetSql($f) . "$reset WHERE contract_id = $id");
    $changes = [];
    $was = [
        'name' => (string) $row['contract_name'], 'type' => contractTypeLabel($row['contract_type']), 'start' => (string) $row['contract_start_date'], 'end' => (string) $row['contract_end_date'],
        'term' => contractTermLabel($row['contract_term_months']), 'notice' => intval($row['contract_notice_days']) . ' days notice',
        'standard rate' => $row['contract_rate_standard'] === null ? 'the client\'s' : sprintf('%.2f', $row['contract_rate_standard']),
        'after-hours rate' => $row['contract_rate_after_hours'] === null ? 'the client\'s' : sprintf('%.2f', $row['contract_rate_after_hours']),
        'net terms' => $row['contract_net_terms'] === null ? 'the client\'s' : intval($row['contract_net_terms']) . ' days',
        'support hours' => (string) $row['contract_support_hours'],
    ];
    if ($hours) {
        $was += ['hours each month' => contractHoursText($row['contract_monthly_hours']), 'hours to be told at' => contractHoursText($row['contract_hours_alert'])];
    }
    $now = [
        'name' => $f['name'], 'type' => contractTypeLabel($f['type']), 'start' => $f['start_date'], 'end' => $f['end_date'],
        'term' => contractTermLabel($f['term_months']), 'notice' => $f['notice_days'] . ' days notice',
        'standard rate' => $f['rate_standard'] === null ? 'the client\'s' : sprintf('%.2f', $f['rate_standard']),
        'after-hours rate' => $f['rate_after_hours'] === null ? 'the client\'s' : sprintf('%.2f', $f['rate_after_hours']),
        'net terms' => $f['net_terms'] === null ? 'the client\'s' : $f['net_terms'] . ' days',
        'support hours' => $f['support_hours'],
    ];
    if ($hours) {
        $now += ['hours each month' => contractHoursText($f['monthly_hours']), 'hours to be told at' => contractHoursText($f['hours_alert'])];
    }
    foreach ($was as $label => $value) {
        if ($value !== $now[$label]) {
            $changes[] = "$label from " . ($value !== '' ? $value : 'none') . ' to ' . ($now[$label] !== '' ? $now[$label] : 'none');
        }
    }
    if (intval($row['contract_auto_renew']) !== $f['auto_renew']) {
        $changes[] = $f['auto_renew'] ? 'renews itself' : 'no longer renews itself';
    }
    foreach ($f['sla'] as $part => $sla_id) {
        if (intval($row["contract_sla_{$part}_id"]) !== $sla_id) {
            $changes[] = "$part priority SLA changed";
        }
    }
    if (intval($row['contract_owner_id']) !== $f['owner_id']) {
        $changes[] = 'owner changed';
    }
    if (intval($row['contract_contact_id']) !== $f['contact_id']) {
        $changes[] = 'contact changed';
    }
    if (trim((string) $row['contract_notes']) !== $f['notes']) {
        $changes[] = 'notes changed';
    }
    if ($f['details'] !== null && (string) $row['contract_details'] !== $f['details']) {
        $changes[] = 'terms changed';
    }
    return $changes;
}

/* Make a draft active from $start (a 'Y-m-d'), with an end date from its term when it has none. */
function contractActivate(array $row, $start) {
    global $mysqli;
    $end = !empty($row['contract_end_date']) ? (string) $row['contract_end_date'] : contractTermEnd($start, $row['contract_term_months']);
    mysqli_query($mysqli, "UPDATE contracts SET contract_status = 'Active', contract_start_date = '" . escapeSql($start) . "', contract_end_date = " . ($end !== '' ? "'" . escapeSql($end) . "'" : 'NULL') . ",
        contract_reminded_at = NULL WHERE contract_id = " . intval($row['contract_id']));
    return $end;
}

/*
 * Renew a contract to a new last day - an active one goes on, an ended or
 * cancelled one is active again. The owner will be reminded again before
 * the new term ends.
 */
function contractRenew(array $row, $new_end) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE contracts SET contract_status = 'Active', contract_end_date = '" . escapeSql($new_end) . "', contract_renewed_at = NOW(), contract_reminded_at = NULL,
        contract_cancelled_at = NULL, contract_cancel_reason = NULL WHERE contract_id = " . intval($row['contract_id']));
}

/* Cancel a contract - its last day is $last_day (it stays in force until then), and why. */
function contractCancel(array $row, $last_day, $reason) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE contracts SET contract_status = 'Cancelled', contract_cancelled_at = NOW(), contract_end_date = '" . escapeSql($last_day) . "', contract_auto_renew = 0,
        contract_cancel_reason = " . ($reason !== '' ? "'" . escapeSql($reason) . "'" : 'NULL') . " WHERE contract_id = " . intval($row['contract_id']));
}

/*
 * The night's work, run by cron/nightly_tasks.php - [renewed, ended,
 * reminded, drawn] for the log:
 *  - an active contract whose last day has passed renews for another term
 *    when it renews itself (as many terms as it takes to reach today), and
 *    ends when it doesn't;
 *  - the owner of one coming up to its notice date is reminded, once a term,
 *    by email and in ITFlow - or everyone, when it has no owner;
 *  - prepaid hours: each month's included hours are added, and the tickets
 *    done since are drawn from them (contractHoursNightly()).
 */
function contractsNightly($today = null) {
    global $mysqli;
    $today = $today ?? date('Y-m-d');
    $done = ['renewed' => 0, 'ended' => 0, 'reminded' => 0, 'drawn' => 0];
    if (!contractsReady()) {
        return $done;
    }

    $sql = mysqli_query($mysqli, "SELECT contract_id FROM contracts WHERE contract_status = 'Active' AND contract_archived_at IS NULL
        AND contract_end_date IS NOT NULL AND contract_end_date < '" . escapeSql($today) . "'");
    while ($id = mysqli_fetch_row($sql)) {
        $row = contractRow($id[0]);
        if (!$row) {
            continue;
        }
        $name = escapeSql($row['contract_name']);
        if (intval($row['contract_auto_renew'])) {
            $end = (string) $row['contract_end_date'];
            while ($end !== '' && $end < $today) {
                $end = contractNextEnd($end, $row['contract_term_months']);
            }
            contractRenew($row, $end);
            logAudit("Contract", "Edit", "Contract $name renewed itself to " . escapeSql($end), intval($row['contract_client_id']), intval($row['contract_id']));
            $done['renewed']++;
        } else {
            mysqli_query($mysqli, "UPDATE contracts SET contract_status = 'Ended' WHERE contract_id = " . intval($row['contract_id']));
            logAudit("Contract", "Edit", "Contract $name ended on " . escapeSql($row['contract_end_date']), intval($row['contract_client_id']), intval($row['contract_id']));
            appNotify("Contract", "Contract $name for " . escapeSql($row['client_name']) . " has ended", "/agent/contract.php?client_id=" . intval($row['contract_client_id']) . "&contract_id=" . intval($row['contract_id']), intval($row['contract_client_id']), intval($row['contract_id']));
            $done['ended']++;
        }
    }

    $sql = mysqli_query($mysqli, "SELECT contract_id FROM contracts WHERE contract_status = 'Active' AND contract_archived_at IS NULL AND contract_reminded_at IS NULL
        AND contract_end_date IS NOT NULL AND contract_end_date >= '" . escapeSql($today) . "'
        AND contract_end_date <= '" . escapeSql($today) . "' + INTERVAL (contract_notice_days + " . CONTRACT_REMINDER_DAYS . ") DAY");
    while ($id = mysqli_fetch_row($sql)) {
        $row = contractRow($id[0]);
        if (!$row) {
            continue;
        }
        contractRemind($row);
        mysqli_query($mysqli, "UPDATE contracts SET contract_reminded_at = NOW() WHERE contract_id = " . intval($row['contract_id']));
        $done['reminded']++;
    }

    // Prepaid hours: each month's included hours, and the tickets done since drawn from them
    if (function_exists('contractHoursNightly')) {
        $done['drawn'] = contractHoursNightly($today);
    }
    return $done;
}

/* Tell the owner a contract is coming up to its notice date - in ITFlow, and by email. */
function contractRemind(array $row) {
    global $config_base_url;
    $client_id = intval($row['contract_client_id']);
    $url = "/agent/contract.php?client_id=$client_id&contract_id=" . intval($row['contract_id']);
    $renews = intval($row['contract_auto_renew']) === 1;
    $notice = contractNoticeDate($row);
    // A notice date already gone by isn't a date to give notice by - say it has passed
    $notice_open = $notice !== '' && $notice >= date('Y-m-d');
    $end = (string) $row['contract_end_date'];
    $what = $renews ? 'renews itself on ' . date('M j, Y', strtotime('+1 day', strtotime($end))) : 'ends on ' . date('M j, Y', strtotime($end));
    appNotify("Contract", escapeSql("Contract {$row['contract_name']} for {$row['client_name']} $what" . ($notice_open ? ' - notice by ' . date('M j, Y', strtotime($notice)) : '')), $url, $client_id, intval($row['contract_id']));
    if (!empty($row['owner_email'])) {
        $passed = $notice !== '' && !$notice_open ? ' Its notice date, ' . date('F j, Y', strtotime($notice)) . ', has passed.' : '';
        emailQueue([['email' => $row['owner_email'], 'name' => $row['owner_name'], 'footnote' => 'You look after this contract in ITFlow.']], "Contract " . ($renews ? 'renewal' : 'ending') . ": {$row['contract_name']} - {$row['client_name']}", [
            'kind' => 'Contract', 'tone' => $renews ? 'primary' : 'warning',
            'title' => $row['contract_name'] . ' ' . $what,
            'intro' => [$renews
                ? "It renews for another " . contractTermLabel($row['contract_term_months']) . ($notice_open ? " unless notice is given by " . date('F j, Y', strtotime($notice)) . ". Now is the time to review it with the client." : ".$passed")
                : "It doesn't renew itself. Renew it, or let the client know it is ending" . ($notice_open ? " - notice is due by " . date('F j, Y', strtotime($notice)) . "." : ".$passed")],
            'summary' => array_filter([
                'Client' => (string) $row['client_name'],
                'Type' => contractTypeLabel($row['contract_type']),
                'Term' => (!empty($row['contract_start_date']) ? date('M j, Y', strtotime($row['contract_start_date'])) . ' to ' : 'Until ') . date('M j, Y', strtotime($end)),
                'Notice' => $notice !== '' ? ($notice_open ? 'By ' : 'Passed - ') . date('M j, Y', strtotime($notice)) : '',
            ]),
            'button' => ['label' => 'Open the contract', 'url' => "https://$config_base_url$url"],
        ]);
    }
}

/* How many contracts a client has that are active - for the client menu. */
function contractClientCount($client_id) {
    global $mysqli;
    if (!contractsReady()) {
        return 0;
    }
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM contracts WHERE contract_client_id = " . intval($client_id) . " AND contract_status = 'Active' AND contract_archived_at IS NULL"))[0]);
}

/* A client's contracts in force now, soonest ending first - for the overview. */
function contractClientActive($client_id) {
    global $mysqli;
    $rows = [];
    if (!contractsReady()) {
        return $rows;
    }
    $sql = mysqli_query($mysqli, "SELECT * FROM contracts WHERE contract_client_id = " . intval($client_id) . " AND contract_status = 'Active' AND contract_archived_at IS NULL
        ORDER BY contract_end_date IS NULL, contract_end_date ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}
