<?php

/*
 * Categories (Admin > Categories) - making, changing, ordering, merging,
 * archiving and deleting them. The work is in functions/categories.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// "asset status" - what a category is, for the audit log and messages
$category_kind_word = function ($row) {
    return categoryKind($row['category_type'] ?? '')['one'] ?? 'category';
};

// Admin > Categories on a kind, to go back to after making one
$category_page = function ($kind, $archived = false) {
    return 'categories.php?category=' . urlencode($kind) . ($archived ? '&archived=1' : '');
};

if (isset($_POST['add_category'])) {

    validateCSRFToken();

    [$fields, $error] = categoryFromInput($_POST);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $category_id = categoryCreate($fields);
    $kind = $category_kind_word(['category_type' => $fields['type']]);

    logAudit("Category", "Create", "$session_name created $kind " . escapeSql($fields['name']), 0, $category_id);

    flashAlert(ucfirst($kind) . " <strong>" . escapeHtml($fields['name']) . "</strong> created");

    redirect($category_page($fields['type']));

}

if (isset($_POST['edit_category'])) {

    validateCSRFToken();

    $row = categoryRow(intval($_POST['category_id'] ?? 0));
    if (!$row) {
        flashAlert("That category is gone - it may have been merged into another, or deleted", 'error');
        redirect();
    }

    [$fields, $error] = categoryFromInput($_POST, $row);
    if ($error !== '') {
        flashAlert(escapeHtml($error), 'error');
        redirect();
    }

    $renamed = categoryUpdate($row, $fields);
    $kind = $category_kind_word($row);
    $was = $row['category_name'] !== $fields['name'] ? " (was " . escapeSql($row['category_name']) . ")" : '';

    logAudit("Category", "Edit", "$session_name edited $kind " . escapeSql($fields['name']) . $was . ($renamed ? " - renamed on $renamed " . ($renamed === 1 ? 'record' : 'records') : ''), 0, intval($row['category_id']));

    flashAlert(ucfirst($kind) . " <strong>" . escapeHtml($fields['name']) . "</strong> saved" . ($renamed ? " - and renamed on the " . number_format($renamed) . ' ' . ($renamed === 1 ? 'record' : 'records') . " that had it" : ''));

    redirect();

}

if (isset($_GET['archive_category'])) {

    validateCSRFToken();

    $row = categoryRow(intval($_GET['archive_category']));
    if ($row && !$row['category_archived_at']) {
        categoryArchive($row['category_id']);
        $used = categoryUsageFor($row['category_id']);
        logAudit("Category", "Archive", "$session_name archived " . $category_kind_word($row) . " " . escapeSql($row['category_name']), 0, intval($row['category_id']));
        flashAlert(ucfirst($category_kind_word($row)) . " <strong>" . escapeHtml($row['category_name']) . "</strong> archived - "
            . ($used['all'] ? "it stays on the " . escapeHtml(categoryUsageText($row, $used)) . " that have it, but isn't offered for new ones" : "it isn't offered for new ones"));
    }

    redirect();

}

if (isset($_GET['restore_category'])) {

    validateCSRFToken();

    $row = categoryRow(intval($_GET['restore_category']));
    if ($row && $row['category_archived_at']) {
        categoryRestore($row['category_id']);
        logAudit("Category", "Restore", "$session_name restored " . $category_kind_word($row) . " " . escapeSql($row['category_name']), 0, intval($row['category_id']));
        flashAlert(ucfirst($category_kind_word($row)) . " <strong>" . escapeHtml($row['category_name']) . "</strong> restored");
    }

    redirect();

}

if (isset($_GET['delete_category'])) {

    validateCSRFToken();

    $row = categoryRow(intval($_GET['delete_category']));
    if (!$row) {
        redirect();
    }

    $error = '';
    if (!categoryDelete($row['category_id'], $error)) {
        flashAlert(escapeHtml(ucfirst($category_kind_word($row)) . " " . $row['category_name'] . " is kept - " . lcfirst($error)), 'error');
        redirect();
    }

    logAudit("Category", "Delete", "$session_name deleted " . $category_kind_word($row) . " " . escapeSql($row['category_name']));

    flashAlert(ucfirst($category_kind_word($row)) . " <strong>" . escapeHtml($row['category_name']) . "</strong> deleted", 'error');

    redirect();

}

if (isset($_POST['merge_categories'])) {

    validateCSRFToken();

    $into = categoryRow(intval($_POST['into_category_id'] ?? 0));
    if (!$into) {
        flashAlert("Pick the category to keep", 'error');
        redirect();
    }
    $into_id = intval($into['category_id']);

    // Their names before they go - other categories of the same kind only
    $from = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['category_ids'] ?? []))) as $from_id) {
        $from_row = $from_id !== $into_id ? categoryRow($from_id) : null;
        if ($from_row && $from_row['category_type'] === $into['category_type']) {
            $from[$from_id] = $from_row['category_name'];
        }
    }
    if (!$from) {
        flashAlert("Pick another " . escapeHtml($category_kind_word($into)) . " to merge into " . escapeHtml($into['category_name']), 'error');
        redirect();
    }

    $moved = categoryMerge(array_keys($from), $into_id);
    $kind = $category_kind_word($into);
    $records = $moved ? number_format($moved) . ($moved === 1 ? ' record' : ' records') : '';

    logAudit("Category", "Merge", "$session_name merged " . (count($from) === 1 ? $kind : categoryKind($into['category_type'])['many']) . " " . escapeSql(implode(', ', $from)) . " into " . escapeSql($into['category_name'])
        . ($moved ? " - $records" : ''), 0, $into_id);

    flashAlert("Merged " . implode(', ', array_map(function ($name) {
        return '<strong>' . escapeHtml($name) . '</strong>';
    }, $from)) . " into <strong>" . escapeHtml($into['category_name']) . "</strong>"
        . ($moved ? " - the $records that had " . (count($from) === 1 ? 'it' : 'them') . " have it now" : ''));

    redirect();

}

if (isset($_GET['move_category'])) {

    validateCSRFToken();

    // One place up or down among its kind's current categories, in the order pickers use
    $row = categoryRow(intval($_GET['move_category']));
    $config = $row ? categoryKind($row['category_type']) : null;
    if ($config && $config['ordered']) {
        $ids = array_keys(array_filter(categoryRows($row['category_type']), function ($r) {
            return !$r['category_archived_at'];
        }));
        $at = array_search(intval($row['category_id']), $ids, true);
        $to = ($_GET['direction'] ?? '') === 'up' ? $at - 1 : $at + 1;
        if ($at !== false && isset($ids[$to])) {
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            categoryReorder($row['category_type'], $ids);
            logAudit("Category", "Edit", "$session_name moved " . $category_kind_word($row) . " " . escapeSql($row['category_name']) . (($_GET['direction'] ?? '') === 'up' ? ' up' : ' down'), 0, intval($row['category_id']));
        }
    }

    redirect();

}

if (isset($_POST['reorder_categories'])) {

    // Dragged into order on Admin > Categories - answers with JSON, as the page saves it in the background
    header('Content-Type: application/json');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        echo json_encode(['ok' => false, 'error' => 'The page is out of date - reload it']);
        exit;
    }

    $config = categoryKind($_POST['category'] ?? '');
    if (!$config || !$config['ordered']) {
        echo json_encode(['ok' => false, 'error' => 'Those categories keep no order']);
        exit;
    }

    // The current ones in the order dragged, the archived ones after them
    $rows = categoryRows($config['kind']);
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['category_ids'] ?? [])), function ($id) use ($rows) {
        return isset($rows[$id]);
    }));
    if (categoryReorder($config['kind'], $ids)) {
        logAudit("Category", "Edit", "$session_name reordered the " . $config['many']);
    }

    echo json_encode(['ok' => true]);
    exit;

}

if (isset($_POST['bulk_archive_categories']) || isset($_POST['bulk_restore_categories'])) {

    validateCSRFToken();

    $archive = isset($_POST['bulk_archive_categories']);
    $count = 0;
    foreach (array_unique(array_map('intval', (array) ($_POST['category_ids'] ?? []))) as $category_id) {
        $row = categoryRow($category_id);
        if (!$row || (bool) $row['category_archived_at'] === $archive) {
            continue;
        }
        if ($archive) {
            categoryArchive($category_id);
        } else {
            categoryRestore($category_id);
        }
        logAudit("Category", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived ' : 'restored ') . $category_kind_word($row) . " " . escapeSql($row['category_name']), 0, $category_id);
        $count++;
    }

    flashAlert(($archive ? "Archived " : "Restored ") . $count . ($count === 1 ? " category" : " categories") . ($archive && $count ? " - they stay on their records, but aren't offered for new ones" : ''));

    redirect();

}

if (isset($_POST['bulk_delete_categories'])) {

    validateCSRFToken();

    $deleted = 0;
    $kept = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['category_ids'] ?? []))) as $category_id) {
        $row = categoryRow($category_id);
        if (!$row) {
            continue;
        }
        $error = '';
        if (!categoryDelete($category_id, $error)) {
            if ($error !== '') {
                $kept[] = '<strong>' . escapeHtml($row['category_name']) . '</strong>';
            }
            continue;
        }
        logAudit("Category", "Delete", "$session_name deleted " . $category_kind_word($row) . " " . escapeSql($row['category_name']));
        $deleted++;
    }

    flashAlert("Deleted $deleted " . ($deleted === 1 ? 'category' : 'categories')
        . ($kept ? ". Kept " . implode(', ', $kept) . " - " . (count($kept) === 1 ? 'it is' : 'they are') . " in use, so merge " . (count($kept) === 1 ? 'it' : 'them') . " into another, or archive " . (count($kept) === 1 ? 'it' : 'them') : ''), $kept ? 'warning' : 'error');

    redirect();

}
