<?php

/*
 * ITFlow - importing a card or bank statement as expenses.
 *
 * A statement exported as CSV is read (any delimiter, a header row found
 * among the first lines or none), its columns are guessed - the date, the
 * description, the amount or separate money out and money in, a reference -
 * along with the date format and whether charges are positive or negative,
 * and the guess is remembered on the account for its next statement.
 *
 * Each line is matched to a vendor: first by what was picked for the same
 * merchant last time (expense_import_rules, keyed on the start of the
 * cleaned-up description), then by one of our vendors' names appearing in
 * it, with the category and client that came with it, or the vendor's last
 * category. Lines with no match suggest a new vendor named from the text.
 *
 * Every line has a key (expense_import_key) from the account, date, amount,
 * text and which repeat of those it is in the file, so the same statement
 * can't bring a line in twice. A line not imported before but with the same
 * amount on the same account within a few days of an expense entered by
 * hand looks entered already - left out unless ticked, and if left out the
 * hand-entered one takes its key, so the next statement knows it too.
 */

const EXPENSE_IMPORT_MAX_BYTES = 2097152;
const EXPENSE_IMPORT_MAX_LINES = 2000;
const EXPENSE_IMPORT_DUPLICATE_DAYS = 4;

/** The date formats a statement can use - format => an example. */
function expenseImportDateFormats() {
    return [
        'm/d/Y' => '10/14/2026', 'm/d/y' => '10/14/26', 'Y-m-d' => '2026-10-14', 'd/m/Y' => '14/10/2026', 'd/m/y' => '14/10/26',
        'd.m.Y' => '14.10.2026', 'Y/m/d' => '2026/10/14', 'm-d-Y' => '10-14-2026', 'd-M-Y' => '14-Oct-2026', 'd-M-y' => '14-Oct-26',
        'd M Y' => '14 Oct 2026', 'M d, Y' => 'Oct 14, 2026', 'Ymd' => '20261014',
    ];
}

/**
 * Read a statement CSV - [header (a name for each column), rows (lists of
 * cells)], or null with $error. Handles a byte order mark, Windows text,
 * commas, semicolons, tabs or pipes, lines before the header, and a file
 * with no header at all.
 */
function expenseImportRead($path, &$error = '') {
    $error = '';
    $text = @file_get_contents($path);
    if ($text === false || trim($text) === '') {
        $error = 'That file is empty';
        return null;
    }
    if (strlen($text) > EXPENSE_IMPORT_MAX_BYTES) {
        $error = 'That file is too big - export a month or two at a time';
        return null;
    }
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (strpos($text, "\0") !== false) {
        $error = "That doesn't look like a CSV file - export the statement as CSV";
        return null;
    }

    // The delimiter the first lines agree on most
    $sample = array_slice(array_filter(explode("\n", $text), 'strlen'), 0, 20);
    $delimiter = ',';
    $best = 0;
    foreach ([',', ';', "\t", '|'] as $candidate) {
        $counts = array_map(function ($line) use ($candidate) { return count(str_getcsv($line, $candidate, '"', '')); }, $sample);
        $common = array_count_values($counts);
        arsort($common);
        $width = intval(array_key_first($common));
        if ($width > 1 && $common[$width] * $width > $best) {
            $best = $common[$width] * $width;
            $delimiter = $candidate;
        }
    }

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $text);
    rewind($handle);
    $rows = [];
    while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
        $cells = array_map(function ($cell) { return trim((string) $cell); }, $cells);
        if (implode('', $cells) === '') {
            continue;
        }
        $rows[] = $cells;
        if (count($rows) > EXPENSE_IMPORT_MAX_LINES + 20) {
            break;
        }
    }
    fclose($handle);
    if (count($rows) < 1) {
        $error = 'There are no lines in that file';
        return null;
    }

    // The header is the last line before the first that has a date in it
    $first_data = null;
    foreach (array_slice($rows, 0, 20, true) as $i => $cells) {
        foreach ($cells as $cell) {
            if (expenseImportLooksLikeDate($cell)) {
                $first_data = $i;
                break 2;
            }
        }
    }
    if ($first_data === null) {
        $error = "There are no dates in that file - export the statement's transactions as CSV";
        return null;
    }
    $width = max(array_map('count', array_slice($rows, $first_data, 50)));
    if ($first_data > 0) {
        $header = $rows[$first_data - 1];
    } else {
        $header = [];
    }
    $names = [];
    for ($c = 0; $c < $width; $c++) {
        $name = trim((string) ($header[$c] ?? ''));
        $names[] = $name !== '' ? mb_substr($name, 0, 60) : 'Column ' . ($c + 1);
    }
    $data = [];
    foreach (array_slice($rows, $first_data) as $cells) {
        $data[] = array_pad(array_slice($cells, 0, $width), $width, '');
    }
    if (count($data) > EXPENSE_IMPORT_MAX_LINES) {
        $error = 'That file has more than ' . EXPENSE_IMPORT_MAX_LINES . ' lines - export a shorter period';
        return null;
    }
    return ['header' => $names, 'rows' => $data];
}

/** Does a cell look like a date in any of the formats? */
function expenseImportLooksLikeDate($value) {
    $value = trim((string) $value);
    if ($value === '' || strlen($value) > 30 || !preg_match('/\d/', $value)) {
        return false;
    }
    foreach (array_keys(expenseImportDateFormats()) as $format) {
        if (expenseImportDate($value, $format) !== null) {
            return true;
        }
    }
    return false;
}

/** A date in a format, as Y-m-d - or null. A time after it is ignored. */
function expenseImportDate($value, $format) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (strpos($format, ' ') === false) {
        $value = preg_split('/[\sT]/', $value)[0];
    } else {
        $value = preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?(\s*[AP]M)?$/i', '', $value);
    }
    if ($format === 'Ymd' && !preg_match('/^\d{8}$/', $value)) {
        return null;
    }
    $date = DateTime::createFromFormat('!' . $format, $value);
    $errors = DateTime::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
        return null;
    }
    $year = intval($date->format('Y'));
    if ($year < 1990 || $year > 2100) {
        return null;
    }
    return $date->format('Y-m-d');
}

/**
 * An amount from a statement - "$1,234.56", "-12", "(12.00)", "12.00 CR",
 * "1.234,56", "USD 9.99" - as a number, or null. CR and brackets are money
 * in, written negative; DR is money out.
 */
function expenseImportAmount($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $negative = false;
    if (preg_match('/^\((.*)\)$/', $value, $m)) {
        $value = $m[1];
        $negative = true;
    }
    if (preg_match('/\s*\b(CR|DR)\.?$/i', $value, $m)) {
        $negative = strtoupper($m[1]) === 'CR';
        $value = trim(substr($value, 0, -strlen($m[0])));
    }
    $value = preg_replace('/[^\d.,\-+]/u', '', $value);
    if ($value === '' || !preg_match('/\d/', $value)) {
        return null;
    }
    if (substr_count($value, '-') + substr_count($value, '+') > 1) {
        return null;
    }
    if (preg_match('/^[+-]?\d{1,3}(\.\d{3})*,\d{1,2}$/', $value) || preg_match('/^[+-]?\d+,\d{1,2}$/', $value)) {
        $value = str_replace(['.', ','], ['', '.'], $value);
    } else {
        $value = str_replace(',', '', $value);
    }
    if (!is_numeric($value)) {
        return null;
    }
    $amount = round(floatval($value), 2);
    return $negative ? -abs($amount) : $amount;
}

/**
 * A first guess at what each column is - [date, description, memo, amount,
 * debit, credit, reference] as column numbers (null where there is none),
 * with date_format and sign ('positive' when charges are positive). From
 * the header names first, then from what the cells hold.
 */
function expenseImportGuess(array $header, array $rows) {
    $map = ['date' => null, 'description' => null, 'memo' => null, 'amount' => null, 'debit' => null, 'credit' => null, 'reference' => null];
    $patterns = [
        'date' => '/^(transaction |trans\.? |posted |post |posting )?date$|^date posted$|^posted$|^date$/i',
        'description' => '/^(description|payee|merchant|name|details|transaction description|narrative|particulars|merchant name|transaction)$/i',
        'memo' => '/^(memo|notes?|extended description|additional info)$/i',
        'amount' => '/^(amount|transaction amount|amt|value|amount \(\w+\))$/i',
        'debit' => '/^(debit|debits|withdrawal|withdrawals|money out|charge|charges|paid out|debit amount|outflow)$/i',
        'credit' => '/^(credit|credits|deposit|deposits|money in|payment|payments|paid in|credit amount|inflow)$/i',
        'reference' => '/^(reference|ref|ref\.|reference number|check|check number|check #|chk|transaction id|id|confirmation)$/i',
    ];
    foreach ($header as $c => $name) {
        foreach ($patterns as $field => $pattern) {
            if ($map[$field] === null && preg_match($pattern, trim($name))) {
                $map[$field] = $c;
                break;
            }
        }
    }
    // Prefer the transaction date over a posted date when both are there
    foreach ($header as $c => $name) {
        if (preg_match('/^trans(action)?\.? date$/i', trim($name))) {
            $map['date'] = $c;
        }
    }

    // What the cells hold, for the columns the names didn't settle
    $sample = array_slice($rows, 0, 60);
    $share = function ($c, $test) use ($sample) {
        $hits = 0;
        $filled = 0;
        foreach ($sample as $cells) {
            $value = trim((string) ($cells[$c] ?? ''));
            if ($value === '') {
                continue;
            }
            $filled++;
            $hits += $test($value) ? 1 : 0;
        }
        return $filled ? $hits / $filled : 0;
    };
    $width = count($header);
    if ($map['date'] === null) {
        for ($c = 0; $c < $width; $c++) {
            if ($share($c, 'expenseImportLooksLikeDate') >= 0.8) {
                $map['date'] = $c;
                break;
            }
        }
    }
    if ($map['amount'] === null && $map['debit'] === null) {
        for ($c = $width - 1; $c >= 0; $c--) {
            if ($c !== $map['date'] && $c !== $map['reference'] && $share($c, function ($v) { return expenseImportAmount($v) !== null && !expenseImportLooksLikeDate($v) && preg_match('/[.,]\d{2}\)?$|^-?\d+$/', $v); }) >= 0.8) {
                $map['amount'] = $c;
                break;
            }
        }
    }
    if ($map['description'] === null) {
        $longest = 0;
        for ($c = 0; $c < $width; $c++) {
            if (in_array($c, [$map['date'], $map['amount'], $map['debit'], $map['credit'], $map['reference']], true)) {
                continue;
            }
            $length = 0;
            foreach ($sample as $cells) {
                $value = (string) ($cells[$c] ?? '');
                $length += preg_match('/[a-z]/i', $value) ? mb_strlen($value) : 0;
            }
            if ($length > $longest) {
                $longest = $length;
                $map['description'] = $c;
            }
        }
    }

    // The date format every date parses in - the US one first when both fit
    $map['date_format'] = 'm/d/Y';
    if ($map['date'] !== null) {
        foreach (array_keys(expenseImportDateFormats()) as $format) {
            if ($share($map['date'], function ($v) use ($format) { return expenseImportDate($v, $format) !== null; }) === 1) {
                $map['date_format'] = $format;
                break;
            }
        }
    }

    // Charges are what most lines are
    $map['sign'] = 'positive';
    if ($map['amount'] !== null) {
        $negative = 0;
        $positive = 0;
        foreach ($sample as $cells) {
            $amount = expenseImportAmount($cells[$map['amount']] ?? '');
            if ($amount !== null && $amount != 0) {
                $amount < 0 ? $negative++ : $positive++;
            }
        }
        $map['sign'] = $negative > $positive ? 'negative' : 'positive';
    }
    return $map;
}

/** A mapping from a form or a saved one, checked against the columns there are - or null when it misses the date, description or an amount. */
function expenseImportMapClean(array $in, $width) {
    $map = [];
    foreach (['date', 'description', 'memo', 'amount', 'debit', 'credit', 'reference'] as $field) {
        $value = $in[$field] ?? null;
        $map[$field] = $value === null || $value === '' || intval($value) < 0 || intval($value) >= $width ? null : intval($value);
    }
    $map['date_format'] = isset(expenseImportDateFormats()[$in['date_format'] ?? '']) ? $in['date_format'] : 'm/d/Y';
    $map['sign'] = ($in['sign'] ?? '') === 'negative' ? 'negative' : 'positive';
    if (($in['amounts'] ?? ($map['amount'] !== null ? 'one' : 'two')) === 'two') {
        $map['amount'] = null;
    } else {
        $map['debit'] = $map['credit'] = null;
    }
    if ($map['date'] === null || $map['description'] === null || ($map['amount'] === null && $map['debit'] === null && $map['credit'] === null)) {
        return null;
    }
    return $map;
}

/**
 * The statement's lines by a mapping - each [n, date, description (with
 * the memo), match_text (without), amount (a charge positive, money in
 * negative), reference, problem]. Lines with nothing like a date, such as
 * totals, are left out.
 */
function expenseImportLines(array $rows, array $map) {
    $lines = [];
    foreach ($rows as $n => $cells) {
        $date = expenseImportDate($cells[$map['date']] ?? '', $map['date_format']);
        if ($map['amount'] !== null) {
            $value = expenseImportAmount($cells[$map['amount']] ?? '');
            $amount = $value === null ? null : ($map['sign'] === 'negative' ? -$value : $value);
        } else {
            $debit = $map['debit'] !== null ? expenseImportAmount($cells[$map['debit']] ?? '') : null;
            $credit = $map['credit'] !== null ? expenseImportAmount($cells[$map['credit']] ?? '') : null;
            $amount = $debit === null && $credit === null ? null : round(abs((float) $debit) - abs((float) $credit), 2);
        }
        // Totals and notes - nothing like a date where the date goes
        if (($date === null && $amount === null) || ($date === null && !preg_match('/\d/', (string) ($cells[$map['date']] ?? '')))) {
            continue;
        }
        $description = trim(preg_replace('/\s+/u', ' ', (string) ($cells[$map['description']] ?? '')));
        $match_text = $description;
        $memo = $map['memo'] !== null ? trim(preg_replace('/\s+/u', ' ', (string) ($cells[$map['memo']] ?? ''))) : '';
        if ($memo !== '' && $memo !== $description) {
            $description = trim("$description - $memo", ' -');
        }
        $problem = $date === null ? "The date isn't in the format picked" : ($amount === null ? 'There is no amount' : ($amount == 0 ? 'The amount is zero' : ''));
        $lines[] = [
            'n' => count($lines),
            'date' => $date,
            'description' => mb_substr($description, 0, 500),
            'match_text' => $match_text,
            'amount' => $amount === null ? null : round($amount, 2),
            'reference' => $map['reference'] !== null ? mb_substr(trim((string) ($cells[$map['reference']] ?? '')), 0, 200) : '',
            'problem' => $problem,
        ];
    }
    return $lines;
}

/** Words that say how a line was paid, not who to - left out of matching. */
function expenseImportNoiseWords() {
    return ['pos', 'purchase', 'purchases', 'debit', 'credit', 'card', 'checkcard', 'check', 'recurring', 'authorized', 'preauthorized', 'auth', 'ach', 'web', 'online',
        'pmt', 'pymt', 'payment', 'bill', 'billpay', 'visa', 'mastercard', 'mc', 'amex', 'withdrawal', 'transaction', 'trans', 'ppd', 'ccd', 'ref', 'id', 'on', 'at',
        'inc', 'llc', 'ltd', 'co', 'corp', 'the', 'www', 'com', 'net', 'org', 'us', 'usa', 'intl', 'sq', 'tst', 'sp', 'pp', 'ext'];
}

/** A description's words for matching - lowercase letters only, the card, store and payment noise taken out. */
function expenseImportWords($description) {
    $text = mb_strtolower((string) $description);
    $text = preg_replace('/\b(sq|tst|sp|pp|paypal|dd|ckc|cke|fsp|py)\s?\*\s?/u', ' ', $text);
    $text = preg_replace('/\*\S*/u', ' ', $text);
    $text = preg_replace('/\b\d{1,2}\/\d{1,2}(\/\d{2,4})?\b/u', ' ', $text);
    $text = preg_replace('/\.(com|net|org|io|co|us)\b/u', ' ', $text);
    $text = preg_replace('/[^\p{L}\s]+/u', ' ', $text);
    $noise = array_flip(expenseImportNoiseWords());
    $words = [];
    foreach (preg_split('/\s+/u', trim($text)) as $word) {
        if (mb_strlen($word) > 1 && !isset($noise[$word])) {
            $words[] = $word;
        }
    }
    return $words;
}

/** What a line is matched on - the first three of its words. '' when there are none. */
function expenseImportMatchKey($description) {
    return mb_substr(implode(' ', array_slice(expenseImportWords($description), 0, 3)), 0, 200);
}

/**
 * A tidy vendor name from a description - "SQ *JOES COFFEE #123 PITTSBURGH
 * PA" is "Joes Coffee": processor prefixes, numbers and card noise out, and
 * a town and state on the end too.
 */
function expenseImportVendorName($description) {
    $text = preg_replace('/\b(sq|tst|sp|pp|paypal|dd|py)\s?\*\s?/iu', ' ', (string) $description);
    $text = preg_replace('/\*\S*|#\S*|\b\S*\d\S*\b/u', ' ', $text);
    $text = preg_replace('/[^\p{L}&\'\s.-]+/u', ' ', $text);
    $noise = array_flip(['pos', 'purchase', 'debit', 'card', 'checkcard', 'recurring', 'ach', 'pmt', 'payment', 'authorized', 'preauthorized', 'withdrawal', 'on']);
    // Statements in capitals get title case; mixed case is kept as it is written
    $shouting = mb_strtoupper($text) === $text;
    $words = [];
    foreach (preg_split('/\s+/u', trim($text)) as $word) {
        $word = trim($word, '.-,');
        if ($word !== '' && !isset($noise[mb_strtolower($word)])) {
            $words[] = $word;
        }
    }
    // A state on the end, and the town before it when that leaves a name
    $states = ['AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO',
        'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'];
    if (count($words) > 1 && in_array(end($words), $states, true)) {
        array_pop($words);
        if (count($words) >= 3) {
            array_pop($words);
        }
    }
    $words = array_slice($words, 0, 3);
    if ($shouting) {
        $words = array_map(function ($word) { return mb_convert_case(mb_strtolower($word), MB_CASE_TITLE); }, $words);
    }
    return mb_substr(implode(' ', $words), 0, 200) ?: 'New vendor';
}

/** The remembered merchants - match => [vendor_id, category_id, client_id, billable, id]. */
function expenseImportRules() {
    global $mysqli;
    $rules = [];
    $sql = mysqli_query($mysqli, "SELECT import_rule_id, import_rule_match, import_rule_vendor_id, import_rule_category_id, import_rule_client_id, import_rule_billable
        FROM expense_import_rules JOIN vendors ON vendor_id = import_rule_vendor_id AND vendor_archived_at IS NULL AND vendor_client_id = 0");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rules[$row['import_rule_match']] = ['id' => intval($row['import_rule_id']), 'vendor_id' => intval($row['import_rule_vendor_id']),
            'category_id' => intval($row['import_rule_category_id']), 'client_id' => intval($row['import_rule_client_id']), 'billable' => intval($row['import_rule_billable'])];
    }
    return $rules;
}

/**
 * Match each line to a vendor - sets key (the match key), vendor_id,
 * category_id, client_id, billable, matched ('rule', 'name' or '') and
 * new_vendor (a name to make one with when nothing matched).
 */
function expenseImportMatch(array $lines) {
    $rules = expenseImportRules();
    uksort($rules, function ($a, $b) { return mb_strlen($b) <=> mb_strlen($a); });
    $defaults = expenseVendorDefaults();
    $vendor_words = [];
    foreach (expenseVendors() as $vendor_id => $vendor) {
        $words = expenseImportWords($vendor['vendor_name']);
        if ($words && mb_strlen(implode(' ', $words)) >= 3) {
            $vendor_words[$vendor_id] = ' ' . implode(' ', $words) . ' ';
        }
    }
    uasort($vendor_words, function ($a, $b) { return mb_strlen($b) <=> mb_strlen($a); });

    foreach ($lines as $i => $line) {
        $key = expenseImportMatchKey($line['match_text'] ?? $line['description']);
        $line += ['key' => $key, 'vendor_id' => 0, 'category_id' => 0, 'client_id' => 0, 'billable' => 0, 'matched' => '', 'new_vendor' => ''];
        $line['key'] = $key;
        $rule = null;
        if ($key !== '') {
            foreach ($rules as $match => $candidate) {
                if ($key === $match || strpos($key, $match . ' ') === 0) {
                    $rule = $candidate;
                    break;
                }
            }
        }
        if ($rule) {
            $line['vendor_id'] = $rule['vendor_id'];
            $line['category_id'] = $rule['category_id'];
            $line['client_id'] = $rule['client_id'];
            $line['billable'] = $rule['billable'];
            $line['matched'] = 'rule';
        } else {
            $line_words = expenseImportWords($line['match_text'] ?? $line['description']);
            $text = ' ' . implode(' ', $line_words) . ' ';
            foreach ($vendor_words as $vendor_id => $words) {
                if (strpos($text, $words) !== false) {
                    $line['vendor_id'] = $vendor_id;
                    $line['matched'] = 'name';
                    break;
                }
            }
            // "THREE RIVERS COLO" for Three Rivers Colocation - the words in order, the last one cut short
            if (!$line['vendor_id']) {
                foreach ($vendor_words as $vendor_id => $words) {
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
                            $line['vendor_id'] = $vendor_id;
                            $line['matched'] = 'name';
                            break 2;
                        }
                    }
                }
            }
        }
        if ($line['vendor_id'] && !$line['category_id'] && isset($defaults[$line['vendor_id']])) {
            $line['category_id'] = $defaults[$line['vendor_id']]['category'];
        }
        if (!$line['vendor_id']) {
            $line['new_vendor'] = expenseImportVendorName($line['match_text'] ?? $line['description']);
        }
        $lines[$i] = $line;
    }
    return $lines;
}

/**
 * The key of each line - the account, date, amount and cleaned text, and
 * which repeat of those it is in the file (two coffees on one day are two
 * lines). Sets key_hash on each.
 */
function expenseImportKeys($account_id, array $lines) {
    $seen = [];
    foreach ($lines as $i => $line) {
        $base = intval($account_id) . '|' . $line['date'] . '|' . number_format((float) $line['amount'], 2, '.', '') . '|' . mb_strtolower(preg_replace('/\s+/u', ' ', $line['description']));
        $seen[$base] = ($seen[$base] ?? 0) + 1;
        $lines[$i]['key_hash'] = hash('sha256', $base . '|' . $seen[$base]);
    }
    return $lines;
}

/**
 * Lines already in - sets imported (the expense id a line came in as
 * before) or entered (an expense entered by hand on the account with the
 * same amount within EXPENSE_IMPORT_DUPLICATE_DAYS days, the closest one,
 * each matched once), with entered_label to show for it.
 */
function expenseImportDuplicates($account_id, array $lines) {
    global $mysqli;
    $account_id = intval($account_id);
    $hashes = array_filter(array_column($lines, 'key_hash'));
    $imported = [];
    if ($hashes) {
        $sql = mysqli_query($mysqli, "SELECT expense_id, expense_import_key FROM expenses WHERE expense_import_key IN ('" . implode("','", array_map('escapeSql', $hashes)) . "')");
        while ($row = mysqli_fetch_row($sql)) {
            $imported[$row[1]] = intval($row[0]);
        }
    }
    $dates = array_filter(array_column($lines, 'date'));
    $existing = [];
    if ($dates) {
        $from = date('Y-m-d', strtotime(min($dates) . ' -' . EXPENSE_IMPORT_DUPLICATE_DAYS . ' days'));
        $to = date('Y-m-d', strtotime(max($dates) . ' +' . EXPENSE_IMPORT_DUPLICATE_DAYS . ' days'));
        $sql = mysqli_query($mysqli, "SELECT expense_id, expense_date, expense_amount, vendor_name FROM expenses LEFT JOIN vendors ON vendor_id = expense_vendor_id
            WHERE expense_account_id = $account_id AND expense_vendor_id > 0 AND expense_import_key IS NULL AND expense_archived_at IS NULL
            AND expense_date BETWEEN '$from' AND '$to'");
        while ($row = mysqli_fetch_assoc($sql)) {
            $existing[intval($row['expense_id'])] = $row;
        }
    }
    $used = [];
    foreach ($lines as $i => $line) {
        $lines[$i]['imported'] = $imported[$line['key_hash']] ?? 0;
        $lines[$i]['entered'] = 0;
        $lines[$i]['entered_label'] = '';
        if ($lines[$i]['imported'] || $line['problem'] !== '') {
            continue;
        }
        $best = null;
        foreach ($existing as $expense_id => $row) {
            if (isset($used[$expense_id]) || abs(floatval($row['expense_amount']) - $line['amount']) >= 0.005) {
                continue;
            }
            $days = abs((strtotime($row['expense_date']) - strtotime($line['date'])) / 86400);
            if ($days <= EXPENSE_IMPORT_DUPLICATE_DAYS && ($best === null || $days < $best[1])) {
                $best = [$expense_id, $days];
            }
        }
        if ($best) {
            $used[$best[0]] = true;
            $lines[$i]['entered'] = $best[0];
            $lines[$i]['entered_label'] = trim($existing[$best[0]]['vendor_name'] . ' on ' . $existing[$best[0]]['expense_date']);
        }
    }
    return $lines;
}

/** Everything about a statement's lines for the review - read, matched, keyed and checked for duplicates. */
function expenseImportReview(array $import) {
    $lines = expenseImportLines($import['rows'], $import['map']);
    $lines = expenseImportMatch($lines);
    $lines = expenseImportKeys($import['account_id'], $lines);
    return expenseImportDuplicates($import['account_id'], $lines);
}

/** The mapping an account's last statement used, if its columns are the same as these. */
function expenseImportSavedMap($account_id, array $header) {
    $saved = json_decode((string) getFieldById('accounts', intval($account_id), 'account_import_map'), true);
    if (!is_array($saved) || ($saved['columns'] ?? '') !== expenseImportColumnsKey($header) || !is_array($saved['map'] ?? null)) {
        return null;
    }
    return expenseImportMapClean($saved['map'] + ['amounts' => ($saved['map']['amount'] ?? null) !== null ? 'one' : 'two'], count($header));
}

/** What identifies a statement's layout - its column names. */
function expenseImportColumnsKey(array $header) {
    return md5(mb_strtolower(implode('|', $header)));
}

/** Remember the mapping for the account's next statement. */
function expenseImportSaveMap($account_id, array $header, array $map) {
    global $mysqli;
    $json = json_encode(['columns' => expenseImportColumnsKey($header), 'map' => $map]);
    mysqli_query($mysqli, "UPDATE accounts SET account_import_map = '" . escapeSql($json) . "' WHERE account_id = " . intval($account_id));
}

/**
 * Bring in the ticked lines as expenses. $picks is line n => [vendor (an id,
 * or 'new' with new_vendor), category, client, billable]; $import the
 * statement in the session. New vendors are made once per name (or an
 * existing one by that name is used), each line's merchant is remembered for
 * next time, and a line left out that looked entered already gives its key to
 * that expense. Returns [imported, total, vendors (made), linked, skipped] or
 * null with $error.
 */
function expenseImportSave(array $import, array $picks, &$error = '') {
    global $mysqli, $session_user_id, $session_company_currency;
    $error = '';
    $account_id = intval($import['account_id']);
    $accounts = paymentAccounts();
    if (!isset($accounts[$account_id])) {
        $error = 'The account the statement is for no longer exists';
        return null;
    }
    $method = (string) $import['method'];
    if ($method !== '' && !in_array($method, paymentMethods(), true)) {
        $method = '';
    }
    $currency = escapeSql((string) ($accounts[$account_id]['account_currency_code'] ?: $session_company_currency));
    $lines = expenseImportReview($import);
    $vendors = expenseVendors();
    $categories = expenseCategories();
    $markup = expenseMarkupDefault();
    $clients = [];
    $sql = mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id'));
    while ($row = mysqli_fetch_row($sql)) {
        $clients[intval($row[0])] = true;
    }

    // Check every ticked line before bringing any in
    $bring = [];
    foreach ($lines as $line) {
        $pick = $picks[$line['n']] ?? null;
        if (!$pick || empty($pick['take']) || $line['imported'] || $line['problem'] !== '') {
            continue;
        }
        $vendor = (string) ($pick['vendor'] ?? '');
        $new_name = trim((string) ($pick['new_vendor'] ?? ''));
        $category = intval($pick['category'] ?? 0);
        $client = intval($pick['client'] ?? 0);
        $what = date('M j', strtotime($line['date'])) . ' ' . billingMoney(abs($line['amount']), $accounts[$account_id]['account_currency_code']);
        if ($vendor === 'new' ? $new_name === '' : !isset($vendors[intval($vendor)])) {
            $error = "Pick the vendor for $what";
            return null;
        }
        if (!isset($categories[$category])) {
            $error = "Pick the category for $what";
            return null;
        }
        if ($client && !isset($clients[$client])) {
            $error = "The client picked for $what isn't one you can file expenses against";
            return null;
        }
        $bring[] = ['line' => $line, 'vendor' => $vendor === 'new' ? 0 : intval($vendor), 'new_vendor' => mb_substr($new_name, 0, 200), 'category' => $category, 'client' => $client,
            'billable' => intval(!empty($pick['billable']) && $client > 0 && $line['amount'] > 0)];
    }

    // New vendors - one per name, or the one of ours already called that
    $made = 0;
    $by_name = [];
    foreach ($vendors as $vendor_id => $vendor) {
        $by_name[mb_strtolower(trim($vendor['vendor_name']))] = $vendor_id;
    }
    foreach ($bring as $i => $item) {
        if ($item['vendor']) {
            continue;
        }
        $name_key = mb_strtolower($item['new_vendor']);
        if (!isset($by_name[$name_key])) {
            $existing = mysqli_fetch_row(mysqli_query($mysqli, "SELECT vendor_id FROM vendors WHERE vendor_client_id = 0 AND vendor_archived_at IS NULL AND vendor_name = '" . escapeSql($item['new_vendor']) . "' LIMIT 1"));
            if ($existing) {
                $by_name[$name_key] = intval($existing[0]);
            } else {
                mysqli_query($mysqli, "INSERT INTO vendors SET " . vendorFormSet(['name' => $item['new_vendor'], 'description' => 'Added from a statement import']) . ", vendor_client_id = 0");
                $by_name[$name_key] = mysqli_insert_id($mysqli);
                logAudit('Vendor', 'Create', escapeSql(stripslashes((string) ($GLOBALS['session_name'] ?? 'ITFlow')) . " created vendor {$item['new_vendor']} from a statement import"), 0, $by_name[$name_key]);
                $made++;
            }
        }
        $bring[$i]['vendor'] = $by_name[$name_key];
    }

    // The expenses, and what each merchant is next time
    $imported = 0;
    $total = 0.0;
    $min_date = null;
    $max_date = null;
    foreach ($bring as $item) {
        $line = $item['line'];
        mysqli_query($mysqli, "INSERT INTO expenses SET expense_date = '{$line['date']}', expense_amount = " . number_format($line['amount'], 2, '.', '') . ",
            expense_currency_code = '$currency', expense_vendor_id = {$item['vendor']}, expense_category_id = {$item['category']}, expense_account_id = $account_id,
            expense_client_id = {$item['client']}, expense_payment_method = " . ($method === '' ? 'NULL' : "'" . escapeSql($method) . "'") . ",
            expense_reference = '" . escapeSql($line['reference']) . "', expense_description = '" . escapeSql($line['description']) . "',
            expense_billable = {$item['billable']}, expense_markup = $markup, expense_import_key = '" . escapeSql($line['key_hash']) . "',
            expense_created_by = " . intval($session_user_id));
        $imported++;
        $total += $line['amount'];
        $min_date = $min_date === null || $line['date'] < $min_date ? $line['date'] : $min_date;
        $max_date = $max_date === null || $line['date'] > $max_date ? $line['date'] : $max_date;
        if ($line['key'] !== '') {
            mysqli_query($mysqli, "INSERT INTO expense_import_rules SET import_rule_match = '" . escapeSql($line['key']) . "', import_rule_vendor_id = {$item['vendor']},
                import_rule_category_id = {$item['category']}, import_rule_client_id = {$item['client']}, import_rule_billable = {$item['billable']}, import_rule_used_count = 1
                ON DUPLICATE KEY UPDATE import_rule_vendor_id = VALUES(import_rule_vendor_id), import_rule_category_id = VALUES(import_rule_category_id),
                import_rule_client_id = VALUES(import_rule_client_id), import_rule_billable = VALUES(import_rule_billable), import_rule_used_count = import_rule_used_count + 1");
        }
    }

    // Lines left out that were entered by hand already - those expenses take their keys
    $linked = 0;
    foreach ($lines as $line) {
        $pick = $picks[$line['n']] ?? null;
        if ($line['entered'] && !$line['imported'] && (!$pick || empty($pick['take']))) {
            mysqli_query($mysqli, "UPDATE expenses SET expense_import_key = '" . escapeSql($line['key_hash']) . "' WHERE expense_id = {$line['entered']} AND expense_import_key IS NULL");
            $linked += mysqli_affected_rows($mysqli);
        }
    }

    return ['imported' => $imported, 'total' => round($total, 2), 'vendors' => $made, 'linked' => $linked, 'from' => $min_date, 'to' => $max_date,
        'skipped' => count(array_filter($lines, function ($l) { return $l['imported']; }))];
}
