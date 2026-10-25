<?php

/*
 * Prepaid hours on contracts - block hours bought up front, and hours a
 * contract includes each month - drawn down by the time on the client's
 * tickets.
 *
 * A block (contract_blocks, from database 3.4.4) is hours bought on a day,
 * maybe for a price and on an invoice, maybe running out on a day; a month's
 * included hours are a block made for that month, running out at its end.
 * Time is drawn the way it is billed - by the ticket, once it is billable and
 * resolved or closed, rounded by the rules in Admin > Settings > Invoice -
 * from the client's active contracts' blocks, the one running out first
 * first (contract_block_uses: a row per block a ticket drew from). A ticket
 * whose time is all covered leaves Unbilled Time; one that is partly covered
 * stays there with the rest, the overage, to bill as usual. A block covers
 * work done before it runs out.
 *
 * The nightly job adds each month's included hours and draws the tickets
 * done since; Draw now does it straight away, from the contract or from
 * Unbilled Time. A ticket can be put back until it is invoiced: its hours
 * return to the blocks and it is billed as usual, kept out of later draws
 * (a use of no hours marks it) until an agent draws it again. The owner is
 * told when hours run low, once, until more are added.
 *
 * agent/contract.php shows them, with Add hours (agent/modals/contract/
 * contract_hours_add.php) and the handlers in agent/post/contract.php;
 * functions/billing.php leaves out what is covered (billingUnbilledSql(),
 * billingUnbilledTickets()).
 */

/** Whether the 3.4.4 update has run - contract_blocks is there. */
function contractHoursReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.4.4', '>=');
    }
    return $ready;
}

/** Hours as "12.5 h", "1 h". */
function contractHoursText($hours) {
    return rtrim(rtrim(number_format(round(floatval($hours), 2), 2, '.', ''), '0'), '.') . ' h';
}

/** Hours in words - "12.5 hours", "1 hour". */
function contractHoursWords($hours) {
    $hours = round(floatval($hours), 2);
    return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.') . ($hours == 1 ? ' hour' : ' hours');
}

/*
 * A contract's blocks, the one running out first first - each with what was
 * drawn from it, what is left, and whether it has run out ($today).
 */
function contractBlocks($contract_id, $today = null) {
    global $mysqli;
    $today = $today ?? date('Y-m-d');
    $blocks = [];
    if (!contractHoursReady()) {
        return $blocks;
    }
    $sql = mysqli_query($mysqli, "SELECT contract_blocks.*, COALESCE(SUM(contract_block_use_hours), 0) AS used, COUNT(NULLIF(contract_block_use_hours, 0)) AS uses,
            invoice_prefix, invoice_number, invoice_status
        FROM contract_blocks
        LEFT JOIN contract_block_uses ON contract_block_use_block_id = contract_block_id
        LEFT JOIN invoices ON contract_block_invoice_id = invoice_id AND contract_block_invoice_id > 0
        WHERE contract_block_contract_id = " . intval($contract_id) . "
        GROUP BY contract_block_id
        ORDER BY contract_block_expires IS NULL, contract_block_expires ASC, contract_block_bought ASC, contract_block_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['used'] = round(floatval($row['used']), 2);
        $row['left'] = max(0.0, round(floatval($row['contract_block_hours']) - $row['used'], 2));
        $row['expired'] = !empty($row['contract_block_expires']) && $row['contract_block_expires'] < $today;
        $blocks[intval($row['contract_block_id'])] = $row;
    }
    return $blocks;
}

/*
 * Where a contract's prepaid hours stand - bought (in blocks still good),
 * used of those, left, left unused in blocks that ran out, the next day a
 * block with hours left runs out, and how many blocks. $blocks from
 * contractBlocks().
 */
function contractHoursSummary(array $blocks) {
    $s = ['bought' => 0.0, 'used' => 0.0, 'left' => 0.0, 'lapsed' => 0.0, 'next_expiry' => '', 'next_expiry_hours' => 0.0, 'blocks' => count($blocks)];
    foreach ($blocks as $block) {
        if ($block['expired']) {
            $s['lapsed'] += $block['left'];
            continue;
        }
        $s['bought'] += floatval($block['contract_block_hours']);
        $s['used'] += $block['used'];
        $s['left'] += $block['left'];
        if ($block['left'] > 0 && !empty($block['contract_block_expires']) && ($s['next_expiry'] === '' || $block['contract_block_expires'] < $s['next_expiry'])) {
            $s['next_expiry'] = (string) $block['contract_block_expires'];
            $s['next_expiry_hours'] = $block['left'];
        }
    }
    foreach (['bought', 'used', 'left', 'lapsed', 'next_expiry_hours'] as $key) {
        $s[$key] = round($s[$key], 2);
    }
    return $s;
}

/** Hours left (and prepaid) in blocks still good, for some contracts - id => [left, bought], for the ones with blocks. For lists. */
function contractHoursLeftFor(array $contract_ids, $today = null) {
    global $mysqli;
    $ids = array_filter(array_map('intval', $contract_ids));
    $left = [];
    if (!$ids || !contractHoursReady()) {
        return $left;
    }
    $today = escapeSql($today ?? date('Y-m-d'));
    $sql = mysqli_query($mysqli, "SELECT contract_block_contract_id, SUM(GREATEST(contract_block_hours - used, 0)) AS hours_left, SUM(contract_block_hours) AS bought
        FROM (SELECT contract_block_contract_id, contract_block_hours,
                COALESCE((SELECT SUM(contract_block_use_hours) FROM contract_block_uses WHERE contract_block_use_block_id = contract_block_id), 0) AS used
            FROM contract_blocks WHERE contract_block_contract_id IN (" . implode(',', $ids) . ") AND (contract_block_expires IS NULL OR contract_block_expires >= '$today')) AS blocks
        GROUP BY contract_block_contract_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $left[intval($row['contract_block_contract_id'])] = ['left' => round(floatval($row['hours_left']), 2), 'bought' => round(floatval($row['bought']), 2)];
    }
    return $left;
}

/** Whether a contract has prepaid hours to show - blocks, hours each month, or it is a block hours contract. */
function contractHasHours(array $row, array $blocks) {
    return $blocks || floatval($row['contract_monthly_hours'] ?? 0) > 0 || ($row['contract_type'] ?? '') === 'block';
}

/** The client's active contracts that have prepaid hours - id => row with its blocks and summary. */
function contractClientHours($client_id) {
    global $mysqli;
    $contracts = [];
    if (!contractHoursReady()) {
        return $contracts;
    }
    $sql = mysqli_query($mysqli, "SELECT * FROM contracts WHERE contract_client_id = " . intval($client_id) . " AND contract_status = 'Active' AND contract_archived_at IS NULL
        AND (contract_monthly_hours > 0 OR EXISTS (SELECT 1 FROM contract_blocks WHERE contract_block_contract_id = contract_id))
        ORDER BY contract_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['blocks'] = contractBlocks(intval($row['contract_id']));
        $row['hours'] = contractHoursSummary($row['blocks']);
        $contracts[intval($row['contract_id'])] = $row;
    }
    return $contracts;
}

/*
 * This month's included hours, when a contract has them and is in force -
 * a block from the first of the month (or the day it starts) to the end of
 * it (or its last day), made once. Whether one was made.
 */
function contractHoursEnsureMonthly(array $row, $today = null) {
    global $mysqli;
    $today = $today ?? date('Y-m-d');
    $hours = round(floatval($row['contract_monthly_hours'] ?? 0), 2);
    if (!contractHoursReady() || $hours <= 0 || ($row['contract_status'] ?? '') !== 'Active') {
        return false;
    }
    $start = (string) ($row['contract_start_date'] ?? '');
    $end = (string) ($row['contract_end_date'] ?? '');
    if (($start !== '' && $start > $today) || ($end !== '' && $end < $today)) {
        return false;
    }
    $month = substr($today, 0, 7);
    $from = max($month . '-01', $start !== '' ? $start : $month . '-01');
    $to = date('Y-m-t', strtotime($today));
    $to = $end !== '' && $end < $to ? $end : $to;
    $note = 'Included in ' . date('F Y', strtotime($today));
    mysqli_query($mysqli, "INSERT IGNORE INTO contract_blocks SET contract_block_contract_id = " . intval($row['contract_id']) . ", contract_block_hours = " . sprintf('%.2f', $hours) . ",
        contract_block_bought = '$from', contract_block_expires = '$to', contract_block_month = '$month', contract_block_note = '" . escapeSql($note) . "'");
    if (mysqli_affected_rows($mysqli) === 1) {
        contractHoursAlertReset(intval($row['contract_id']));
        return true;
    }
    return false;
}

/*
 * After a contract is saved or made active: this month's included hours
 * follow a change to the hours each month (never under what was used), and
 * are made when it is in force and has none yet.
 */
function contractHoursSync($contract_id) {
    global $mysqli;
    $row = contractRow($contract_id);
    if (!$row || !contractHoursReady()) {
        return;
    }
    $hours = round(floatval($row['contract_monthly_hours']), 2);
    if ($hours > 0) {
        mysqli_query($mysqli, "UPDATE contract_blocks SET contract_block_hours = GREATEST(" . sprintf('%.2f', $hours) . ",
                (SELECT COALESCE(SUM(contract_block_use_hours), 0) FROM contract_block_uses WHERE contract_block_use_block_id = contract_block_id))
            WHERE contract_block_contract_id = " . intval($contract_id) . " AND contract_block_month = '" . date('Y-m') . "'");
    }
    contractHoursEnsureMonthly($row);
    contractHoursAlertReset(intval($contract_id));
}

/** The prepaid hours a ticket drew - each with its block and contract. $kept_out: whether it was put back and is kept out of draws. */
function contractHoursTicketUses($ticket_id, &$kept_out = false) {
    global $mysqli;
    $uses = [];
    $kept_out = false;
    if (!contractHoursReady()) {
        return $uses;
    }
    $sql = mysqli_query($mysqli, "SELECT contract_block_uses.*, contract_block_note, contract_block_month, contract_block_bought, contract_block_expires, contract_id, contract_name, contract_client_id
        FROM contract_block_uses
        JOIN contract_blocks ON contract_block_use_block_id = contract_block_id
        JOIN contracts ON contract_block_contract_id = contract_id
        WHERE contract_block_use_ticket_id = " . intval($ticket_id) . " ORDER BY contract_block_use_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (floatval($row['contract_block_use_hours']) > 0) {
            $uses[] = $row;
        } else {
            $kept_out = true;
        }
    }
    return $uses;
}

/** The tickets a contract's blocks covered, newest first - each with the hours drawn. $limit 0 for all. */
function contractHoursUses($contract_id, $limit = 0) {
    global $mysqli;
    $rows = [];
    if (!contractHoursReady()) {
        return $rows;
    }
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_resolved_at, ticket_closed_at, ticket_invoice_id, ticket_client_id, ticket_contact_id,
            SUM(contract_block_use_hours) AS hours, MAX(contract_block_use_full) AS covered, MAX(contract_block_use_created_at) AS drawn_at
        FROM contract_block_uses
        JOIN contract_blocks ON contract_block_use_block_id = contract_block_id
        JOIN tickets ON contract_block_use_ticket_id = ticket_id
        WHERE contract_block_contract_id = " . intval($contract_id) . " AND contract_block_use_hours > 0
        GROUP BY ticket_id
        ORDER BY drawn_at DESC, ticket_id DESC" . ($limit ? ' LIMIT ' . intval($limit) : ''));
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['hours'] = round(floatval($row['hours']), 2);
        $rows[] = $row;
    }
    return $rows;
}

/*
 * Draw a client's done tickets from their prepaid hours - $ticket_ids, or
 * every one waiting in Unbilled Time. Oldest first, each from the blocks
 * that cover the day it was done (from the day bought to the day they run
 * out), the one running out first first. What a ticket needs beyond them
 * stays in Unbilled Time to bill. $by for the history. [drawn (references
 * => hours), hours drawn, overage (references => hours left to bill, for
 * tickets the hours ran out on), hours left, contracts].
 */
function contractHoursDraw($client_id, array $ticket_ids = [], $by = 'ITFlow', $user_id = 0) {
    global $mysqli;
    $client_id = intval($client_id);
    $result = ['drawn' => [], 'hours' => 0.0, 'overage' => [], 'left' => 0.0, 'contracts' => []];
    if (!contractHoursReady() || !$client_id) {
        return $result;
    }
    // One draw for a client at a time - the nightly job and an agent could start together
    $lock = "itflow_contract_hours_$client_id";
    if (!intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT GET_LOCK('$lock', 10)"))[0] ?? 0)) {
        return $result;
    }
    try {
        $contracts = contractClientHours($client_id);
        $made = false;
        foreach ($contracts as $contract) {
            $made = contractHoursEnsureMonthly($contract) || $made;
        }
        if ($made) {
            $contracts = contractClientHours($client_id);
        }
        contractHoursRecheck($client_id);

        // The blocks with hours left, the one running out first first, across the client's active contracts
        $blocks = [];
        foreach ($contracts as $contract) {
            foreach ($contract['blocks'] as $id => $block) {
                if ($block['left'] > 0) {
                    $blocks[$id] = $block + ['contract_name' => $contract['contract_name']];
                }
            }
        }
        uasort($blocks, function ($a, $b) {
            return [$a['contract_block_expires'] === null ? 1 : 0, (string) $a['contract_block_expires'], $a['contract_block_bought'], $a['contract_block_id']]
                <=> [$b['contract_block_expires'] === null ? 1 : 0, (string) $b['contract_block_expires'], $b['contract_block_bought'], $b['contract_block_id']];
        });
        $result['contracts'] = $contracts;
        if (!$blocks) {
            return $result;
        }

        // Tickets put back stay out - unless an agent draws them by name
        if ($ticket_ids) {
            mysqli_query($mysqli, "DELETE FROM contract_block_uses WHERE contract_block_use_hours = 0 AND contract_block_use_ticket_id IN (" . implode(',', array_map('intval', $ticket_ids)) . ")");
        }
        $kept_out = [];
        $sql = mysqli_query($mysqli, "SELECT DISTINCT contract_block_use_ticket_id FROM contract_block_uses JOIN tickets ON contract_block_use_ticket_id = ticket_id
            WHERE ticket_client_id = $client_id AND contract_block_use_hours = 0");
        while ($row = mysqli_fetch_row($sql)) {
            $kept_out[intval($row[0])] = true;
        }
        $tickets = array_diff_key(billingUnbilledTickets($client_id, $ticket_ids), $kept_out);
        uasort($tickets, function ($a, $b) {
            return [contractHoursDoneOn($a), $a['ticket_id']] <=> [contractHoursDoneOn($b), $b['ticket_id']];
        });
        $touched = [];
        foreach ($tickets as $ticket_id => $ticket) {
            $need = round(floatval($ticket['hours']), 2);
            if ($need <= 0) {
                continue;
            }
            $done = contractHoursDoneOn($ticket);
            $taken = [];
            foreach ($blocks as $id => $block) {
                if ($need <= 0) {
                    break;
                }
                if ($blocks[$id]['left'] <= 0 || $done < $block['contract_block_bought'] || (!empty($block['contract_block_expires']) && $done > $block['contract_block_expires'])) {
                    continue;
                }
                $take = round(min($need, $blocks[$id]['left']), 2);
                mysqli_query($mysqli, "INSERT INTO contract_block_uses SET contract_block_use_block_id = " . intval($id) . ", contract_block_use_ticket_id = " . intval($ticket_id) . ",
                    contract_block_use_hours = " . sprintf('%.2f', $take) . ", contract_block_use_created_by = " . intval($user_id));
                $taken[] = [intval(mysqli_insert_id($mysqli)), $block['contract_name'], $take];
                $blocks[$id]['left'] = round($blocks[$id]['left'] - $take, 2);
                $need = round($need - $take, 2);
                $touched[intval($block['contract_block_contract_id'])] = true;
            }
            if (!$taken) {
                // No hours that cover the day it was done - it is billed as usual
                continue;
            }
            $drawn = round(array_sum(array_column($taken, 2)), 2);
            $reference = $ticket['ticket_prefix'] . $ticket['ticket_number'];
            if ($need <= 0) {
                // All of it covered - it leaves Unbilled Time
                mysqli_query($mysqli, "UPDATE contract_block_uses SET contract_block_use_full = 1 WHERE contract_block_use_id = " . intval(end($taken)[0]));
            } else {
                $result['overage'][$reference] = $need;
            }
            $result['drawn'][$reference] = $drawn;
            $result['hours'] += $drawn;
            $names = implode(', ', array_unique(array_column($taken, 1)));
            logTicketHistory($ticket_id, escapeSql(contractHoursText($drawn) . " drawn from prepaid hours ($names) by $by" . ($need > 0 ? ' - ' . contractHoursText($need) . ' over, left to bill' : '')));
        }
        $result['hours'] = round($result['hours'], 2);
        $today = date('Y-m-d');
        foreach ($blocks as $block) {
            $result['left'] += empty($block['contract_block_expires']) || $block['contract_block_expires'] >= $today ? $block['left'] : 0;
        }
        $result['left'] = round($result['left'], 2);

        // Running low, or out - the owner hears once
        foreach (array_keys($touched) as $contract_id) {
            contractHoursAlertCheck($contract_id);
        }
        // The hours ran out part way through a ticket - the rest is to bill
        if ($result['overage']) {
            $count = count($result['overage']);
            appNotify("Contract", escapeSql(getFieldById('clients', $client_id, 'client_name') . " ran out of prepaid hours - " . contractHoursText(array_sum($result['overage']))
                . " on $count ticket" . ($count === 1 ? '' : 's') . " left to bill"), "/agent/unbilled_time.php?client_id=$client_id", $client_id);
        }
        return $result;
    } finally {
        mysqli_query($mysqli, "SELECT RELEASE_LOCK('$lock')");
    }
}

/** The day a ticket's work was done - resolved, else closed, else opened. */
function contractHoursDoneOn(array $ticket) {
    return substr((string) ($ticket['ticket_resolved_at'] ?: ($ticket['ticket_closed_at'] ?: $ticket['ticket_created_at'])), 0, 10);
}

/*
 * A ticket that was all covered and has had time added since (reopened and
 * worked on, say) needs the rest billed: it goes back to Unbilled Time,
 * keeping what it drew, for the next draw to take the rest. How many.
 */
function contractHoursRecheck($client_id) {
    global $mysqli;
    $rules = billingTimeRules();
    $back = 0;
    $sql = mysqli_query($mysqli, "SELECT ticket_id, SUM(contract_block_use_hours) AS drawn,
            COALESCE((SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies WHERE ticket_reply_ticket_id = ticket_id AND ticket_reply_archived_at IS NULL), 0) AS seconds
        FROM contract_block_uses
        JOIN tickets ON contract_block_use_ticket_id = ticket_id
        WHERE ticket_client_id = " . intval($client_id) . " AND ticket_invoice_id = 0 AND contract_block_use_hours > 0
        GROUP BY ticket_id
        HAVING MAX(contract_block_use_full) = 1");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (billingTimeHours(intval($row['seconds']), $rules) > round(floatval($row['drawn']), 2)) {
            mysqli_query($mysqli, "UPDATE contract_block_uses SET contract_block_use_full = 0 WHERE contract_block_use_ticket_id = " . intval($row['ticket_id']));
            $back++;
        }
    }
    return $back;
}

/*
 * Put a ticket's hours back - its draws undone, so it is in Unbilled Time
 * again with all its time, to bill as usual, and kept out of later draws
 * until it is drawn by name. Not once it is on an invoice. Hours put back,
 * or null.
 */
function contractHoursPutBack($ticket_id, $by, $user_id = 0) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $uses = contractHoursTicketUses($ticket_id);
    if (!$uses || intval(getFieldById('tickets', $ticket_id, 'ticket_invoice_id'))) {
        return null;
    }
    $hours = round(array_sum(array_column($uses, 'contract_block_use_hours')), 2);
    mysqli_query($mysqli, "DELETE FROM contract_block_uses WHERE contract_block_use_ticket_id = $ticket_id");
    // A use of no hours keeps it out of the nightly draw
    mysqli_query($mysqli, "INSERT INTO contract_block_uses SET contract_block_use_block_id = " . intval($uses[0]['contract_block_use_block_id']) . ", contract_block_use_ticket_id = $ticket_id,
        contract_block_use_hours = 0, contract_block_use_created_by = " . intval($user_id));
    logTicketHistory($ticket_id, escapeSql(contractHoursText($hours) . " put back on the prepaid hours by $by - the ticket is billed as usual, in Unbilled Time"));
    foreach (array_unique(array_column($uses, 'contract_id')) as $contract_id) {
        contractHoursAlertReset(intval($contract_id));
    }
    return $hours;
}

/* After hours are added or put back: told again next time they run low, once they are no longer low. */
function contractHoursAlertReset($contract_id) {
    global $mysqli;
    $row = contractRow($contract_id);
    if (!$row || empty($row['contract_hours_alerted_at'])) {
        return;
    }
    $left = contractHoursSummary(contractBlocks($contract_id))['left'];
    if ($left > 0 && $left >= floatval($row['contract_hours_alert'])) {
        mysqli_query($mysqli, "UPDATE contracts SET contract_hours_alerted_at = NULL WHERE contract_id = " . intval($contract_id));
    }
}

/*
 * Running low: once a contract's hours left fall under its alert level, its
 * owner is emailed and the agents told - once, until more are added. Whether
 * they were told now.
 */
function contractHoursAlertCheck($contract_id) {
    global $mysqli;
    $row = contractRow($contract_id);
    $alert = $row ? floatval($row['contract_hours_alert']) : 0;
    if (!$row || $alert <= 0 || !empty($row['contract_hours_alerted_at'])) {
        return false;
    }
    $hours = contractHoursSummary(contractBlocks($contract_id));
    if ($hours['left'] >= $alert) {
        return false;
    }
    mysqli_query($mysqli, "UPDATE contracts SET contract_hours_alerted_at = NOW() WHERE contract_id = " . intval($contract_id) . " AND contract_hours_alerted_at IS NULL");
    if (mysqli_affected_rows($mysqli) !== 1) {
        return false;
    }
    contractHoursAlertSend($row, $hours);
    return true;
}

/** Tell the owner - and the agents, in ITFlow - that a contract's prepaid hours are running low. */
function contractHoursAlertSend(array $row, array $hours) {
    global $config_base_url;
    $client_id = intval($row['contract_client_id']);
    $left = $hours['left'] > 0 ? contractHoursWords($hours['left']) . ' left' : 'no hours left';
    appNotify("Contract", escapeSql("{$row['client_name']} has $left on {$row['contract_name']}"), contractAgentPath($row), $client_id, intval($row['contract_id']));
    if (empty($row['owner_email'])) {
        return;
    }
    emailQueue([['email' => $row['owner_email'], 'name' => $row['owner_name'], 'footnote' => 'You look after this contract in ITFlow.']],
        "Prepaid hours running low: {$row['contract_name']} - {$row['client_name']}", [
            'kind' => 'Contract', 'tone' => 'warning',
            'title' => "{$row['client_name']} has $left",
            'intro' => [$hours['left'] > 0
                ? "The prepaid hours on {$row['contract_name']} are down to " . contractHoursWords($hours['left']) . ", under the " . contractHoursWords($row['contract_hours_alert']) . " you set to be told at. Time beyond them goes to Unbilled Time to bill as usual - a good time to offer the client more."
                : "The prepaid hours on {$row['contract_name']} are used up. Time beyond them goes to Unbilled Time to bill as usual - a good time to offer the client more."],
            'summary' => array_filter([
                'Client' => (string) $row['client_name'],
                'Prepaid' => contractHoursWords($hours['bought']),
                'Used' => contractHoursWords($hours['used']),
                'Left' => contractHoursWords($hours['left']),
                'Next to run out' => $hours['next_expiry'] !== '' ? contractHoursWords($hours['next_expiry_hours']) . ' on ' . date('M j, Y', strtotime($hours['next_expiry'])) : '',
            ]),
            'button' => ['label' => 'Open the contract', 'url' => "https://$config_base_url" . contractAgentPath($row)],
        ]);
}

/*
 * A block of hours from the Add hours window - [fields, error]: hours, the
 * price (null: none), the day bought, the day it runs out ('' never), a
 * note, and whether to invoice it (in which category).
 */
function contractHoursFromInput(array $in) {
    $date = function ($key) use ($in) {
        $value = trim((string) ($in[$key] ?? ''));
        $parsed = DateTime::createFromFormat('!Y-m-d', $value);
        return $value === '' ? '' : ($parsed && $parsed->format('Y-m-d') === $value ? $value : false);
    };
    $hours = billingNumberInput(trim((string) ($in['hours'] ?? '')));
    $price = trim((string) ($in['price'] ?? '')) === '' ? null : billingNumberInput($in['price']);
    $f = [
        'hours' => $hours === null ? 0 : round($hours, 2),
        'price' => $price === null ? null : round($price, 2),
        'bought' => $date('bought'),
        'expires' => $date('expires'),
        'note' => mb_substr(trim(strip_tags((string) ($in['note'] ?? ''))), 0, 255),
        'invoice' => !empty($in['invoice']),
        'category_id' => max(0, intval($in['category'] ?? 0)),
    ];
    if ($hours === null || $f['hours'] <= 0 || $f['hours'] >= 10000) {
        return [$f, 'Give the hours - more than none, under 10,000.'];
    }
    if (trim((string) ($in['price'] ?? '')) !== '' && ($price === null || $price < 0 || $price >= 1e9)) {
        return [$f, 'The price is an amount, such as 2,400.00 - or leave it empty.'];
    }
    if ($f['bought'] === false || $f['bought'] === '') {
        return [$f, 'Give the day they were bought.'];
    }
    if ($f['expires'] === false || ($f['expires'] !== '' && $f['expires'] < $f['bought'])) {
        return [$f, 'The day they run out is a day after they were bought - or leave it empty for never.'];
    }
    if ($f['invoice'] && !$f['price']) {
        return [$f, 'Give a price to invoice them.'];
    }
    return [$f, ''];
}

/*
 * Add a block of hours to a contract - and, with $f['invoice'], a draft
 * invoice for it, one line. [block id, invoice id (0 for none), invoice
 * number].
 */
function contractHoursAdd(array $row, array $f, $user_id, $by) {
    global $mysqli;
    $contract_id = intval($row['contract_id']);
    $invoice_id = 0;
    $number = '';
    if ($f['invoice']) {
        $invoice_id = billingInvoiceCreate(intval($row['contract_client_id']), date('Y-m-d'), $f['category_id'] ?? 0, 'Prepaid hours - ' . $row['contract_name'], $by);
        if ($invoice_id) {
            billingItemSave('invoice', $invoice_id, ['name' => 'Prepaid support hours', 'description' => contractHoursWords($f['hours']) . ' for ' . $row['contract_name']
                . ($f['expires'] !== '' ? ', good until ' . date('M j, Y', strtotime($f['expires'])) : ''), 'qty' => 1, 'price' => $f['price'], 'tax_id' => taxChoice(TAX_USUAL, intval($row['contract_client_id']), 'time')]);
            billingRecalc('invoice', $invoice_id);
            $number = billingNumber('invoice', billingRow('invoice', $invoice_id, false));
        }
    }
    mysqli_query($mysqli, "INSERT INTO contract_blocks SET contract_block_contract_id = $contract_id, contract_block_hours = " . sprintf('%.2f', $f['hours']) . ",
        contract_block_price = " . ($f['price'] === null ? 'NULL' : sprintf('%.2f', $f['price'])) . ", contract_block_bought = '" . escapeSql($f['bought']) . "',
        contract_block_expires = " . ($f['expires'] !== '' ? "'" . escapeSql($f['expires']) . "'" : 'NULL') . ", contract_block_invoice_id = $invoice_id,
        contract_block_note = " . ($f['note'] !== '' ? "'" . escapeSql($f['note']) . "'" : 'NULL') . ", contract_block_created_by = " . intval($user_id));
    $block_id = intval(mysqli_insert_id($mysqli));
    contractHoursAlertReset($contract_id);
    return [$block_id, $invoice_id, $number];
}

/** Delete a block nothing was drawn from. Whether it went. */
function contractHoursBlockDelete($block_id) {
    global $mysqli;
    $block_id = intval($block_id);
    mysqli_query($mysqli, "DELETE FROM contract_blocks WHERE contract_block_id = $block_id
        AND NOT EXISTS (SELECT 1 FROM contract_block_uses WHERE contract_block_use_block_id = $block_id AND contract_block_use_hours > 0)");
    return mysqli_affected_rows($mysqli) === 1;
}

/** A block with its contract - or null. */
function contractBlockRow($block_id) {
    global $mysqli;
    if (!contractHoursReady()) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contract_blocks.*, contract_id, contract_name, contract_client_id,
            (SELECT COUNT(*) FROM contract_block_uses WHERE contract_block_use_block_id = contract_block_id AND contract_block_use_hours > 0) AS uses
        FROM contract_blocks JOIN contracts ON contract_block_contract_id = contract_id WHERE contract_block_id = " . intval($block_id))) ?: null;
}

/*
 * The night's prepaid hours, from contractsNightly(): each month's included
 * hours made, and every client with hours left has its done tickets drawn.
 * How many tickets were drawn.
 */
function contractHoursNightly($today = null) {
    global $mysqli;
    if (!contractHoursReady()) {
        return 0;
    }
    $sql = mysqli_query($mysqli, "SELECT * FROM contracts WHERE contract_status = 'Active' AND contract_archived_at IS NULL AND contract_monthly_hours > 0");
    while ($row = mysqli_fetch_assoc($sql)) {
        contractHoursEnsureMonthly($row, $today);
    }
    $drawn = 0;
    $sql = mysqli_query($mysqli, "SELECT DISTINCT contract_client_id FROM contracts
        JOIN contract_blocks ON contract_block_contract_id = contract_id
        WHERE contract_status = 'Active' AND contract_archived_at IS NULL");
    while ($row = mysqli_fetch_row($sql)) {
        $drawn += count(contractHoursDraw(intval($row[0]))['drawn']);
    }
    return $drawn;
}
