<?php

/*
 * The Knowledge Base - articles written once for every client: how-tos, fixes,
 * policies and the answers given again and again. A client's own documents
 * stay in Documents (functions/files.php); one can be copied in here.
 *
 * Pages: agent/kb.php (the list) and agent/kb_article.php (an article); the
 * windows are in agent/modals/kb/ and the handlers in agent/post/kb.php. It is
 * a module of its own, switched in Admin > Settings > Modules (kbOn()), and
 * comes with database 3.9.5.
 *
 * An article has a title, a line saying what it is for, a category (Admin >
 * Categories, the 'kb' kind), tags (the kb_article kind) and its content - the
 * editor's HTML, purified wherever it is shown (documentHtmlPurify()). Without
 * a publish date it is a draft. Each edit keeps what it said before as a
 * version. When it says how often, it is due for review that long after it was
 * last made, edited or reviewed - an edit counts, as it does for documents.
 *
 * Who can read it (database 3.9.6): agents only, clients - every client, the
 * clients with some client tags, or some clients by name - in their client
 * portal (client/kb.php), or anyone: every client's portal, and the public
 * help center (guest/kb.php) when Admin > Settings > Knowledge Base has it on.
 * Only published articles are shown outside.
 *
 * On a ticket (kbFind(), kbReachFor()): the Knowledge Base picker above the
 * reply suggests articles for it, to link to or put in the reply
 * (agent/includes/inc_kb_picker.php); a ticket can be written up as a draft
 * article (agent/modals/kb/kb_article_from_ticket.php); and a client typing a
 * new ticket's subject in the portal is shown the articles that might help.
 *
 * Keeping it useful (database 3.9.7, kbFeedbackReady()): reads outside ITFlow
 * are counted, readers say whether an article helped and what was missing,
 * and searches that found nothing are listed - for the Needs a look view, an
 * article's Feedback and the Not found list.
 *
 * Reading takes Support (view), writing Support edit, deleting Support full.
 */

const KB_REVIEW_MONTHS = [0 => 'Never', 3 => 'Every 3 months', 6 => 'Every 6 months', 12 => 'Every year', 24 => 'Every 2 years'];

/* Who can read an article: its label, icon and what it means. */
const KB_VISIBILITY = [
    'agents' => ['Agents only', 'fa-user-shield', 'Your team, here'],
    'clients' => ['Clients', 'fa-users', 'In the client portal - every client, or the ones you pick'],
    'public' => ['Anyone', 'fa-globe', 'Every client\'s portal, and the public help center when it\'s on'],
];

/** Is the Knowledge Base in use - switched on, and its tables there (database 3.9.5 and 3.9.6)? */
function kbOn() {
    return isset($GLOBALS['config_module_enable_kb'], $GLOBALS['config_kb_public_enable']) && moduleOn('kb');
}

/** Is the public help center open - the Knowledge Base on, and its switch in Admin > Settings > Knowledge Base? */
function kbPublicOn() {
    return kbOn() && intval($GLOBALS['config_kb_public_enable']) === 1;
}

/* The published articles a client's portal shows, as a WHERE condition on kb_articles. */
function kbClientSql($client_id) {
    $client_id = intval($client_id);
    return "(kb_articles.kb_article_archived_at IS NULL AND kb_articles.kb_article_published_at IS NOT NULL
        AND (kb_articles.kb_article_visibility = 'public' OR (kb_articles.kb_article_visibility = 'clients' AND (kb_articles.kb_article_all_clients = 1
            OR EXISTS (SELECT 1 FROM kb_article_clients kc WHERE kc.kb_article_id = kb_articles.kb_article_id AND kc.client_id = $client_id)
            OR EXISTS (SELECT 1 FROM kb_article_client_tags kt JOIN client_tags ct ON ct.tag_id = kt.tag_id
                WHERE kt.kb_article_id = kb_articles.kb_article_id AND ct.client_id = $client_id)))))";
}

/* The published articles the public help center shows, as a WHERE condition on kb_articles. */
function kbPublicSql() {
    return "(kb_articles.kb_article_archived_at IS NULL AND kb_articles.kb_article_published_at IS NOT NULL AND kb_articles.kb_article_visibility = 'public')";
}

/** An article's address in the public help center - its id, then its title as words, for a readable link. */
function kbPublicUrl($kb_article_id, $title = '') {
    global $config_base_url;
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $title)), '-');
    return "https://$config_base_url/guest/kb.php?article=" . intval($kb_article_id) . ($slug !== '' ? '-' . mb_substr($slug, 0, 80) : '');
}

/** An article's address in the client portal - the contact signs in to read it. */
function kbPortalArticleUrl($kb_article_id) {
    global $config_base_url;
    return "https://$config_base_url/client/kb_article.php?id=" . intval($kb_article_id);
}

/** Has a client's portal anything under Help - an article it may read? */
function kbClientHas($client_id) {
    global $mysqli;
    return kbOn() && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM kb_articles WHERE " . kbClientSql($client_id) . " LIMIT 1")) > 0;
}

/** Which of these articles a client's portal shows - [id => true]. */
function kbClientReadableIds(array $kb_article_ids, $client_id) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $kb_article_ids)));
    if (!$ids || intval($client_id) <= 0) {
        return [];
    }
    $out = [];
    $sql = mysqli_query($mysqli, "SELECT kb_article_id FROM kb_articles WHERE kb_article_id IN (" . implode(',', $ids) . ") AND " . kbClientSql($client_id));
    while ($row = mysqli_fetch_row($sql)) {
        $out[intval($row[0])] = true;
    }
    return $out;
}

/*
 * The published articles that look like they answer some text - a ticket's
 * subject and what was asked, or the subject a client is typing - best first,
 * by the words they share (the FULLTEXT index on their search text). With
 * $search it is a search box's: a title or summary with the words in it counts
 * too, and comes first. $where narrows which may be offered - kbClientSql()
 * for a client's contacts, '' for agents. Rows with the category's name, icon
 * and colour, and the start of the content for an excerpt.
 */
function kbFind($text, $where = '', $limit = 5, $search = false) {
    global $mysqli;
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (mb_strlen($text) < ($search ? 2 : 3)) {
        return [];
    }
    $q = escapeSql(mb_substr($text, 0, $search ? 200 : 1000));
    $match = "MATCH(kb_article_content_raw) AGAINST ('$q')";
    $title_like = $search ? "(kb_article_title LIKE '%$q%' OR kb_article_summary LIKE '%$q%')" : '0';
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title, kb_article_summary, LEFT(kb_article_content, 2000) AS kb_article_content,
            kb_article_visibility, kb_article_all_clients, category_name, category_icon, category_color, $match AS kb_score, $title_like AS kb_title_match
        FROM kb_articles
        LEFT JOIN categories ON category_id = kb_article_category_id
        WHERE kb_article_archived_at IS NULL AND kb_article_published_at IS NOT NULL" . ($where !== '' ? " AND $where" : '') . "
            AND ($match" . ($search ? " OR $title_like" : '') . ")
        ORDER BY kb_title_match DESC, kb_score DESC, kb_article_title ASC
        LIMIT " . intval($limit));
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    // Suggestions leave out the ones that share only a stray word - a fifth of the best one's score
    if (!$search && $rows) {
        $floor = floatval($rows[0]['kb_score']) / 5;
        $rows = array_values(array_filter($rows, function ($row) use ($floor) { return floatval($row['kb_score']) >= $floor; }));
    }
    return $rows;
}

/*
 * How a ticket's client could read an article, for a reply to them -
 * ['text', 'icon', 'url' => the link to send, or '']: the public help
 * center's while it's on and the article is for anyone, their portal's while
 * it shows them the article ($client_readable, from kbClientReadableIds()),
 * otherwise no link - an article for agents only, or not shared with them.
 */
function kbReachFor(array $row, $client_readable) {
    if (($row['kb_article_visibility'] ?? '') === 'public' && kbPublicOn()) {
        return ['text' => 'Public help center', 'icon' => 'fa-globe', 'url' => kbPublicUrl($row['kb_article_id'], $row['kb_article_title'])];
    }
    if ($client_readable && moduleOn('portal')) {
        return ['text' => 'In their portal', 'icon' => 'fa-users', 'url' => kbPortalArticleUrl($row['kb_article_id'])];
    }
    return ['text' => ($row['kb_article_visibility'] ?? 'agents') === 'agents' ? 'Agents only' : 'Not shared with them', 'icon' => 'fa-user-shield', 'url' => ''];
}

/*
 * The rows of the Knowledge Base picker above a ticket reply
 * (agent/includes/inc_kb_picker.php) - each article's title, a line of what it
 * is for and how the ticket's client could read it, with Link when there is
 * a link to send them, and Insert. HTML.
 */
function kbPickerRowsHtml(array $rows, $client_id) {
    $readable = kbClientReadableIds(array_column($rows, 'kb_article_id'), $client_id);
    $html = '';
    foreach ($rows as $row) {
        $id = intval($row['kb_article_id']);
        $reach = kbReachFor($row, isset($readable[$id]));
        $html .= '<div class="itflow-kb-pick-item" data-kb-pick-item data-kb-id="' . $id . '" data-kb-title="' . escapeHtml($row['kb_article_title']) . '" data-kb-url="' . escapeHtml($reach['url']) . '">'
            . '<div class="itflow-kb-pick-text">'
            . '<a class="d-block fw-semibold text-truncate text-reset" href="/agent/kb_article.php?kb_article_id=' . $id . '" target="_blank" title="Read it - opens in a new tab">' . escapeHtml($row['kb_article_title']) . '</a>'
            . '<span class="d-block small text-body-secondary text-truncate">' . escapeHtml(kbArticleExcerpt($row, 120)) . '</span>'
            . '<span class="d-block small text-body-secondary"><i class="fas fa-fw ' . $reach['icon'] . ' me-1"></i>' . $reach['text'] . '</span>'
            . '</div>'
            . '<div class="d-flex gap-1 flex-shrink-0">'
            . ($reach['url'] !== '' ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-kb-pick-link title="Put a link to it in the reply - ' . escapeHtml($reach['text']) . '"><i class="fas fa-link me-1"></i>Link</button>' : '')
            . '<button type="button" class="btn btn-sm btn-outline-secondary" data-kb-pick-insert title="Put what it says in the reply"><i class="fas fa-file-import me-1"></i>Insert</button>'
            . '</div></div>';
    }
    return $html;
}

/* An article's content for a ticket reply - purified, with its pictures' addresses made whole so they show in an email too. */
function kbArticleReplyHtml(array $row) {
    global $config_base_url;
    $html = documentHtmlPurify((string) $row['kb_article_content']);
    return preg_replace('#\b(src|href)="/?(uploads/kb/\d+/[^"]+)"#', '$1="https://' . $config_base_url . '/$2"', $html);
}

/** Who can read each article - [id => ['tags' => [tag rows], 'clients' => [client_id => name]]], in one query each. */
function kbAudiencesFor(array $kb_article_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $kb_article_ids)));
    $out = array_fill_keys($ids, ['tags' => [], 'clients' => []]);
    if (!$ids) {
        return $out;
    }
    $list = implode(',', $ids);
    $sql = mysqli_query($mysqli, "SELECT kt.kb_article_id, tags.tag_id, tag_name, tag_description, tag_color, tag_icon FROM kb_article_client_tags kt
        JOIN tags ON tags.tag_id = kt.tag_id WHERE kt.kb_article_id IN ($list) ORDER BY tag_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $out[intval($row['kb_article_id'])]['tags'][] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT kc.kb_article_id, clients.client_id, client_name FROM kb_article_clients kc
        JOIN clients ON clients.client_id = kc.client_id WHERE kc.kb_article_id IN ($list) ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $out[intval($row['kb_article_id'])]['clients'][intval($row['client_id'])] = $row['client_name'];
    }
    return $out;
}

/** Who can read an article, in a few words - "Agents only", "All clients", "2 tags, 3 clients", "Anyone". Raw. */
function kbAudienceText(array $row, array $audience) {
    $visibility = $row['kb_article_visibility'] ?? 'agents';
    if ($visibility === 'public') {
        return 'Anyone';
    }
    if ($visibility !== 'clients') {
        return 'Agents only';
    }
    if (intval($row['kb_article_all_clients'] ?? 0) === 1) {
        return 'All clients';
    }
    $parts = [];
    if ($audience['tags']) {
        $parts[] = count($audience['tags']) === 1 ? 'Clients tagged ' . $audience['tags'][0]['tag_name'] : count($audience['tags']) . ' client tags';
    }
    if ($audience['clients']) {
        $parts[] = count($audience['clients']) === 1 ? reset($audience['clients']) : count($audience['clients']) . ' clients';
    }
    return $parts ? implode(', ', $parts) : 'No clients picked';
}

/** Who can read an article, as a small badge - its icon and kbAudienceText(). */
function kbAudienceBadge(array $row, array $audience) {
    $visibility = isset(KB_VISIBILITY[$row['kb_article_visibility'] ?? '']) ? $row['kb_article_visibility'] : 'agents';
    $tone = ['agents' => 'bg-body-secondary text-body-secondary', 'clients' => 'bg-info-subtle text-info-emphasis', 'public' => 'bg-success-subtle text-success-emphasis'][$visibility];
    return '<span class="badge rounded-pill fw-normal border ' . $tone . '" title="Who can read it - ' . escapeHtml(KB_VISIBILITY[$visibility][2]) . '">'
        . '<i class="fas fa-fw ' . KB_VISIBILITY[$visibility][1] . ' me-1"></i>' . escapeHtml(kbAudienceText($row, $audience)) . '</span>';
}

/** May the signed-in agent use the Knowledge Base at this level - 1 read, 2 write, 3 delete? */
function kbCan($level = 1) {
    return kbOn() && lookupUserPermission('module_support') >= intval($level);
}

/** Why a page, window or handler is closed - the module off, or the agent's role - or '' when it's open. Plain text. */
function kbClosedText($level = 1) {
    if (!kbOn()) {
        return isset($GLOBALS['config_module_enable_kb'], $GLOBALS['config_kb_public_enable']) ? moduleOffText('kb') : 'The Knowledge Base needs the database update - ask an admin to run it from Maintenance > Update.';
    }
    $has = intval(lookupUserPermission('module_support'));
    return $has >= intval($level) ? '' : permissionRefusedText('module_support', $level, $has);
}

/** One article with its category and the names of who made, changed and reviewed it - or null. */
function kbArticleRow($kb_article_id) {
    global $mysqli;
    $kb_article_id = intval($kb_article_id);
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title, kb_article_summary, kb_article_content, kb_article_category_id,
            kb_article_published_at, kb_article_visibility, kb_article_all_clients, kb_article_review_months, kb_article_reviewed_at, kb_article_reviewed_by,
            " . (kbFeedbackReady() ? 'kb_article_views' : '0 AS kb_article_views') . ", kb_article_created_at, kb_article_created_by,
            kb_article_updated_at, kb_article_updated_by, kb_article_archived_at,
            category_id, category_name, category_description, category_color, category_icon, category_type, category_archived_at,
            creator.user_name AS created_by_name, updater.user_name AS updated_by_name, reviewer.user_name AS reviewed_by_name
        FROM kb_articles
        LEFT JOIN categories ON category_id = kb_article_category_id AND kb_article_category_id > 0
        LEFT JOIN users creator ON creator.user_id = kb_article_created_by AND kb_article_created_by > 0
        LEFT JOIN users updater ON updater.user_id = kb_article_updated_by AND kb_article_updated_by > 0
        LEFT JOIN users reviewer ON reviewer.user_id = kb_article_reviewed_by AND kb_article_reviewed_by > 0
        WHERE kb_article_id = $kb_article_id")) ?: null;
}

/*
 * An article's fields from a form - title (required), summary, category_id,
 * review_months, published (on or off) and content - as [fields, error]. A
 * category must be a Knowledge Base one; anything else counts as none.
 */
function kbArticleFields(array $in) {
    $title = trim((string) ($in['title'] ?? ''));
    if ($title === '') {
        return [[], 'An article needs a title.'];
    }
    if (mb_strlen($title) > 200) {
        return [[], 'Keep the title to 200 characters.'];
    }
    $summary = trim(preg_replace('/\s+/u', ' ', (string) ($in['summary'] ?? '')));
    if (mb_strlen($summary) > 255) {
        return [[], 'Keep the summary to 255 characters.'];
    }
    $category_id = kbCategoryId($in['category_id'] ?? 0);
    $review_months = intval($in['review_months'] ?? 0);

    // Who can read it - clients need a way to be picked
    $visibility = isset(KB_VISIBILITY[$in['visibility'] ?? '']) ? (string) $in['visibility'] : 'agents';
    $all_clients = $visibility === 'clients' && !empty($in['all_clients']);
    $client_tag_ids = $client_ids = [];
    if ($visibility === 'clients' && !$all_clients) {
        $client_tag_ids = kbClientTagIds((array) ($in['client_tag_ids'] ?? []));
        $client_ids = kbClientIds((array) ($in['client_ids'] ?? []), (array) ($in['keep_client_ids'] ?? []));
        if (!$client_tag_ids && !$client_ids) {
            return [[], 'Pick which clients can read it - all of them, the ones with some client tags, or some clients by name.'];
        }
    }

    return [[
        'title' => $title,
        'summary' => $summary,
        'category_id' => $category_id,
        'review_months' => isset(KB_REVIEW_MONTHS[$review_months]) ? $review_months : 0,
        'published' => !empty($in['published']),
        'content' => (string) ($in['content'] ?? ''),
        'visibility' => $visibility,
        'all_clients' => $all_clients,
        'client_tag_ids' => $client_tag_ids,
        'client_ids' => $client_ids,
    ], ''];
}

/** The client tags among these ids. */
function kbClientTagIds(array $ids) {
    global $mysqli;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $ok = [];
    if ($ids) {
        $sql = mysqli_query($mysqli, "SELECT tag_id FROM tags WHERE tag_type = " . tagEntity('client')['type'] . " AND tag_id IN (" . implode(',', $ids) . ")");
        while ($row = mysqli_fetch_row($sql)) {
            $ok[] = intval($row[0]);
        }
    }
    return $ok;
}

/** The clients among these ids that the agent can reach - and any in $keep, already on the article, whether they can or not. */
function kbClientIds(array $ids, array $keep = []) {
    global $mysqli;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $keep = array_map('intval', $keep);
    $ok = [];
    if ($ids) {
        $sql = mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id IN (" . implode(',', $ids) . ")");
        while ($row = mysqli_fetch_row($sql)) {
            $client_id = intval($row[0]);
            if (in_array($client_id, $keep, true) || hasClientAccess($client_id)) {
                $ok[] = $client_id;
            }
        }
    }
    return $ok;
}

/** A Knowledge Base category's id - or 0 for none, or for one that isn't a Knowledge Base category. */
function kbCategoryId($category_id) {
    global $mysqli;
    $category_id = intval($category_id);
    return $category_id > 0 && mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM categories WHERE category_id = $category_id AND category_type = 'kb'")) ? $category_id : 0;
}

/** What search runs over - the title, the summary and the text. Raw. */
function kbArticleContentRaw($title, $summary, $html) {
    return documentContentRaw(trim($title . ' ' . $summary), $html);
}

/** Where an article's images live, on disk. */
function kbArticleDir($kb_article_id) {
    return dirname(__DIR__) . '/uploads/kb/' . intval($kb_article_id);
}

/** Keep what an article says now as a version, before it changes. */
function kbKeepVersion(array $row) {
    global $mysqli;
    $by = intval($row['kb_article_updated_by']) ?: intval($row['kb_article_created_by']);
    $at = escapeSql($row['kb_article_updated_at'] ?: $row['kb_article_created_at']);
    mysqli_query($mysqli, "INSERT INTO kb_article_versions SET
        kb_article_version_title = '" . escapeSql($row['kb_article_title']) . "',
        kb_article_version_summary = '" . escapeSql((string) $row['kb_article_summary']) . "',
        kb_article_version_content = '" . mysqli_real_escape_string($mysqli, (string) $row['kb_article_content']) . "',
        kb_article_version_created_at = '$at',
        kb_article_version_created_by = $by,
        kb_article_version_kb_article_id = " . intval($row['kb_article_id']));
}

/*
 * Save an article - a new one ($row null), or a change to $row, which keeps
 * what it said before as a version when its words change. Images pasted into
 * the editor go into its folder. Its id.
 */
function kbArticleSave(array $f, $row = null) {
    global $mysqli, $session_user_id;
    $user_id = intval($session_user_id ?? 0);
    if ($row === null) {
        mysqli_query($mysqli, "INSERT INTO kb_articles SET kb_article_title = '" . escapeSql($f['title']) . "', kb_article_content = '', kb_article_content_raw = '',
            kb_article_created_by = $user_id");
        $kb_article_id = intval(mysqli_insert_id($mysqli));
        $published_sql = $f['published'] ? 'NOW()' : 'NULL';
    } else {
        $kb_article_id = intval($row['kb_article_id']);
        if ($f['title'] !== $row['kb_article_title'] || $f['summary'] !== (string) $row['kb_article_summary'] || $f['content'] !== (string) $row['kb_article_content']) {
            kbKeepVersion($row);
        }
        // Published stays when it was - its date is when it first went out
        $published_sql = $f['published'] ? ($row['kb_article_published_at'] ? 'kb_article_published_at' : 'NOW()') : 'NULL';
    }
    $content = saveBase64Images($f['content'], dirname(__DIR__) . '/uploads/kb/', 'uploads/kb/', $kb_article_id);
    mysqli_query($mysqli, "UPDATE kb_articles SET
        kb_article_title = '" . escapeSql($f['title']) . "',
        kb_article_summary = " . ($f['summary'] !== '' ? "'" . escapeSql($f['summary']) . "'" : 'NULL') . ",
        kb_article_content = '" . mysqli_real_escape_string($mysqli, $content) . "',
        kb_article_content_raw = '" . escapeSql(kbArticleContentRaw($f['title'], $f['summary'], $content)) . "',
        kb_article_category_id = " . intval($f['category_id']) . ",
        kb_article_review_months = " . intval($f['review_months']) . ",
        kb_article_published_at = $published_sql,
        kb_article_visibility = '" . escapeSql($f['visibility'] ?? 'agents') . "',
        kb_article_all_clients = " . (!empty($f['all_clients']) ? 1 : 0) . "
        " . ($row === null ? '' : ", kb_article_updated_at = NOW(), kb_article_updated_by = $user_id") . "
        WHERE kb_article_id = $kb_article_id");
    kbAudienceSave($kb_article_id, $f['client_tag_ids'] ?? [], $f['client_ids'] ?? []);
    return $kb_article_id;
}

/** Who can read an article as it stands, as the fields kbArticleSave() takes - to save it again unchanged. */
function kbAudienceFields(array $row) {
    $audience = kbAudiencesFor([$row['kb_article_id']])[intval($row['kb_article_id'])];
    return [
        'visibility' => isset(KB_VISIBILITY[$row['kb_article_visibility'] ?? '']) ? $row['kb_article_visibility'] : 'agents',
        'all_clients' => intval($row['kb_article_all_clients'] ?? 0) === 1,
        'client_tag_ids' => array_map('intval', array_column($audience['tags'], 'tag_id')),
        'client_ids' => array_keys($audience['clients']),
    ];
}

/* Set the client tags and clients an article is for - replacing what it had. */
function kbAudienceSave($kb_article_id, array $client_tag_ids, array $client_ids) {
    global $mysqli;
    $kb_article_id = intval($kb_article_id);
    mysqli_query($mysqli, "DELETE FROM kb_article_client_tags WHERE kb_article_id = $kb_article_id");
    mysqli_query($mysqli, "DELETE FROM kb_article_clients WHERE kb_article_id = $kb_article_id");
    foreach (array_unique(array_map('intval', $client_tag_ids)) as $tag_id) {
        mysqli_query($mysqli, "INSERT INTO kb_article_client_tags SET kb_article_id = $kb_article_id, tag_id = $tag_id");
    }
    foreach (array_unique(array_map('intval', $client_ids)) as $client_id) {
        mysqli_query($mysqli, "INSERT INTO kb_article_clients SET kb_article_id = $kb_article_id, client_id = $client_id");
    }
}

/*
 * When a published article is next due for review - and whether it is - or
 * null when it isn't reviewed (never, a draft, or archived). Like a
 * document's: from when it was last made, edited or reviewed.
 */
function kbReviewState(array $row) {
    $months = intval($row['kb_article_review_months'] ?? 0);
    if ($months <= 0 || empty($row['kb_article_published_at']) || !empty($row['kb_article_archived_at'])) {
        return null;
    }
    $times = array_filter([$row['kb_article_created_at'] ?? null, $row['kb_article_updated_at'] ?? null, $row['kb_article_reviewed_at'] ?? null]);
    $last = max(array_map('strtotime', $times));
    $next = strtotime("+$months months", $last);
    return [
        'last' => date('Y-m-d H:i:s', $last),
        'reviewed' => !empty($row['kb_article_reviewed_at']) && strtotime($row['kb_article_reviewed_at']) === $last,
        'next' => date('Y-m-d H:i:s', $next),
        'due' => $next < time(),
    ];
}

/* The articles due for review, as a WHERE condition - what kbReviewState() says, in SQL. */
function kbReviewDueSql() {
    return "(kb_article_archived_at IS NULL AND kb_article_published_at IS NOT NULL AND kb_article_review_months > 0
        AND GREATEST(kb_article_created_at, COALESCE(kb_article_updated_at, kb_article_created_at), COALESCE(kb_article_reviewed_at, kb_article_created_at))
            + INTERVAL kb_article_review_months MONTH < NOW())";
}

/** A line saying what an article is - its summary, or the start of its text. Raw. */
function kbArticleExcerpt(array $row, $length = 160) {
    $summary = trim((string) ($row['kb_article_summary'] ?? ''));
    if ($summary !== '') {
        return $summary;
    }
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) ($row['kb_article_content'] ?? ''))), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
}

/** An article's square icon - in its category's icon and colour, or a book in the accent colour. */
function kbArticleTile(array $row, $size = '') {
    $icon = categoryIcon($row['category_icon'] ?? '') ?: 'book';
    $color = categoryColor($row['category_color'] ?? '');
    return '<span class="itflow-file-icon itflow-file-tone-document' . ($size ? " itflow-file-icon-$size" : '') . '"' . ($color ? ' style="--itflow-file-tone: ' . $color . '"' : '')
        . ' aria-hidden="true"><i class="fas fa-fw fa-' . $icon . '"></i></span>';
}

/** The badges after an article's title - Draft, Archived, Review due. */
function kbArticleBadges(array $row, $size = '') {
    $fs = $size === 'lg' ? ' fs-6' : '';
    $html = '';
    if (!empty($row['kb_article_archived_at'])) {
        $html .= '<span class="badge rounded-pill text-bg-secondary fw-normal' . $fs . '">Archived</span>';
    } elseif (empty($row['kb_article_published_at'])) {
        $html .= '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border fw-normal' . $fs . '" title="Not finished - publish it when it is">Draft</span>';
    }
    $review = kbReviewState($row);
    if ($review && $review['due']) {
        $html .= '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-normal' . $fs . '" title="Due ' . date('M j, Y', strtotime($review['next'])) . '"><i class="fas fa-calendar-check me-1"></i>Review due</span>';
    }
    return $html;
}

/* Delete an article for good, with its versions, tags and images. Its title, or null. */
function kbArticleDelete($kb_article_id) {
    global $mysqli;
    $kb_article_id = intval($kb_article_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT kb_article_title FROM kb_articles WHERE kb_article_id = $kb_article_id"));
    if (!$row) {
        return null;
    }
    mysqli_query($mysqli, "DELETE FROM kb_articles WHERE kb_article_id = $kb_article_id");
    mysqli_query($mysqli, "DELETE FROM kb_article_versions WHERE kb_article_version_kb_article_id = $kb_article_id");
    mysqli_query($mysqli, "DELETE FROM kb_article_tags WHERE kb_article_id = $kb_article_id");
    mysqli_query($mysqli, "DELETE FROM kb_article_client_tags WHERE kb_article_id = $kb_article_id");
    mysqli_query($mysqli, "DELETE FROM kb_article_clients WHERE kb_article_id = $kb_article_id");
    if (kbFeedbackReady()) {
        mysqli_query($mysqli, "DELETE FROM kb_article_feedback WHERE kb_article_feedback_kb_article_id = $kb_article_id");
    }
    removeDirectory(kbArticleDir($kb_article_id));
    return $row['kb_article_title'];
}

/*
 * A new draft article from a client's document - its name, description and
 * content, with copies of its images. The caller has checked the document's
 * client. The article's id, or 0.
 */
function kbArticleFromDocument($document_id) {
    global $mysqli;
    $document_id = intval($document_id);
    $doc = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_name, document_description, document_content FROM documents WHERE document_id = $document_id"));
    if (!$doc) {
        return 0;
    }
    $kb_article_id = kbArticleSave([
        'title' => mb_substr(trim($doc['document_name']), 0, 200),
        'summary' => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $doc['document_description'])), 0, 255),
        'category_id' => 0,
        'review_months' => 0,
        'published' => false,
        'content' => '',
    ]);
    $from = dirname(__DIR__) . "/uploads/documents/$document_id";
    $content = (string) $doc['document_content'];
    if (is_dir($from)) {
        copyDirectory($from, kbArticleDir($kb_article_id));
        $content = str_replace("/uploads/documents/$document_id/", "/uploads/kb/$kb_article_id/", $content);
    }
    mysqli_query($mysqli, "UPDATE kb_articles SET kb_article_content = '" . mysqli_real_escape_string($mysqli, $content) . "',
        kb_article_content_raw = '" . escapeSql(kbArticleContentRaw($doc['document_name'], (string) $doc['document_description'], $content)) . "'
        WHERE kb_article_id = $kb_article_id");
    return $kb_article_id;
}

/*
 * Keeping it useful (database 3.9.7): how many times each article was read
 * outside ITFlow, "Was this helpful?" from those readers, and what they
 * searched for and didn't find. Readers are contacts in the client portal
 * and anyone on the public help center; agents reading here aren't counted.
 */

const KB_SEARCH_MISS_DAYS = 90;

/** Has the database the read counts, feedback and searches that found nothing (3.9.7)? */
function kbFeedbackReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.9.7', '>=');
    }
    return kbOn() && $ready;
}

/** Count a read of an article outside ITFlow - once a visit, and not for a search engine, a link preview or a script. */
function kbCountView($kb_article_id) {
    global $mysqli;
    $kb_article_id = intval($kb_article_id);
    $agent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (!kbFeedbackReady() || !empty($_SESSION['kb_viewed'][$kb_article_id]) || $agent === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|java\//', $agent)) {
        return;
    }
    $_SESSION['kb_viewed'][$kb_article_id] = 1;
    mysqli_query($mysqli, "UPDATE kb_articles SET kb_article_views = kb_article_views + 1 WHERE kb_article_id = $kb_article_id");
}

/*
 * "Was this helpful?" from a reader - yes or no, from a contact in the portal
 * ($contact_id at $client_id) or someone on the public help center. A
 * contact has one answer an article, and a new one replaces it; on the help
 * center it is one a visit, and at most 20 an hour from one IP address. The
 * answer is kept in the visit's session, so what was missing can be added to
 * it. Whether it was kept.
 */
function kbFeedbackSave($kb_article_id, $helpful, $source, $contact_id = 0, $client_id = 0) {
    global $mysqli;
    $kb_article_id = intval($kb_article_id);
    $contact_id = intval($contact_id);
    $client_id = intval($client_id);
    $ip = '';
    if ($source === 'portal' && $contact_id) {
        mysqli_query($mysqli, "DELETE FROM kb_article_feedback WHERE kb_article_feedback_kb_article_id = $kb_article_id
            AND kb_article_feedback_source = 'portal' AND kb_article_feedback_contact_id = $contact_id");
    } else {
        $source = 'public';
        $contact_id = $client_id = 0;
        $ip = escapeSql(mb_substr(getIP(), 0, 45));
        $mine = intval($_SESSION['kb_feedback'][$kb_article_id] ?? 0);
        if ($mine) {
            // Changing their mind replaces the answer they gave
            mysqli_query($mysqli, "DELETE FROM kb_article_feedback WHERE kb_article_feedback_id = $mine AND kb_article_feedback_kb_article_id = $kb_article_id AND kb_article_feedback_source = 'public'");
        } elseif (intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM kb_article_feedback WHERE kb_article_feedback_ip = '$ip'
            AND kb_article_feedback_created_at > NOW() - INTERVAL 1 HOUR"))[0]) >= 20) {
            return false;
        }
    }
    mysqli_query($mysqli, "INSERT INTO kb_article_feedback SET kb_article_feedback_kb_article_id = $kb_article_id, kb_article_feedback_helpful = " . ($helpful ? 1 : 0) . ",
        kb_article_feedback_source = '$source', kb_article_feedback_contact_id = $contact_id, kb_article_feedback_client_id = $client_id,
        kb_article_feedback_ip = " . ($ip !== '' ? "'$ip'" : 'NULL'));
    $_SESSION['kb_feedback'][$kb_article_id] = intval(mysqli_insert_id($mysqli));
    return true;
}

/** What was missing, added to the "No" this visit gave an article. Whether it was kept. */
function kbFeedbackComment($kb_article_id, $comment) {
    global $mysqli;
    $kb_article_id = intval($kb_article_id);
    $feedback_id = intval($_SESSION['kb_feedback'][$kb_article_id] ?? 0);
    $comment = trim(mb_substr(preg_replace('/\s+/u', ' ', strip_tags((string) $comment)), 0, 500));
    if (!$feedback_id || $comment === '') {
        return false;
    }
    mysqli_query($mysqli, "UPDATE kb_article_feedback SET kb_article_feedback_comment = '" . escapeSql($comment) . "'
        WHERE kb_article_feedback_id = $feedback_id AND kb_article_feedback_kb_article_id = $kb_article_id AND kb_article_feedback_helpful = 0");
    return mysqli_affected_rows($mysqli) > 0;
}

/*
 * Where a reader is with "Was this helpful?" on an article - '' (not
 * answered), 'yes', 'no' (to say what was missing) or 'done'. A contact's
 * answer from an earlier visit counts.
 */
function kbFeedbackState($kb_article_id, $contact_id = 0) {
    global $mysqli;
    $kb_article_id = intval($kb_article_id);
    $feedback_id = intval($_SESSION['kb_feedback'][$kb_article_id] ?? 0);
    $where = $feedback_id ? "kb_article_feedback_id = $feedback_id"
        : (intval($contact_id) ? "kb_article_feedback_source = 'portal' AND kb_article_feedback_contact_id = " . intval($contact_id) : '0');
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT kb_article_feedback_id, kb_article_feedback_helpful, kb_article_feedback_comment FROM kb_article_feedback
        WHERE kb_article_feedback_kb_article_id = $kb_article_id AND $where ORDER BY kb_article_feedback_id DESC LIMIT 1"));
    if (!$row) {
        return '';
    }
    $_SESSION['kb_feedback'][$kb_article_id] = intval($row['kb_article_feedback_id']);
    return intval($row['kb_article_feedback_helpful']) ? 'yes' : ($row['kb_article_feedback_comment'] !== null ? 'done' : 'no');
}

/*
 * "Was this helpful?" under an article in the portal or on the public help
 * center, for where the reader is with it (kbFeedbackState()). Its forms
 * post to $action with kb_feedback (the answer) or kb_feedback_comment (what
 * was missing). $ask_url, when there is one, offers a ticket instead. HTML.
 */
function kbFeedbackHtml($kb_article_id, $state, $action, $csrf_token, $ask_url = '') {
    $kb_article_id = intval($kb_article_id);
    $hidden = '<input type="hidden" name="csrf_token" value="' . escapeHtml($csrf_token) . '">';
    $html = '<div class="itflow-portal-panel itflow-portal-panel-body itflow-kb-feedback d-print-none mb-4" id="kb-feedback">';
    if ($state === 'yes') {
        $html .= '<i class="fas fa-check-circle text-success me-2"></i>Thanks - glad it helped.';
    } elseif ($state === 'done') {
        $html .= '<i class="fas fa-check-circle text-success me-2"></i>Thanks - we\'ll use that to make it better.';
    } elseif ($state === 'no') {
        $html .= '<form method="post" action="' . escapeHtml($action) . '">' . $hidden
            . '<label class="form-label fw-semibold" for="kbFeedbackComment">Sorry it didn\'t help. What were you looking for?</label>'
            . '<textarea class="form-control" id="kbFeedbackComment" name="comment" rows="2" maxlength="500" required placeholder="What was missing, or what didn\'t work"></textarea>'
            . '<div class="d-flex flex-wrap align-items-center gap-3 mt-2"><button type="submit" class="btn btn-sm btn-primary" name="kb_feedback_comment" value="' . $kb_article_id . '"><i class="fas fa-paper-plane me-1"></i>Send</button>'
            . ($ask_url !== '' ? '<a href="' . escapeHtml($ask_url) . '">Or ask us in a ticket</a>' : '') . '</div></form>';
    } else {
        $html .= '<form method="post" action="' . escapeHtml($action) . '" class="d-flex flex-wrap align-items-center gap-2">' . $hidden
            . '<input type="hidden" name="kb_feedback" value="' . $kb_article_id . '">'
            . '<span class="fw-semibold me-2">Was this helpful?</span>'
            . '<button type="submit" class="btn btn-sm btn-outline-success" name="helpful" value="1"><i class="fas fa-thumbs-up me-1"></i>Yes</button>'
            . '<button type="submit" class="btn btn-sm btn-outline-secondary" name="helpful" value="0"><i class="fas fa-thumbs-down me-1"></i>No</button></form>';
    }
    return $html . '</div>';
}

/** The readers' answers on each article - [id => ['yes', 'no', 'open' => the noes nobody has dealt with]]. */
function kbFeedbackCounts(array $kb_article_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $kb_article_ids)));
    $out = array_fill_keys($ids, ['yes' => 0, 'no' => 0, 'open' => 0]);
    if (!$ids || !kbFeedbackReady()) {
        return $out;
    }
    $sql = mysqli_query($mysqli, "SELECT kb_article_feedback_kb_article_id AS id, SUM(kb_article_feedback_helpful = 1) AS yes, SUM(kb_article_feedback_helpful = 0) AS no,
            SUM(kb_article_feedback_helpful = 0 AND kb_article_feedback_dealt_at IS NULL) AS open
        FROM kb_article_feedback WHERE kb_article_feedback_kb_article_id IN (" . implode(',', $ids) . ") GROUP BY kb_article_feedback_kb_article_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $out[intval($row['id'])] = ['yes' => intval($row['yes']), 'no' => intval($row['no']), 'open' => intval($row['open'])];
    }
    return $out;
}

/** The articles a reader said didn't help, that nobody has dealt with yet - a WHERE on kb_articles. */
function kbNeedsLookSql() {
    return "(kb_articles.kb_article_archived_at IS NULL AND EXISTS (SELECT 1 FROM kb_article_feedback f WHERE f.kb_article_feedback_kb_article_id = kb_articles.kb_article_id
        AND f.kb_article_feedback_helpful = 0 AND f.kb_article_feedback_dealt_at IS NULL))";
}

/** How many articles want an agent - due for review, or said not to help - for the main menu. */
function kbAttentionCount() {
    global $mysqli;
    if (!kbOn()) {
        return 0;
    }
    return intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM kb_articles WHERE " . kbReviewDueSql() . (kbFeedbackReady() ? " OR " . kbNeedsLookSql() : '')))[0]);
}

/** How an article does with its readers, for the list - its reads, the share who said it helped, and the noes to look at. HTML. */
function kbReadersHtml(array $row, array $counts) {
    $views = intval($row['kb_article_views'] ?? 0);
    $answers = $counts['yes'] + $counts['no'];
    $html = '<span title="Times read in the client portal and the public help center">' . number_format($views) . ' read' . ($views === 1 ? '' : 's') . '</span>';
    if ($answers) {
        $html .= '<div class="text-body-secondary" title="' . $counts['yes'] . ' said it helped, ' . $counts['no'] . ' said it didn\'t"><i class="fas fa-fw fa-thumbs-up me-1"></i>'
            . round(100 * $counts['yes'] / $answers) . '% of ' . $answers . '</div>';
    }
    if ($counts['open']) {
        $html .= '<div class="text-warning-emphasis fw-semibold">' . $counts['open'] . ' to look at</div>';
    }
    return $html;
}

/*
 * What readers said didn't help on an article, for its page - the ones nobody
 * has dealt with first, then the newest - with the contact's and client's
 * names. A client the agent can't reach isn't named.
 */
function kbFeedbackFor($kb_article_id, $limit = 20) {
    global $mysqli;
    if (!kbFeedbackReady()) {
        return [];
    }
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT kb_article_feedback_id, kb_article_feedback_helpful, kb_article_feedback_comment, kb_article_feedback_source, kb_article_feedback_contact_id,
            kb_article_feedback_client_id, kb_article_feedback_created_at, kb_article_feedback_dealt_at, contact_name, client_name, user_name AS dealt_by_name
        FROM kb_article_feedback
        LEFT JOIN contacts ON contact_id = kb_article_feedback_contact_id AND kb_article_feedback_contact_id > 0
        LEFT JOIN clients ON client_id = kb_article_feedback_client_id AND kb_article_feedback_client_id > 0
        LEFT JOIN users ON user_id = kb_article_feedback_dealt_by AND kb_article_feedback_dealt_by > 0
        WHERE kb_article_feedback_kb_article_id = " . intval($kb_article_id) . " AND kb_article_feedback_helpful = 0
        ORDER BY kb_article_feedback_dealt_at IS NULL DESC, kb_article_feedback_id DESC
        LIMIT " . intval($limit));
    while ($row = mysqli_fetch_assoc($sql)) {
        if (intval($row['kb_article_feedback_client_id']) && !hasClientAccess(intval($row['kb_article_feedback_client_id']))) {
            $row['contact_name'] = $row['client_name'] = null;
        }
        $rows[] = $row;
    }
    return $rows;
}

/*
 * A search of Help or the public help center that found nothing - counted by
 * its words, where and for which client. A search repeated on a visit counts
 * once, and a visit counts at most 20, so a script can't fill the list.
 */
function kbSearchMissSave($q, $source, $client_id = 0) {
    global $mysqli;
    $q = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $q)));
    $source = $source === 'portal' ? 'portal' : 'public';
    $key = md5("$source|$q");
    if (!kbFeedbackReady() || mb_strlen($q) < 2 || mb_strlen($q) > 100 || isset($_SESSION['kb_misses'][$key]) || count($_SESSION['kb_misses'] ?? []) >= 20) {
        return;
    }
    $_SESSION['kb_misses'][$key] = 1;
    mysqli_query($mysqli, "INSERT INTO kb_search_misses SET kb_search_miss_query = '" . escapeSql($q) . "', kb_search_miss_source = '$source', kb_search_miss_client_id = " . intval($client_id) . "
        ON DUPLICATE KEY UPDATE kb_search_miss_searches = kb_search_miss_searches + 1, kb_search_miss_last_at = NOW()");
}

/*
 * The searches that found nothing in the last 90 days, of the clients the
 * agent can reach - by their words, the most searched first: [query =>
 * ['searches', 'last_at', 'places' => [['source', 'client_id', 'client_name']]]].
 */
function kbSearchMisses($limit = 100) {
    global $mysqli;
    if (!kbFeedbackReady()) {
        return [];
    }
    $out = [];
    $sql = mysqli_query($mysqli, "SELECT kb_search_miss_query, kb_search_miss_source, kb_search_miss_client_id, kb_search_miss_searches, kb_search_miss_last_at, client_name
        FROM kb_search_misses
        LEFT JOIN clients ON client_id = kb_search_miss_client_id AND kb_search_miss_client_id > 0
        WHERE kb_search_miss_last_at > NOW() - INTERVAL " . KB_SEARCH_MISS_DAYS . " DAY" . clientScopeSql('kb_search_miss_client_id') . "
        ORDER BY kb_search_miss_source = 'public' DESC, client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $query = (string) $row['kb_search_miss_query'];
        $out[$query] = $out[$query] ?? ['searches' => 0, 'last_at' => '', 'places' => []];
        $out[$query]['searches'] += intval($row['kb_search_miss_searches']);
        $out[$query]['last_at'] = max($out[$query]['last_at'], (string) $row['kb_search_miss_last_at']);
        $out[$query]['places'][] = ['source' => $row['kb_search_miss_source'], 'client_id' => intval($row['kb_search_miss_client_id']), 'client_name' => $row['client_name']];
    }
    uasort($out, function ($a, $b) {
        return [$b['searches'], $b['last_at']] <=> [$a['searches'], $a['last_at']];
    });
    return array_slice($out, 0, intval($limit), true);
}

/** How many different searches found nothing in the last 90 days, of the clients the agent can reach. */
function kbSearchMissCount() {
    global $mysqli;
    return kbFeedbackReady() ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(DISTINCT kb_search_miss_query) FROM kb_search_misses
        WHERE kb_search_miss_last_at > NOW() - INTERVAL " . KB_SEARCH_MISS_DAYS . " DAY" . clientScopeSql('kb_search_miss_client_id')))[0]) : 0;
}
