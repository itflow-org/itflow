<?php

/*
 * ITFlow - importing the deposits on a bank statement as income.
 *
 * The statement is read, and its columns worked out and remembered on the
 * account, by functions/expense_import.php - the same file can be brought in
 * on both sides: its charges as expenses, its deposits here. Only the money
 * in is a deposit.
 *
 * Each deposit becomes one of three things:
 *
 *  - payment   on one or more of a client's open invoices, the oldest due
 *              first, the last part paid when the deposit is short of them
 *  - income    other income, with a category and the client it came from
 *  - transfer  money moved in from another of our accounts - a payout of
 *              card payments from the payment provider's account, or savings
 *
 * It is matched, in this order, by an invoice number in its text; by what
 * was picked for the same payer last time (income_import_rules, keyed on the
 * start of the cleaned-up text, like the expense side's merchants); by a
 * client's name in it; by being the only invoice, or the one client's open
 * invoices together, owing exactly that much; and by looking like a payout
 * or a transfer. A client's invoices are picked as the one owing the amount,
 * the ones that add up to it, or the oldest first.
 *
 * Every deposit has a key, as the expense side's lines do, kept on the
 * payments (payment_import_key) or the revenue (revenue_import_key) it came
 * in as, so the same statement can't bring it in twice. A deposit not
 * imported before but with the amount of a payment, other income or transfer
 * entered by hand on the account within a few days - or of a day's payments
 * together, one check paying several invoices - looks entered already: left
 * out unless ticked, and if left out those take its key. A payout into the
 * account the payment provider's payments are recorded in is recorded
 * already.
 */

const INCOME_IMPORT_DUPLICATE_DAYS = 4;

/** May this agent record payments on invoices from a statement - the rule for adding a payment (Sales and Financial at 2). */
function incomeImportCanPay() {
    return lookupUserPermission('module_sales') >= 2 && lookupUserPermission('module_financial') >= 2;
}

/** What a deposit can be brought in as. */
function incomeImportActions() {
    return [
        'payment' => 'Payment on invoices',
        'income' => 'Other income',
        'transfer' => 'Transfer from an account',
    ];
}

/**
 * The deposits on a statement by a mapping - the lines with money in, each
 * [n, date, description, match_text, amount (what came in, positive),
 * reference, problem]. $charges gets how many lines are money out.
 */
function incomeImportLines(array $rows, array $map, &$charges = 0) {
    $charges = 0;
    $deposits = [];
    foreach (expenseImportLines($rows, $map) as $line) {
        if ($line['amount'] !== null && $line['amount'] > 0) {
            $charges++;
            continue;
        }
        if ($line['amount'] === null && $line['problem'] === '') {
            continue;
        }
        $line['amount'] = $line['amount'] === null ? null : round(-$line['amount'], 2);
        if ($line['problem'] === 'The amount is zero') {
            continue;
        }
        $line['n'] = count($deposits);
        $deposits[] = $line;
    }
    return $deposits;
}

/**
 * Every invoice still owed that the agent may see - id => [invoice_id,
 * client_id, client_name, ref, scope, due, currency, balance], the oldest
 * due first.
 */
function incomeImportOpenInvoices() {
    global $mysqli;
    $invoices = [];
    $sql = mysqli_query($mysqli, "SELECT invoice_id, invoice_prefix, invoice_number, invoice_scope, invoice_due, invoice_currency_code, invoice_client_id, client_name,
            invoice_amount - COALESCE((SELECT SUM(payment_amount) FROM payments WHERE payment_invoice_id = invoice_id), 0) AS balance
        FROM invoices JOIN clients ON client_id = invoice_client_id
        WHERE invoice_status IN ('Sent', 'Viewed', 'Partial') AND invoice_archived_at IS NULL " . clientScopeSql('invoices.invoice_client_id') . "
        HAVING balance > 0.004
        ORDER BY invoice_due ASC, invoice_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $invoices[intval($row['invoice_id'])] = [
            'invoice_id' => intval($row['invoice_id']),
            'client_id' => intval($row['invoice_client_id']),
            'client_name' => (string) $row['client_name'],
            'prefix' => (string) $row['invoice_prefix'],
            'number' => intval($row['invoice_number']),
            'ref' => $row['invoice_prefix'] . $row['invoice_number'],
            'scope' => (string) $row['invoice_scope'],
            'due' => (string) $row['invoice_due'],
            'currency' => (string) $row['invoice_currency_code'],
            'balance' => round(floatval($row['balance']), 2),
        ];
    }
    return $invoices;
}

/** The remembered payers - match => [id, action, client_id, category_id, account_id]. */
function incomeImportRules() {
    global $mysqli;
    $rules = [];
    $sql = mysqli_query($mysqli, "SELECT import_rule_id, import_rule_match, import_rule_action, import_rule_client_id, import_rule_category_id, import_rule_account_id FROM income_import_rules");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rules[$row['import_rule_match']] = ['id' => intval($row['import_rule_id']), 'action' => (string) $row['import_rule_action'], 'client_id' => intval($row['import_rule_client_id']),
            'category_id' => intval($row['import_rule_category_id']), 'account_id' => intval($row['import_rule_account_id'])];
    }
    return $rules;
}

/** How a deposit was paid, from its text - one of the payment methods set up when it says, else $default. */
function incomeImportMethod($text, $default = '') {
    $methods = paymentMethods();
    $text = mb_strtolower((string) $text);
    $wants = [
        '/\b(ach|direct dep|direct deposit|ccd|ppd|eft)\b/' => ['ACH', 'Bank Transfer', 'EFT'],
        '/\b(wire|fedwire|swift)\b/' => ['Wire', 'Wire Transfer', 'Bank Transfer'],
        '/\b(check|cheque|chk|mobile deposit|remote deposit|rdc|counter deposit)\b/' => ['Check', 'Cheque'],
        '/\b(zelle|venmo|cash app)\b/' => ['Zelle', 'Bank Transfer'],
        '/\b(cash)\b/' => ['Cash'],
    ];
    foreach ($wants as $pattern => $names) {
        if (preg_match($pattern, $text)) {
            foreach ($names as $name) {
                foreach ($methods as $method) {
                    if (strcasecmp($method, $name) === 0) {
                        return $method;
                    }
                }
            }
        }
    }
    return in_array((string) $default, $methods, true) ? (string) $default : '';
}

/**
 * The invoice numbers a deposit's text names - after an invoice prefix in
 * use ("INV-1042", "inv 1042") or the word invoice ("Invoice #1042"). A bare
 * number is a check number as often as not, so it doesn't count.
 */
function incomeImportInvoiceNumbers($text, array $prefixes) {
    $stems = ['invoices', 'invoice', 'inv'];
    foreach ($prefixes as $prefix) {
        $stem = trim(mb_strtolower((string) $prefix), " -#:_/.");
        if ($stem !== '' && preg_match('/^[\p{L}\d]+$/u', $stem)) {
            $stems[] = $stem;
        }
    }
    $stems = array_unique($stems);
    usort($stems, function ($a, $b) { return mb_strlen($b) <=> mb_strlen($a); });
    $alternation = implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $stems));
    $numbers = [];
    if (preg_match_all('/\b(?:' . $alternation . ')\s*(?:no\.?|number)?\s*[-#:_\/.]?\s*(\d{1,9}(?:\s*(?:,|&|and)\s*\d{1,9})*)\b/iu', (string) $text, $matches)) {
        foreach ($matches[1] as $list) {
            foreach (preg_split('/\s*(?:,|&|and)\s*/i', $list) as $number) {
                if (ctype_digit($number) && intval($number) > 0) {
                    $numbers[intval($number)] = intval($number);
                }
            }
        }
    }
    return array_values($numbers);
}

/**
 * Which of a client's open invoices (oldest due first) a deposit pays - [ids,
 * how]: the one owing exactly the amount ('exact'), the fewest that add up
 * to it ('sum'), the oldest first until it is used up, the last part paid
 * ('oldest'), or all of them when it is more than they owe ('over').
 */
function incomeImportPickInvoices(array $invoices, $amount) {
    $invoices = array_values($invoices);
    if (!$invoices || $amount <= 0) {
        return [[], ''];
    }
    $cents = (int) round($amount * 100);
    foreach ($invoices as $invoice) {
        if ((int) round($invoice['balance'] * 100) === $cents) {
            return [[$invoice['invoice_id']], 'exact'];
        }
    }
    // The fewest that add up to it exactly - a check paying several
    $count = min(count($invoices), 14);
    $best = null;
    for ($mask = 1; $mask < (1 << $count); $mask++) {
        $sum = 0;
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            if ($mask & (1 << $i)) {
                $sum += (int) round($invoices[$i]['balance'] * 100);
                $ids[] = $invoices[$i]['invoice_id'];
            }
        }
        if ($sum === $cents && ($best === null || count($ids) < count($best))) {
            $best = $ids;
        }
    }
    if ($best) {
        return [$best, 'sum'];
    }
    $total = 0;
    $ids = [];
    foreach ($invoices as $invoice) {
        $ids[] = $invoice['invoice_id'];
        $total += (int) round($invoice['balance'] * 100);
        if ($total >= $cents) {
            return [$ids, 'oldest'];
        }
    }
    return [$ids, 'over'];
}

/**
 * The names to look for in a deposit's text - id => ' its words ', the
 * longest first, from expenseImportWords() (so "Ravenwood Dental Group, LLC"
 * is " ravenwood dental group ").
 */
function incomeImportNameWords(array $names) {
    $out = [];
    foreach ($names as $id => $name) {
        $words = expenseImportWords($name);
        if ($words && mb_strlen(implode(' ', $words)) >= 4) {
            $out[$id] = ' ' . implode(' ', $words) . ' ';
        }
    }
    uasort($out, function ($a, $b) { return mb_strlen($b) <=> mb_strlen($a); });
    return $out;
}

/** The id of the name that appears in a line's words - whole, or its first words with the last cut short ("RAVENWOOD DENTAL GRP"). 0 for none. */
function incomeImportNameMatch(array $line_words, array $name_words) {
    $text = ' ' . implode(' ', $line_words) . ' ';
    foreach ($name_words as $id => $words) {
        if (strpos($text, $words) !== false) {
            return $id;
        }
    }
    foreach ($name_words as $id => $words) {
        $want = explode(' ', trim($words));
        if (count($want) < 2) {
            continue;
        }
        for ($at = 0; $at < count($line_words); $at++) {
            $ok = 0;
            foreach ($want as $j => $word) {
                $have = $line_words[$at + $j] ?? null;
                if ($have === null || !($have === $word || (mb_strlen($have) >= 3 && strpos($word, $have) === 0))) {
                    break;
                }
                $ok++;
            }
            if ($ok >= 2 && ($ok === count($want) || !isset($line_words[$at + $ok]) || $ok >= 3)) {
                return $id;
            }
        }
    }
    return 0;
}

/** The accounts payment providers record online payments in. */
function incomeImportProviderAccounts() {
    global $mysqli;
    $accounts = [];
    $sql = mysqli_query($mysqli, "SELECT payment_provider_account, payment_provider_name FROM payment_providers WHERE payment_provider_active = 1 AND payment_provider_account > 0");
    while ($row = mysqli_fetch_row($sql)) {
        $accounts[intval($row[0])] = (string) $row[1];
    }
    return $accounts;
}

/** Does a deposit look like a payout of card payments, or a transfer between accounts? 'payout', 'transfer' or ''. */
function incomeImportKind($text) {
    $text = mb_strtolower((string) $text);
    if (preg_match('/\b(stripe|paypal|square ?inc|squareup|gocardless|intuit|qbms|quickbooks payments|authorize ?net|bankcard|merchant (deposit|settlement|services|bankcd)|payout)\b/u', $text)) {
        return 'payout';
    }
    if (preg_match('/\b(transfer|xfer|xfr|trnsfr|tfr)\b|\bfrom (savings|checking|share|sav|chk)\b/u', $text)) {
        return 'transfer';
    }
    return '';
}

/**
 * Match each deposit - sets key (the match key), action, client_id,
 * invoice_ids, how (exact, sum, oldest or over), category_id,
 * from_account_id, method, matched ('number', 'rule', 'name', 'amount',
 * 'payout', 'transfer' or '') and match_note to say why.
 */
function incomeImportMatch(array $lines, $account_id, $default_method = '') {
    global $mysqli;
    $account_id = intval($account_id);
    $can_pay = incomeImportCanPay();
    $open = $can_pay ? incomeImportOpenInvoices() : [];
    $by_client = [];
    $prefixes = [];
    foreach ($open as $invoice) {
        $by_client[$invoice['client_id']][] = $invoice;
        $prefixes[$invoice['prefix']] = $invoice['prefix'];
    }
    $rules = incomeImportRules();
    uksort($rules, function ($a, $b) { return mb_strlen($b) <=> mb_strlen($a); });
    $clients = [];
    $client_names = [];
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id'));
    while ($row = mysqli_fetch_row($sql)) {
        $clients[intval($row[0])] = (string) $row[1];
    }
    $client_names = incomeImportNameWords($clients);
    $accounts = paymentAccounts();
    $account_names = [];
    foreach ($accounts as $id => $account) {
        if ($id === $account_id) {
            continue;
        }
        // Words that tell one of our accounts from another - not "business" or "account"
        $words = array_values(array_diff(expenseImportWords($account['account_name']), ['business', 'account', 'accounts', 'bank', 'main', 'company', 'our']));
        if ($words) {
            $account_names[$id] = $words;
        }
    }
    $providers = incomeImportProviderAccounts();
    $categories = incomeCategories();

    // Clients whose open invoices together owe one amount - when no other client's owe the same
    $client_totals = [];
    foreach ($by_client as $client_id => $invoices) {
        $cents = 0;
        foreach ($invoices as $invoice) {
            $cents += (int) round($invoice['balance'] * 100);
        }
        $client_totals[$cents][] = $client_id;
    }
    $balances = [];
    foreach ($open as $invoice) {
        $balances[(int) round($invoice['balance'] * 100)][] = $invoice['invoice_id'];
    }
    $money = function ($amount, $currency = '') {
        return billingMoney($amount, $currency);
    };

    foreach ($lines as $i => $line) {
        $text = trim($line['match_text'] . ' ' . $line['reference']);
        $key = expenseImportMatchKey($line['match_text']);
        $line += ['key' => $key, 'action' => 'income', 'client_id' => 0, 'invoice_ids' => [], 'how' => '', 'category_id' => 0, 'from_account_id' => 0, 'matched' => '', 'match_note' => ''];
        $line['key'] = $key;
        $line['method'] = incomeImportMethod($text, $default_method);
        if ($line['problem'] !== '' || $line['amount'] === null) {
            $lines[$i] = $line;
            continue;
        }
        $amount = $line['amount'];
        $cents = (int) round($amount * 100);
        $pay = function ($client_id, $invoices, $matched, $note) use (&$line, $amount) {
            [$ids, $how] = incomeImportPickInvoices($invoices, $amount);
            $line['action'] = 'payment';
            $line['client_id'] = $client_id;
            $line['invoice_ids'] = $ids;
            $line['how'] = $how;
            $line['matched'] = $matched;
            $line['match_note'] = $note;
        };

        // 1. An invoice number in it
        $named = [];
        foreach (incomeImportInvoiceNumbers($text, array_values($prefixes)) as $number) {
            foreach ($open as $invoice) {
                if ($invoice['number'] === $number) {
                    $named[] = $invoice;
                }
            }
        }
        if ($named) {
            $client_id = $named[0]['client_id'];
            $named = array_values(array_filter($named, function ($inv) use ($client_id) { return $inv['client_id'] === $client_id; }));
            $refs = implode(', ', array_column($named, 'ref'));
            $line['action'] = 'payment';
            $line['client_id'] = $client_id;
            $line['invoice_ids'] = array_column($named, 'invoice_id');
            $owed = (int) round(array_sum(array_column($named, 'balance')) * 100);
            $line['how'] = $owed === $cents ? (count($named) > 1 ? 'sum' : 'exact') : ($owed > $cents ? 'oldest' : 'over');
            $line['matched'] = 'number';
            $line['match_note'] = "$refs in the text";
            $lines[$i] = $line;
            continue;
        }

        // 2. What this payer was last time
        $rule = null;
        if ($key !== '') {
            foreach ($rules as $match => $candidate) {
                if ($key === $match || strpos($key, $match . ' ') === 0) {
                    $rule = $candidate;
                    break;
                }
            }
        }
        $kind = incomeImportKind($text);
        if ($rule) {
            $note = 'As last time';
            if ($rule['action'] === 'transfer' && isset($accounts[$rule['account_id']]) && $rule['account_id'] !== $account_id) {
                $line['action'] = 'transfer';
                $line['from_account_id'] = $rule['account_id'];
                $line['matched'] = 'rule';
                $line['match_note'] = $note;
            } elseif ($rule['action'] === 'payment' && isset($clients[$rule['client_id']]) && !empty($by_client[$rule['client_id']])) {
                $pay($rule['client_id'], $by_client[$rule['client_id']], 'rule', $note);
            } elseif (in_array($rule['action'], ['payment', 'income'], true) && ($rule['client_id'] === 0 || isset($clients[$rule['client_id']]))) {
                $line['action'] = 'income';
                $line['client_id'] = $rule['client_id'];
                $line['category_id'] = isset($categories[$rule['category_id']]) ? $rule['category_id'] : 0;
                $line['matched'] = 'rule';
                $line['match_note'] = $rule['action'] === 'payment' ? 'From them before - nothing of theirs is owed now' : $note;
            }
            if ($line['matched'] !== '') {
                $lines[$i] = $line;
                continue;
            }
        }

        // 3. A payout of card payments, or a transfer from another of our accounts
        if ($kind === 'payout') {
            $line['matched'] = 'payout';
            $line['action'] = 'transfer';
            if (isset($providers[$account_id])) {
                $line['recorded'] = true;
                $line['match_note'] = 'A payout of ' . $providers[$account_id] . ' payments, recorded in this account already';
            } elseif ($providers) {
                $line['from_account_id'] = array_key_first($providers);
                $line['match_note'] = 'A payout from ' . reset($providers);
            } else {
                $line['match_note'] = 'Looks like a payout of card payments';
            }
            $lines[$i] = $line;
            continue;
        }
        if ($kind === 'transfer') {
            $line['matched'] = 'transfer';
            $line['action'] = 'transfer';
            $line_words = expenseImportWords($text);
            foreach ($account_names as $id => $words) {
                if (array_intersect($words, $line_words)) {
                    $line['from_account_id'] = $id;
                    break;
                }
            }
            $line['match_note'] = $line['from_account_id'] ? 'Looks like a transfer from ' . $accounts[$line['from_account_id']]['account_name'] : 'Looks like a transfer';
            $lines[$i] = $line;
            continue;
        }

        // 4. A client's name in it
        $client_id = incomeImportNameMatch(expenseImportWords($text), $client_names);
        if ($client_id) {
            if (!empty($by_client[$client_id])) {
                $pay($client_id, $by_client[$client_id], 'name', $clients[$client_id] . ' is named in it');
            } else {
                $line['client_id'] = $client_id;
                $line['matched'] = 'name';
                $line['match_note'] = $clients[$client_id] . ' is named in it - nothing of theirs is owed';
            }
            $lines[$i] = $line;
            continue;
        }

        // 5. The only invoice owing exactly that, or the one client whose open invoices add up to it
        if (count($balances[$cents] ?? []) === 1) {
            $invoice = $open[$balances[$cents][0]];
            $line['action'] = 'payment';
            $line['client_id'] = $invoice['client_id'];
            $line['invoice_ids'] = [$invoice['invoice_id']];
            $line['how'] = 'exact';
            $line['matched'] = 'amount';
            $line['match_note'] = 'The only invoice owing ' . $money($amount, $invoice['currency']);
        } elseif (count($client_totals[$cents] ?? []) === 1 && count($by_client[$client_totals[$cents][0]]) > 1) {
            $client_id = $client_totals[$cents][0];
            $line['action'] = 'payment';
            $line['client_id'] = $client_id;
            $line['invoice_ids'] = array_column($by_client[$client_id], 'invoice_id');
            $line['how'] = 'sum';
            $line['matched'] = 'amount';
            $line['match_note'] = 'All ' . $clients[$client_id] . ' owes';
        } elseif (count($balances[$cents] ?? []) > 1 && count(array_unique(array_map(function ($id) use ($open) { return $open[$id]['client_id']; }, $balances[$cents]))) === 1) {
            // Several of one client's invoices owe it - the oldest due
            $invoice = $open[$balances[$cents][0]];
            $line['action'] = 'payment';
            $line['client_id'] = $invoice['client_id'];
            $line['invoice_ids'] = [$invoice['invoice_id']];
            $line['how'] = 'exact';
            $line['matched'] = 'amount';
            $line['match_note'] = 'The oldest of ' . count($balances[$cents]) . ' ' . $clients[$invoice['client_id']] . ' invoices owing ' . $money($amount, $invoice['currency']);
        } elseif (count($balances[$cents] ?? []) > 1) {
            $line['match_note'] = count($balances[$cents]) . ' invoices owe ' . $money($amount) . ' - pick the one';
        }
        $lines[$i] = $line;
    }
    return $lines;
}

/**
 * Deposits already in - sets imported ('payment' or 'revenue' with
 * imported_id, the first thing it came in as) or entered (entered_type and
 * entered_ids: a payment, other income or transfer entered by hand on the
 * account for the amount within INCOME_IMPORT_DUPLICATE_DAYS days, the
 * closest, or a day's payments that add up to it), with entered_label.
 */
function incomeImportDuplicates($account_id, array $lines) {
    global $mysqli;
    $account_id = intval($account_id);
    $hashes = array_filter(array_column($lines, 'key_hash'));
    $imported = [];
    if ($hashes) {
        $in = "('" . implode("','", array_map('escapeSql', $hashes)) . "')";
        $sql = mysqli_query($mysqli, "SELECT 'payment', payment_id, payment_import_key FROM payments WHERE payment_import_key IN $in
            UNION ALL SELECT 'revenue', revenue_id, revenue_import_key FROM revenues WHERE revenue_import_key IN $in");
        while ($row = mysqli_fetch_row($sql)) {
            if (!isset($imported[$row[2]])) {
                $imported[$row[2]] = [$row[0], intval($row[1])];
            }
        }
    }

    // What was entered by hand on the account around the statement's dates
    $dates = array_filter(array_column($lines, 'date'));
    $singles = [];
    $days = [];
    if ($dates) {
        $from = date('Y-m-d', strtotime(min($dates) . ' -' . INCOME_IMPORT_DUPLICATE_DAYS . ' days'));
        $to = date('Y-m-d', strtotime(max($dates) . ' +' . INCOME_IMPORT_DUPLICATE_DAYS . ' days'));
        $sql = mysqli_query($mysqli, "SELECT payment_id, payment_date, payment_amount, CONCAT(COALESCE(invoice_prefix, ''), COALESCE(invoice_number, '')) AS what FROM payments
            LEFT JOIN invoices ON invoice_id = payment_invoice_id
            WHERE payment_account_id = $account_id AND payment_import_key IS NULL AND payment_archived_at IS NULL AND payment_date BETWEEN '$from' AND '$to'
            ORDER BY payment_date, payment_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $singles[] = ['type' => 'payment', 'ids' => [intval($row['payment_id'])], 'date' => $row['payment_date'], 'cents' => (int) round($row['payment_amount'] * 100),
                'label' => 'Payment on ' . ($row['what'] !== '' ? $row['what'] : 'an invoice') . ' on ' . $row['payment_date']];
            $days[$row['payment_date']][] = ['id' => intval($row['payment_id']), 'cents' => (int) round($row['payment_amount'] * 100), 'what' => $row['what']];
        }
        $sql = mysqli_query($mysqli, "SELECT revenue_id, revenue_date, revenue_amount, revenue_description, category_name, transfer_id FROM revenues
            LEFT JOIN categories ON category_id = revenue_category_id
            LEFT JOIN transfers ON transfer_revenue_id = revenue_id
            WHERE revenue_account_id = $account_id AND revenue_import_key IS NULL AND revenue_archived_at IS NULL AND revenue_date BETWEEN '$from' AND '$to'
            ORDER BY revenue_date, revenue_id");
        while ($row = mysqli_fetch_assoc($sql)) {
            $what = $row['transfer_id'] !== null ? 'A transfer in' : (trim(strtok((string) $row['revenue_description'], "\n")) ?: ($row['category_name'] ?: 'Other income'));
            $singles[] = ['type' => 'revenue', 'ids' => [intval($row['revenue_id'])], 'date' => $row['revenue_date'], 'cents' => (int) round($row['revenue_amount'] * 100),
                'label' => $what . ' on ' . $row['revenue_date'], 'transfer' => $row['transfer_id'] !== null];
        }
    }
    // A day's payments together - one check for several invoices
    $groups = [];
    foreach ($days as $date => $payments) {
        if (count($payments) > 1) {
            $groups[] = ['type' => 'payment', 'ids' => array_column($payments, 'id'), 'date' => $date, 'cents' => array_sum(array_column($payments, 'cents')),
                'label' => 'Payments on ' . implode(', ', array_filter(array_column($payments, 'what'))) . ' on ' . $date];
        }
    }

    $used = [];
    foreach ($lines as $i => $line) {
        $lines[$i]['imported'] = $imported[$line['key_hash']][0] ?? '';
        $lines[$i]['imported_id'] = $imported[$line['key_hash']][1] ?? 0;
        $lines[$i]['entered_type'] = '';
        $lines[$i]['entered_ids'] = [];
        $lines[$i]['entered_label'] = '';
        if ($lines[$i]['imported'] !== '' || $line['problem'] !== '') {
            continue;
        }
        $cents = (int) round($line['amount'] * 100);
        foreach ([$singles, $groups] as $pool) {
            $best = null;
            foreach ($pool as $j => $candidate) {
                if ($candidate['cents'] !== $cents || array_intersect_key(array_flip(array_map(function ($id) use ($candidate) { return $candidate['type'] . $id; }, $candidate['ids'])), $used)) {
                    continue;
                }
                $gap = abs((strtotime($candidate['date']) - strtotime($line['date'])) / 86400);
                if ($gap <= INCOME_IMPORT_DUPLICATE_DAYS && ($best === null || $gap < $best[1])) {
                    $best = [$j, $gap];
                }
            }
            if ($best) {
                $candidate = $pool[$best[0]];
                foreach ($candidate['ids'] as $id) {
                    $used[$candidate['type'] . $id] = true;
                }
                $lines[$i]['entered_type'] = $candidate['type'];
                $lines[$i]['entered_ids'] = $candidate['ids'];
                $lines[$i]['entered_label'] = $candidate['label'];
                break;
            }
        }
    }
    return $lines;
}

/**
 * Everything about a statement's deposits for the review - read, matched,
 * keyed and checked for what is in already, each with a state: new,
 * imported, entered, recorded (a payout into the payment provider's own
 * account) or problem. $charges gets how many lines are money out.
 */
function incomeImportReview(array $import, &$charges = 0) {
    $lines = incomeImportLines($import['rows'], $import['map'], $charges);
    $lines = incomeImportMatch($lines, $import['account_id'], (string) ($import['method'] ?? ''));
    $lines = expenseImportKeys($import['account_id'], $lines);
    $lines = incomeImportDuplicates($import['account_id'], $lines);
    foreach ($lines as $i => $line) {
        $lines[$i]['state'] = $line['problem'] !== '' ? 'problem' : ($line['imported'] !== '' ? 'imported' : ($line['entered_type'] !== '' ? 'entered' : (!empty($line['recorded']) ? 'recorded' : 'new')));
    }
    return $lines;
}

/** Bring a transfer in from another of our accounts - the money out of one, the money into the other, and the transfer joining them. The revenue's id. */
function incomeImportTransfer($from_account_id, $to_account_id, $date, $amount, $currency, $method, $notes, $key) {
    global $mysqli, $session_user_id;
    $amount = number_format($amount, 2, '.', '');
    $currency = escapeSql($currency);
    mysqli_query($mysqli, "INSERT INTO expenses SET expense_date = '$date', expense_amount = $amount, expense_currency_code = '$currency', expense_vendor_id = 0,
        expense_category_id = 0, expense_account_id = " . intval($from_account_id) . ", expense_created_by = " . intval($session_user_id));
    $expense_id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli, "INSERT INTO revenues SET revenue_date = '$date', revenue_amount = $amount, revenue_currency_code = '$currency', revenue_category_id = 0,
        revenue_account_id = " . intval($to_account_id) . ", revenue_created_by = " . intval($session_user_id) . ", revenue_import_key = '" . escapeSql($key) . "'");
    $revenue_id = mysqli_insert_id($mysqli);
    mysqli_query($mysqli, "INSERT INTO transfers SET transfer_expense_id = $expense_id, transfer_revenue_id = $revenue_id, transfer_method = '" . escapeSql($method) . "',
        transfer_notes = '" . escapeSql(mb_substr($notes, 0, 1000)) . "'");
    return $revenue_id;
}

/**
 * Bring in the ticked deposits. $picks is line n => [take, action, client,
 * invoices (ids), category, account]; $opts receipts (email each client
 * their receipt). Every ticked line is checked before any comes in: a
 * payment needs Sales access, the client's open invoices and no more than
 * they owe (the oldest due paid first, the last part paid); other income a
 * category; a transfer another of our accounts. Each payer is remembered,
 * and a deposit left out that looked entered already gives its key to what
 * was entered. Returns [payments, invoices_paid, invoices_part, paid,
 * income, income_total, transfers, transfer_total, receipts, linked,
 * skipped, from, to] or null with $error.
 */
function incomeImportSave(array $import, array $picks, array $opts, &$error = '') {
    global $mysqli, $session_user_id, $session_company_currency, $session_name;
    $error = '';
    $account_id = intval($import['account_id']);
    $accounts = paymentAccounts();
    if (!isset($accounts[$account_id])) {
        $error = 'The account the statement is for no longer exists';
        return null;
    }
    $currency = (string) ($accounts[$account_id]['account_currency_code'] ?: $session_company_currency);
    $lines = incomeImportReview($import);
    $can_pay = incomeImportCanPay();
    $open = $can_pay ? incomeImportOpenInvoices() : [];
    $categories = incomeCategories();
    $clients = [];
    $sql = mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id'));
    while ($row = mysqli_fetch_row($sql)) {
        $clients[intval($row[0])] = true;
    }
    $who = escapeSql(stripslashes((string) $session_name));

    // Check every ticked deposit first, working out what each payment leaves owing
    $bring = [];
    $balances = array_map(function ($invoice) { return (int) round($invoice['balance'] * 100); }, $open);
    foreach ($lines as $line) {
        $pick = $picks[$line['n']] ?? null;
        if (!$pick || empty($pick['take']) || !in_array($line['state'], ['new', 'entered', 'recorded'], true)) {
            continue;
        }
        $what = date('M j', strtotime($line['date'])) . ' ' . billingMoney($line['amount'], $currency);
        $action = (string) ($pick['action'] ?? '');
        $client = intval($pick['client'] ?? 0);
        if ($client && !isset($clients[$client])) {
            $error = "The client picked for $what isn't one you can open";
            return null;
        }
        $item = ['line' => $line, 'action' => $action, 'client' => $client, 'category' => 0, 'account' => 0, 'payments' => []];
        if ($action === 'payment') {
            if (!$can_pay) {
                $error = 'Recording payments on invoices takes write access to Sales - ' . $what . ' can come in as other income';
                return null;
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($pick['invoices'] ?? [])))));
            if (!$client || !$ids) {
                $error = "Pick the client and the invoices $what pays";
                return null;
            }
            $invoices = [];
            foreach ($ids as $id) {
                if (!isset($open[$id]) || $open[$id]['client_id'] !== $client) {
                    $error = "An invoice picked for $what isn't one of the client's still owed";
                    return null;
                }
                $invoices[] = $open[$id];
            }
            usort($invoices, function ($a, $b) { return [$a['due'], $a['invoice_id']] <=> [$b['due'], $b['invoice_id']]; });
            $left = (int) round($line['amount'] * 100);
            foreach ($invoices as $invoice) {
                $owed = $balances[$invoice['invoice_id']];
                if ($left <= 0 || $owed <= 0) {
                    continue;
                }
                $take = min($left, $owed);
                $item['payments'][] = ['invoice' => $invoice, 'cents' => $take];
                $balances[$invoice['invoice_id']] -= $take;
                $left -= $take;
            }
            if ($left > 0) {
                $error = "$what is " . billingMoney($left / 100, $currency) . ' more than the invoices picked owe - pick another, or bring it in as other income';
                return null;
            }
        } elseif ($action === 'income') {
            $category = intval($pick['category'] ?? 0);
            if (!isset($categories[$category])) {
                $error = "Pick the category for $what";
                return null;
            }
            $item['category'] = $category;
        } elseif ($action === 'transfer') {
            $from = intval($pick['account'] ?? 0);
            if (!isset($accounts[$from]) || $from === $account_id) {
                $error = "Pick the account $what came from";
                return null;
            }
            $item['account'] = $from;
        } else {
            $error = "Pick what $what is";
            return null;
        }
        $bring[] = $item;
    }

    $statuses = [];
    $result = ['payments' => 0, 'invoices_paid' => 0, 'invoices_part' => 0, 'paid' => 0.0, 'income' => 0, 'income_total' => 0.0, 'transfers' => 0, 'transfer_total' => 0.0,
        'receipts' => 0, 'linked' => 0, 'from' => null, 'to' => null, 'skipped' => count(array_filter($lines, function ($l) { return $l['state'] === 'imported'; }))];
    foreach ($bring as $item) {
        $line = $item['line'];
        $date = $line['date'];
        $key = escapeSql($line['key_hash']);
        $method = (string) $line['method'];
        // The bank's text says who paid when it is more than "DEPOSIT" - it is the payment's reference when the statement has none
        $words = array_diff(expenseImportWords($line['description']), ['deposit', 'deposits', 'mobile', 'remote', 'branch', 'counter', 'teller', 'incoming', 'item', 'items']);
        $reference = mb_substr($line['reference'] !== '' ? $line['reference'] : ($words ? $line['description'] : ''), 0, 200);
        if ($item['action'] === 'payment') {
            foreach ($item['payments'] as $part) {
                $invoice = $part['invoice'];
                $invoice_id = $invoice['invoice_id'];
                $amount = number_format($part['cents'] / 100, 2, '.', '');
                mysqli_query($mysqli, "INSERT INTO payments SET payment_date = '$date', payment_amount = $amount, payment_currency_code = '" . escapeSql($invoice['currency'] ?: $currency) . "',
                    payment_account_id = $account_id, payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ", payment_reference = '" . escapeSql($reference) . "',
                    payment_invoice_id = $invoice_id, payment_import_key = '$key'");
                $status = updateInvoiceStatusFromPayments($invoice_id);
                mysqli_query($mysqli, "INSERT INTO history SET history_status = '" . escapeSql((string) $status) . "', history_description = 'Payment added from a bank statement', history_invoice_id = $invoice_id");
                logAudit("Invoice", "Payment", "$who added a payment of " . escapeSql(billingMoney($amount, $invoice['currency'])) . " to invoice " . escapeSql($invoice['ref']) . " from a bank statement", $invoice['client_id'], $invoice_id);
                if (!empty($opts['receipts'])) {
                    $sent = billingPaymentSend($invoice_id, floatval($amount), ['date' => $date, 'method' => $method, 'reference' => $reference]);
                    if ($sent) {
                        $result['receipts']++;
                        logAudit("Invoice", "Payment", "Payment receipt for invoice " . escapeSql($invoice['ref']) . " queued to " . escapeSql(implode(', ', $sent)), $invoice['client_id'], $invoice_id);
                    }
                }
                triggerCustomAction('invoice_pay', $invoice_id);
                $result['payments']++;
                $statuses[$invoice_id] = $status;
            }
            $result['paid'] += $line['amount'];
        } elseif ($item['action'] === 'income') {
            mysqli_query($mysqli, "INSERT INTO revenues SET revenue_date = '$date', revenue_amount = " . number_format($line['amount'], 2, '.', '') . ", revenue_currency_code = '" . escapeSql($currency) . "',
                revenue_account_id = $account_id, revenue_category_id = {$item['category']}, revenue_client_id = {$item['client']},
                revenue_payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ", revenue_reference = '" . escapeSql(mb_substr($line['reference'], 0, 200)) . "',
                revenue_description = '" . escapeSql($line['description']) . "', revenue_created_by = " . intval($session_user_id) . ", revenue_import_key = '$key'");
            $revenue_id = mysqli_insert_id($mysqli);
            logAudit("Revenue", "Create", "$who added other income " . escapeSql(mb_substr($line['description'], 0, 80) . ' - ' . billingMoney($line['amount'], $currency)) . " from a bank statement", $item['client'], $revenue_id);
            $result['income']++;
            $result['income_total'] += $line['amount'];
        } else {
            incomeImportTransfer($item['account'], $account_id, $date, $line['amount'], $currency, $method !== '' ? $method : 'Bank Transfer', $line['description'], $line['key_hash']);
            logAudit("Account Transfer", "Create", "$who brought in a transfer of " . escapeSql(billingMoney($line['amount'], $currency)) . " from " . escapeSql($accounts[$item['account']]['account_name'])
                . " to " . escapeSql($accounts[$account_id]['account_name']) . " from a bank statement");
            $result['transfers']++;
            $result['transfer_total'] += $line['amount'];
        }
        $result['from'] = $result['from'] === null || $date < $result['from'] ? $date : $result['from'];
        $result['to'] = $result['to'] === null || $date > $result['to'] ? $date : $result['to'];
        if ($line['key'] !== '') {
            mysqli_query($mysqli, "INSERT INTO income_import_rules SET import_rule_match = '" . escapeSql($line['key']) . "', import_rule_action = '{$item['action']}',
                import_rule_client_id = {$item['client']}, import_rule_category_id = {$item['category']}, import_rule_account_id = {$item['account']}, import_rule_used_count = 1
                ON DUPLICATE KEY UPDATE import_rule_action = VALUES(import_rule_action), import_rule_client_id = VALUES(import_rule_client_id),
                import_rule_category_id = VALUES(import_rule_category_id), import_rule_account_id = VALUES(import_rule_account_id), import_rule_used_count = import_rule_used_count + 1");
        }
    }

    // Deposits left out that were entered by hand already - those take their keys
    foreach ($lines as $line) {
        $pick = $picks[$line['n']] ?? null;
        if ($line['state'] === 'entered' && (!$pick || empty($pick['take']))) {
            $table = $line['entered_type'] === 'payment' ? 'payments' : 'revenues';
            $column = $line['entered_type'] === 'payment' ? 'payment' : 'revenue';
            mysqli_query($mysqli, "UPDATE $table SET {$column}_import_key = '" . escapeSql($line['key_hash']) . "' WHERE {$column}_id IN (" . implode(',', array_map('intval', $line['entered_ids'])) . ") AND {$column}_import_key IS NULL");
            $result['linked'] += mysqli_affected_rows($mysqli) ? 1 : 0;
        }
    }
    // Each invoice once, as it was left
    $result['invoices_paid'] = count(array_filter($statuses, function ($status) { return $status === 'Paid'; }));
    $result['invoices_part'] = count($statuses) - $result['invoices_paid'];
    foreach (['paid', 'income_total', 'transfer_total'] as $total) {
        $result[$total] = round($result[$total], 2);
    }
    return $result;
}
