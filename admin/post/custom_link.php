<?php

/*
 * ITFlow - Admin > Custom Links: add and change a link, copy it, move it in
 * its menu (dragged, or up and down), archive it, bring it back and delete
 * it. The work is in functions/custom_link.php.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_custom_link']) || isset($_POST['edit_custom_link'])) {

    validateCSRFToken();

    $row = isset($_POST['edit_custom_link']) ? customLinkRow($_POST['custom_link_id'] ?? 0) : null;
    if (isset($_POST['edit_custom_link']) && !$row) {
        flashAlert("That link is gone - it may have been deleted.", 'error');
        redirect();
    }

    $fields = customLinkFromInput($_POST);
    $problem = customLinkProblem($fields);
    if ($problem !== '') {
        flashAlert(escapeHtml($problem) . ' The link was not saved.', 'error');
        redirect();
    }

    $name = escapeSql($fields['name']);
    $where = escapeSql(customLinkLocations()[$fields['location']]['label']);

    if ($row) {
        $custom_link_id = intval($row['custom_link_id']);
        $changes = customLinkUpdate($row, $fields);
        if ($changes) {
            logAudit("Custom Link", "Edit", "$session_name changed custom link " . escapeSql($row['custom_link_name']) . ": " . escapeSql(implode('; ', $changes)), 0, $custom_link_id);
        }
        flashAlert("Link <strong>" . escapeHtml($fields['name']) . "</strong> saved");
    } else {
        $custom_link_id = customLinkCreate($fields);
        logAudit("Custom Link", "Create", "$session_name added custom link $name to the $where -> " . escapeSql($fields['uri']), 0, $custom_link_id);
        flashAlert("Link <strong>" . escapeHtml($fields['name']) . "</strong> added to the " . escapeHtml(customLinkLocations()[$fields['location']]['label']));
    }

    redirect();

}

if (isset($_GET['copy_custom_link'])) {

    validateCSRFToken();

    $row = customLinkRow($_GET['copy_custom_link']);
    if ($row) {
        $custom_link_id = customLinkCopy($row);
        logAudit("Custom Link", "Create", "$session_name copied custom link " . escapeSql($row['custom_link_name']), 0, $custom_link_id);
        flashAlert("Link <strong>" . escapeHtml($row['custom_link_name']) . "</strong> copied - change the copy, or move it to another menu");
    }

    redirect();

}

if (isset($_GET['archive_custom_link']) || isset($_GET['restore_custom_link'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_custom_link']);
    $row = customLinkRow($archive ? $_GET['archive_custom_link'] : $_GET['restore_custom_link']);
    if ($row) {
        $custom_link_id = intval($row['custom_link_id']);
        if ($archive) {
            mysqli_query($mysqli, "UPDATE custom_links SET custom_link_archived_at = NOW() WHERE custom_link_id = $custom_link_id");
        } else {
            // Back at the end of its menu
            mysqli_query($mysqli, "UPDATE custom_links SET custom_link_archived_at = NULL, custom_link_order = " . customLinkNextOrder($row['custom_link_location']) . " WHERE custom_link_id = $custom_link_id");
        }
        logAudit("Custom Link", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " custom link " . escapeSql($row['custom_link_name']), 0, $custom_link_id);
        flashAlert("Link <strong>" . escapeHtml($row['custom_link_name']) . "</strong> " . ($archive ? 'archived - it no longer shows in its menu' : 'restored'));
    }

    redirect();

}

if (isset($_GET['delete_custom_link'])) {

    validateCSRFToken();

    $row = customLinkRow($_GET['delete_custom_link']);
    if ($row) {
        $custom_link_id = intval($row['custom_link_id']);
        mysqli_query($mysqli, "DELETE FROM custom_links WHERE custom_link_id = $custom_link_id");
        logAudit("Custom Link", "Delete", "$session_name deleted custom link " . escapeSql($row['custom_link_name']) . " -> " . escapeSql($row['custom_link_uri']), 0, $custom_link_id);
        flashAlert("Link <strong>" . escapeHtml($row['custom_link_name']) . "</strong> deleted", 'error');
    }

    redirect();

}

if (isset($_GET['move_custom_link'])) {

    validateCSRFToken();

    // One place up or down in its menu
    $row = customLinkRow($_GET['move_custom_link']);
    if ($row && !$row['custom_link_archived_at']) {
        $ids = customLinkMenuIds($row['custom_link_location']);
        $at = array_search(intval($row['custom_link_id']), $ids, true);
        $up = ($_GET['direction'] ?? '') === 'up';
        $to = $up ? $at - 1 : $at + 1;
        if ($at !== false && isset($ids[$to])) {
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            customLinkReorder($row['custom_link_location'], $ids);
            logAudit("Custom Link", "Edit", "$session_name moved custom link " . escapeSql($row['custom_link_name']) . ($up ? ' up' : ' down'), 0, intval($row['custom_link_id']));
        }
    }

    redirect();

}

if (isset($_POST['reorder_custom_links'])) {

    // Dragged into order on Admin > Custom Links - answers with JSON, as the page saves it in the background
    header('Content-Type: application/json');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        echo json_encode(['ok' => false, 'error' => 'The page is out of date - reload it']);
        exit;
    }

    $location = intval($_POST['location'] ?? 0);
    if (!isset(customLinkLocations()[$location])) {
        echo json_encode(['ok' => false, 'error' => 'No such menu']);
        exit;
    }

    // The ones dragged, a page of them, in their place among the menu's others - offset is how many come before them
    $current = customLinkMenuIds($location);
    $ids = array_values(array_intersect(array_unique(array_map('intval', (array) ($_POST['custom_link_ids'] ?? []))), $current));
    $new_order = array_values(array_diff($current, $ids));
    array_splice($new_order, min(max(0, intval($_POST['offset'] ?? 0)), count($new_order)), 0, $ids);
    if (customLinkReorder($location, $new_order)) {
        logAudit("Custom Link", "Edit", "$session_name reordered the custom links in the " . escapeSql(customLinkLocations()[$location]['label']));
    }

    echo json_encode(['ok' => true]);
    exit;

}
