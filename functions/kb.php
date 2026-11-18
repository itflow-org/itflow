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
            kb_article_created_at, kb_article_created_by,
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
