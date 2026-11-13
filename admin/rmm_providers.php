<?php

/*
 * Admin > RMM Providers - the RMMs ITFlow brings devices in from (functions/rmm.php), Tactical
 * RMM the first kind. Each row says which RMM it is, how many of its clients are linked to
 * yours, how many of its devices are assets here, whether its alerts open tickets and how its
 * last sync went. Its clients are
 * linked on its Clients page (rmm_clients.php). Add and Edit are one window
 * (modals/rmm/rmm_provider_edit.php); the rest are admin/post/rmm.php.
 */

// Default Column Sortby Filter
$sort = "rmm_provider_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// The tables arrive with the 3.6.7 database update
if (!rmmTablesReady()) { ?>
    <div class="alert alert-warning"><i class="fas fa-fw fa-exclamation-triangle me-2"></i>RMM Providers arrive with a database update - run it in <a href="update.php">Maintenance &gt; Update</a> first.</div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$kinds = rmmProviderKinds();

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'rmm_provider_name' => "rmm_provider_name $order",
    'rmm_provider_kind' => "rmm_provider_kind $order, rmm_provider_name ASC",
    'clients_linked' => "clients_linked $order, rmm_provider_name ASC",
    'devices_in' => "devices_in $order, rmm_provider_name ASC",
    'rmm_provider_sync_at' => "rmm_provider_sync_at IS NULL, rmm_provider_sync_at $order, rmm_provider_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'rmm_provider_name';
}

// A search for "tactical" finds Tactical RMM's providers by their kind's name too
$kinds_found = array_keys(array_filter($kinds, function ($kind) use ($q) {
    return $q !== '' && stripos($kind['name'], stripslashes($q)) !== false;
}));
$kind_search = $kinds_found ? " OR rmm_provider_kind IN ('" . implode("', '", array_map('escapeSql', $kinds_found)) . "')" : '';

// Alerts open tickets once the 3.6.8 database update is in - and their open ones, with tickets
$alerts_ready = rmmAlertsReady();
$alerts_sql = $alerts_ready ? ", (SELECT COUNT(*) FROM rmm_alerts WHERE rmm_alert_provider_id = rmm_provider_id AND rmm_alert_cleared_at IS NULL AND rmm_alert_ticket_id > 0) AS alerts_open" : '';

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS rmm_providers.*,
    (SELECT COUNT(*) FROM rmm_clients WHERE rmm_client_provider_id = rmm_provider_id AND rmm_client_removed_at IS NULL) AS clients_total,
    (SELECT COUNT(*) FROM rmm_clients WHERE rmm_client_provider_id = rmm_provider_id AND rmm_client_removed_at IS NULL AND rmm_client_client_id > 0) AS clients_linked,
    (SELECT COUNT(*) FROM rmm_devices WHERE rmm_device_provider_id = rmm_provider_id AND rmm_device_removed_at IS NULL AND rmm_device_asset_id > 0) AS devices_in,
    (SELECT COUNT(*) FROM rmm_devices WHERE rmm_device_provider_id = rmm_provider_id AND rmm_device_removed_at IS NULL AND rmm_device_asset_id > 0 AND rmm_device_status = 'overdue') AS devices_overdue
    $alerts_sql
    FROM rmm_providers
    WHERE (rmm_provider_name LIKE '%$q%' OR rmm_provider_api_url LIKE '%$q%' OR rmm_provider_kind LIKE '%$q%'$kind_search)
    ORDER BY {$sortable_columns[$sort]}, rmm_provider_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// Syncing needs cron - say so while a provider is on and it isn't running
$any_on = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM rmm_providers WHERE rmm_provider_active = 1"))[0]) > 0;
$cron_on = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT config_enable_cron FROM settings WHERE company_id = 1"))[0] ?? 0) === 1;
$job = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_enabled FROM cron_jobs WHERE cron_job_name = 'rmm_sync' LIMIT 1"));
$job_on = !$job || intval($job['cron_job_enabled']) === 1;

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$add_url = 'modals/rmm/rmm_provider_add.php';
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-laptop-medical me-2"></i>RMM Providers</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $add_url ?>"><i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">Add Provider</span></button>
        </div>
    </div>
    <?php if ($any_on && (!$cron_on || !$job_on)) { ?>
        <div class="card-header py-2 small">
            <span class="text-warning-emphasis" data-rmm-cron-off><i class="fas fa-fw fa-exclamation-triangle me-1"></i><?= $cron_on ? 'The RMM Sync job is off in' : 'Cron is off in' ?> <a class="text-reset" href="cron.php">Maintenance &gt; Cron</a> - devices only come in with Sync now.</span>
        </div>
    <?php } ?>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search RMM providers..." title="Name, kind or API address">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('rmm_provider_name', 'Provider') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('rmm_provider_kind', 'Kind') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('clients_linked', 'Clients') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('devices_in', 'Devices') ?></th>
                <?php if ($alerts_ready) { ?><th class="d-none d-lg-table-cell">Alerts</th><?php } ?>
                <th class="d-none d-sm-table-cell"><?= $sort_link('rmm_provider_sync_at', 'Last sync') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $provider_id = intval($row['rmm_provider_id']);
                $name = rmmProviderName($row);
                $kind = rmmKind($row);
                $on = intval($row['rmm_provider_active']) === 1;
                $usable = rmmProviderUsable($row);
                $clients_url = "rmm_clients.php?provider_id=$provider_id";
                $edit_url = "modals/rmm/rmm_provider_edit.php?id=$provider_id";
                $linked = intval($row['clients_linked']);
                $total = intval($row['clients_total']);
                $suggested = $kind ? count(rmmClientSuggestions($provider_id)) : 0;
                $devices = intval($row['devices_in']);
                $overdue = intval($row['devices_overdue']);
                $host = (string) parse_url((string) $row['rmm_provider_api_url'], PHP_URL_HOST);
                $dashboard = $kind ? rmmDashboardUrl($row) : '';
                // Recurring invoice lines billing its devices count none once it is gone - say so before it goes
                $billed_lines = recurringSeatManagedLineCount($provider_id);
                ?>
                <tr data-rmm-provider-id="<?= $provider_id ?>" class="<?= $on ? '' : 'text-body-secondary' ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="d-none d-sm-inline-flex itflow-asset-icon itflow-asset-tone-other" aria-hidden="true"><i class="<?= escapeHtml($kind['icon'] ?? 'fas fa-laptop-medical') ?>"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body" href="<?= $clients_url ?>"><?= escapeHtml($name) ?></a>
                                <?php if (!$kind) { ?>
                                    <span class="badge text-bg-warning ms-1" title="This ITFlow doesn't know its kind (<?= escapeHtml($row['rmm_provider_kind']) ?>) - nothing is brought in">Unknown kind</span>
                                <?php } elseif (!$on) { ?>
                                    <span class="badge text-bg-secondary ms-1" data-rmm-provider-state="off" title="Nothing is brought in, and assets keep what it last said">Off</span>
                                <?php } ?>
                                <div class="small text-body-secondary text-truncate"><span class="d-lg-none"><?= escapeHtml($kind['name'] ?? $row['rmm_provider_kind']) ?><?= $host !== '' ? ' &middot; ' : '' ?></span><?= escapeHtml($host) ?></div>
                                <!-- On smaller screens its clients and devices come along under the name -->
                                <div class="d-md-none small text-body-secondary"><?= $linked ?> of <?= $total ?> clients linked &middot; <?= number_format($devices) ?> devices</div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-lg-table-cell small"><?= escapeHtml($kind['name'] ?? $row['rmm_provider_kind']) ?></td>
                    <td class="d-none d-md-table-cell small text-nowrap">
                        <a class="text-reset" href="<?= $clients_url ?>"><?= $linked ?> of <?= $total ?> linked</a>
                        <?php if ($suggested) { ?>
                            <div><a class="text-body-secondary" href="<?= $clients_url ?>&amp;show=unlinked" data-rmm-suggested="<?= $suggested ?>" title="Your clients of the same name - link them on its Clients page"><?= $suggested ?> to link</a></div>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-nowrap">
                        <div><?= number_format($devices) ?> asset<?= $devices === 1 ? '' : 's' ?></div>
                        <?php if ($overdue) { ?><div class="text-danger-emphasis" title="Not heard from for longer than its RMM's overdue time"><?= $overdue ?> overdue</div><?php } ?>
                    </td>
                    <?php if ($alerts_ready) {
                        $alert_level = (string) ($row['rmm_provider_alert_level'] ?? '');
                        $alerts_open = intval($row['alerts_open'] ?? 0); ?>
                        <td class="d-none d-lg-table-cell small" data-rmm-alerts="<?= escapeHtml($alert_level === '' ? 'off' : $alert_level) ?>">
                            <?php if ($alert_level === '' || !isset($kind['alerts'])) { ?>
                                <span class="text-body-secondary" title="Its alerts don't open tickets - Edit to turn them on">Off</span>
                            <?php } else { ?>
                                <div class="text-nowrap" title="These alerts open tickets"><i class="fas fa-fw fa-bell text-secondary me-1"></i><?= escapeHtml(rmmAlertLevels()[$alert_level] ?? $alert_level) ?></div>
                                <?php if ($row['rmm_provider_alerts_note'] !== null) { ?>
                                    <div class="text-danger-emphasis text-truncate" style="max-width: 16rem;" title="<?= escapeHtml((string) $row['rmm_provider_alerts_note']) ?>"><i class="fas fa-fw fa-exclamation-circle me-1"></i>Didn't work: <?= escapeHtml((string) $row['rmm_provider_alerts_note']) ?></div>
                                <?php } elseif ($row['rmm_provider_alerts_since'] === null) { ?>
                                    <div class="text-body-secondary" title="The first look takes the alerts open then as known, with no tickets">Starts in a minute</div>
                                <?php } elseif ($alerts_open) { ?>
                                    <div class="text-body-secondary"><?= $alerts_open ?> open, with tickets</div>
                                <?php } ?>
                            <?php } ?>
                        </td>
                    <?php } ?>
                    <td class="d-none d-sm-table-cell small" data-rmm-sync-state="<?= $row['rmm_provider_sync_at'] ? (intval($row['rmm_provider_sync_ok']) ? 'ok' : 'failed') : 'never' ?>">
                        <?php if (!$row['rmm_provider_sync_at']) { ?>
                            <span class="text-body-secondary">Not yet</span>
                        <?php } elseif (intval($row['rmm_provider_sync_ok'])) { ?>
                            <span class="text-nowrap" title="<?= escapeHtml($row['rmm_provider_sync_at'] . ' - ' . $row['rmm_provider_sync_note']) ?>"><i class="fas fa-fw fa-check-circle text-success me-1"></i><?= escapeHtml(timeAgo($row['rmm_provider_sync_at'])) ?></span>
                        <?php } else { ?>
                            <div class="text-danger-emphasis text-nowrap" title="<?= escapeHtml($row['rmm_provider_sync_at']) ?>"><i class="fas fa-fw fa-exclamation-circle me-1"></i>Didn't work, <?= escapeHtml(timeAgo($row['rmm_provider_sync_at'])) ?></div>
                            <div class="text-body-secondary text-truncate" style="max-width: 22rem;" title="<?= escapeHtml((string) $row['rmm_provider_sync_note']) ?>"><?= escapeHtml((string) $row['rmm_provider_sync_note']) ?></div>
                        <?php } ?>
                    </td>
                    <td class="text-end pe-3">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="<?= $clients_url ?>"><i class="fas fa-fw fa-users text-secondary me-2"></i>Clients</a>
                                <?php if ($kind) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                <?php } ?>
                                <?php if ($usable) { ?>
                                    <a class="dropdown-item" href="post.php?sync_rmm=<?= $provider_id ?>&amp;csrf_token=<?= $csrf ?>" data-rmm-sync title="Bring in its linked clients' devices now"><i class="fas fa-fw fa-sync-alt text-secondary me-2"></i>Sync now</a>
                                <?php } ?>
                                <?php if ($kind) { ?>
                                    <a class="dropdown-item" href="post.php?test_rmm_provider=<?= $provider_id ?>&amp;csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-vial text-secondary me-2"></i>Test the connection</a>
                                <?php } ?>
                                <?php if ($dashboard !== '') { ?>
                                    <a class="dropdown-item" href="<?= escapeHtml($dashboard) ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-fw fa-external-link-alt text-secondary me-2"></i>Open <?= escapeHtml($kind['short']) ?></a>
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
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_rmm_provider=<?= $provider_id ?>&amp;csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= escapeHtml($name) ?>?" data-confirm-button="Delete"
                                    data-confirm-text="The links between its clients and yours are forgotten, and assets no longer show what it says about them. The <?= number_format($devices) ?> asset<?= $devices === 1 ? '' : 's' ?> it brought in stay, as they are.<?= $billed_lines ? ' ' . $billed_lines . ' recurring invoice line' . ($billed_lines === 1 ? ' bills' : 's bill') . ' its managed devices - look at ' . ($billed_lines === 1 ? 'it' : 'them') . ' after, as its devices no longer count.' : '' ?>">
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
        'thing' => 'RMM providers',
        'icon' => 'fa-laptop-medical',
        'text' => 'Add ' . escapeHtml(implode(' or ', array_column($kinds, 'name'))) . ' and link its clients to yours: their devices come in as assets, matched to the ones you have, and each asset shows whether it is online, who is signed in and what needs attention - kept up every 15 minutes.',
        'actions' => [['label' => 'Add Provider', 'modal' => $add_url]],
    ];
    ?>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script>
// Sync now waits on the RMM - say so while it does
document.querySelectorAll('[data-rmm-sync]').forEach(function (button) {
    button.addEventListener('click', function () {
        button.classList.add('disabled');
        var icon = button.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-fw fa-spinner fa-spin text-secondary me-2';
        }
    });
});
</script>

<?php
require_once "../includes/footer.php";
