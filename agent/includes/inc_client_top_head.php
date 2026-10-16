<?php
/*
 * The client header at the top of every client page except the overview,
 * which draws its own (same tags, same actions menu).
 *
 * One card: the client's initials, name with its type under it, tags, open
 * tickets and any balance due, and the actions menu. Clicking the name opens
 * a panel with the same primary contact, location and details as the
 * overview's header (includes/client_details.php).
 *
 * Whether the panel starts open is remembered in a cookie rather than
 * localStorage precisely so it can be answered HERE, server side - the panel
 * and its chevron then render in the right state on the first paint instead of
 * flashing shut and springing open once JS has run. Closed until opened.
 */

$show_add_credit = 0; // Remove once credits is added hides the button

$client_header_open = ($_COOKIE['client_header_open'] ?? '0') === '1';

if (empty($client_top_head_skip)) {

    $client_head_edit = lookupUserPermission("module_client") >= 2;
    $client_head_billing = lookupUserPermission("module_financial") >= 1 && $config_module_enable_accounting == 1;
    $client_head_support = lookupUserPermission("module_support") >= 1 && $config_module_enable_ticketing == 1;
    $client_head_invoices = lookupUserPermission("module_sales") >= 1 && $config_module_enable_accounting == 1;
    $client_head_glance_tickets = $client_head_support && $num_active_tickets > 0;
    $client_head_glance_due = $client_head_billing && $balance > 0;
    $client_head_money = function ($amount) use ($currency_format, $client_currency_code, $session_company_currency) {
        return numfmt_format_currency($currency_format, floatval($amount), $client_currency_code ?: $session_company_currency);
    };
    ?>

<div class="card mb-3 d-print-none itflow-client-head">
    <div class="card-body d-flex align-items-start align-items-md-center gap-2 px-3 py-2">
        <div class="d-flex flex-wrap align-items-center column-gap-2 row-gap-2 row-gap-md-1 flex-grow-1 itflow-client-head-main">
            <a href="#" class="itflow-client-head-toggle client-header-toggle<?= $client_header_open ? '' : ' collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#clientHeader" aria-controls="clientHeader" aria-expanded="<?= $client_header_open ? 'true' : 'false' ?>" title="Client ID: <?= $client_id ?>">
                <i class="fas fa-fw fa-chevron-<?= $client_header_open ? 'down' : 'right' ?> client-header-chevron" aria-hidden="true"></i>
                <span class="itflow-client-avatar itflow-client-avatar-sm text-bg-<?= $client_initials_tone ?>" aria-hidden="true"><?= $client_initials ?></span>
                <span class="itflow-client-head-title">
                    <span class="itflow-client-head-name"><?= $client_name ?></span>
                    <?php if ($client_type) { ?><span class="itflow-client-head-type"><?= $client_type ?></span><?php } ?>
                </span>
            </a>
            <?php if ($client_is_lead) { ?><span class="badge rounded-pill text-bg-info fw-normal">Lead</span><?php } ?>
            <?php if ($client_archived_at) { ?><span class="badge rounded-pill text-bg-secondary fw-normal">Archived</span><?php } ?>
            <?= tagPills('client', $client_id, $client_tags) ?>

            <?php /* Open tickets and balance due, always in sight */ ?>
            <?php if ($client_head_glance_tickets || $client_head_glance_due) { ?>
            <div class="itflow-client-head-glance d-flex align-items-center gap-3 ms-md-auto">
                <?php if ($client_head_glance_tickets) { ?>
                    <a class="text-body-secondary" href="tickets.php?client_id=<?= $client_id ?>"><i class="fas fa-fw fa-life-ring me-1" aria-hidden="true"></i><?= number_format($num_active_tickets) ?> open</a>
                <?php } ?>
                <?php if ($client_head_glance_due) { ?>
                    <?php if ($client_head_invoices) { ?>
                        <a class="text-danger-emphasis fw-semibold" href="invoices.php?client_id=<?= $client_id ?>&status=Unpaid"><?= $client_head_money($balance) ?> due</a>
                    <?php } else { ?>
                        <span class="text-danger-emphasis fw-semibold"><?= $client_head_money($balance) ?> due</span>
                    <?php } ?>
                <?php } ?>
            </div>
            <?php } ?>
        </div>

        <?php if ($client_head_edit) { ?>
            <div class="dropdown dropstart flex-shrink-0">
                <button class="btn btn-outline-secondary btn-sm" title="More actions" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-fw fa-ellipsis-v"></i>
                </button>
                <?php require 'includes/client_actions_menu.php'; ?>
            </div>
        <?php } ?>
    </div>

    <div class="collapse<?= $client_header_open ? ' show' : '' ?>" id="clientHeader">
        <div class="card-body border-top px-3 py-3">
            <div class="row g-3">
                <?php require 'includes/client_details.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    /*
     * Remember whether the client header is open so it stays that way while you move
     * around the client area. The cookie is what the PHP at the top of this file reads
     * on the next request. The chevron is swapped by CLASS rather than rotated in CSS,
     * so the direction cannot go stale behind a cached stylesheet.
     *
     * No readiness wrapper: these are document-level listeners for events that cannot
     * fire until both Bootstrap and the markup exist, so registration order is moot.
     */
    function syncClientHeader(open) {
        document.cookie = 'client_header_open=' + (open ? '1' : '0') +
            ';path=/;max-age=31536000;samesite=lax' +
            (location.protocol === 'https:' ? ';secure' : '');

        document.querySelectorAll('.client-header-chevron').forEach(function (icon) {
            icon.classList.toggle('fa-chevron-down', open);
            icon.classList.toggle('fa-chevron-right', !open);
        });
    }

    document.addEventListener('shown.bs.collapse', function (e) {
        if (e.target.id === 'clientHeader') {
            syncClientHeader(true);
        }
    });

    document.addEventListener('hidden.bs.collapse', function (e) {
        if (e.target.id === 'clientHeader') {
            syncClientHeader(false);
        }
    });
})();
</script>

<?php } ?>

<?php
// require_once "modals/client/client_credit_add.php"; --Credit Not Ready 2025-08-27
require_once "modals/client/client_delete.php";
