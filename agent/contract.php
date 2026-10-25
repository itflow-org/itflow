<?php

/*
 * Contract - one agreement with a client: where it stands and when it renews
 * or ends (with the notice date), what it covers (support hours, an SLA for
 * each ticket priority, rates and net terms - the client's own where the
 * contract sets none), its terms, the internal notes, and its history from
 * the audit log. Make active, Renew, Cancel and Edit from the top. Signing:
 * send it to the client to sign, where each request stands - sent, opened,
 * signed, countersigned - the link to copy, the copies signed before, and
 * the document as the client sees it (agent/contract_document.php).
 * Prepaid hours: what is left of the hours bought and included each month,
 * each block of them, and the tickets drawn from them - Add hours, Draw now,
 * and a ticket put back. While it is in force, its SLAs, rates and net terms
 * are the client's; the recurring invoice that bills it starts and stops
 * with it. The work is in functions/contract.php,
 * functions/contract_signature.php, functions/contract_hours.php and
 * functions/contract_billing.php; the handlers are agent/post/contract.php.
 */

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_url = '';
}

enforceUserPermission('module_sales');

$contract_id = intval($_GET['contract_id'] ?? 0);
$contract = contractRow($contract_id);

if (!$contract || ($client_url && intval($contract['contract_client_id']) !== intval($client_id))) {

    echo "<center><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='javascript:history.back()'><i class='fa fa-fw fa-arrow-left'></i> Go Back</a></center>";

} else {

    $client_id = intval($contract['contract_client_id']);
    enforceClientAccess();

    $can_edit = lookupUserPermission('module_sales') >= 2;
    $can_delete = lookupUserPermission('module_sales') >= 3;
    $name = escapeHtml($contract['contract_name']);
    $page_title = $contract['contract_name'];

    $state = contractState($contract);
    $type = CONTRACT_TYPES[$contract['contract_type']] ?? CONTRACT_TYPES['other'];
    $notice = contractNoticeDate($contract);
    $today = date('Y-m-d');
    $status = (string) $contract['contract_status'];
    $slas = contractSlaChoices(array_map(function ($part) use ($contract) {
        return intval($contract["contract_sla_{$part}_id"]);
    }, CONTRACT_SLA_PRIORITIES));
    $history = auditLogHistory('Contract', $contract_id);
    // Signing - the rounds, newest first, the latest's state, and the copy signed by both
    $signing = contractSignaturesReady();
    $rounds = $signing ? contractSignatureRounds($contract_id) : [];
    $latest = $rounds[0] ?? null;
    $latest_state = contractSignatureState($latest);
    $signed_round = contractSignatureSigned($rounds);
    $outdated = $signed_round && (!$latest_state || in_array($latest_state['key'], ['signed', 'withdrawn'], true)) && contractSignatureOutdated($contract, $signed_round);
    $can_send = $signing && $can_edit && !in_array($status, ['Ended', 'Cancelled'], true);
    // Prepaid hours - the blocks, where they stand, and the tickets drawn from them
    $hours_on = contractHoursReady();
    $blocks = $hours_on ? contractBlocks($contract_id) : [];
    $hours = contractHoursSummary($blocks);
    $show_hours = $hours_on && contractHasHours($contract, $blocks);
    $hours_uses = $show_hours ? contractHoursUses($contract_id, 20) : [];
    $can_hours = $hours_on && $can_edit && !in_array($status, ['Ended', 'Cancelled'], true);
    $hours_alert = $hours_on ? floatval($contract['contract_hours_alert']) : 0;
    $hours_low = $show_hours && $status === 'Active' && $hours['bought'] > 0 && ($hours['left'] <= 0 || ($hours_alert > 0 && $hours['left'] < $hours_alert));
    // In force today - its SLAs, rates and net terms are the client's - and the recurring invoice that bills it
    $in_force = contractInForceOn($contract, $today);
    $billing_on = contractBillingReady() && intval($config_module_enable_accounting) === 1;
    $recurring = $billing_on ? contractRecurringRow($contract) : null;
    $can_link = $billing_on && $can_edit && !$recurring && (contractRecurringShouldRun($contract) || $status === 'Draft');
    $currency = $session_company_currency ?? '';
    $money = function ($amount) use ($currency) {
        return escapeHtml(function_exists('billingMoney') ? billingMoney($amount, $currency) : number_format((float) $amount, 2));
    };

    // How far through its term it is
    $progress = null;
    if ($contract['contract_start_date'] && $contract['contract_end_date'] && in_array($state['key'], ['active', 'renewing', 'ending'], true)) {
        // The current term began a term's length before it ends - or when the contract started, while it is in its first
        // term (a first term a few days longer or shorter than the rest is still the first)
        $months = intval($contract['contract_term_months']);
        $started = strtotime($contract['contract_start_date']);
        $term_start = strtotime("-$months months +1 day", strtotime($contract['contract_end_date']));
        if ($term_start < strtotime("+$months months", $started)) {
            $term_start = $started;
        }
        $term_end = strtotime($contract['contract_end_date']);
        $progress = [
            'percent' => $term_end > $term_start ? max(0, min(100, round((time() - $term_start) / ($term_end - $term_start) * 100))) : 100,
            'days_left' => max(0, intval(floor(($term_end - strtotime($today)) / 86400))),
            'from' => date('M j, Y', $term_start),
        ];
    }

    ?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item"><a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($contract['client_name']) ?></a></li>
        <li class="breadcrumb-item"><a href="contracts.php?client_id=<?= $client_id ?>">Contracts</a></li>
        <li class="breadcrumb-item active"><?= $name ?></li>
    </ol>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="itflow-asset-icon itflow-asset-icon-lg itflow-asset-tone-other" aria-hidden="true"><i class="fas fa-fw <?= $type['icon'] ?>"></i></span>
            <div class="flex-grow-1 min-w-0">
                <h1 class="h4 fw-semibold mb-1 d-flex flex-wrap align-items-center gap-2">
                    <span><?= $name ?></span>
                    <span class="fs-6"><?= contractStateHtml($contract) ?></span>
                </h1>
                <div class="text-body-secondary small d-flex flex-wrap column-gap-3 row-gap-1">
                    <span title="<?= escapeHtml($type['about']) ?>"><i class="fas fa-fw <?= $type['icon'] ?> me-1"></i><?= escapeHtml(contractTypeLabel($contract['contract_type'])) ?></span>
                    <span><i class="fas fa-fw fa-calendar-alt me-1"></i><?= escapeHtml($state['when']) ?></span>
                    <?php if ($contract['contract_template_name']) { ?><span><i class="fas fa-fw fa-clone me-1"></i>From <?= escapeHtml($contract['contract_template_name']) ?></span><?php } ?>
                </div>
            </div>
            <?php if ($can_edit) { ?>
                <div class="d-flex flex-wrap gap-2 d-print-none">
                    <?php if ($latest_state && $latest_state['key'] === 'countersign') { ?>
                        <button type="button" class="btn btn-success ajax-modal" data-modal-url="modals/contract/contract_countersign.php?id=<?= intval($latest['contract_signature_id']) ?>"><i class="fas fa-pen-nib me-2"></i>Countersign</button>
                    <?php } elseif ($can_send && $status === 'Draft' && (!$latest_state || !contractSignaturePending($latest))) { ?>
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/contract/contract_send.php?id=<?= $contract_id ?>"><i class="fas fa-file-signature me-2"></i>Send to sign</button>
                    <?php } ?>
                    <?php if ($status === 'Draft') { ?>
                        <button type="button" class="btn btn-<?= $can_send ? 'outline-primary' : 'primary' ?> ajax-modal" data-modal-url="modals/contract/contract_activate.php?id=<?= $contract_id ?>"><i class="fas fa-check me-2"></i>Make active</button>
                    <?php } elseif ($contract['contract_end_date']) { ?>
                        <button type="button" class="btn btn-outline-primary ajax-modal" data-modal-url="modals/contract/contract_renew.php?id=<?= $contract_id ?>"><i class="fas fa-sync-alt me-2"></i>Renew</button>
                    <?php } ?>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/contract/contract_edit.php?id=<?= $contract_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
                    <div class="dropdown dropstart">
                        <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                        <div class="dropdown-menu">
                            <?php if ($signing) { ?>
                                <a class="dropdown-item" href="contract_document.php?client_id=<?= $client_id ?>&contract_id=<?= $contract_id ?>"><i class="fas fa-fw fa-file-alt text-secondary me-2"></i>Document</a>
                                <a class="dropdown-item" href="post.php?export_contract_pdf=<?= $contract_id ?>"><i class="fas fa-fw fa-file-pdf text-secondary me-2"></i>Download PDF</a>
                                <?php if ($can_send && $status !== 'Draft') { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/contract/contract_send.php?id=<?= $contract_id ?>"><i class="fas fa-fw fa-file-signature text-secondary me-2"></i>Send to sign</a>
                                <?php } ?>
                                <div class="dropdown-divider"></div>
                            <?php } ?>
                            <?php if ($can_hours && !$show_hours) { ?>
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/contract/contract_hours_add.php?id=<?= $contract_id ?>"><i class="fas fa-fw fa-hourglass-half text-secondary me-2"></i>Add prepaid hours</a>
                            <?php } ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/contract/contract_add.php?client_id=<?= $client_id ?>&copy=<?= $contract_id ?>"><i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy</a>
                            <?php if ($status === 'Active') { ?>
                                <a class="dropdown-item text-danger ajax-modal" href="#" data-modal-url="modals/contract/contract_cancel.php?id=<?= $contract_id ?>"><i class="fas fa-fw fa-ban me-2"></i>Cancel</a>
                            <?php } ?>
                            <?php if ($can_delete) { ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_contract=<?= $contract_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="It goes for good. Cancel it instead to keep a record of it." data-confirm-button="Delete">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
        <?php if ($progress) { ?>
            <div class="card-footer bg-transparent">
                <div class="d-flex flex-wrap justify-content-between small text-body-secondary mb-1">
                    <span>This term since <?= escapeHtml($progress['from']) ?></span>
                    <span><?= number_format($progress['days_left']) ?> day<?= $progress['days_left'] === 1 ? '' : 's' ?> left</span>
                </div>
                <div class="progress" style="height: .5rem;" role="progressbar" aria-label="How far through its term" aria-valuenow="<?= $progress['percent'] ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-<?= $state['tone'] === 'warning' ? 'warning' : ($state['tone'] === 'primary' ? 'primary' : 'success') ?>" style="width: <?= $progress['percent'] ?>%"></div>
                </div>
            </div>
        <?php } ?>
    </div>

    <?php if (in_array($state['key'], ['renewing', 'ending'], true) && $notice !== '') { ?>
        <div class="alert alert-<?= $state['key'] === 'ending' ? 'warning' : 'info' ?> d-flex flex-wrap align-items-center gap-2 mb-3" data-contract-notice>
            <i class="fas fa-fw <?= $state['icon'] ?>"></i>
            <div class="me-auto">
                <?php if ($state['key'] === 'renewing') { ?>
                    <strong>It renews itself on <?= escapeHtml(date('M j, Y', strtotime('+1 day', strtotime($contract['contract_end_date'])))) ?></strong> for another <?= escapeHtml(contractTermLabel($contract['contract_term_months'])) ?>.
                    <?= $notice >= $today ? 'Notice to stop it is due by ' . escapeHtml(date('M j, Y', strtotime($notice))) . ' - a good time to review it with the client.' : 'The notice date has passed.' ?>
                <?php } else { ?>
                    <strong>It ends on <?= escapeHtml(date('M j, Y', strtotime($contract['contract_end_date']))) ?></strong> and doesn't renew itself.
                    <?= $notice >= $today ? 'Notice is due by ' . escapeHtml(date('M j, Y', strtotime($notice))) . '.' : '' ?> Renew it to keep it going.
                <?php } ?>
            </div>
            <?php if ($can_edit) { ?>
                <button type="button" class="btn btn-sm btn-<?= $state['key'] === 'ending' ? 'warning' : 'info' ?> ajax-modal" data-modal-url="modals/contract/contract_renew.php?id=<?= $contract_id ?>">Renew</button>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="row g-3">
        <div class="col-xl-4 col-lg-5">

            <?php if ($signing) { ?>
                <div class="card mb-3" data-contract-signing>
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-file-signature text-secondary me-2"></i>Signing</h3>
                        <?php if ($latest_state) { ?>
                            <span class="badge rounded-pill text-bg-<?= $latest_state['tone'] ?>"><i class="fas fa-fw <?= $latest_state['icon'] ?> me-1"></i><?= escapeHtml($latest_state['label']) ?></span>
                        <?php } ?>
                    </div>
                    <div class="card-body">
                        <?php if (!$latest) { ?>
                            <p class="small text-body-secondary mb-<?= $can_send ? '3' : '0' ?>">Not sent to the client to sign yet. They sign from a link in an email, or in the client portal; you countersign after.</p>
                            <?php if ($can_send) { ?>
                                <button type="button" class="btn btn-outline-primary btn-sm ajax-modal" data-modal-url="modals/contract/contract_send.php?id=<?= $contract_id ?>"><i class="fas fa-file-signature me-2"></i>Send to sign</button>
                            <?php } ?>
                        <?php } else { ?>
                            <ul class="itflow-contract-trail mb-3">
                                <?php foreach (contractSignatureTrail($latest) as [$when, $what, $detail]) {
                                    $icon = strpos($what, 'Sent') === 0 ? 'fa-paper-plane' : (strpos($what, 'Opened') === 0 ? 'fa-envelope-open' : (strpos($what, 'Withdrawn') === 0 ? 'fa-undo' : 'fa-signature')); ?>
                                    <li><i class="fas fa-fw <?= $icon ?>"></i><span><?= escapeHtml($what) ?><span class="d-block small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$when, $detail]))) ?></span></span></li>
                                <?php } ?>
                            </ul>
                            <?php if ($latest_state['key'] === 'sent' || $latest_state['key'] === 'viewed') { ?>
                                <label class="form-label small text-body-secondary mb-1">Signing link</label>
                                <div class="input-group input-group-sm mb-3">
                                    <span class="input-group-text"><i class="fas fa-fw fa-link"></i></span>
                                    <input type="text" class="form-control" name="signing_link" value="<?= escapeHtml(contractSignatureUrl($latest)) ?>" readonly aria-label="Signing link">
                                    <button type="button" class="btn btn-outline-secondary clipboardjs" data-clipboard-target="input[name=signing_link]" title="Copy the link"><i class="far fa-copy"></i></button>
                                </div>
                            <?php } ?>
                            <?php if ($outdated) { ?>
                                <div class="alert alert-warning small py-2" data-contract-outdated><i class="fas fa-fw fa-exclamation-triangle me-1"></i>The contract has changed since it was signed. Send it to sign again for the new version.</div>
                            <?php } ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($can_edit && $latest_state['key'] === 'countersign') { ?>
                                    <button type="button" class="btn btn-success btn-sm ajax-modal" data-modal-url="modals/contract/contract_countersign.php?id=<?= intval($latest['contract_signature_id']) ?>"><i class="fas fa-pen-nib me-2"></i>Countersign</button>
                                <?php } ?>
                                <a class="btn btn-outline-secondary btn-sm" href="contract_document.php?client_id=<?= $client_id ?>&contract_id=<?= $contract_id ?>&round=<?= intval($latest['contract_signature_id']) ?>"><i class="fas fa-eye me-2"></i><?= $latest_state['key'] === 'signed' ? 'Signed copy' : 'Copy sent' ?></a>
                                <a class="btn btn-outline-secondary btn-sm" href="post.php?export_contract_pdf=<?= $contract_id ?>&round=<?= intval($latest['contract_signature_id']) ?>" title="Download PDF"><i class="fas fa-file-pdf"></i></a>
                                <?php if ($can_edit && contractSignaturePending($latest)) { ?>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="dropdown" title="More"><i class="fas fa-fw fa-ellipsis-h"></i></button>
                                        <div class="dropdown-menu">
                                            <?php if ($latest_state['key'] !== 'countersign') { ?>
                                                <a class="dropdown-item confirm-link" href="post.php?resend_contract_signature=<?= intval($latest['contract_signature_id']) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                    data-confirm-title="Send the link again?" data-confirm-text="To <?= escapeHtml($latest['contract_signature_sent_to_name'] . ' at ' . $latest['contract_signature_sent_to_email']) ?>." data-confirm-button="Send"><i class="fas fa-fw fa-paper-plane text-secondary me-2"></i>Send the link again</a>
                                            <?php } ?>
                                            <a class="dropdown-item text-danger confirm-link" href="post.php?withdraw_contract_signature=<?= intval($latest['contract_signature_id']) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                                data-confirm-title="Withdraw the request?" data-confirm-text="Its link stops working for signing<?= $latest_state['key'] === 'countersign' ? ' - the signature already on it is set aside' : '' ?>." data-confirm-button="Withdraw"><i class="fas fa-fw fa-undo me-2"></i>Withdraw</a>
                                        </div>
                                    </div>
                                <?php } ?>
                                <?php if ($can_send && !contractSignaturePending($latest) && $status !== 'Draft') { ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm ajax-modal" data-modal-url="modals/contract/contract_send.php?id=<?= $contract_id ?>"><i class="fas fa-file-signature me-2"></i>Send to sign</button>
                                <?php } ?>
                            </div>
                            <?php if (count($rounds) > 1) { ?>
                                <details class="mt-3 small">
                                    <summary class="text-body-secondary">Earlier requests (<?= count($rounds) - 1 ?>)</summary>
                                    <ul class="list-unstyled mt-2 mb-0">
                                        <?php foreach (array_slice($rounds, 1) as $earlier) {
                                            $earlier_state = contractSignatureState($earlier); ?>
                                            <li class="d-flex align-items-center gap-2 py-1">
                                                <span class="badge rounded-pill text-bg-<?= $earlier_state['tone'] ?>"><?= escapeHtml($earlier_state['label']) ?></span>
                                                <a href="contract_document.php?client_id=<?= $client_id ?>&contract_id=<?= $contract_id ?>&round=<?= intval($earlier['contract_signature_id']) ?>"><?= escapeHtml(!empty($earlier['contract_signature_sent_at']) ? 'Sent ' . date('M j, Y', strtotime($earlier['contract_signature_sent_at'])) . ' to ' . $earlier['contract_signature_sent_to_name'] : 'Recorded ' . date('M j, Y', strtotime($earlier['contract_signature_created_at']))) ?></a>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </details>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-calendar-alt text-secondary me-2"></i>Term</h3></div>
                <div class="card-body">
                    <dl class="itflow-asset-dl mb-0">
                        <dt>Starts</dt><dd><?= $contract['contract_start_date'] ? escapeHtml(date('M j, Y', strtotime($contract['contract_start_date']))) : '<span class="text-body-secondary">Not set</span>' ?></dd>
                        <dt>Ends</dt><dd><?= $contract['contract_end_date'] ? escapeHtml(date('M j, Y', strtotime($contract['contract_end_date']))) : '<span class="text-body-secondary">No end date</span>' ?></dd>
                        <dt>Term</dt><dd><?= escapeHtml(contractTermLabel($contract['contract_term_months'])) ?></dd>
                        <dt>Renews</dt><dd><?= intval($contract['contract_auto_renew']) ? 'By itself, for another term' : 'Only when renewed' ?></dd>
                        <dt>Notice</dt>
                        <dd>
                            <?= intval($contract['contract_notice_days']) ? intval($contract['contract_notice_days']) . ' days' : 'None' ?>
                            <?php if ($notice !== '' && $status === 'Active') { ?><div class="small text-body-secondary">By <?= escapeHtml(date('M j, Y', strtotime($notice))) ?></div><?php } ?>
                        </dd>
                        <?php if ($contract['contract_renewed_at']) { ?><dt>Renewed</dt><dd title="<?= escapeHtml($contract['contract_renewed_at']) ?>"><?= escapeHtml(date('M j, Y', strtotime($contract['contract_renewed_at']))) ?></dd><?php } ?>
                        <?php if ($status === 'Cancelled') { ?>
                            <dt>Cancelled</dt>
                            <dd><?= $contract['contract_cancelled_at'] ? escapeHtml(date('M j, Y', strtotime($contract['contract_cancelled_at']))) : '' ?><?php if ($contract['contract_cancel_reason']) { ?><div class="small text-body-secondary"><?= escapeHtml($contract['contract_cancel_reason']) ?></div><?php } ?></dd>
                        <?php } ?>
                    </dl>
                </div>
            </div>

            <?php if ($billing_on) { ?>
                <div class="card mb-3" data-contract-recurring>
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-redo-alt text-secondary me-2"></i>Recurring invoice</h3>
                        <?php if ($recurring) { ?>
                            <span class="badge rounded-pill text-bg-<?= intval($recurring['recurring_invoice_status']) ? 'success' : 'secondary' ?>"><?= intval($recurring['recurring_invoice_status']) ? 'Running' : 'Paused' ?></span>
                        <?php } ?>
                    </div>
                    <div class="card-body">
                        <?php if ($recurring) {
                            $recurring_active = intval($recurring['recurring_invoice_status']) === 1; ?>
                            <div class="d-flex align-items-baseline justify-content-between gap-2 mb-2">
                                <a class="fw-semibold" href="recurring_invoice.php?client_id=<?= $client_id ?>&recurring_invoice_id=<?= intval($recurring['recurring_invoice_id']) ?>"><?= escapeHtml(billingNumber('recurring', $recurring)) ?></a>
                                <span class="fw-semibold text-nowrap"><?= escapeHtml(contractRecurringAmountText($recurring)) ?></span>
                            </div>
                            <dl class="itflow-asset-dl mb-2">
                                <dt>Next</dt>
                                <dd><?= $recurring_active ? escapeHtml(date('M j, Y', strtotime($recurring['recurring_invoice_next_date']))) : '<span class="text-body-secondary">' . ($status === 'Draft' ? 'Starts when the contract is active' : 'Paused') . '</span>' ?></dd>
                                <?php if (trim((string) $recurring['recurring_invoice_scope']) !== '') { ?><dt>Scope</dt><dd><?= escapeHtml($recurring['recurring_invoice_scope']) ?></dd><?php } ?>
                            </dl>
                            <div class="small text-body-secondary mb-<?= $can_edit ? '3' : '0' ?>">It runs while the contract is active, and is paused once it ends or its cancellation takes effect.</div>
                            <?php if ($can_edit) { ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-outline-secondary btn-sm" href="recurring_invoice.php?client_id=<?= $client_id ?>&recurring_invoice_id=<?= intval($recurring['recurring_invoice_id']) ?>"><i class="fas fa-eye me-2"></i>Open</a>
                                    <a class="btn btn-outline-secondary btn-sm confirm-link" href="post.php?unlink_contract_recurring=<?= $contract_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                        data-confirm-title="Unlink <?= escapeHtml(billingNumber('recurring', $recurring)) ?>?" data-confirm-text="It carries on as it is - <?= $recurring_active ? 'running' : 'paused' ?> - but no longer starts and stops with the contract." data-confirm-button="Unlink"><i class="fas fa-unlink me-2"></i>Unlink</a>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <p class="small text-body-secondary mb-<?= $can_link ? '3' : '0' ?>">Not billed by a recurring invoice. Make one, or link one of the client's - it runs while the contract is active.</p>
                            <?php if ($can_link) { ?>
                                <button type="button" class="btn btn-outline-primary btn-sm ajax-modal" data-modal-size="lg" data-modal-url="modals/contract/contract_recurring.php?id=<?= $contract_id ?>"><i class="fas fa-redo-alt me-2"></i>Recurring invoice</button>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-users text-secondary me-2"></i>People</h3></div>
                <div class="card-body">
                    <dl class="itflow-asset-dl mb-0">
                        <dt>Client</dt><dd><a href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($contract['client_name']) ?></a></dd>
                        <dt>Contact</dt><dd><?= $contract['contact_name'] ? '<a href="contact.php?client_id=' . $client_id . '&contact_id=' . intval($contract['contract_contact_id']) . '">' . escapeHtml($contract['contact_name']) . '</a>' : '<span class="text-body-secondary">None</span>' ?></dd>
                        <dt>Owner</dt><dd><?= $contract['owner_name'] ? escapeHtml($contract['owner_name']) : '<span class="text-body-secondary">No one - everyone is reminded</span>' ?></dd>
                        <dt>Added</dt><dd title="<?= escapeHtml($contract['contract_created_at']) ?>"><?= timeAgo($contract['contract_created_at']) ?></dd>
                    </dl>
                </div>
            </div>

            <?= auditLogHistoryCard($history, ['client_id' => $client_id]) ?>
        </div>

        <div class="col-xl-8 col-lg-7">

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-shield-alt text-secondary me-2"></i>What it covers</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-body-secondary text-uppercase fw-semibold mb-1">Support hours</div>
                            <div><?= $contract['contract_support_hours'] ? escapeHtml($contract['contract_support_hours']) : '<span class="text-body-secondary">Not set</span>' ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-body-secondary text-uppercase fw-semibold mb-1">Rates</div>
                            <div><?= $contract['contract_rate_standard'] !== null ? $money($contract['contract_rate_standard']) . '/hr' : '<span title="The client\'s own rate">' . $money($contract['client_rate']) . '/hr <span class="text-body-secondary small">(client\'s)</span></span>' ?></div>
                            <div class="small text-body-secondary"<?= $contract['contract_rate_after_hours'] !== null ? ' title="For tickets opened outside the business days and hours in Admin > Settings"' : '' ?>><?= $contract['contract_rate_after_hours'] !== null ? $money($contract['contract_rate_after_hours']) . '/hr after hours' : 'No after-hours rate' ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-body-secondary text-uppercase fw-semibold mb-1">Net terms</div>
                            <div><?php
                                $terms = $contract['contract_net_terms'] !== null ? intval($contract['contract_net_terms']) : intval($contract['client_net_terms']);
                                echo $terms ? "Net $terms" : 'Due on receipt';
                                echo $contract['contract_net_terms'] === null ? ' <span class="text-body-secondary small">(client\'s)</span>' : '';
                            ?></div>
                        </div>
                    </div>
                    <div class="small text-body-secondary text-uppercase fw-semibold mt-3 mb-1">SLA by priority</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0" data-contract-slas>
                            <tbody>
                            <?php foreach (CONTRACT_SLA_PRIORITIES as $priority => $part) {
                                $sla = $slas[intval($contract["contract_sla_{$part}_id"])] ?? null; ?>
                                <tr>
                                    <td class="ps-0" style="width: 7rem;"><?= ticketPriorityHtml($priority, 'detail') ?></td>
                                    <td>
                                        <?php if ($sla) { ?>
                                            <span class="fw-semibold"><?= escapeHtml($sla['sla_name']) ?></span><?= $sla['sla_archived_at'] ? ' <span class="badge text-bg-secondary">Archived</span>' : '' ?>
                                            <div class="small text-body-secondary"><?= escapeHtml(contractSlaTargets($sla)) ?></div>
                                        <?php } else { ?>
                                            <span class="text-body-secondary">The client's usual SLA</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer small text-body-secondary" data-contract-in-force>
                    <i class="fas fa-fw <?= $in_force ? 'fa-check-circle text-success' : 'fa-info-circle' ?> me-1"></i>
                    <?= $in_force ? 'In force now:' : 'While it is in force,' ?> new tickets get these SLAs, time bills at these rates - the after-hours one for tickets opened outside business hours - and new invoices are due on these terms. What it leaves empty, the client's own applies.
                </div>
            </div>

            <?php if ($show_hours) {
                $used_percent = $hours['bought'] > 0 ? max(0, min(100, round($hours['used'] / $hours['bought'] * 100))) : 0;
                $monthly = floatval($contract['contract_monthly_hours']); ?>
                <div class="card mb-3" data-contract-hours>
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                        <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-hourglass-half text-secondary me-2"></i>Prepaid hours</h3>
                        <?php if ($can_hours) { ?>
                            <?php if ($status === 'Active' && $hours['left'] > 0) { ?>
                                <form action="post.php" method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="contract_id" value="<?= $contract_id ?>">
                                    <button type="submit" name="draw_contract_hours" value="1" class="btn btn-sm btn-outline-secondary" title="Draw the time on the client's done tickets now, not tonight"><i class="fas fa-fw fa-arrow-down"></i><span class="d-none d-sm-inline ms-1">Draw now</span></button>
                                </form>
                            <?php } ?>
                            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/contract/contract_hours_add.php?id=<?= $contract_id ?>" title="Add hours"><i class="fas fa-fw fa-plus"></i><span class="d-none d-sm-inline ms-1">Add hours</span></button>
                        <?php } ?>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-sm-5">
                                <div class="small text-body-secondary text-uppercase fw-semibold">Left</div>
                                <div class="itflow-contract-hours-left <?= $hours_low ? 'text-warning-emphasis' : '' ?>" data-contract-hours-left><?= escapeHtml(contractHoursText($hours['left'])) ?></div>
                                <div class="small text-body-secondary">
                                    <?php if ($hours['bought'] > 0) { ?>
                                        Of <?= escapeHtml(contractHoursText($hours['bought'])) ?> &middot; <?= escapeHtml(contractHoursText($hours['used'])) ?> used
                                    <?php } else { ?>
                                        No hours to draw from<?= $monthly > 0 && $status !== 'Active' ? ' until it is active' : '' ?>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="col-sm-7">
                                <?php if ($hours['bought'] > 0) { ?>
                                    <div class="progress mb-2" style="height: .6rem;" role="progressbar" aria-label="Prepaid hours used" aria-valuenow="<?= $used_percent ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar bg-<?= $hours_low ? 'warning' : 'primary' ?>" style="width: <?= $used_percent ?>%"></div>
                                    </div>
                                <?php } ?>
                                <ul class="list-unstyled small text-body-secondary mb-0 d-flex flex-column gap-1">
                                    <?php if ($monthly > 0) { ?>
                                        <li><i class="fas fa-fw fa-redo me-1"></i><?= escapeHtml(contractHoursWords($monthly)) ?> included each month, for that month only</li>
                                    <?php } ?>
                                    <?php if ($hours['next_expiry'] !== '') { ?>
                                        <li><i class="fas fa-fw fa-calendar-times me-1"></i><?= escapeHtml(contractHoursText($hours['next_expiry_hours'])) ?> run out on <?= escapeHtml(date('M j, Y', strtotime($hours['next_expiry']))) ?></li>
                                    <?php } ?>
                                    <li><i class="fas fa-fw fa-bell me-1"></i><?= $hours_alert > 0 ? 'The owner is told under ' . escapeHtml(contractHoursText($hours_alert)) . ' left' : 'No one is told when they run low' ?></li>
                                    <?php if ($hours['lapsed'] > 0) { ?>
                                        <li><i class="fas fa-fw fa-hourglass-end me-1"></i><?= escapeHtml(contractHoursText($hours['lapsed'])) ?> ran out unused</li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>
                        <?php if ($status !== 'Active') { ?>
                            <div class="alert alert-secondary small py-2 mb-3"><i class="fas fa-fw fa-info-circle me-1"></i>Time is drawn from them only while the contract is active.</div>
                        <?php } elseif ($hours_low) { ?>
                            <div class="alert alert-warning small py-2 mb-3" data-contract-hours-low><i class="fas fa-fw fa-exclamation-triangle me-1"></i><?= $hours['left'] > 0 ? 'Running low.' : 'All used.' ?> Time beyond them goes to Unbilled Time to bill as usual.</div>
                        <?php } ?>

                        <?php if ($blocks) { ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0" data-contract-blocks>
                                    <thead class="small text-body-secondary">
                                    <tr>
                                        <th class="fw-semibold">Hours</th>
                                        <th class="fw-semibold d-none d-md-table-cell">Good for</th>
                                        <th class="fw-semibold text-end">Used</th>
                                        <th class="fw-semibold text-end">Left</th>
                                        <th class="fw-semibold d-none d-sm-table-cell ps-3">Paid</th>
                                        <?php if ($can_edit) { ?><th></th><?php } ?>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($blocks as $block_id => $block) {
                                        $what = $block['contract_block_note'] ?: ($block['contract_block_month'] ? 'Included hours' : 'Bought'); ?>
                                        <tr class="<?= $block['expired'] ? 'opacity-50' : '' ?>">
                                            <td class="min-w-0">
                                                <span class="fw-semibold"><?= escapeHtml(contractHoursText($block['contract_block_hours'])) ?></span>
                                                <span class="small text-body-secondary ms-1"><?= escapeHtml($what) ?></span>
                                                <?php if ($block['expired']) { ?><span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1">Ran out</span><?php } ?>
                                            </td>
                                            <td class="small text-nowrap d-none d-md-table-cell">
                                                <?= escapeHtml(date('M j, Y', strtotime($block['contract_block_bought']))) ?> &ndash; <?= $block['contract_block_expires'] ? escapeHtml(date('M j, Y', strtotime($block['contract_block_expires']))) : 'no end' ?>
                                            </td>
                                            <td class="text-end text-nowrap"><?= escapeHtml(contractHoursText($block['used'])) ?></td>
                                            <td class="text-end text-nowrap fw-semibold"><?= escapeHtml(contractHoursText($block['left'])) ?></td>
                                            <td class="small text-nowrap d-none d-sm-table-cell ps-3">
                                                <?php if (intval($block['contract_block_invoice_id']) && $block['invoice_number'] !== null) { ?>
                                                    <a href="invoice.php?client_id=<?= $client_id ?>&invoice_id=<?= intval($block['contract_block_invoice_id']) ?>"><?= escapeHtml(billingNumber('invoice', $block)) ?></a>
                                                    <span class="text-body-secondary"><?= escapeHtml($block['invoice_status']) ?></span>
                                                <?php } elseif ($block['contract_block_price'] !== null) { ?>
                                                    <?= $money($block['contract_block_price']) ?>
                                                <?php } else { ?>
                                                    <span class="text-body-secondary"><?= $block['contract_block_month'] ? 'In the contract' : '-' ?></span>
                                                <?php } ?>
                                            </td>
                                            <?php if ($can_edit) { ?>
                                                <td class="text-end">
                                                    <?php if (!intval($block['uses'])) { ?>
                                                        <a class="btn btn-sm btn-link text-danger p-0 confirm-link" href="post.php?delete_contract_block=<?= $block_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Delete these hours"
                                                            data-confirm-title="Delete <?= escapeHtml(contractHoursText($block['contract_block_hours'])) ?>?" data-confirm-text="Nothing was drawn from them.<?= intval($block['contract_block_invoice_id']) ? ' Their invoice stays - void it if it should go too.' : '' ?>" data-confirm-button="Delete"><i class="fas fa-fw fa-trash-alt"></i></a>
                                                    <?php } ?>
                                                </td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="small text-body-secondary"><?= $monthly > 0 ? "This month's included hours are added once it is active." : 'No hours yet.' . ($can_hours ? ' Add the hours the client bought, and invoice them if you like.' : '') ?></div>
                        <?php } ?>

                        <?php if ($hours_uses) { ?>
                            <div class="small text-body-secondary text-uppercase fw-semibold mt-4 mb-1">Drawn by tickets</div>
                            <ul class="list-group list-group-flush" data-contract-hours-uses>
                                <?php foreach ($hours_uses as $use) {
                                    $reference = $use['ticket_prefix'] . $use['ticket_number']; ?>
                                    <li class="list-group-item px-0 d-flex align-items-center gap-2">
                                        <div class="flex-grow-1 min-w-0">
                                            <a class="d-block text-truncate" href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= intval($use['ticket_id']) ?>"><span class="font-monospace small me-1"><?= escapeHtml($reference) ?></span><?= escapeHtml($use['ticket_subject']) ?></a>
                                            <div class="small text-body-secondary">
                                                Drawn <?= escapeHtml(date('M j, Y', strtotime($use['drawn_at']))) ?>
                                                <?= intval($use['covered']) ? '' : ' &middot; <span class="text-warning-emphasis">part - the rest is billed</span>' ?>
                                                <?= intval($use['ticket_invoice_id']) ? ' &middot; invoiced' : '' ?>
                                            </div>
                                        </div>
                                        <span class="fw-semibold text-nowrap"><?= escapeHtml(contractHoursText($use['hours'])) ?></span>
                                        <?php if ($can_edit && !intval($use['ticket_invoice_id'])) { ?>
                                            <a class="btn btn-sm btn-outline-secondary confirm-link" href="post.php?put_back_contract_hours=<?= intval($use['ticket_id']) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Put the hours back"
                                                data-confirm-title="Put <?= escapeHtml($reference) ?> back?" data-confirm-text="Its <?= escapeHtml(contractHoursText($use['hours'])) ?> go back on the prepaid hours, and the ticket goes to Unbilled Time to bill as usual. Tonight's draw leaves it alone." data-confirm-button="Put back"><i class="fas fa-fw fa-undo"></i></a>
                                        <?php } ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-file-alt text-secondary me-2"></i>Terms</h3></div>
                <div class="card-body">
                    <?php if (trim(strip_tags((string) $contract['contract_details'])) !== '') { ?>
                        <div class="prettyContent" style="overflow-wrap: anywhere;"><?= documentHtmlPurify((string) $contract['contract_details']) ?></div>
                    <?php } else { ?>
                        <div class="small text-body-secondary">No terms written down.<?php if ($can_edit) { ?> <a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/contract/contract_edit.php?id=<?= $contract_id ?>">Add them</a><?php } ?></div>
                    <?php } ?>
                </div>
            </div>

            <?php if (trim((string) $contract['contract_notes']) !== '') { ?>
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-sticky-note text-secondary me-2"></i>Notes</h3></div>
                    <div class="card-body"><div style="white-space: pre-wrap;"><?= escapeHtml($contract['contract_notes']) ?></div></div>
                </div>
            <?php } ?>
        </div>
    </div>

<?php }

require_once "../includes/footer.php";
