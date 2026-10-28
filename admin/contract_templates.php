<?php

/*
 * Admin > Contract Templates - the agreements you offer, to start a client's
 * contract from: its type, term, renewal and notice, support hours, rates,
 * net terms, an SLA for each ticket priority, and the terms themselves.
 * Each row says what it sets and how many contracts were started from it.
 * Tabs for the ones in use and the archived ones. The work is in
 * functions/contract.php; the handlers are admin/post/contract_template.php.
 */

// Default Column Sort by Filter
$sort = "contract_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$views = [
    '' => ['label' => 'In use', 'icon' => 'fa-file-contract', 'where' => 'contract_template_archived_at IS NULL', 'title' => 'Offered on a new contract'],
    'archived' => ['label' => 'Archived', 'icon' => 'fa-archive', 'where' => 'contract_template_archived_at IS NOT NULL', 'title' => 'Kept, but not offered'],
];
$show = (string) ($_GET['show'] ?? '');
$show = isset($views[$show]) ? $show : '';

$sortable_columns = [
    'contract_template_name' => "contract_template_name $order",
    'contract_template_type' => "contract_template_type $order, contract_template_name ASC",
    'contract_template_term_months' => "contract_template_term_months $order, contract_template_name ASC",
    'contracts' => "n_contracts $order, contract_template_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'contract_template_name';
}

$rows = [];
$num_rows = [0];
$view_counts = ['' => 0, 'archived' => 0];
if (contractsReady()) {
    $filters = "(contract_template_name LIKE '%$q%' OR contract_template_description LIKE '%$q%' OR contract_template_support_hours LIKE '%$q%')";
    $sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS contract_templates.*,
            (SELECT COUNT(*) FROM contracts WHERE contracts.contract_template_id = contract_templates.contract_template_id) AS n_contracts
        FROM contract_templates
        WHERE $filters AND {$views[$show]['where']}
        ORDER BY {$sortable_columns[$sort]} LIMIT $record_from, $record_to");
    $num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[] = $row;
    }
    $counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', array_map(function ($v) {
        return "COALESCE(SUM({$v['where']}), 0)";
    }, $views)) . " FROM contract_templates WHERE $filters"));
    $view_counts = array_combine(array_keys($views), array_map('intval', $counts));
}
$slas = contractSlaChoices();
// What a template leaves empty, a contract made from it gets from the defaults (Admin > Settings > Defaults, Admin > SLAs) - shown as such
$template_defaults = billingDefaults();
$template_default_slas = [];
foreach (CONTRACT_SLA_PRIORITIES as $priority => $part) {
    $template_default_slas[$priority] = intval(getTicketSlaId(0, $priority, false));
}

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($view) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => $view]));
};
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-file-contract me-2"></i>Contract Templates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/contract_template/contract_template_add.php" data-modal-size="lg">
                <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Template</span>
            </button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= escapeHtml($show) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search templates..." title="Name, description or support hours">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <?php foreach ($views as $view_key => $view) { ?>
                <a class="nav-link <?= $show === (string) $view_key ? 'active' : '' ?>" href="<?= escapeHtml($pill_url($view_key)) ?>" title="<?= escapeHtml($view['title']) ?>"><i class="fas fa-fw <?= $view['icon'] ?> me-1"></i><?= escapeHtml($view['label']) ?> <span class="badge rounded-pill ms-1"><?= intval($view_counts[$view_key]) ?></span></a>
            <?php } ?>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('contract_template_name', 'Template') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('contract_template_term_months', 'Term') ?></th>
                <th class="d-none d-lg-table-cell">Cover</th>
                <th class="text-end"><?= $sort_link('contracts', 'Contracts') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) {
                $id = intval($row['contract_template_id']);
                $name = escapeHtml($row['contract_template_name']);
                $type = CONTRACT_TYPES[$row['contract_template_type']] ?? CONTRACT_TYPES['other'];
                $archived = !empty($row['contract_template_archived_at']);
                $edit_url = "modals/contract_template/contract_template_edit.php?id=$id";
                // Each SLA with its priorities - "Standard Support (Low, Medium)", and "(Urgent - default)" for one the template leaves to the default
                $sla_names = ['own' => [], 'default' => []];
                foreach (CONTRACT_SLA_PRIORITIES as $priority => $part) {
                    $own = $slas[intval($row["contract_template_sla_{$part}_id"])] ?? null;
                    $sla = $own ?: ($slas[$template_default_slas[$priority]] ?? null);
                    if ($sla) {
                        $sla_names[$own ? 'own' : 'default'][$sla['sla_name']][] = $priority;
                    }
                }
                $sla_text = [];
                foreach ($sla_names as $whose => $names) {
                    foreach ($names as $sla_name => $priorities) {
                        $sla_text[] = "$sla_name (" . implode(', ', $priorities) . ($whose === 'default' ? ' - default' : '') . ')';
                    }
                }
                $net_text = function ($days) {
                    return intval($days) ? 'Net ' . intval($days) : 'Due on receipt';
                };
                $cover = array_filter([
                    $row['contract_template_support_hours'],
                    $row['contract_template_rate_standard'] !== null ? number_format((float) $row['contract_template_rate_standard'], 2) . '/hr'
                        : ($template_defaults['rate'] > 0 ? number_format($template_defaults['rate'], 2) . '/hr (default)' : ''),
                    $row['contract_template_net_terms'] !== null ? $net_text($row['contract_template_net_terms']) : $net_text($template_defaults['net_terms']) . ' (default)',
                ]);
                ?>
                <tr data-contract-template-id="<?= $id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true" title="<?= escapeHtml($type['label']) ?>"><i class="fas fa-fw <?= $type['icon'] ?>"></i></span>
                            <div class="min-w-0">
                                <a class="ajax-modal fw-semibold text-body" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><?= $name ?></a>
                                <div class="small text-body-secondary"><?= escapeHtml(contractTypeLabel($row['contract_template_type'])) ?><?= trim((string) $row['contract_template_description']) !== '' ? ' - ' . escapeHtml($row['contract_template_description']) : '' ?></div>
                                <?php if ($archived) { ?>
                                    <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['contract_template_archived_at'])) ?></div>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell small text-nowrap">
                        <?= escapeHtml(contractTermLabel($row['contract_template_term_months'])) ?>
                        <div class="text-body-secondary"><?= intval($row['contract_template_auto_renew']) ? 'Renews itself' : 'Renewed by hand' ?>, <?= intval($row['contract_template_notice_days']) ?> days notice</div>
                    </td>
                    <td class="d-none d-lg-table-cell small">
                        <?= $cover ? escapeHtml(implode(' · ', $cover)) : '<span class="text-body-tertiary">The default rate and terms</span>' ?>
                        <div class="text-body-secondary">
                            <?php if ($sla_text) {
                                echo escapeHtml(implode(' · ', $sla_text));
                            } else { ?>
                                No SLAs
                            <?php } ?>
                        </div>
                    </td>
                    <td class="text-end"><?= intval($row['n_contracts']) ?: '<span class="text-body-tertiary">0</span>' ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                <a class="dropdown-item" href="post.php?copy_contract_template=<?= $id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy</a>
                                <div class="dropdown-divider"></div>
                                <?php if ($archived) { ?>
                                    <a class="dropdown-item" href="post.php?restore_contract_template=<?= $id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore</a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger" href="post.php?archive_contract_template=<?= $id ?>&csrf_token=<?= $csrf ?>" title="Kept, but not offered on a new contract"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                                <?php } ?>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_contract_template=<?= $id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="<?= intval($row['n_contracts']) ? 'The ' . intval($row['n_contracts']) . ' contract' . (intval($row['n_contracts']) === 1 ? '' : 's') . ' started from it keep their terms.' : 'Nothing was started from it.' ?>" data-confirm-button="Delete">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
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
        'thing' => 'contract templates',
        'icon' => 'fa-file-contract',
        'filtered' => $q !== '',
        'archived' => $show === 'archived',
        'views' => ['show'],
        'text' => 'The agreements you offer - Fully managed, Block hours, Break/fix - to start a client\'s contract from.',
        'actions' => [['label' => 'New Template', 'modal' => 'modals/contract_template/contract_template_add.php', 'size' => 'lg']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
