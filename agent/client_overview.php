<?php

/*
 * Client overview.
 *
 * Who the client is and how to reach them, what needs attention, then the
 * technical side (tickets, notes, contacts, favourites, schedule, renewals,
 * assets) and the financial side (balance, payments, recurring, unbilled
 * work, open invoices). Each part shows to agents whose role can read it.
 *
 * The figures come from functions/client_overview.php; the chart is drawn by
 * js/client_overview.js, which also saves the quick notes.
 */

// This page draws its own client header, with the same actions menu
$client_top_head_skip = true;

require_once "includes/inc_all_client.php";

$access = clientOverviewAccess();
$renewals = $access['docs'] ? clientOverviewRenewals($client_id) : null;
$attention = clientOverviewAttention($client_id, $access, $renewals);
$open_tickets = $access['tickets'] ? clientOverviewOpenTickets($client_id) : null;
$overview_contacts = clientOverviewContacts($client_id);
$favorites = clientOverviewFavorites($client_id, $access);
$upcoming = clientOverviewUpcoming($client_id, $access);
$asset_summary = $access['docs'] ? clientOverviewAssets($client_id) : null;
$shared_items = clientOverviewSharedItems($client_id);
$activity = clientOverviewActivity($client_id);
$money = $access['financial'] ? clientOverviewMoney($client_id, $balance, $recurring_monthly) : null;
$open_invoices = $access['sales'] ? clientOverviewOpenInvoices($client_id) : null;
$recurring_invoices = $access['sales'] ? clientOverviewRecurringInvoices($client_id) : null;

$chart_data = [];
if ($access['financial']) {
    $chart_data['monthly'] = clientOverviewMonthly($client_id);
    $chart_data['currency'] = $client_currency_code ?: $session_company_currency;
}

$priority_colors = ['Urgent' => 'dark', 'High' => 'danger', 'Medium' => 'warning', 'Low' => 'info'];
$tone_text = ['danger' => 'text-danger-emphasis', 'warning' => 'text-warning-emphasis', 'info' => 'text-info-emphasis', 'secondary' => 'text-body'];
$money_format = function ($amount) use ($currency_format, $client_currency_code, $session_company_currency) {
    return numfmt_format_currency($currency_format, floatval($amount), $client_currency_code ?: $session_company_currency);
};

?>

<div class="itflow-dash itflow-ov">

    <!-- Who this is -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start gap-3">
                <span class="itflow-client-avatar itflow-client-avatar-lg text-bg-<?= $client_initials_tone ?>" aria-hidden="true"><?= $client_initials ?></span>
                <div class="flex-grow-1 overflow-hidden itflow-ov-hero-name">
                    <h1 class="itflow-dash-greeting mb-1">
                        <?= $client_name ?>
                        <?php if ($client_is_lead) { ?><span class="badge rounded-pill text-bg-info align-middle fs-6 fw-normal ms-1">Lead</span><?php } ?>
                        <?php if ($client_archived_at) { ?><span class="badge rounded-pill text-bg-secondary align-middle fs-6 fw-normal ms-1">Archived</span><?php } ?>
                    </h1>
                    <div class="d-flex flex-wrap align-items-center gap-1">
                        <?php if ($client_type) { ?>
                            <span class="text-body-secondary me-2"><?= $client_type ?></span>
                        <?php } ?>
                        <?= tagPills('client', $client_id, $client_tags) ?>
                    </div>
                </div>
                <div class="d-flex gap-2 ms-auto itflow-ov-hero-actions">
                    <?php if ($access['add_ticket'] && !$client_archived_at) { ?>
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/ticket/ticket_add.php?client_id=<?= $client_id ?>" data-modal-size="lg">
                            <i class="fas fa-plus me-2"></i>New Ticket
                        </button>
                    <?php } ?>
                    <?php if ($access['edit']) { ?>
                        <div class="dropdown dropstart">
                            <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-ellipsis-v"></i>
                            </button>
                            <?php require 'includes/client_actions_menu.php'; ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="card-body border-top">
            <div class="row g-3">
                <?php require 'includes/client_details.php'; ?>
            </div>
        </div>
    </div>

    <?php if ($attention) { ?>
        <section class="mb-4" aria-labelledby="ovAttention">
            <h2 id="ovAttention" class="itflow-dash-section mb-2">Needs attention</h2>
            <div class="card mb-0 overflow-hidden">
                <div class="itflow-dash-rail">
                    <?php foreach ($attention as $item) {
                        $tone = $item['tone']; ?>
                        <a href="<?= escapeHtml($item['url']) ?>" class="itflow-dash-rail-item" style="--itflow-dash-tone: var(--bs-<?= $tone ?>)">
                            <span class="itflow-dash-rail-count <?= $tone_text[$tone] ?? 'text-body' ?>"><?= number_format(intval($item['count'])) ?></span>
                            <span class="itflow-dash-rail-label"><i class="fas fa-fw fa-<?= $item['icon'] ?> <?= $tone_text[$tone] ?? '' ?>" aria-hidden="true"></i><?= escapeHtml($item['label']) ?></span>
                            <?php if (!empty($item['detail'])) { ?><span class="itflow-dash-rail-detail"><?= escapeHtml($item['detail']) ?></span><?php } ?>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </section>
    <?php } ?>

    <h2 class="itflow-dash-section mb-2">Technical</h2>

    <div class="row g-3 mb-3">
        <?php if ($open_tickets !== null) { ?>
            <div class="col-xl-8">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                        <h3 class="card-title mb-0 me-auto">Open tickets</h3>
                        <span class="small text-body-secondary"><?= $open_tickets['opened_30'] ?> opened, <?= $open_tickets['resolved_30'] ?> resolved in the last 30 days</span>
                        <?php if ($open_tickets['total']) { ?><a class="small ms-2" href="tickets.php?client_id=<?= $client_id ?>">View all <?= $open_tickets['total'] ?></a><?php } ?>
                    </div>
                    <?php if ($open_tickets['tickets']) { ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($open_tickets['tickets'] as $ticket) {
                                $status_color = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $ticket['ticket_status_color']) ? $ticket['ticket_status_color'] : '#6c757d';
                                $last_activity = $ticket['ticket_updated_at'] ?: $ticket['ticket_created_at'];
                                $ticket_who = trim(implode(', ', array_filter([$ticket['contact_name'], $ticket['user_name'] ? 'assigned to ' . $ticket['user_name'] : 'unassigned'])));
                                ?>
                                <a href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= intval($ticket['ticket_id']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2">
                                    <span class="itflow-dash-dot bg-<?= $priority_colors[$ticket['ticket_priority']] ?? 'secondary' ?>" title="<?= escapeHtml($ticket['ticket_priority']) ?> priority"></span>
                                    <span class="flex-grow-1 overflow-hidden">
                                        <span class="d-block text-truncate"><span class="small text-body-secondary me-2"><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) ?></span><?= escapeHtml($ticket['ticket_subject']) ?></span>
                                        <span class="d-block small text-body-secondary text-truncate"><?= escapeHtml(ucfirst($ticket_who)) ?></span>
                                    </span>
                                    <?php if ($ticket['sla_breached']) { ?>
                                        <span class="badge rounded-pill text-bg-danger" title="Past SLA"><i class="fas fa-stopwatch me-1" aria-hidden="true"></i>SLA</span>
                                    <?php } ?>
                                    <span class="badge rounded-pill text-white d-none d-sm-inline" style="background-color: <?= $status_color ?>"><?= escapeHtml($ticket['ticket_status_name']) ?></span>
                                    <span class="small text-body-secondary text-nowrap d-none d-md-inline" title="Last activity <?= escapeHtml($last_activity) ?>"><?= escapeHtml(timeAgo($last_activity)) ?></span>
                                </a>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="card-body text-center text-body-secondary py-5">
                            <i class="fas fa-check-circle fa-2x mb-2 d-block text-success" aria-hidden="true"></i>
                            No open tickets.
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <div class="<?= $open_tickets !== null ? 'col-xl-4' : 'col-md-6 col-xl-4' ?>">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto">Quick notes</h3>
                    <span class="small text-body-secondary" data-notes-status><?= $access['edit'] ? 'Saves when you click away' : '' ?></span>
                </div>
                <div class="card-body pt-2 d-flex">
                    <textarea class="form-control itflow-ov-notes" id="clientNotes" rows="8" placeholder="<?= $access['edit'] ? 'Gate codes, quirks, who to ask for - anything the next person should know' : 'No notes' ?>"
                        data-client-id="<?= $client_id ?>" data-csrf="<?= $_SESSION['csrf_token'] ?>" aria-label="Quick notes" <?= $access['edit'] ? '' : 'readonly' ?>><?= $client_notes ?></textarea>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto">Key contacts</h3>
                    <a class="small" href="contacts.php?client_id=<?= $client_id ?>">All contacts</a>
                </div>
                <?php if ($overview_contacts) { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($overview_contacts as $person) {
                            $person_id = intval($person['contact_id']);
                            $person_name = escapeHtml($person['contact_name']);
                            $person_photo = escapeHtml($person['contact_photo']);
                            $person_phone = escapeHtml(formatPhoneNumber($person['contact_phone'], $person['contact_phone_country_code']));
                            $person_mobile = escapeHtml(formatPhoneNumber($person['contact_mobile'], $person['contact_mobile_country_code']));
                            $person_email = escapeHtml($person['contact_email']);
                            $person_flags = array_keys(array_filter(['Primary' => $person['contact_primary'], 'Billing' => $person['contact_billing'], 'Technical' => $person['contact_technical'], 'Important' => $person['contact_important']]));
                            ?>
                            <div class="list-group-item d-flex gap-3 py-2">
                                <?php if ($person_photo) { ?>
                                    <img class="itflow-ov-person rounded-circle" src="../uploads/clients/<?= $client_id ?>/<?= $person_photo ?>" alt="">
                                <?php } else { ?>
                                    <span class="itflow-ov-person rounded-circle bg-secondary-subtle text-secondary-emphasis" aria-hidden="true"><?= escapeHtml(clientOverviewPersonInitials($person['contact_name'])) ?></span>
                                <?php } ?>
                                <div class="overflow-hidden flex-grow-1">
                                    <a class="ajax-modal fw-semibold" href="#" data-modal-url="modals/contact/contact.php?client_id=<?= $client_id ?>&id=<?= $person_id ?>" data-modal-size="lg"><?= $person_name ?></a>
                                    <?php foreach ($person_flags as $flag) { ?>
                                        <span class="badge rounded-pill <?= $flag === 'Primary' ? 'text-bg-success' : 'bg-secondary-subtle text-secondary-emphasis' ?> fw-normal ms-1"><?= $flag ?></span>
                                    <?php } ?>
                                    <?php if ($person['contact_title']) { ?><div class="small text-body-secondary text-truncate"><?= escapeHtml($person['contact_title']) ?></div><?php } ?>
                                    <div class="small d-flex flex-wrap column-gap-3">
                                        <?php if ($person_phone) { ?><a class="text-reset text-decoration-none text-nowrap" href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', (string) $person['contact_phone'])) ?>"><i class="fas fa-fw fa-phone text-body-secondary me-1" aria-hidden="true"></i><?= $person_phone ?><?= $person['contact_extension'] ? ' x' . escapeHtml($person['contact_extension']) : '' ?></a><?php } ?>
                                        <?php if ($person_mobile) { ?><a class="text-reset text-decoration-none text-nowrap" href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', (string) $person['contact_mobile'])) ?>"><i class="fas fa-fw fa-mobile-alt text-body-secondary me-1" aria-hidden="true"></i><?= $person_mobile ?></a><?php } ?>
                                    </div>
                                    <?php if ($person_email) { ?>
                                        <div class="small d-flex align-items-center">
                                            <a class="text-reset text-decoration-none text-truncate" href="mailto:<?= $person_email ?>"><i class="far fa-fw fa-envelope text-body-secondary me-1" aria-hidden="true"></i><?= $person_email ?></a>
                                            <button type="button" class="itflow-copy clipboardjs" title="Copy email" data-clipboard-text="<?= $person_email ?>"><i class="far fa-copy"></i></button>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="card-body text-center text-body-secondary py-4 small">
                        Mark contacts as primary, billing, technical or important and they show here.
                    </div>
                <?php } ?>
            </div>
        </div>

        <?php if ($favorites['assets'] || $favorites['credentials']) { ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">Favorites</h3>
                        <i class="fas fa-star text-warning" aria-hidden="true"></i>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($favorites['assets'] as $asset) {
                            $asset_ip = escapeHtml($asset['interface_ip']); ?>
                            <div class="list-group-item d-flex align-items-center gap-2 py-2">
                                <i class="fas fa-fw fa-<?= getAssetIcon($asset['asset_type']) ?> text-body-secondary" title="<?= escapeHtml($asset['asset_type']) ?>"></i>
                                <span class="flex-grow-1 overflow-hidden">
                                    <a class="ajax-modal d-block text-truncate" href="#" data-modal-url="modals/asset/asset.php?id=<?= intval($asset['asset_id']) ?>" data-modal-size="lg"><?= escapeHtml($asset['asset_name']) ?></a>
                                    <span class="d-block small text-body-secondary text-truncate"><?= escapeHtml(trim($asset['asset_make'] . ' ' . $asset['asset_model'])) ?></span>
                                </span>
                                <?php if ($asset_ip) { ?>
                                    <span class="small text-nowrap"><?= $asset_ip ?><button type="button" class="itflow-copy clipboardjs" title="Copy IP" data-clipboard-text="<?= $asset_ip ?>"><i class="far fa-copy"></i></button></span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                        <?php foreach ($favorites['credentials'] as $credential) {
                            $credential_id = intval($credential['credential_id']);
                            $credential_username = escapeHtml(decryptCredentialEntry($credential['credential_username']));
                            ?>
                            <div class="list-group-item d-flex align-items-center gap-2 py-2">
                                <i class="fas fa-fw fa-key text-body-secondary" title="Credential"></i>
                                <span class="flex-grow-1 overflow-hidden">
                                    <a class="ajax-modal d-block text-truncate" href="#" data-modal-url="modals/credential/credential_edit.php?id=<?= $credential_id ?>"><?= escapeHtml($credential['credential_name']) ?></a>
                                    <?php if ($credential_username) { ?>
                                        <span class="d-flex align-items-center small text-body-secondary">
                                            <span class="text-truncate"><?= $credential_username ?></span>
                                            <button type="button" class="itflow-copy clipboardjs" title="Copy username" data-clipboard-text="<?= $credential_username ?>"><i class="far fa-copy"></i></button>
                                        </span>
                                    <?php } ?>
                                </span>
                                <?php if ($credential['credential_otp_secret']) { ?>
                                    <span class="small text-body-secondary text-nowrap" onmouseenter="showOTPViaCredentialID(<?= $credential_id ?>)" title="Hover for the current code"><i class="far fa-clock me-1" aria-hidden="true"></i><span id="otp_<?= $credential_id ?>">OTP</span></span>
                                <?php } ?>
                                <span class="text-nowrap">
                                    <button class="btn btn-sm btn-link text-body-secondary p-1" type="button" title="Show password" onclick="showPasswordViaCredentialID(this, <?= $credential_id ?>)"><i class="fas fa-fw fa-eye" aria-hidden="true"></i></button><button type="button" class="itflow-copy" title="Copy password" onclick="copyPasswordViaCredentialID(this, <?= $credential_id ?>)"><i class="far fa-copy"></i></button>
                                </span>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if ($upcoming) { ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">Coming up</h3>
                        <span class="small text-body-secondary">Next 14 days</span>
                    </div>
                    <div class="itflow-dash-schedule">
                        <?php
                        $upcoming_day = null;
                        foreach (array_slice($upcoming, 0, 10) as $item) {
                            $item_day = dashboardDayLabel($item['when']);
                            if ($item_day !== $upcoming_day) {
                                $upcoming_day = $item_day; ?>
                                <div class="itflow-dash-schedule-day"><?= escapeHtml($item_day) ?></div>
                            <?php } ?>
                            <a class="itflow-dash-schedule-item" href="<?= escapeHtml($item['url']) ?>">
                                <span class="itflow-dash-schedule-time"><?= $item['all_day'] ? 'All day' : escapeHtml(date('g:i A', strtotime($item['when']))) ?></span>
                                <span class="itflow-dash-schedule-mark bg-<?= ['ticket' => 'primary', 'recurring' => 'info', 'event' => 'success'][$item['kind']] ?>"></span>
                                <span class="flex-grow-1 overflow-hidden">
                                    <span class="d-block text-truncate"><?php if ($item['ref']) { ?><span class="small text-body-secondary me-2"><?= escapeHtml($item['ref']) ?></span><?php } ?><?= escapeHtml($item['title']) ?></span>
                                    <?php if ($item['detail']) { ?><span class="d-block small text-body-secondary text-truncate"><?= escapeHtml($item['detail']) ?></span><?php } ?>
                                </span>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if ($renewals !== null) { ?>
            <div class="col-md-6 col-xl-4" id="clientRenewals">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">Renewals</h3>
                        <span class="small text-body-secondary">Expired or due in <?= $renewals['days'] ?> days</span>
                    </div>
                    <?php if ($renewals['items']) { ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($renewals['items'], 0, 8) as $item) {
                                $expired = $item['days'] < 0;
                                $when = $item['days'] === 0 ? 'Today' : ($expired ? 'Expired ' . timeAgo($item['date']) : 'In ' . preg_replace('/^in /', '', timeAgo($item['date'])));
                                ?>
                                <a href="<?= escapeHtml($item['url']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2">
                                    <i class="fas fa-fw fa-<?= $item['icon'] ?> text-body-secondary" aria-hidden="true"></i>
                                    <span class="flex-grow-1 overflow-hidden">
                                        <span class="d-block text-truncate"><?= escapeHtml($item['name']) ?></span>
                                        <span class="d-block small text-body-secondary"><?= $item['label'] ?></span>
                                    </span>
                                    <span class="small text-nowrap text-end <?= $expired ? 'text-danger-emphasis' : 'text-warning-emphasis' ?>" title="<?= escapeHtml(date('M j, Y', strtotime($item['date']))) ?>"><?= escapeHtml($when) ?></span>
                                </a>
                            <?php } ?>
                        </div>
                        <?php if (count($renewals['items']) > 8) { ?>
                            <div class="card-footer small text-body-secondary py-2"><?= count($renewals['items']) - 8 ?> more not shown</div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="card-body text-center text-body-secondary py-4">
                            <i class="fas fa-check-circle fa-2x mb-2 d-block text-success" aria-hidden="true"></i>
                            Nothing expired or due in the next <?= $renewals['days'] ?> days.
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <?php if ($asset_summary !== null && $asset_summary['total']) { ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">Assets</h3>
                        <a class="small" href="assets.php?client_id=<?= $client_id ?>">View all <?= $asset_summary['total'] ?></a>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($asset_summary['groups'] as $group_key => $group) {
                            if (!$group['count']) {
                                continue;
                            } ?>
                            <a href="assets.php?client_id=<?= $client_id ?>&type=<?= $group_key ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2">
                                <i class="fas fa-fw fa-<?= $group['icon'] ?> text-body-secondary" aria-hidden="true"></i>
                                <span class="flex-grow-1"><?= $group['label'] ?></span>
                                <span class="fw-semibold"><?= $group['count'] ?></span>
                            </a>
                        <?php } ?>
                    </div>
                    <div class="card-footer small text-body-secondary py-2">
                        Warranty: <?= $asset_summary['warranty_active'] ?> covered<?php if ($asset_summary['warranty_expired']) { ?>, <span class="text-danger-emphasis"><?= $asset_summary['warranty_expired'] ?> expired</span><?php } ?><?php if ($asset_summary['warranty_unknown']) { ?>, <?= $asset_summary['warranty_unknown'] ?> not recorded<?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if ($shared_items) { ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto">Active share links</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($shared_items as $item) {
                            $item_icon = ['Credential' => 'key', 'Document' => 'file-alt', 'File' => 'paperclip'][$item['item_type']] ?? 'share-square';
                            $item_views = intval($item['item_views']);
                            $item_limit = intval($item['item_view_limit']);
                            ?>
                            <div class="list-group-item d-flex align-items-center gap-2 py-2">
                                <i class="fas fa-fw fa-<?= $item_icon ?> text-body-secondary" title="<?= escapeHtml($item['item_type']) ?>"></i>
                                <span class="flex-grow-1 overflow-hidden">
                                    <span class="d-block text-truncate"><?= escapeHtml($item['item_name'] ?: $item['item_type']) ?></span>
                                    <span class="d-block small text-body-secondary text-truncate">
                                        <?= $item['item_recipient'] ? escapeHtml($item['item_recipient']) . ', ' : '' ?><?= $item_views ?><?= $item_limit ? " of $item_limit" : '' ?> view<?= ($item_limit ?: $item_views) === 1 ? '' : 's' ?>, expires <?= escapeHtml(timeAgo($item['item_expire_at'])) ?>
                                    </span>
                                </span>
                                <a class="btn btn-sm btn-link text-danger confirm-link" href="post.php?deactivate_shared_item=<?= intval($item['item_id']) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Turn off this link"><i class="fas fa-fw fa-unlink" aria-hidden="true"></i></a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>

        <div class="col-md-6 col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0 me-auto">Recent activity</h3>
                    <?php if ($session_is_admin) { ?><a class="small" href="../admin/audit_logs.php?client=<?= $client_id ?>">Audit log</a><?php } ?>
                </div>
                <?php if ($activity) { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($activity as $log) { ?>
                            <div class="list-group-item py-2">
                                <div class="small"><?= escapeHtml($log['log_description']) ?></div>
                                <div class="small text-body-secondary" title="<?= escapeHtml($log['log_created_at']) ?>"><?= escapeHtml(timeAgo($log['log_created_at'])) ?></div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="card-body text-center text-body-secondary py-4 small">Nothing recorded yet.</div>
                <?php } ?>
            </div>
        </div>
    </div>

    <?php if ($money !== null || $open_invoices !== null) { ?>

        <h2 class="itflow-dash-section mt-4 mb-2">Financial</h2>

        <?php if ($money !== null) { ?>
            <div class="card mb-3 overflow-hidden">
                <div class="itflow-dash-rail itflow-ov-stats">
                    <a href="invoices.php?client_id=<?= $client_id ?>&status=Unpaid" class="itflow-dash-rail-item">
                        <span class="small text-body-secondary">Balance</span>
                        <span class="itflow-dash-money-value <?= $money['overdue'] > 0.004 ? 'text-danger-emphasis' : '' ?>"><?= $money_format($money['balance']) ?></span>
                        <span class="small <?= $money['overdue'] > 0.004 ? 'text-danger-emphasis' : 'text-body-tertiary' ?>"><?= $money['overdue'] > 0.004 ? $money_format($money['overdue']) . ' overdue' : 'Nothing overdue' ?></span>
                    </a>
                    <a href="income.php?client_id=<?= $client_id ?>" class="itflow-dash-rail-item">
                        <span class="small text-body-secondary">Paid this year</span>
                        <span class="itflow-dash-money-value"><?= $money_format($money['paid_ytd']) ?></span>
                        <?php if ($money['paid_change'] !== null) { ?>
                            <span class="small"><span class="fw-semibold <?= $money['paid_change'] >= 0 ? 'text-success-emphasis' : 'text-danger-emphasis' ?>"><i class="fas fa-arrow-<?= $money['paid_change'] >= 0 ? 'up' : 'down' ?> me-1" aria-hidden="true"></i><?= abs($money['paid_change']) ?>%</span> <span class="text-body-tertiary">vs last year</span></span>
                        <?php } else { ?>
                            <span class="small text-body-tertiary">Nothing last year to compare</span>
                        <?php } ?>
                    </a>
                    <a href="recurring_invoices.php?client_id=<?= $client_id ?>" class="itflow-dash-rail-item">
                        <span class="small text-body-secondary">Recurring</span>
                        <span class="itflow-dash-money-value"><?= $money_format($money['recurring']) ?><span class="fs-6 fw-normal text-body-secondary">/mo</span></span>
                        <span class="small text-body-tertiary"><?= $num_recurring_invoices ?> recurring invoice<?= $num_recurring_invoices === 1 ? '' : 's' ?></span>
                    </a>
                    <?php if ($access['tickets']) { ?>
                        <a href="tickets.php?client_id=<?= $client_id ?>&billing=unbilled&state=closed" class="itflow-dash-rail-item">
                            <span class="small text-body-secondary">Not yet invoiced</span>
                            <span class="itflow-dash-money-value <?= $money['unbilled_tickets'] ? 'text-info-emphasis' : '' ?>"><?= $money['unbilled_tickets'] ?> <span class="fs-6 fw-normal text-body-secondary">ticket<?= $money['unbilled_tickets'] === 1 ? '' : 's' ?></span></span>
                            <span class="small text-body-tertiary"><?= $money['unbilled_seconds'] ? dashboardDuration($money['unbilled_seconds']) . ' logged' : 'Resolved and billable' ?></span>
                        </a>
                    <?php } ?>
                    <div class="itflow-dash-rail-item">
                        <span class="small text-body-secondary">Terms</span>
                        <span class="itflow-dash-money-value"><?= $money_format($client_rate) ?><span class="fs-6 fw-normal text-body-secondary">/hr</span></span>
                        <span class="small text-body-tertiary"><?= $client_net_terms ? "Net $client_net_terms" : 'Due on receipt' ?><?= $client_tax_id_number ? ", tax ID $client_tax_id_number" : '' ?></span>
                    </div>
                </div>
            </div>
        <?php } ?>

        <div class="row g-3 mb-3">
            <?php if ($open_invoices !== null) { ?>
                <div class="col-xl-8">
                    <div class="card h-100 mb-0">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title mb-0 me-auto">Unpaid invoices</h3>
                            <a class="small" href="invoices.php?client_id=<?= $client_id ?>">All invoices</a>
                        </div>
                        <?php if ($open_invoices['invoices']) { ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($open_invoices['invoices'] as $invoice) {
                                    $days_over = (int) (new DateTime('today'))->diff(new DateTime($invoice['invoice_due']))->format('%r%a') * -1;
                                    ?>
                                    <a href="invoice.php?client_id=<?= $client_id ?>&invoice_id=<?= intval($invoice['invoice_id']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2">
                                        <span class="flex-grow-1 overflow-hidden">
                                            <span class="d-block text-truncate"><span class="fw-semibold me-2"><?= escapeHtml($invoice['invoice_prefix'] . $invoice['invoice_number']) ?></span><span class="text-body-secondary"><?= escapeHtml($invoice['invoice_scope']) ?></span></span>
                                            <span class="d-block small <?= $days_over > 0 ? 'text-danger-emphasis' : 'text-body-secondary' ?>">
                                                <?= $days_over > 0 ? "$days_over day" . ($days_over === 1 ? '' : 's') . ' overdue' : 'Due ' . escapeHtml(date('M j', strtotime($invoice['invoice_due']))) ?>
                                                <?= $invoice['invoice_status'] === 'Partial' ? ', part paid' : '' ?>
                                            </span>
                                        </span>
                                        <span class="fw-semibold text-nowrap"><?= $money_format($invoice['balance']) ?></span>
                                    </a>
                                <?php } ?>
                            </div>
                            <?php if ($open_invoices['total'] > count($open_invoices['invoices'])) { ?>
                                <a class="card-footer small text-decoration-none py-2" href="invoices.php?client_id=<?= $client_id ?>&status=Unpaid"><?= $open_invoices['total'] - count($open_invoices['invoices']) ?> more unpaid</a>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="card-body text-center text-body-secondary py-4">
                                <i class="fas fa-check-circle fa-2x mb-2 d-block text-success" aria-hidden="true"></i>
                                All invoices are paid.
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card h-100 mb-0">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title mb-0 me-auto">Recurring invoices</h3>
                            <a class="small" href="recurring_invoices.php?client_id=<?= $client_id ?>">View all</a>
                        </div>
                        <?php if ($recurring_invoices) { ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recurring_invoices as $recurring) { ?>
                                    <a href="recurring_invoice.php?client_id=<?= $client_id ?>&recurring_invoice_id=<?= intval($recurring['recurring_invoice_id']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2">
                                        <span class="flex-grow-1 overflow-hidden">
                                            <span class="d-block text-truncate"><?= escapeHtml($recurring['recurring_invoice_scope'] ?: $recurring['recurring_invoice_prefix'] . $recurring['recurring_invoice_number']) ?></span>
                                            <span class="d-block small text-body-secondary"><?= $recurring['recurring_invoice_frequency'] === 'year' ? 'Yearly' : 'Monthly' ?>, next <?= escapeHtml(date('M j', strtotime($recurring['recurring_invoice_next_date']))) ?></span>
                                        </span>
                                        <span class="fw-semibold text-nowrap"><?= $money_format($recurring['recurring_invoice_amount']) ?></span>
                                    </a>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="card-body text-center text-body-secondary py-4 small">No recurring invoices.</div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <?php if ($chart_data) { ?>
                <div class="col-12">
                    <div class="card mb-0">
                        <div class="card-header d-flex align-items-center">
                            <h3 class="card-title mb-0 me-auto">Invoiced and paid</h3>
                            <span class="small text-body-secondary">Last 12 months</span>
                        </div>
                        <?php if (array_sum($chart_data['monthly']['invoiced']) + array_sum($chart_data['monthly']['paid']) > 0) { ?>
                            <div class="card-body">
                                <div class="itflow-dash-chart"><canvas id="clientMonthly" aria-label="Invoiced and paid per month"></canvas></div>
                            </div>
                        <?php } else { ?>
                            <div class="card-body text-center text-body-secondary py-4 small">Nothing invoiced or paid in the last 12 months.</div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>

    <?php } ?>

</div>

<script src="js/credential_show_otp_via_id.js"></script>
<script src="js/credential_show_password_via_id.js"></script>

<?php require_once "../includes/footer.php"; ?>

<?php if ($chart_data) { ?>
    <script>window.itflowClientOverviewData = <?= json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;</script>
<?php } ?>
<script src="/js/client_overview.js"></script>
