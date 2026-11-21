<?php

/*
 * Banners for agents (functions/agent_banner.php) - a client's, from its ⋮ menu, and every
 * client's, from Admin > Agent Banners - and an agent hiding one.
 */

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['add_agent_banner'])) {

    validateCSRFToken();

    [$fields, $error] = announcementFromPost('A banner');
    if ($error === '' && !agentBannerCanEdit($fields['client_id'])) {
        $error = intval($fields['client_id']) ? "You can't add a banner for that client" : 'Only an admin can add a banner for every client';
    }
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "INSERT INTO portal_announcements SET " . announcementSetSql($fields) . ", announcement_audience = 'agents', announcement_created_by = $session_user_id");
    $announcement_id = mysqli_insert_id($mysqli);
    $title = escapeSql($fields['title']);

    logAudit("Announcement", "Create", "$session_name posted the agent banner $title", intval($fields['client_id']), $announcement_id);

    flashAlert("Banner <strong>" . escapeHtml($fields['title']) . "</strong> posted");

    redirect();

}

if (isset($_POST['edit_agent_banner'])) {

    validateCSRFToken();

    $row = agentBannerRow($_POST['announcement_id'] ?? 0);
    if (!$row) {
        redirect();
    }
    $announcement_id = intval($row['announcement_id']);

    [$fields, $error] = announcementFromPost('A banner');
    if ($error === '' && (!agentBannerCanEdit($row['announcement_client_id']) || !agentBannerCanEdit($fields['client_id']))) {
        $error = intval($row['announcement_client_id']) ? "You can't change that client's banners" : 'Only an admin can change a banner for every client';
    }
    if ($error !== '') {
        flashAlert($error, 'error');
        redirect();
    }

    mysqli_query($mysqli, "UPDATE portal_announcements SET " . announcementSetSql($fields) . " WHERE announcement_id = $announcement_id");
    $title = escapeSql($fields['title']);

    logAudit("Announcement", "Edit", "$session_name edited the agent banner $title", intval($fields['client_id']), $announcement_id);

    flashAlert("Banner <strong>" . escapeHtml($fields['title']) . "</strong> saved");

    redirect();

}

if (isset($_GET['archive_agent_banner']) || isset($_GET['restore_agent_banner'])) {

    validateCSRFToken();

    $archive = isset($_GET['archive_agent_banner']);
    $row = agentBannerRow($archive ? $_GET['archive_agent_banner'] : $_GET['restore_agent_banner']);
    if (!$row || !agentBannerCanEdit($row['announcement_client_id'])) {
        redirect();
    }
    $announcement_id = intval($row['announcement_id']);
    $undo = $archive ? undoArchivedBefore('portal_announcements', 'announcement_id', [$announcement_id], 'announcement_client_id') : null;

    mysqli_query($mysqli, "UPDATE portal_announcements SET announcement_archived_at = " . ($archive ? 'NOW()' : 'NULL') . " WHERE announcement_id = $announcement_id");
    $title = escapeSql($row['announcement_title']);

    logAudit("Announcement", $archive ? "Archive" : "Restore", "$session_name " . ($archive ? 'archived' : 'restored') . " the agent banner $title", intval($row['announcement_client_id']), $announcement_id);

    flashAlert("Banner <strong>" . escapeHtml($row['announcement_title']) . "</strong> " . ($archive ? 'archived - nobody sees it now' : 'restored'), $archive ? 'error' : 'success');
    if ($undo) {
        $client_banner = intval($row['announcement_client_id']) > 0;
        undoOfferArchived($undo, '', $row['announcement_title'], [], 's', $client_banner ? 'module_client' : 'admin', $client_banner ? 2 : 3);
    }

    redirect();

}

if (isset($_GET['delete_agent_banner'])) {

    validateCSRFToken();

    $row = agentBannerRow($_GET['delete_agent_banner']);
    if (!$row || !agentBannerCanEdit($row['announcement_client_id'])) {
        redirect();
    }
    $announcement_id = intval($row['announcement_id']);

    mysqli_query($mysqli, "DELETE FROM portal_announcements WHERE announcement_id = $announcement_id");
    mysqli_query($mysqli, "DELETE FROM announcement_dismissals WHERE announcement_dismissal_announcement_id = $announcement_id");
    $title = escapeSql($row['announcement_title']);

    logAudit("Announcement", "Delete", "$session_name deleted the agent banner $title", intval($row['announcement_client_id']), $announcement_id);

    flashAlert("Banner <strong>" . escapeHtml($row['announcement_title']) . "</strong> deleted", 'error');

    redirect();

}

// Hidden by this agent until it is changed - not an Urgent one (sent by the banner's close button)
if (isset($_POST['hide_agent_banner'])) {

    validateCSRFToken();

    $row = agentBannerRow($_POST['hide_agent_banner']);
    if ($row && $row['announcement_tone'] !== 'danger') {
        mysqli_query($mysqli, "INSERT INTO announcement_dismissals SET announcement_dismissal_announcement_id = " . intval($row['announcement_id']) . ", announcement_dismissal_user_id = $session_user_id
            ON DUPLICATE KEY UPDATE announcement_dismissal_at = NOW()");
    }

    http_response_code(204);
    exit;

}
