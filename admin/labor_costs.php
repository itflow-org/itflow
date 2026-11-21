<?php

/*
 * Admin > Labor Costs - what an hour of each agent costs the company (functions/labor_cost.php),
 * with the date each rate counts from, and the company default for agents with none. Client
 * Profitability costs the hours on each client's tickets at them. An agent's rates are in their
 * window (modals/labor_cost/labor_cost_rates.php); the handlers are admin/post/labor_cost.php.
 * The audit log names the agent, never the amount.
 */

// Default Column Sortby Filter
$sort = "user_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

if (!laborCostReady()) { ?>
    <div class="alert alert-warning">Labor costs need the database update - <a href="update.php" class="alert-link">Maintenance &gt; Update</a>.</div>
    <?php require_once "../includes/footer.php";
    return;
}

$views = [
    '' => ['label' => 'Agents', 'icon' => 'fa-user', 'title' => 'Active agents'],
    'archived' => ['label' => 'Archived', 'icon' => 'fa-archive', 'title' => 'Archived agents with a rate - their time in earlier periods is still costed'],
];
$show = (string) ($_GET['show'] ?? '');
$show = isset($views[$show]) ? $show : '';

$rates = laborCostRates();
$default = laborCostDefault();
$today = date('Y-m-d');

// Active agents, and archived ones that have a rate - then searched, sorted and paged here
$search = mb_strtolower(trim(stripslashes((string) ($q ?? ''))));
$rows = [];
$view_counts = ['' => 0, 'archived' => 0];
$result = mysqli_query($mysqli, "SELECT user_id, user_name, user_email, user_avatar, user_archived_at FROM users WHERE user_type = 1" . serviceAccountsOutSql() . "
    AND (user_archived_at IS NULL OR user_id IN (SELECT user_cost_rate_user_id FROM user_cost_rates))");
while ($row = mysqli_fetch_assoc($result)) {
    if ($search !== '' && mb_strpos(mb_strtolower($row['user_name'] . ' ' . $row['user_email']), $search) === false) {
        continue;
    }
    $view = $row['user_archived_at'] === null ? '' : 'archived';
    $view_counts[$view]++;
    if ($view !== $show) {
        continue;
    }
    $user_id = intval($row['user_id']);
    $list = $rates[$user_id] ?? [];
    $row['now'] = laborCostRateOn($rates, $user_id, $today, null);
    $row['since'] = '';
    foreach ($list as [$from]) {
        if ($from <= $today || $row['since'] === '') {
            $row['since'] = $from;
        }
    }
    $row['rate_count'] = count($list);
    $rows[] = $row;
}

$sortable_columns = [
    'user_name' => function ($row) { return mb_strtolower($row['user_name']); },
    'now' => function ($row) { return $row['now'] ?? -1; },
    'since' => function ($row) { return $row['since']; },
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'user_name';
}
$key = $sortable_columns[$sort];
usort($rows, function ($a, $b) use ($key, $order) {
    $cmp = $key($a) <=> $key($b);
    if ($cmp === 0) {
        $cmp = strcasecmp($a['user_name'], $b['user_name']);
    }
    return $order === 'DESC' ? -$cmp : $cmp;
});
$num_rows = [count($rows)];
$rows = array_slice($rows, $record_from, $record_to);

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($view) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => $view]));
};
$money = function ($amount) use ($currency_format, $session_company_currency) {
    return escapeHtml(billingMoney($amount, $session_company_currency));
};

?>

<div class="card" data-labor-costs>
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-user-tag me-2"></i>Labor Costs</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/labor_cost/labor_cost_default.php" data-labor-default-button>
                <i class="fas fa-fw fa-users"></i><span class="d-none d-lg-inline ms-2">Company default: <?= $default === null ? 'none' : $money($default) . ' / h' ?></span>
            </button>
        </div>
    </div>

    <div class="card-header py-3">
        <p class="small text-body-secondary mb-3">
            What an hour of each agent costs the company - their wage, payroll taxes and benefits. <a href="/agent/reports/client_profitability.php">Client Profitability</a>
            costs the hours on each client's tickets at these rates. A new rate counts from its date, so earlier periods keep the rate they had; an agent's first rate also
            covers the time before it. Agents with no rate take the company default. Only admins see this page.
        </p>
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= escapeHtml($show) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search agents..." title="Name or email">
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
                <th class="ps-3"><?= $sort_link('user_name', 'Agent') ?></th>
                <th><?= $sort_link('now', 'Cost per hour now') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('since', 'Since') ?></th>
                <th class="d-none d-sm-table-cell">Rates</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row) {
                $user_id = intval($row['user_id']);
                $rates_url = "modals/labor_cost/labor_cost_rates.php?user_id=$user_id";
                ?>
                <tr data-labor-agent="<?= $user_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-none d-sm-inline-flex"><?= userAvatar($user_id, $row['user_name'], $row['user_avatar']) ?></span>
                            <div class="min-w-0">
                                <a class="ajax-modal fw-semibold text-body" href="#" data-modal-url="<?= $rates_url ?>" title="Rates for <?= escapeHtml($row['user_name']) ?>"><?= escapeHtml($row['user_name']) ?></a>
                                <div class="small text-body-secondary text-truncate"><?= escapeHtml($row['user_email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td data-labor-now>
                        <?php if ($row['now'] !== null) { ?>
                            <span class="fw-semibold"><?= $money($row['now']) ?></span>
                        <?php } elseif ($default !== null) { ?>
                            <span class="text-body-secondary">Default, <?= $money($default) ?></span>
                        <?php } else { ?>
                            <span class="text-warning-emphasis"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Not set</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell text-nowrap text-body-secondary"><?= $row['since'] !== '' ? escapeHtml(date('M j, Y', strtotime($row['since']))) : '-' ?></td>
                    <td class="d-none d-sm-table-cell text-body-secondary"><?= $row['rate_count'] ? number_format($row['rate_count']) : '<span class="text-body-tertiary">None</span>' ?></td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-sm btn-light border ajax-modal" data-modal-url="<?= $rates_url ?>"><i class="fas fa-fw fa-dollar-sign"></i><span class="d-none d-md-inline ms-1"><?= $row['rate_count'] ? 'Rates' : 'Set a rate' ?></span></button>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'agents',
        'icon' => 'fa-user-tag',
        'filtered' => $q !== '',
        'archived' => $show === 'archived',
        'views' => ['show'],
        'text' => 'Every active agent is listed here, to give a cost per hour.',
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
