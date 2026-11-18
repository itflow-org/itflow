<?php

/*
 * ITFlow - GET/POST request handlers for the Knowledge Base (agent/kb.php,
 * agent/kb_article.php): writing, editing, publishing, reviewing, archiving,
 * copying and deleting articles, their versions, the bulk actions, and
 * copying a client's document in as a new article. The work is in
 * functions/kb.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// Closed while the Knowledge Base is off, or to a role that can't write in it (or delete, for the deletes)
$kb_requested = array_merge(
    array_intersect(['add_kb_article', 'edit_kb_article', 'restore_kb_article_version', 'bulk_category_kb_articles', 'bulk_publish_kb_articles',
        'bulk_draft_kb_articles', 'bulk_archive_kb_articles', 'bulk_restore_kb_articles', 'bulk_delete_kb_articles'], array_keys($_POST)),
    array_intersect(['publish_kb_article', 'draft_kb_article', 'mark_kb_article_reviewed', 'archive_kb_article', 'restore_kb_article', 'delete_kb_article',
        'copy_kb_article', 'delete_kb_article_version', 'copy_document_to_kb'], array_keys($_GET))
);
if ($kb_requested) {
    validateCSRFToken();
    $kb_closed = kbClosedText(array_intersect(['delete_kb_article', 'delete_kb_article_version', 'bulk_delete_kb_articles'], $kb_requested) ? 3 : 2);
    if ($kb_closed !== '') {
        flashAlert(escapeHtml($kb_closed), 'error');
        redirect();
    }
}

/** The article a handler works on - or a flash and back. */
function kbArticleForHandler($kb_article_id, $live = true) {
    $row = kbArticleRow($kb_article_id);
    if (!$row) {
        flashAlert('That article no longer exists.', 'error');
        redirect('kb.php');
    }
    if ($live && $row['kb_article_archived_at']) {
        flashAlert('Restore the article first.', 'error');
        redirect();
    }
    return $row;
}

/** The ticked articles of a bulk action - [id => title]. */
function kbBulkArticles() {
    global $mysqli;
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['kb_article_ids'] ?? [])))));
    $articles = [];
    if ($ids) {
        $sql = mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title FROM kb_articles WHERE kb_article_id IN (" . implode(',', $ids) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            $articles[intval($row['kb_article_id'])] = $row['kb_article_title'];
        }
    }
    if (!$articles) {
        flashAlert('No articles were ticked.', 'error');
        redirect();
    }
    return $articles;
}

if (isset($_POST['add_kb_article'])) {

    [$fields, $error] = kbArticleFields($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $kb_article_id = kbArticleSave($fields);
    if (isset($_POST['tags_form'])) {
        tagSetForRecord('kb_article', $kb_article_id, (array) ($_POST['tags'] ?? []));
    }

    logAudit("Knowledge Base", "Create", "$session_name wrote " . ($fields['published'] ? '' : 'a draft of ') . "article " . escapeSql($fields['title']), 0, $kb_article_id);

    flashAlert("Article <strong>" . escapeHtml($fields['title']) . "</strong> " . ($fields['published'] ? 'published' : 'saved as a draft'));

    redirect("kb_article.php?kb_article_id=$kb_article_id");
}

if (isset($_POST['edit_kb_article'])) {

    $row = kbArticleForHandler($_POST['kb_article_id'] ?? 0);
    $kb_article_id = intval($row['kb_article_id']);

    [$fields, $error] = kbArticleFields($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    kbArticleSave($fields, $row);
    if (isset($_POST['tags_form'])) {
        tagSetForRecord('kb_article', $kb_article_id, (array) ($_POST['tags'] ?? []));
    }

    $was = !empty($row['kb_article_published_at']);
    $how = $fields['published'] === $was ? 'edited' : ($fields['published'] ? 'edited and published' : 'edited and made a draft of');
    logAudit("Knowledge Base", "Edit", "$session_name $how article " . escapeSql($fields['title']), 0, $kb_article_id);

    flashAlert("Article <strong>" . escapeHtml($fields['title']) . "</strong> saved" . ($fields['published'] === $was ? '' : ($fields['published'] ? ' and published' : ' as a draft')));

    redirect();
}

if (isset($_GET['publish_kb_article']) || isset($_GET['draft_kb_article'])) {

    $publish = isset($_GET['publish_kb_article']);
    $row = kbArticleForHandler($publish ? $_GET['publish_kb_article'] : $_GET['draft_kb_article']);
    $kb_article_id = intval($row['kb_article_id']);

    mysqli_query($mysqli, "UPDATE kb_articles SET kb_article_published_at = " . ($publish ? 'COALESCE(kb_article_published_at, NOW())' : 'NULL') . " WHERE kb_article_id = $kb_article_id");

    logAudit("Knowledge Base", $publish ? "Publish" : "Draft", "$session_name " . ($publish ? 'published' : 'made a draft of') . " article " . escapeSql($row['kb_article_title']), 0, $kb_article_id);

    flashAlert("Article <strong>" . escapeHtml($row['kb_article_title']) . "</strong> " . ($publish ? 'published' : 'is a draft again'));

    redirect();
}

if (isset($_GET['mark_kb_article_reviewed'])) {

    $row = kbArticleForHandler($_GET['mark_kb_article_reviewed']);
    $kb_article_id = intval($row['kb_article_id']);

    mysqli_query($mysqli, "UPDATE kb_articles SET kb_article_reviewed_at = NOW(), kb_article_reviewed_by = " . intval($session_user_id) . " WHERE kb_article_id = $kb_article_id");

    logAudit("Knowledge Base", "Review", "$session_name reviewed article " . escapeSql($row['kb_article_title']), 0, $kb_article_id);

    $months = intval($row['kb_article_review_months']);
    flashAlert("Article <strong>" . escapeHtml($row['kb_article_title']) . "</strong> marked reviewed" . ($months ? ' - next due ' . date('M j, Y', strtotime("+$months months")) : ''));

    redirect();
}

if (isset($_GET['archive_kb_article']) || isset($_GET['restore_kb_article'])) {

    $archive = isset($_GET['archive_kb_article']);
    $row = kbArticleForHandler($archive ? $_GET['archive_kb_article'] : $_GET['restore_kb_article'], false);
    $kb_article_id = intval($row['kb_article_id']);

    mysqli_query($mysqli, "UPDATE kb_articles SET kb_article_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE kb_article_id = $kb_article_id");

    logAudit("Knowledge Base", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " article " . escapeSql($row['kb_article_title']), 0, $kb_article_id);

    flashAlert("Article <strong>" . escapeHtml($row['kb_article_title']) . "</strong> " . ($archive ? 'archived' : 'restored'), $archive ? 'error' : 'success');

    redirect();
}

if (isset($_GET['delete_kb_article'])) {

    $kb_article_id = intval($_GET['delete_kb_article']);
    $title = kbArticleDelete($kb_article_id);
    if ($title === null) {
        flashAlert('That article no longer exists.', 'error');
        redirect('kb.php');
    }

    logAudit("Knowledge Base", "Delete", "$session_name deleted article " . escapeSql($title) . " and its versions", 0, $kb_article_id);

    flashAlert("Article <strong>" . escapeHtml($title) . "</strong> deleted", 'error');

    // Not back to its page - it's gone
    redirect(str_contains((string) ($_SERVER['HTTP_REFERER'] ?? ''), 'kb_article.php') ? 'kb.php' : null);
}

if (isset($_GET['copy_kb_article'])) {

    $row = kbArticleForHandler($_GET['copy_kb_article']);

    // A new draft, with copies of its images and its tags
    $kb_article_id = kbArticleSave([
        'title' => mb_substr('Copy of ' . $row['kb_article_title'], 0, 200),
        'summary' => (string) $row['kb_article_summary'],
        'category_id' => intval($row['kb_article_category_id']),
        'review_months' => intval($row['kb_article_review_months']),
        'published' => false,
        'content' => '',
    ]);
    $content = (string) $row['kb_article_content'];
    $from = kbArticleDir($row['kb_article_id']);
    if (is_dir($from)) {
        copyDirectory($from, kbArticleDir($kb_article_id));
        $content = str_replace('/uploads/kb/' . intval($row['kb_article_id']) . '/', "/uploads/kb/$kb_article_id/", $content);
    }
    mysqli_query($mysqli, "UPDATE kb_articles SET kb_article_content = '" . mysqli_real_escape_string($mysqli, $content) . "',
        kb_article_content_raw = '" . escapeSql(kbArticleContentRaw('Copy of ' . $row['kb_article_title'], (string) $row['kb_article_summary'], $content)) . "'
        WHERE kb_article_id = $kb_article_id");
    mysqli_query($mysqli, "INSERT INTO kb_article_tags (kb_article_id, tag_id) SELECT $kb_article_id, tag_id FROM kb_article_tags WHERE kb_article_id = " . intval($row['kb_article_id']));

    logAudit("Knowledge Base", "Create", "$session_name copied article " . escapeSql($row['kb_article_title']) . " as a new draft", 0, $kb_article_id);

    flashAlert("A copy of <strong>" . escapeHtml($row['kb_article_title']) . "</strong> is ready as a draft");

    redirect("kb_article.php?kb_article_id=$kb_article_id");
}

if (isset($_POST['restore_kb_article_version'])) {

    $kb_article_version_id = intval($_POST['kb_article_version_id'] ?? 0);
    $version = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT kb_article_version_title, kb_article_version_summary, kb_article_version_content, kb_article_version_kb_article_id
        FROM kb_article_versions WHERE kb_article_version_id = $kb_article_version_id"));
    if (!$version) {
        flashAlert('That version no longer exists.', 'error');
        redirect();
    }
    $row = kbArticleForHandler($version['kb_article_version_kb_article_id']);
    $kb_article_id = intval($row['kb_article_id']);

    // What it says now becomes a version itself
    kbArticleSave([
        'title' => $version['kb_article_version_title'],
        'summary' => (string) $version['kb_article_version_summary'],
        'category_id' => intval($row['kb_article_category_id']),
        'review_months' => intval($row['kb_article_review_months']),
        'published' => !empty($row['kb_article_published_at']),
        'content' => (string) $version['kb_article_version_content'],
    ], $row);

    logAudit("Knowledge Base", "Edit", "$session_name put back an earlier version of article " . escapeSql($version['kb_article_version_title']), 0, $kb_article_id);

    flashAlert("The earlier version of <strong>" . escapeHtml($version['kb_article_version_title']) . "</strong> is back - what it said before is kept as a version");

    redirect("kb_article.php?kb_article_id=$kb_article_id");
}

if (isset($_GET['delete_kb_article_version'])) {

    $kb_article_version_id = intval($_GET['delete_kb_article_version']);
    $version = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT kb_article_version_title, kb_article_version_created_at, kb_article_version_kb_article_id
        FROM kb_article_versions WHERE kb_article_version_id = $kb_article_version_id"));
    if (!$version) {
        flashAlert('That version no longer exists.', 'error');
        redirect();
    }

    mysqli_query($mysqli, "DELETE FROM kb_article_versions WHERE kb_article_version_id = $kb_article_version_id");

    logAudit("Knowledge Base", "Edit", "$session_name deleted the version of " . date('M j, Y g:ia', strtotime($version['kb_article_version_created_at'])) . " of article " . escapeSql($version['kb_article_version_title']), 0, intval($version['kb_article_version_kb_article_id']));

    flashAlert("Version deleted", 'error');

    redirect("kb_article.php?kb_article_id=" . intval($version['kb_article_version_kb_article_id']));
}

if (isset($_POST['bulk_category_kb_articles'])) {

    $articles = kbBulkArticles();
    $category_id = kbCategoryId($_POST['category_id'] ?? 0);

    mysqli_query($mysqli, "UPDATE kb_articles SET kb_article_category_id = $category_id WHERE kb_article_id IN (" . implode(',', array_keys($articles)) . ")");

    $category = $category_id ? categoryRow($category_id) : null;
    foreach ($articles as $kb_article_id => $title) {
        logAudit("Knowledge Base", "Edit", "$session_name put article " . escapeSql($title) . ($category ? " in category " . escapeSql($category['category_name']) : " in no category"), 0, $kb_article_id);
    }

    $count = count($articles);
    flashAlert("<strong>$count</strong> article" . ($count === 1 ? '' : 's') . ($category ? " put in <strong>" . escapeHtml($category['category_name']) . "</strong>" : " taken out of their category"));

    redirect();
}

if (isset($_POST['bulk_publish_kb_articles']) || isset($_POST['bulk_draft_kb_articles']) || isset($_POST['bulk_archive_kb_articles']) || isset($_POST['bulk_restore_kb_articles'])) {

    $articles = kbBulkArticles();
    $ids = implode(',', array_keys($articles));
    // What each does, on the ones it applies to
    if (isset($_POST['bulk_publish_kb_articles'])) {
        [$set, $only, $action, $verb] = ['kb_article_published_at = NOW()', 'kb_article_published_at IS NULL AND kb_article_archived_at IS NULL', 'Publish', 'published'];
    } elseif (isset($_POST['bulk_draft_kb_articles'])) {
        [$set, $only, $action, $verb] = ['kb_article_published_at = NULL', 'kb_article_published_at IS NOT NULL AND kb_article_archived_at IS NULL', 'Draft', 'made drafts'];
    } elseif (isset($_POST['bulk_archive_kb_articles'])) {
        [$set, $only, $action, $verb] = ['kb_article_archived_at = NOW()', 'kb_article_archived_at IS NULL', 'Archive', 'archived'];
    } else {
        [$set, $only, $action, $verb] = ['kb_article_archived_at = NULL', 'kb_article_archived_at IS NOT NULL', 'Restore', 'restored'];
    }

    $changed = [];
    $sql = mysqli_query($mysqli, "SELECT kb_article_id, kb_article_title FROM kb_articles WHERE kb_article_id IN ($ids) AND $only");
    while ($row = mysqli_fetch_assoc($sql)) {
        $changed[intval($row['kb_article_id'])] = $row['kb_article_title'];
    }
    if ($changed) {
        mysqli_query($mysqli, "UPDATE kb_articles SET $set WHERE kb_article_id IN (" . implode(',', array_keys($changed)) . ")");
    }
    foreach ($changed as $kb_article_id => $title) {
        logAudit("Knowledge Base", $action, "$session_name " . ($action === 'Draft' ? 'made a draft of' : $verb) . " article " . escapeSql($title), 0, $kb_article_id);
    }

    $count = count($changed);
    $left = count($articles) - $count;
    flashAlert("<strong>$count</strong> article" . ($count === 1 ? '' : 's') . " $verb" . ($left ? " - $left already " . ($left === 1 ? 'was' : 'were') : ''), in_array($action, ['Archive'], true) ? 'error' : 'success');

    redirect();
}

if (isset($_POST['bulk_delete_kb_articles'])) {

    $articles = kbBulkArticles();
    foreach ($articles as $kb_article_id => $title) {
        kbArticleDelete($kb_article_id);
        logAudit("Knowledge Base", "Delete", "$session_name deleted article " . escapeSql($title) . " and its versions", 0, $kb_article_id);
    }

    $count = count($articles);
    flashAlert("<strong>$count</strong> article" . ($count === 1 ? '' : 's') . " deleted", 'error');

    redirect();
}

if (isset($_GET['copy_document_to_kb'])) {

    // The document's client must be one the agent can reach, as on its page
    $document_id = intval($_GET['copy_document_to_kb']);
    $document = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_name, document_client_id FROM documents WHERE document_id = $document_id"));
    if (!$document) {
        flashAlert('That document no longer exists.', 'error');
        redirect();
    }
    $client_id = intval($document['document_client_id']);
    enforceClientAccess();

    $kb_article_id = kbArticleFromDocument($document_id);

    logAudit("Knowledge Base", "Create", "$session_name copied document " . escapeSql($document['document_name']) . " into the Knowledge Base as a draft", 0, $kb_article_id);

    flashAlert("<strong>" . escapeHtml($document['document_name']) . "</strong> is in the Knowledge Base as a draft - take out anything that's only about this client before you publish it");

    redirect("kb_article.php?kb_article_id=$kb_article_id");
}
