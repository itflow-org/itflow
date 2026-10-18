<?php

/*
 * Document templates (Admin > Document Templates) - making, changing,
 * copying, archiving and deleting them, with their images, setting up what
 * they ask for when a document is made (their fill-ins), who should have a
 * document from one and how often those are reviewed, making documents from
 * one for several clients at once, and exporting and importing them
 * (functions/document_template_transfer.php). The work is in
 * functions/document_template.php; a document is made from one by
 * add_document_from_template (agent/post/document.php).
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/* The template a request is for - or back to the list, saying it is gone. */
function documentTemplateForRequest($document_template_id) {
    $row = documentTemplateRow($document_template_id);
    if (!$row) {
        flashAlert('That document template no longer exists.', 'error');
        redirect('document_templates.php');
    }
    return $row;
}

if (isset($_POST['add_document_template']) || isset($_POST['edit_document_template'])) {

    validateCSRFToken();

    $editing = isset($_POST['edit_document_template']);
    $document_template_id = $editing ? intval(documentTemplateForRequest($_POST['document_template_id'] ?? 0)['document_template_id']) : 0;

    $fields = documentTemplateFields($_POST, $error);
    if (!$fields) {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }
    $document_template_id = documentTemplateSave($document_template_id, $fields);
    $name = escapeSql($fields['name']);

    logAudit("Document Template", $editing ? "Edit" : "Create", "$session_name " . ($editing ? 'edited' : 'created') . " document template $name", 0, $document_template_id);

    flashAlert("Document template <strong>" . escapeHtml($fields['name']) . "</strong> " . ($editing ? 'saved' : 'created'));

    // A new one opens on its page
    redirect($editing ? null : "document_template.php?document_template_id=$document_template_id");

}

if (isset($_GET['copy_document_template'])) {

    validateCSRFToken();

    $row = documentTemplateForRequest($_GET['copy_document_template']);
    $new_id = documentTemplateCopy($row['document_template_id']);
    $new_name = (string) documentTemplateRow($new_id)['document_template_name'];

    logAudit("Document Template", "Create", "$session_name copied document template " . escapeSql($row['document_template_name']) . " as " . escapeSql($new_name), 0, $new_id);

    flashAlert("Document template <strong>" . escapeHtml($row['document_template_name']) . "</strong> copied as <strong>" . escapeHtml($new_name) . "</strong>");

    redirect("document_template.php?document_template_id=$new_id");

}

if (isset($_GET['archive_document_template']) || isset($_GET['restore_document_template'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_document_template']);
    $row = documentTemplateForRequest($archive ? $_GET['archive_document_template'] : $_GET['restore_document_template']);
    $document_template_id = intval($row['document_template_id']);

    // Archived, it isn't offered for new documents; the ones made from it stay
    mysqli_query($mysqli, "UPDATE document_templates SET document_template_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE document_template_id = $document_template_id");

    logAudit("Document Template", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " document template " . escapeSql($row['document_template_name']), 0, $document_template_id);

    flashAlert("Document template <strong>" . escapeHtml($row['document_template_name']) . "</strong> " . ($archive ? 'archived' : 'restored'), $archive ? 'error' : 'success');

    redirect();

}

if (isset($_GET['delete_document_template'])) {

    validateCSRFToken();

    $row = documentTemplateForRequest($_GET['delete_document_template']);

    // Its images go with it; the documents made from it stay, no longer linked to it
    documentTemplateDelete($row['document_template_id']);

    logAudit("Document Template", "Delete", "$session_name deleted document template " . escapeSql($row['document_template_name']));

    flashAlert("Document template <strong>" . escapeHtml($row['document_template_name']) . "</strong> deleted", 'error');

    // From its own page, back to the list
    redirect(!empty($_GET['from_page']) ? 'document_templates.php' : null);

}

if (isset($_POST['save_document_template_fill_in'])) {

    // A fill-in set up - a new one, or one in the text or already set up, changed
    validateCSRFToken();

    $row = documentTemplateForRequest($_POST['document_template_id'] ?? 0);
    $document_template_id = intval($row['document_template_id']);
    $original = trim((string) ($_POST['original_name'] ?? ''));

    $name = documentFillInName($_POST['name'] ?? '');
    if ($name === '') {
        flashAlert("A fill-in's name is what goes in square brackets - a letter first, then letters, digits, spaces, _ - or /, up to 41 in all. [Client], [Site] and the others filled in from the client, and the tables of records, are taken.", 'error');
        redirect();
    }
    $type = isset(TICKET_FILL_IN_TYPES[$_POST['type'] ?? '']) ? $_POST['type'] : 'text';
    $options = $type === 'list' ? ticketFillInOptions($_POST['options'] ?? '') : [];
    if ($type === 'list' && count($options) < 2) {
        flashAlert('A list needs at least two choices - one per line.', 'error');
        redirect();
    }
    $required = intval($_POST['required'] ?? 0) ? 1 : 0;
    $help = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['help'] ?? ''))), 0, 255));

    // One name, one fill-in - adding one already in the text but not set up yet sets that one up
    foreach (documentTemplateFillInsFor($document_template_id) as $fill_in) {
        if ($original === '' && !$fill_in['id'] && strtolower($fill_in['name']) === strtolower($name)) {
            $original = $fill_in['name'];
            continue;
        }
        if (strtolower($fill_in['name']) === strtolower($name) && strtolower($fill_in['name']) !== strtolower($original)) {
            flashAlert("<strong>" . escapeHtml($row['document_template_name']) . "</strong> already asks for <strong>" . escapeHtml($fill_in['name']) . "</strong>.", 'error');
            redirect();
        }
    }

    $existing = $original !== '' ? mysqli_fetch_row(mysqli_query($mysqli, "SELECT document_fill_in_id FROM document_template_fill_ins
        WHERE document_fill_in_document_template_id = $document_template_id AND document_fill_in_name = '" . escapeSql($original) . "' LIMIT 1")) : null;

    // Renamed: its [Name] in what the template says goes with it
    if ($original !== '' && $original !== $name) {
        documentTemplateRenameFillIn($document_template_id, $original, $name);
    }
    $set = "document_fill_in_name = '" . escapeSql($name) . "', document_fill_in_type = '$type', document_fill_in_options = " . ($options ? "'" . escapeSql(implode("\n", $options)) . "'" : 'NULL') . ",
        document_fill_in_required = $required, document_fill_in_help = " . ($help !== '' ? "'" . escapeSql($help) . "'" : 'NULL');
    if ($existing) {
        mysqli_query($mysqli, "UPDATE document_template_fill_ins SET $set WHERE document_fill_in_id = " . intval($existing[0]));
    } else {
        $order = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(MAX(document_fill_in_order), 0) + 1 FROM document_template_fill_ins WHERE document_fill_in_document_template_id = $document_template_id"))[0]);
        mysqli_query($mysqli, "INSERT INTO document_template_fill_ins SET document_fill_in_document_template_id = $document_template_id, document_fill_in_order = $order, $set");
    }

    logAudit("Document Template", "Edit", "$session_name " . ($original === '' ? 'added' : 'changed') . " fill-in " . escapeSql($name) . " on document template " . escapeSql($row['document_template_name']), 0, $document_template_id);

    flashAlert("Fill-in <strong>" . escapeHtml($name) . "</strong> saved" . ($original === '' ? " - put <code>[" . escapeHtml($name) . "]</code> in what it says where the answer goes" : ''));

    redirect();

}

if (isset($_GET['delete_document_template_fill_in'])) {

    // A fill-in's setup removed - gone, or, while its [Name] is still in the text, asked as plain optional text
    validateCSRFToken();

    $fill_in_id = intval($_GET['delete_document_template_fill_in']);
    $fill_in = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_fill_in_name, document_fill_in_document_template_id FROM document_template_fill_ins WHERE document_fill_in_id = $fill_in_id"));
    $row = documentTemplateForRequest($fill_in['document_fill_in_document_template_id'] ?? 0);
    $document_template_id = intval($row['document_template_id']);
    $name = (string) $fill_in['document_fill_in_name'];

    mysqli_query($mysqli, "DELETE FROM document_template_fill_ins WHERE document_fill_in_id = $fill_in_id");

    $still_asked = false;
    foreach (documentTemplateFillInsFor($document_template_id) as $other) {
        $still_asked = $still_asked || strtolower($other['name']) === strtolower($name);
    }

    logAudit("Document Template", "Edit", "$session_name removed fill-in " . escapeSql($name) . " from document template " . escapeSql($row['document_template_name']), 0, $document_template_id);

    flashAlert($still_asked
        ? "Fill-in <strong>" . escapeHtml($name) . "</strong> reset - <code>[" . escapeHtml($name) . "]</code> is still in what it says, so it is asked for as plain, optional text"
        : "Fill-in <strong>" . escapeHtml($name) . "</strong> removed", 'error');

    redirect();

}

if (isset($_POST['reorder_document_template_fill_ins'])) {

    // Dragged into order on the template's page - answers with JSON, as the page saves it in the background.
    // Every one dragged keeps its place from now on, so each gets a row.
    header('Content-Type: application/json');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        echo json_encode(['ok' => false, 'error' => 'The page is out of date - reload it']);
        exit;
    }
    $row = documentTemplateRow($_POST['document_template_id'] ?? 0);
    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'That document template no longer exists']);
        exit;
    }
    $document_template_id = intval($row['document_template_id']);
    $fill_ins = [];
    foreach (documentTemplateFillInsFor($document_template_id) as $fill_in) {
        $fill_ins[strtolower($fill_in['name'])] = $fill_in;
    }
    $order = 0;
    foreach ((array) ($_POST['names'] ?? []) as $name) {
        $fill_in = $fill_ins[strtolower((string) $name)] ?? null;
        if (!$fill_in) {
            continue;
        }
        $order++;
        if ($fill_in['id']) {
            mysqli_query($mysqli, "UPDATE document_template_fill_ins SET document_fill_in_order = $order WHERE document_fill_in_id = " . intval($fill_in['id']));
        } else {
            mysqli_query($mysqli, "INSERT INTO document_template_fill_ins SET document_fill_in_document_template_id = $document_template_id, document_fill_in_name = '" . escapeSql($fill_in['name']) . "', document_fill_in_type = 'text', document_fill_in_order = $order");
        }
    }
    logAudit("Document Template", "Edit", "$session_name reordered the fill-ins on document template " . escapeSql($row['document_template_name']), 0, $document_template_id);

    echo json_encode(['ok' => true]);
    exit;

}

if (isset($_POST['save_document_template_required'])) {

    // Who should have a document from it, and how often those documents are reviewed
    validateCSRFToken();

    $row = documentTemplateForRequest($_POST['document_template_id'] ?? 0);
    $document_template_id = intval($row['document_template_id']);
    $required = intval($_POST['required'] ?? 0);
    $tag_ids = array_map('intval', (array) ($_POST['tags'] ?? []));
    if ($required === DOCUMENT_TEMPLATE_REQUIRED_TAGGED && !array_filter($tag_ids)) {
        flashAlert('Pick the client tags - or Every client.', 'error');
        redirect();
    }
    documentTemplateSetRequired($document_template_id, $required, $required === DOCUMENT_TEMPLATE_REQUIRED_TAGGED ? $tag_ids : [], $_POST['review_months'] ?? 0);

    $saved = documentTemplateRow($document_template_id);
    $scope = documentTemplateScopeText($saved['document_template_required'], documentTemplateRequiredTags([$document_template_id])[$document_template_id]);
    $months = intval($saved['document_template_review_months']);
    $said = ($scope !== '' ? "required of " . lcfirst($scope) : 'not required of anyone') . ($months ? ", reviewed " . lcfirst(DOCUMENT_TEMPLATE_REVIEW_MONTHS[$months]) : ', with no reviews');

    logAudit("Document Template", "Edit", "$session_name set document template " . escapeSql($row['document_template_name']) . " " . escapeSql($said), 0, $document_template_id);

    flashAlert("<strong>" . escapeHtml($row['document_template_name']) . "</strong> is " . escapeHtml($said));

    redirect();

}

if (isset($_POST['make_documents_from_template'])) {

    // One for each client picked, filled in from each - the fill-ins answered once for all
    validateCSRFToken();

    $row = documentTemplateForRequest($_POST['document_template_id'] ?? 0);
    $document_template_id = intval($row['document_template_id']);
    if ($row['document_template_archived_at']) {
        flashAlert('That template is archived - restore it to make documents from it.', 'error');
        redirect();
    }
    $client_ids = array_values(array_filter(array_map('intval', (array) ($_POST['client_ids'] ?? []))));
    if (!$client_ids) {
        flashAlert('Pick the clients to make one for.', 'error');
        redirect();
    }
    $answers = ticketFillInAnswers(documentTemplateFillInsFor($document_template_id), $_POST['fill_ins'] ?? [], $fill_in_error);
    if ($answers === null) {
        flashAlert($fill_in_error, 'error');
        redirect();
    }

    $result = documentTemplateMakeForClients($document_template_id, $client_ids, $answers, !empty($_POST['skip_existing']));

    foreach ($result['made'] as $client_id => $document_id) {
        $document_name = (string) getFieldById('documents', $document_id, 'document_name');
        logAudit("Document", "Create", "$session_name created document " . escapeSql($document_name) . " from template " . escapeSql($row['document_template_name']), $client_id, $document_id);
    }

    $made = count($result['made']);
    $skipped = count($result['skipped']);
    flashAlert(($made ? "Made $made document" . ($made === 1 ? '' : 's') : 'Made no documents') . " from <strong>" . escapeHtml($row['document_template_name']) . "</strong>"
        . ($skipped ? " - skipped " . ($skipped === 1 ? escapeHtml(reset($result['skipped'])) . ", which already has one" : "$skipped clients that already have one") : ''), $made ? 'success' : 'warning');

    redirect("document_template.php?document_template_id=$document_template_id");

}

if (isset($_POST['export_document_templates']) || isset($_GET['export_document_template'])) {

    // A file of templates to import into another ITFlow - every current one, the ones picked, or one
    validateCSRFToken();

    if (isset($_GET['export_document_template'])) {
        $row = documentTemplateForRequest($_GET['export_document_template']);
        $export = documentTemplatesExport([intval($row['document_template_id'])]);
    } else {
        $export = documentTemplatesExport((array) ($_POST['document_template_ids'] ?? []), !empty($_POST['archived']));
    }
    if (!$export['templates']) {
        flashAlert('There are no document templates to export.', 'error');
        redirect();
    }

    $count = count($export['templates']);
    logAudit("Document Template", "Export", "$session_name exported $count document template" . ($count === 1 ? ' ' . escapeSql($export['templates'][0]['name']) : 's'));

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . documentTemplatesExportFileName($export) . '"');
    header('Cache-Control: no-store');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;

}

if (isset($_POST['import_document_templates'])) {

    // Templates from a file exported by ITFlow
    validateCSRFToken();

    $file = $_FILES['file'] ?? null;
    if ($file && in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        flashAlert('That file is bigger than this server takes in an upload - export a few at a time.', 'error');
        redirect('document_templates.php');
    }
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        flashAlert('Pick the file to import.', 'error');
        redirect('document_templates.php');
    }
    if ($file['size'] > 100 * 1024 * 1024) {
        flashAlert('That file is too big to be document templates.', 'error');
        redirect('document_templates.php');
    }
    $templates = documentTemplatesImportRead(file_get_contents($file['tmp_name']), $import_error);
    if ($templates === null) {
        flashAlert(escapeHtml($import_error), 'error');
        redirect('document_templates.php');
    }
    $existing = in_array($_POST['existing'] ?? '', ['skip', 'copy', 'replace'], true) ? $_POST['existing'] : 'skip';
    $counts = documentTemplatesImport($templates, $existing);

    logAudit("Document Template", "Import", "$session_name imported document templates from " . escapeSql(mb_substr((string) $file['name'], 0, 100)) . " - {$counts['added']} added, {$counts['replaced']} replaced, {$counts['skipped']} skipped");

    flashAlert(documentTemplatesImportSummary($counts), $counts['images'] ? 'warning' : ($counts['added'] || $counts['replaced'] ? 'success' : 'info'));

    redirect('document_templates.php');

}
