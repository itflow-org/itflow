<?php

/*
 * Tags (Admin > Tags) - making, changing, merging, archiving and deleting
 * them. The work is in functions/tags.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// "asset" - what a tag is for, for the audit log and messages
$tag_kind_word = function ($row) {
    return strtolower(tagEntityByType($row['tag_type'] ?? 0)['title'] ?? 'unknown');
};

// "12 assets" / "1 asset"
$tag_records_text = function ($n, $type) {
    $kind = tagEntityByType($type);
    return number_format($n) . ' ' . ($n === 1 ? strtolower($kind['title'] ?? 'record') : ($kind['plural'] ?? 'records'));
};

if (isset($_POST['add_tag'])) {

    validateCSRFToken();

    [$fields, $error] = tagFromInput($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $tag_id = tagCreate($fields);
    $kind = $tag_kind_word(['tag_type' => $fields['type']]);

    logAudit("Tag", "Create", "$session_name created $kind tag " . escapeSql($fields['name']), 0, $tag_id);

    flashAlert(ucfirst($kind) . " tag <strong>" . escapeHtml($fields['name']) . "</strong> created");

    redirect("tags.php?type=" . intval($fields['type']));

}

if (isset($_POST['edit_tag'])) {

    validateCSRFToken();

    $tag_id = intval($_POST['tag_id'] ?? 0);
    $row = tagRow($tag_id);
    if (!$row) {
        flashAlert("That tag is gone - it may have been merged into another, or deleted", 'error');
        redirect();
    }

    [$fields, $error] = tagFromInput($_POST, $row);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    tagUpdate($tag_id, $fields);
    $kind = $tag_kind_word(['tag_type' => $fields['type']]);
    $moved = intval($row['tag_type']) !== intval($fields['type']);
    $renamed = $row['tag_name'] !== $fields['name'];

    logAudit("Tag", "Edit", "$session_name edited $kind tag " . escapeSql($fields['name'])
        . ($renamed ? " (was " . escapeSql($row['tag_name']) . ")" : '')
        . ($moved ? " - moved from " . $tag_kind_word($row) . " tags" : ''), 0, $tag_id);

    flashAlert("Tag <strong>" . escapeHtml($fields['name']) . "</strong> saved" . ($moved ? " - it's " . tagKindPhrase($fields['type']) . " now" : ''));

    redirect($moved ? "tags.php?type=" . intval($fields['type']) . ($row['tag_archived_at'] ? '&archived=1' : '') : null);

}

if (isset($_GET['archive_tag'])) {

    validateCSRFToken();

    $row = tagRow(intval($_GET['archive_tag']));
    if ($row && !$row['tag_archived_at']) {
        tagArchive($row['tag_id']);
        $used = tagUsage($row['tag_id']);
        logAudit("Tag", "Archive", "$session_name archived " . $tag_kind_word($row) . " tag " . escapeSql($row['tag_name']), 0, intval($row['tag_id']));
        flashAlert("Tag <strong>" . escapeHtml($row['tag_name']) . "</strong> archived - "
            . ($used ? "it stays on its " . $tag_records_text($used, $row['tag_type']) . ", but isn't offered for tagging" : "it isn't offered for tagging"));
    }

    redirect();

}

if (isset($_GET['restore_tag'])) {

    validateCSRFToken();

    $row = tagRow(intval($_GET['restore_tag']));
    if ($row && $row['tag_archived_at']) {
        tagRestore($row['tag_id']);
        logAudit("Tag", "Restore", "$session_name restored " . $tag_kind_word($row) . " tag " . escapeSql($row['tag_name']), 0, intval($row['tag_id']));
        flashAlert("Tag <strong>" . escapeHtml($row['tag_name']) . "</strong> restored");
    }

    redirect();

}

if (isset($_GET['delete_tag'])) {

    validateCSRFToken();

    $row = tagRow(intval($_GET['delete_tag']));
    if (!$row) {
        redirect();
    }

    $used = tagUsage($row['tag_id']);
    $error = '';
    if (!tagDelete($row['tag_id'], $error)) {
        flashAlert($error !== '' ? escapeHtml($error) : "That tag is gone", 'error');
        redirect();
    }

    logAudit("Tag", "Delete", "$session_name deleted " . $tag_kind_word($row) . " tag " . escapeSql($row['tag_name'])
        . ($used ? ", taking it off " . $tag_records_text($used, $row['tag_type']) : ''));

    flashAlert("Tag <strong>" . escapeHtml($row['tag_name']) . "</strong> deleted" . ($used ? " and taken off " . $tag_records_text($used, $row['tag_type']) : ''), 'error');

    redirect();

}

if (isset($_POST['merge_tags'])) {

    validateCSRFToken();

    $into = tagRow(intval($_POST['into_tag_id'] ?? 0));
    if (!$into) {
        flashAlert("Pick the tag to keep", 'error');
        redirect();
    }
    $into_id = intval($into['tag_id']);

    // Their names before they go - other tags of the same kind only
    $from = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['tag_ids'] ?? []))) as $from_id) {
        $from_row = $from_id !== $into_id ? tagRow($from_id) : null;
        if ($from_row && intval($from_row['tag_type']) === intval($into['tag_type'])) {
            $from[$from_id] = $from_row['tag_name'];
        }
    }
    if (!$from) {
        flashAlert("Pick another " . $tag_kind_word($into) . " tag to merge into " . escapeHtml($into['tag_name']), 'error');
        redirect();
    }

    $moved = tagMerge(array_keys($from), $into_id);

    logAudit("Tag", "Merge", "$session_name merged " . $tag_kind_word($into) . " " . (count($from) === 1 ? 'tag' : 'tags') . " " . escapeSql(implode(', ', $from)) . " into " . escapeSql($into['tag_name'])
        . ($moved ? " - " . $tag_records_text($moved, $into['tag_type']) : ''), 0, $into_id);

    flashAlert("Merged " . implode(', ', array_map(function ($name) {
        return '<strong>' . escapeHtml($name) . '</strong>';
    }, $from)) . " into <strong>" . escapeHtml($into['tag_name']) . "</strong>"
        . ($moved ? " - the " . $tag_records_text($moved, $into['tag_type']) . " that had " . (count($from) === 1 ? 'it' : 'them') . " have it now" : ''));

    redirect();

}

if (isset($_POST['bulk_archive_tags']) || isset($_POST['bulk_restore_tags'])) {

    validateCSRFToken();

    $archive = isset($_POST['bulk_archive_tags']);
    $count = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['tag_ids'] ?? []))) as $tag_id) {
        $row = tagRow($tag_id);
        if (!$row || (bool) $row['tag_archived_at'] === $archive) {
            continue;
        }
        if ($archive) {
            tagArchive($tag_id);
        } else {
            tagRestore($tag_id);
        }
        logAudit("Tag", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived ' : 'restored ') . $tag_kind_word($row) . " tag " . escapeSql($row['tag_name']), 0, $tag_id);
        $count++;
    }

    flashAlert(($archive ? "Archived " : "Restored ") . $count . ($count === 1 ? " tag" : " tags") . ($archive && $count ? " - they stay on their records, but aren't offered for tagging" : ''));

    redirect();

}

if (isset($_POST['bulk_delete_tags'])) {

    validateCSRFToken();

    $deleted = $kept = $records = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['tag_ids'] ?? []))) as $tag_id) {
        $row = tagRow($tag_id);
        if (!$row) {
            continue;
        }
        $used = tagUsage($tag_id);
        $error = '';
        if (!tagDelete($tag_id, $error)) {
            $kept += $error !== '' ? 1 : 0;
            continue;
        }
        logAudit("Tag", "Delete", "$session_name deleted " . $tag_kind_word($row) . " tag " . escapeSql($row['tag_name'])
            . ($used ? ", taking it off " . $tag_records_text($used, $row['tag_type']) : ''));
        $deleted++;
        $records += $used;
    }

    flashAlert("Deleted $deleted " . ($deleted === 1 ? 'tag' : 'tags') . ($records ? ", taken off " . number_format($records) . ($records === 1 ? ' record' : ' records') : '')
        . ($kept ? ". Kept $kept that recurring invoice lines count by - archive " . ($kept === 1 ? 'it' : 'them') . ", or merge " . ($kept === 1 ? 'it' : 'them') . " into another tag" : ''), $kept ? 'warning' : 'error');

    redirect();

}
