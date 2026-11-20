<?php

/*
 * ITFlow - Undo: the message after an archive, a bulk change or a move offers to put it back,
 * so the change doesn't need an "Are you sure?" first. includes/inc_alert_feedback.php draws the
 * Undo button on the message; agent/post/undo.php puts it back.
 *
 * What putting it back takes is kept in the session under a random token, for UNDO_SECONDS -
 * for the agent who made the change, once, and while they still have the permission the change
 * needed. Each step puts back what the change did and nothing that happened since: a row changed
 * again in the meantime, or already restored, is left as it is and counted as not put back.
 *
 * The steps:
 *   ['restore', kind, id]                                   archivedRestore() (functions/archived.php)
 *   ['set', table, id column, id, before, after, client id] the columns back to before - if they are still after
 *   ['rows', table, rows]                                   rows the change deleted, back (a document's links)
 *   ['recurring_on', recurring invoice id]                  a recurring invoice the change stopped, running again
 *   ['folder', folder id, parent before, parent after, client id]  a folder back under its parent
 *   ['rack', unit id, start, end, face, start after, face after]   a rack item back where it was, if that's free
 *   ['ticket_priority', ticket id, before, after]           through ticketChangePriority(), as the picker
 *   ['ticket_category', ticket id, before, after]           with the ticket's history line, as the bulk change
 *   ['ticket_project', ticket id, before, after]            the same
 *   ['asset_status', asset id, before, after]               with the asset's history line
 *   ['also', step]                                          a step that goes with the others, not counted
 */

const UNDO_SECONDS = 600;
const UNDO_KEEP = 5;
// Tables where putting a row back isn't an edit - its updated time stays as it was
const UNDO_KEEP_UPDATED = [
    'documents' => 'document_updated_at',
    'software_templates' => 'software_template_updated_at',
    'vendor_templates' => 'vendor_template_updated_at',
];

/*
 * Offer to undo what was just done - on the message flashAlert() set, so call it after that.
 * $done is the message once it is undone (escapeHtml() any names in it): in it {n} is how many
 * steps were put back, {s} the plural ending ($plural) when that isn't one, and {are} is or are.
 * A $plural of 'y|ies' gives both endings ("categor{s}").
 * $module and $level are the permission the change needed. The token, or '' when there is nothing to put back.
 */
function undoOffer(string $done, array $steps, string $module, int $level = 2, string $plural = 's'): string {
    global $session_user_id;
    if (!$steps || empty($session_user_id)) {
        return '';
    }
    $token = bin2hex(random_bytes(12));
    $offers = array_filter((array) ($_SESSION['undo'] ?? []), function ($offer) {
        return is_array($offer) && time() - intval($offer['at'] ?? 0) < UNDO_SECONDS;
    });
    $offers[$token] = [
        'done' => $done,
        'plural' => $plural,
        'steps' => array_values($steps),
        'module' => $module,
        'level' => $level,
        'user' => intval($session_user_id),
        'at' => time(),
    ];
    $_SESSION['undo'] = array_slice($offers, -UNDO_KEEP, null, true);
    $_SESSION['alert_undo'] = $token;
    return $token;
}

/** The offer the next message carries - its token, or '' (includes/inc_alert_feedback.php). */
function undoTokenForAlert(): string {
    $token = (string) ($_SESSION['alert_undo'] ?? '');
    $offer = $_SESSION['undo'][$token] ?? null;
    return $offer && time() - intval($offer['at']) < UNDO_SECONDS ? $token : '';
}

/*
 * These rows' columns as they are now, before a change - for undoChangedSteps() after it. $ids
 * as posted (made whole numbers here); $client_column says whose each row is, '' for none.
 */
function undoSnapshot(string $table, string $id_column, $ids, array $columns, string $client_column = ''): array {
    global $mysqli;
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    $snapshot = ['table' => $table, 'id' => $id_column, 'columns' => $columns, 'client' => $client_column, 'rows' => []];
    if (!$ids) {
        return $snapshot;
    }
    $select = implode(', ', array_merge([$id_column], $columns, $client_column !== '' ? [$client_column] : []));
    $sql = mysqli_query($mysqli, "SELECT $select FROM $table WHERE $id_column IN (" . implode(',', $ids) . ")");
    while ($row = mysqli_fetch_assoc($sql)) {
        $snapshot['rows'][intval($row[$id_column])] = $row;
    }
    return $snapshot;
}

/** After the change: a 'set' step for each row of the snapshot that it changed. */
function undoChangedSteps(array $snapshot): array {
    global $mysqli;
    if (!$snapshot['rows']) {
        return [];
    }
    $after = undoSnapshot($snapshot['table'], $snapshot['id'], array_keys($snapshot['rows']), $snapshot['columns'], $snapshot['client'])['rows'];
    $steps = [];
    foreach ($snapshot['rows'] as $id => $row) {
        if (!isset($after[$id])) {
            continue;
        }
        $before = [];
        $now = [];
        foreach ($snapshot['columns'] as $column) {
            if ($row[$column] !== $after[$id][$column]) {
                $before[$column] = $row[$column];
                $now[$column] = $after[$id][$column];
            }
        }
        if ($before) {
            $client_id = $snapshot['client'] !== '' ? intval($row[$snapshot['client']]) : 0;
            $steps[] = ['set', $snapshot['table'], $snapshot['id'], $id, $before, $now, $client_id];
        }
    }
    return $steps;
}

/*
 * undoChangedSteps() and undoOffer() in one - for a bulk change of columns and nothing else.
 * $as runs each row back through a step of its own (undoStepsAs()) rather than a plain 'set'.
 */
function undoOfferChanges(array $snapshot, string $done, string $module, int $level = 2, string $plural = 's', string $as = ''): string {
    $steps = undoChangedSteps($snapshot);
    if ($as !== '') {
        $steps = undoStepsAs($as, $steps);
    }
    return undoOffer($done, $steps, $module, $level, $plural);
}

/** 'set' steps of one column as steps of $type - [type, id, before, after] - for a change that goes back through its own function. */
function undoStepsAs(string $type, array $steps): array {
    return array_map(function ($step) use ($type) {
        $column = array_key_first($step[4]);
        return [$type, $step[3], $step[4][$column], $step[5][$column]];
    }, $steps);
}

/*
 * Before archiving $ids of a kind on Archived (functions/archived.php): which of them aren't
 * archived yet, and what the archive also takes away - a contact's roles, a document's links,
 * a client's running recurring invoices. Hand it to undoOfferArchive() after.
 */
function undoArchiveBefore(string $kind_key, $ids): array {
    global $mysqli;
    $kind = archivedKinds()[$kind_key] ?? null;
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
    $before = ['kind' => $kind_key, 'ids' => [], 'snapshot' => null, 'rows' => [], 'recurring' => []];
    if (!$kind || !$ids) {
        return $before;
    }
    $sql = mysqli_query($mysqli, "SELECT {$kind['id']} FROM {$kind['table']} WHERE {$kind['id']} IN (" . implode(',', $ids) . ") AND {$kind['archived']} IS NULL");
    $ids = array_map('intval', array_column(mysqli_fetch_all($sql), 0));
    $before['ids'] = $ids;
    if (!$ids) {
        return $before;
    }
    if ($kind_key === 'contact') {
        $before['snapshot'] = undoSnapshot('contacts', 'contact_id', $ids, ['contact_important', 'contact_billing', 'contact_technical'], 'contact_client_id');
    }
    if ($kind_key === 'document') {
        foreach (LINK_RELATIONS as $pair => $relation) {
            if (in_array('document', explode('|', $pair), true)) {
                $sql = mysqli_query($mysqli, "SELECT * FROM {$relation['table']} WHERE document_id IN (" . implode(',', $ids) . ")");
                $rows = mysqli_fetch_all($sql, MYSQLI_ASSOC);
                if ($rows) {
                    $before['rows'][$relation['table']] = $rows;
                }
            }
        }
    }
    if ($kind_key === 'client') {
        $sql = mysqli_query($mysqli, "SELECT recurring_invoice_id FROM recurring_invoices WHERE recurring_invoice_client_id IN (" . implode(',', $ids) . ") AND recurring_invoice_status = 1");
        $before['recurring'] = array_map('intval', array_column(mysqli_fetch_all($sql), 0));
    }
    return $before;
}

/*
 * After archiving: offer back what undoArchiveBefore() saw, as far as it is archived now. One
 * offer for any number of kinds (the files list archives files and documents together). The
 * message after: "Name is back", or "3 assets are back".
 */
function undoOfferArchive(array ...$befores): string {
    global $mysqli;
    $kinds = archivedKinds();
    $steps = [];
    $names = [];
    $labels = [];
    $module = '';
    foreach ($befores as $before) {
        $kind = $kinds[$before['kind']] ?? null;
        if (!$kind || !$before['ids']) {
            continue;
        }
        $sql = mysqli_query($mysqli, "SELECT {$kind['id']} AS id, {$kind['name']} AS name FROM {$kind['table']} WHERE {$kind['id']} IN (" . implode(',', $before['ids']) . ") AND {$kind['archived']} IS NOT NULL");
        $archived = [];
        while ($row = mysqli_fetch_assoc($sql)) {
            $archived[intval($row['id'])] = $row['name'];
        }
        if (!$archived) {
            continue;
        }
        foreach (array_keys($archived) as $id) {
            $steps[] = ['restore', $before['kind'], $id];
        }
        if ($before['snapshot']) {
            $before['snapshot']['rows'] = array_intersect_key($before['snapshot']['rows'], $archived);
            $steps = array_merge($steps, undoChangedSteps($before['snapshot']));
        }
        foreach ($before['rows'] as $table => $rows) {
            $rows = array_values(array_filter($rows, function ($row) use ($archived) {
                return isset($archived[intval($row['document_id'])]);
            }));
            if ($rows) {
                $steps[] = ['rows', $table, $rows];
            }
        }
        foreach ($before['recurring'] as $recurring_invoice_id) {
            $steps[] = ['recurring_on', $recurring_invoice_id];
        }
        $names = array_merge($names, array_values($archived));
        $labels[] = strtolower($kind['label']);
        $module = $module ?: $kind['module'];
    }
    if (!$names) {
        return '';
    }
    $done = count($names) === 1
        ? '<strong>' . escapeHtml($names[0]) . '</strong> is back'
        : '<strong>{n}</strong> ' . (count(array_unique($labels)) === 1 ? $labels[0] : 'item') . '{s} {are} back';
    return undoOffer($done, $steps, $module);
}

/*
 * Archiving something that isn't on Archived - a tag, a category, a template, a role, a payment
 * method, and the like on the admin pages: which of $ids ($id_column is the table's X_id, archived
 * in X_archived_at) aren't archived yet, before. Hand it to undoOfferArchived() after.
 * $client_column, for one that can be a client's, has Undo check the agent can still get to it.
 */
function undoArchivedBefore(string $table, string $id_column, $ids, string $client_column = ''): array {
    $column = substr($id_column, 0, -3) . '_archived_at';
    $snapshot = undoSnapshot($table, $id_column, $ids, [$column], $client_column);
    $snapshot['rows'] = array_filter($snapshot['rows'], function ($row) use ($column) {
        return $row[$column] === null;
    });
    return $snapshot;
}

/*
 * After archiving: Undo for what undoArchivedBefore() saw that is archived now - "Name is back"
 * with $name, or "3 tags are back" ($label 'tag'; 'categor' with $plural 'y|ies'). $also are
 * snapshots of what else the archive changed (the default it stopped being), put back with it.
 * Admins only unless $module says who else.
 */
function undoOfferArchived(array $snapshot, string $label, string $name = '', array $also = [], string $plural = 's', string $module = 'admin', int $level = 3): string {
    $steps = undoChangedSteps($snapshot);
    if (!$steps) {
        return '';
    }
    foreach ($also as $more) {
        foreach (undoChangedSteps($more) as $step) {
            $steps[] = ['also', $step];
        }
    }
    $done = $name !== '' && count($snapshot['rows']) === 1
        ? '<strong>' . escapeHtml($name) . '</strong> is back'
        : '<strong>{n}</strong> ' . $label . '{s} {are} back';
    return undoOffer($done, $steps, $module, $level, $plural);
}

/** A value as SQL - NULL stays NULL. Values come from the database, never from the request. */
function undoSqlValue($value): string {
    global $mysqli;
    return $value === null ? 'NULL' : "'" . mysqli_real_escape_string($mysqli, (string) $value) . "'";
}

/*
 * Put an offer back. [ok, message] - ok false when there was nothing it could do: gone, too late,
 * someone else's, or the permission is no longer there.
 */
function undoApply(string $token): array {
    global $mysqli, $session_user_id, $session_name;

    $offer = $_SESSION['undo'][$token] ?? null;
    unset($_SESSION['undo'][$token]);
    if (!is_array($offer) || intval($offer['user']) !== intval($session_user_id)) {
        return [false, 'That can no longer be undone.'];
    }
    if (time() - intval($offer['at']) >= UNDO_SECONDS) {
        return [false, 'That can no longer be undone - Undo works for ' . intval(UNDO_SECONDS / 60) . ' minutes.'];
    }
    if (lookupUserPermission($offer['module']) < intval($offer['level'])) {
        return [false, 'You no longer have the permission to undo that.'];
    }

    $done = 0;
    $missed = 0;
    foreach ($offer['steps'] as $step) {
        $ok = undoStep($step);
        if ($ok === null) {
            continue; // a step that goes with another, not counted on its own
        }
        $ok ? $done++ : $missed++;
    }

    if (!$done && $missed) {
        return [false, 'Nothing was put back - it has changed since.'];
    }
    $plural = (string) ($offer['plural'] ?? 's');
    [$one, $many] = strpos($plural, '|') !== false ? explode('|', $plural, 2) : ['', $plural];
    $message = str_replace(['{n}', '{s}', '{are}'], [$done, $done === 1 ? $one : $many, $done === 1 ? 'is' : 'are'], $offer['done']);
    logAudit('Undo', 'Undo', "$session_name undid a change: " . html_entity_decode(strip_tags($message), ENT_QUOTES));
    return [true, 'Undone - ' . $message . ($missed ? " - $missed had changed since, and " . ($missed === 1 ? 'was left as it is' : 'were left as they are') : '')];
}

/** One step: true put back, false not (changed since, or not theirs), null not counted. */
function undoStep(array $step) {
    global $mysqli, $session_name;

    switch ($step[0]) {
        case 'restore':
            return archivedRestore($step[1], $step[2]) !== null;

        case 'set':
            [, $table, $id_column, $id, $before, $after, $client_id] = $step;
            if ($client_id && !hasClientAccess($client_id)) {
                return false;
            }
            $sets = [];
            $still = [];
            foreach ($before as $column => $value) {
                $sets[] = "$column = " . undoSqlValue($value);
                $still[] = "$column <=> " . undoSqlValue($after[$column]);
            }
            if (isset(UNDO_KEEP_UPDATED[$table])) {
                $sets[] = UNDO_KEEP_UPDATED[$table] . ' = ' . UNDO_KEEP_UPDATED[$table]; // a move or a restore isn't an edit
            }
            mysqli_query($mysqli, "UPDATE $table SET " . implode(', ', $sets) . " WHERE $id_column = " . intval($id) . " AND " . implode(' AND ', $still));
            return mysqli_affected_rows($mysqli) > 0;

        case 'also':
            undoStep($step[1]);
            return null;

        case 'rows':
            [, $table, $rows] = $step;
            foreach ($rows as $row) {
                $values = array_map('undoSqlValue', array_values($row));
                mysqli_query($mysqli, "INSERT IGNORE INTO $table (" . implode(', ', array_keys($row)) . ") VALUES (" . implode(', ', $values) . ")");
            }
            return null;

        case 'recurring_on':
            $recurring_invoice_id = intval($step[1]);
            // Only once its client is back
            mysqli_query($mysqli, "UPDATE recurring_invoices JOIN clients ON client_id = recurring_invoice_client_id SET recurring_invoice_status = 1
                WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_status = 0 AND client_archived_at IS NULL");
            if (mysqli_affected_rows($mysqli) > 0) {
                mysqli_query($mysqli, "INSERT INTO history SET history_status = 'Active', history_description = 'Recurring Invoice active again - its client is back', history_recurring_invoice_id = $recurring_invoice_id");
            }
            return null;

        case 'folder':
            [, $folder_id, $parent, $parent_after, $client_id] = $step;
            if (!hasClientAccess($client_id)) {
                return false;
            }
            $tree = folderTree($client_id);
            if (!isset($tree[$folder_id]) || intval($tree[$folder_id]['parent_folder']) !== intval($parent_after)
                || folderOwnedId($parent, $client_id) !== intval($parent) || in_array(intval($parent), folderSubtree($tree, $folder_id), true)) {
                return false;
            }
            mysqli_query($mysqli, "UPDATE folders SET parent_folder = " . intval($parent) . " WHERE folder_id = " . intval($folder_id));
            logAudit("Folder", "Move", "$session_name moved folder " . escapeSql($tree[$folder_id]['folder_name'] ?? '') . " back into " . escapeSql(folderDisplayName($parent)), $client_id, $folder_id);
            return true;

        case 'rack':
            [, $unit_id, $start, $end, $face, $start_after, $face_after] = $step;
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT unit_rack_id, unit_start_number, unit_face, unit_full_depth, rack_client_id, rack_archived_at FROM rack_units
                JOIN racks ON rack_id = unit_rack_id WHERE unit_id = " . intval($unit_id)));
            if (!$row || $row['rack_archived_at'] || !hasClientAccess($row['rack_client_id'])
                || intval($row['unit_start_number']) !== intval($start_after) || $row['unit_face'] !== $face_after
                || rackUnitConflict(intval($row['unit_rack_id']), $start, $end, $face, intval($row['unit_full_depth']), $unit_id)) {
                return false;
            }
            mysqli_query($mysqli, "UPDATE rack_units SET unit_start_number = " . intval($start) . ", unit_end_number = " . intval($end) . ", unit_face = " . undoSqlValue($face) . " WHERE unit_id = " . intval($unit_id));
            logAudit("Rack", "Edit", "$session_name moved an item back to " . html_entity_decode(rackPositionLabel($start, $end)) . " ($face)", intval($row['rack_client_id']), intval($row['unit_rack_id']));
            return true;

        case 'ticket_priority':
            [, $ticket_id, $before, $after] = $step;
            $row = ticketFieldsRow($ticket_id);
            if (!$row || !hasClientAccess($row['ticket_client_id']) || $row['ticket_priority'] !== $after) {
                return false;
            }
            return ticketChangePriority($ticket_id, $before)[0];

        case 'ticket_category':
        case 'ticket_project':
            [, $ticket_id, $before, $after] = $step;
            $column = $step[0] === 'ticket_category' ? 'ticket_category' : 'ticket_project_id';
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_client_id, ticket_prefix, ticket_number FROM tickets WHERE ticket_id = " . intval($ticket_id) . " AND $column = " . intval($after)));
            if (!$row || !hasClientAccess($row['ticket_client_id'])) {
                return false;
            }
            mysqli_query($mysqli, "UPDATE tickets SET $column = " . intval($before) . " WHERE ticket_id = " . intval($ticket_id));
            if ($column === 'ticket_category') {
                $name = intval($before) ? (string) getFieldById('categories', intval($before), 'category_name') : 'Not categorized';
                logTicketHistory($ticket_id, "$session_name put the category back to " . escapeSql($name));
            } else {
                $name = intval($before) ? (string) getFieldById('projects', intval($before), 'project_name') : '';
                logTicketHistory($ticket_id, $name !== '' ? "$session_name put the ticket back in project " . escapeSql($name) : "$session_name took the ticket back out of its project");
            }
            logAudit("Ticket", "Edit", "$session_name undid a bulk change on ticket " . escapeSql(ticketReference($row)), intval($row['ticket_client_id']), $ticket_id);
            triggerCustomAction('ticket_update', $ticket_id);
            return true;

        case 'asset_status':
            [, $asset_id, $before, $after] = $step;
            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT asset_name, asset_client_id FROM assets WHERE asset_id = " . intval($asset_id) . " AND asset_status <=> " . undoSqlValue($after)));
            if (!$row || !hasClientAccess($row['asset_client_id'])) {
                return false;
            }
            mysqli_query($mysqli, "UPDATE assets SET asset_status = " . undoSqlValue($before) . " WHERE asset_id = " . intval($asset_id));
            mysqli_query($mysqli, "INSERT INTO asset_history SET asset_history_status = " . undoSqlValue((string) $before) . ", asset_history_description = '" . escapeSql("$session_name put the status back on " . $row['asset_name']) . "', asset_history_asset_id = " . intval($asset_id));
            return true;
    }
    return false;
}
