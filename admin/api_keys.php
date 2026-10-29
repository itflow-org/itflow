<?php

/*
 * Admin > API Keys - the keys other systems use the API with. Each acts as
 * an agent, so each shows who, what their role opens and which clients
 * they see, whether the key works (it expires, and an agent who is
 * disabled, archived or has no role turns every call away), when it was
 * last used and from where, and its calls over the last 30 days - each
 * opening what it was used for (modals/api/api_key_usage.php). A revoked key
 * has expired, so it moves to Expired, where it can be deleted. The work is in functions/api_keys.php; the handlers are
 * admin/post/api_keys.php.
 */

// Default Column Sortby Filter
$sort = "api_key_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$show_expired = ($_GET['show'] ?? '') === 'expired';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'api_key_name' => "api_key_name $order",
    'api_key_expire' => "api_key_expire $order, api_key_name ASC",
    'user_name' => "user_name IS NULL, user_name $order, api_key_name ASC",
    'api_key_last_used_at' => "api_key_last_used_at IS NULL, api_key_last_used_at $order, api_key_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'api_key_name';
}

// It works until the start of its expiry date - as apiKeyExpired() has it
$today = date('Y-m-d');
$search = "(api_key_name LIKE '%$q%' OR user_name LIKE '%$q%')";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS api_key_id, api_key_name, api_key_secret, api_key_created_at, api_key_expire, api_key_user_id, api_key_last_used_at,
        user_name, user_avatar, user_type, user_status, user_archived_at, user_role_id
    FROM api_keys
    LEFT JOIN users ON user_id = api_key_user_id
    WHERE $search AND api_key_expire " . ($show_expired ? '<=' : '>') . " '$today'
    ORDER BY {$sortable_columns[$sort]}, api_key_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$keys = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $keys[intval($row['api_key_id'])] = $row;
}
$roles = roleRows();
$access = userClientAccess(array_filter(array_map('intval', array_column($keys, 'api_key_user_id'))));
// What each is used for, the last 30 days (functions/api_keys.php)
$usage = apiKeyUsageSummary(array_keys($keys));

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(api_key_expire > '$today'), 0), COALESCE(SUM(api_key_expire <= '$today'), 0)
    FROM api_keys LEFT JOIN users ON user_id = api_key_user_id WHERE $search"));

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-key me-2"></i>API Keys</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/api/api_key_add.php"><i class="fas fa-plus me-2"></i>New API Key</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show_expired) { ?><input type="hidden" name="show" value="expired"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search API keys..." title="Key name or the agent it acts as">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="d-flex justify-content-end gap-2">
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_api_keys"
                                    data-confirm-title="Delete these keys?" data-confirm-text="Anything using them stops working." data-confirm-button="Delete">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <a class="nav-link <?= $show_expired ? '' : 'active' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '']))) ?>"><i class="fas fa-fw fa-key me-1"></i>Active <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
            <a class="nav-link <?= $show_expired ? 'active' : '' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => 'expired']))) ?>" title="Expired or revoked - nothing can use them"><i class="fas fa-fw fa-ban me-1"></i>Expired <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
        </nav>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" title="Select all on this page">
                        </div>
                    </th>
                    <th><?= $sort_link('api_key_name', 'Key') ?> <span class="fw-normal text-body-secondary small ms-2"><?= $sort_link('api_key_expire', 'by expiry') ?></span></th>
                    <th class="d-none d-md-table-cell"><?= $sort_link('user_name', 'Acts as') ?></th>
                    <th class="d-none d-lg-table-cell">Can open</th>
                    <th class="d-none d-sm-table-cell"><?= $sort_link('api_key_last_used_at', 'Last used') ?></th>
                    <th class="d-none d-xl-table-cell text-end">Calls <span class="fw-normal text-body-secondary small">(30 days)</span></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($keys as $api_key_id => $row) {
                    $name = escapeHtml($row['api_key_name']);
                    $role = $roles[intval($row['user_role_id'])] ?? null;
                    $expired = apiKeyExpired($row);
                    $agent_name = trim(str_replace(' (archived)', '', (string) $row['user_name']));
                    $last_used = $row['api_key_last_used_at'] ? '<span title="' . escapeHtml($row['api_key_last_used_at']) . '">' . escapeHtml(timeAgo($row['api_key_last_used_at'])) . '</span>' : '<span class="text-body-secondary">Never</span>';
                    $use = $usage[$api_key_id] ?? null;
                    if ($use && $use['last_ip'] !== '') {
                        $last_used .= '<div class="text-body-secondary font-monospace" title="Where the last call came from">' . escapeHtml($use['last_ip']) . '</div>';
                    }
                    $usage_url = "modals/api/api_key_usage.php?id=$api_key_id";
                    ob_start(); ?>
                    <?php if ($row['user_name'] !== null && intval($row['user_type']) === 1) { ?>
                        <div class="d-flex align-items-center gap-2">
                            <?= userAvatar($row['api_key_user_id'], $agent_name, $row['user_avatar'], 'sm') ?>
                            <div class="min-w-0">
                                <div class="text-truncate"><?= escapeHtml($agent_name) ?></div>
                                <div class="small text-body-secondary text-truncate"><i class="fas fa-fw <?= $role && intval($role['role_is_admin']) ? 'fa-user-shield' : 'fa-user-tag' ?> me-1"></i><?= $role ? escapeHtml($role['role_name']) : 'No role' ?></div>
                            </div>
                        </div>
                    <?php } else { ?>
                        <span class="small text-danger-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>Nobody</span>
                    <?php }
                    $agent_html = ob_get_clean();
                    $can_html = $role ? roleAccessHtml($role) . '<div class="small text-body-secondary mt-1"><i class="fas fa-fw fa-building me-1"></i>' . escapeHtml(userClientAccessText($access[intval($row['api_key_user_id'])] ?? ['allow' => [], 'deny' => []], intval($role['role_is_admin']))) . '</div>' : '<span class="small text-body-secondary">Nothing</span>';
                    ?>
                    <tr data-api-key-id="<?= $api_key_id ?>" class="<?= $expired ? 'text-body-secondary' : '' ?>">
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="api_key_ids[]" value="<?= $api_key_id ?>" aria-label="Select <?= $name ?>">
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw fa-key"></i></span>
                                <div class="min-w-0">
                                    <a class="ajax-modal fw-semibold <?= $expired ? 'text-body-secondary' : 'text-body' ?>" href="#" data-modal-url="modals/api/api_key_edit.php?id=<?= $api_key_id ?>" title="Edit <?= $name ?>"><?= $name ?></a>
                                    <div class="small text-body-secondary"><span class="font-monospace">••••<?= escapeHtml(substr((string) $row['api_key_secret'], -4)) ?></span> · made <?= date('M j, Y', strtotime($row['api_key_created_at'])) ?></div>
                                    <div class="mt-1"><?= apiKeyStatePill($row, $role) ?></div>
                                    <!-- On a phone, who it acts as and when it was used come along -->
                                    <div class="d-md-none mt-2"><?= $agent_html ?></div>
                                    <div class="d-lg-none mt-2"><?= $can_html ?></div>
                                    <div class="d-sm-none small mt-1">Last used <?= $last_used ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell"><?= $agent_html ?></td>
                        <td class="d-none d-lg-table-cell"><?= $can_html ?></td>
                        <td class="d-none d-sm-table-cell small text-nowrap"><?= $last_used ?></td>
                        <td class="d-none d-xl-table-cell text-end small text-nowrap" data-api-calls="<?= intval($use['calls'] ?? 0) ?>">
                            <?php if ($use) { ?>
                                <a class="ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $usage_url ?>" title="What it was used for"><?= number_format($use['calls']) ?></a>
                                <div class="text-body-secondary"><?= $use['endpoints'] ?> endpoint<?= $use['endpoints'] === 1 ? '' : 's' ?></div>
                            <?php } else { ?>
                                <span class="text-body-secondary">None</span>
                            <?php } ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/api/api_key_edit.php?id=<?= $api_key_id ?>">
                                        <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $usage_url ?>">
                                        <i class="fas fa-fw fa-chart-bar text-secondary me-2"></i>Usage
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <?php if (!$expired) { // revoked first, then deleted ?>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?revoke_api_key=<?= $api_key_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Revoke <?= $name ?>?" data-confirm-text="It stops working now. Anything using it is turned away." data-confirm-button="Revoke">
                                            <i class="fas fa-fw fa-ban me-2"></i>Revoke
                                        </a>
                                    <?php } else { ?>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_api_key=<?= $api_key_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Delete <?= $name ?>?" data-confirm-text="It has expired, so nothing can be using it." data-confirm-button="Delete">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php
    $empty_state = [
        'thing' => 'API keys',
        'icon' => 'fa-key',
        'filtered' => $q !== '',
        'archived' => $show_expired,
        'archived_title' => 'No expired API keys',
        'views' => ['show'],
        'text' => 'A key lets another system read and write ITFlow through its API, as one of your agents - it can do what their role allows, for the clients they see, until it expires.',
        'actions' => [['label' => 'New API Key', 'modal' => 'modals/api/api_key_add.php']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
