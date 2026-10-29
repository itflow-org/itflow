<?php

/*
 * Ticket templates to and from a file - to copy a library between ITFlow
 * installs, keep a copy, or share one. Admin > Ticket Templates exports them
 * (export_ticket_templates) and imports them (import_ticket_templates); the
 * MSP starter pack on Maintenance > Starter Content is loaded the same way.
 *
 * The file is JSON: {"format": "itflow-ticket-templates", "version": 1,
 * "exported_at", "templates": [...]}. Each template carries its name,
 * description, subject and details, category and tags (by name, with their
 * colour and icon, made on the way in when missing), priority, billing, due
 * time, tasks (with the answer each is for), the fill-ins set up on it and
 * its request form for the client portal. Not who it goes to or the clients
 * its form is for - those belong to the install it came from.
 */

const TICKET_TEMPLATE_FILE_FORMAT = 'itflow-ticket-templates';
const TICKET_TEMPLATE_FILE_VERSION = 1;

/*
 * Templates as a file's contents (an array, for json_encode()) - the ones in
 * $ids, or every current one when $ids is empty, and the archived ones too
 * with $archived.
 */
function ticketTemplatesExport(array $ids = [], $archived = false) {
    global $mysqli;
    $ids = array_values(array_filter(array_map('intval', $ids)));
    $where = $ids ? 'ticket_template_id IN (' . implode(',', $ids) . ')' : ($archived ? '1 = 1' : 'ticket_template_archived_at IS NULL');
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_templates.*, category_name, category_color, category_icon, category_description FROM ticket_templates
        LEFT JOIN categories ON category_id = ticket_template_category_id AND ticket_template_category_id > 0
        WHERE $where ORDER BY ticket_template_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[intval($row['ticket_template_id'])] = $row;
    }
    $tasks = ticketTemplateTasksFor(array_keys($rows));
    $fill_ins = ticketTemplateFillInRowsFor(array_keys($rows));
    $tags = [];
    if ($rows) {
        $sql = mysqli_query($mysqli, "SELECT ticket_template_id, tag_name, tag_color, tag_icon FROM ticket_template_tags JOIN tags ON tags.tag_id = ticket_template_tags.tag_id
            WHERE ticket_template_id IN (" . implode(',', array_keys($rows)) . ") ORDER BY tag_name");
        while ($row = mysqli_fetch_assoc($sql)) {
            $tags[intval($row['ticket_template_id'])][] = ['name' => (string) $row['tag_name'], 'color' => (string) $row['tag_color'], 'icon' => (string) $row['tag_icon']];
        }
    }

    $templates = [];
    foreach ($rows as $id => $row) {
        $has_portal = intval($row['ticket_template_portal']) || trim((string) $row['ticket_template_portal_title']) !== '' || trim((string) $row['ticket_template_portal_description']) !== '';
        $templates[] = [
            'name' => (string) $row['ticket_template_name'],
            'description' => (string) $row['ticket_template_description'],
            'subject' => (string) $row['ticket_template_subject'],
            'details' => (string) $row['ticket_template_details'],
            'category' => intval($row['ticket_template_category_id']) && $row['category_name'] !== null
                ? ['name' => (string) $row['category_name'], 'color' => (string) $row['category_color'], 'icon' => (string) $row['category_icon'], 'description' => (string) $row['category_description']] : null,
            'priority' => $row['ticket_template_priority'] ?: null,
            'billable' => $row['ticket_template_billable'] === null ? null : (bool) intval($row['ticket_template_billable']),
            'due_hours' => intval($row['ticket_template_due_hours']),
            'tags' => $tags[$id] ?? [],
            'tasks' => array_map(function ($task) {
                return ['name' => $task['name'], 'estimate' => $task['estimate'], 'when' => $task['when']];
            }, $tasks[$id] ?? []),
            'fill_ins' => array_map(function ($fill_in) {
                return ['name' => $fill_in['name'], 'type' => $fill_in['type'], 'options' => $fill_in['options'], 'required' => $fill_in['required'], 'client' => $fill_in['client'], 'help' => $fill_in['help']];
            }, $fill_ins[$id] ?? []),
            'portal' => $has_portal ? [
                'title' => (string) $row['ticket_template_portal_title'],
                'description' => (string) $row['ticket_template_portal_description'],
                'icon' => (string) $row['ticket_template_portal_icon'],
                'who' => (string) $row['ticket_template_portal_who'],
            ] : null,
        ];
    }
    return ['format' => TICKET_TEMPLATE_FILE_FORMAT, 'version' => TICKET_TEMPLATE_FILE_VERSION, 'exported_at' => date('c'), 'templates' => $templates];
}

/** A file name for an export - its one template's name, or the date. */
function ticketTemplatesExportFileName(array $export) {
    $templates = $export['templates'] ?? [];
    if (count($templates) === 1) {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($templates[0]['name'])), '-');
        return 'ticket-template-' . ($slug !== '' ? substr($slug, 0, 60) : 'export') . '.json';
    }
    return 'ticket-templates-' . date('Y-m-d') . '.json';
}

/* One line of text from a file, tidied - no markup, no runs of spaces, no longer than $max. */
function ticketTemplateFileText($value, $max) {
    return is_scalar($value) ? trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags((string) $value)), 0, $max)) : '';
}

/*
 * A file's templates, read and checked - from what export wrote, or the
 * starter pack. Each made safe: text tidied, details through the HTML
 * purifier, a priority, type, icon or audience that isn't one dropped.
 * Null with $error saying why the file can't be used.
 */
function ticketTemplatesImportRead($contents, &$error = '') {
    $data = is_array($contents) ? $contents : json_decode((string) $contents, true);
    if (!is_array($data) || ($data['format'] ?? '') !== TICKET_TEMPLATE_FILE_FORMAT || !isset($data['templates']) || !is_array($data['templates'])) {
        $error = "That isn't a ticket templates file - export one from Admin > Ticket Templates.";
        return null;
    }
    if (intval($data['version'] ?? 0) > TICKET_TEMPLATE_FILE_VERSION) {
        $error = 'That file was made by a newer version of ITFlow - update this one first.';
        return null;
    }
    if (count($data['templates']) > 500) {
        $error = 'That file holds more than 500 templates - split it up.';
        return null;
    }

    $templates = [];
    foreach ($data['templates'] as $t) {
        $name = is_array($t) ? ticketTemplateFileText($t['name'] ?? '', 200) : '';
        if ($name === '') {
            continue;
        }
        $category = is_array($t['category'] ?? null) ? ticketTemplateFileText($t['category']['name'] ?? '', 200) : '';
        $tags = [];
        foreach (array_slice(is_array($t['tags'] ?? null) ? $t['tags'] : [], 0, 20) as $tag) {
            $tag_name = is_array($tag) ? ticketTemplateFileText($tag['name'] ?? '', 200) : ticketTemplateFileText($tag, 200);
            if ($tag_name !== '') {
                $tags[] = ['name' => $tag_name, 'color' => is_array($tag) ? (string) ($tag['color'] ?? '') : '', 'icon' => is_array($tag) ? (string) ($tag['icon'] ?? '') : ''];
            }
        }
        $fill_ins = [];
        foreach (array_slice(is_array($t['fill_ins'] ?? null) ? $t['fill_ins'] : [], 0, 50) as $f) {
            $fill_in_name = is_array($f) ? ticketFillInName($f['name'] ?? '') : '';
            if ($fill_in_name === '' || isset($fill_ins[strtolower($fill_in_name)])) {
                continue;
            }
            $type = isset(TICKET_FILL_IN_TYPES[$f['type'] ?? '']) ? $f['type'] : 'text';
            $options = $type === 'list' ? ticketFillInOptions(implode("\n", array_filter(is_array($f['options'] ?? null) ? $f['options'] : [], 'is_scalar'))) : [];
            $fill_ins[strtolower($fill_in_name)] = ['name' => $fill_in_name, 'type' => count($options) < 2 && $type === 'list' ? 'text' : $type, 'options' => count($options) < 2 ? [] : $options,
                'required' => !empty($f['required']), 'client' => !array_key_exists('client', $f) || !empty($f['client']), 'help' => ticketTemplateFileText($f['help'] ?? '', 255)];
        }
        $tasks = [];
        foreach (array_slice(is_array($t['tasks'] ?? null) ? $t['tasks'] : [], 0, 100) as $task) {
            $task_name = is_array($task) ? ticketTemplateFileText($task['name'] ?? '', 200) : '';
            if ($task_name === '') {
                continue;
            }
            $when = is_array($task['when'] ?? null) ? ['fill_in' => ticketFillInName($task['when']['fill_in'] ?? ''), 'value' => ticketTemplateFileText($task['when']['value'] ?? '', 255)] : null;
            $tasks[] = ['name' => $task_name, 'estimate' => max(0, min(100000, intval($task['estimate'] ?? 0))), 'when' => $when && $when['fill_in'] !== '' && $when['value'] !== '' ? $when : null];
        }
        $portal = is_array($t['portal'] ?? null) ? [
            'title' => ticketTemplateFileText($t['portal']['title'] ?? '', 200),
            'description' => ticketTemplateFileText($t['portal']['description'] ?? '', 500),
            'icon' => isset(TICKET_TEMPLATE_PORTAL_ICONS[$t['portal']['icon'] ?? '']) ? $t['portal']['icon'] : '',
            'who' => isset(TICKET_TEMPLATE_PORTAL_WHO[$t['portal']['who'] ?? '']) ? $t['portal']['who'] : 'everyone',
        ] : null;
        $billable = $t['billable'] ?? null;
        $templates[] = [
            'name' => $name,
            'description' => ticketTemplateFileText($t['description'] ?? '', 500),
            'subject' => ticketTemplateFileText($t['subject'] ?? '', 500),
            'details' => is_scalar($t['details'] ?? null) && trim((string) $t['details']) !== '' ? documentHtmlPurify((string) $t['details']) : '',
            'category' => $category !== '' ? ['name' => $category, 'color' => (string) ($t['category']['color'] ?? ''), 'icon' => (string) ($t['category']['icon'] ?? ''), 'description' => (string) ($t['category']['description'] ?? '')] : null,
            'priority' => in_array($t['priority'] ?? '', TICKET_PRIORITIES, true) ? $t['priority'] : null,
            'billable' => $billable === null || $billable === '' ? null : (bool) $billable,
            'due_hours' => max(0, min(24 * 366, intval($t['due_hours'] ?? 0))),
            'tags' => $tags,
            'tasks' => $tasks,
            'fill_ins' => array_values($fill_ins),
            'portal' => $portal,
        ];
    }
    if (!$templates) {
        $error = 'There are no templates in that file.';
        return null;
    }
    return $templates;
}

/* A file's ticket category by name - this install's of that name, or one made from it. 0 when there is only an archived one. */
function ticketTemplateImportCategory(array $category, array &$counts) {
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

/* A file's ticket tag by name - this install's of that name, or one made from it. 0 when there is only an archived one. */
function ticketTemplateImportTag(array $tag, array &$counts) {
    global $mysqli;
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tag_id, tag_archived_at FROM tags WHERE tag_type = 12 AND LOWER(tag_name) = LOWER('" . escapeSql($tag['name']) . "')
        ORDER BY tag_archived_at IS NOT NULL LIMIT 1"));
    if ($row) {
        return $row['tag_archived_at'] ? 0 : intval($row['tag_id']);
    }
    $counts['tags']++;
    return tagCreate(['name' => $tag['name'], 'description' => '', 'type' => 12, 'color' => tagColor($tag['color']) ?: tagNextColor(12), 'icon' => tagIcon($tag['icon']) ?: 'tag']);
}

/*
 * Bring in templates read by ticketTemplatesImportRead(). One whose name is
 * already here is skipped ($existing 'skip'), added under a name of its own
 * ('copy') or has everything in the file put in its place ('replace') - its
 * tickets, recurring tickets and project templates stay linked, and who it
 * goes to, whether its request form is on and the clients it is for stay as
 * they were. $only_existing: bring in only the ones already here (to update
 * them). A request form comes in turned off - who sees it is this install's
 * call. ['added', 'replaced', 'skipped', 'categories', 'tags' made, 'forms'
 * brought in turned off, 'ids' of the templates added or replaced].
 */
function ticketTemplatesImport(array $templates, $existing = 'skip', $only_existing = false) {
    global $mysqli;
    $counts = ['added' => 0, 'replaced' => 0, 'skipped' => 0, 'categories' => 0, 'tags' => 0, 'forms' => 0, 'ids' => []];
    $here = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_template_id, ticket_template_name FROM ticket_templates");
    while ($row = mysqli_fetch_assoc($sql)) {
        $here[mb_strtolower($row['ticket_template_name'])] = intval($row['ticket_template_id']);
    }

    foreach ($templates as $t) {
        $key = mb_strtolower($t['name']);
        $id = $here[$key] ?? 0;
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
        $current = $id ? ticketTemplateRow($id) : null;

        $tag_ids = [];
        foreach ($t['tags'] as $tag) {
            $tag_id = ticketTemplateImportTag($tag, $counts);
            if ($tag_id) {
                $tag_ids[] = (string) $tag_id;
            }
        }
        $portal = $t['portal'];
        $saved = ticketTemplateSave($id, [
            'name' => $name, 'description' => $t['description'], 'subject' => $t['subject'], 'details' => $t['details'],
            'category_id' => $t['category'] ? ticketTemplateImportCategory($t['category'], $counts) : 0,
            'priority' => $t['priority'], 'billable' => $t['billable'] === null ? null : ($t['billable'] ? 1 : 0),
            'assigned_to' => $current ? intval($current['ticket_template_assigned_to']) : 0,
            'due_hours' => $t['due_hours'], 'tags' => $tag_ids,
            'portal' => $current ? intval($current['ticket_template_portal']) : 0,
            'portal_title' => $portal['title'] ?? '', 'portal_description' => $portal['description'] ?? '',
            'portal_icon' => $portal['icon'] ?? '', 'portal_who' => $portal['who'] ?? 'everyone',
            'portal_all_clients' => $current ? intval($current['ticket_template_portal_all_clients']) : 1,
        ]);
        $here[mb_strtolower($name)] = $saved;

        // Its tasks and fill-ins are the file's
        mysqli_query($mysqli, "DELETE FROM task_templates WHERE task_template_ticket_template_id = $saved");
        foreach (array_values($t['tasks']) as $order => $task) {
            mysqli_query($mysqli, "INSERT INTO task_templates SET task_template_ticket_template_id = $saved, task_template_name = '" . escapeSql($task['name']) . "',
                task_template_order = " . ($order + 1) . ", task_template_completion_estimate = " . intval($task['estimate']) . ",
                task_template_when_fill_in = " . ($task['when'] ? "'" . escapeSql($task['when']['fill_in']) . "'" : 'NULL') . ",
                task_template_when_value = " . ($task['when'] ? "'" . escapeSql($task['when']['value']) . "'" : 'NULL'));
        }
        mysqli_query($mysqli, "DELETE FROM ticket_template_fill_ins WHERE fill_in_ticket_template_id = $saved");
        foreach (array_values($t['fill_ins']) as $order => $f) {
            mysqli_query($mysqli, "INSERT INTO ticket_template_fill_ins SET fill_in_ticket_template_id = $saved, fill_in_name = '" . escapeSql($f['name']) . "',
                fill_in_type = '" . escapeSql($f['type']) . "', fill_in_options = " . ($f['options'] ? "'" . escapeSql(implode("\n", $f['options'])) . "'" : 'NULL') . ",
                fill_in_required = " . ($f['required'] ? 1 : 0) . ", fill_in_client = " . ($f['client'] ? 1 : 0) . ",
                fill_in_help = " . ($f['help'] !== '' ? "'" . escapeSql($f['help']) . "'" : 'NULL') . ", fill_in_order = " . ($order + 1));
        }

        $counts[$current ? 'replaced' : 'added']++;
        $counts['ids'][] = $saved;
        if ($portal && !($current && intval($current['ticket_template_portal']))) {
            $counts['forms']++;
        }
    }
    return $counts;
}

/* What an import did, in words - for its message. HTML. */
function ticketTemplatesImportSummary(array $counts) {
    $parts = [];
    foreach (['added' => 'added', 'replaced' => 'replaced', 'skipped' => 'already here, left as they were'] as $key => $word) {
        if ($counts[$key]) {
            $parts[] = '<strong>' . $counts[$key] . '</strong> ' . $word;
        }
    }
    $made = [];
    if ($counts['categories']) {
        $made[] = $counts['categories'] . ' categor' . ($counts['categories'] === 1 ? 'y' : 'ies');
    }
    if ($counts['tags']) {
        $made[] = $counts['tags'] . ' tag' . ($counts['tags'] === 1 ? '' : 's');
    }
    return ucfirst(implode(', ', $parts) ?: 'nothing brought in')
        . ($made ? '. Made ' . implode(' and ', $made) . ' they use' : '')
        . ($counts['forms'] ? '. <strong>' . $counts['forms'] . '</strong> come with a request form for the client portal - turned off until you turn it on in their Client portal tab' : '')
        . '.';
}
