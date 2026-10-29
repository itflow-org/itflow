<?php

/*
 * Client onboarding - taking a client on, a step at a time
 * (agent/client_onboarding.php, its steps in agent/client_onboarding/, saved
 * by agent/post/client_onboarding.php): the company, its locations, its
 * people and their portal access, the agreement (a contract from a template,
 * sent to sign if you like), billing (who is billed, their tax, and the
 * agreement's recurring invoice), IT basics (domains, and vendors and
 * licenses from templates) and a kickoff project from a project template.
 *
 * Each step saves as it goes - the company step makes the client - so it can
 * be left and carried on from the client's overview. A step is done once it
 * is saved here, or, for most, once the client has what it is about however
 * it was added: a location, a contact, a contract, a recurring invoice, a
 * vendor or license, a project. A step can be put off (Skip for now) and done
 * later. Steps the agent's role can't do, or whose module is off, aren't
 * there for them.
 *
 * The client overview shows how far it has got until every step is done or
 * skipped, Finish is pressed on the summary, or its card is closed.
 */

/** The steps, in order - key => nav label, title, icon and what it is for. */
const CLIENT_ONBOARDING_STEPS = [
    'company' => ['label' => 'Company', 'title' => 'The company', 'icon' => 'fa-building',
        'about' => 'Who they are - the name you know them by, what they do, their website and how they found you.'],
    'locations' => ['label' => 'Locations', 'title' => 'Locations', 'icon' => 'fa-map-marker-alt',
        'about' => 'Where they work - their main office first, then any other sites you look after.'],
    'people' => ['label' => 'People', 'title' => 'People', 'icon' => 'fa-users',
        'about' => 'Who you deal with - the primary contact, who gets the invoices and who to call about technical things - and client portal access for them.'],
    'agreement' => ['label' => 'Agreement', 'title' => 'Agreement', 'icon' => 'fa-file-contract',
        'about' => 'The contract you look after them on, from one of your templates - its rates, net terms and SLAs are theirs while it is in force. Send it to them to sign, if you like.'],
    'billing' => ['label' => 'Billing', 'title' => 'Billing', 'icon' => 'fa-file-invoice-dollar',
        'about' => 'Who gets the invoices, their tax, and the recurring invoice that bills the agreement.'],
    'it' => ['label' => 'IT basics', 'title' => 'IT basics', 'icon' => 'fa-network-wired',
        'about' => 'Their domains, to watch for renewals, and the vendors and licenses most of your clients have - from your templates.'],
    'kickoff' => ['label' => 'Kickoff', 'title' => 'Kickoff', 'icon' => 'fa-flag',
        'about' => 'The onboarding project - its tickets raised from a project template, for your team to work through.'],
];

/** Has the 3.5.2 update run? Until it has, nothing here is shown or done. */
function clientOnboardingReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.5.2', '>=');
    }
    return $ready;
}

/** May this agent onboard a client - the update run, and Clients at Modify, as adding one needs? */
function clientOnboardingCan() {
    return clientOnboardingReady() && lookupUserPermission('module_client') >= 2;
}

/** Where a step is - the wizard for a client (or a new one, with no client), at $step ('done' for the summary). */
function clientOnboardingUrl($client_id = 0, $step = '') {
    $query = array_filter(['client_id' => intval($client_id) ?: null, 'step' => $step !== '' ? $step : null]);
    return '/agent/client_onboarding.php' . ($query ? '?' . http_build_query($query) : '');
}

/** A client's onboarding - or null when it was never onboarded. */
function clientOnboardingRow($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    if (!$client_id || !clientOnboardingReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM client_onboardings WHERE onboarding_client_id = $client_id LIMIT 1")) ?: null;
}

/** Start onboarding a client - once; logged the first time. Its row. */
function clientOnboardingStart($client_id) {
    global $mysqli, $session_user_id, $session_name;
    $client_id = intval($client_id);
    mysqli_query($mysqli, "INSERT IGNORE INTO client_onboardings SET onboarding_client_id = $client_id, onboarding_started_by = " . intval($session_user_id));
    if (mysqli_affected_rows($mysqli) === 1) {
        logAudit("Client", "Onboard", "$session_name started onboarding client " . escapeSql((string) getFieldById('clients', $client_id, 'client_name')), $client_id, $client_id);
    }
    return clientOnboardingRow($client_id);
}

/** The steps this agent has, in order - those whose module is off, or their role can't do, left out. */
function clientOnboardingStepKeys() {
    global $config_module_enable_accounting, $config_module_enable_itdoc, $config_module_enable_ticketing;
    $keys = [];
    foreach (array_keys(CLIENT_ONBOARDING_STEPS) as $key) {
        if ($key === 'agreement' && !contractClientPickReady()) {
            continue;
        }
        if ($key === 'billing' && !intval($config_module_enable_accounting)) {
            continue;
        }
        if ($key === 'it' && !intval($config_module_enable_itdoc)) {
            continue;
        }
        if ($key === 'kickoff' && (!intval($config_module_enable_ticketing) || lookupUserPermission('module_support') < 2)) {
            continue;
        }
        $keys[] = $key;
    }
    return $keys;
}

/** A step list column, as keys. */
function clientOnboardingList($value) {
    return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
}

/**
 * The contract the Agreement step made, still there - else the client's most
 * recent one that is active or out to sign. Its row, or null.
 */
function clientOnboardingContract($client_id, $row = null) {
    global $mysqli;
    $client_id = intval($client_id);
    $row = $row ?? clientOnboardingRow($client_id);
    if (!contractsReady()) {
        return null;
    }
    $made = intval($row['onboarding_contract_id'] ?? 0);
    $id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT contract_id FROM contracts WHERE contract_client_id = $client_id AND contract_archived_at IS NULL
        AND (contract_id = $made OR contract_status IN ('Active', 'Draft'))
        ORDER BY contract_id = $made DESC, contract_status = 'Active' DESC, contract_id DESC LIMIT 1"))[0] ?? 0);
    return $id ? contractRow($id) : null;
}

/**
 * The steps for a client, in order, each with whether it is done (saved here,
 * or the client has what it is about), skipped and a line on where it stands
 * - key => CLIENT_ONBOARDING_STEPS' fields + done, skipped, saved, status.
 * With no client, the company step alone - the client is made there.
 */
function clientOnboardingSteps($client_id, $row = null) {
    global $mysqli;
    $client_id = intval($client_id);
    $row = $row ?? clientOnboardingRow($client_id);
    $saved = clientOnboardingList($row['onboarding_saved'] ?? '');
    $skipped = clientOnboardingList($row['onboarding_skipped'] ?? '');
    $client = $client_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name, client_type, client_lead, client_created_at FROM clients WHERE client_id = $client_id")) : null;
    $count = function ($sql) use ($mysqli) {
        $result = mysqli_query($mysqli, $sql);
        return $result ? intval(mysqli_fetch_row($result)[0] ?? 0) : 0;
    };
    $plural = function ($n, $one, $many = null) {
        return $n . ' ' . ($n === 1 ? $one : ($many ?? $one . 's'));
    };

    $steps = [];
    foreach (clientOnboardingStepKeys() as $key) {
        if (!$client && $key !== 'company') {
            continue;
        }
        $done = in_array($key, $saved, true);
        $status = '';
        switch ($key) {
            case 'company':
                $done = $client && intval($client['client_lead']) === 0;
                $status = !$client ? 'Not added yet' : (intval($client['client_lead']) ? 'A lead - saving makes them a client'
                    : (trim((string) $client['client_type']) !== '' ? $client['client_type'] . ', a' : 'A') . ' client since ' . date('M j, Y', strtotime($client['client_created_at'])));
                break;
            case 'locations':
                $n = $count("SELECT COUNT(*) FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL");
                $primary = $n ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT location_name, location_address, location_city FROM locations
                    WHERE location_client_id = $client_id AND location_archived_at IS NULL ORDER BY location_primary DESC, location_id ASC LIMIT 1")) : null;
                $done = $done || $n > 0;
                $where = $primary ? trim(implode(', ', array_filter([trim((string) $primary['location_address']), trim((string) $primary['location_city'])]))) : '';
                $where = $where !== '' ? $where : ($primary ? $primary['location_name'] : '');
                $status = $n ? $plural($n, 'location') . ($primary ? ' - ' . ($n > 1 ? "the primary at $where" : $where) : '') : 'None yet';
                break;
            case 'people':
                $n = $count("SELECT COUNT(*) FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL");
                $primary = $n ? (string) (mysqli_fetch_row(mysqli_query($mysqli, "SELECT contact_name FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND contact_primary = 1 LIMIT 1"))[0] ?? '') : '';
                $done = $done || $n > 0;
                $status = $n ? $plural($n, 'person', 'people') . ($primary !== '' ? " - $primary is the primary contact" : ' - no primary contact yet') : 'None yet';
                break;
            case 'agreement':
                $contract = clientOnboardingContract($client_id, $row);
                $done = $done || ($contract !== null);
                if ($contract) {
                    $pending = contractSignaturesReady() && contractSignaturePendingFor(intval($contract['contract_id']));
                    $status = $contract['contract_name'] . ' - ' . ($contract['contract_status'] === 'Draft' ? ($pending ? 'a draft, out to sign' : 'a draft')
                        : strtolower($contract['contract_status']) . (!empty($contract['contract_start_date']) ? ' from ' . date('M j, Y', strtotime($contract['contract_start_date'])) : ''));
                } else {
                    $status = 'No contract - billed on the defaults';
                }
                break;
            case 'billing':
                $recurring = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_amount,
                        recurring_invoice_frequency, recurring_invoice_currency_code, recurring_invoice_status
                    FROM recurring_invoices WHERE recurring_invoice_client_id = $client_id AND recurring_invoice_archived_at IS NULL
                    ORDER BY recurring_invoice_id = " . intval($row['onboarding_recurring_invoice_id'] ?? 0) . " DESC, recurring_invoice_status DESC, recurring_invoice_id DESC LIMIT 1"));
                $done = $done || $recurring !== null;
                $status = $recurring ? $recurring['recurring_invoice_prefix'] . $recurring['recurring_invoice_number'] . ' bills ' . billingMoney($recurring['recurring_invoice_amount'], $recurring['recurring_invoice_currency_code'])
                    . ' ' . strtolower(recurringInvoiceFrequencies()[$recurring['recurring_invoice_frequency']] ?? '') . (intval($recurring['recurring_invoice_status']) ? '' : ' - paused')
                    : ($done ? 'Billing details saved - no recurring invoice' : 'No recurring invoice');
                break;
            case 'it':
                $domains = $count("SELECT COUNT(*) FROM domains WHERE domain_client_id = $client_id AND domain_archived_at IS NULL");
                $vendors = $count("SELECT COUNT(*) FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL");
                $licenses = $count("SELECT COUNT(*) FROM software WHERE software_client_id = $client_id AND software_archived_at IS NULL");
                $done = $done || $vendors + $licenses > 0;
                $status = $domains + $vendors + $licenses ? implode(', ', array_filter([$domains ? $plural($domains, 'domain') : '', $vendors ? $plural($vendors, 'vendor') : '', $licenses ? $plural($licenses, 'license') : ''])) : 'Nothing yet';
                break;
            case 'kickoff':
                $project = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT project_id, project_prefix, project_number, project_name,
                        (SELECT COUNT(*) FROM tickets WHERE ticket_project_id = project_id) AS tickets
                    FROM projects WHERE project_client_id = $client_id AND project_archived_at IS NULL
                    ORDER BY project_id = " . intval($row['onboarding_project_id'] ?? 0) . " DESC, project_id DESC LIMIT 1"));
                $done = $done || $project !== null;
                $status = $project ? $project['project_prefix'] . $project['project_number'] . ' ' . $project['project_name'] . ' - ' . $plural(intval($project['tickets']), 'ticket') : 'No project yet';
                break;
        }
        $steps[$key] = CLIENT_ONBOARDING_STEPS[$key] + ['done' => $done, 'skipped' => !$done && in_array($key, $skipped, true), 'saved' => in_array($key, $saved, true), 'status' => $status];
    }
    return $steps;
}

/** How far it has got - done (or skipped), of how many, the next step to do ('' when none is left) and whether it is finished. */
function clientOnboardingProgress(array $steps) {
    $done = 0;
    $next = '';
    foreach ($steps as $key => $step) {
        if ($step['done'] || $step['skipped']) {
            $done++;
        } elseif ($next === '') {
            $next = $key;
        }
    }
    return ['done' => $done, 'total' => count($steps), 'next' => $next, 'finished' => $next === ''];
}

/** The step after this one, in order - 'done' (the summary) after the last. */
function clientOnboardingAfter($key, array $steps) {
    $keys = array_keys($steps);
    $at = array_search($key, $keys, true);
    return $at === false || !isset($keys[$at + 1]) ? 'done' : $keys[$at + 1];
}

/** Note a step saved, or put off ($kind saved or skipped) - or take that back with $on false. Saving one takes it out of skipped. */
function clientOnboardingMark($client_id, $key, $kind = 'saved', $on = true) {
    global $mysqli;
    $row = clientOnboardingRow($client_id) ?? clientOnboardingStart($client_id);
    if (!$row || !isset(CLIENT_ONBOARDING_STEPS[$key]) || !in_array($kind, ['saved', 'skipped'], true)) {
        return;
    }
    $lists = ['saved' => clientOnboardingList($row['onboarding_saved']), 'skipped' => clientOnboardingList($row['onboarding_skipped'])];
    $lists[$kind] = $on ? array_values(array_unique(array_merge($lists[$kind], [$key]))) : array_values(array_diff($lists[$kind], [$key]));
    if ($kind === 'saved' && $on) {
        $lists['skipped'] = array_values(array_diff($lists['skipped'], [$key]));
    }
    mysqli_query($mysqli, "UPDATE client_onboardings SET onboarding_saved = '" . escapeSql(implode(',', $lists['saved'])) . "',
        onboarding_skipped = '" . escapeSql(implode(',', $lists['skipped'])) . "' WHERE onboarding_id = " . intval($row['onboarding_id']));
}

/** Remember what a step made - contract, recurring_invoice or project, by id. */
function clientOnboardingMade($client_id, $what, $id) {
    global $mysqli;
    if (!in_array($what, ['contract', 'recurring_invoice', 'project'], true)) {
        return;
    }
    mysqli_query($mysqli, "UPDATE client_onboardings SET onboarding_{$what}_id = " . intval($id) . " WHERE onboarding_client_id = " . intval($client_id));
}

/** Finish - from the summary. The overview stops showing it; the wizard is still there from the client's menu. */
function clientOnboardingFinish($client_id) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE client_onboardings SET onboarding_finished_at = IFNULL(onboarding_finished_at, NOW()) WHERE onboarding_client_id = " . intval($client_id));
    return mysqli_affected_rows($mysqli) === 1;
}

/** Stop showing it on the client's overview. */
function clientOnboardingDismiss($client_id) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE client_onboardings SET onboarding_dismissed_at = NOW() WHERE onboarding_client_id = " . intval($client_id) . " AND onboarding_dismissed_at IS NULL");
}

/**
 * What the client overview's card shows - ['steps', 'progress'] while the
 * client is being onboarded: begun, not finished or closed, and a step still
 * to do. Null otherwise, and for an agent who can't onboard.
 */
function clientOnboardingCard($client_id) {
    if (!clientOnboardingCan()) {
        return null;
    }
    $row = clientOnboardingRow($client_id);
    if (!$row || $row['onboarding_finished_at'] || $row['onboarding_dismissed_at']) {
        return null;
    }
    $steps = clientOnboardingSteps($client_id, $row);
    $progress = clientOnboardingProgress($steps);
    return $progress['finished'] ? null : ['steps' => $steps, 'progress' => $progress];
}

/** Where a client's onboarding stands, for its menu - none (never begun), open, or finished (finished, closed, or every step done or skipped). */
function clientOnboardingState($client_id) {
    $row = clientOnboardingRow($client_id);
    if (!$row) {
        return 'none';
    }
    if ($row['onboarding_finished_at'] || $row['onboarding_dismissed_at'] || clientOnboardingProgress(clientOnboardingSteps($client_id, $row))['finished']) {
        return 'finished';
    }
    return 'open';
}
