<?php

/*
 * ITFlow - document templates (Admin > Document Templates): documents ready
 * to start a client's from - a runbook, a build sheet, a plan - with their
 * images, kept in uploads/document_templates/<id>/. A document made from one
 * gets a copy of what it says and its images, and remembers which template it
 * came from (documents.document_template_id, from database 3.2.5 on), so each
 * template's use is counted. Words in square brackets - [Client], [Date] - are
 * left for whoever writes the document to fill in.
 *
 * Shared by admin/document_templates.php, admin/document_template.php,
 * admin/modals/document_template/*, admin/post/document_template.php and the
 * New document from a template window and its handler on the agent side
 * (agent/modals/document/document_add_from_template.php, agent/post/document.php).
 */

/** Where a template's images are kept, from the app's root - its folder under uploads/document_templates/. */
function documentTemplateDir($document_template_id) {
    return dirname(__DIR__) . '/uploads/document_templates/' . intval($document_template_id);
}

/** One template, archived or not, with who made it and last changed it - or null. */
function documentTemplateRow($document_template_id) {
    global $mysqli;
    $document_template_id = intval($document_template_id);
    if ($document_template_id <= 0) {
        return null;
    }
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_template_id, document_template_name, document_template_description, document_template_content,
            document_template_created_at, document_template_updated_at, document_template_archived_at, document_template_created_by, document_template_updated_by,
            creator.user_name AS created_by_name, updater.user_name AS updated_by_name
        FROM document_templates
        LEFT JOIN users creator ON creator.user_id = document_template_created_by AND document_template_created_by > 0
        LEFT JOIN users updater ON updater.user_id = document_template_updated_by AND document_template_updated_by > 0
        WHERE document_template_id = $document_template_id"));
    return $row ?: null;
}

/*
 * How the templates have been used - template id => count (documents made from
 * it that are not archived), clients (how many clients have one) and last (when
 * the latest was made). Ones not used aren't in it.
 */
function documentTemplateUsage(array $document_template_ids) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $document_template_ids)));
    $usage = [];
    if (!$ids) {
        return $usage;
    }
    $sql = mysqli_query($mysqli, "SELECT document_template_id, COUNT(*) AS n, COUNT(DISTINCT document_client_id) AS clients, MAX(document_created_at) AS last
        FROM documents WHERE document_template_id IN (" . implode(',', $ids) . ") AND document_archived_at IS NULL GROUP BY document_template_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $usage[intval($row['document_template_id'])] = ['count' => intval($row['n']), 'clients' => intval($row['clients']), 'last' => $row['last']];
    }
    return $usage;
}

/*
 * The template window's fields, checked - or null with $error saying why.
 * $post: name, description (one line on what it is for) and content (the
 * editor's HTML, kept as written - it is made safe where it is shown).
 */
function documentTemplateFields(array $post, &$error = '') {
    $error = '';
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($post['name'] ?? ''))));
    if ($name === '') {
        $error = 'A document template needs a name';
        return null;
    }
    if (mb_strlen($name) > 200) {
        $error = 'Keep the name to 200 characters';
        return null;
    }
    $description = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($post['description'] ?? ''))));
    if (mb_strlen($description) > 500) {
        $error = 'Keep the summary to 500 characters';
        return null;
    }
    return ['name' => $name, 'description' => $description, 'content' => (string) ($post['content'] ?? '')];
}

/*
 * Save a template's fields (documentTemplateFields()) - a new one with
 * $document_template_id 0. Pasted-in images are kept in its folder, and on a
 * change the ones it no longer shows are taken away. Its id.
 */
function documentTemplateSave($document_template_id, array $f) {
    global $mysqli, $session_user_id;
    $document_template_id = intval($document_template_id);
    $user_id = intval($session_user_id ?? 0);
    $set = "document_template_name = '" . escapeSql($f['name']) . "', document_template_description = '" . escapeSql($f['description']) . "'";
    $new = !$document_template_id;
    if ($new) {
        // Made first, so its images have a folder named for it
        mysqli_query($mysqli, "INSERT INTO document_templates SET $set, document_template_content = '', document_template_created_by = $user_id");
        $document_template_id = intval(mysqli_insert_id($mysqli));
    }
    $content = saveBase64Images($f['content'], dirname(__DIR__) . '/uploads/document_templates/', 'uploads/document_templates/', $document_template_id);
    cleanupUnusedImages($content, documentTemplateDir($document_template_id), '/uploads/document_templates/' . $document_template_id);
    // A new one hasn't been changed yet - its content going in isn't a change
    mysqli_query($mysqli, "UPDATE document_templates SET $set, document_template_content = '" . mysqli_real_escape_string($mysqli, $content) . "',
        " . ($new ? 'document_template_updated_at = NULL' : "document_template_updated_by = $user_id") . " WHERE document_template_id = $document_template_id");
    return $document_template_id;
}

/*
 * A template's HTML with its images pointed at another folder of copies - for a
 * copy of the template, or a document made from it. $to_web is the new
 * folder's web path ('/uploads/documents/12/').
 */
function documentTemplateCopyImages($document_template_id, $to_dir, $to_web, $html) {
    $document_template_id = intval($document_template_id);
    copyDirectory(documentTemplateDir($document_template_id), $to_dir);
    return str_replace("/uploads/document_templates/$document_template_id/", $to_web, (string) $html);
}

/* A new template starting from this one - "Copy of", with copies of its images. Its id, or 0. */
function documentTemplateCopy($document_template_id) {
    global $mysqli, $session_user_id;
    $row = documentTemplateRow($document_template_id);
    if (!$row) {
        return 0;
    }
    $user_id = intval($session_user_id ?? 0);
    mysqli_query($mysqli, "INSERT INTO document_templates SET document_template_name = '" . escapeSql(mb_substr('Copy of ' . $row['document_template_name'], 0, 200)) . "',
        document_template_description = '" . escapeSql((string) $row['document_template_description']) . "', document_template_content = '', document_template_created_by = $user_id");
    $new_id = intval(mysqli_insert_id($mysqli));
    $content = documentTemplateCopyImages($row['document_template_id'], documentTemplateDir($new_id), "/uploads/document_templates/$new_id/", $row['document_template_content']);
    mysqli_query($mysqli, "UPDATE document_templates SET document_template_content = '" . mysqli_real_escape_string($mysqli, $content) . "', document_template_updated_at = NULL WHERE document_template_id = $new_id");
    return $new_id;
}

/* Delete a template and its folder of images. The documents made from it stay, no longer linked to it. */
function documentTemplateDelete($document_template_id) {
    global $mysqli;
    $document_template_id = intval($document_template_id);
    mysqli_query($mysqli, "DELETE FROM document_templates WHERE document_template_id = $document_template_id");
    mysqli_query($mysqli, "UPDATE documents SET document_template_id = 0 WHERE document_template_id = $document_template_id");
    removeDirectory(documentTemplateDir($document_template_id));
}

/*
 * Make a client's document from a template - what it says and its images,
 * copied, in $folder_id, called $name (the template's when '') - remembering
 * the template. The new document's id, or 0 when the template is gone or
 * archived.
 */
function documentTemplateMakeDocument($document_template_id, $client_id, $name = '', $description = '', $folder_id = 0) {
    global $mysqli, $session_user_id;
    $row = documentTemplateRow($document_template_id);
    if (!$row || $row['document_template_archived_at']) {
        return 0;
    }
    $template_id = intval($row['document_template_id']);
    $client_id = intval($client_id);
    $name = trim((string) $name) !== '' ? mb_substr(trim((string) $name), 0, 200) : (string) $row['document_template_name'];
    $description = trim((string) $description) !== '' ? trim((string) $description) : (string) $row['document_template_description'];
    mysqli_query($mysqli, "INSERT INTO documents SET document_name = '" . escapeSql($name) . "', document_description = '" . escapeSql($description) . "',
        document_content = '', document_content_raw = '', document_folder_id = " . intval($folder_id) . ", document_created_by = " . intval($session_user_id ?? 0) . ",
        document_client_id = $client_id, document_template_id = $template_id");
    $document_id = intval(mysqli_insert_id($mysqli));

    // The template's images come along, and its HTML is pointed at the copies
    $html = documentTemplateCopyImages($template_id, dirname(__DIR__) . "/uploads/documents/$document_id", "/uploads/documents/$document_id/", $row['document_template_content']);
    mysqli_query($mysqli, "UPDATE documents SET document_content = '" . mysqli_real_escape_string($mysqli, $html) . "',
        document_content_raw = '" . escapeSql(documentContentRaw($name, $html)) . "' WHERE document_id = $document_id");
    return $document_id;
}

/* What a template says, as a line of plain text - for the lists. */
function documentTemplatePreview($html, $length = 160) {
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) $html)), ENT_QUOTES, 'UTF-8')));
    return mb_strimwidth($text, 0, $length, '...');
}

/* The words in square brackets in a template's text - [Client], [Date of visit] - each once, as written first. */
function documentTemplateBracketsIn($html) {
    $text = html_entity_decode(strip_tags(str_replace('<', ' <', (string) $html)), ENT_QUOTES, 'UTF-8');
    preg_match_all('/\[([A-Za-z][A-Za-z0-9 _\-\/\x{00A0}]{0,40})\]/u', $text, $matches);
    $found = [];
    foreach ($matches[1] as $word) {
        $word = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $word));
        if ($word !== '' && !isset($found[mb_strtolower($word)])) {
            $found[mb_strtolower($word)] = $word;
        }
    }
    return array_values($found);
}

/* A template's HTML (already made safe) with its [words] marked, in its text only - for its page. */
function documentTemplateMarkBrackets($html) {
    $pieces = preg_split('/(<[^>]*>)/', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($pieces as $i => $piece) {
        if ($i % 2 === 0 && $piece !== '') {
            $pieces[$i] = preg_replace('/\[([A-Za-z][A-Za-z0-9 _\-\/]{0,40})\]/', '<span class="badge rounded-pill text-bg-light border font-monospace fw-normal">[$1]</span>', $piece);
        }
    }
    return implode('', $pieces);
}
