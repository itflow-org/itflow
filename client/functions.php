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
    global $session_contact_primary, $session_contact_is_billing_contact, $session_contact_is_technical_contact, $portal_view_as;

    // An agent viewing as the contact sees billing only when their own role may (portalViewAsAccess())
    if ($portal_view_as && $capability === 'accounting' && empty($portal_view_as['billing'])) {
        return false;
    }

    // Primary contacts can do everything in the portal
    if ($session_contact_primary == 1) {
        return true;
    }

    switch ($capability) {
        case 'accounting':   // invoices, quotes, recurring invoices, saved payment methods
            return (bool) $session_contact_is_billing_contact;

        case 'itdoc':        // assets, certificates, domains, documents, files
        case 'contacts':     // see the company's contacts
            return (bool) $session_contact_is_technical_contact;

        case 'contacts_manage': // add and change contacts and their portal access - the primary contact's alone
            return false;

        default:             // unknown capability -> deny (fail closed)
            return false;
    }
}

/*
 * Enforce a capability at the top of a page or handler - a contact without it
 * is sent home with a note, rather than signed out for following a link.
 */
function enforceContactCan($capability) {
    if (!contactCan($capability)) {
        flashAlert("That part of the portal isn't open to you", 'error');
        redirect("/client/index.php");
    }
}

/** Whether an agent is looking at the portal as this contact (View as contact) - nothing is changed or recorded then. */
function portalViewingAs() {
    global $portal_view_as;
    return !empty($portal_view_as);
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
    global $mysqli, $session_user_id, $session_auth_method;

    if ($session_auth_method !== 'local') {
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
        $cards = billingStripeProvider() !== null;
        $children = [
            ['label' => 'Invoices', 'icon' => 'fa-file-invoice-dollar', 'href' => '/client/invoices.php', 'pages' => ['invoices.php', 'invoice.php']],
        ];
        if ($cards) {
            $children[] = ['label' => 'Pay invoices', 'icon' => 'fa-hand-holding-usd', 'href' => '/client/unpaid_invoices.php', 'pages' => ['unpaid_invoices.php', 'pay.php']];
        }
        $children[] = ['label' => 'Quotes', 'icon' => 'fa-comment-dollar', 'href' => '/client/quotes.php', 'pages' => ['quotes.php', 'quote.php']];
        $children[] = ['label' => 'Recurring invoices', 'icon' => 'fa-redo-alt', 'href' => '/client/recurring_invoices.php', 'pages' => ['recurring_invoices.php', 'recurring_invoice.php']];
        $children[] = ['label' => 'Payments', 'icon' => 'fa-receipt', 'href' => '/client/payments.php', 'pages' => ['payments.php', 'receipt.php']];
        $children[] = ['label' => 'Statement', 'icon' => 'fa-file-alt', 'href' => '/client/statement.php', 'pages' => ['statement.php']];
        if ($cards) {
            $children[] = ['label' => 'Cards & autopay', 'icon' => 'fa-credit-card', 'href' => '/client/saved_payment_methods.php', 'pages' => ['saved_payment_methods.php']];
        }
        $nav['billing'] = ['label' => 'Billing', 'icon' => 'fa-file-invoice-dollar', 'children' => $children, 'pages' => array_merge(...array_column($children, 'pages'))];
    }

    if ($config_module_enable_itdoc && contactCan('itdoc')) {
        $children = [
            ['label' => 'Devices', 'icon' => 'fa-desktop', 'href' => '/client/assets.php', 'pages' => ['assets.php', 'asset.php']],
            ['label' => 'Licenses', 'icon' => 'fa-cube', 'href' => '/client/licenses.php', 'pages' => ['licenses.php']],
            ['label' => 'Domains & certificates', 'icon' => 'fa-globe', 'href' => '/client/domains.php', 'pages' => ['domains.php', 'certificates.php']],
            ['label' => 'Documents', 'icon' => 'fa-folder-open', 'href' => '/client/documents.php', 'pages' => ['documents.php', 'document.php', 'document_add.php', 'file.php']],
        ];
        if (contactCan('contacts')) {
            $children[] = ['label' => 'Contacts', 'icon' => 'fa-address-book', 'href' => '/client/contacts.php', 'pages' => ['contacts.php', 'contact.php', 'contact_add.php', 'contact_edit.php']];
        }
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

/*
 * ---------- billing ----------
 *
 * Invoices, quotes, recurring invoices, payments and the statement, for
 * primary and billing contacts. Every query is keyed to the session's client;
 * paying by card is functions/billing_pay.php.
 */

/** Billing pages and handlers: a primary or billing contact, with the accounting module on - anyone else is sent away. */
function portalBillingGate() {
    global $config_module_enable_accounting;
    enforceContactCan('accounting');
    if ($config_module_enable_accounting != 1) {
        redirect('/client/index.php');
    }
}

/** A badge from a state - label, icon and class (portalInvoiceState(), portalQuoteState()). */
function portalStateBadge(array $state) {
    return '<span class="itflow-portal-state ' . $state['class'] . '"><i class="fas fa-fw ' . $state['icon'] . '"></i>' . escapeHtml($state['label']) . '</span>';
}

/**
 * Where an invoice stands for the client: invoiceState() (state, balance,
 * paid, days) with a label, icon and class - paid, cancelled, overdue, or
 * when it is due.
 */
function portalInvoiceState(array $row, $paid) {
    $state = invoiceState($row, $paid);
    $days = $state['days'];
    switch ($state['state']) {
        case 'paid':
            return $state + ['label' => 'Paid', 'icon' => 'fa-check', 'class' => 'itflow-portal-state-resolved'];
        case 'cancelled':
            return $state + ['label' => 'Cancelled', 'icon' => 'fa-ban', 'class' => 'itflow-portal-state-closed'];
        case 'nonbillable':
            return $state + ['label' => 'No charge', 'icon' => 'fa-minus-circle', 'class' => 'itflow-portal-state-closed'];
        case 'draft':
            return $state + ['label' => 'Draft', 'icon' => 'fa-pencil-alt', 'class' => 'itflow-portal-state-closed'];
        case 'overdue':
            return $state + ['label' => -$days . ' ' . (-$days === 1 ? 'day' : 'days') . ' overdue', 'icon' => 'fa-exclamation-circle', 'class' => 'itflow-portal-state-late'];
    }
    if ($days === 0) {
        return $state + ['label' => 'Due today', 'icon' => 'fa-clock', 'class' => 'itflow-portal-state-you'];
    }
    if ($days <= 7) {
        return $state + ['label' => "Due in $days " . ($days === 1 ? 'day' : 'days'), 'icon' => 'fa-clock', 'class' => 'itflow-portal-state-you'];
    }
    return $state + ['label' => 'Due ' . date('M j', strtotime((string) $row['invoice_due'])), 'icon' => 'fa-clock', 'class' => 'itflow-portal-state-new'];
}

/** Where a quote stands for the client - waiting for their answer, run out, accepted or declined - with a label, icon and class. */
function portalQuoteState(array $row) {
    $status = (string) $row['quote_status'];
    if (in_array($status, ['Accepted', 'Invoiced'], true)) {
        return ['key' => 'accepted', 'label' => 'Accepted', 'icon' => 'fa-thumbs-up', 'class' => 'itflow-portal-state-resolved'];
    }
    if ($status === 'Declined') {
        return ['key' => 'declined', 'label' => 'Declined', 'icon' => 'fa-thumbs-down', 'class' => 'itflow-portal-state-closed'];
    }
    if (quoteExpired($row)) {
        return ['key' => 'expired', 'label' => 'Expired', 'icon' => 'fa-hourglass-end', 'class' => 'itflow-portal-state-hold'];
    }
    return ['key' => 'waiting', 'label' => 'Waiting for you', 'icon' => 'fa-reply', 'class' => 'itflow-portal-state-you'];
}

/** SQL: quotes waiting for the client's answer - sent or viewed, and not run out. */
function portalQuoteWaitingSql() {
    return "quote_status IN ('Sent', 'Viewed') AND quote_expire >= CURDATE()";
}

/**
 * The contact opened an invoice or quote in the portal - marked viewed, in
 * its history and told to the agents (billingGuestViewed()), once each time
 * they sign in rather than on every look.
 */
function portalBillingViewed($type, array $row) {
    if (portalViewingAs()) {
        return;
    }
    global $contact;
    $key = $type . ':' . intval($row[billingType($type)['col'] . '_id']);
    if (!empty($_SESSION['portal_viewed'][$key])) {
        return;
    }
    $_SESSION['portal_viewed'][$key] = 1;
    billingGuestViewed($type, $row, (string) ($contact['contact_name'] ?? ''));
}

/** The ask-us link for a document - a new ticket with its subject filled in. */
function portalAskUrl($subject) {
    return 'ticket_add.php?' . http_build_query(['subject' => $subject]);
}

/*
 * ---------- Your IT ----------
 *
 * Devices, licenses, domains and certificates, documents and contacts, for
 * primary and technical contacts - and any contact's own devices. Every query
 * is keyed to the session's client.
 */

/** Your IT pages: a primary or technical contact, with the IT documentation module on - anyone else is sent away. */
function portalItGate() {
    global $config_module_enable_itdoc;
    enforceContactCan('itdoc');
    if (!$config_module_enable_itdoc) {
        redirect('/client/index.php');
    }
}

/** SQL: the devices this contact may open - all of their company's for a primary or technical contact, else the ones they use. */
function portalAssetScopeSql() {
    global $session_client_id, $session_contact_id, $config_module_enable_itdoc;
    $sql = "asset_client_id = " . intval($session_client_id) . " AND asset_archived_at IS NULL";
    if (!($config_module_enable_itdoc && contactCan('itdoc'))) {
        $sql .= " AND asset_contact_id = " . intval($session_contact_id);
    }
    return $sql;
}

/** The tabs on Devices - each kind with its label, icon and SQL on asset_type; Other is whatever the rest don't take. */
function portalAssetGroups() {
    $kinds = [
        'computers' => ['Computers', 'fa-desktop', ['Desktop', 'Laptop']],
        'servers' => ['Servers', 'fa-server', ['Server', 'Virtual Machine']],
        'network' => ['Network', 'fa-network-wired', ['Switch', 'Firewall/Router', 'Access Point']],
        'phones' => ['Phones & tablets', 'fa-mobile-alt', ['Phone', 'Mobile Phone', 'Tablet']],
    ];
    $groups = ['all' => ['label' => 'All', 'icon' => 'fa-list', 'where' => '1 = 1']];
    $taken = [];
    foreach ($kinds as $key => [$label, $icon, $types]) {
        $in = "'" . implode("', '", array_map('escapeSql', $types)) . "'";
        $groups[$key] = ['label' => $label, 'icon' => $icon, 'where' => "asset_type IN ($in)"];
        $taken[] = $in;
    }
    $groups['other'] = ['label' => 'Other', 'icon' => 'fa-print', 'where' => "asset_type NOT IN (" . implode(', ', $taken) . ")"];
    return $groups;
}

/** A grey square tile with an icon - devices, licenses, domains and documents in the portal's lists. */
function portalTile($icon, $size = '') {
    return '<span class="itflow-portal-tile' . ($size !== '' ? " itflow-portal-tile-$size" : '') . '" aria-hidden="true"><i class="' . escapeHtml(strpos($icon, ' ') === false ? "fas fa-fw $icon" : $icon) . '"></i></span>';
}

/** A renewal, warranty or expiry from softwareRenewal() and its kin, as a portal state - for portalStateBadge(). Null without a date. */
function portalRenewalState($renewal, $active_label = '') {
    if (!$renewal) {
        return null;
    }
    if ($renewal['state'] === 'expired') {
        return ['label' => 'Expired', 'icon' => 'fa-calendar-times', 'class' => 'itflow-portal-state-late'];
    }
    if ($renewal['state'] === 'soon') {
        return ['label' => $renewal['text'], 'icon' => 'fa-hourglass-half', 'class' => 'itflow-portal-state-you'];
    }
    return ['label' => $active_label !== '' ? $active_label : $renewal['text'], 'icon' => 'fa-calendar-check', 'class' => 'itflow-portal-state-resolved'];
}

/** A contact's roles in words - Primary contact, Billing, Technical - or [] for none. */
function portalContactRoles(array $row) {
    if (intval($row['contact_primary'] ?? 0)) {
        return ['Primary contact'];
    }
    $roles = [];
    if (intval($row['contact_billing'] ?? 0)) {
        $roles[] = 'Billing';
    }
    if (intval($row['contact_technical'] ?? 0)) {
        $roles[] = 'Technical';
    }
    return $roles;
}

/**
 * Whether a contact can sign in to the portal, and how: key none (never set
 * up), off (turned off), local (a password) or azure (Microsoft) - with a
 * label, icon and class for portalStateBadge(). From a row with user_id,
 * user_auth_method, user_status and user_archived_at (LEFT JOIN users).
 */
function portalContactAccess(array $row) {
    if (empty($row['user_id'])) {
        return ['key' => 'none', 'label' => 'No portal access', 'icon' => 'fa-minus-circle', 'class' => 'itflow-portal-state-closed'];
    }
    $method = (string) ($row['user_auth_method'] ?? '');
    if (!in_array($method, ['local', 'azure'], true) || intval($row['user_status'] ?? 0) !== 1 || !empty($row['user_archived_at'])) {
        return ['key' => 'off', 'label' => 'Portal access off', 'icon' => 'fa-ban', 'class' => 'itflow-portal-state-closed'];
    }
    return ['key' => $method, 'label' => $method === 'azure' ? 'Signs in with Microsoft' : 'Signs in with a password', 'icon' => 'fa-check', 'class' => 'itflow-portal-state-resolved'];
}

/** The ways a portal user can sign in here - a password, and Microsoft when it is set up. method => label. */
function portalSignInMethods() {
    global $config_azure_client_id;
    $methods = ['local' => 'With a password'];
    if (!empty($config_azure_client_id)) {
        $methods['azure'] = 'With their Microsoft 365 account';
    }
    return $methods;
}

/**
 * Give a contact of this client portal access and email them how to sign in -
 * a link to set their password, or for Microsoft a link to the sign-in page.
 * Makes their portal user when they have none, turns it back on when it was
 * off, and sends the invite again when it is on. $by is the primary contact
 * doing it. ['ok' => bool, 'error' => why not, 'emailed' => bool].
 */
function portalContactInvite($contact_id, $method, $by) {
    global $mysqli, $session_client_id;
    $contact_id = intval($contact_id);
    $client_id = intval($session_client_id);
    $contact = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email, contact_user_id, client_name, user_id, user_auth_method
        FROM contacts JOIN clients ON client_id = contact_client_id LEFT JOIN users ON user_id = contact_user_id
        WHERE contact_id = $contact_id AND contact_client_id = $client_id AND contact_archived_at IS NULL LIMIT 1"));
    if (!$contact) {
        return ['ok' => false, 'error' => "That person isn't here", 'emailed' => false];
    }
    if (!isset(portalSignInMethods()[$method])) {
        $method = 'local';
    }
    $name = (string) $contact['contact_name'];
    $email = trim((string) $contact['contact_email']);
    if (userCheckIdentity(['name' => $name, 'email' => $email], intval($contact['user_id']), $error) === null) {
        return ['ok' => false, 'error' => strip_tags($error), 'emailed' => false];
    }
    $name_sql = escapeSql($name);
    $email_sql = escapeSql($email);
    $method_sql = escapeSql($method);
    $user_id = intval($contact['user_id']);
    if ($user_id) {
        mysqli_query($mysqli, "UPDATE users SET user_name = '$name_sql', user_email = '$email_sql', user_auth_method = '$method_sql', user_status = 1, user_archived_at = NULL WHERE user_id = $user_id AND user_type = 2");
    } else {
        $hash = escapeSql(password_hash(randomString(), PASSWORD_DEFAULT));
        mysqli_query($mysqli, "INSERT INTO users SET user_name = '$name_sql', user_email = '$email_sql', user_password = '$hash', user_auth_method = '$method_sql', user_type = 2");
        $user_id = intval(mysqli_insert_id($mysqli));
        mysqli_query($mysqli, "UPDATE contacts SET contact_user_id = $user_id WHERE contact_id = $contact_id AND contact_client_id = $client_id");
    }

    // The email - a link to set their password, or for Microsoft the sign-in page (functions/portal.php)
    $emailed = portalMailInvite($user_id, $by);
    if (!$emailed && $method !== 'local') {
        portalPasswordLinkClear($user_id);
    }
    return ['ok' => true, 'error' => '', 'emailed' => $emailed];
}

/** Turn a contact's portal access off - they are signed out on their next click (check_login.php) and their reset link stops working. */
function portalContactAccessOff($contact_id) {
    global $mysqli, $session_client_id;
    $contact_id = intval($contact_id);
    $client_id = intval($session_client_id);
    $user_id = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT contact_user_id FROM contacts WHERE contact_id = $contact_id AND contact_client_id = $client_id"))[0] ?? 0);
    if ($user_id) {
        mysqli_query($mysqli, "UPDATE users SET user_auth_method = '', user_password_reset_token = NULL, user_password_reset_token_expire = NULL WHERE user_id = $user_id AND user_type = 2");
    }
    return $user_id > 0;
}
