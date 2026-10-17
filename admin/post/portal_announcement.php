<?php

/*
 * Client portal announcements (Admin > Client Portal) - the notices on the
 * portal's home page, to every client or one, between their start and end.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

/** The announcement form, checked - [$fields, $error]. Raw values; the handlers escape them. */
function portalAnnouncementFromPost() {
    global $mysqli;
    $title = trim(mb_substr((string) ($_POST['title'] ?? ''), 0, 200));
    $body = trim(mb_substr((string) ($_POST['body'] ?? ''), 0, 5000));
    $tone = isset(PORTAL_ANNOUNCEMENT_TONES[$_POST['tone'] ?? '']) ? $_POST['tone'] : 'info';
    $client_id = intval($_POST['client_id'] ?? 0);
    if ($client_id && !mysqli_num_rows(mysqli_query($mysqli, "SELECT client_id FROM clients WHERE client_id = $client_id"))) {
        $client_id = 0;
    }
    $when = function ($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $time = strtotime($value);
        return $time ? date('Y-m-d H:i:s', $time) : false;
    };
    $starts = $when($_POST['starts_at'] ?? '');
    $ends = $when($_POST['ends_at'] ?? '');
    $fields = compact('title', 'body', 'tone', 'client_id', 'starts', 'ends');
    if ($title === '') {
        return [$fields, 'An announcement needs a title'];
    }
    if ($starts === false || $ends === false) {
        return [$fields, 'That date is not one'];
    }
    if ($starts && $ends && strtotime($ends) <= strtotime($starts)) {
        return [$fields, 'It has to end after it starts'];
    }
    return [$fields, ''];
}

/** The SET list for an announcement's fields. */
function portalAnnouncementSetSql(array $f) {
    global $mysqli;
    $body = mysqli_real_escape_string($mysqli, $f['body']);
    return "announcement_title = '" . escapeSql($f['title']) . "', announcement_body = '$body', announcement_tone = '" . escapeSql($f['tone']) . "',
        announcement_client_id = " . intval($f['client_id']) . ",
        announcement_starts_at = " . ($f['starts'] ? "'" . escapeSql($f['starts']) . "'" : 'NULL') . ",
        announcement_ends_at = " . ($f['ends'] ? "'" . escapeSql($f['ends']) . "'" : 'NULL');
}

if (isset($_POST['add_portal_announcement'])) {

    validateCSRFToken();

    [$fields, $error] = portalAnnouncementFromPost();
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO portal_announcements SET " . portalAnnouncementSetSql($fields) . ", announcement_created_by = $session_user_id");
    $announcement_id = mysqli_insert_id($mysqli);
    $title = escapeSql($fields['title']);

    logAudit("Announcement", "Create", "$session_name posted the client portal announcement $title", intval($fields['client_id']), $announcement_id);

    flashAlert("Announcement <strong>" . escapeHtml($fields['title']) . "</strong> posted");

    redirect();

}

if (isset($_POST['edit_portal_announcement'])) {

    validateCSRFToken();

    $announcement_id = intval($_POST['announcement_id'] ?? 0);
    [$fields, $error] = portalAnnouncementFromPost();
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE portal_announcements SET " . portalAnnouncementSetSql($fields) . " WHERE announcement_id = $announcement_id");
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

    mysqli_query($mysqli, "UPDATE portal_announcements SET announcement_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE announcement_id = $announcement_id");
    $title = escapeSql($row['announcement_title']);

    logAudit("Announcement", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " the client portal announcement $title", intval($row['announcement_client_id']), $announcement_id);

    flashAlert("Announcement <strong>" . escapeHtml($row['announcement_title']) . "</strong> " . ($archive ? 'archived - the portal no longer shows it' : 'restored'), $archive ? 'error' : 'success');

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
