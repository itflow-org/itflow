<?php

/*
 * Contract - one agreement with a client: where it stands and when it renews
 * or ends (with the notice date), what it covers (support hours, an SLA for
 * each ticket priority, rates and net terms - the client's own where the
 * contract sets none), its terms, the internal notes, and its history from
 * the audit log. Make active, Renew, Cancel and Edit from the top. The work
 * is in functions/contract.php; the handlers are agent/post/contract.php.
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
                    <?php if ($status === 'Draft') { ?>
                        <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/contract/contract_activate.php?id=<?= $contract_id ?>"><i class="fas fa-check me-2"></i>Make active</button>
                    <?php } elseif ($contract['contract_end_date']) { ?>
                        <button type="button" class="btn btn-outline-primary ajax-modal" data-modal-url="modals/contract/contract_renew.php?id=<?= $contract_id ?>"><i class="fas fa-sync-alt me-2"></i>Renew</button>
                    <?php } ?>
                    <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/contract/contract_edit.php?id=<?= $contract_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
                    <div class="dropdown dropstart">
                        <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                        <div class="dropdown-menu">
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
                            <div class="small text-body-secondary"><?= $contract['contract_rate_after_hours'] !== null ? $money($contract['contract_rate_after_hours']) . '/hr after hours' : 'No after-hours rate' ?></div>
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
            </div>

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
