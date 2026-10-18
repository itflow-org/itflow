<?php

/*
 * Document templates to and from a file - to copy a library between ITFlow
 * installs, keep a copy, or share one. Admin > Document Templates exports them
 * (export_document_templates) and imports them (import_document_templates);
 * the MSP starter pack on Maintenance > Starter Content is loaded the same way.
 *
 * The file is JSON: {"format": "itflow-document-templates", "version": 1,
 * "exported_at", "templates": [...]}. Each template carries its name, summary,
 * what it says - its images in it, as data - the fill-ins set up on it, and
 * how often the documents made from it are reviewed. Not who should have one:
 * that is the call of the install it goes into - a new one comes in required
 * of no one, and one replaced keeps its own.
 */

const DOCUMENT_TEMPLATE_FILE_FORMAT = 'itflow-document-templates';
const DOCUMENT_TEMPLATE_FILE_VERSION = 1;

/** The largest image that goes into an export, in bytes - bigger ones stay behind. */
const DOCUMENT_TEMPLATE_TRANSFER_IMAGE_MAX = 5 * 1024 * 1024;

/* A template's HTML with its own images in it as data - for a file. How many were too big or gone, in $left_out. */
function documentTemplateInlineImages($document_template_id, $html, &$left_out = 0) {
    $document_template_id = intval($document_template_id);
    $dir = documentTemplateDir($document_template_id);
    return preg_replace_callback('/(<img\b[^>]*?\bsrc=)(["\'])\/?uploads\/document_templates\/' . $document_template_id . '\/([^"\'\/]+)\2/i', function ($match) use ($dir, &$left_out) {
        $path = $dir . '/' . basename($match[3]);
        $info = is_file($path) && filesize($path) <= DOCUMENT_TEMPLATE_TRANSFER_IMAGE_MAX ? @getimagesize($path) : false;
        if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            $left_out++;
            return $match[0];
        }
        return $match[1] . $match[2] . 'data:' . $info['mime'] . ';base64,' . base64_encode((string) file_get_contents($path)) . $match[2];
    }, (string) $html);
}

/*
 * Templates as a file's contents (an array, for json_encode()) - the ones in
 * $ids, or every current one when $ids is empty, and the archived ones too
 * with $archived.
 */
function documentTemplatesExport(array $ids = [], $archived = false) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $ids)));
    $where = $ids ? 'document_template_id IN (' . implode(',', $ids) . ')' : ($archived ? '1 = 1' : 'document_template_archived_at IS NULL');
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT document_template_id, document_template_name, document_template_description, document_template_content, document_template_review_months
        FROM document_templates WHERE $where ORDER BY document_template_name ASC, document_template_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['document_template_id'])] = $row;
    }
    $fill_ins = documentTemplateFillInRowsFor(array_keys($rows));

    $templates = [];
    foreach ($rows as $id => $row) {
        $templates[] = [
            'name' => (string) $row['document_template_name'],
            'description' => (string) $row['document_template_description'],
            'content' => documentTemplateInlineImages($id, $row['document_template_content']),
            'fill_ins' => array_map(function ($fill_in) {
                return ['name' => $fill_in['name'], 'type' => $fill_in['type'], 'options' => $fill_in['options'], 'required' => $fill_in['required'], 'help' => $fill_in['help']];
            }, $fill_ins[$id] ?? []),
            'review_months' => intval($row['document_template_review_months']),
        ];
    }
    return ['format' => DOCUMENT_TEMPLATE_FILE_FORMAT, 'version' => DOCUMENT_TEMPLATE_FILE_VERSION, 'exported_at' => date('c'), 'templates' => $templates];
}

/** A file name for an export - its one template's name, or the date. */
function documentTemplatesExportFileName(array $export) {
    $templates = $export['templates'] ?? [];
    if (count($templates) === 1) {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($templates[0]['name'])), '-');
        return 'document-template-' . ($slug !== '' ? substr($slug, 0, 60) : 'export') . '.json';
    }
    return 'document-templates-' . date('Y-m-d') . '.json';
}

/* One line of text from a file, tidied - no markup, no runs of spaces, no longer than $max. */
function documentTemplateFileText($value, $max) {
    return is_scalar($value) ? trim(mb_substr(preg_replace('/\s+/u', ' ', strip_tags((string) $value)), 0, $max)) : '';
}

/*
 * A file's templates, read and checked - from what export wrote, or the
 * starter pack. Each made safe: text tidied, what it says through the HTML
 * purifier (images in it as data kept when they are images; ones pointing at
 * another install's uploads taken out, counted in 'images_dropped'), fill-ins
 * with a name that can be one, a type that is one and a list with two
 * choices (else plain text), each once, and a review period that is one.
 * Null with $error saying why the file can't be used.
 */
function documentTemplatesImportRead($contents, &$error = '') {
    $data = is_array($contents) ? $contents : json_decode((string) $contents, true);
    if (!is_array($data) || ($data['format'] ?? '') !== DOCUMENT_TEMPLATE_FILE_FORMAT || !isset($data['templates']) || !is_array($data['templates'])) {
        $error = "That isn't a document templates file - export one from Admin > Document Templates.";
        return null;
    }
    if (intval($data['version'] ?? 0) > DOCUMENT_TEMPLATE_FILE_VERSION) {
        $error = 'That file was made by a newer version of ITFlow - update this one first.';
        return null;
    }
    if (count($data['templates']) > 500) {
        $error = 'That file holds more than 500 document templates - split it up.';
        return null;
    }

    $templates = [];
    foreach ($data['templates'] as $t) {
        $name = is_array($t) ? documentTemplateFileText($t['name'] ?? '', 200) : '';
        if ($name === '') {
            continue;
        }
        // An image carried as data with no alt would get one made of its data
        $content = is_scalar($t['content'] ?? null) ? preg_replace('/<img\b(?![^>]*\balt=)(?=[^>]*\bsrc=["\']data:)/i', '<img alt=""', (string) $t['content']) : '';
        $content = trim($content) !== '' ? documentHtmlPurify($content) : '';
        // An image still pointing at the uploads of the install it came from is no image here
        $dropped = 0;
        $content = preg_replace('/<img\b[^>]*?\bsrc=(["\'])\/?uploads\/[^"\']*\1[^>]*>/i', '', $content, -1, $dropped);

        $fill_ins = [];
        foreach (array_slice(is_array($t['fill_ins'] ?? null) ? $t['fill_ins'] : [], 0, 100) as $f) {
            $fill_in_name = is_array($f) ? documentFillInName(documentTemplateFileText($f['name'] ?? '', 50)) : '';
            if ($fill_in_name === '' || isset($fill_ins[strtolower($fill_in_name)])) {
                continue;
            }
            $type = is_string($f['type'] ?? null) && isset(TICKET_FILL_IN_TYPES[$f['type']]) ? $f['type'] : 'text';
            $options = $type === 'list' ? ticketFillInOptions(is_array($f['options'] ?? null) ? implode("\n", array_filter($f['options'], 'is_scalar')) : (string) ($f['options'] ?? '')) : [];
            if ($type === 'list' && count($options) < 2) {
                $type = 'text';
                $options = [];
            }
            $fill_ins[strtolower($fill_in_name)] = ['name' => $fill_in_name, 'type' => $type, 'options' => $options, 'required' => !empty($f['required']),
                'help' => documentTemplateFileText($f['help'] ?? '', 255)];
        }

        $review_months = intval($t['review_months'] ?? 0);
        $templates[] = [
            'name' => $name,
            'description' => documentTemplateFileText($t['description'] ?? '', 500),
            'content' => $content,
            'fill_ins' => array_values($fill_ins),
            'review_months' => isset(DOCUMENT_TEMPLATE_REVIEW_MONTHS[$review_months]) ? $review_months : 0,
            'images_dropped' => $dropped,
        ];
    }
    if (!$templates) {
        $error = 'There are no document templates in that file.';
        return null;
    }
    return $templates;
}

/*
 * Bring in templates read by documentTemplatesImportRead(). One whose name is
 * already here is skipped ($existing 'skip'), added under a name of its own
 * ('copy') or has everything in the file put in its place ('replace') - its
 * name, summary, what it says and its images, its fill-ins and how often its
 * documents are reviewed. Its documents stay made from it, and who should
 * have one stays as it was; a new one is required of no one. $only_existing:
 * bring in only the ones already here (to update them). ['added', 'replaced',
 * 'skipped', 'images' left out, 'ids' of those added or replaced].
 */
function documentTemplatesImport(array $templates, $existing = 'skip', $only_existing = false) {
    global $mysqli;
    $counts = ['added' => 0, 'replaced' => 0, 'skipped' => 0, 'images' => 0, 'ids' => []];
    $here = [];
    $sql = mysqli_query($mysqli, "SELECT document_template_id, document_template_name FROM document_templates ORDER BY document_template_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $here[mb_strtolower($row['document_template_name'])] = $here[mb_strtolower($row['document_template_name'])] ?? intval($row['document_template_id']);
    }

    foreach ($templates as $t) {
        $id = $here[mb_strtolower($t['name'])] ?? 0;
        if (($only_existing && !$id) || ($id && $existing === 'skip')) {
            $counts['skipped']++;
            continue;
        }
        $name = $t['name'];
        if ($id && $existing === 'copy') {
            for ($n = 2; isset($here[mb_strtolower($name)]); $n++) {
                $name = mb_substr($t['name'], 0, 190) . " ($n)";
            }
            $id = 0;
        }
        $replacing = $id > 0;

        // What it says, its images kept in its own folder
        $saved = documentTemplateSave($id, ['name' => $name, 'description' => $t['description'], 'content' => $t['content']]);
        $here[mb_strtolower($name)] = $saved;

        // Its fill-ins are the file's
        mysqli_query($mysqli, "DELETE FROM document_template_fill_ins WHERE document_fill_in_document_template_id = $saved");
        foreach (array_values($t['fill_ins']) as $order => $f) {
            mysqli_query($mysqli, "INSERT INTO document_template_fill_ins SET document_fill_in_document_template_id = $saved, document_fill_in_name = '" . escapeSql($f['name']) . "',
                document_fill_in_type = '" . escapeSql($f['type']) . "', document_fill_in_options = " . ($f['options'] ? "'" . escapeSql(implode("\n", $f['options'])) . "'" : 'NULL') . ",
                document_fill_in_required = " . ($f['required'] ? 1 : 0) . ", document_fill_in_help = " . ($f['help'] !== '' ? "'" . escapeSql($f['help']) . "'" : 'NULL') . ",
                document_fill_in_order = " . ($order + 1));
        }
        mysqli_query($mysqli, "UPDATE document_templates SET document_template_review_months = " . intval($t['review_months']) . ", document_template_updated_at = document_template_updated_at
            WHERE document_template_id = $saved");

        $counts['images'] += intval($t['images_dropped']);
        $counts[$replacing ? 'replaced' : 'added']++;
        $counts['ids'][] = $saved;
    }
    return $counts;
}

/* What an import did, in words - for its message. HTML. */
function documentTemplatesImportSummary(array $counts) {
    $parts = [];
    foreach (['added' => 'added', 'replaced' => 'replaced', 'skipped' => 'already here, left as they were'] as $key => $word) {
        if ($counts[$key]) {
            $parts[] = '<strong>' . $counts[$key] . '</strong> ' . $word;
        }
    }
    return ucfirst(implode(', ', $parts) ?: 'nothing brought in')
        . ($counts['images'] ? '. Left out <strong>' . $counts['images'] . '</strong> image' . ($counts['images'] === 1 ? '' : 's') . " the file only pointed at, without carrying - too big, or gone where it came from" : '')
        . '.';
}
