<?php

/*
 * ITFlow - canned responses (Admin > Canned Responses): replies an agent puts
 * into a ticket in one go. Each is offered on tickets in the ticket
 * categories it is set to, or on every ticket when it has none. The
 * placeholders in it - [Contact], [Ticket], [Agent] and the like - are filled
 * in from the ticket as it goes in, and each time one goes into a reply is
 * kept, for how often each is used and when it last was. A response can set
 * up the reply too - who sees it, the ticket's status after it and time
 * worked - when it is picked; the agent can still change them. Files can go
 * with one: kept in uploads/canned_responses/<id>/, they are copied onto the
 * ticket of each reply it goes into, and go out with its email.
 *
 * Shared ones (canned_response_user_id 0) are made in Admin > Canned
 * Responses and offered to everyone; an agent's own are made in Your account
 * > Canned Responses, or saved from a reply - this ticket's details in it
 * turned back into placeholders - and offered to them alone, first. An admin
 * can share one of an agent's with everyone.
 *
 * Picked from the picker above the reply box, or by typing / in it
 * (agent/includes/inc_canned_response_picker.php, agent/js/canned_response_picker.js),
 * on a ticket and in Update & Reply for several tickets.
 *
 * Shared by admin/canned_responses.php, admin/modals/canned_response/*,
 * admin/post/canned_response.php, Your account's agent/user/user_canned_responses.php,
 * agent/user/modals/canned_response_* and agent/user/post/canned_response.php,
 * includes/canned_response_form.php, agent/ticket.php, the Update & Reply
 * window, agent/ajax.php, agent/canned_response_file.php and the reply
 * handlers (agent/post/ticket.php).
 */

/** The placeholders filled in from the ticket as a response goes in - what each becomes. */
const CANNED_RESPONSE_PLACEHOLDERS = [
    'Contact' => "the contact's name",
    'First Name' => "the contact's first name",
    'Client' => "the client's name",
    'Ticket' => 'its number - TCK-123',
    'Subject' => "the ticket's subject",
    'Ticket Link' => 'a link to the ticket, as its emails have',
    'Asset' => "the asset's name",
    'Site' => "the site's name",
    'Project' => "the project's name",
    'Agent' => 'you - the agent replying',
    'Date' => "today's date",
    'Month' => 'the month - October 2026',
    'Quarter' => 'the quarter - Q4 2026',
    'Year' => 'the year',
];

/** Who a reply is for, as a response can set it - the ticket page's reply types: [label, icon]. */
const CANNED_RESPONSE_REPLY_TYPES = [
    0 => ['Internal note', 'fa-lock'],
    1 => ['Public note', 'fa-eye'],
    2 => ['Email reply', 'fa-paper-plane'],
];

/** The longest time worked a response can add, in minutes - a day. */
const CANNED_RESPONSE_MAX_MINUTES = 1440;

/** One response, archived or not, or null. */
function cannedResponseRow($canned_response_id) {
    global $mysqli;
    $canned_response_id = intval($canned_response_id);
    if ($canned_response_id <= 0) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT canned_response_id, canned_response_name, canned_response_body, canned_response_reply_type, canned_response_ticket_status, canned_response_minutes,
            canned_response_user_id, canned_response_created_by, canned_response_created_at, canned_response_updated_at, canned_response_archived_at
        FROM canned_responses WHERE canned_response_id = $canned_response_id"));
    return $row ?: null;
}

/* The responses an agent may use - SQL on canned_responses: the shared ones and their own. */
function cannedResponseUsableSql($user_id) {
    return "canned_responses.canned_response_user_id IN (0, " . intval($user_id) . ")";
}

/* Whether an agent may use a response (a cannedResponseRow()) - a shared one, or their own. */
function cannedResponseUsable(array $row, $user_id) {
    $owner = intval($row['canned_response_user_id']);
    return $owner === 0 || $owner === intval($user_id);
}

/** The ticket categories one is offered on - category ids; none for every ticket. */
function cannedResponseCategoryIds($canned_response_id) {
    global $mysqli;
    $ids = [];
    $sql = mysqli_query($mysqli, "SELECT category_id FROM canned_response_categories WHERE canned_response_id = " . intval($canned_response_id) . " ORDER BY category_id");
    while ($row = mysqli_fetch_row($sql)) {
        $ids[] = intval($row[0]);
    }
    return $ids;
}

/** The ticket categories of several responses - canned_response_id => [category rows, by name]; one with none isn't in it. */
function cannedResponseCategoriesFor(array $canned_response_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $canned_response_ids)));
    $categories = [];
    if (!$ids) {
        return $categories;
    }
    $sql = mysqli_query($mysqli, "SELECT canned_response_id, categories.category_id, category_name, category_type, category_color, category_icon, category_archived_at
        FROM canned_response_categories JOIN categories ON categories.category_id = canned_response_categories.category_id
        WHERE canned_response_id IN (" . implode(',', $ids) . ") ORDER BY category_name");
    while ($row = mysqli_fetch_assoc($sql)) {
        $categories[intval($row['canned_response_id'])][] = $row;
    }
    return $categories;
}

/** The statuses a response can leave a ticket in - the ones a reply can set: ticket_status_id => [name, color], active ones but New and Closed. */
function cannedResponseStatuses() {
    global $mysqli;
    static $statuses = null;
    if ($statuses === null) {
        $statuses = [];
        $sql = mysqli_query($mysqli, "SELECT ticket_status_id, ticket_status_name, ticket_status_color FROM ticket_statuses
            WHERE ticket_status_id NOT IN (1, 5) AND ticket_status_active = 1 ORDER BY ticket_status_order ASC, ticket_status_id ASC");
        while ($row = mysqli_fetch_assoc($sql)) {
            $statuses[intval($row['ticket_status_id'])] = ['name' => (string) $row['ticket_status_name'], 'color' => (string) $row['ticket_status_color']];
        }
    }
    return $statuses;
}

/*
 * The response window's fields, checked - [$fields, $error]. $post: name,
 * body (the editor's HTML, kept as written - it is purified where it is
 * shown), category_ids[] (ticket categories; none for every ticket), and
 * what picking it sets up: reply_type ('' leaves it, or 0 1 2 as
 * CANNED_RESPONSE_REPLY_TYPES), ticket_status ('' leaves it, or one of
 * cannedResponseStatuses()) and minutes (added to the time worked).
 */
function cannedResponseFields(array $post, &$error = '') {
    global $mysqli;
    $error = '';
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($post['name'] ?? ''))));
    if ($name === '') {
        $error = 'A canned response needs a name';
        return null;
    }
    if (mb_strlen($name) > 200) {
        $error = 'Keep the name to 200 characters';
        return null;
    }
    // Ticket categories that are there
    $category_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($post['category_ids'] ?? [])))));
    if ($category_ids) {
        $known = [];
        $sql = mysqli_query($mysqli, "SELECT category_id FROM categories WHERE category_type = 'Ticket' AND category_id IN (" . implode(',', $category_ids) . ")");
        while ($row = mysqli_fetch_row($sql)) {
            $known[] = intval($row[0]);
        }
        $category_ids = array_values(array_intersect($category_ids, $known));
    }
    $reply_type = trim((string) ($post['reply_type'] ?? ''));
    $reply_type = $reply_type !== '' && isset(CANNED_RESPONSE_REPLY_TYPES[intval($reply_type)]) ? intval($reply_type) : null;
    $ticket_status = intval($post['ticket_status'] ?? 0);
    $ticket_status = isset(cannedResponseStatuses()[$ticket_status]) ? $ticket_status : null;
    $minutes = intval($post['minutes'] ?? 0);
    if ($minutes < 0 || $minutes > CANNED_RESPONSE_MAX_MINUTES) {
        $error = 'The time worked it adds is up to ' . CANNED_RESPONSE_MAX_MINUTES . ' minutes - a day';
        return null;
    }
    return ['name' => $name, 'body' => (string) ($post['body'] ?? ''), 'category_ids' => $category_ids,
        'reply_type' => $reply_type, 'ticket_status' => $ticket_status, 'minutes' => $minutes];
}

/*
 * Save a response's fields (cannedResponseFields()) - a new one with
 * $canned_response_id 0, shared, or the agent $owner_id's own. Its id.
 */
function cannedResponseSave($canned_response_id, array $f, $owner_id = 0) {
    global $mysqli, $session_user_id;
    $canned_response_id = intval($canned_response_id);
    $set = "canned_response_name = '" . escapeSql($f['name']) . "', canned_response_body = '" . mysqli_real_escape_string($mysqli, $f['body']) . "',
        canned_response_reply_type = " . ($f['reply_type'] === null ? 'NULL' : intval($f['reply_type'])) . ",
        canned_response_ticket_status = " . ($f['ticket_status'] === null ? 'NULL' : intval($f['ticket_status'])) . ",
        canned_response_minutes = " . intval($f['minutes']);
    if ($canned_response_id) {
        mysqli_query($mysqli, "UPDATE canned_responses SET $set WHERE canned_response_id = $canned_response_id");
    } else {
        mysqli_query($mysqli, "INSERT INTO canned_responses SET $set, canned_response_user_id = " . intval($owner_id) . ", canned_response_created_by = " . intval($session_user_id ?? 0));
        $canned_response_id = mysqli_insert_id($mysqli);
    }
    cannedResponseSetCategories($canned_response_id, $f['category_ids']);
    return $canned_response_id;
}

/* Offer a response on these ticket categories (ids) - none for every ticket. */
function cannedResponseSetCategories($canned_response_id, array $category_ids) {
    global $mysqli;
    $canned_response_id = intval($canned_response_id);
    mysqli_query($mysqli, "DELETE FROM canned_response_categories WHERE canned_response_id = $canned_response_id");
    foreach (array_unique(array_filter(array_map('intval', $category_ids))) as $category_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO canned_response_categories SET canned_response_id = $canned_response_id, category_id = $category_id");
    }
}

/*
 * A new response starting from this one - "Copy of", for the same categories,
 * setting up the same, with copies of its files. Whose it is: the same as
 * this one's, or the agent $owner_id's own. Its id, or 0.
 */
function cannedResponseCopy($canned_response_id, $owner_id = null) {
    $row = cannedResponseRow($canned_response_id);
    if (!$row) {
        return 0;
    }
    $new_id = cannedResponseSave(0, [
        'name' => mb_substr('Copy of ' . $row['canned_response_name'], 0, 200),
        'body' => (string) $row['canned_response_body'],
        'category_ids' => cannedResponseCategoryIds($row['canned_response_id']),
        'reply_type' => $row['canned_response_reply_type'] === null ? null : intval($row['canned_response_reply_type']),
        'ticket_status' => $row['canned_response_ticket_status'] === null ? null : intval($row['canned_response_ticket_status']),
        'minutes' => intval($row['canned_response_minutes']),
    ], $owner_id === null ? intval($row['canned_response_user_id']) : intval($owner_id));
    cannedResponseCopyFiles($row['canned_response_id'], $new_id);
    return $new_id;
}

/* What a response says, as a line of plain text - for the lists. */
function cannedResponsePreview($html, $length = 140) {
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) $html)), ENT_QUOTES, 'UTF-8')));
    return mb_strimwidth($text, 0, $length, '...');
}

/* "15m", "1h 30m" - time worked a response adds. */
function cannedResponseMinutesText($minutes) {
    $minutes = intval($minutes);
    $hours = intdiv($minutes, 60);
    return trim(($hours ? $hours . 'h ' : '') . ($minutes % 60 || !$hours ? ($minutes % 60) . 'm' : ''));
}

/*
 * What picking a response sets up on the reply, as little badges - who sees
 * it, the status after it and the time it adds. HTML; '' when it sets none
 * of them (a row of canned_responses).
 */
function cannedResponseSetsUpHtml(array $row) {
    $chips = [];
    $reply_type = $row['canned_response_reply_type'];
    if ($reply_type !== null && isset(CANNED_RESPONSE_REPLY_TYPES[intval($reply_type)])) {
        [$label, $icon] = CANNED_RESPONSE_REPLY_TYPES[intval($reply_type)];
        $chips[] = '<span class="badge rounded-pill text-bg-light border fw-normal"><i class="fas fa-fw ' . $icon . ' text-secondary me-1"></i>' . $label . '</span>';
    }
    $status = cannedResponseStatuses()[intval($row['canned_response_ticket_status'])] ?? null;
    if ($row['canned_response_ticket_status'] !== null && $status) {
        $chips[] = ticketStatusHtml($status['name'], $status['color'], 'detail');
    }
    if (intval($row['canned_response_minutes'])) {
        $chips[] = '<span class="badge rounded-pill text-bg-light border fw-normal"><i class="far fa-fw fa-clock text-secondary me-1"></i>+' . cannedResponseMinutesText($row['canned_response_minutes']) . '</span>';
    }
    return $chips ? '<span class="d-inline-flex flex-wrap gap-1 align-items-center">' . implode('', $chips) . '</span>' : '';
}

/*
 * The responses offered to agent $user_id on tickets in these ticket
 * categories, for the picker - [label => rows]: Yours first (their own for
 * these categories or every ticket), then a group for each of the
 * categories with any shared ones (by name), then Every ticket. Current
 * ones only, each by name, with what it sets up and how many files go with
 * it; no bodies. One offered on several of the categories is in each of
 * their groups.
 */
function cannedResponsePickerGroups(array $category_ids, $user_id) {
    global $mysqli;
    $category_ids = array_values(array_unique(array_filter(array_map('intval', $category_ids))));
    $user_id = intval($user_id);
    $groups = [];
    $general = [];
    $yours = [];
    $sql = mysqli_query($mysqli, "SELECT canned_response_id, canned_response_name, canned_response_body, canned_response_reply_type, canned_response_ticket_status, canned_response_minutes, canned_response_user_id,
            EXISTS (SELECT 1 FROM canned_response_categories c WHERE c.canned_response_id = canned_responses.canned_response_id) AS has_categories,
            " . ($category_ids ? "EXISTS (SELECT 1 FROM canned_response_categories c WHERE c.canned_response_id = canned_responses.canned_response_id AND c.category_id IN (" . implode(',', $category_ids) . "))" : '0') . " AS in_categories,
            (SELECT COUNT(*) FROM canned_response_files WHERE canned_response_file_canned_response_id = canned_responses.canned_response_id) AS file_count
        FROM canned_responses
        WHERE canned_response_archived_at IS NULL AND " . cannedResponseUsableSql($user_id) . "
        ORDER BY canned_response_name ASC");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['preview'] = cannedResponsePreview($row['canned_response_body'], 160);
        unset($row['canned_response_body']);
        if (intval($row['canned_response_user_id'])) {
            // The agent's own - in Yours, when it is for these categories or every ticket
            if (!intval($row['has_categories']) || intval($row['in_categories'])) {
                $yours[] = $row;
            }
            continue;
        }
        $rows[intval($row['canned_response_id'])] = $row;
        if (!intval($row['has_categories'])) {
            $general[] = $row;
        }
    }
    if ($yours) {
        $groups['Yours'] = $yours;
    }
    if ($category_ids && $rows) {
        $sql = mysqli_query($mysqli, "SELECT canned_response_id, categories.category_id, category_name FROM canned_response_categories
            JOIN categories ON categories.category_id = canned_response_categories.category_id
            WHERE canned_response_categories.category_id IN (" . implode(',', $category_ids) . ")
            ORDER BY category_name ASC, categories.category_id ASC");
        $by_category = [];
        while ($link = mysqli_fetch_assoc($sql)) {
            $id = intval($link['canned_response_id']);
            if (isset($rows[$id])) {
                $by_category[intval($link['category_id'])]['label'] = $link['category_name'];
                $by_category[intval($link['category_id'])]['rows'][$id] = $rows[$id];
            }
        }
        foreach ($by_category as $group) {
            uasort($group['rows'], function ($a, $b) {
                return strnatcasecmp($a['canned_response_name'], $b['canned_response_name']);
            });
            $groups[$group['label']] = array_values($group['rows']);
        }
    }
    if ($general) {
        $groups['Every ticket'] = $general;
    }
    return $groups;
}

/* The responses offered to agent $user_id on a ticket in a category - cannedResponsePickerGroups() for one. */
function cannedResponsesForTicket($category_id, $user_id) {
    return cannedResponsePickerGroups([$category_id], $user_id);
}

/* What the placeholders become on a ticket for $agent_name replying - lowercase name => value, as ticketPlaceholderFill() takes them; [] for no ticket. Raw. */
function cannedResponseValues($ticket_id, $agent_name) {
    global $mysqli;
    $values = ticketPlaceholderValues($ticket_id);
    if (!$values) {
        return [];
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_subject FROM tickets WHERE ticket_id = " . intval($ticket_id)));
    $contact = trim($values['contact']);
    $values['first name'] = $contact !== '' ? preg_split('/\s+/u', $contact)[0] : '';
    $values['subject'] = (string) ($row['ticket_subject'] ?? '');
    $values['agent'] = (string) $agent_name;
    return $values;
}

/*
 * HTML with the placeholders filled in for a ticket and $agent_name replying:
 * [Ticket Link] a link to the ticket (ticketEmailUrl()), the rest as
 * cannedResponseValues() has them, escaped. A placeholder with nothing to put
 * in - [Asset] on a ticket with no asset, or one that is no placeholder at
 * all - stays as it is, for the agent to fill in. As it comes for no ticket.
 */
function cannedResponsePlaceholders($html, $ticket_id, $agent_name) {
    $ticket = ticketRow($ticket_id);
    if (!$ticket) {
        return (string) $html;
    }
    $url = escapeHtml(ticketEmailUrl($ticket));
    $html = preg_replace('/\[ticket(?: |&nbsp;|\x{00A0})+link\]/iu', '<a href="' . $url . '">' . $url . '</a>', (string) $html);
    return ticketPlaceholderFill($html, cannedResponseValues($ticket['ticket_id'], $agent_name), true);
}

/* A response's HTML made safe and filled in for a ticket, as it goes into a reply - cannedResponsePlaceholders(). */
function cannedResponseFill($html, $ticket_id, $agent_name) {
    return cannedResponsePlaceholders(documentHtmlPurify($html), $ticket_id, $agent_name);
}

/*
 * What picking a response sets up, for the picker - reply_type (0 1 2, or
 * null to leave it), ticket_status (an id, or null) with status_name, and
 * minutes.
 */
function cannedResponseActions(array $row) {
    $status = $row['canned_response_ticket_status'] === null ? null : intval($row['canned_response_ticket_status']);
    $statuses = cannedResponseStatuses();
    return [
        'reply_type' => $row['canned_response_reply_type'] === null ? null : intval($row['canned_response_reply_type']),
        'ticket_status' => $status !== null && isset($statuses[$status]) ? $status : null,
        'status_name' => $status !== null && isset($statuses[$status]) ? $statuses[$status]['name'] : '',
        'minutes' => intval($row['canned_response_minutes']),
    ];
}

/* Keep that these responses went into a reply on a ticket - each once, the ones that are there. */
function cannedResponseRecordUses(array $canned_response_ids, $ticket_id, $user_id) {
    global $mysqli;
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $canned_response_ids)))), 0, 20);
    if (!$ids) {
        return;
    }
    mysqli_query($mysqli, "INSERT INTO canned_response_uses (canned_response_use_canned_response_id, canned_response_use_user_id, canned_response_use_ticket_id)
        SELECT canned_response_id, " . intval($user_id) . ", " . intval($ticket_id) . " FROM canned_responses WHERE canned_response_id IN (" . implode(',', $ids) . ")");
}

/* ---------------------------------------------------------------- files */

/** The most files that can go with one response. */
const CANNED_RESPONSE_MAX_FILES = 10;

/* Where a response's files are kept - its folder under uploads/canned_responses/, from the app's root. */
function cannedResponseFileDir($canned_response_id) {
    return dirname(__DIR__) . '/uploads/canned_responses/' . intval($canned_response_id) . '/';
}

/* The files that go with responses - canned_response_id => [rows by name]. */
function cannedResponseFilesFor(array $canned_response_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $canned_response_ids)));
    $files = [];
    if (!$ids) {
        return $files;
    }
    $sql = mysqli_query($mysqli, "SELECT canned_response_file_id, canned_response_file_canned_response_id, canned_response_file_name, canned_response_file_reference_name, canned_response_file_size
        FROM canned_response_files WHERE canned_response_file_canned_response_id IN (" . implode(',', $ids) . ") ORDER BY canned_response_file_name, canned_response_file_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $files[intval($row['canned_response_file_canned_response_id'])][] = $row;
    }
    return $files;
}

/* The files that go with one response, by name. */
function cannedResponseFiles($canned_response_id) {
    return cannedResponseFilesFor([$canned_response_id])[intval($canned_response_id)] ?? [];
}

/*
 * Keep the files uploaded with a response's window ($_FILES[$field]) - the
 * ticket attachment kinds, up to CANNED_RESPONSE_MAX_FILES in all. What
 * wasn't kept, by name - the wrong kind, too many, or a failed upload.
 */
function cannedResponseSaveFiles($canned_response_id, $field = 'files') {
    global $mysqli;
    $canned_response_id = intval($canned_response_id);
    $refused = [];
    if (!$canned_response_id || empty($_FILES[$field]['name']) || !is_array($_FILES[$field]['name'])) {
        return $refused;
    }
    $have = count(cannedResponseFiles($canned_response_id));
    $dir = cannedResponseFileDir($canned_response_id);
    foreach ($_FILES[$field]['name'] as $i => $name) {
        if ((string) $name === '' && intval($_FILES[$field]['error'][$i] ?? 0) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $file = ['name' => $name, 'type' => $_FILES[$field]['type'][$i] ?? '', 'tmp_name' => $_FILES[$field]['tmp_name'][$i] ?? '',
            'error' => $_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $_FILES[$field]['size'][$i] ?? 0];
        $reference = $have < CANNED_RESPONSE_MAX_FILES ? checkFileUpload($file, TICKET_ATTACHMENT_EXTENSIONS) : false;
        if (!$reference) {
            $refused[] = (string) $name;
            continue;
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $dir . $reference)) {
            $refused[] = (string) $name;
            continue;
        }
        mysqli_query($mysqli, "INSERT INTO canned_response_files SET canned_response_file_canned_response_id = $canned_response_id, canned_response_file_name = '" . escapeSql(mb_substr(basename((string) $name), 0, 255)) . "',
            canned_response_file_reference_name = '" . escapeSql($reference) . "', canned_response_file_size = " . intval($file['size']));
        $have++;
    }
    return $refused;
}

/* Take files off a response - its own, by id, from its folder too. How many went. */
function cannedResponseRemoveFiles($canned_response_id, array $file_ids) {
    global $mysqli;
    $removed = 0;
    $wanted = array_map('intval', $file_ids);
    foreach (cannedResponseFiles($canned_response_id) as $file) {
        if (in_array(intval($file['canned_response_file_id']), $wanted, true)) {
            @unlink(cannedResponseFileDir($canned_response_id) . basename($file['canned_response_file_reference_name']));
            mysqli_query($mysqli, "DELETE FROM canned_response_files WHERE canned_response_file_id = " . intval($file['canned_response_file_id']));
            $removed++;
        }
    }
    return $removed;
}

/* A response's files, copied to another - a copy of the response. */
function cannedResponseCopyFiles($from_id, $to_id) {
    global $mysqli;
    $to_dir = cannedResponseFileDir($to_id);
    foreach (cannedResponseFiles($from_id) as $file) {
        $source = cannedResponseFileDir($from_id) . basename($file['canned_response_file_reference_name']);
        if (!is_file($source)) {
            continue;
        }
        if (!is_dir($to_dir)) {
            mkdir($to_dir, 0775, true);
        }
        $reference = randomString(32) . '.' . strtolower(pathinfo($file['canned_response_file_reference_name'], PATHINFO_EXTENSION));
        if (copy($source, $to_dir . $reference)) {
            mysqli_query($mysqli, "INSERT INTO canned_response_files SET canned_response_file_canned_response_id = " . intval($to_id) . ", canned_response_file_name = '" . escapeSql($file['canned_response_file_name']) . "',
                canned_response_file_reference_name = '$reference', canned_response_file_size = " . intval($file['canned_response_file_size']));
        }
    }
}

/* Take a deleted response's folder of files away - its rows go with it by their foreign key. */
function cannedResponseDeleteFiles($canned_response_id) {
    removeDirectory(rtrim(cannedResponseFileDir($canned_response_id), '/'));
}

/*
 * Put files that go with responses ($file_ids) onto a ticket, as a reply's
 * attachments ($reply_id, or null for the ticket itself) - copies, so the
 * response keeps its own. Only the files of responses agent $user_id may use.
 * What saveTicketAttachments() gives: name, path (from the app's root) and
 * size of each, for its email.
 */
function cannedResponseAttachFiles(array $file_ids, $ticket_id, $reply_id, $user_id) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $file_ids)))), 0, 50);
    $stored = [];
    if (!$ticket_id || !$ids) {
        return $stored;
    }
    $sql = mysqli_query($mysqli, "SELECT canned_response_file_id, canned_response_file_canned_response_id, canned_response_file_name, canned_response_file_reference_name
        FROM canned_response_files JOIN canned_responses ON canned_response_id = canned_response_file_canned_response_id
        WHERE canned_response_file_id IN (" . implode(',', $ids) . ") AND " . cannedResponseUsableSql($user_id) . " ORDER BY canned_response_file_id");
    $dir = dirname(__DIR__) . "/uploads/tickets/$ticket_id/";
    while ($file = mysqli_fetch_assoc($sql)) {
        $source = cannedResponseFileDir($file['canned_response_file_canned_response_id']) . basename($file['canned_response_file_reference_name']);
        if (!is_file($source)) {
            continue;
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $reference = randomString(32) . '.' . strtolower(pathinfo($file['canned_response_file_reference_name'], PATHINFO_EXTENSION));
        if (!copy($source, $dir . $reference)) {
            continue;
        }
        mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '" . escapeSql($file['canned_response_file_name']) . "', ticket_attachment_reference_name = '$reference',
            ticket_attachment_reply_id = " . ($reply_id === null ? 'NULL' : intval($reply_id)) . ", ticket_attachment_ticket_id = $ticket_id");
        $stored[] = ['name' => $file['canned_response_file_name'], 'path' => "uploads/tickets/$ticket_id/$reference", 'size' => filesize($dir . $reference)];
    }
    return $stored;
}

/* "240 KB" - a file's size. */
function cannedResponseFileSizeText($bytes) {
    $bytes = intval($bytes);
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    return max(1, round($bytes / 1024)) . ' KB';
}

/* ---------------------------------------------------------------- saving a reply as one */

/*
 * A reply written on a ticket, made into a response: this ticket's details
 * in it turned back into placeholders - [Ticket Link] (its link), [Subject],
 * [Contact], [Client], [Ticket], [Agent] (the agent saving it) and [First
 * Name] - so it reads right on the next ticket. [HTML, [the placeholders put
 * in]]. Whole words in the text only, never in tags and their attributes; a
 * subject of one word isn't swapped, as it would be any use of that word.
 */
function cannedResponseUnfill($html, $ticket_id, $agent_name) {
    $html = (string) $html;
    $ticket = ticketRow($ticket_id);
    $values = $ticket ? cannedResponseValues($ticket['ticket_id'], $agent_name) : [];
    $made = [];
    if (!$values) {
        return [$html, $made];
    }
    $url = ticketEmailUrl($ticket);
    foreach ([escapeHtml($url), $url] as $form) {
        $count = 0;
        $html = preg_replace('#<a\b[^>]*href="' . preg_quote($form, '#') . '"[^>]*>.*?</a>#is', '[Ticket Link]', $html, -1, $count);
        $html = str_replace($form, '[Ticket Link]', $html, $more);
        if ($count + $more) {
            $made['Ticket Link'] = true;
        }
    }
    // The subject first, as it can have the client's or contact's name in it
    $swaps = ['Subject' => $values['subject'], 'Contact' => $values['contact'], 'Client' => $values['client'], 'Ticket' => $values['ticket'], 'Agent' => $values['agent'], 'First Name' => $values['first name']];
    // The text between the tags - the even pieces
    $pieces = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($swaps as $placeholder => $value) {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
        if (mb_strlen($value) < 2 || ($placeholder === 'Subject' && strpos($value, ' ') === false)) {
            continue;
        }
        foreach (array_unique([escapeHtml($value), $value]) as $form) {
            // The editor writes some spaces as &nbsp;
            $pattern = '/(?<![\p{L}\p{N}])' . implode('(?: |&nbsp;|\x{00A0})+', array_map(function ($word) {
                return preg_quote($word, '/');
            }, explode(' ', $form))) . '(?![\p{L}\p{N}])/u';
            foreach ($pieces as $i => $piece) {
                if ($i % 2 === 0 && $piece !== '') {
                    $count = 0;
                    $pieces[$i] = preg_replace($pattern, '[' . $placeholder . ']', $piece, -1, $count);
                    if ($count) {
                        $made[$placeholder] = true;
                    }
                }
            }
        }
    }
    return [implode('', $pieces), array_keys($made)];
}
