<?php
/*
 * A ticket as it opens from the link in a ticket email, without signing in -
 * for the contact, and for the watchers and colleagues the same emails go to.
 * The client portal's ticket page (client/ticket.php) in the guest layout:
 * where it stands, the conversation oldest first with the files that came
 * with each message, approvals the client owes, work booked with a calendar
 * file, what to do next, and the details and how to reach you down the side.
 *
 * Anyone holding the link can open it, so it shows what the emails already
 * do - never an internal note or its files - and takes no replies: those
 * come by email or from the portal, where who wrote them is known. Close,
 * reopen and the rating post to guest_post.php; the files come from
 * guest_ticket_attachment.php.
 *
 * The key is in the address, so the browser is told not to hand the address
 * on to a site linked from a reply (Referrer-Policy), and not to let another
 * site frame the page and its buttons.
 */

header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");
header('X-Robots-Tag: noindex, nofollow');

require_once "includes/inc_all_guest.php";

// The portal's ticket helpers - portalTicketState(), portalAvatarHtml(), portalDateTime() and the rest
require_once $_SERVER['DOCUMENT_ROOT'] . '/client/functions.php';

//Initialize the HTML Purifier to prevent XSS
require "../libs/htmlpurifier/HTMLPurifier.standalone.php";

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null); // Disable cache by setting a non-existent directory or an invalid one
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

$company = billingCompany();
$company_phone = (string) ($company['phone_display'] ?? '');
$company_tel = preg_replace('/[^0-9+]/', '', $company_phone);
$company_website = trim((string) ($company['company_website'] ?? ''));
$support_email = (string) ticketEmailFrom()['email'];

// The company's logo, or its initials and name, as the client portal's top bar shows them - so the page is plainly yours
$brand_html = $company['logo_url'] !== ''
    ? '<img src="' . escapeHtml($company['logo_url']) . '" alt="' . escapeHtml($session_company_name) . '">'
    : '<span class="itflow-brand-mark itflow-brand-mark-initials" aria-hidden="true">' . escapeHtml(clientInitials('', $session_company_name)) . '</span>'
        . '<span class="itflow-portal-brand-name">' . escapeHtml($session_company_name) . '</span>';

$found = ticketByGuestKey($_GET['ticket_id'] ?? 0, $_GET['url_key'] ?? '');
$ticket_id = $found ? intval($found['ticket_id']) : 0;

$ticket = $ticket_id ? mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . portalTicketColumnsSql() . ", tickets.ticket_details, tickets.ticket_feedback, tickets.ticket_url_key,
        tickets.ticket_client_id, tickets.ticket_assigned_to, tickets.ticket_schedule, tickets.ticket_onsite, tickets.ticket_first_response_at, tickets.ticket_response_due_at,
        user_name, user_avatar, category_name, contact_name, contact_photo, asset_name, asset_type,
        location_name, location_address, location_city, location_state, location_zip
    FROM tickets
    LEFT JOIN users ON ticket_assigned_to = user_id
    LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
    LEFT JOIN categories ON ticket_category = category_id
    LEFT JOIN contacts ON contact_id = tickets.ticket_contact_id
    LEFT JOIN assets ON asset_id = tickets.ticket_asset_id AND asset_client_id = tickets.ticket_client_id
    LEFT JOIN locations ON location_id = IF(tickets.ticket_location_id > 0, tickets.ticket_location_id, contacts.contact_location_id) AND location_client_id = tickets.ticket_client_id
    WHERE tickets.ticket_id = $ticket_id LIMIT 1")) : null;

if (!$ticket) {
    ?>
    <div class="d-flex align-items-center py-3"><span class="itflow-portal-brand"><?= $brand_html ?></span></div>
    <section class="itflow-portal-panel my-4">
        <?= emptyStateHtml(['title' => "This ticket link isn't working", 'filtered' => false, 'icon' => 'fa-life-ring',
            'text' => 'It may have been cut short when it was copied. Open it again from the email'
                . ($company_phone !== '' ? ', or call us on ' . escapeHtml($company_phone) : '') . '.']) ?>
    </section>
    <?php
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit;
}

$client_id = intval($ticket['ticket_client_id']);
$state = portalTicketState($ticket);
$number = ticketReference($ticket);
$resolved = !empty($ticket['ticket_resolved_at']);
$closed = !empty($ticket['ticket_closed_at']);
$open = !$resolved && !$closed;
$url_key = (string) $ticket['ticket_url_key'];
$query = "ticket_id=$ticket_id&amp;url_key=" . rawurlencode($url_key);
$page_title = escapeHtml("Ticket $number");

// Replying: by email when the ticket mailbox is read (Admin > Mail), with the number in the subject so it lands on
// this ticket - and in the portal, for a ticket that belongs to a client, when the portal is on
$reply_mailto = $config_ticket_email_parse === 1 && filter_var($support_email, FILTER_VALIDATE_EMAIL)
    ? "mailto:$support_email?subject=" . rawurlencode("Re: [$config_ticket_prefix" . intval($ticket['ticket_number']) . '] - ' . $ticket['ticket_subject'])
    : '';
$portal_url = $config_client_portal_enable === 1 && $client_id ? "/client/ticket.php?id=$ticket_id" : '';

// Tasks - how far along, and the ones waiting on the client's approval (approved from the emailed link, not here)
$tasks = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS total, COALESCE(SUM(task_completed_at IS NOT NULL), 0) AS done FROM tasks WHERE task_ticket_id = $ticket_id"));
$task_count = intval($tasks['total']);
$task_done = intval($tasks['done']);
$approvals = [];
if ($open) {
    $sql = mysqli_query($mysqli, "SELECT task_name, approval_type FROM tasks
        JOIN task_approvals ON task_id = approval_task_id
        WHERE task_ticket_id = $ticket_id AND task_completed_at IS NULL AND approval_scope = 'client' AND approval_status = 'pending' ORDER BY task_order ASC, task_id ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $approvals[] = $row;
    }
}

// Attachments, by the reply they came with (0 for the ticket itself) - never an internal note's, nor the email it was parsed from
$attachments = [];
$sql = mysqli_query($mysqli, "SELECT ticket_attachment_id, ticket_attachment_name, ticket_attachment_reply_id FROM ticket_attachments
    LEFT JOIN ticket_replies ON ticket_reply_id = ticket_attachment_reply_id
    WHERE ticket_attachment_ticket_id = $ticket_id AND ticket_attachment_name != 'Original-parsed-email.eml'
    AND (ticket_attachment_reply_id IS NULL OR (ticket_reply_type != 'Internal' AND ticket_reply_archived_at IS NULL))
    ORDER BY ticket_attachment_id ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $attachments[intval($row['ticket_attachment_reply_id'])][] = $row;
}

// The conversation, oldest first
$replies = mysqli_query($mysqli, "SELECT ticket_reply_id, ticket_reply, ticket_reply_type, ticket_reply_created_at,
        contact_name, contact_photo, user_id, user_name, user_avatar
    FROM ticket_replies
    LEFT JOIN users ON ticket_reply_type != 'Client' AND ticket_reply_by = user_id
    LEFT JOIN contacts ON ticket_reply_type = 'Client' AND ticket_reply_by = contact_id
    WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL AND ticket_reply_type != 'Internal'
    ORDER BY ticket_reply_id ASC");

/** One message - the client's side on the right, yours on the left, each with its files. */
function guestTicketMessageHtml($name, $avatar_url, $when, $body_html, array $files, $client_side, $file_query) {
    $html = '<div class="itflow-portal-msg' . ($client_side ? ' itflow-portal-msg-mine' : '') . '">'
        . portalAvatarHtml($name, $avatar_url, 'lg')
        . '<div class="itflow-portal-msg-body">'
        . '<div class="itflow-portal-msg-head"><strong>' . escapeHtml($name) . '</strong>'
        . (!$client_side ? '<span>' . escapeHtml($GLOBALS['session_company_name']) . '</span>' : '')
        . '<span title="' . escapeHtml($when) . '">' . portalDateTime($when) . '</span></div>'
        . '<div class="itflow-portal-msg-bubble prettyContent">' . $body_html;
    if ($files) {
        $html .= '<div class="itflow-portal-files">';
        foreach ($files as $file) {
            $href = 'guest_ticket_attachment.php?' . $file_query . '&amp;attachment_id=' . intval($file['ticket_attachment_id']);
            $html .= '<a class="itflow-portal-file-chip" href="' . $href . '&amp;action=view" target="_blank" rel="noopener" title="Open">'
                . '<i class="fas fa-paperclip"></i><span class="text-truncate">' . escapeHtml($file['ticket_attachment_name']) . '</span></a>'
                . '<a class="itflow-portal-file-chip" href="' . $href . '" title="Download ' . escapeHtml($file['ticket_attachment_name']) . '" aria-label="Download"><i class="fas fa-download"></i></a>';
        }
        $html .= '</div>';
    }
    return $html . '</div></div></div>';
}

// Where it has got to: received, in progress, resolved, closed
$steps = ['Received', 'In progress', 'Resolved', 'Closed'];
$at = $closed ? 3 : ($resolved ? 2 : ($state['key'] === 'new' ? 0 : 1));

// When we will answer - the SLA's response time, until an agent first replies, while it is still ahead
$reply_by = $open && !in_array($state['key'], ['you', 'hold'], true) && empty($ticket['ticket_first_response_at'])
    && !empty($ticket['ticket_response_due_at']) && strtotime($ticket['ticket_response_due_at']) > time()
    ? $ticket['ticket_response_due_at'] : '';

// Work booked from today on, while the ticket is open
$booked = $open && !empty($ticket['ticket_schedule']) && strtotime($ticket['ticket_schedule']) >= strtotime('today');
$booked_where = '';
if ($booked && !empty($ticket['ticket_onsite'])) {
    $address = formatAddress($ticket['location_address'] ?? '', $ticket['location_city'] ?? '', $ticket['location_state'] ?? '', $ticket['location_zip'] ?? '', '', ', ');
    $booked_where = trim(implode(' - ', array_filter([(string) ($ticket['location_name'] ?? ''), $address], 'strlen')));
}

// A resolved ticket closes by itself after Admin > Ticket's hours, as the resolved email says
$autoclose_hours = intval($config_ticket_autoclose_hours);
$autoclose_text = $autoclose_hours > 0
    ? ($autoclose_hours % 24 === 0 ? ($autoclose_hours / 24) . ' day' . ($autoclose_hours === 24 ? '' : 's') : "$autoclose_hours hours")
    : '';

$requester = (string) ($ticket['contact_name'] ?? '');
$requester_photo = $ticket['contact_photo'] && $client_id ? "/uploads/clients/$client_id/" . $ticket['contact_photo'] : '';
$agent_name = (string) ($ticket['user_name'] ?? '');
// Urgent and High: the phone, up front - as the new ticket email says
$urgent = $open && $company_phone !== '' && in_array($ticket['ticket_priority'], ['Urgent', 'High'], true);
?>

<div class="d-flex flex-wrap align-items-center gap-2 py-3">
    <span class="itflow-portal-brand"><?= $brand_html ?></span>
    <?php if ($portal_url) { ?>
        <a class="btn btn-sm btn-light border ms-auto" href="<?= escapeHtml($portal_url) ?>"><i class="fas fa-sign-in-alt me-2"></i>Client portal</a>
    <?php } ?>
</div>

<div class="itflow-portal-page-head">
    <div class="min-w-0">
        <h1><?= escapeHtml($ticket['ticket_subject']) ?></h1>
        <p>
            <?= escapeHtml($number) ?> · opened <?= portalDateTime($ticket['ticket_created_at']) ?>
            <?php if ($requester !== '') { ?>by <?= escapeHtml($requester) ?><?php } ?>
        </p>
    </div>
    <?= portalTicketStateHtml($ticket) ?>
</div>

<div class="row g-4 pb-4">
    <div class="col-lg-8">

        <section class="itflow-portal-panel itflow-portal-panel-body mb-4">
            <ol class="itflow-portal-steps">
                <?php foreach ($steps as $i => $step) { ?>
                    <li class="<?= $i < $at ? 'done' : ($i === $at ? 'current' : '') ?>"><?= $i === 1 && $state['key'] === 'you' ? 'Waiting on you' : ($i === 1 && $state['key'] === 'hold' ? escapeHtml($state['label']) : $step) ?></li>
                <?php } ?>
            </ol>
            <?php if ($reply_by !== '') { ?>
                <p class="text-center text-body-secondary small mt-3 mb-0"><i class="far fa-clock me-1"></i>We aim to reply by <?= portalDateTime($reply_by) ?></p>
            <?php } ?>
        </section>

        <?php if ($booked || $approvals) { ?>
            <section class="itflow-portal-panel mb-4">
                <ul class="itflow-portal-list">
                    <?php if ($booked) { ?>
                        <li class="itflow-portal-todo itflow-portal-todo-wrap">
                            <span class="itflow-portal-todo-icon is-info"><i class="fas fa-calendar-check"></i></span>
                            <span class="itflow-portal-todo-main">
                                <div>Booked for <?= escapeHtml(date('l, F j', strtotime($ticket['ticket_schedule'])) . ' at ' . date($config_time_format, strtotime($ticket['ticket_schedule']))) ?></div>
                                <div><?= !empty($ticket['ticket_onsite']) ? 'Onsite' . ($booked_where !== '' ? ' at ' . escapeHtml($booked_where) : '') : 'Remote - we will connect to you' ?></div>
                            </span>
                            <a class="btn btn-sm btn-light border" href="guest_post.php?ticket_calendar=<?= $ticket_id ?>&amp;url_key=<?= rawurlencode($url_key) ?>"><i class="far fa-calendar-plus me-1"></i>Add to calendar</a>
                        </li>
                    <?php } ?>
                    <?php foreach ($approvals as $approval) {
                        $approver = ['technical' => 'your technical contacts', 'billing' => 'your billing contacts'][$approval['approval_type']] ?? ($requester !== '' ? $requester : 'the ticket\'s contact'); ?>
                        <li class="itflow-portal-todo itflow-portal-todo-wrap">
                            <span class="itflow-portal-todo-icon"><i class="fas fa-clipboard-check"></i></span>
                            <span class="itflow-portal-todo-main">
                                <div>Waiting for approval: <?= escapeHtml($approval['task_name']) ?></div>
                                <div>We need a go-ahead before we do this - we have emailed <?= escapeHtml($approver) ?> a link to approve it</div>
                            </span>
                        </li>
                    <?php } ?>
                </ul>
            </section>
        <?php } ?>

        <div class="itflow-portal-thread mb-4">
            <?= guestTicketMessageHtml($requester ?: 'Request', $requester_photo, $ticket['ticket_created_at'], $purifier->purify($ticket['ticket_details']),
                $attachments[0] ?? [], true, $query) ?>

            <?php while ($reply = mysqli_fetch_assoc($replies)) {
                $client_reply = $reply['ticket_reply_type'] === 'Client';
                $event = $client_reply ? portalTicketEventText($reply['ticket_reply']) : '';
                if ($event !== '') { ?>
                    <div class="itflow-portal-event"><span><?= escapeHtml($event) ?> · <?= portalDateTime($reply['ticket_reply_created_at']) ?></span></div>
                    <?php continue;
                }
                if ($client_reply) {
                    $name = (string) ($reply['contact_name'] ?: 'Client');
                    $photo = $reply['contact_photo'] && $client_id ? "/uploads/clients/$client_id/" . $reply['contact_photo'] : '';
                } else {
                    $name = (string) ($reply['user_name'] ?: $session_company_name);
                    $photo = $reply['user_avatar'] ? '/uploads/users/' . intval($reply['user_id']) . '/' . $reply['user_avatar'] : '';
                }
                echo guestTicketMessageHtml($name, $photo, $reply['ticket_reply_created_at'], $purifier->purify($reply['ticket_reply']),
                    $attachments[intval($reply['ticket_reply_id'])] ?? [], $client_reply, $query);
            } ?>
        </div>

        <?php if ($open) {
            if ($reply_mailto || $portal_url || $company_phone !== '') { ?>

            <section class="itflow-portal-panel" id="reply">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-reply"></i><?= $state['key'] === 'you' ? 'We are waiting on you' : 'Need to add something?' ?></h2>
                </div>
                <div class="itflow-portal-panel-body">
                    <p class="mb-3">
                        <?php if ($state['key'] === 'you') { ?>
                            Our last message needs an answer before we can carry on.
                        <?php } ?>
                        <?php if ($reply_mailto) { ?>
                            Reply to any of our emails about this ticket - it goes straight onto it. Screenshots and files can go with it.
                        <?php } elseif ($portal_url) { ?>
                            Sign in to the client portal to reply - screenshots and files can go with it.
                        <?php } elseif ($company_phone !== '') { ?>
                            Give us a call on <?= escapeHtml($company_phone) ?>.
                        <?php } ?>
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($reply_mailto) { ?>
                            <a class="btn btn-primary" href="<?= escapeHtml($reply_mailto) ?>"><i class="fas fa-envelope me-2"></i>Reply by email</a>
                        <?php } ?>
                        <?php if ($portal_url) { ?>
                            <a class="btn <?= $reply_mailto ? 'btn-outline-primary' : 'btn-primary' ?>" href="<?= escapeHtml($portal_url) ?>"><i class="fas fa-sign-in-alt me-2"></i>Reply in the client portal</a>
                        <?php } ?>
                        <?php if ($urgent) { ?>
                            <a class="btn btn-outline-secondary" href="tel:<?= escapeHtml($company_tel) ?>"><i class="fas fa-phone me-2"></i>Call <?= escapeHtml($company_phone) ?></a>
                        <?php } ?>
                    </div>
                </div>
            </section>

        <?php }
        } elseif (!$closed) { ?>

            <section class="itflow-portal-panel">
                <?= emptyStateHtml(['title' => 'We think this is fixed', 'icon' => 'fa-check', 'filtered' => false,
                    'text' => 'Close it if everything is working' . ($autoclose_text !== '' ? ' - or leave it, and it closes by itself after ' . $autoclose_text : '')
                        . '. If it is not, reopen it and tell us what is still wrong.']) ?>
                <form action="guest_post.php" method="post" class="d-flex flex-wrap justify-content-center gap-2 pb-4 px-3">
                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                    <input type="hidden" name="url_key" value="<?= escapeHtml($url_key) ?>">
                    <button type="submit" class="btn btn-success confirm-link" name="close_ticket" value="1" data-confirm-title="Close this ticket?" data-confirm-text="Glad it is sorted." data-confirm-button="Close it" data-confirm-icon="question"><i class="fas fa-check-circle me-2"></i>Yes, close it</button>
                    <button type="submit" class="btn btn-outline-secondary confirm-link" name="reopen_ticket" value="1" data-confirm-title="Reopen this ticket?" data-confirm-text="We will pick it up again. Then reply to our email, or call us, and tell us what is still wrong." data-confirm-button="Reopen it" data-confirm-icon="question"><i class="fas fa-redo me-2"></i>No, reopen it</button>
                </form>
            </section>

        <?php } elseif (empty($ticket['ticket_feedback'])) { ?>

            <section class="itflow-portal-panel">
                <?= emptyStateHtml(['title' => 'How did we do?', 'text' => 'One click tells us how this one went.', 'icon' => 'fa-star', 'filtered' => false]) ?>
                <form action="guest_post.php" method="post" class="d-flex flex-wrap justify-content-center gap-2 pb-4 px-3">
                    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
                    <input type="hidden" name="url_key" value="<?= escapeHtml($url_key) ?>">
                    <button type="submit" class="btn btn-outline-success" name="add_ticket_feedback" value="Good"><i class="far fa-smile me-2"></i>Good</button>
                    <button type="submit" class="btn btn-outline-danger" name="add_ticket_feedback" value="Bad"><i class="far fa-frown me-2"></i>Not good</button>
                </form>
            </section>

        <?php } else { ?>

            <div class="itflow-portal-event"><span>Closed and rated <?= $ticket['ticket_feedback'] === 'Bad' ? 'not good' : 'good' ?> - thank you for the feedback.</span></div>
            <?php if ($ticket['ticket_feedback'] === 'Bad' && $company_phone !== '') { ?>
                <p class="text-center text-body-secondary mt-3 mb-0">Sorry this one didn't go the way it should have. Call us on <?= escapeHtml($company_phone) ?> and we will put it right.</p>
            <?php } ?>

        <?php } ?>

        <?php if ($closed && ($portal_url || $support_email !== '')) { ?>
            <p class="text-center text-body-secondary small mt-3 mb-0">
                Need more help with this? Replies to a closed ticket don't reach us - raise a new one<?= $portal_url ? ' in the <a href="/client/ticket_add.php">client portal</a>' : '' ?><?= $portal_url && $support_email !== '' ? ' or' : '' ?><?= $support_email !== '' ? ' by emailing <a href="mailto:' . escapeHtml($support_email) . '">' . escapeHtml($support_email) . '</a>' : '' ?>.
            </p>
        <?php } ?>

    </div>
    <div class="col-lg-4">
        <section class="itflow-portal-panel">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-info-circle"></i>Details</h2>
            </div>
            <dl class="itflow-portal-facts itflow-portal-panel-body py-1">
                <div><dt>Ticket</dt><dd><?= escapeHtml($number) ?></dd></div>
                <div><dt>Status</dt><dd><?= escapeHtml($state['label']) ?></dd></div>
                <div><dt>Priority</dt><dd><?= escapeHtml($ticket['ticket_priority']) ?></dd></div>
                <?php if (!empty($ticket['category_name'])) { ?>
                    <div><dt>Category</dt><dd><?= escapeHtml($ticket['category_name']) ?></dd></div>
                <?php } ?>
                <?php if ($requester !== '') { ?>
                    <div><dt>Raised by</dt><dd><?= escapeHtml($requester) ?></dd></div>
                <?php } ?>
                <?php if ($agent_name !== '' && !$closed) { ?>
                    <div>
                        <dt>Working on it</dt>
                        <dd class="d-flex align-items-center justify-content-end gap-2">
                            <?= portalAvatarHtml($agent_name, $ticket['user_avatar'] ? '/uploads/users/' . intval($ticket['ticket_assigned_to']) . '/' . $ticket['user_avatar'] : '', 'sm') ?>
                            <?= escapeHtml($agent_name) ?>
                        </dd>
                    </div>
                <?php } ?>
                <?php if (!empty($ticket['asset_name'])) { ?>
                    <div><dt>Device</dt><dd><i class="fas fa-<?= getAssetIcon($ticket['asset_type']) ?> me-1 text-body-secondary"></i><?= escapeHtml($ticket['asset_name']) ?></dd></div>
                <?php } ?>
                <?php if ($task_count && !$closed) { ?>
                    <div><dt>Tasks</dt><dd><?= $task_done ?> of <?= $task_count ?> done</dd></div>
                <?php } ?>
                <div><dt>Opened</dt><dd><?= portalDateTime($ticket['ticket_created_at']) ?></dd></div>
                <?php if ($closed) { ?>
                    <div><dt>Closed</dt><dd><?= portalDateTime($ticket['ticket_closed_at']) ?></dd></div>
                <?php } elseif ($resolved) { ?>
                    <div><dt>Resolved</dt><dd><?= portalDateTime($ticket['ticket_resolved_at']) ?></dd></div>
                <?php } elseif (!empty($ticket['ticket_updated_at'])) { ?>
                    <div><dt>Last update</dt><dd><?= portalDateTime($ticket['ticket_updated_at']) ?></dd></div>
                <?php } ?>
            </dl>
        </section>

        <?php
        // What the request asked, and the answers - the ones the client is asked, not the agents' own
        $request_answers = array_values(array_filter(ticketFillInsStored('ticket', $ticket_id), function ($answer) {
            return $answer['client'];
        }));
        if ($request_answers) { ?>
            <section class="itflow-portal-panel mt-4">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-clipboard-list"></i>Request details</h2>
                </div>
                <dl class="itflow-portal-facts itflow-portal-panel-body py-1">
                    <?php foreach ($request_answers as $answer) {
                        $answer_text = ticketFillInText($answer); ?>
                        <div><dt><?= escapeHtml($answer['name']) ?></dt><dd><?= $answer_text !== '' ? escapeHtml($answer_text) : '<span class="text-body-secondary">Not given</span>' ?></dd></div>
                    <?php } ?>
                </dl>
            </section>
        <?php } ?>

        <?php if ($company_phone !== '' || $support_email !== '' || $company_website !== '') { ?>
            <section class="itflow-portal-panel mt-4">
                <div class="itflow-portal-panel-head">
                    <h2><i class="fas fa-headset"></i>Contact us</h2>
                </div>
                <?php if ($urgent && $state['key'] !== 'you') { ?>
                    <div class="alert alert-warning small mx-3 mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>This ticket is marked <?= escapeHtml($ticket['ticket_priority']) ?>. If it is stopping work right now, call us rather than waiting for a reply.
                    </div>
                <?php } ?>
                <dl class="itflow-portal-facts itflow-portal-panel-body py-1">
                    <?php if ($company_phone !== '') { ?>
                        <div><dt>Phone</dt><dd><a href="tel:<?= escapeHtml($company_tel) ?>"><?= escapeHtml($company_phone) ?></a></dd></div>
                    <?php } ?>
                    <?php if ($support_email !== '') { ?>
                        <div><dt>Email</dt><dd class="text-break"><a href="mailto:<?= escapeHtml($support_email) ?>"><?= escapeHtml($support_email) ?></a></dd></div>
                    <?php } ?>
                    <?php if ($company_website !== '') { ?>
                        <div><dt>Website</dt><dd class="text-break"><a href="<?= escapeHtml(preg_match('#^https?://#i', $company_website) ? $company_website : "https://$company_website") ?>" target="_blank" rel="noopener noreferrer"><?= escapeHtml(preg_replace('#^https?://#i', '', $company_website)) ?></a></dd></div>
                    <?php } ?>
                </dl>
            </section>
        <?php } ?>
    </div>
</div>

<script src="/js/pretty_content.js"></script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
