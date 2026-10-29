<?php

/*
 * Mentions in a ticket's internal notes - @ and an agent's name, picked from
 * the agents who can open the ticket (js/app.js itflowMentionAutocompleter).
 * Each agent a note mentions is told in ITFlow and by email, the note goes
 * with them on the ticket ("Mentions you"), and the tickets list's Mentioned
 * me finds the open tickets they were mentioned on.
 *
 * In the note, a mention is a span the editor can't type into:
 *   <span class="itflow-mention itflow-mention-u5" data-mention="5" contenteditable="false">@Jordan Lee</span>
 * Saving puts each one right (ticketMentionsFromHtml()): the agent's name as
 * it is now, or the span gone - leaving its text - for anyone who can't open
 * the ticket, and for every mention in a public reply, which tells nobody.
 * The page's purifier keeps the classes, so a mention of you stands out.
 *
 * Who was mentioned is kept in ticket_reply_mentions, with the note: an edit
 * that adds someone tells them, one that takes someone out takes them off,
 * and an archived note's mentions no longer count.
 */

/** Has the 3.5.3 update run? Until it has, a mention is plain text and nobody is told. */
function ticketMentionsReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '3.5.3', '>=');
    }
    return $ready;
}

/**
 * The agents who can open a ticket of this client - active, with Support (or
 * an admin role), and let at the client by their client access - by name:
 * user_id => ['id', 'name', 'role', 'avatar' (its URL, or ''), 'initials'].
 * $client_id 0 is a ticket with no client, which any of them can open.
 */
function ticketMentionAgents($client_id) {
    global $mysqli;
    static $cache = [];
    $client_id = intval($client_id);
    if (isset($cache[$client_id])) {
        return $cache[$client_id];
    }
    $support_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT module_id FROM modules WHERE module_name = 'module_support' LIMIT 1"))[0] ?? 0);
    $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar, role_name, role_is_admin,
            COALESCE((SELECT user_role_permission_level FROM user_role_permissions WHERE user_role_permissions.user_role_id = users.user_role_id AND module_id = $support_id LIMIT 1), 0) AS support_level
        FROM users LEFT JOIN user_roles ON role_id = user_role_id
        WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL
        ORDER BY user_name ASC");
    $agents = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        if (intval($row['role_is_admin']) !== 1 && intval($row['support_level']) < 1) {
            continue;
        }
        $user_id = intval($row['user_id']);
        $agents[$user_id] = [
            'id' => $user_id,
            'name' => (string) $row['user_name'],
            'role' => (string) ($row['role_name'] ?? ''),
            'admin' => intval($row['role_is_admin']) === 1,
            'avatar' => trim((string) $row['user_avatar']) !== '' ? "/uploads/users/$user_id/" . rawurlencode($row['user_avatar']) : '',
            'initials' => personInitials($row['user_name']),
        ];
    }
    // Client access: a deny keeps them out; an allow list keeps them to it. Admins see every client.
    if ($client_id && $agents) {
        $allow = $deny = [];
        $sql = mysqli_query($mysqli, "SELECT user_id, client_id, permission_type FROM user_client_permissions WHERE user_id IN (" . implode(',', array_keys($agents)) . ")");
        while ($row = mysqli_fetch_assoc($sql)) {
            if ($row['permission_type'] === 'deny') {
                $deny[intval($row['user_id'])][] = intval($row['client_id']);
            } else {
                $allow[intval($row['user_id'])][] = intval($row['client_id']);
            }
        }
        foreach ($agents as $user_id => $agent) {
            if ($agent['admin']) {
                continue;
            }
            if (in_array($client_id, $deny[$user_id] ?? [], true) || (!empty($allow[$user_id]) && !in_array($client_id, $allow[$user_id], true))) {
                unset($agents[$user_id]);
            }
        }
    }
    return $cache[$client_id] = $agents;
}

/** Who the @ picker offers on a ticket of this client - everyone who can open it but you: [['id', 'name', 'role', 'avatar', 'initials'], ...]. None before the update. */
function ticketMentionOffer($client_id) {
    global $session_user_id;
    $offer = [];
    if (!ticketMentionsReady()) {
        return $offer;
    }
    foreach (ticketMentionAgents($client_id) as $user_id => $agent) {
        if ($user_id === intval($session_user_id)) {
            continue;
        }
        $offer[] = ['id' => $user_id, 'name' => $agent['name'], 'role' => $agent['role'], 'avatar' => $agent['avatar'], 'initials' => $agent['initials']];
    }
    return $offer;
}

/** The offer as a textarea's data-mention-agents attribute (js/app.js), or '' when there is nobody to offer. */
function ticketMentionAgentsAttr($client_id) {
    $offer = ticketMentionOffer($client_id);
    return $offer ? ' data-mention-agents="' . escapeHtml(json_encode($offer)) . '"' : '';
}

/** A mention as it is saved - the agent's name as it is now. */
function ticketMentionSpan($user_id, $name) {
    $user_id = intval($user_id);
    return '<span class="itflow-mention itflow-mention-u' . $user_id . '" data-mention="' . $user_id . '" contenteditable="false">@' . escapeHtml($name) . '</span>';
}

/**
 * A note's mentions put right before it is saved - [$html, user ids]. In an
 * internal note ($internal) each mention of an agent who can open a ticket of
 * $client_id is made again with their name as it is now, and counted; any
 * other - someone who can't, or every one in a public reply - is left as the
 * text it showed, @ and all, and tells nobody. $html is the note as posted
 * (not SQL escaped).
 */
function ticketMentionsFromHtml($html, $client_id, $internal = true) {
    $html = (string) $html;
    if (!ticketMentionsReady() || stripos($html, 'data-mention') === false) {
        return [$html, []];
    }
    $agents = $internal ? ticketMentionAgents($client_id) : [];
    $ids = [];
    $html = preg_replace_callback('#<span\b(?=[^>]*\bdata-mention\s*=\s*["\']?(\d+))[^>]*>(.*?)</span>#is', function ($m) use ($agents, &$ids) {
        $user_id = intval($m[1]);
        if (!isset($agents[$user_id])) {
            return strip_tags($m[2]);
        }
        $ids[$user_id] = $user_id;
        return ticketMentionSpan($user_id, $agents[$user_id]['name']);
    }, $html);
    return [$html, array_values($ids)];
}

/** Who a note mentions now - [user ids]; ones no longer there taken off, new ones added. The ones added - to tell. */
function ticketMentionsSync($reply_id, array $user_ids) {
    global $mysqli, $session_user_id;
    $reply_id = intval($reply_id);
    if (!$reply_id || !ticketMentionsReady()) {
        return [];
    }
    $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
    $had = [];
    $sql = mysqli_query($mysqli, "SELECT mention_user_id FROM ticket_reply_mentions WHERE mention_reply_id = $reply_id");
    while ($row = mysqli_fetch_row($sql)) {
        $had[] = intval($row[0]);
    }
    $gone = array_diff($had, $user_ids);
    if ($gone) {
        mysqli_query($mysqli, "DELETE FROM ticket_reply_mentions WHERE mention_reply_id = $reply_id AND mention_user_id IN (" . implode(',', $gone) . ")");
    }
    $new = array_values(array_diff($user_ids, $had));
    foreach ($new as $user_id) {
        mysqli_query($mysqli, "INSERT IGNORE INTO ticket_reply_mentions SET mention_reply_id = $reply_id, mention_user_id = $user_id, mention_by = " . intval($session_user_id));
    }
    return $new;
}

/** The ids of the agents a ticket's notes mention, by note - reply_id => [user ids]. Archived notes left out. */
function ticketMentionsForTicket($ticket_id) {
    global $mysqli;
    $mentions = [];
    if (!ticketMentionsReady()) {
        return $mentions;
    }
    $sql = mysqli_query($mysqli, "SELECT mention_reply_id, mention_user_id FROM ticket_reply_mentions
        JOIN ticket_replies ON ticket_reply_id = mention_reply_id
        WHERE ticket_reply_ticket_id = " . intval($ticket_id) . " AND ticket_reply_archived_at IS NULL");
    while ($row = mysqli_fetch_row($sql)) {
        $mentions[intval($row[0])][] = intval($row[1]);
    }
    return $mentions;
}

/** SQL for "a note on this ticket mentions $user_id" - tickets.ticket_id in the query. */
function ticketMentionedSql($user_id) {
    return "EXISTS (SELECT 1 FROM ticket_reply_mentions JOIN ticket_replies ON ticket_reply_id = mention_reply_id
        WHERE ticket_reply_ticket_id = tickets.ticket_id AND mention_user_id = " . intval($user_id) . " AND ticket_reply_archived_at IS NULL)";
}

/** Where a note is - the ticket, scrolled to it. For the agent side; $absolute for an email. */
function ticketMentionUrl(array $row, $reply_id, $absolute = false) {
    return ($absolute ? ticketAgentUrl($row) : '/agent/' . ticketUrl($row['ticket_id'], $row['ticket_client_id'] ?? 0)) . '#reply-' . intval($reply_id);
}

/**
 * Tell the agents a note mentions - in ITFlow, and by email from the ticket
 * mailbox when mail is set up - but not whoever wrote it. $row is the ticket
 * (ticketRow() or the handler's), $html the note as saved. Who was told -
 * user_id => name, raw.
 */
function ticketMentionsTell(array $row, $reply_id, $html, array $user_ids) {
    global $mysqli, $session_user_id, $session_name;
    $told = [];
    $agents = ticketMentionAgents($row['ticket_client_id'] ?? 0);
    $by = stripslashes((string) $session_name);
    $reference = ticketReference($row);
    $client_id = intval($row['ticket_client_id'] ?? 0);
    $action = escapeSql(ticketMentionUrl($row, $reply_id));
    foreach (array_unique(array_map('intval', $user_ids)) as $user_id) {
        if ($user_id === intval($session_user_id) || !isset($agents[$user_id])) {
            continue;
        }
        $text = escapeSql("$by mentioned you in an internal note on ticket $reference - " . $row['ticket_subject']);
        mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$text', notification_action = '$action',
            notification_client_id = $client_id, notification_entity_id = " . intval($row['ticket_id']) . ", notification_user_id = $user_id");
        ticketMailMentioned($row['ticket_id'], $reply_id, $html, $user_id, $by);
        $told[$user_id] = $agents[$user_id]['name'];
    }
    return $told;
}

/** Email an agent a note mentions them - the note, and the ticket. $by raw. How many went (0 or 1). */
function ticketMailMentioned($ticket_id, $reply_id, $html, $user_id, $by) {
    global $config_app_name;
    $row = ticketRow($ticket_id);
    $agent = $row ? ticketMailAgent($user_id) : null;
    if (!$agent) {
        return 0;
    }
    $agent['footnote'] = 'You get this because ' . ($by !== '' ? $by : 'someone') . ' mentioned you in an internal note on ' . ticketReference($row) . '.';
    return ticketMailAgents([$agent], ($config_app_name ?: 'ITFlow') . " - Mentioned on ticket [" . ticketReference($row) . "] " . $row['ticket_subject'],
        ticketAgentEmailContent($row, 'mentioned', ['by' => $by, 'message' => (string) $html, 'reply_id' => intval($reply_id)]));
}

/** "Jordan Lee", "Jordan Lee and Sam Rivera", "Jordan Lee, Sam Rivera and Priya Shah". */
function ticketMentionNames(array $names) {
    $names = array_values($names);
    $last = array_pop($names);
    return $names ? implode(', ', $names) . ' and ' . $last : (string) $last;
}
