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
 * Reading takes Support (view), writing Support edit, deleting Support full.
 */

const KB_REVIEW_MONTHS = [0 => 'Never', 3 => 'Every 3 months', 6 => 'Every 6 months', 12 => 'Every year', 24 => 'Every 2 years'];

/** Is the Knowledge Base in use - switched on, and its tables there (database 3.9.5)? */
function kbOn() {
    return isset($GLOBALS['config_module_enable_kb']) && moduleOn('kb');
}

/** May the signed-in agent use the Knowledge Base at this level - 1 read, 2 write, 3 delete? */
function kbCan($level = 1) {
    return kbOn() && lookupUserPermission('module_support') >= intval($level);
}

/** Why a page, window or handler is closed - the module off, or the agent's role - or '' when it's open. Plain text. */
function kbClosedText($level = 1) {
    if (!kbOn()) {
        return isset($GLOBALS['config_module_enable_kb']) ? moduleOffText('kb') : 'The Knowledge Base needs the database update - ask an admin to run it from Maintenance > Update.';
    }
    $has = intval(lookupUserPermission('module_support'));
    return $has >= intval($level) ? '' : permissionRefusedText('module_support', $level, $has);
}

/** One article with its category and the names of who made, changed and reviewed it - or null. */
function kbArticleRow($kb_article_id) {
    global $mysqli;
    $kb_article_id = intval($kb_article_id);
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title, kb_article_summary, kb_article_content, kb_article_category_id,
            kb_article_published_at, kb_article_review_months, kb_article_reviewed_at, kb_article_reviewed_by, kb_article_created_at, kb_article_created_by,
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
    return [[
        'title' => $title,
        'summary' => $summary,
        'category_id' => $category_id,
        'review_months' => isset(KB_REVIEW_MONTHS[$review_months]) ? $review_months : 0,
        'published' => !empty($in['published']),
        'content' => (string) ($in['content'] ?? ''),
    ], ''];
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
        kb_article_published_at = $published_sql
        " . ($row === null ? '' : ", kb_article_updated_at = NOW(), kb_article_updated_by = $user_id") . "
        WHERE kb_article_id = $kb_article_id");
    return $kb_article_id;
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
