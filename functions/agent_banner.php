<?php

/*
 * Banners for agents - a note along the top of agents' pages: for every client, on every page,
 * or for one client, on its pages and on its tickets, invoices, quotes, recurring invoices and
 * projects opened on their own. Kept with the client portal's announcements (functions/portal.php),
 * as announcement_audience agents, with the same tones and form, and shown between their start
 * and end. Notice and Heads up can be hidden by each agent until the banner is changed
 * (announcement_dismissals); Urgent is big and red, and nobody can hide it.
 *
 * A client's are added and changed from its ⋮ menu by agents who can edit that client; every
 * client's by admins, from Admin > Agent Banners, which lists them all. The handlers are
 * agent/post/agent_banner.php.
 */

/** Has the 3.7.2 update run? Until it has, there are no banners. */
function agentBannersReady() {
    return defined('CURRENT_DATABASE_VERSION') && version_compare(CURRENT_DATABASE_VERSION, '3.7.2', '>=');
}

/** May this agent add or change a banner for this client - one they can edit, or, for every client (0), an admin? */
function agentBannerCanEdit($client_id) {
    global $session_is_admin;
    $client_id = intval($client_id);
    if (!empty($session_is_admin)) {
        return true;
    }
    return $client_id > 0 && lookupUserPermission('module_client') >= 2 && hasClientAccess($client_id);
}

/** A banner, or null. */
function agentBannerRow($announcement_id) {
    global $mysqli;
    $announcement_id = intval($announcement_id);
    if (!agentBannersReady() || !$announcement_id) {
        return null;
    }
    return mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT announcement_id, announcement_title, announcement_body, announcement_tone, announcement_client_id,
        announcement_starts_at, announcement_ends_at, announcement_archived_at
        FROM portal_announcements WHERE announcement_id = $announcement_id AND announcement_audience = 'agents' LIMIT 1")) ?: null;
}

/** A client's banners not over yet - showing or still to come - for its ⋮ menu. */
function agentBannersForClient($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $rows = [];
    if (!agentBannersReady() || !$client_id) {
        return $rows;
    }
    $sql = mysqli_query($mysqli, "SELECT announcement_id, announcement_title, announcement_tone, announcement_starts_at, announcement_ends_at, announcement_archived_at
        FROM portal_announcements
        WHERE announcement_audience = 'agents' AND announcement_client_id = $client_id AND announcement_archived_at IS NULL
          AND (announcement_ends_at IS NULL OR announcement_ends_at > NOW())
        ORDER BY COALESCE(announcement_starts_at, announcement_created_at) DESC, announcement_id DESC
        LIMIT 10");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    return $rows;
}

/*
 * The banners for this page, as HTML - every client's, and this client's when the page is one of
 * its (0 when it isn't). Urgent first, then the client's, then the newest. Drawn with the alerts
 * (includes/inc_alert_feedback.php), unless the page sets $agent_banners_later because it learns
 * its client further down (a ticket opened on its own) and asks then. Each is drawn once a page,
 * so asking again on a client's page adds nothing.
 */
function agentBannersHtml($client_id = 0) {
    global $mysqli, $session_user_id;
    static $shown = [];
    static $script = false;

    $user_id = intval($session_user_id ?? 0);
    if (!$user_id || !agentBannersReady()) {
        return '';
    }
    $client_id = intval($client_id);
    $not_shown = $shown ? ' AND announcement_id NOT IN (' . implode(',', array_keys($shown)) . ')' : '';

    $sql = mysqli_query($mysqli, "SELECT announcement_id, announcement_title, announcement_body, announcement_tone, announcement_client_id, client_name
        FROM portal_announcements
        LEFT JOIN clients ON client_id = announcement_client_id
        LEFT JOIN announcement_dismissals ON announcement_dismissal_announcement_id = announcement_id AND announcement_dismissal_user_id = $user_id
        WHERE announcement_audience = 'agents' AND announcement_archived_at IS NULL AND announcement_client_id IN (0, $client_id)$not_shown
          AND (announcement_starts_at IS NULL OR announcement_starts_at <= NOW())
          AND (announcement_ends_at IS NULL OR announcement_ends_at > NOW())
          AND (announcement_tone = 'danger' OR announcement_dismissal_at IS NULL OR announcement_dismissal_at < COALESCE(announcement_updated_at, announcement_created_at))
        ORDER BY announcement_tone = 'danger' DESC, announcement_client_id > 0 DESC, COALESCE(announcement_starts_at, announcement_created_at) DESC, announcement_id DESC
        LIMIT 10");

    $html = '';
    $hideable = false;
    while ($row = mysqli_fetch_assoc($sql)) {
        $announcement_id = intval($row['announcement_id']);
        $shown[$announcement_id] = true;
        $tone_key = isset(PORTAL_ANNOUNCEMENT_TONES[$row['announcement_tone']]) ? $row['announcement_tone'] : 'info';
        $tone = PORTAL_ANNOUNCEMENT_TONES[$tone_key];
        $urgent = $tone_key === 'danger';
        $hideable = $hideable || !$urgent;
        $for = intval($row['announcement_client_id']) ? '<span class="itflow-agent-banner-for">' . escapeHtml($row['client_name'] ?? '') . '</span>' : '';
        $body = trim((string) $row['announcement_body']) !== '' ? '<div class="itflow-agent-banner-body">' . portalAnnouncementBodyHtml($row['announcement_body']) . '</div>' : '';

        $html .= '<div class="itflow-agent-banner is-' . $tone_key . ($urgent ? '' : ' alert alert-' . $tone_key . ' gap-2') . ' d-flex align-items-start d-print-none" data-agent-banner="' . $announcement_id . '" role="' . ($urgent ? 'alert' : 'status') . '">'
            . '<i class="fas fa-fw ' . $tone['icon'] . ' itflow-agent-banner-icon"></i>'
            . '<div class="min-w-0 flex-grow-1"><div class="itflow-agent-banner-title">' . escapeHtml($row['announcement_title']) . $for . '</div>' . $body . '</div>'
            . ($urgent ? '' : '<button type="button" class="btn-close" data-agent-banner-hide="' . $announcement_id . '" title="Hide - it comes back if it is changed" aria-label="Hide"></button>')
            . '</div>';
    }

    // Hiding one - gone at once, and kept hidden for this agent from the next page on
    if ($hideable && !$script) {
        $script = true;
        $html .= '<script>document.addEventListener("click", function (event) {
            var button = event.target.closest("[data-agent-banner-hide]");
            if (!button) { return; }
            var body = new FormData();
            body.append("hide_agent_banner", button.getAttribute("data-agent-banner-hide"));
            body.append("csrf_token", ' . json_encode((string) ($_SESSION['csrf_token'] ?? '')) . ');
            fetch("/agent/post.php", { method: "POST", body: body, credentials: "same-origin" }).catch(function () {});
            button.closest("[data-agent-banner]").remove();
        });</script>';
    }

    return $html;
}
