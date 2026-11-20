<?php

/*
 * Client portal announcements (Admin > Client Portal) - the notices on the
 * portal's home page, to every client or one, between their start and end.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_portal_announcement'])) {

    validateCSRFToken();

    [$fields, $error] = announcementFromPost();
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO portal_announcements SET " . announcementSetSql($fields) . ", announcement_created_by = $session_user_id");
    $announcement_id = mysqli_insert_id($mysqli);
    $title = escapeSql($fields['title']);

    logAudit("Announcement", "Create", "$session_name posted the client portal announcement $title", intval($fields['client_id']), $announcement_id);

    flashAlert("Announcement <strong>" . escapeHtml($fields['title']) . "</strong> posted");

    redirect();

}

if (isset($_POST['edit_portal_announcement'])) {

    validateCSRFToken();

    $announcement_id = intval($_POST['announcement_id'] ?? 0);
    [$fields, $error] = announcementFromPost();
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE portal_announcements SET " . announcementSetSql($fields) . " WHERE announcement_id = $announcement_id");
    $title = escapeSql($fields['title']);

    logAudit("Announcement", "Edit", "$session_name edited the client portal announcement $title", intval($fields['client_id']), $announcement_id);

    flashAlert("Announcement <strong>" . escapeHtml($fields['title']) . "</strong> saved");

    redirect();

}

if (isset($_GET['archive_portal_announcement']) || isset($_GET['restore_portal_announcement'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_portal_announcement']);
    $announcement_id = intval($archive ? $_GET['archive_portal_announcement'] : $_GET['restore_portal_announcement']);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT announcement_title, announcement_client_id FROM portal_announcements WHERE announcement_id = $announcement_id"));
    if (!$row) {
        redirect();
    }

    $undo = $archive ? undoArchivedBefore('portal_announcements', 'announcement_id', [$announcement_id]) : null;
    mysqli_query($mysqli, "UPDATE portal_announcements SET announcement_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE announcement_id = $announcement_id");
    $title = escapeSql($row['announcement_title']);

    logAudit("Announcement", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " the client portal announcement $title", intval($row['announcement_client_id']), $announcement_id);

    flashAlert("Announcement <strong>" . escapeHtml($row['announcement_title']) . "</strong> " . ($archive ? 'archived - the portal no longer shows it' : 'restored'), $archive ? 'error' : 'success');
    if ($undo) {
        undoOfferArchived($undo, '', $row['announcement_title']);
    }

    redirect();

}

if (isset($_GET['delete_portal_announcement'])) {

    validateCSRFToken();

    $announcement_id = intval($_GET['delete_portal_announcement']);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT announcement_title, announcement_client_id FROM portal_announcements WHERE announcement_id = $announcement_id"));
    if (!$row) {
        redirect();
    }

    mysqli_query($mysqli, "DELETE FROM portal_announcements WHERE announcement_id = $announcement_id");
    $title = escapeSql($row['announcement_title']);

    logAudit("Announcement", "Delete", "$session_name deleted the client portal announcement $title", intval($row['announcement_client_id']), $announcement_id);

    flashAlert("Announcement <strong>" . escapeHtml($row['announcement_title']) . "</strong> deleted", 'error');

    redirect();

}
