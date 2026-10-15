<?php

/*
 * Ticket quick look - opened from the ticket list and board, and anywhere
 * else that wants a ticket at a glance without leaving the page. Whose move
 * it is and when it is due, who it is for and how to reach them, what it is
 * about, the other open tickets for the same person or device, the latest
 * replies and the tasks left. The ticket page is one click away for the rest.
 */

require_once '../../../includes/modal_header.php';

require_once '../../../libs/htmlpurifier/HTMLPurifier.standalone.php';

enforceUserPermission('module_support');

$ticket_id = intval($_GET['id'] ?? 0);
$ticket = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT tickets.*, client_name, contact_name, contact_email, contact_photo, contact_title,
        contact_phone, contact_phone_country_code, contact_extension, contact_mobile, contact_mobile_country_code,
        ticket_status_name, ticket_status_color, ticket_status_pauses_sla, user_name, user_avatar, asset_name, location_name,
        vendor_name, category_name, category_color, project_prefix, project_number, project_name, sla_name,
        " . ticketLastSpokeSql() . " AS ticket_last_spoke
    FROM tickets
    LEFT JOIN clients ON client_id = ticket_client_id
    LEFT JOIN contacts ON contact_id = ticket_contact_id
    LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
    LEFT JOIN users ON user_id = ticket_assigned_to
    LEFT JOIN assets ON asset_id = ticket_asset_id
    LEFT JOIN locations ON location_id = ticket_location_id
    LEFT JOIN vendors ON vendor_id = ticket_vendor_id
    LEFT JOIN categories ON category_id = ticket_category
    LEFT JOIN projects ON project_id = ticket_project_id
    LEFT JOIN slas ON sla_id = ticket_sla_id
    WHERE ticket_id = $ticket_id LIMIT 1"));
if (!$ticket) {
    echo json_encode(['error' => 'That ticket no longer exists.']);
    exit;
}
$client_id = intval($ticket['ticket_client_id']);
if ($client_id) {
    enforceClientAccess();
}

$purifier_config = HTMLPurifier_Config::createDefault();
$purifier_config->set('Cache.DefinitionImpl', null);
$purifier_config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
$purifier = new HTMLPurifier($purifier_config);

$reference = escapeHtml(ticketReference($ticket));
$url = escapeHtml(ticketUrl($ticket_id, $client_id));
$is_open = ticketIsOpen($ticket);
$is_closed = !empty($ticket['ticket_closed_at']);
$can_edit = ticketCan(2) && !$is_closed;
$mine = intval($ticket['ticket_assigned_to']) === intval($session_user_id);
$move = ticketWhoseMove($ticket);
$sla = ticketSlaState($ticket);
$due = ticketDueState($ticket);
$also_open = $is_open ? ticketAlsoOpen($ticket['ticket_contact_id'], $ticket['ticket_asset_id'], $ticket_id, 5) : [];
$also_open_shares = array_values(array_unique(array_merge([], ...array_column($also_open, 'shares'))));
$also_open_shares = count($also_open_shares) > 1 ? 'contact or asset' : ($also_open_shares[0] ?? 'contact');
$tasks = ticketTaskCounts([$ticket_id])[$ticket_id] ?? null;

$replies = [];
$sql_replies = mysqli_query($mysqli, "SELECT ticket_reply_id, ticket_reply, ticket_reply_type, ticket_reply_created_at, ticket_reply_by, user_name, contact_name
    FROM ticket_replies
    LEFT JOIN users ON ticket_reply_type <> 'Client' AND ticket_reply_by = user_id
    LEFT JOIN contacts ON ticket_reply_type = 'Client' AND ticket_reply_by = contact_id
    WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL
    ORDER BY ticket_reply_id DESC LIMIT 3");
while ($row = mysqli_fetch_assoc($sql_replies)) {
    $replies[] = $row;
}
$reply_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM ticket_replies WHERE ticket_reply_ticket_id = $ticket_id AND ticket_reply_archived_at IS NULL"))[0]);

$open_tasks = [];
if ($tasks && $tasks[0] < $tasks[1]) {
    $sql_tasks = mysqli_query($mysqli, "SELECT task_name FROM tasks WHERE task_ticket_id = $ticket_id AND task_completed_at IS NULL ORDER BY task_order, task_id LIMIT 5");
    while ($row = mysqli_fetch_assoc($sql_tasks)) {
        $open_tasks[] = $row['task_name'];
    }
}

$move_text = ['us' => ['fa-reply text-primary', 'Needs a reply from us'], 'client' => ['fa-hourglass-half', 'With the client - we replied last'],
    'parked' => ['fa-pause', 'Parked - the SLA clock is paused in this status']][$move] ?? null;
$phones = [];
if (!empty($ticket['contact_phone'])) {
    $phones[] = formatPhoneNumber($ticket['contact_phone'], $ticket['contact_phone_country_code']) . ($ticket['contact_extension'] ? ' x' . $ticket['contact_extension'] : '');
}
if (!empty($ticket['contact_mobile'])) {
    $phones[] = formatPhoneNumber($ticket['contact_mobile'], $ticket['contact_mobile_country_code']);
}

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="min-w-0">
        <div class="small d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="font-monospace text-body-secondary"><?= $reference ?></span>
            <?= ticketStatusHtml($ticket['ticket_status_name'], $ticket['ticket_status_color'], 'detail') ?>
            <?= ticketPriorityHtml($ticket['ticket_priority'], 'detail') ?>
        </div>
        <h5 class="modal-title fw-semibold text-break"><?= escapeHtml($ticket['ticket_subject']) ?></h5>
        <div class="small text-body-secondary mt-1">
            <?= $client_id ? escapeHtml($ticket['client_name']) : 'Internal - no client' ?>
            <span class="mx-1">&middot;</span>opened <?= timeAgo($ticket['ticket_created_at']) ?>
            <?= ticketSourceIcon($ticket['ticket_source']) ?>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body itflow-ticket-glance">

    <?php if ($move_text || $sla['key'] !== 'none' || $due) { ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <?php if ($move_text) { ?>
                <span class="badge rounded-pill bg-body-secondary text-body fw-normal"><i class="fas <?= $move_text[0] ?> me-1"></i><?= $move_text[1] ?></span>
            <?php } ?>
            <?= ticketDuePill($due) ?>
            <?= ticketSlaPill($sla) ?>
            <?php if ($sla['key'] !== 'none' && !empty($sla['due']) && $is_open) { ?>
                <span class="small text-body-secondary"><?= escapeHtml($sla['what']) ?> <?= escapeHtml(date('M j, g:i A', strtotime($sla['due']))) ?><?= $ticket['sla_name'] !== null ? ' &middot; ' . escapeHtml($ticket['sla_name']) : '' ?></span>
            <?php } ?>
        </div>
    <?php } ?>

    <dl class="itflow-asset-dl mb-3">
        <dt>Contact</dt>
        <dd>
            <?php if (intval($ticket['ticket_contact_id']) && $ticket['contact_name'] !== null) { ?>
                <?= contactLink($client_id, $ticket['ticket_contact_id'], $ticket['contact_name'], $ticket['contact_photo'] ?? '') ?>
                <?php if ($ticket['contact_title']) { ?><span class="text-body-secondary small ms-1"><?= escapeHtml($ticket['contact_title']) ?></span><?php } ?>
                <?php if ($ticket['contact_email']) { ?>
                    <div class="small">
                        <a href="mailto:<?= escapeHtml($ticket['contact_email']) ?>"><?= escapeHtml($ticket['contact_email']) ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= escapeHtml($ticket['contact_email']) ?>"><i class="far fa-copy"></i></button>
                    </div>
                <?php } ?>
                <?php foreach ($phones as $phone) { ?>
                    <div class="small">
                        <a href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= escapeHtml($phone) ?></a><button type="button" class="itflow-copy clipboardjs" title="Copy number" data-clipboard-text="<?= escapeHtml($phone) ?>"><i class="far fa-copy"></i></button>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <span class="text-body-secondary">-</span>
            <?php } ?>
        </dd>

        <dt>Assigned</dt>
        <dd><?= formatAssignee($ticket['ticket_assigned_to'], $ticket['user_name'], $ticket['user_avatar'], $is_open) ?></dd>

        <?php if (intval($ticket['ticket_asset_id']) && $ticket['asset_name'] !== null) { ?>
            <dt>Asset</dt>
            <dd><a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/asset/asset.php?id=<?= intval($ticket['ticket_asset_id']) ?>"><i class="fas fa-desktop text-body-secondary me-1"></i><?= escapeHtml($ticket['asset_name']) ?></a></dd>
        <?php } ?>

        <?php if (intval($ticket['ticket_location_id']) && $ticket['location_name'] !== null) { ?>
            <dt>Site</dt>
            <dd><a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/location/location.php?id=<?= intval($ticket['ticket_location_id']) ?>"><i class="fas fa-map-marker-alt text-body-secondary me-1"></i><?= escapeHtml($ticket['location_name']) ?></a></dd>
        <?php } ?>

        <?php if ($ticket['category_name'] !== null && $ticket['category_name'] !== '') { ?>
            <dt>Category</dt>
            <dd><i class="fas fa-circle itflow-ticket-dot" <?= preg_match('/^#[0-9a-f]{3,8}$/i', (string) $ticket['category_color']) ? 'style="color: ' . $ticket['category_color'] . '"' : '' ?>></i><?= escapeHtml($ticket['category_name']) ?></dd>
        <?php } ?>

        <?php if (!empty($ticket['ticket_schedule'])) { ?>
            <dt>Scheduled</dt>
            <dd><i class="fas fa-<?= intval($ticket['ticket_onsite']) ? 'truck' : 'calendar-check' ?> text-body-secondary me-1"></i><?= escapeHtml(date('D M j, g:i A', strtotime($ticket['ticket_schedule']))) ?><?= intval($ticket['ticket_onsite']) ? ' <span class="text-body-secondary">on site</span>' : '' ?></dd>
        <?php } ?>

        <?php if (intval($ticket['ticket_project_id']) && $ticket['project_name'] !== null) { ?>
            <dt>Project</dt>
            <dd><a href="project.php?project_id=<?= intval($ticket['ticket_project_id']) ?>"><?= escapeHtml($ticket['project_name']) ?></a> <span class="text-body-secondary small"><?= escapeHtml($ticket['project_prefix'] . intval($ticket['project_number'])) ?></span></dd>
        <?php } ?>

        <?php if (intval($ticket['ticket_vendor_id']) && $ticket['vendor_name'] !== null) { ?>
            <dt>Vendor</dt>
            <dd>
                <?= vendorLink($ticket['ticket_vendor_id'], $ticket['vendor_name']) ?>
                <?php if (!empty($ticket['ticket_vendor_ticket_number'])) { ?>
                    <span class="small text-body-secondary ms-1">their ref <span class="font-monospace"><?= escapeHtml($ticket['ticket_vendor_ticket_number']) ?></span><button type="button" class="itflow-copy clipboardjs" title="Copy their reference" data-clipboard-text="<?= escapeHtml($ticket['ticket_vendor_ticket_number']) ?>"><i class="far fa-copy"></i></button></span>
                <?php } ?>
            </dd>
        <?php } ?>

        <?php if ($tasks) { ?>
            <dt>Tasks</dt>
            <dd>
                <?= ticketTasksHtml($tasks) ?>
                <?php if ($open_tasks) { ?>
                    <ul class="list-unstyled small mb-0 mt-1">
                        <?php foreach ($open_tasks as $task_name) { ?>
                            <li class="text-truncate"><i class="far fa-square text-body-secondary me-2"></i><?= escapeHtml($task_name) ?></li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </dd>
        <?php } ?>
    </dl>

    <?php if ($also_open) { ?>
        <div class="alert alert-warning py-2 small">
            <div class="fw-semibold mb-1"><i class="fas fa-layer-group me-1"></i>Also open for the same <?= $also_open_shares ?></div>
            <?php foreach ($also_open as $other) { ?>
                <div class="text-truncate"><?= ticketLink($other) ?></div>
            <?php } ?>
        </div>
    <?php } ?>

    <h6 class="small text-body-secondary fw-semibold text-uppercase">What it is about</h6>
    <div class="itflow-ticket-glance-text border rounded p-2 mb-3"><?= $purifier->purify($ticket['ticket_details']) ?: '<span class="text-body-secondary">No details.</span>' ?></div>

    <?php if ($replies) { ?>
        <h6 class="small text-body-secondary fw-semibold text-uppercase">
            Latest replies
            <?php if ($reply_count > count($replies)) { ?><span class="fw-normal text-lowercase">- <?= count($replies) ?> of <?= $reply_count ?></span><?php } ?>
        </h6>
        <?php foreach ($replies as $reply) {
            $type = $reply['ticket_reply_type'];
            $who = $type === 'Client' ? $reply['contact_name'] : $reply['user_name'];
            ?>
            <div class="border rounded p-2 mb-2 <?= $type === 'Internal' ? 'bg-body-tertiary' : '' ?>">
                <div class="small d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="badge rounded-pill <?= ['Client' => 'text-bg-warning', 'Public' => 'text-bg-primary', 'Internal' => 'text-bg-secondary'][$type] ?? 'text-bg-secondary' ?>"><?= ['Client' => 'Client', 'Public' => 'Public', 'Internal' => 'Internal note'][$type] ?? escapeHtml($type) ?></span>
                    <span class="fw-semibold"><?= escapeHtml($who ?? '') ?></span>
                    <span class="text-body-secondary ms-auto" title="<?= escapeHtml($reply['ticket_reply_created_at']) ?>"><?= timeAgo($reply['ticket_reply_created_at']) ?></span>
                </div>
                <div class="itflow-ticket-glance-text small"><?= $purifier->purify($reply['ticket_reply']) ?></div>
            </div>
        <?php } ?>
    <?php } else { ?>
        <div class="small text-body-secondary"><i class="far fa-clock me-1"></i>No replies yet.</div>
    <?php } ?>

</div>

<div class="modal-footer">
    <?php if ($can_edit && !$mine) { ?>
        <a class="btn btn-outline-secondary me-auto" href="post.php?assign_ticket_to_me=<?= $ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-hand-paper me-2"></i>Assign to me</a>
    <?php } ?>
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-light ajax-modal" data-modal-size="lg" data-modal-url="modals/ticket/ticket_edit.php?id=<?= $ticket_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <a class="btn btn-primary text-bold" href="<?= $url ?>"><i class="fas fa-life-ring me-2"></i>Open ticket</a>
</div>

<?php
require_once '../../../includes/modal_footer.php';
