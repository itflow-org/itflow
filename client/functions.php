<?php
/*
 * Client Portal
 * Functions
 */

/*
 * Verifies a contact has access to a particular ticket ID, and that the ticket is in the correct state (open/closed) to perform an action
 */
function verifyContactTicketAccess($requested_ticket_id, $expected_ticket_state) {

    // Access the global variables
    global $mysqli, $session_contact_id, $session_contact_primary, $session_contact_is_technical_contact, $session_client_id;

    // Setup
    if ($expected_ticket_state == "Closed") {
        // Closed tickets
        $ticket_state_snippet = "ticket_status = 5";
    } elseif ($expected_ticket_state == "Any") {
        // Open or closed - reading, such as an attachment
        $ticket_state_snippet = "1 = 1";
    } else {
        // Open (working/hold) tickets
        $ticket_state_snippet = "ticket_status != 5";
    }

    // Verify the contact has access to the provided ticket ID
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = $requested_ticket_id AND $ticket_state_snippet AND ticket_client_id = $session_client_id LIMIT 1"));
    if ($row) {
        $ticket_id = $row['ticket_id'];

        if (intval($ticket_id) && ($session_contact_id == $row['ticket_contact_id'] || $session_contact_primary == 1 || $session_contact_is_technical_contact)) {
            // Client is ticket owner, primary contact, or a technical contact
            return true;
        }
    }

    // Client is NOT ticket owner or primary/tech contact
    return false;

}

/*
 * Portal access control - single source of truth for what a logged-in contact can do.
 * Primary contacts have full access; others are gated by their billing / technical flags.
 * Capabilities are named by area so the rule for one can change without touching callers.
 */
function contactCan($capability) {
    global $session_contact_primary, $session_contact_is_billing_contact, $session_contact_is_technical_contact;

    // Primary contacts can do everything in the portal
    if ($session_contact_primary == 1) {
        return true;
    }

    switch ($capability) {
        case 'accounting':   // invoices, quotes, recurring invoices, saved payment methods
            return (bool) $session_contact_is_billing_contact;

        case 'itdoc':        // assets, certificates, domains, documents, files
        case 'contacts':     // view / manage contacts
            return (bool) $session_contact_is_technical_contact;

        default:             // unknown capability -> deny (fail closed)
            return false;
    }
}

/*
 * Enforce a capability at the top of a page or handler - bounce the contact out if they lack it.
 */
function enforceContactCan($capability) {
    if (!contactCan($capability)) {
        redirect("post.php?logout");
    }
}

/*
 * Confirms the person at the keyboard is the account holder, before a change
 * that would let someone who hijacked a session take the account over or
 * defeat phone verification.
 *
 * Returns true for SSO contacts without checking anything: there is no local
 * password to compare against, and the identity provider has already done this
 * work. Gating them on a password they do not have would just lock them out.
 */
function portalReauthenticate($current_password) {
    global $mysqli, $session_user_id;

    if (($_SESSION['login_method'] ?? 'local') !== 'local') {
        return true;
    }

    if (empty($current_password)) {
        return false;
    }

    $sql = mysqli_query($mysqli, "SELECT user_password FROM users WHERE user_id = $session_user_id LIMIT 1");
    $row = mysqli_fetch_assoc($sql);

    if (!$row || empty($row['user_password'])) {
        return false;
    }

    return password_verify($current_password, $row['user_password']);
}

/*
 * A timestamp a person can read, in the company's configured date and time
 * format rather than the raw DATETIME the database hands back.
 *
 * Today and yesterday are named instead of dated, because on an activity list
 * the recent rows are the ones being scanned and "Today at 4:12 PM" answers
 * "was that just now?" faster than a date does. Anything older gets the full
 * date, since by then the date is the useful part.
 *
 * Returns HTML-escaped output - callers print it directly.
 */
function portalDateTime($datetime) {
    global $config_date_format, $config_time_format;

    if (empty($datetime)) {
        return '';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return escapeHtml($datetime);
    }

    $time = date($config_time_format, $timestamp);
    $day = date('Y-m-d', $timestamp);

    if ($day === date('Y-m-d')) {
        return escapeHtml("Today at $time");
    }

    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return escapeHtml("Yesterday at $time");
    }

    return escapeHtml(date($config_date_format, $timestamp) . " at $time");
}

/*
 * The empty state for the portal's list pages - the same one the agent lists
 * use (emptyStateHtml()), so an empty list reads as empty rather than broken.
 *
 * Pass 'success' where empty is genuinely good news (nothing owed, nothing
 * unpaid) and it shows a tick.
 */
function portalEmptyState($message, $icon = 'fa-inbox', $type = 'secondary') {
    return emptyStateHtml([
        'title' => rtrim((string) $message, '.'),
        'icon' => $type === 'success' ? 'fa-check' : $icon,
        'filtered' => false,
    ]);
}

/*
 * The portal's sections, in menu order, as this contact may see them - the top
 * bar, the phone menu and the tab bar all draw from this. Each has a label, an
 * icon, the pages that light it up, and an href or children.
 */
function portalNav() {
    global $mysqli, $config_module_enable_accounting, $config_module_enable_itdoc;

    $nav = [
        'home' => ['label' => 'Home', 'icon' => 'fa-home', 'href' => '/client/index.php', 'pages' => ['index.php']],
        'tickets' => ['label' => 'Tickets', 'icon' => 'fa-life-ring', 'href' => '/client/tickets.php', 'pages' => ['tickets.php', 'ticket.php', 'ticket_add.php', 'ticket_view_all.php']],
    ];

    if (contactCan('accounting') && $config_module_enable_accounting == 1) {
        $children = [
            ['label' => 'Invoices', 'icon' => 'fa-file-invoice-dollar', 'href' => '/client/invoices.php', 'pages' => ['invoices.php']],
            ['label' => 'Pay invoices', 'icon' => 'fa-hand-holding-usd', 'href' => '/client/unpaid_invoices.php', 'pages' => ['unpaid_invoices.php']],
            ['label' => 'Quotes', 'icon' => 'fa-comment-dollar', 'href' => '/client/quotes.php', 'pages' => ['quotes.php']],
            ['label' => 'Recurring invoices', 'icon' => 'fa-redo-alt', 'href' => '/client/recurring_invoices.php', 'pages' => ['recurring_invoices.php']],
            ['label' => 'Statement', 'icon' => 'fa-file-alt', 'href' => '/client/statement.php', 'pages' => ['statement.php']],
            ['label' => 'Saved cards', 'icon' => 'fa-credit-card', 'href' => '/client/saved_payment_methods.php', 'pages' => ['saved_payment_methods.php']],
        ];
        $nav['billing'] = ['label' => 'Billing', 'icon' => 'fa-file-invoice-dollar', 'children' => $children, 'pages' => array_merge(...array_column($children, 'pages'))];
    }

    if ($config_module_enable_itdoc && contactCan('itdoc')) {
        $children = [
            ['label' => 'Assets', 'icon' => 'fa-desktop', 'href' => '/client/assets.php', 'pages' => ['assets.php']],
            ['label' => 'Documents', 'icon' => 'fa-folder-open', 'href' => '/client/documents.php', 'pages' => ['documents.php', 'document.php', 'file.php']],
            ['label' => 'Domains', 'icon' => 'fa-globe', 'href' => '/client/domains.php', 'pages' => ['domains.php']],
            ['label' => 'Certificates', 'icon' => 'fa-lock', 'href' => '/client/certificates.php', 'pages' => ['certificates.php']],
            ['label' => 'Contacts', 'icon' => 'fa-address-book', 'href' => '/client/contacts.php', 'pages' => ['contacts.php', 'contact_add.php', 'contact_edit.php']],
        ];
        $nav['it'] = ['label' => 'Your IT', 'icon' => 'fa-laptop', 'children' => $children, 'pages' => array_merge(...array_column($children, 'pages'))];
    }

    // The links an admin set to show in the portal (Admin > Custom Links)
    $sql = mysqli_query($mysqli, "SELECT custom_link_id, custom_link_icon, custom_link_name, custom_link_new_tab, custom_link_uri FROM custom_links
        WHERE custom_link_location = 3 AND custom_link_archived_at IS NULL ORDER BY custom_link_order ASC, custom_link_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $icon = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $row['custom_link_icon']));
        $nav['link-' . intval($row['custom_link_id'])] = [
            'label' => (string) $row['custom_link_name'],
            'icon_class' => 'fas fa-fw fa-' . ($icon !== '' ? $icon : 'external-link-alt'),
            'href' => (string) $row['custom_link_uri'],
            'new_tab' => intval($row['custom_link_new_tab']) === 1,
            'pages' => [basename((string) $row['custom_link_uri'])],
        ];
    }

    return $nav;
}

/** A round avatar - the photo when there is one, else initials on a tone picked from the name. */
function portalAvatarHtml($name, $photo_url = '', $size = '') {
    $class = 'itflow-portal-avatar' . ($size !== '' ? " itflow-portal-avatar-$size" : '');
    if ($photo_url !== '') {
        return '<img class="' . $class . '" src="' . escapeHtml($photo_url) . '" alt="">';
    }
    $initials = personInitials((string) $name) ?: '?';
    $tone = 1 + (abs(crc32((string) $name)) % 6);
    return '<span class="' . $class . ' itflow-portal-avatar-tone-' . $tone . '" aria-hidden="true">' . escapeHtml($initials) . '</span>';
}

/*
 * ---------- tickets ----------
 *
 * A ticket as the client sees it. The statuses agents work with (New, Open,
 * On Hold and any of their own) mean little to a client, so the portal says
 * where it stands for them: received, being worked on, waiting on them, on
 * hold, resolved for them to confirm, or closed.
 */

/** Whether this contact sees every ticket of their company, not only their own - primary and technical contacts. */
function portalSeesAllTickets() {
    global $session_contact_primary, $session_contact_is_technical_contact;
    return $session_contact_primary == 1 || $session_contact_is_technical_contact;
}

/** SQL: the tickets this contact may open - their company's, and only their own unless they see them all. */
function portalTicketScopeSql($mine_only = false) {
    global $session_client_id, $session_contact_id;
    $sql = "tickets.ticket_client_id = " . intval($session_client_id);
    if ($mine_only || !portalSeesAllTickets()) {
        $sql .= " AND tickets.ticket_contact_id = " . intval($session_contact_id);
    }
    return $sql;
}

/**
 * Where a ticket stands for the client - key, label, icon and the badge class.
 * From a row with ticket_status, ticket_resolved_at, ticket_closed_at,
 * ticket_last_spoke (ticketLastSpokeSql()) and ticket_status_pauses_sla.
 */
function portalTicketState($row) {
    if (!empty($row['ticket_closed_at'])) {
        return ['key' => 'closed', 'label' => 'Closed', 'icon' => 'fa-check-circle', 'class' => 'itflow-portal-state-closed'];
    }
    if (!empty($row['ticket_resolved_at'])) {
        return ['key' => 'resolved', 'label' => 'Resolved', 'icon' => 'fa-check', 'class' => 'itflow-portal-state-resolved'];
    }
    if (($row['ticket_last_spoke'] ?? null) === 'Public') {
        return ['key' => 'you', 'label' => 'Waiting on you', 'icon' => 'fa-reply', 'class' => 'itflow-portal-state-you'];
    }
    if (intval($row['ticket_status_pauses_sla'] ?? 0) === 1) {
        return ['key' => 'hold', 'label' => $row['ticket_status_name'] ?: 'On hold', 'icon' => 'fa-pause', 'class' => 'itflow-portal-state-hold'];
    }
    if (intval($row['ticket_status'] ?? 0) === 1 && empty($row['ticket_last_spoke'])) {
        return ['key' => 'new', 'label' => 'Received', 'icon' => 'fa-inbox', 'class' => 'itflow-portal-state-new'];
    }
    return ['key' => 'progress', 'label' => 'In progress', 'icon' => 'fa-tools', 'class' => 'itflow-portal-state-progress'];
}

/** The state as a badge. */
function portalTicketStateHtml($row) {
    $state = portalTicketState($row);
    return '<span class="itflow-portal-state ' . $state['class'] . '"><i class="fas fa-fw ' . $state['icon'] . '"></i>' . escapeHtml($state['label']) . '</span>';
}

/** The ticket list's tabs - each with its label, icon and SQL. Needs tickets joined to ticket_statuses. */
function portalTicketTabs() {
    $spoke = ticketLastSpokeSql();
    return [
        'open' => ['label' => 'Open', 'icon' => 'fa-folder-open', 'where' => TICKET_OPEN_SQL],
        'you' => ['label' => 'Waiting on you', 'icon' => 'fa-reply', 'where' => TICKET_OPEN_SQL . " AND $spoke = 'Public'"],
        'resolved' => ['label' => 'Resolved', 'icon' => 'fa-check', 'where' => 'tickets.ticket_resolved_at IS NOT NULL AND tickets.ticket_closed_at IS NULL'],
        'closed' => ['label' => 'Closed', 'icon' => 'fa-check-circle', 'where' => 'tickets.ticket_closed_at IS NOT NULL'],
        'all' => ['label' => 'All', 'icon' => 'fa-list', 'where' => '1 = 1'],
    ];
}

/** The columns a ticket list or page needs for portalTicketState(), with the ticket's own. */
function portalTicketColumnsSql() {
    return "tickets.ticket_id, tickets.ticket_prefix, tickets.ticket_number, tickets.ticket_subject, tickets.ticket_status, tickets.ticket_priority,
        tickets.ticket_created_at, tickets.ticket_updated_at, tickets.ticket_resolved_at, tickets.ticket_closed_at, tickets.ticket_contact_id,
        ticket_statuses.ticket_status_name, ticket_statuses.ticket_status_pauses_sla, " . ticketLastSpokeSql() . " AS ticket_last_spoke";
}

/** Whether what the editor sent says nothing - only tags, spaces and non-breaking spaces, and no picture. */
function portalHtmlIsEmpty($html) {
    $html = (string) $html;
    if (stripos($html, '<img') !== false) {
        return false;
    }
    return preg_replace('/[\s\x{00A0}]+/u', '', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) === '';
}

/** The reply lines the portal writes itself when a contact resolves, reopens or closes - shown as a line in the conversation, not a message. */
function portalTicketEventText($html) {
    $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));
    return preg_match('/^Ticket (resolved|reopened|closed) by .{1,200}\.$/', $text) ? $text : '';
}

