<?php

/*
 * ITFlow - related tickets: tickets about one thing - the same outage at several clients, a
 * problem that keeps coming back - linked so each shows the others, and one update or resolve
 * can go to them all. The Related tickets card on agent/ticket.php, its Link picker
 * (functions/link_picker.php) and Link together on the tickets list's Bulk Action
 * (agent/post/ticket_link.php).
 *
 * ticket_links (database 4.0.8) puts each linked ticket in a group: every ticket in a group is
 * related to every other. Linking a ticket to one in a group brings it into the whole group, and
 * linking two groups makes them one. A group's number is the lowest ticket id in it, kept so as
 * tickets join and leave - so one number never names two groups. A ticket deleted goes with its
 * row (the foreign key), and a group left with one ticket is no group.
 */

/** Whether the 4.0.8 update has run - the ticket_links table. */
function ticketLinksReady() {
    global $mysqli;
    static $ready = null;
    if ($ready === null) {
        if (defined('CURRENT_DATABASE_VERSION')) {
            $version = CURRENT_DATABASE_VERSION;
        } else {
            $result = mysqli_query($mysqli, "SELECT config_current_database_version FROM settings WHERE company_id = 1");
            $version = $result && ($row = mysqli_fetch_row($result)) ? (string) $row[0] : '0';
        }
        $ready = version_compare($version, '4.0.8', '>=');
    }
    return $ready;
}

/** A ticket's group, or 0 when it is linked to nothing. */
function ticketLinkGroup($ticket_id) {
    global $mysqli;
    if (!ticketLinksReady()) {
        return 0;
    }
    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT link_group FROM ticket_links WHERE ticket_id = " . intval($ticket_id)));
    return $row ? intval($row[0]) : 0;
}

/** The tickets in a group - their ids, whoever can see them. */
function ticketLinkMembers($group) {
    global $mysqli;
    $group = intval($group);
    if (!$group) {
        return [];
    }
    $sql = mysqli_query($mysqli, "SELECT ticket_id FROM ticket_links WHERE link_group = $group ORDER BY ticket_id");
    return array_map('intval', array_column(mysqli_fetch_all($sql), 0));
}

/** Keep a group numbered by its lowest ticket - and drop it when one ticket is all that's left. */
function ticketLinkTidy($group) {
    global $mysqli;
    $members = ticketLinkMembers($group);
    if (count($members) < 2) {
        mysqli_query($mysqli, "DELETE FROM ticket_links WHERE link_group = " . intval($group));
        return;
    }
    if ($members[0] !== intval($group)) {
        mysqli_query($mysqli, "UPDATE ticket_links SET link_group = {$members[0]} WHERE link_group = " . intval($group));
    }
}

/*
 * Link these tickets - and every ticket already related to any of them - into one group.
 * Each ticket newly related to others gets a history line naming them. Checks are the caller's:
 * the agent's level and their access to each ticket's client. Returns how many tickets were
 * related to one they weren't before - 0 when they all already were.
 */
function ticketLinkTogether(array $ticket_ids) {
    global $mysqli, $session_user_id, $session_name;
    $ticket_ids = array_values(array_unique(array_filter(array_map('intval', $ticket_ids))));
    if (!ticketLinksReady() || count($ticket_ids) < 2) {
        return 0;
    }
    $list = implode(',', $ticket_ids);

    // The groups they are in now, and everything in those groups
    $groups = array_map('intval', array_column(mysqli_fetch_all(mysqli_query($mysqli, "SELECT DISTINCT link_group FROM ticket_links WHERE ticket_id IN ($list)")), 0));
    $before = [];
    foreach ($groups as $group) {
        foreach (ticketLinkMembers($group) as $member) {
            $before[$member] = $group;
        }
    }
    $everyone = array_values(array_unique(array_merge(array_keys($before), $ticket_ids)));
    $target = min($everyone);

    if ($groups) {
        mysqli_query($mysqli, "UPDATE ticket_links SET link_group = $target WHERE link_group IN (" . implode(',', $groups) . ")");
    }
    $user_id = intval($session_user_id);
    foreach ($ticket_ids as $ticket_id) {
        if (!isset($before[$ticket_id])) {
            mysqli_query($mysqli, "INSERT IGNORE INTO ticket_links SET ticket_id = $ticket_id, link_group = $target, linked_by = $user_id");
        }
    }

    // A history line on each ticket for the ones it wasn't related to before
    $joined = 0;
    $references = [];
    $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number FROM tickets WHERE ticket_id IN (" . implode(',', $everyone) . ")");
    while ($row = mysqli_fetch_assoc($sql)) {
        $references[intval($row['ticket_id'])] = ticketReference($row);
    }
    foreach ($everyone as $ticket_id) {
        $new = array_filter($everyone, function ($other) use ($ticket_id, $before) {
            return $other !== $ticket_id && (!isset($before[$ticket_id], $before[$other]) || $before[$ticket_id] !== $before[$other]);
        });
        if (!$new) {
            continue;
        }
        $joined++;
        $names = array_map(function ($id) use ($references) {
            return $references[$id] ?? '';
        }, array_slice(array_values($new), 0, 10));
        logTicketHistory($ticket_id, "$session_name linked it with " . escapeSql(implode(', ', array_filter($names)) . (count($new) > 10 ? ' and ' . (count($new) - 10) . ' more' : '')));
    }
    return $joined;
}

/** Take a ticket out of its group, with a history line on it. True when it was in one. */
function ticketUnlink($ticket_id) {
    global $mysqli, $session_name;
    $ticket_id = intval($ticket_id);
    $group = ticketLinkGroup($ticket_id);
    if (!$group) {
        return false;
    }
    mysqli_query($mysqli, "DELETE FROM ticket_links WHERE ticket_id = $ticket_id");
    ticketLinkTidy($group);
    logTicketHistory($ticket_id, "$session_name took it out of its related tickets");
    return true;
}

/*
 * The tickets related to this one that the agent can see - open ones first - each with its
 * client, status, priority and who has it. Count those they can't see with ticketLinkHidden().
 */
function ticketRelated($ticket_id) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $group = ticketLinkGroup($ticket_id);
    if (!$group) {
        return [];
    }
    $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_client_id,
            ticket_resolved_at, ticket_closed_at, ticket_assigned_to, ticket_created_at, client_name, user_name, user_avatar,
            ticket_status_name, ticket_status_color
        FROM ticket_links
        JOIN tickets ON tickets.ticket_id = ticket_links.ticket_id
        LEFT JOIN clients ON client_id = ticket_client_id
        LEFT JOIN users ON user_id = ticket_assigned_to
        LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
        WHERE link_group = $group AND tickets.ticket_id <> $ticket_id" . clientScopeSql('ticket_client_id') . "
        ORDER BY (ticket_resolved_at IS NULL AND ticket_closed_at IS NULL) DESC, ticket_number DESC");
    $rows = [];
    while ($row = mysqli_fetch_assoc($sql)) {
        $row['is_open'] = ticketIsOpen($row);
        $rows[] = $row;
    }
    return $rows;
}

/** How many of a ticket's related tickets are for clients the agent can't open. */
function ticketLinkHidden($ticket_id, $shown) {
    $group = ticketLinkGroup($ticket_id);
    return $group ? max(0, count(ticketLinkMembers($group)) - 1 - intval($shown)) : 0;
}

/** For a list of tickets: how many others each is related to that the agent can see - [ticket id => count]. */
function ticketLinkCounts(array $ticket_ids) {
    global $mysqli;
    $ids = implode(',', array_filter(array_map('intval', $ticket_ids)));
    if ($ids === '' || !ticketLinksReady()) {
        return [];
    }
    $sql = mysqli_query($mysqli, "SELECT mine.ticket_id, COUNT(others.ticket_id) FROM ticket_links mine
        JOIN ticket_links others ON others.link_group = mine.link_group AND others.ticket_id <> mine.ticket_id
        JOIN tickets ON tickets.ticket_id = others.ticket_id
        WHERE mine.ticket_id IN ($ids)" . clientScopeSql('tickets.ticket_client_id') . "
        GROUP BY mine.ticket_id");
    $counts = [];
    while ($row = mysqli_fetch_row($sql)) {
        $counts[intval($row[0])] = intval($row[1]);
    }
    return $counts;
}

/*
 * The tickets this one could be linked to, for its Link picker - any client's the agent can open,
 * not this one or those already related to it: open ones, and those closed in the last 90 days
 * (a problem back again). Open first, then newest; at most 1000.
 */
function ticketLinkCandidates($ticket_id) {
    global $mysqli;
    $ticket_id = intval($ticket_id);
    $exclude = array_merge([$ticket_id], ticketLinkMembers(ticketLinkGroup($ticket_id)));
    $sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_resolved_at, ticket_closed_at,
            client_name, ticket_status_name, ticket_status_color
        FROM tickets
        LEFT JOIN clients ON client_id = ticket_client_id
        LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
        WHERE tickets.ticket_id NOT IN (" . implode(',', $exclude) . ") AND ticket_archived_at IS NULL
        AND (ticket_closed_at IS NULL OR ticket_closed_at > NOW() - INTERVAL 90 DAY)" . clientScopeSql('ticket_client_id') . "
        ORDER BY (ticket_resolved_at IS NULL AND ticket_closed_at IS NULL) DESC, tickets.ticket_id DESC
        LIMIT 1000");
    return mysqli_fetch_all($sql, MYSQLI_ASSOC);
}

/** A merged ticket's related tickets go to the ticket it went into (ticketMergeInto()). */
function ticketLinkCarry($from_id, $to_id) {
    global $mysqli;
    $from_id = intval($from_id);
    $group = ticketLinkGroup($from_id);
    if (!$group) {
        return;
    }
    ticketLinkTogether([$to_id, $from_id]);
    $group = ticketLinkGroup($from_id);
    mysqli_query($mysqli, "DELETE FROM ticket_links WHERE ticket_id = $from_id");
    ticketLinkTidy($group);
}
