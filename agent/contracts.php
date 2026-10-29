<?php

/*
 * Contracts - one client's agreements, or every one the agent can see. Along
 * the top, how many are in force, how many end soon without renewing, how
 * many renew soon (a chance to review them with the client) and the drafts.
 * Tabs: Active (the default), Due soon (within a month of their notice
 * date), Signing (out for signature, or to countersign), Drafts, Ended
 * (ended or cancelled) and All. Each row says where its signing stands,
 * and how many prepaid hours are left on it. Across every client, archived
 * clients' contracts are left out - they are on the client's own page.
 * The work is in functions/contract.php; the handlers are
 * agent/post/contract.php.
 */

// Default Column Sortby/Order Filter
$sort = "contract_end_date";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND contract_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_all.php";
    // An archived client's contracts are on their own page, not here
    $client_query = "AND " . contractClientLiveSql();
    $client_url = '';
}

enforceUserPermission('module_sales');
$can_edit = lookupUserPermission('module_sales') >= 2;
$can_delete = lookupUserPermission('module_sales') >= 3;

// Client Filter (all clients)
$client = '';
if (!$client_url && !empty($_GET['client'])) {
    $client = intval($_GET['client']);
    $client_query = "AND contract_client_id = $client AND " . contractClientLiveSql();
}

$type_filter = isset(CONTRACT_TYPES[$_GET['type'] ?? '']) ? $_GET['type'] : '';
$type_query = $type_filter !== '' ? "AND contract_type = '" . escapeSql($type_filter) . "'" : '';

$views = contractViews();
$view = isset($views[$_GET['show'] ?? '']) ? $_GET['show'] : 'active';

$sortable_columns = [
    'contract_name' => "contract_name $order",
    'client_name' => "client_name $order, contract_name ASC",
    'contract_type' => "contract_type $order, contract_name ASC",
    'contract_start_date' => "contract_start_date IS NULL, contract_start_date $order",
    'contract_end_date' => "contract_end_date IS NULL, contract_end_date $order",
    'owner_name' => "user_name IS NULL, user_name $order",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'contract_end_date';
}

$scope_query = clientScopeSql('contract_client_id');
$search_query = "AND (contract_name LIKE '%$q%' OR client_name LIKE '%$q%' OR contract_support_hours LIKE '%$q%' OR contract_notes LIKE '%$q%')";
$joins = "LEFT JOIN clients ON contract_client_id = client_id LEFT JOIN users ON contract_owner_id = user_id";
$filters = "AND contract_archived_at IS NULL $scope_query $client_query $type_query $search_query";

$contracts = [];
$num_rows = [0];
$counts = array_fill_keys(array_keys($views), 0);
$stats = ['active' => 0, 'ending' => 0, 'renewing' => 0, 'draft' => 0];
if (contractsReady()) {
    $sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS contracts.*, client_name, client_archived_at, user_name AS owner_name
        FROM contracts $joins
        WHERE {$views[$view]['where']} $filters
        ORDER BY {$sortable_columns[$sort]}, contract_id ASC LIMIT $record_from, $record_to");
    $num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));
    while ($row = mysqli_fetch_assoc($sql)) {
        $contracts[] = $row;
    }

    // Where each one's latest signing request stands
    $signing = [];
    if ($contracts && contractSignaturesReady()) {
        $ids = implode(',', array_map(function ($row) { return intval($row['contract_id']); }, $contracts));
        $sql = mysqli_query($mysqli, "SELECT contract_signature_id, contract_signature_contract_id, contract_signature_url_key, contract_signature_sent_at, contract_signature_viewed_at, contract_signature_client_name,
                contract_signature_client_signed_at, contract_signature_agent_signed_at, contract_signature_withdrawn_at
            FROM contract_signatures WHERE contract_signature_id IN (SELECT MAX(contract_signature_id) FROM contract_signatures WHERE contract_signature_contract_id IN ($ids) GROUP BY contract_signature_contract_id)");
        $by_id = array_column($contracts, null, 'contract_id');
        while ($round = mysqli_fetch_assoc($sql)) {
            $id = intval($round['contract_signature_contract_id']);
            $signing[$id] = contractSignatureState($round + array_intersect_key($by_id[$id] ?? [], ['contract_status' => 1, 'client_archived_at' => 1]));
        }
    }

    // The ones that can't be deleted, and why
    $delete_blocked = $can_delete ? contractDeleteBlockers($contracts) : [];

    // Prepaid hours left on each
    $hours_left = contractHoursLeftFor(array_column($contracts, 'contract_id'));

    $row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', array_map(function ($v) {
        return "COALESCE(SUM({$v['where']}), 0)";
    }, $views)) . " FROM contracts $joins WHERE 1 $filters"));
    $counts = array_combine(array_keys($views), array_map('intval', $row));

    // The figures along the top - everything in this scope, whatever the search
    $soon = $views['soon']['where'];
    $stats = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT
            COALESCE(SUM(contract_status = 'Active'), 0) AS active,
            COALESCE(SUM($soon AND contract_auto_renew = 0), 0) AS ending,
            COALESCE(SUM($soon AND contract_auto_renew = 1), 0) AS renewing,
            COALESCE(SUM(contract_status = 'Draft'), 0) AS draft
        FROM contracts WHERE contract_archived_at IS NULL $scope_query $client_query"));
}

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};
$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$add_url = 'modals/contract/contract_add.php' . ($client_url ? "?client_id=$client_id" : '');
$csrf = $_SESSION['csrf_token'];

?>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $view === 'active' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => 'active'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-check-circle me-1"></i>In force</div>
            <div class="itflow-billing-stat-value"><?= intval($stats['active']) ?></div>
            <div class="itflow-billing-stat-note">Active contracts</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $view === 'soon' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => 'soon'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-exclamation-triangle me-1"></i>Ending soon</div>
            <div class="itflow-billing-stat-value <?= $stats['ending'] ? 'text-warning-emphasis' : '' ?>"><?= intval($stats['ending']) ?></div>
            <div class="itflow-billing-stat-note">Won't renew themselves</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat" href="<?= escapeHtml($filter_url(['show' => 'soon'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-sync-alt me-1"></i>Renewing soon</div>
            <div class="itflow-billing-stat-value"><?= intval($stats['renewing']) ?></div>
            <div class="itflow-billing-stat-note">Time to review them with the client</div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="itflow-billing-stat <?= $view === 'draft' ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => 'draft'])) ?>">
            <div class="itflow-billing-stat-label"><i class="fas fa-pencil-alt me-1"></i>Drafts</div>
            <div class="itflow-billing-stat-value"><?= intval($stats['draft']) ?></div>
            <div class="itflow-billing-stat-note">Not in force yet</div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-contract me-2"></i>Contracts</h3>
        <div class="card-tools">
            <?php if ($can_edit) { ?>
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= escapeHtml($add_url) ?>"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Contract</span></button>
            <?php } ?>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="show" value="<?= escapeHtml($view) ?>">
            <div class="row g-2 align-items-center">
                <div class="<?= $client_url ? 'col-lg-8' : 'col-lg-6' ?> col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search contracts..." title="Name, client, support hours or notes">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="<?= $client_url ? 'col-lg-4' : 'col-lg-3' ?> col-md-6">
                    <select class="form-select select2" name="type" onchange="this.form.submit()" aria-label="Type">
                        <option value="">- Any type -</option>
                        <?php foreach (CONTRACT_TYPES as $key => $type) { ?>
                            <option value="<?= $key ?>" <?= $type_filter === $key ? 'selected' : '' ?> data-icon="fas <?= $type['icon'] ?>"><?= escapeHtml($type['label']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if (!$client_url) { ?>
                    <div class="col-lg-3 col-md-6">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="">- All Clients -</option>
                            <?php
                            if (contractsReady()) {
                                $sql_clients_filter = mysqli_query($mysqli, "SELECT DISTINCT client_id, client_name, client_abbreviation FROM clients
                                    JOIN contracts ON contract_client_id = client_id WHERE 1 " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                    $client_option_id = intval($row['client_id']); ?>
                                    <option <?= $client === $client_option_id ? 'selected' : '' ?> value="<?= $client_option_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                                <?php }
                            } ?>
                        </select>
                    </div>
                <?php } ?>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($views as $key => $v) {
                $alarm = $key === 'soon' && $counts[$key]; ?>
                <a class="nav-link text-nowrap <?= $view === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key])) ?>"<?= !empty($v['title']) ? ' title="' . escapeHtml($v['title']) . '"' : '' ?>>
                    <?php if ($v['icon'] !== '') { ?><i class="fas fa-fw <?= $v['icon'] ?> me-1"></i><?php } ?><?= $v['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? 'text-bg-warning' : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="<?= $num_rows[0] ? '' : 'd-none' ?> text-nowrap table-light">
            <tr>
                <th class="ps-3"><?= $sort_link('contract_name', 'Contract') ?></th>
                <?php if (!$client_url) { ?>
                    <th><?= $sort_link('client_name', 'Client') ?></th>
                <?php } ?>
                <th class="d-none d-md-table-cell"><?= $sort_link('contract_start_date', 'Term') ?></th>
                <th><?= $sort_link('contract_end_date', 'Renewal') ?></th>
                <th class="d-none d-xl-table-cell"><?= $sort_link('owner_name', 'Owner') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($contracts as $row) {
                $contract_id = intval($row['contract_id']);
                $row_client_id = intval($row['contract_client_id']);
                $row_url = "contract.php?client_id=$row_client_id&contract_id=$contract_id";
                $state = contractState($row);
                $type = CONTRACT_TYPES[$row['contract_type']] ?? CONTRACT_TYPES['other'];
                $notice = contractNoticeDate($row);
                ?>
                <tr data-contract-id="<?= $contract_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true" title="<?= escapeHtml($type['label']) ?>"><i class="fas fa-fw <?= $type['icon'] ?>"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold" href="<?= $row_url ?>"><?= escapeHtml($row['contract_name']) ?></a>
                                <div class="small text-body-secondary"><?= escapeHtml(contractTypeLabel($row['contract_type'])) ?>
                                    <?php $sign = $signing[$contract_id] ?? null;
                                    if ($sign && in_array($sign['key'], ['sent', 'viewed', 'countersign'], true)) { ?>
                                        · <span class="text-<?= $sign['key'] === 'countersign' ? 'warning' : 'primary' ?>-emphasis" title="<?= escapeHtml($sign['when']) ?>"><i class="fas fa-fw <?= $sign['icon'] ?>"></i><?= escapeHtml($sign['label']) ?></span>
                                    <?php } elseif ($sign && $sign['key'] === 'signed') { ?>
                                        · <span class="text-success-emphasis" title="<?= escapeHtml($sign['when']) ?>"><i class="fas fa-fw fa-file-signature"></i>Signed</span>
                                    <?php } ?>
                                    <?php if (isset($hours_left[$contract_id])) {
                                        $left = $hours_left[$contract_id]['left'];
                                        $low = $row['contract_status'] === 'Active' && ($left <= 0 || $left < floatval($row['contract_hours_alert'] ?? 0)); ?>
                                        · <span class="<?= $low ? 'text-warning-emphasis' : '' ?>" title="Prepaid hours left, of <?= escapeHtml(contractHoursText($hours_left[$contract_id]['bought'])) ?>" data-contract-hours-chip><i class="fas fa-fw fa-hourglass-half"></i><?= escapeHtml(contractHoursText($left)) ?> left</span>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <?php if (!$client_url) { ?>
                        <td><a class="text-reset" href="contracts.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                    <?php } ?>
                    <td class="d-none d-md-table-cell text-nowrap small">
                        <?php if ($row['contract_start_date']) { ?>
                            <?= escapeHtml(date('M j, Y', strtotime($row['contract_start_date']))) ?><?= $row['contract_end_date'] ? ' - ' . escapeHtml(date('M j, Y', strtotime($row['contract_end_date']))) : '' ?>
                        <?php } else { ?>
                            <span class="text-body-tertiary">Not started</span>
                        <?php } ?>
                        <div class="text-body-secondary"><?= escapeHtml(contractTermLabel($row['contract_term_months'])) ?><?= intval($row['contract_auto_renew']) ? ', renews itself' : '' ?></div>
                    </td>
                    <td class="text-nowrap">
                        <?= contractStateHtml($row) ?>
                        <div class="small text-body-secondary"><?= escapeHtml($state['when']) ?></div>
                        <?php if ($notice !== '' && in_array($state['key'], ['renewing', 'ending', 'active'], true) && $notice >= date('Y-m-d')) { ?>
                            <div class="small <?= in_array($state['key'], ['renewing', 'ending'], true) ? 'text-warning-emphasis' : 'text-body-tertiary' ?>">Notice by <?= escapeHtml(date('M j, Y', strtotime($notice))) ?></div>
                        <?php } ?>
                    </td>
                    <td class="d-none d-xl-table-cell small"><?= $row['owner_name'] ? escapeHtml($row['owner_name']) : '<span class="text-body-tertiary">No one</span>' ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="<?= $row_url ?>"><i class="fas fa-fw fa-eye text-secondary me-2"></i>Open</a>
                                <?php if ($can_edit) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/contract/contract_edit.php?id=<?= $contract_id ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                    <?php if ($row['contract_status'] !== 'Draft' && $row['contract_end_date']) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/contract/contract_renew.php?id=<?= $contract_id ?>"><i class="fas fa-fw fa-sync-alt text-secondary me-2"></i>Renew</a>
                                    <?php } ?>
                                <?php } ?>
                                <?php if ($can_delete) { ?>
                                    <div class="dropdown-divider"></div>
                                    <?php if (isset($delete_blocked[$contract_id])) { ?>
                                        <div class="dropdown-item-text small text-body-secondary text-wrap" style="max-width: 18rem;" data-delete-blocked><i class="fas fa-fw fa-lock me-1"></i>Can't be deleted. <?= escapeHtml($delete_blocked[$contract_id]) ?></div>
                                    <?php } else { ?>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_contract=<?= $contract_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Delete <?= escapeHtml($row['contract_name']) ?>?" data-confirm-text="<?= escapeHtml(contractDeleteWarning($row)) ?>" data-confirm-button="Delete">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'contracts',
        'icon' => 'fa-file-contract',
        'filtered' => (bool) $q || $type_filter !== '',
        'views' => ['show'],
        'view' => emptyStatePill($views, $view, 'active', ['soon']),
        'text' => 'The agreement with each client - what it covers, its SLAs and rates, and when it renews.',
        'actions' => $can_edit ? [['label' => 'New Contract', 'modal' => $add_url, 'size' => 'lg']] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php require_once "../includes/footer.php";
