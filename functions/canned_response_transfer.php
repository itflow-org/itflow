<?php

/*
 * Canned responses to and from a file - to copy a library between ITFlow
 * installs, keep a copy, or share one. Admin > Canned Responses exports them
 * (export_canned_responses) and imports them (import_canned_responses); the
 * MSP starter pack on Maintenance > Starter Content is loaded the same way.
 *
 * The file is JSON: {"format": "itflow-canned-responses", "version": 1,
 * "exported_at", "responses": [...]}. Each response carries its name, what it
 * says, the ticket categories it is offered on (by name, with their colour,
 * icon and description, made on the way in when missing - none for every
 * ticket), what picking it sets up (reply_type internal, public or email;
 * status by name; minutes) and, when exported with them, its files (name and
 * data, base64). Only shared ones are imported, as shared ones - an agent's
 * own belong to the install it came from.
 */

const CANNED_RESPONSE_FILE_FORMAT = 'itflow-canned-responses';
const CANNED_RESPONSE_FILE_VERSION = 1;

/** The largest file that goes into an export, in bytes - bigger ones stay behind. */
const CANNED_RESPONSE_TRANSFER_FILE_MAX = 10 * 1024 * 1024;

/** Who a reply is for, as a file has it - CANNED_RESPONSE_REPLY_TYPES by word. */
const CANNED_RESPONSE_FILE_REPLY_TYPES = [0 => 'internal', 1 => 'public', 2 => 'email'];

/*
 * Responses as a file's contents (an array, for json_encode()) - the ones in
 * $ids, or every current shared one when $ids is empty, and the archived
 * shared ones too with $archived. Their files go with them with $with_files,
 * each up to CANNED_RESPONSE_TRANSFER_FILE_MAX.
 */
function cannedResponsesExport(array $ids = [], $archived = false, $with_files = true) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $ids)));
    $where = $ids ? 'canned_response_id IN (' . implode(',', $ids) . ')'
        : 'canned_response_user_id = 0' . ($archived ? '' : ' AND canned_response_archived_at IS NULL');
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT canned_response_id, canned_response_name, canned_response_body, canned_response_reply_type, canned_response_ticket_status, canned_response_minutes, ticket_status_name
        FROM canned_responses LEFT JOIN ticket_statuses ON ticket_status_id = canned_response_ticket_status
        WHERE $where ORDER BY canned_response_name ASC, canned_response_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['canned_response_id'])] = $row;
    }
    $categories = [];
    if ($rows) {
        $sql = mysqli_query($mysqli, "SELECT canned_response_id, category_name, category_color, category_icon, category_description FROM canned_response_categories
            JOIN categories ON categories.category_id = canned_response_categories.category_id
            WHERE canned_response_id IN (" . implode(',', array_keys($rows)) . ") ORDER BY category_name");
        while ($row = mysqli_fetch_assoc($sql)) {
            $categories[intval($row['canned_response_id'])][] = ['name' => (string) $row['category_name'], 'color' => (string) $row['category_color'],
                'icon' => (string) $row['category_icon'], 'description' => (string) $row['category_description']];
        }
    }
    $files = $with_files ? cannedResponseFilesFor(array_keys($rows)) : [];

    $responses = [];
    foreach ($rows as $id => $row) {
        $response = [
            'name' => (string) $row['canned_response_name'],
            'body' => (string) $row['canned_response_body'],
            'categories' => $categories[$id] ?? [],
            'reply_type' => $row['canned_response_reply_type'] === null ? null : (CANNED_RESPONSE_FILE_REPLY_TYPES[intval($row['canned_response_reply_type'])] ?? null),
            'status' => $row['canned_response_ticket_status'] !== null && $row['ticket_status_name'] !== null ? (string) $row['ticket_status_name'] : null,
            'minutes' => intval($row['canned_response_minutes']),
        ];
        if ($with_files) {
            $response['files'] = [];
            foreach ($files[$id] ?? [] as $file) {
                $path = cannedResponseFileDir($id) . basename($file['canned_response_file_reference_name']);
                if (is_file($path) && filesize($path) <= CANNED_RESPONSE_TRANSFER_FILE_MAX) {
                    $response['files'][] = ['name' => (string) $file['canned_response_file_name'], 'data' => base64_encode((string) file_get_contents($path))];
                }
            }
        }
        $responses[] = $response;
    }
    return ['format' => CANNED_RESPONSE_FILE_FORMAT, 'version' => CANNED_RESPONSE_FILE_VERSION, 'exported_at' => date('c'), 'responses' => $responses];
}

/** A file name for an export - its one response's name, or the date. */
function cannedResponsesExportFileName(array $export) {
    $responses = $export['responses'] ?? [];
    if (count($responses) === 1) {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($responses[0]['name'])), '-');
        return 'canned-response-' . ($slug !== '' ? substr($slug, 0, 60) : 'export') . '.json';
    }
    return 'canned-responses-' . date('Y-m-d') . '.json';
}

/* One line of text from a file, tidied - no markup, no runs of spaces, no longer than $max. */
function cannedResponseFileText($value, $max) {
    return is_scalar($value) ? trim(mb_substr(preg_replace('/\s+/u', ' ', strip_tags((string) $value)), 0, $max)) : '';
}

/*
 * A file's responses, read and checked - from what export wrote, or the
 * starter pack. Each made safe: text tidied, what it says through the HTML
 * purifier, a reply type or status that isn't one dropped, the time worked
 * kept to a day, and its files to the kinds a ticket takes, each decoded and
 * no bigger than CANNED_RESPONSE_TRANSFER_FILE_MAX, up to
 * CANNED_RESPONSE_MAX_FILES. 'files' is null when the file carried none for
 * it (exported without them), and 'files_refused' names the ones left out.
 * Null with $error saying why the file can't be used.
 */
function cannedResponsesImportRead($contents, &$error = '') {
    $data = is_array($contents) ? $contents : json_decode((string) $contents, true);
    if (!is_array($data) || ($data['format'] ?? '') !== CANNED_RESPONSE_FILE_FORMAT || !isset($data['responses']) || !is_array($data['responses'])) {
        $error = "That isn't a canned responses file - export one from Admin > Canned Responses.";
        return null;
    }
    if (intval($data['version'] ?? 0) > CANNED_RESPONSE_FILE_VERSION) {
        $error = 'That file was made by a newer version of ITFlow - update this one first.';
        return null;
    }
    if (count($data['responses']) > 1000) {
        $error = 'That file holds more than 1000 canned responses - split it up.';
        return null;
    }

    $statuses = [];
    foreach (cannedResponseStatuses() as $status_id => $status) {
        $statuses[mb_strtolower($status['name'])] = $status_id;
    }
    $reply_types = array_flip(CANNED_RESPONSE_FILE_REPLY_TYPES);

    $responses = [];
    foreach ($data['responses'] as $r) {
        $name = is_array($r) ? cannedResponseFileText($r['name'] ?? '', 200) : '';
        if ($name === '') {
            continue;
        }
        $categories = [];
        foreach (array_slice(is_array($r['categories'] ?? null) ? $r['categories'] : [], 0, 50) as $category) {
            $category_name = is_array($category) ? cannedResponseFileText($category['name'] ?? '', 200) : cannedResponseFileText($category, 200);
            if ($category_name !== '' && !isset($categories[mb_strtolower($category_name)])) {
                $categories[mb_strtolower($category_name)] = ['name' => $category_name, 'color' => is_array($category) ? (string) ($category['color'] ?? '') : '',
                    'icon' => is_array($category) ? (string) ($category['icon'] ?? '') : '', 'description' => is_array($category) ? cannedResponseFileText($category['description'] ?? '', 255) : ''];
            }
        }
        $files = null;
        $refused = [];
        if (is_array($r['files'] ?? null)) {
            $files = [];
            foreach ($r['files'] as $file) {
                $file_name = is_array($file) ? trim(mb_substr(basename(str_replace('\\', '/', preg_replace('/[\x00-\x1F\x7F]/u', '', (string) ($file['name'] ?? '')))), 0, 255)) : '';
                $bytes = is_array($file) && is_string($file['data'] ?? null) ? base64_decode($file['data'], true) : false;
                $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if ($file_name === '' || $bytes === false || $bytes === '' || strlen($bytes) > CANNED_RESPONSE_TRANSFER_FILE_MAX
                    || !in_array($extension, TICKET_ATTACHMENT_EXTENSIONS, true) || count($files) >= CANNED_RESPONSE_MAX_FILES) {
                    $refused[] = $file_name !== '' ? $file_name : 'a file with no name';
                    continue;
                }
                $files[] = ['name' => $file_name, 'data' => $bytes];
            }
        }
        $reply_type = is_string($r['reply_type'] ?? null) ? ($reply_types[strtolower($r['reply_type'])] ?? null) : null;
        $status = is_string($r['status'] ?? null) ? ($statuses[mb_strtolower(trim($r['status']))] ?? null) : null;
        $responses[] = [
            'name' => $name,
            'body' => is_scalar($r['body'] ?? null) && trim((string) $r['body']) !== '' ? documentHtmlPurify((string) $r['body']) : '',
            'categories' => array_values($categories),
            'reply_type' => $reply_type,
            'ticket_status' => $status,
            'minutes' => max(0, min(CANNED_RESPONSE_MAX_MINUTES, intval($r['minutes'] ?? 0))),
            'files' => $files,
            'files_refused' => $refused,
        ];
    }
    if (!$responses) {
        $error = 'There are no canned responses in that file.';
        return null;
    }
    return $responses;
}

/* A file's ticket category by name - this install's of that name, or one made from it. 0 when there is only an archived one. */
function cannedResponseImportCategory(array $category, array &$counts) {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT category_id, category_archived_at FROM categories
        WHERE category_type = 'Ticket' AND category_name = '" . escapeSql($category['name']) . "' ORDER BY category_archived_at IS NOT NULL LIMIT 1"));
    if ($row) {
        return $row['category_archived_at'] ? 0 : intval($row['category_id']);
    }
    [$fields] = categoryFromInput(['type' => 'Ticket', 'name' => $category['name'], 'description' => $category['description'], 'color' => $category['color'] ?: categoryNextColor('Ticket'), 'icon' => $category['icon']]);
    if (!$fields) {
        return 0;
    }
    $counts['categories']++;
    return categoryCreate($fields);
}

/* Keep a file's bytes with a response - the import's. Whether it was kept. */
function cannedResponseAddFileData($canned_response_id, $name, $bytes) {
    global $mysqli;
    $canned_response_id = intval($canned_response_id);
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!$canned_response_id || $bytes === '' || !in_array($extension, TICKET_ATTACHMENT_EXTENSIONS, true) || count(cannedResponseFiles($canned_response_id)) >= CANNED_RESPONSE_MAX_FILES) {
        return false;
    }
    $dir = cannedResponseFileDir($canned_response_id);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $reference = randomString(32) . '.' . $extension;
    if (file_put_contents($dir . $reference, $bytes) === false) {
        return false;
    }
    mysqli_query($mysqli, "INSERT INTO canned_response_files SET canned_response_file_canned_response_id = $canned_response_id, canned_response_file_name = '" . escapeSql($name) . "',
        canned_response_file_reference_name = '$reference', canned_response_file_size = " . strlen($bytes));
    return true;
}

/*
 * Bring in responses read by cannedResponsesImportRead(), as shared ones. One
 * whose name a shared one here already has is skipped ($existing 'skip'),
 * added under a name of its own ('copy') or has everything in the file put in
 * its place ('replace') - what it says, where it is offered and what it sets
 * up, and its files when the file carries them. Its record of use and whether
 * it is archived stay. $only_existing: bring in only the ones already here
 * (to update them). ['added', 'replaced', 'skipped', 'categories' made,
 * 'files' kept, 'files_refused' (names), 'ids' of those added or replaced].
 */
function cannedResponsesImport(array $responses, $existing = 'skip', $only_existing = false) {
    global $mysqli;
    $counts = ['added' => 0, 'replaced' => 0, 'skipped' => 0, 'categories' => 0, 'files' => 0, 'files_refused' => [], 'ids' => []];
    $here = [];
    $sql = mysqli_query($mysqli, "SELECT canned_response_id, canned_response_name FROM canned_responses WHERE canned_response_user_id = 0 ORDER BY canned_response_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $here[mb_strtolower($row['canned_response_name'])] = $here[mb_strtolower($row['canned_response_name'])] ?? intval($row['canned_response_id']);
    }

    foreach ($responses as $r) {
        $id = $here[mb_strtolower($r['name'])] ?? 0;
        if (($only_existing && !$id) || ($id && $existing === 'skip')) {
            $counts['skipped']++;
            continue;
        }
        $name = $r['name'];
        if ($id && $existing === 'copy') {
            for ($n = 2; isset($here[mb_strtolower($name)]); $n++) {
                $name = mb_substr($r['name'], 0, 190) . " ($n)";
            }
            $id = 0;
        }
        $replacing = $id > 0;

        $category_ids = [];
        foreach ($r['categories'] as $category) {
            $category_id = cannedResponseImportCategory($category, $counts);
            if ($category_id) {
                $category_ids[] = $category_id;
            }
        }
        $saved = cannedResponseSave($id, ['name' => $name, 'body' => $r['body'], 'category_ids' => $category_ids,
            'reply_type' => $r['reply_type'], 'ticket_status' => $r['ticket_status'], 'minutes' => $r['minutes']], 0);
        $here[mb_strtolower($name)] = $saved;

        // Its files are the file's, when it carries them
        if ($r['files'] !== null) {
            if ($replacing) {
                cannedResponseRemoveFiles($saved, array_column(cannedResponseFiles($saved), 'canned_response_file_id'));
            }
            foreach ($r['files'] as $file) {
                if (cannedResponseAddFileData($saved, $file['name'], $file['data'])) {
                    $counts['files']++;
                } else {
                    $counts['files_refused'][] = $file['name'];
                }
            }
        }
        $counts['files_refused'] = array_merge($counts['files_refused'], $r['files_refused']);

        $counts[$replacing ? 'replaced' : 'added']++;
        $counts['ids'][] = $saved;
    }
    return $counts;
}

/* What an import did, in words - for its message. HTML. */
function cannedResponsesImportSummary(array $counts) {
    $parts = [];
    foreach (['added' => 'added', 'replaced' => 'replaced', 'skipped' => 'already here, left as they were'] as $key => $word) {
        if ($counts[$key]) {
            $parts[] = '<strong>' . $counts[$key] . '</strong> ' . $word;
        }
    }
    $made = [];
    if ($counts['categories']) {
        $made[] = 'Made ' . $counts['categories'] . ' ticket categor' . ($counts['categories'] === 1 ? 'y' : 'ies') . ' they are offered on';
    }
    if ($counts['files']) {
        $made[] = 'Brought in ' . $counts['files'] . ' file' . ($counts['files'] === 1 ? '' : 's') . ' that go with them';
    }
    return ucfirst(implode(', ', $parts) ?: 'nothing brought in')
        . ($made ? '. ' . implode('. ', $made) : '')
        . ($counts['files_refused'] ? '. Left out <strong>' . escapeHtml(implode(', ', array_slice($counts['files_refused'], 0, 10))) . '</strong>'
            . (count($counts['files_refused']) > 10 ? ' and ' . (count($counts['files_refused']) - 10) . ' more' : '') . " - a kind a ticket doesn't take, too big or more than " . CANNED_RESPONSE_MAX_FILES . ' on one' : '')
        . '.';
}
