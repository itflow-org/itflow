<?php

/*
 * ITFlow - canned responses (Admin > Canned Responses): replies an agent puts
 * into a ticket in one go. Each is offered on tickets in the ticket
 * categories it is set to, or on every ticket when it has none. The
 * placeholders in it - [Contact], [Ticket], [Agent] and the like - are filled
 * in from the ticket as it goes in, and each time one goes into a reply is
 * kept, for how often each is used and when it last was.
 *
 * Shared by admin/canned_responses.php, admin/modals/canned_response/*,
 * admin/post/canned_response.php, agent/ticket.php, agent/ajax.php and the
 * reply handler (agent/post/ticket.php).
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

/** One response, archived or not, or null. */
function cannedResponseRow($canned_response_id) {
    global $mysqli;
    $canned_response_id = intval($canned_response_id);
    if ($canned_response_id <= 0) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT canned_response_id, canned_response_name, canned_response_body, canned_response_created_at, canned_response_updated_at, canned_response_archived_at
        FROM canned_responses WHERE canned_response_id = $canned_response_id"));
    return $row ?: null;
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

/*
 * The response window's fields, checked - [$fields, $error]. $post: name,
 * body (the editor's HTML, kept as written - it is purified where it is
 * shown) and category_ids[] (ticket categories; none for every ticket).
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
    return ['name' => $name, 'body' => (string) ($post['body'] ?? ''), 'category_ids' => $category_ids];
}

/* Save a response's fields (cannedResponseFields()) - a new one with $canned_response_id 0. Its id. */
function cannedResponseSave($canned_response_id, array $f) {
    global $mysqli;
    $canned_response_id = intval($canned_response_id);
    $set = "canned_response_name = '" . escapeSql($f['name']) . "', canned_response_body = '" . mysqli_real_escape_string($mysqli, $f['body']) . "'";
    if ($canned_response_id) {
        mysqli_query($mysqli, "UPDATE canned_responses SET $set WHERE canned_response_id = $canned_response_id");
    } else {
        mysqli_query($mysqli, "INSERT INTO canned_responses SET $set");
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

/* A new response starting from this one - "Copy of", for the same categories. Its id, or 0. */
function cannedResponseCopy($canned_response_id) {
    $row = cannedResponseRow($canned_response_id);
    if (!$row) {
        return 0;
    }
    return cannedResponseSave(0, [
        'name' => mb_substr('Copy of ' . $row['canned_response_name'], 0, 200),
        'body' => (string) $row['canned_response_body'],
        'category_ids' => cannedResponseCategoryIds($row['canned_response_id']),
    ]);
}

/* What a response says, as a line of plain text - for the lists. */
function cannedResponsePreview($html, $length = 140) {
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) $html)), ENT_QUOTES, 'UTF-8')));
    return mb_strimwidth($text, 0, $length, '...');
}

/*
 * The responses offered on a ticket in a category - ['category' => rows for
 * its category, 'general' => rows for every ticket], each by name. Current
 * ones only; names, not bodies.
 */
function cannedResponsesForTicket($category_id) {
    global $mysqli;
    $category_id = intval($category_id);
    $offered = ['category' => [], 'general' => []];
    $sql = mysqli_query($mysqli, "SELECT canned_response_id, canned_response_name,
            EXISTS (SELECT 1 FROM canned_response_categories c WHERE c.canned_response_id = canned_responses.canned_response_id) AS has_categories,
            EXISTS (SELECT 1 FROM canned_response_categories c WHERE c.canned_response_id = canned_responses.canned_response_id AND c.category_id = $category_id) AS in_category
        FROM canned_responses
        WHERE canned_response_archived_at IS NULL
        ORDER BY canned_response_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        if (intval($row['in_category'])) {
            $offered['category'][] = $row;
        } elseif (!intval($row['has_categories'])) {
            $offered['general'][] = $row;
        }
    }
    return $offered;
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
 * A response's HTML made safe and filled in for a ticket, as it goes into a
 * reply: [Ticket Link] a link to the ticket (ticketEmailUrl()), the rest as
 * cannedResponseValues() has them. A placeholder with nothing to put in -
 * [Asset] on a ticket with no asset, or one that is no placeholder at all -
 * stays as it is, for the agent to fill in.
 */
function cannedResponseFill($html, $ticket_id, $agent_name) {
    $html = documentHtmlPurify($html);
    $ticket = ticketRow($ticket_id);
    if (!$ticket) {
        return $html;
    }
    $url = escapeHtml(ticketEmailUrl($ticket));
    $html = preg_replace('/\[ticket(?: |&nbsp;|\x{00A0})+link\]/iu', '<a href="' . $url . '">' . $url . '</a>', $html);
    return ticketPlaceholderFill($html, cannedResponseValues($ticket['ticket_id'], $agent_name), true);
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
