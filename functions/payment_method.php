<?php

/*
 * Payment methods - Cash, Check, ACH, Credit Card, the company Amex - how
 * money comes in and goes out. A payment, income, an expense, a recurring
 * expense, a reimbursement, a transfer and an autopay keep the method by its
 * name, so renaming one here renames it on everything that used it, and
 * deleting one can move its records to another (a merge). Each is for money
 * in (payments and income), money out (expenses, reimbursements) or both,
 * and the pickers offer only the ones that fit. An archived one leaves the
 * pickers, and the records that used it keep it. Admin > Payment Methods
 * (admin/payment_methods.php) and its handlers (admin/post/payment_method.php)
 * are built on these.
 *
 * Card payments made online are recorded as "Stripe" whatever is set up here.
 */

/** What a method is for - payment_method_use. */
const PAYMENT_METHOD_USES = [
    0 => ['label' => 'Money in and out', 'short' => 'In and out', 'icon' => 'fa-exchange-alt', 'about' => 'Offered for payments, income, expenses and transfers'],
    1 => ['label' => 'Money in - payments and income', 'short' => 'Money in', 'icon' => 'fa-arrow-down', 'about' => 'Offered for payments from clients and other income'],
    2 => ['label' => 'Money out - expenses', 'short' => 'Money out', 'icon' => 'fa-arrow-up', 'about' => 'Offered for expenses, recurring expenses, reimbursements and mileage claims'],
];

/** The name online card payments are recorded under. */
const PAYMENT_METHOD_ONLINE = 'Stripe';

/*
 * Every place a method is kept by name - key => table, column, what one is
 * called, which way the money goes, the date it is dated by, and where the
 * records are listed.
 */
function paymentMethodUsageColumns() {
    return [
        'payments' => ['table' => 'payments', 'column' => 'payment_method', 'one' => 'payment', 'many' => 'payments', 'way' => 'in', 'date' => 'payment_date'],
        'income' => ['table' => 'revenues', 'column' => 'revenue_payment_method', 'one' => 'income', 'many' => 'income', 'way' => 'in', 'date' => 'revenue_date'],
        'expenses' => ['table' => 'expenses', 'column' => 'expense_payment_method', 'one' => 'expense', 'many' => 'expenses', 'way' => 'out', 'date' => 'expense_date'],
        'recurring_expenses' => ['table' => 'recurring_expenses', 'column' => 'recurring_expense_payment_method', 'one' => 'recurring expense', 'many' => 'recurring expenses', 'way' => 'out', 'date' => ''],
        'reimbursements' => ['table' => 'reimbursements', 'column' => 'reimbursement_payment_method', 'one' => 'reimbursement', 'many' => 'reimbursements', 'way' => 'out', 'date' => 'reimbursement_date'],
        'transfers' => ['table' => 'transfers', 'column' => 'transfer_method', 'one' => 'transfer', 'many' => 'transfers', 'way' => 'both', 'date' => 'DATE(transfer_created_at)'],
        'autopay' => ['table' => 'recurring_payments', 'column' => 'recurring_payment_method', 'one' => 'autopay', 'many' => 'autopays', 'way' => 'in', 'date' => ''],
    ];
}

/** Whether the 3.4.1 update has run - payment_method_use and payment_method_archived_at. */
function paymentMethodColumnsReady() {
    static $ready = null;
    if ($ready === null) {
        $ready = defined('CURRENT_DATABASE_VERSION') ? version_compare(CURRENT_DATABASE_VERSION, '3.4.1', '>=') : true;
    }
    return $ready;
}

/** The columns a method is read with - the 3.4.1 ones only once they are there. */
function paymentMethodColumnsSql() {
    return 'payment_method_id, payment_method_name, payment_method_description, payment_method_created_at'
        . (paymentMethodColumnsReady() ? ', payment_method_use, payment_method_archived_at' : ', 0 AS payment_method_use, NULL AS payment_method_archived_at');
}

/** One method, or null. */
function paymentMethodRow($payment_method_id) {
    global $mysqli;
    $payment_method_id = intval($payment_method_id);
    if ($payment_method_id <= 0) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . paymentMethodColumnsSql() . " FROM payment_methods WHERE payment_method_id = $payment_method_id LIMIT 1")) ?: null;
}

/* Every method by name - id => row. Read once a page; pass $fresh after changing one. */
function paymentMethodsAll($fresh = false) {
    global $mysqli;
    static $methods = null;
    if ($methods === null || $fresh) {
        $methods = [];
        $sql = mysqli_query($mysqli, "SELECT " . paymentMethodColumnsSql() . " FROM payment_methods ORDER BY payment_method_name ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $methods[intval($row['payment_method_id'])] = $row;
        }
    }
    return $methods;
}

/*
 * The ways money can move, by name - for a picker or a check. $way 'in'
 * (payments and income), 'out' (expenses) or '' for every one. Archived ones
 * only with $with_archived - a filter looking for old records wants them.
 */
function paymentMethods($way = '', $with_archived = false) {
    $names = [];
    foreach (paymentMethodsAll() as $row) {
        $use = intval($row['payment_method_use']);
        if (!$with_archived && !empty($row['payment_method_archived_at'])) {
            continue;
        }
        if (($way === 'in' && $use === 2) || ($way === 'out' && $use === 1)) {
            continue;
        }
        $names[] = (string) $row['payment_method_name'];
    }
    return $names;
}

/** Whether a name is the one online card payments are recorded under. */
function paymentMethodIsOnline($name) {
    return strcasecmp(trim((string) $name), PAYMENT_METHOD_ONLINE) === 0;
}

/*
 * How much each method has been used - lower-case name => [name (as first
 * written), key => count for each of paymentMethodUsageColumns(), 'total',
 * 'last' (the latest date)]. Archived records count too - they still say it.
 */
function paymentMethodUsage() {
    global $mysqli;
    static $usage = null;
    if ($usage !== null) {
        return $usage;
    }
    $usage = [];
    $parts = [];
    foreach (paymentMethodUsageColumns() as $key => $c) {
        $parts[] = "SELECT '$key' AS k, {$c['column']} AS m, COUNT(*) AS n, " . ($c['date'] !== '' ? "MAX({$c['date']})" : 'NULL') . " AS d
            FROM {$c['table']} WHERE {$c['column']} IS NOT NULL AND {$c['column']} <> '' GROUP BY {$c['column']}";
    }
    $sql = mysqli_query($mysqli, implode(' UNION ALL ', $parts));
    while ($row = mysqli_fetch_assoc($sql)) {
        $name = trim((string) $row['m']);
        $lower = mb_strtolower($name);
        if (!isset($usage[$lower])) {
            $usage[$lower] = ['name' => $name, 'total' => 0, 'last' => null];
        }
        $usage[$lower][$row['k']] = ($usage[$lower][$row['k']] ?? 0) + intval($row['n']);
        $usage[$lower]['total'] += intval($row['n']);
        if ($row['d'] && (!$usage[$lower]['last'] || $row['d'] > $usage[$lower]['last'])) {
            $usage[$lower]['last'] = $row['d'];
        }
    }
    return $usage;
}

/** One method's use, as paymentMethodUsage() has it - all zeros when it has none. */
function paymentMethodUsageFor($name) {
    return paymentMethodUsage()[mb_strtolower(trim((string) $name))] ?? ['name' => (string) $name, 'total' => 0, 'last' => null];
}

/* What a method is used on, in words - "137 payments · 24 expenses". */
function paymentMethodUsageText(array $usage) {
    $parts = [];
    foreach (paymentMethodUsageColumns() as $key => $c) {
        $n = intval($usage[$key] ?? 0);
        if ($n) {
            $parts[] = number_format($n) . ' ' . ($n === 1 ? $c['one'] : $c['many']);
        }
    }
    return implode(' · ', $parts);
}

/*
 * Methods records say they were paid by that aren't in the list - from an
 * import, an older version, or a method deleted since. Name => usage, the
 * most used first. Not "Stripe": online card payments are recorded under it,
 * and it isn't one to pick by hand.
 */
function paymentMethodsNotSetUp() {
    $set_up = [];
    foreach (paymentMethodsAll() as $row) {
        $set_up[mb_strtolower(trim((string) $row['payment_method_name']))] = true;
    }
    $missing = [];
    foreach (paymentMethodUsage() as $lower => $usage) {
        if (!isset($set_up[$lower]) && !paymentMethodIsOnline($usage['name'])) {
            $missing[$usage['name']] = $usage;
        }
    }
    uasort($missing, function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    return $missing;
}

/* The default methods - 'in' for payments (Settings > Defaults' payment method), 'out' for expenses. */
function paymentMethodDefaults() {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT config_default_payment_method, config_default_expense_payment_method FROM settings WHERE company_id = 1")) ?: [];
    return ['in' => (string) ($row['config_default_payment_method'] ?? ''), 'out' => (string) ($row['config_default_expense_payment_method'] ?? '')];
}

/* Which defaults a method is - ['in', 'out'], one of them, or none. */
function paymentMethodDefaultFor($name, array $defaults = null) {
    $defaults = $defaults ?? paymentMethodDefaults();
    $is = [];
    foreach ($defaults as $way => $default) {
        if ($default !== '' && strcasecmp($default, (string) $name) === 0) {
            $is[] = $way;
        }
    }
    return $is;
}

/* Make a method the default for payments ('in') or expenses ('out') - '' clears it. */
function paymentMethodSetDefault($way, $name) {
    global $mysqli;
    $column = $way === 'out' ? 'config_default_expense_payment_method' : 'config_default_payment_method';
    $name = (string) $name;
    mysqli_query($mysqli, "UPDATE settings SET $column = " . ($name !== '' ? "'" . escapeSql($name) . "'" : 'NULL') . " WHERE company_id = 1");
}

/* A method's fields from a form - name, description and use. A method online payments are recorded under keeps its name. */
function paymentMethodFromInput(array $in, $row = null) {
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($in['name'] ?? ''))));
    $use = intval($in['use'] ?? 0);
    $f = [
        'name' => mb_substr($name, 0, 200),
        'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($in['description'] ?? '')))), 0, 250),
        'use' => isset(PAYMENT_METHOD_USES[$use]) ? $use : 0,
    ];
    if ($row && paymentMethodIsOnline($row['payment_method_name'])) {
        $f['name'] = (string) $row['payment_method_name'];
    }
    return $f;
}

/* What is wrong with a method's fields - '' when nothing. $id is the method being changed, 0 for a new one. */
function paymentMethodProblem(array $f, $id = 0) {
    global $mysqli;
    if ($f['name'] === '') {
        return 'A payment method needs a name.';
    }
    $taken = mysqli_fetch_row(mysqli_query($mysqli, "SELECT payment_method_name FROM payment_methods WHERE payment_method_name = '" . escapeSql($f['name']) . "' AND payment_method_id <> " . intval($id) . " LIMIT 1"));
    if ($taken) {
        return 'There is already a payment method called "' . $taken[0] . '".';
    }
    return '';
}

/** The SET clause for a method's fields. */
function paymentMethodSetSql(array $f) {
    return "payment_method_name = '" . escapeSql($f['name']) . "', payment_method_description = " . ($f['description'] !== '' ? "'" . escapeSql($f['description']) . "'" : 'NULL')
        . (paymentMethodColumnsReady() ? ", payment_method_use = {$f['use']}" : '');
}

/** A new method - its id. */
function paymentMethodCreate(array $f) {
    global $mysqli;
    mysqli_query($mysqli, "INSERT INTO payment_methods SET " . paymentMethodSetSql($f));
    $id = intval(mysqli_insert_id($mysqli));
    paymentMethodsAll(true);
    return $id;
}

/*
 * Rename a method on everything that says it - payments, income, expenses
 * and the rest (paymentMethodUsageColumns()), and the defaults. Matched
 * without regard to case. How many records changed.
 */
function paymentMethodRename($old, $new) {
    global $mysqli;
    $old = (string) $old;
    $new = (string) $new;
    if ($old === '' || $old === $new) {
        return 0;
    }
    $old_sql = escapeSql($old);
    $new_sql = escapeSql($new);
    $changed = 0;
    foreach (paymentMethodUsageColumns() as $c) {
        mysqli_query($mysqli, "UPDATE {$c['table']} SET {$c['column']} = '$new_sql' WHERE {$c['column']} = '$old_sql'");
        $changed += max(0, mysqli_affected_rows($mysqli));
    }
    foreach (paymentMethodDefaults() as $way => $default) {
        if ($default !== '' && strcasecmp($default, $old) === 0) {
            paymentMethodSetDefault($way, $new);
        }
    }
    return $changed;
}

/*
 * Save a method's fields - [what changed, as phrases for the audit log; how
 * many records were renamed with it]. A method no longer offered for money in
 * (or out) stops being the default for it.
 */
function paymentMethodUpdate(array $row, array $f) {
    global $mysqli;
    $id = intval($row['payment_method_id']);
    $was_name = (string) $row['payment_method_name'];
    mysqli_query($mysqli, "UPDATE payment_methods SET " . paymentMethodSetSql($f) . " WHERE payment_method_id = $id");

    $changes = [];
    $renamed = 0;
    if ($was_name !== $f['name']) {
        $changes[] = "name from $was_name to {$f['name']}";
        $renamed = paymentMethodRename($was_name, $f['name']);
        if ($renamed) {
            $changes[] = "renamed on $renamed records";
        }
    }
    $was_description = trim((string) $row['payment_method_description']);
    if ($was_description !== $f['description']) {
        $changes[] = 'description from ' . ($was_description !== '' ? $was_description : 'none') . ' to ' . ($f['description'] !== '' ? $f['description'] : 'none');
    }
    $was_use = intval($row['payment_method_use']);
    if ($was_use !== $f['use']) {
        $changes[] = 'for ' . strtolower(PAYMENT_METHOD_USES[$was_use]['short'] ?? 'in and out') . ' now ' . strtolower(PAYMENT_METHOD_USES[$f['use']]['short']);
        foreach (paymentMethodDefaultFor($f['name']) as $way) {
            if (($way === 'in' && $f['use'] === 2) || ($way === 'out' && $f['use'] === 1)) {
                paymentMethodSetDefault($way, '');
                $changes[] = 'no longer the default for ' . ($way === 'in' ? 'payments' : 'expenses');
            }
        }
    }
    paymentMethodsAll(true);
    return [$changes, $renamed];
}

/* Archive a method (it leaves the pickers, and stops being a default) or bring it back. The defaults it stopped being. */
function paymentMethodSetArchived(array $row, $archived) {
    global $mysqli;
    $id = intval($row['payment_method_id']);
    mysqli_query($mysqli, "UPDATE payment_methods SET payment_method_archived_at = " . ($archived ? 'NOW()' : 'NULL') . " WHERE payment_method_id = $id");
    $cleared = [];
    if ($archived) {
        foreach (paymentMethodDefaultFor($row['payment_method_name']) as $way) {
            paymentMethodSetDefault($way, '');
            $cleared[] = $way;
        }
    }
    paymentMethodsAll(true);
    return $cleared;
}

/*
 * Delete a method. One that has been used needs $move_to, another method's
 * id: its records are renamed to that one (a merge), which takes its place
 * as a default too. One nothing used just goes, and stops being a default.
 * [ok, records moved, defaults cleared].
 */
function paymentMethodDelete(array $row, $move_to = 0) {
    global $mysqli;
    $id = intval($row['payment_method_id']);
    $name = (string) $row['payment_method_name'];
    $target = intval($move_to) && intval($move_to) !== $id ? (paymentMethodsAll()[intval($move_to)] ?? null) : null;
    $used = paymentMethodUsageFor($name)['total'];
    if ($used && !$target) {
        return [false, 0, []];
    }
    $moved = 0;
    $cleared = [];
    if ($target) {
        $moved = paymentMethodRename($name, (string) $target['payment_method_name']);
    } else {
        foreach (paymentMethodDefaultFor($name) as $way) {
            paymentMethodSetDefault($way, '');
            $cleared[] = $way;
        }
    }
    mysqli_query($mysqli, "DELETE FROM payment_methods WHERE payment_method_id = $id");
    paymentMethodsAll(true);
    return [true, $moved, $cleared];
}

/* Add names records already use to the list - for money in and out. How many were added. */
function paymentMethodAddNames(array $names) {
    $missing = paymentMethodsNotSetUp();
    $added = 0;
    foreach ($names as $name) {
        $name = trim((string) $name);
        foreach ($missing as $missing_name => $usage) {
            if (strcasecmp($missing_name, $name) === 0) {
                paymentMethodCreate(['name' => mb_substr($missing_name, 0, 200), 'description' => '', 'use' => 0]);
                $added++;
                unset($missing[$missing_name]);
                break;
            }
        }
    }
    return $added;
}
