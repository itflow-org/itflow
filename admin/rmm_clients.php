<?php

/*
 * Admin > RMM Providers > a provider's clients (functions/rmm.php). Each of the RMM's clients
 * is linked to the ITFlow client its devices go to, or not linked yet - with the client of the
 * same name suggested. Linking brings that client's devices in straight away; after that,
 * every 15 minutes (cron/rmm_sync.php) or with Sync now. The handlers are admin/post/rmm.php.
 */

// Default Column Sortby Filter
$sort = "rmm_client_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$provider = rmmProviderRow($_GET['provider_id'] ?? 0);
$kind = $provider ? rmmKind($provider) : null;

if (!$provider || !$kind) { ?>
    <div class="alert alert-secondary"><i class="fas fa-fw fa-question-circle me-2"></i><?= $provider ? 'This ITFlow doesn\'t know that provider\'s kind of RMM.' : 'That RMM provider is gone - it may have been deleted.' ?> <a href="rmm_providers.php" class="alert-link">RMM Providers</a></div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$provider_id = intval($provider['rmm_provider_id']);
$name = rmmProviderName($provider);
$short = $kind['short'];
$on = intval($provider['rmm_provider_active']) === 1;
$usable = rmmProviderUsable($provider);
$csrf = $_SESSION['csrf_token'];
$edit_url = "modals/rmm/rmm_provider_edit.php?id=$provider_id";
$suggestions = rmmClientSuggestions($provider_id);

// The views - every client it has, the ones not linked yet, the linked ones, and the ones it no longer has
$shows = [
    '' => ['label' => 'All', 'icon' => 'fa-list', 'sql' => 'rmm_client_removed_at IS NULL', 'title' => "Every client $name has"],
    'unlinked' => ['label' => 'Not linked', 'icon' => 'fa-unlink', 'sql' => 'rmm_client_removed_at IS NULL AND rmm_client_client_id = 0', 'title' => 'Their devices don\'t come in until they are linked'],
    'linked' => ['label' => 'Linked', 'icon' => 'fa-link', 'sql' => 'rmm_client_removed_at IS NULL AND rmm_client_client_id > 0', 'title' => 'Their devices come in as assets'],
    'removed' => ['label' => "Gone from $short", 'icon' => 'fa-ghost', 'sql' => 'rmm_client_removed_at IS NOT NULL', 'title' => "$name no longer has them"],
];
$show = isset($shows[$_GET['show'] ?? '']) ? (string) ($_GET['show'] ?? '') : '';
$view_counts = [];
foreach ($shows as $key => $view) {
    $view_counts[$key] = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM rmm_clients WHERE rmm_client_provider_id = $provider_id AND {$view['sql']}"))[0]);
}
$filter_url = function (array $change) {
    $get = array_merge($_GET, $change);
    unset($get['page']);
    $get = array_filter($get, function ($value) {
        return $value !== null && $value !== '';
    });
    return '?' . http_build_query($get);
};

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'rmm_client_name' => "rmm_client_name $order",
    'rmm_client_devices' => "rmm_client_devices $order, rmm_client_name ASC",
    'client_name' => "client_name IS NULL, client_name $order, rmm_client_name ASC",
    'rmm_client_synced_at' => "rmm_client_synced_at IS NULL, rmm_client_synced_at $order, rmm_client_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'rmm_client_name';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS rmm_clients.*, client_name, client_abbreviation, client_archived_at,
    (SELECT COUNT(*) FROM rmm_devices WHERE rmm_device_rmm_client_id = rmm_clients.rmm_client_id AND rmm_device_removed_at IS NULL AND rmm_device_asset_id > 0) AS assets_in,
    (SELECT COUNT(*) FROM rmm_devices WHERE rmm_device_rmm_client_id = rmm_clients.rmm_client_id AND rmm_device_removed_at IS NULL AND rmm_device_asset_id = 0) AS assets_deleted,
    (SELECT COUNT(*) FROM rmm_devices WHERE rmm_device_rmm_client_id = rmm_clients.rmm_client_id AND rmm_device_removed_at IS NULL AND rmm_device_status = 'overdue') AS devices_overdue
    FROM rmm_clients
    LEFT JOIN clients ON client_id = rmm_client_client_id AND rmm_client_client_id > 0
    WHERE rmm_client_provider_id = $provider_id AND {$shows[$show]['sql']}
    AND (rmm_client_name LIKE '%$q%' OR client_name LIKE '%$q%')
    ORDER BY {$sortable_columns[$sort]}, rmm_client_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// The ITFlow clients the suggestions name
$suggested_names = [];
if ($suggestions) {
    $suggested_sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_id IN (" . implode(', ', array_map('intval', $suggestions)) . ")");
    while ($row = mysqli_fetch_assoc($suggested_sql)) {
        $suggested_names[intval($row['client_id'])] = $row['client_name'];
    }
}

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$dashboard = rmmDashboardUrl($provider);
$devices_in = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM rmm_devices WHERE rmm_device_provider_id = $provider_id AND rmm_device_removed_at IS NULL AND rmm_device_asset_id > 0"))[0]);

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="<?= escapeHtml($kind['icon']) ?> fa-fw me-2"></i><?= escapeHtml($name) ?> <span class="fw-normal opacity-75">&middot; Clients</span>
            <?php if (!$on) { ?><span class="badge text-bg-secondary ms-2 fw-normal" data-rmm-provider-state="off" title="Nothing is brought in, and assets keep what it last said">Off</span><?php } ?>
        </h3>
        <div class="card-tools">
            <a class="btn btn-outline-light me-1" href="rmm_providers.php" title="Back to RMM Providers"><i class="fas fa-fw fa-arrow-left"></i><span class="d-none d-lg-inline ms-2">RMM Providers</span></a>
            <?php if ($dashboard !== '') { ?>
                <a class="btn btn-outline-light me-1" href="<?= escapeHtml($dashboard) ?>" target="_blank" rel="noopener noreferrer" title="<?= escapeHtml($name) ?>'s dashboard"><i class="fas fa-fw fa-external-link-alt"></i><span class="d-none d-lg-inline ms-2">Open <?= escapeHtml($short) ?></span></a>
            <?php } ?>
            <?php if ($usable) { ?>
                <a class="btn btn-primary" href="post.php?sync_rmm=<?= $provider_id ?>&amp;csrf_token=<?= $csrf ?>" data-rmm-sync title="Bring in the linked clients' devices now"><i class="fas fa-fw fa-sync-alt"></i><span class="d-none d-lg-inline ms-2">Sync now</span></a>
            <?php } ?>
            <div class="dropdown dropstart d-inline-block ms-1">
                <button class="btn btn-outline-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                    <a class="dropdown-item" href="post.php?test_rmm_provider=<?= $provider_id ?>&amp;csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-vial text-secondary me-2"></i>Test the connection</a>
                    <?php if ($suggestions) { ?>
                        <a class="dropdown-item" href="post.php?link_rmm_suggested=all&amp;provider_id=<?= $provider_id ?>&amp;csrf_token=<?= $csrf ?>" data-rmm-sync><i class="fas fa-fw fa-link text-secondary me-2"></i>Link the <?= count($suggestions) ?> suggested</a>
                    <?php } ?>
                    <?php if ($on) { ?>
                        <a class="dropdown-item confirm-link" href="post.php?turn_rmm_provider_off=<?= $provider_id ?>&amp;csrf_token=<?= $csrf ?>"
                            data-confirm-title="Turn <?= escapeHtml($name) ?> off?" data-confirm-button="Turn off"
                            data-confirm-text="Nothing more is brought in from it. Assets keep what it last said about them until it is on again.">
                            <i class="fas fa-fw fa-toggle-off text-secondary me-2"></i>Turn off
                        </a>
                    <?php } else { ?>
                        <a class="dropdown-item" href="post.php?turn_rmm_provider_on=<?= $provider_id ?>&amp;csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-toggle-on text-secondary me-2"></i>Turn on</a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-header py-2 small" data-rmm-sync-state="<?= $provider['rmm_provider_sync_at'] ? (intval($provider['rmm_provider_sync_ok']) ? 'ok' : 'failed') : 'never' ?>">
        <div class="d-flex flex-wrap align-items-center column-gap-3 row-gap-1">
            <?php if (!$provider['rmm_provider_sync_at']) { ?>
                <span class="text-body-secondary"><i class="fas fa-fw fa-info-circle me-1"></i>Not synced yet - link a client below and its devices come in straight away.</span>
            <?php } elseif (intval($provider['rmm_provider_sync_ok'])) { ?>
                <span><i class="fas fa-fw fa-check-circle text-success me-1"></i>Synced <span title="<?= escapeHtml($provider['rmm_provider_sync_at']) ?>"><?= escapeHtml(timeAgo($provider['rmm_provider_sync_at'])) ?></span> - <?= escapeHtml((string) $provider['rmm_provider_sync_note']) ?></span>
            <?php } else { ?>
                <span class="text-danger-emphasis"><i class="fas fa-fw fa-exclamation-circle me-1"></i>The sync <span title="<?= escapeHtml($provider['rmm_provider_sync_at']) ?>"><?= escapeHtml(timeAgo($provider['rmm_provider_sync_at'])) ?></span> didn't work: <?= escapeHtml((string) $provider['rmm_provider_sync_note']) ?></span>
            <?php } ?>
            <span class="text-body-secondary"><?= number_format($devices_in) ?> device<?= $devices_in === 1 ? '' : 's' ?> in ITFlow</span>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="provider_id" value="<?= $provider_id ?>">
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search clients..." title="<?= escapeHtml($short) ?>'s client, or yours">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-7 col-md-6 small text-body-secondary text-md-end">
                    Link each of <?= escapeHtml($short) ?>'s clients to yours - its devices come in as assets.
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $view) {
                if ($key === 'removed' && !$view_counts[$key] && $show !== 'removed') {
                    continue;
                } ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key === '' ? null : $key])) ?>" title="<?= escapeHtml($view['title']) ?>">
                    <i class="fas fa-fw <?= $view['icon'] ?> me-1"></i><?= escapeHtml($view['label']) ?> <span class="badge rounded-pill ms-1"><?= $view_counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('rmm_client_name', escapeHtml("$short client")) ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('rmm_client_devices', 'Devices') ?></th>
                <th><?= $sort_link('client_name', 'ITFlow client') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('rmm_client_synced_at', 'Brought in') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $rmm_client_id = intval($row['rmm_client_id']);
                $client_id = intval($row['rmm_client_client_id']);
                $linked = $client_id && $row['client_name'] !== null;
                $removed = $row['rmm_client_removed_at'] !== null;
                $suggested = $suggestions[$rmm_client_id] ?? 0;
                $sites = intval($row['rmm_client_sites']);
                $deleted = intval($row['assets_deleted']);
                $overdue = intval($row['devices_overdue']);
                $link_url = "modals/rmm/rmm_client_link.php?id=$rmm_client_id";
                ?>
                <tr data-rmm-client-id="<?= $rmm_client_id ?>" class="<?= $removed ? 'text-body-secondary' : '' ?>">
                    <td class="ps-3">
                        <div class="fw-semibold"><?= escapeHtml($row['rmm_client_name']) ?>
                            <?php if ($removed) { ?><span class="badge text-bg-secondary fw-normal ms-1" title="<?= escapeHtml($name) ?> stopped listing it <?= escapeHtml(timeAgo($row['rmm_client_removed_at'])) ?>">Gone from <?= escapeHtml($short) ?></span><?php } ?>
                        </div>
                        <div class="small text-body-secondary"><?= $sites ?> site<?= $sites === 1 ? '' : 's' ?><span class="d-md-none"> &middot; <?= intval($row['rmm_client_devices']) ?> devices</span></div>
                    </td>
                    <td class="d-none d-md-table-cell text-nowrap">
                        <div><?= number_format(intval($row['rmm_client_devices'])) ?> in <?= escapeHtml($short) ?></div>
                        <?php if ($linked) { ?>
                            <div class="small"><a href="../agent/assets.php?client_id=<?= $client_id ?>&amp;rmm=any"><?= number_format(intval($row['assets_in'])) ?> asset<?= intval($row['assets_in']) === 1 ? '' : 's' ?></a>
                                <?php if ($overdue) { ?><span class="text-danger-emphasis ms-1" title="Not heard from for longer than <?= escapeHtml($short) ?>'s overdue time"><?= $overdue ?> overdue</span><?php } ?>
                                <?php if ($deleted) { ?><span class="text-body-secondary ms-1" title="Their assets were deleted in ITFlow, so they aren't brought in again"><?= $deleted ?> deleted</span><?php } ?>
                            </div>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if ($linked) { ?>
                            <div class="d-flex align-items-center gap-2">
                                <span class="itflow-client-avatar d-none d-sm-inline-flex bg-<?= clientInitialsTone($client_id) ?>-subtle text-<?= clientInitialsTone($client_id) ?>-emphasis" aria-hidden="true"><?= escapeHtml(clientInitials($row['client_abbreviation'] ?? '', $row['client_name'])) ?></span>
                                <a class="text-body" href="../agent/client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($row['client_name']) ?></a>
                                <?php if ($row['client_archived_at'] !== null) { ?><span class="badge text-bg-secondary fw-normal" title="Nothing is brought in for an archived client">Archived</span><?php } ?>
                            </div>
                        <?php } elseif ($client_id) { ?>
                            <span class="text-warning-emphasis" title="Its client was deleted in ITFlow - link it again"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Its client was deleted</span>
                        <?php } elseif ($removed) { ?>
                            <span class="text-body-secondary">-</span>
                        <?php } else { ?>
                            <div class="d-flex flex-wrap align-items-center column-gap-2 row-gap-1">
                                <span class="text-body-secondary">Not linked</span>
                                <?php if ($suggested && isset($suggested_names[$suggested])) { ?>
                                    <a class="btn btn-sm btn-outline-primary" href="post.php?link_rmm_suggested=<?= $rmm_client_id ?>&amp;provider_id=<?= $provider_id ?>&amp;csrf_token=<?= $csrf ?>" data-rmm-suggest="<?= $suggested ?>" title="Link it to your client of the same name">
                                        <i class="fas fa-fw fa-link me-1"></i><?= escapeHtml($suggested_names[$suggested]) ?>
                                    </a>
                                <?php } ?>
                                <a class="btn btn-sm btn-light ajax-modal" href="#" data-modal-url="<?= $link_url ?>"><i class="fas fa-fw fa-search me-1"></i>Pick</a>
                            </div>
                        <?php } ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-nowrap">
                        <?php if ($row['rmm_client_synced_at']) { ?>
                            <span title="<?= escapeHtml($row['rmm_client_synced_at']) ?>"><?= escapeHtml(timeAgo($row['rmm_client_synced_at'])) ?></span>
                        <?php } else { ?>
                            <span class="text-body-secondary"><?= $linked ? 'Next sync' : '-' ?></span>
                        <?php } ?>
                    </td>
                    <td class="text-end pe-3">
                        <?php if (!$removed || $client_id) { ?>
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <?php if (!$removed) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $link_url ?>">
                                            <i class="fas fa-fw fa-link text-secondary me-2"></i><?= $client_id ? 'Link to another client' : 'Link to a client' ?>
                                        </a>
                                    <?php } ?>
                                    <?php if ($deleted && $linked && !$removed) { ?>
                                        <a class="dropdown-item confirm-link" href="post.php?rmm_bring_back=<?= $rmm_client_id ?>&amp;csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Bring back <?= $deleted ?> deleted device<?= $deleted === 1 ? '' : 's' ?>?" data-confirm-button="Bring back"
                                            data-confirm-text="Their assets were deleted in ITFlow. The sync matches them to assets that are there, or makes them again.">
                                            <i class="fas fa-fw fa-trash-restore text-secondary me-2"></i>Bring back <?= $deleted ?> deleted
                                        </a>
                                    <?php } ?>
                                    <?php if ($client_id) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?unlink_rmm_client=<?= $rmm_client_id ?>&amp;csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Unlink <?= escapeHtml($row['rmm_client_name']) ?>?" data-confirm-button="Unlink"
                                            data-confirm-text="Its devices stop coming in. Their assets stay, and keep what <?= escapeHtml($short) ?> last said about them.">
                                            <i class="fas fa-fw fa-unlink me-2"></i>Unlink
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => "$short clients",
        'icon' => $kind['icon'],
        'title' => escapeHtml($show === 'unlinked' ? "Every $short client is linked" : "No $short clients yet"),
        'text' => $show === 'unlinked' ? '' : escapeHtml($provider['rmm_provider_sync_at'] ? "$name has no clients this key can see." : "Sync now asks $name for its clients."),
        'actions' => $usable && !$provider['rmm_provider_sync_at'] ? [['label' => 'Sync now', 'href' => "post.php?sync_rmm=$provider_id&csrf_token=$csrf", 'icon' => 'fa-sync-alt']] : [],
        'filtered' => $q !== '',
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script>
// Sync now and the suggested links wait on the RMM - say so while they do
document.querySelectorAll('[data-rmm-sync], [data-rmm-suggest]').forEach(function (button) {
    button.addEventListener('click', function () {
        button.classList.add('disabled');
        var icon = button.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-fw fa-spinner fa-spin' + (button.hasAttribute('data-rmm-suggest') ? ' me-1' : (button.classList.contains('dropdown-item') ? ' text-secondary me-2' : ''));
        }
    });
});
</script>

<?php
require_once "../includes/footer.php";
