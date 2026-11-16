<?php

/*
 * Imports - a spreadsheet saved as CSV brought into one kind of record, in
 * the Import window (agent/modals/import/import.php, answered by
 * agent/post/import.php). The window sends the file and ITFlow matches its
 * columns to the kind's fields by their headings (importAutoMap()), which the
 * window shows to change. It then shows what each row would do - be added,
 * update what is already here, be skipped, or not import and why - by doing
 * the import in a transaction and rolling it back (importProcess()), so what
 * it says is what Import does. The rows that don't import come back, to
 * download with why and import again once fixed.
 *
 * The kinds are in importKinds() (functions/import_kinds.php). Each has its
 * fields in the template's order - key => [label, also, required, about,
 * example, skip]. The label is the template's heading and, where it has one,
 * the export's (getExportColumns()), so a file exported from ITFlow comes back
 * in as it is; also are other headings taken for it, matched whatever their
 * case and spacing; a skip field is one an export has that the import takes
 * no notice of (a count, the client's name). A file of the kind's old fixed
 * columns whose headings match none of its fields is read by position
 * (legacy).
 *
 * A record already here is found by the kind's find - by name in the client,
 * mostly. It is skipped, or with Update the file's cells change it - an empty
 * cell leaves what it has. An archived one is never changed. A row for the
 * same record as a row before it (key) is skipped.
 */

/** Imports take a file up to this size, of up to this many rows. */
const IMPORT_MAX_BYTES = 10485760;
const IMPORT_MAX_ROWS = 5000;

/** A kind of import with its key in 'kind' - or null. */
function importKind($kind) {
    $kinds = importKinds();
    return isset($kinds[$kind]) ? ['kind' => $kind] + $kinds[$kind] : null;
}

/** Why this person can't import this kind into this client - '' when they can. */
function importRefusal(array $def, $client_id) {
    global $mysqli;
    $has = lookupUserPermission($def['module']);
    if ($has < 2) {
        return permissionRefusedText($def['module'], 2, $has);
    }
    if (!empty($def['client'])) {
        $client_id = intval($client_id);
        if (!$client_id || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM clients WHERE client_id = $client_id"))) {
            return 'Open the client to import its ' . strtolower($def['title']) . ' into.';
        }
        if (!hasClientAccess($client_id)) {
            return "You don't have access to this client.";
        }
    }
    return isset($def['ready']) ? (string) call_user_func($def['ready']) : '';
}

/** A heading as it is compared - its case, spacing and a trailing colon or asterisk aside. */
function importHeadingKey($heading) {
    return trim(preg_replace('/\s+/u', ' ', mb_strtolower(trim(rtrim(trim((string) $heading), ':*')))));
}

/**
 * The uploaded file ($_FILES[$field]) - [$headings, $rows, $error]; $rows
 * are [line in the file, cells]. Comma or semicolon separated, whichever its
 * heading line has more of; a byte-order mark is skipped, and so is a row with
 * nothing in it.
 */
function importFile($field = 'file') {
    $upload = $_FILES[$field] ?? null;
    if (empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
        return [[], [], 'Choose a CSV file to import.'];
    }
    if (strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION)) !== 'csv') {
        return [[], [], "That isn't a CSV file - in your spreadsheet, save it as CSV and import that."];
    }
    if (intval($upload['size']) > IMPORT_MAX_BYTES) {
        return [[], [], 'The file is over ' . (IMPORT_MAX_BYTES / 1048576) . ' MB - split it and import each part.'];
    }
    $handle = fopen($upload['tmp_name'], 'r');
    $first = $handle ? fgets($handle) : false;
    if ($first === false || trim($first) === '') {
        return [[], [], 'The file is empty.'];
    }
    $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    rewind($handle);
    $headings = array_map(function ($heading) {
        return trim((string) $heading);
    }, (array) fgetcsv($handle, 0, $delimiter, '"', ''));
    $headings[0] = trim(preg_replace('/^\xEF\xBB\xBF/', '', $headings[0] ?? ''));
    $rows = [];
    $line = 1;
    while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
        $line++;
        $cells = array_map(function ($cell) {
            return trim((string) $cell);
        }, $cells);
        if (implode('', $cells) === '') {
            continue;
        }
        if (count($rows) >= IMPORT_MAX_ROWS) {
            fclose($handle);
            return [[], [], 'The file has more than ' . IMPORT_MAX_ROWS . ' rows - split it and import each part.'];
        }
        $rows[] = [$line, $cells];
    }
    fclose($handle);
    if (!$rows) {
        return [[], [], 'The file has a heading row but no rows under it.'];
    }
    return [$headings, $rows, ''];
}

/** Which field each of the file's columns is, by its heading - column => field key. The first column for a field takes it. */
function importAutoMap(array $def, array $headings) {
    $lookup = [];
    foreach ($def['fields'] as $key => $field) {
        foreach (array_merge([$field['label']], $field['also'] ?? []) as $heading) {
            $lookup[importHeadingKey($heading)] = $lookup[importHeadingKey($heading)] ?? $key;
        }
    }
    $map = [];
    foreach ($headings as $index => $heading) {
        $key = $lookup[importHeadingKey($heading)] ?? null;
        if ($key !== null && !in_array($key, $map, true)) {
            $map[$index] = $key;
        }
    }
    // The old template's columns, by position, when no heading is a field
    $legacy = $def['legacy'] ?? [];
    if (!$map && $legacy && count($headings) === count($legacy)) {
        $map = array_combine(array_keys($headings), $legacy);
    }
    return $map;
}

/** The map the window sent (JSON, column => field key) - each field once - or the file's own when it sent none. */
function importMapFrom(array $def, array $headings, $input) {
    $input = is_string($input) && $input !== '' ? json_decode($input, true) : null;
    if (!is_array($input)) {
        return importAutoMap($def, $headings);
    }
    $map = [];
    foreach ($input as $index => $key) {
        $index = intval($index);
        $key = (string) $key;
        if (isset($headings[$index], $def['fields'][$key]) && !in_array($key, $map, true)) {
            $map[$index] = $key;
        }
    }
    return $map;
}

/** The fields a new record needs that the map leaves out - their labels. */
function importMissing(array $def, array $map) {
    $missing = [];
    foreach ($def['fields'] as $key => $field) {
        if (!empty($field['required']) && !in_array($key, $map, true)) {
            $missing[] = $field['label'];
        }
    }
    return $missing;
}

/**
 * What the rows do, and with $write done - without, done in a transaction
 * and rolled back. $mode is what a row for a record already here does: skip
 * or update. [added, updated, skipped, failed, rows => [[line, name, what
 * (add, update, skip, fail), why]] - only the rows with something to say -
 * and failed_rows, [[line, why, cells]] of those that didn't import]. Each
 * row is kept or undone whole.
 */
function importProcess(array $def, $client_id, array $rows, array $map, $mode, $write) {
    global $mysqli;
    $client_id = intval($client_id);
    $result = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'rows' => [], 'failed_rows' => []];
    $fail = function ($line, $name, $why, $cells) use (&$result) {
        $result['failed']++;
        $result['rows'][] = [$line, $name, 'fail', $why];
        $result['failed_rows'][] = [$line, $why, $cells];
    };
    $seen = [];
    $after = [];

    mysqli_begin_transaction($mysqli);
    foreach ($rows as [$line, $cells]) {
        // The row by field - a field the file doesn't have isn't in it
        $row = [];
        foreach ($map as $index => $key) {
            if (empty($def['fields'][$key]['skip'])) {
                $row[$key] = (string) ($cells[$index] ?? '');
            }
        }
        $name = '';
        foreach ((array) $def['name'] as $key) {
            $name = $name !== '' ? $name : (string) ($row[$key] ?? '');
        }

        // The same record as a row before it - by the first of its key fields the row gives
        $key = '';
        if ($def['key'] instanceof Closure) {
            $key = (string) call_user_func($def['key'], $row);
        } else {
            foreach ((array) $def['key'] as $field) {
                if (($row[$field] ?? '') !== '') {
                    $key = $field . ':' . mb_strtolower($row[$field]);
                    break;
                }
            }
        }
        if (trim($key) !== '' && isset($seen[$key])) {
            $result['skipped']++;
            $result['rows'][] = [$line, $name, 'skip', 'The same ' . $def['one'] . ' as row ' . $seen[$key]];
            continue;
        }
        if (trim($key) !== '') {
            $seen[$key] = $line;
        }

        mysqli_query($mysqli, "SAVEPOINT import_row");
        $existing_id = 0;
        try {
            [$existing_id, $archived] = call_user_func($def['find'], $row, $client_id);
            if ($existing_id && ($archived || $mode !== 'update')) {
                $result['skipped']++;
                $result['rows'][] = [$line, $name, 'skip', $archived ? 'Archived here - restore it to change it' : 'Already here'];
                continue;
            }
            $answer = call_user_func($def['row'], $row, $client_id, intval($existing_id), (bool) $write);
        } catch (Throwable $e) {
            $answer = ['error' => 'ITFlow could not save this row - check its values.'];
            error_log('ITFlow import (' . $def['kind'] . ', row ' . $line . '): ' . $e->getMessage());
        }
        if (($answer['error'] ?? '') !== '') {
            mysqli_query($mysqli, "ROLLBACK TO SAVEPOINT import_row");
            $fail($line, $name, $answer['error'], $cells);
            continue;
        }
        $existing_id ? $result['updated']++ : $result['added']++;
        $result['rows'][] = [$line, $name, $existing_id ? 'update' : 'add', implode('; ', $answer['notes'] ?? [])];
        if (isset($answer['after'])) {
            $after[count($result['rows']) - 1] = $answer['after'];
        }
    }
    // What waits for every row to be in - a VM's host further down the file
    foreach ($after as $at => $then) {
        $notes = (array) call_user_func($then);
        if ($notes) {
            $result['rows'][$at][3] = ltrim($result['rows'][$at][3] . '; ' . implode('; ', $notes), '; ');
        }
    }
    if ($write) {
        mysqli_commit($mysqli);
    } else {
        // Something that ended the transaction early would have kept what it imported - say so, as an email preview does
        if (intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT @@in_transaction"))[0] ?? 0) !== 1) {
            logApp('Import', 'error', 'A check of a ' . $def['kind'] . ' import ended its transaction early, so what it imported may have been kept');
        }
        mysqli_rollback($mysqli);
    }

    $result['rows'] = array_values(array_filter($result['rows'], function ($row) {
        return !in_array($row[2], ['add', 'update'], true) || $row[3] !== '';
    }));
    return $result;
}

/** A kind's template - its fields' headings with an example row - as a CSV download. Ends the request. */
function importTemplateDownload(array $def, $prefix = '') {
    $headings = $example = [];
    foreach ($def['fields'] as $field) {
        if (empty($field['skip']) && empty($field['detail'])) {
            $headings[] = $field['label'];
            $example[] = (string) ($field['example'] ?? '');
        }
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace(['"', ' '], ['', '-'], $prefix . $def['title'] . '-Template.csv') . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headings, ',', '"', '');
    fputcsv($out, $example, ',', '"', '');
    fclose($out);
    exit;
}

/*
 * ------------------------------------------------------------------ Cells
 */

/** The cell a row gives a field - null when the file hasn't the column, or when updating and the cell is empty (it leaves what is there). */
function importGiven(array $row, $key, $updating) {
    if (!array_key_exists($key, $row) || ($updating && $row[$key] === '')) {
        return null;
    }
    return (string) $row[$key];
}

/** A cell as quoted SQL text, cut to $max characters (0 for no limit). */
function importSqlText($value, $max = 200) {
    $value = (string) $value;
    return "'" . escapeSql($max ? mb_substr($value, 0, $max) : $value) . "'";
}

/** A yes in a cell - Yes, Y, True, 1 or X. */
function importYes($value) {
    return in_array(mb_strtolower(trim((string) $value)), ['yes', 'y', 'true', '1', 'x'], true);
}

/** A date in a cell as YYYY-MM-DD - or '' when it is empty, and null when it is something else. */
function importDate($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m) && checkdate(intval($m[2]), intval($m[3]), intval($m[1]))) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    return null;
}

/** A cell's list - tags - split on commas and semicolons. */
function importList($value) {
    return array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string) $value)), 'strlen'));
}

/**
 * A record's tags with those named in a cell added, as tagSetForRecord()
 * takes them (functions/tags.php) - the tags of those names, and new ones for
 * the rest when an admin imports, as typing them in the tag picker does.
 */
function importTagValues($entity, $record_id, $value) {
    global $mysqli;
    $config = tagEntity($entity);
    $values = $record_id ? array_map('strval', tagIdsForRecord($entity, $record_id)) : [];
    foreach (importList($value) as $name) {
        $row = $config ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_id FROM tags WHERE tag_type = {$config['type']} AND tag_archived_at IS NULL
            AND LOWER(tag_name) = LOWER('" . escapeSql($name) . "') LIMIT 1")) : null;
        $values[] = $row ? (string) intval($row['tag_id']) : $name;
    }
    return array_values(array_unique($values));
}

/** Add the tags a cell names to a record - nothing when the cell is empty or the file hasn't one. */
function importTagsSet($entity, $record_id, array $row, $key) {
    if (($row[$key] ?? '') !== '') {
        tagSetForRecord($entity, $record_id, importTagValues($entity, $record_id, $row[$key]));
    }
}

/**
 * One of a client's records named in a cell - its id, or 0. $kind: contact
 * (by name or email), location, vendor (with $ours one of your own vendors
 * too), network or asset; archived ones aren't taken.
 */
function importClientRecordId($kind, $value, $client_id, $ours = false) {
    global $mysqli;
    $value = trim((string) $value);
    $client_id = intval($client_id);
    if ($value === '') {
        return 0;
    }
    $sql = escapeSql($value);
    $queries = [
        'contact' => "SELECT contact_id FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL AND (contact_name = '$sql' OR contact_email = '$sql') ORDER BY contact_name = '$sql' DESC, contact_id LIMIT 1",
        'location' => "SELECT location_id FROM locations WHERE location_client_id = $client_id AND location_archived_at IS NULL AND location_name = '$sql' ORDER BY location_id LIMIT 1",
        'vendor' => "SELECT vendor_id FROM vendors WHERE vendor_client_id IN ($client_id" . ($ours ? ', 0' : '') . ") AND vendor_archived_at IS NULL AND vendor_name = '$sql' ORDER BY vendor_client_id = $client_id DESC, vendor_id LIMIT 1",
        'network' => "SELECT network_id FROM networks WHERE network_client_id = $client_id AND network_archived_at IS NULL AND network_name = '$sql' ORDER BY network_id LIMIT 1",
        'asset' => "SELECT asset_id FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL AND asset_name = '$sql' ORDER BY asset_id LIMIT 1",
    ];
    if (!isset($queries[$kind]) || (!$client_id && !$ours)) {
        return 0;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, $queries[$kind]));
    return intval($row[0] ?? 0);
}

/**
 * A record already here by a column's value, for a kind's find - [id,
 * archived], one not archived first; [0, false] when there is none. $where is
 * SQL, the client's records only.
 */
function importFindBy($table, $prefix, $column, $value, $where = '1') {
    global $mysqli;
    $value = trim((string) $value);
    if ($value === '') {
        return [0, false];
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT {$prefix}_id, {$prefix}_archived_at IS NOT NULL FROM $table
        WHERE $column = '" . escapeSql($value) . "' AND $where ORDER BY {$prefix}_archived_at IS NOT NULL, {$prefix}_id LIMIT 1"));
    return $row ? [intval($row[0]), (bool) $row[1]] : [0, false];
}

/** A tax rate named in a cell - by its name, or its name and rate as ITFlow shows it ("Sales Tax 7%") - its id, or 0. */
function importTaxId($value) {
    global $mysqli;
    $key = importHeadingKey($value);
    if ($key === '') {
        return 0;
    }
    $sql = mysqli_query($mysqli, "SELECT tax_id FROM taxes WHERE tax_archived_at IS NULL ORDER BY tax_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tax = taxRow($row['tax_id']);
        if ($tax && (importHeadingKey($tax['tax_name']) === $key || importHeadingKey(taxLabel($tax)) === $key)) {
            return intval($tax['tax_id']);
        }
    }
    return 0;
}

/**
 * A phone number in a cell as [dial code, number] - "+1 (412) 555-0100", as
 * the exports write it, is ['1', '4125550100']; one without a + or 00 in
 * front is just its digits. The dial code is the one its number fits
 * (functions/format.php).
 */
function importPhone($value) {
    // The ' the exports put in front, so a spreadsheet doesn't take it for a formula
    $value = ltrim(trim((string) $value), "'");
    $digits = preg_replace('/\D/', '', $value);
    if ($digits === '' || !preg_match('/^(\+|00)/', $value)) {
        return ['', $digits];
    }
    if (strpos($value, '00') === 0) {
        $digits = substr($digits, 2);
    }
    for ($length = 1; $length <= 3; $length++) {
        $rules = phoneFormatRules(substr($digits, 0, $length));
        if ($rules && ($rules['valid'] === '' || preg_match('/^(?:' . $rules['valid'] . ')$/', substr($digits, $length)))) {
            return [substr($digits, 0, $length), substr($digits, $length)];
        }
    }
    return ['', $digits];
}

/** A phone cell as SQL for its two columns - "{column}_country_code = '1', {column} = '4125550100'". */
function importPhoneSql($value, $column, $code_column = null) {
    [$code, $number] = importPhone($value);
    return ($code_column ?? "{$column}_country_code") . " = '" . escapeSql($code) . "', $column = '" . escapeSql(mb_substr($number, 0, 200)) . "'";
}
