<?php

/*
 * What a contract sets while it is in force, and the recurring invoice that
 * bills it.
 *
 * A client's hourly rate, after-hours rate, net terms and SLAs come from its
 * contract - a client has none of its own (from database 3.5.0). In force:
 * an active, ended or cancelled contract, from the day it starts to its last
 * day - and an active one that renews itself after that too, until the
 * nightly job renews it. While a contract is in force:
 *  - a new ticket of the client's gets the contract's SLA for its priority
 *    (getTicketSlaId() in functions/sla.php asks contractSlaFor() first);
 *  - time bills at the contract's rate - its after-hours rate for a ticket
 *    opened outside the business days and hours in Admin > Settings (the
 *    ones SLAs use) - going by the contract in force on the day the work was
 *    done (contractTicketRate(), used by billingUnbilledTickets());
 *  - a new invoice is due on the contract's net terms (contractNetTerms()).
 * Whatever no contract in force sets, the instance's default applies - the
 * rates and net terms in Admin > Settings > Defaults (billingDefaults()),
 * the SLAs in Admin > SLAs. When two are in force, the one that started
 * last is asked first.
 *
 * A contract can be linked to one of the client's recurring invoices
 * (contract_recurring_invoice_id, from database 3.4.5), or make one. It is
 * paused while the contract is a draft, runs while it is active (or
 * cancelled and still in force), and is paused once the contract ends -
 * once: contract_recurring_stopped_at notes it, so one started again by
 * hand is left alone. agent/contract.php shows it, with the Recurring
 * invoice window (agent/modals/contract/contract_recurring.php) and the
 * handlers in agent/post/contract.php.
 */

/** Whether the 3.4.5 update has run - a contract can be linked to a recurring invoice. */
function contractBillingReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.4.5', '>=');
    }
    return $ready;
}

/* ---------------------------------------------------------------- in force */

/*
 * A client's contracts that are, were or will be in force - not drafts, not
 * archived, with a start date - the latest to start first. Kept for the
 * request; $refresh reads them again.
 */
function contractTermsContracts($client_id, $refresh = false) {
    global $mysqli;
    static $cache = [];
    $client_id = intval($client_id);
    if ($client_id <= 0 || !contractsReady()) {
        return [];
    }
    if ($refresh || !isset($cache[$client_id])) {
        $parts = implode(', ', array_map(function ($part) { return "contract_sla_{$part}_id"; }, CONTRACT_SLA_PRIORITIES));
        $rows = [];
        $sql = mysqli_query($mysqli, "SELECT contract_id, contract_name, contract_status, contract_start_date, contract_end_date, contract_auto_renew,
                contract_rate_standard, contract_rate_after_hours, contract_net_terms, $parts
            FROM contracts WHERE contract_client_id = $client_id AND contract_status IN ('Active', 'Ended', 'Cancelled') AND contract_archived_at IS NULL
                AND contract_start_date IS NOT NULL
            ORDER BY contract_start_date DESC, contract_id DESC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $rows[] = $row;
        }
        $cache[$client_id] = $rows;
    }
    return $cache[$client_id];
}

/** Whether a contract is in force on a day ('Y-m-d'). */
function contractInForceOn(array $row, $date) {
    $start = (string) ($row['contract_start_date'] ?? '');
    $end = (string) ($row['contract_end_date'] ?? '');
    if (!in_array($row['contract_status'] ?? '', ['Active', 'Ended', 'Cancelled'], true) || $start === '' || $start > $date) {
        return false;
    }
    if ($end === '' || $end >= $date) {
        return true;
    }
    // Past its last day - an active one that renews itself is only waiting for tonight's renewal
    return $row['contract_status'] === 'Active' && intval($row['contract_auto_renew']) === 1;
}

/*
 * The contract in force for a client on a day that sets $what - 'rate',
 * 'net', an SLA priority part ('low', 'medium', 'high', 'urgent'), or ''
 * for any - the one that started last. Null when none does.
 */
function contractTermsOn($client_id, $date, $what = '') {
    $date = $date ?: date('Y-m-d');
    foreach (contractTermsContracts($client_id) as $row) {
        if (!contractInForceOn($row, $date)) {
            continue;
        }
        if ($what === 'rate' && floatval($row['contract_rate_standard']) <= 0 && floatval($row['contract_rate_after_hours']) <= 0) {
            continue;
        }
        if ($what === 'net' && $row['contract_net_terms'] === null) {
            continue;
        }
        if (in_array($what, CONTRACT_SLA_PRIORITIES, true) && !intval($row["contract_sla_{$what}_id"])) {
            continue;
        }
        return $row;
    }
    return null;
}

/*
 * What applies to a contract's client for what the contract leaves empty, on
 * $date ('Y-m-d', today when not given): another contract in force for the
 * client that sets it - the one that started last first; a Standard terms
 * contract carries what a client had of its own before the 3.5.0 update -
 * else the default. Before that update, the client's own, as it was (and no
 * after-hours rate). Each as [value, where from - a contract's name, "the
 * client's" or '' for the default]: 'rate' and 'after_hours' (null for
 * none), 'net_terms' (days), 'slas' (priority => sla_id, 0 for none). For
 * the contract's page and its document.
 */
function contractUsualTerms(array $row, $date = null) {
    $date = $date ?: date('Y-m-d');
    $client_id = intval($row['contract_client_id'] ?? 0);
    $defaults = billingDefaults();
    $usual = [
        'rate' => [$defaults['rate'] > 0 ? $defaults['rate'] : null, ''],
        'after_hours' => [$defaults['after_hours'] > 0 ? $defaults['after_hours'] : null, ''],
        'net_terms' => [$defaults['net_terms'], ''],
        'slas' => [],
    ];
    $legacy = billingClientLegacyTerms($client_id);
    foreach (CONTRACT_SLA_PRIORITIES as $priority => $part) {
        // The default for the priority - or, before the update, the client's own
        $usual['slas'][$priority] = [intval(getTicketSlaId($client_id, $priority, false)), $legacy && array_key_exists($priority, $legacy['slas']) ? "the client's" : ''];
    }
    if ($legacy) {
        $usual['rate'] = [$legacy['rate'], "the client's"];
        $usual['after_hours'] = [null, ''];
        if ($legacy['net_terms'] !== null) {
            $usual['net_terms'] = [$legacy['net_terms'], "the client's"];
        }
        return $usual;
    }
    $live = contractSlaChoices();
    $found = [];
    foreach (contractTermsContracts($client_id) as $other) {
        if (intval($other['contract_id']) === intval($row['contract_id'] ?? 0) || !contractInForceOn($other, $date)) {
            continue;
        }
        $name = (string) $other['contract_name'];
        if (!isset($found['rate']) && floatval($other['contract_rate_standard']) > 0) {
            $usual['rate'] = [floatval($other['contract_rate_standard']), $name];
            $found['rate'] = true;
        }
        if (!isset($found['after_hours']) && floatval($other['contract_rate_after_hours']) > 0) {
            $usual['after_hours'] = [floatval($other['contract_rate_after_hours']), $name];
            $found['after_hours'] = true;
        }
        if (!isset($found['net_terms']) && $other['contract_net_terms'] !== null) {
            $usual['net_terms'] = [intval($other['contract_net_terms']), $name];
            $found['net_terms'] = true;
        }
        foreach (CONTRACT_SLA_PRIORITIES as $priority => $part) {
            $sla_id = intval($other["contract_sla_{$part}_id"]);
            // An archived SLA doesn't count, as for tickets
            if (!isset($found[$part]) && $sla_id && isset($live[$sla_id])) {
                $usual['slas'][$priority] = [$sla_id, $name];
                $found[$part] = true;
            }
        }
    }
    return $usual;
}

/*
 * contractInForceOn() as SQL, for the contract columns of $alias on $date
 * (today when not given) - for lists that pick clients by their contract.
 */
function contractInForceSql($alias = 'contracts', $date = null) {
    $date = escapeSql($date ?: date('Y-m-d'));
    return "$alias.contract_status IN ('Active', 'Ended', 'Cancelled') AND $alias.contract_archived_at IS NULL AND $alias.contract_start_date IS NOT NULL AND $alias.contract_start_date <= '$date'
        AND ($alias.contract_end_date IS NULL OR $alias.contract_end_date >= '$date' OR ($alias.contract_status = 'Active' AND $alias.contract_auto_renew = 1))";
}

/** The contracts in force for a client today - the latest to start first. */
function contractsInForce($client_id) {
    $today = date('Y-m-d');
    return array_values(array_filter(contractTermsContracts($client_id), function ($row) use ($today) {
        return contractInForceOn($row, $today);
    }));
}

/* ---------------------------------------------------------------- SLAs */

/*
 * The SLA a contract in force today sets for a priority of the client's
 * tickets - 0 when none does (the default applies). An archived SLA
 * doesn't count.
 */
function contractSlaFor($client_id, $priority, $date = null) {
    global $mysqli;
    $part = CONTRACT_SLA_PRIORITIES[(string) $priority] ?? null;
    if (!$part) {
        return 0;
    }
    foreach (contractTermsContracts($client_id) as $row) {
        $sla_id = intval($row["contract_sla_{$part}_id"]);
        if (!$sla_id || !contractInForceOn($row, $date ?? date('Y-m-d'))) {
            continue;
        }
        if (mysqli_num_rows(mysqli_query($mysqli, "SELECT sla_id FROM slas WHERE sla_id = $sla_id AND sla_archived_at IS NULL LIMIT 1"))) {
            return $sla_id;
        }
    }
    return 0;
}

/* ---------------------------------------------------------------- rates */

/*
 * Whether a moment is outside business hours - not a business day, a
 * closure day, or before or after the hours in Admin > Settings (the
 * calendar SLAs use). With no business calendar set up, nothing is.
 */
function contractAfterHours($datetime) {
    $settings = getSlaSettings();
    $ts = strtotime((string) $datetime);
    $days = $settings['business_days'];
    $start = (string) $settings['business_hours_start'];
    $end = (string) $settings['business_hours_end'];
    if (!$ts || empty($days) || $start === '' || $end === '' || $start >= $end) {
        return false;
    }
    $day = date('Y-m-d', $ts);
    if (!in_array(intval(date('N', $ts)), $days) || isset(getBusinessHolidays()[$day])) {
        return true;
    }
    return $ts < strtotime("$day $start") || $ts >= strtotime("$day $end");
}

/*
 * The hourly rate a ticket's time bills at, and where it comes from, going
 * by the contracts in force on the day the work was done (resolved, else
 * closed, else opened). A ticket opened outside business hours bills at an
 * after-hours rate - a contract's, else the default after-hours rate - when
 * there is one. Otherwise a contract's rate, else the default rate. Each is
 * taken on its own: a contract that sets only a rate leaves after-hours time
 * to the default after-hours rate. $ticket has ticket_client_id,
 * ticket_created_at, ticket_resolved_at, ticket_closed_at. [rate, source
 * (after_hours, contract, default_after_hours or default - or client, the
 * client's own before the 3.5.0 update), contract_id, contract_name, note -
 * in words].
 */
function contractTicketRate(array $ticket) {
    $done = substr((string) (($ticket['ticket_resolved_at'] ?? '') ?: (($ticket['ticket_closed_at'] ?? '') ?: ($ticket['ticket_created_at'] ?? ''))), 0, 10) ?: date('Y-m-d');
    $defaults = billingDefaults();
    $in_force = array_values(array_filter(contractTermsContracts($ticket['ticket_client_id'] ?? 0), function ($row) use ($done) {
        return contractInForceOn($row, $done);
    }));
    // A rate of nothing is no rate - the default applies
    $after_rates = array_filter($in_force, function ($row) {
        return floatval($row['contract_rate_after_hours']) > 0;
    });
    if (($after_rates || $defaults['after_hours'] > 0) && contractAfterHours($ticket['ticket_created_at'] ?? '')) {
        foreach ($after_rates as $row) {
            return ['rate' => floatval($row['contract_rate_after_hours']), 'source' => 'after_hours', 'contract_id' => intval($row['contract_id']), 'contract_name' => $row['contract_name'],
                'note' => contractPossessive($row['contract_name']) . " after-hours rate - opened outside business hours"];
        }
        return ['rate' => $defaults['after_hours'], 'source' => 'default_after_hours', 'contract_id' => 0, 'contract_name' => '',
            'note' => 'the default after-hours rate - opened outside business hours'];
    }
    foreach ($in_force as $row) {
        if (floatval($row['contract_rate_standard']) > 0) {
            return ['rate' => floatval($row['contract_rate_standard']), 'source' => 'contract', 'contract_id' => intval($row['contract_id']), 'contract_name' => $row['contract_name'],
                'note' => contractPossessive($row['contract_name']) . " rate"];
        }
    }
    // Before the 3.5.0 update, the client's own rate, as it was
    $legacy = billingClientLegacyTerms($ticket['ticket_client_id'] ?? 0);
    if ($legacy && $legacy['rate'] !== null) {
        return ['rate' => $legacy['rate'], 'source' => 'client', 'contract_id' => 0, 'contract_name' => '', 'note' => "the client's rate"];
    }
    return ['rate' => $defaults['rate'], 'source' => 'default', 'contract_id' => 0, 'contract_name' => '', 'note' => 'the default rate'];
}

/*
 * A client's hourly rate today - the contract in force's, else the default -
 * for a price to start from. [rate, source, contract_id, contract_name, note].
 */
function contractClientRate($client_id) {
    return contractTicketRate(['ticket_client_id' => intval($client_id), 'ticket_resolved_at' => date('Y-m-d'),
        // A moment in business hours, so the standard rate is the one given
        'ticket_created_at' => '']);
}

/* ---------------------------------------------------------------- net terms */

/** The days a client's invoice dated $date is due after - the contract in force's net terms, else the default. */
function contractNetTerms($client_id, $date) {
    return contractNetTermsWhose($client_id, $date)[0];
}

/*
 * contractNetTerms() and whose terms they are, in words - [days, "Gold's",
 * or "the client's" before the 3.5.0 update, or 'the default'].
 */
function contractNetTermsWhose($client_id, $date) {
    $row = contractTermsOn($client_id, $date ?: date('Y-m-d'), 'net');
    if ($row) {
        return [intval($row['contract_net_terms']), contractPossessive($row['contract_name'])];
    }
    // Before the 3.5.0 update, the client's own, as it was
    $legacy = billingClientLegacyTerms($client_id);
    if ($legacy && $legacy['net_terms'] !== null) {
        return [$legacy['net_terms'], "the client's"];
    }
    return [billingDefaults()['net_terms'], 'the default'];
}

/*
 * A client's terms today, each with where it comes from - for the pages
 * that show them: ['rate' => contractClientRate(), 'after_hours' => [rate,
 * contract name or ''] (rate 0: none, the hourly rate applies), 'net_terms'
 * => [days, contract name or ''], 'slas' => [priority => [sla_id, contract
 * name or '']]].
 */
function contractClientTerms($client_id) {
    $client_id = intval($client_id);
    $today = date('Y-m-d');
    $defaults = billingDefaults();
    $after = [$defaults['after_hours'], ''];
    foreach (contractsInForce($client_id) as $row) {
        if (floatval($row['contract_rate_after_hours']) > 0) {
            $after = [floatval($row['contract_rate_after_hours']), $row['contract_name']];
            break;
        }
    }
    $net = contractTermsOn($client_id, $today, 'net');
    $slas = [];
    foreach (CONTRACT_SLA_PRIORITIES as $priority => $part) {
        $sla_id = contractSlaFor($client_id, $priority);
        $from = '';
        if ($sla_id) {
            $from = (string) (contractTermsOn($client_id, $today, $part)['contract_name'] ?? '');
        } else {
            $sla_id = intval(getTicketSlaId($client_id, $priority, false));
        }
        $slas[$priority] = [$sla_id, $from];
    }
    return [
        'rate' => contractClientRate($client_id),
        'after_hours' => $after,
        'net_terms' => $net ? [intval($net['contract_net_terms']), $net['contract_name']] : [contractNetTerms($client_id, $today), ''],
        'slas' => $slas,
    ];
}

/*
 * Every client with a contract in force today that sets net terms - client
 * id => [terms, contract name] - for windows that pick the client and say
 * when an invoice is due.
 */
function contractNetTermsToday() {
    global $mysqli;
    $terms = [];
    if (!contractsReady()) {
        return $terms;
    }
    $today = date('Y-m-d');
    $sql = mysqli_query($mysqli, "SELECT contract_client_id, contract_net_terms, contract_name FROM contracts
        WHERE contract_status IN ('Active', 'Ended', 'Cancelled') AND contract_archived_at IS NULL AND contract_net_terms IS NOT NULL
            AND contract_start_date IS NOT NULL AND contract_start_date <= '$today'
            AND (contract_end_date IS NULL OR contract_end_date >= '$today' OR (contract_status = 'Active' AND contract_auto_renew = 1))
        ORDER BY contract_start_date DESC, contract_id DESC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $terms[intval($row['contract_client_id'])] = $terms[intval($row['contract_client_id'])] ?? [intval($row['contract_net_terms']), $row['contract_name']];
    }
    return $terms;
}

/* ---------------------------------------------------------------- the recurring invoice */

/** The recurring invoice a contract is linked to - with its client - or null. */
function contractRecurringRow(array $row) {
    if (!contractBillingReady() || !intval($row['contract_recurring_invoice_id'] ?? 0)) {
        return null;
    }
    return billingRow('recurring', intval($row['contract_recurring_invoice_id']), false) ?: null;
}

/** The contract a recurring invoice is linked to - id and name - or null. */
function contractForRecurring($recurring_invoice_id) {
    global $mysqli;
    if (!contractBillingReady() || !intval($recurring_invoice_id)) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contract_id, contract_name, contract_status, contract_start_date, contract_end_date, contract_auto_renew, contract_client_id
        FROM contracts WHERE contract_recurring_invoice_id = " . intval($recurring_invoice_id) . " AND contract_archived_at IS NULL ORDER BY contract_id DESC LIMIT 1")) ?: null;
}

/** The client's recurring invoices a contract can be linked to - not archived, not another contract's. */
function contractRecurringChoices($client_id, $contract_id) {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id, recurring_invoice_prefix, recurring_invoice_number, recurring_invoice_scope, recurring_invoice_frequency, recurring_invoice_amount,
            recurring_invoice_status, recurring_invoice_next_date
        FROM recurring_invoices WHERE recurring_invoice_client_id = " . intval($client_id) . " AND recurring_invoice_archived_at IS NULL
            AND NOT EXISTS (SELECT 1 FROM contracts WHERE contract_recurring_invoice_id = recurring_invoice_id AND contract_id <> " . intval($contract_id) . " AND contract_archived_at IS NULL)
        ORDER BY recurring_invoice_status DESC, recurring_invoice_number ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['recurring_invoice_id'])] = $row;
    }
    return $rows;
}

/** Whether a contract's recurring invoice should be running now - active, or cancelled and still in force. */
function contractRecurringShouldRun(array $row, $today = null) {
    $today = $today ?? date('Y-m-d');
    $status = (string) $row['contract_status'];
    return $status === 'Active' || ($status === 'Cancelled' && !empty($row['contract_end_date']) && $row['contract_end_date'] >= $today);
}

/** A recurring invoice's amount and how often - "$1,500.00 a month". */
function contractRecurringAmountText(array $recurring) {
    return billingMoney($recurring['recurring_invoice_amount'], $recurring['recurring_invoice_currency_code'] ?? '') . ' a ' . recurringInvoicePeriodWord($recurring['recurring_invoice_frequency']);
}

/*
 * Start a contract's recurring invoice - its next run moved on to the first
 * that hasn't passed, so missed runs aren't billed. Whether it changed.
 */
function contractRecurringStart(array $row, $by) {
    global $mysqli;
    $recurring = contractRecurringRow($row);
    mysqli_query($mysqli, "UPDATE contracts SET contract_recurring_stopped_at = NULL WHERE contract_id = " . intval($row['contract_id']));
    if (!$recurring || !empty($recurring['recurring_invoice_archived_at']) || intval($recurring['recurring_invoice_status']) === 1) {
        return false;
    }
    $id = intval($recurring['recurring_invoice_id']);
    $next = (string) $recurring['recurring_invoice_next_date'];
    $moved = $next < date('Y-m-d') ? recurringInvoiceCatchUp($recurring) : $next;
    mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_status = 1, recurring_invoice_next_date = '" . escapeSql($moved) . "' WHERE recurring_invoice_id = $id");
    billingLog('recurring', $id, 'Active', "Started with contract {$row['contract_name']} by $by" . ($moved !== $next ? ' - the next run moved on to ' . date('M j, Y', strtotime($moved)) : ''));
    logAudit('Recurring Invoice', 'Edit', escapeSql("$by started recurring invoice " . billingNumber('recurring', $recurring) . " with contract {$row['contract_name']}"), intval($row['contract_client_id']), $id);
    return true;
}

/* Pause a contract's recurring invoice - $why, for its history. Whether it changed. */
function contractRecurringStop(array $row, $by, $why) {
    global $mysqli;
    $recurring = contractRecurringRow($row);
    mysqli_query($mysqli, "UPDATE contracts SET contract_recurring_stopped_at = NOW() WHERE contract_id = " . intval($row['contract_id']));
    if (!$recurring || intval($recurring['recurring_invoice_status']) !== 1) {
        return false;
    }
    $id = intval($recurring['recurring_invoice_id']);
    mysqli_query($mysqli, "UPDATE recurring_invoices SET recurring_invoice_status = 0 WHERE recurring_invoice_id = $id");
    billingLog('recurring', $id, 'Paused', "Paused with contract {$row['contract_name']} by $by - $why");
    logAudit('Recurring Invoice', 'Edit', escapeSql("$by paused recurring invoice " . billingNumber('recurring', $recurring) . " with contract {$row['contract_name']} - $why"), intval($row['contract_client_id']), $id);
    return true;
}

/*
 * Make a contract's recurring invoice match where the contract stands -
 * running while it is active (or cancelled and still in force), paused
 * while it is a draft or once it has ended. 'started', 'stopped' or ''.
 */
function contractRecurringFollow($contract_id, $by, $today = null) {
    $row = contractRow($contract_id);
    if (!$row || !contractRecurringRow($row)) {
        return '';
    }
    if (contractRecurringShouldRun($row, $today)) {
        return contractRecurringStart($row, $by) ? 'started' : '';
    }
    $why = $row['contract_status'] === 'Draft' ? 'it is a draft' : ($row['contract_status'] === 'Cancelled' ? 'it was cancelled' : 'it ended');
    return contractRecurringStop($row, $by, $why) ? 'stopped' : '';
}

/* Link a contract to one of the client's recurring invoices, and have it follow the contract. 'started', 'stopped' or ''. */
function contractRecurringLink(array $row, $recurring_invoice_id, $by) {
    global $mysqli;
    mysqli_query($mysqli, "UPDATE contracts SET contract_recurring_invoice_id = " . intval($recurring_invoice_id) . ", contract_recurring_stopped_at = NULL WHERE contract_id = " . intval($row['contract_id']));
    $recurring = billingRow('recurring', intval($recurring_invoice_id), false);
    if ($recurring) {
        billingLog('recurring', intval($recurring_invoice_id), intval($recurring['recurring_invoice_status']) ? 'Active' : 'Paused', "Linked to contract {$row['contract_name']} by $by - it starts and stops with it");
    }
    return contractRecurringFollow(intval($row['contract_id']), $by);
}

/* Unlink it - the recurring invoice stays as it is. */
function contractRecurringUnlink(array $row, $by) {
    global $mysqli;
    $recurring = contractRecurringRow($row);
    mysqli_query($mysqli, "UPDATE contracts SET contract_recurring_invoice_id = 0, contract_recurring_stopped_at = NULL WHERE contract_id = " . intval($row['contract_id']));
    if ($recurring) {
        billingLog('recurring', intval($recurring['recurring_invoice_id']), intval($recurring['recurring_invoice_status']) ? 'Active' : 'Paused', "Unlinked from contract {$row['contract_name']} by $by");
    }
}

/*
 * A new recurring invoice from the Recurring invoice window - [fields,
 * error]: the amount each time, how often, the first invoice's date, the
 * line, the category, the tax and whether it is emailed by itself.
 */
function contractRecurringFromInput(array $in) {
    $amount = trim((string) ($in['amount'] ?? '')) === '' ? null : billingNumberInput($in['amount']);
    $start = trim((string) ($in['start'] ?? ''));
    $parsed = DateTime::createFromFormat('!Y-m-d', $start);
    $f = [
        'amount' => $amount === null ? 0.0 : round($amount, 2),
        'frequency' => validateRecurringFrequency((string) ($in['frequency'] ?? 'month')),
        'start' => $parsed && $parsed->format('Y-m-d') === $start ? $start : '',
        'item_name' => mb_substr(trim(strip_tags((string) ($in['item_name'] ?? ''))), 0, 200),
        'description' => mb_substr(trim(strip_tags((string) ($in['description'] ?? ''))), 0, 1000),
        'category_id' => max(0, intval($in['category'] ?? 0)),
        'tax_id' => intval($in['tax_id'] ?? 0),
        'auto_send' => !empty($in['auto_send']) ? 1 : 0,
    ];
    if ($amount === null || $f['amount'] <= 0 || $f['amount'] >= 1e9) {
        return [$f, 'Give the amount each time - more than nothing, such as 1,500.00.'];
    }
    if ($f['start'] === '') {
        return [$f, 'Give the day of the first invoice.'];
    }
    if ($f['item_name'] === '') {
        return [$f, 'Give the line a name.'];
    }
    return [$f, ''];
}

/* A first invoice date to suggest - the day the contract starts when that is still to come, else the first of next month. */
function contractRecurringFirstDate(array $row) {
    $start = (string) ($row['contract_start_date'] ?? '');
    return $start !== '' && $start > date('Y-m-d') ? $start : date('Y-m-d', strtotime('first day of next month'));
}

/*
 * Make a recurring invoice for a contract - one line, in the company's
 * currency, its scope the contract's name - and link it. Running only
 * while the contract is. [id, number, state ('started', 'stopped' or '')].
 */
function contractRecurringCreate(array $row, array $f, $by) {
    global $mysqli, $config_recurring_invoice_prefix, $session_company_currency;
    $client_id = intval($row['contract_client_id']);
    $number = billingNextNumber('recurring');
    $prefix = escapeSql($config_recurring_invoice_prefix);
    $scope = escapeSql(mb_substr((string) $row['contract_name'], 0, 255));
    $currency = escapeSql($session_company_currency);
    $start = escapeSql($f['start']);
    $status = contractRecurringShouldRun($row) ? 1 : 0;
    mysqli_query($mysqli, "INSERT INTO recurring_invoices SET recurring_invoice_prefix = '$prefix', recurring_invoice_number = $number, recurring_invoice_scope = '$scope',
        recurring_invoice_frequency = '" . escapeSql($f['frequency']) . "', recurring_invoice_next_date = '$start', recurring_invoice_anchor_day = DAY('$start'), recurring_invoice_status = $status,
        recurring_invoice_amount = 0, recurring_invoice_currency_code = '$currency', recurring_invoice_auto_send = " . intval($f['auto_send']) . ", recurring_invoice_email_notify = 1,
        recurring_invoice_category_id = " . intval($f['category_id']) . ", recurring_invoice_client_id = $client_id");
    $recurring_id = intval(mysqli_insert_id($mysqli));
    billingItemSave('recurring', $recurring_id, ['name' => $f['item_name'], 'description' => $f['description'], 'qty' => 1, 'price' => $f['amount'], 'tax_id' => taxChoice($f['tax_id'], $client_id, 'goods')]);
    billingRecalc('recurring', $recurring_id);
    $ref = billingNumber('recurring', billingRow('recurring', $recurring_id, false));
    billingLog('recurring', $recurring_id, $status ? 'Active' : 'Paused', "Made for contract {$row['contract_name']} by $by - first invoice on " . date('M j, Y', strtotime($f['start'])));
    logAudit('Recurring Invoice', 'Create', escapeSql("$by made recurring invoice $ref for contract {$row['contract_name']}"), $client_id, $recurring_id);
    mysqli_query($mysqli, "UPDATE contracts SET contract_recurring_invoice_id = $recurring_id, contract_recurring_stopped_at = " . ($status ? 'NULL' : 'NOW()') . " WHERE contract_id = " . intval($row['contract_id']));
    return [$recurring_id, $ref, $status ? 'started' : 'stopped'];
}

/*
 * The night's recurring invoices, from contractsNightly(): a contract that
 * has ended, or was cancelled and is past its last day, pauses its
 * recurring invoice - once. How many were paused.
 */
function contractRecurringNightly($today = null) {
    global $mysqli;
    if (!contractBillingReady()) {
        return 0;
    }
    $today = escapeSql($today ?? date('Y-m-d'));
    $stopped = 0;
    $sql = mysqli_query($mysqli, "SELECT contract_id FROM contracts WHERE contract_recurring_invoice_id > 0 AND contract_recurring_stopped_at IS NULL AND contract_archived_at IS NULL
        AND (contract_status = 'Ended' OR (contract_status = 'Cancelled' AND contract_end_date < '$today'))");
    while ($id = mysqli_fetch_row($sql)) {
        $stopped += contractRecurringFollow(intval($id[0]), 'ITFlow', $today) === 'stopped' ? 1 : 0;
        // Paused already or not, the contract is done with it
        mysqli_query($mysqli, "UPDATE contracts SET contract_recurring_stopped_at = NOW() WHERE contract_id = " . intval($id[0]) . " AND contract_recurring_stopped_at IS NULL");
    }
    return $stopped;
}
