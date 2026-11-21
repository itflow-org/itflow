<?php

/*
 * ITFlow - Labor costs, and what each client costs you in Client Profitability
 *
 * Labor: what an hour of each agent costs the company - wage, payroll taxes and benefits - from
 * a date on (user_cost_rates, database 3.8.0). A new rate is a new row, so a past period keeps
 * the rate it had; an agent's first rate also covers the time before its date. Agents with none
 * take the company default (config_labor_cost_default); with no default either, their hours are
 * left uncosted and the report says so. Set on Admin > Labor Costs (admin/labor_costs.php, admins
 * only). The hours are Client Profitability's own: time on ticket replies, by the day the reply
 * was posted.
 *
 * Client Profitability (agent/reports/client_profitability.php) then adds:
 *   - parts: on invoices dated in the period (Sent, Viewed, Partial or Paid), each line's product
 *     cost (products.product_cost x quantity) - the product's cost now
 *   - income invoiced or paid (?basis=): paid, as before, is payments and revenue in the period,
 *     tax included; invoiced is invoice lines before tax, less the invoice's discount, on invoices
 *     dated in the period, plus revenue
 *   - profit, net less labor and parts, and margin, profit over income
 *   - break-even hours for flat monthly fees: the hours a month the client's recurring fee pays
 *     for at the cost of the agents who work for them, against the hours a month they take
 */

const LABOR_PROFIT_BASES = ['paid' => 'Paid', 'invoiced' => 'Invoiced'];
// Invoices that count as sold
const LABOR_PROFIT_INVOICE_STATUSES = "'Sent', 'Viewed', 'Partial', 'Paid'";

/** Whether the 3.8.0 update has run. */
function laborCostReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.8.0', '>=');
    }
    return $ready;
}

/** The company default cost per hour, or null when there's none. */
function laborCostDefault() {
    global $mysqli;
    if (!laborCostReady()) {
        return null;
    }
    $value = mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_labor_cost_default FROM settings WHERE company_id = 1"))[0] ?? null;
    return $value === null ? null : round(floatval($value), 2);
}

/** Every agent's rates: user id => [[from, hourly, rate id], ...], oldest first. */
function laborCostRates() {
    global $mysqli;
    $rates = [];
    if (!laborCostReady()) {
        return $rates;
    }
    $result = mysqli_query($mysqli, "SELECT user_cost_rate_id, user_cost_rate_user_id, user_cost_rate_from, user_cost_rate_hourly FROM user_cost_rates
        ORDER BY user_cost_rate_user_id, user_cost_rate_from");
    while ($row = mysqli_fetch_assoc($result)) {
        $rates[intval($row['user_cost_rate_user_id'])][] = [$row['user_cost_rate_from'], floatval($row['user_cost_rate_hourly']), intval($row['user_cost_rate_id'])];
    }
    return $rates;
}

/** An agent's cost per hour on a day: their newest rate from on or before it (the first also covers earlier days), else $default. */
function laborCostRateOn(array $rates, $user_id, $date, $default) {
    $list = $rates[intval($user_id)] ?? [];
    if (!$list) {
        return $default;
    }
    $rate = $list[0][1];
    foreach ($list as [$from, $hourly]) {
        if ($from <= $date) {
            $rate = $hourly;
        }
    }
    return $rate;
}

/** A cost per hour typed in - [amount, problem]. Blank is null. */
function laborCostParse($text) {
    $text = trim(str_replace([',', '$', ' '], '', (string) $text));
    if ($text === '') {
        return [null, ''];
    }
    if (!is_numeric($text) || floatval($text) < 0 || floatval($text) > 100000) {
        return [null, 'Enter a cost per hour of 0 or more.'];
    }
    return [round(floatval($text), 2), ''];
}

/** Save an agent's rate from a date - one a day, so the same date replaces it. Returns a problem, or ''. */
function laborCostRateSave($user_id, $from, $cost, $by) {
    global $mysqli;
    $user_id = intval($user_id);
    $from = trim((string) $from);
    if (!laborCostReady()) {
        return 'Labor costs need the database update - Maintenance > Update.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !strtotime($from)) {
        return 'Pick the date the rate counts from.';
    }
    [$amount, $problem] = laborCostParse($cost);
    if ($problem !== '' || $amount === null) {
        return $problem ?: 'Enter a cost per hour.';
    }
    if (!mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM users WHERE user_id = $user_id AND user_type = 1"))) {
        return 'That agent wasn\'t found.';
    }
    mysqli_query($mysqli, "INSERT INTO user_cost_rates SET user_cost_rate_user_id = $user_id, user_cost_rate_from = '" . escapeSql($from) . "', user_cost_rate_hourly = $amount,
        user_cost_rate_created_by = " . intval($by) . " ON DUPLICATE KEY UPDATE user_cost_rate_hourly = VALUES(user_cost_rate_hourly),
        user_cost_rate_created_by = VALUES(user_cost_rate_created_by), user_cost_rate_created_at = NOW()");
    return '';
}

/** Remove one rate. Returns its agent's id, or 0 when there was none. */
function laborCostRateDelete($rate_id) {
    global $mysqli;
    $rate_id = intval($rate_id);
    if (!laborCostReady()) {
        return 0;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT user_cost_rate_user_id FROM user_cost_rates WHERE user_cost_rate_id = $rate_id"));
    if (!$row) {
        return 0;
    }
    mysqli_query($mysqli, "DELETE FROM user_cost_rates WHERE user_cost_rate_id = $rate_id");
    return intval($row[0]);
}

/** The basis picked on the report (?basis=) - paid, as the report always counted, unless invoiced is picked. */
function laborProfitBasis() {
    $basis = (string) ($_GET['basis'] ?? 'paid');
    return isset(LABOR_PROFIT_BASES[$basis]) ? $basis : 'paid';
}

/*
 * Client Profitability's money side for each client in the period, in its rows (by reference).
 * $client is the report's closure that adds a row from [client_id, client_name,
 * client_abbreviation]. Sets income (on the invoiced basis), labor, parts and uncosted_seconds.
 */
function laborProfitAdd(array &$clients, callable $client, array $period, $with_tickets) {
    global $mysqli;
    if (!laborCostReady()) {
        return;
    }
    $scope = clientScopeSql('client_id');

    // Invoiced: the lines before tax, less the invoice's discount, on invoices dated in the period - and the client's revenue
    if (laborProfitBasis() === 'invoiced') {
        foreach ($clients as &$row) {
            $row['income'] = 0.0;
        }
        unset($row);
        $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, SUM(amount) AS v FROM (
                SELECT invoice_client_id AS client_id,
                    (SELECT COALESCE(SUM(item_subtotal), 0) FROM invoice_items WHERE item_invoice_id = invoice_id AND item_archived_at IS NULL) - invoice_discount_amount AS amount
                    FROM invoices WHERE invoice_status IN (" . LABOR_PROFIT_INVOICE_STATUSES . ") AND " . reportRangeSql('invoice_date', $period) . "
                UNION ALL
                SELECT revenue_client_id, revenue_amount FROM revenues
                    WHERE revenue_client_id > 0 AND " . dashboardRevenueSql() . " AND " . reportRangeSql('revenue_date', $period) . "
            ) AS income
            JOIN clients USING (client_id)
            WHERE 1 = 1 $scope
            GROUP BY client_id, client_name, client_abbreviation");
        while ($row = mysqli_fetch_assoc($sql)) {
            $clients[$client($row)]['income'] = floatval($row['v']);
        }
    }

    // Labor: each agent's hours at their rate on the day
    $rates = laborCostRates();
    $default = laborCostDefault();
    if ($with_tickets) {
        $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, ticket_reply_by AS user_id, DATE(ticket_reply_created_at) AS d,
                SUM(TIME_TO_SEC(ticket_reply_time_worked)) AS s
            FROM ticket_replies
            JOIN tickets ON ticket_reply_ticket_id = ticket_id
            JOIN clients ON ticket_client_id = client_id
            WHERE ticket_reply_archived_at IS NULL AND ticket_reply_time_worked IS NOT NULL
            AND " . reportRangeSql('ticket_reply_created_at', $period) . " $scope
            GROUP BY client_id, client_name, client_abbreviation, ticket_reply_by, DATE(ticket_reply_created_at)");
        while ($row = mysqli_fetch_assoc($sql)) {
            $id = $client($row);
            $seconds = intval($row['s']);
            $rate = laborCostRateOn($rates, $row['user_id'], $row['d'], $default);
            if ($rate === null) {
                $clients[$id]['uncosted_seconds'] = ($clients[$id]['uncosted_seconds'] ?? 0) + $seconds;
            } else {
                $clients[$id]['labor'] = ($clients[$id]['labor'] ?? 0.0) + $seconds / 3600 * $rate;
            }
        }
    }

    // Parts: what the products sold on invoices dated in the period cost you
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, SUM(product_cost * item_quantity) AS v
        FROM invoice_items
        JOIN invoices ON item_invoice_id = invoice_id
        JOIN products ON product_id = item_product_id
        JOIN clients ON invoice_client_id = client_id
        WHERE item_archived_at IS NULL AND item_product_id > 0 AND product_cost > 0
        AND invoice_status IN (" . LABOR_PROFIT_INVOICE_STATUSES . ") AND " . reportRangeSql('invoice_date', $period) . " $scope
        GROUP BY client_id, client_name, client_abbreviation");
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[$client($row)]['parts'] = floatval($row['v']);
    }

    foreach ($clients as &$row) {
        $row['labor'] = round($row['labor'] ?? 0.0, 2);
        $row['parts'] = round($row['parts'] ?? 0.0, 2);
        $row['uncosted_seconds'] = $row['uncosted_seconds'] ?? 0;
    }
    unset($row);
}

/*
 * Once the report has worked out net: profit and margin on each row, and break-even hours for
 * flat monthly fees - the hours a month a client's recurring fee pays for, after their parts and
 * direct costs, at the cost per hour of the agents who work for them (their labor over their
 * costed hours; with none, everyone in the report's, else the default), against the hours a
 * month they take. No fee, no break-even.
 */
function laborProfitFinish(array &$clients, array $period) {
    if (!laborCostReady()) {
        return;
    }
    $days = (strtotime($period['end']) - strtotime($period['start'])) / 86400 + 1;
    $months = max($days, 1) / 30.4375;
    $labor = 0.0;
    $costed = 0;
    foreach ($clients as $row) {
        $labor += $row['labor'] ?? 0;
        $costed += max(0, ($row['seconds'] ?? 0) - ($row['uncosted_seconds'] ?? 0));
    }
    $blended = $costed > 0 ? $labor / ($costed / 3600) : laborCostDefault();
    foreach ($clients as &$row) {
        $row['labor'] = $row['labor'] ?? 0.0;
        $row['parts'] = $row['parts'] ?? 0.0;
        $row['uncosted_seconds'] = $row['uncosted_seconds'] ?? 0;
        $row['profit'] = round(($row['net'] ?? 0) - $row['labor'] - $row['parts'], 2);
        $row['margin'] = reportShare($row['profit'], $row['income'], 1);
        $row['hours_month'] = round(($row['seconds'] ?? 0) / 3600 / $months, 2);
        $row['break_even'] = null;
        $row['over_break_even'] = false;
        $fee = floatval($row['mrr'] ?? 0);
        $own = max(0, ($row['seconds'] ?? 0) - $row['uncosted_seconds']);
        $rate = $own > 0 && $row['labor'] > 0 ? $row['labor'] / ($own / 3600) : $blended;
        if ($fee > 0 && $rate) {
            $other = (($row['costs'] ?? 0) + $row['parts']) / $months;
            $row['break_even'] = round(max(0, $fee - $other) / $rate, 2);
            $row['over_break_even'] = $row['hours_month'] > $row['break_even'];
        }
    }
    unset($row);
}
