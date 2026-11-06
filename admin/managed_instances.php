<?php

/*
 * Admin > Managed Instances - the ITFlow installs this one manages (functions/managed.php).
 * Each row says what it is and whose, the version it runs and whether an update is waiting,
 * how it's doing (from the report it sends every minute), its agents against the seat limit
 * set here, whether it's active, read-only or suspended, and when it last checked in. Removed
 * instances sit under Removed, where they can be deleted. Add Instance makes one and its
 * connection code; the handlers are admin/post/managed_instances.php.
 */

// Default Column Sortby Filter
$sort = "managed_instance_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

// Off unless config.php turns it on (managerEnabled())
if (!managerEnabled()) { ?>
    <div class="alert alert-secondary"><i class="fas fa-fw fa-toggle-off me-2"></i>Managing other ITFlow installs is off. To turn it on, add this line to the end of config.php: <code>$config_enable_managed_instances = true;</code></div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

// The manager's tables arrive with a migration - before it, say so rather than throw
if (!settingsColumnExists($mysqli, 'config_manager_keys')) { ?>
    <div class="alert alert-warning"><i class="fas fa-fw fa-database me-2"></i>Managing other ITFlow installs needs this install's database update - apply it from <a href="update.php" class="alert-link">Maintenance &gt; Update</a> first.</div>
    <?php
    require_once "../includes/footer.php";
    exit;
}

$show_removed = ($_GET['show'] ?? '') === 'removed';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'managed_instance_name' => "managed_instance_name $order",
    'managed_instance_version' => "managed_instance_version IS NULL, INET_ATON(CONCAT(managed_instance_version, '.0')) $order, managed_instance_version $order, managed_instance_name ASC",
    'managed_instance_last_checkin_at' => "managed_instance_last_checkin_at IS NULL, managed_instance_last_checkin_at $order, managed_instance_name ASC",
    'client_name' => "client_name IS NULL, client_name $order, managed_instance_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'managed_instance_name';
}

$search = "(managed_instance_name LIKE '%$q%' OR managed_instance_url LIKE '%$q%' OR client_name LIKE '%$q%' OR managed_instance_version LIKE '%$q%')";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS managed_instances.*, client_name
    FROM managed_instances
    LEFT JOIN clients ON client_id = managed_instance_client_id
    WHERE $search AND managed_instance_archived_at IS " . ($show_removed ? 'NOT NULL' : 'NULL') . "
    ORDER BY {$sortable_columns[$sort]}, managed_instance_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(managed_instance_archived_at IS NULL), 0), COALESCE(SUM(managed_instance_archived_at IS NOT NULL), 0)
    FROM managed_instances LEFT JOIN clients ON client_id = managed_instance_client_id WHERE $search"));

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-network-wired me-2"></i>Managed Instances</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/managed_instance/managed_instance_form.php"><i class="fas fa-plus me-2"></i>Add Instance</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show_removed) { ?><input type="hidden" name="show" value="removed"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search instances..." title="Name, address, client or version">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <a class="nav-link <?= $show_removed ? '' : 'active' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '']))) ?>"><i class="fas fa-fw fa-network-wired me-1"></i>Managed <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
            <a class="nav-link <?= $show_removed ? 'active' : '' ?>" href="?<?= escapeHtml(http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => 'removed']))) ?>" title="No longer managed - they let go at their next check-in"><i class="fas fa-fw fa-unlink me-1"></i>Removed <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
        </nav>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('managed_instance_name', 'Instance') ?> <span class="fw-normal text-body-secondary small ms-2"><?= $sort_link('client_name', 'by client') ?></span></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('managed_instance_version', 'Version') ?></th>
                <th class="d-none d-lg-table-cell">Health</th>
                <th class="d-none d-xl-table-cell">Agents</th>
                <th class="d-none d-sm-table-cell"><?= $sort_link('managed_instance_last_checkin_at', 'Last check-in') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $instance_id = intval($row['managed_instance_id']);
                $name = escapeHtml($row['managed_instance_name']);
                $health = managedInstanceHealth($row);
                $report = $health['report'];
                $agents = (array) ($report['agents'] ?? []);
                $seat_limit = intval($row['managed_instance_seat_limit']);
                $update = (array) ($report['update'] ?? []);
                $url = (string) $row['managed_instance_url'];
                $dot = ['online' => 'success', 'offline' => 'danger', 'pending' => 'secondary'][$health['state']];
                $last = $row['managed_instance_last_checkin_at'];
                $last_html = $health['state'] === 'pending'
                    ? '<span class="text-body-secondary">Not connected yet</span>'
                    : ($last ? '<span title="' . escapeHtml($last) . '"><i class="fas fa-circle fa-xs text-' . $dot . ' me-1"></i>' . escapeHtml(timeAgo($last)) . '</span>' : '<span class="text-body-secondary">Never</span>');
                $page = "managed_instance.php?id=$instance_id";
                ?>
                <tr data-managed-instance-id="<?= $instance_id ?>" data-managed-state="<?= $health['state'] ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-server d-none d-sm-inline-flex position-relative" aria-hidden="true">
                                <i class="fas fa-fw fa-server"></i>
                                <span class="position-absolute bottom-0 end-0 translate-middle-y p-1 rounded-circle bg-<?= $dot ?> border border-2 border-body"></span>
                            </span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body" href="<?= $page ?>"><?= $name ?></a>
                                <?php if ($row['managed_instance_mode'] !== 'active') { ?><span class="ms-1"><?= managedModePill($row['managed_instance_mode']) ?></span><?php } ?>
                                <div class="small text-body-secondary text-truncate">
                                    <?= $url !== '' ? escapeHtml(preg_replace('#^https?://#', '', $url)) : 'Waiting to connect' ?><?= $row['client_name'] !== null && $row['client_name'] !== $row['managed_instance_name'] ? ' · ' . escapeHtml($row['client_name']) : '' ?>
                                </div>
                                <!-- On smaller screens the version, health and check-in come along -->
                                <div class="d-md-none small mt-1"><?= escapeHtml((string) ($row['managed_instance_version'] ?: '')) ?></div>
                                <div class="d-lg-none small mt-1"><?php if ($health['issues']) { [$tone, $text] = $health['issues'][0]; ?><span class="text-<?= $tone ?>-emphasis"><?= escapeHtml($text) ?></span><?php } ?></div>
                                <div class="d-sm-none small mt-1"><?= $last_html ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell small text-nowrap">
                        <?php if ($row['managed_instance_version']) { ?>
                            <div class="fw-semibold"><?= escapeHtml($row['managed_instance_version']) ?></div>
                            <?php if (!empty($update['available'])) { ?>
                                <div class="text-<?= !empty($update['security']) ? 'danger' : 'primary' ?>-emphasis" title="<?= !empty($update['security']) ? 'The update has security fixes' : 'An update is waiting' ?>">
                                    <i class="fas fa-fw fa-arrow-alt-circle-up me-1"></i><?= !empty($update['release']) ? escapeHtml($update['release']) : intval($update['commits'] ?? 0) . ' new' ?><?= !empty($update['security']) ? ' · security' : '' ?>
                                </div>
                            <?php } ?>
                            <div class="text-body-secondary"><?= $row['managed_instance_update_policy'] === 'auto' ? 'Updates itself' : 'Updated by hand' ?></div>
                        <?php } else { ?>
                            <span class="text-body-secondary">&mdash;</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-lg-table-cell small text-nowrap">
                        <?php if ($health['state'] === 'pending') { ?>
                            <span class="text-body-secondary">&mdash;</span>
                        <?php } elseif (!$health['issues']) { ?>
                            <span class="text-success-emphasis"><i class="fas fa-fw fa-check-circle me-1"></i>All good</span>
                        <?php } else { ?>
                            <?php foreach (array_slice($health['issues'], 0, 2) as [$tone, $text]) { ?>
                                <div class="text-<?= $tone ?>-emphasis"><i class="fas fa-fw fa-<?= $tone === 'info' ? 'info-circle' : 'exclamation-circle' ?> me-1"></i><?= escapeHtml($text) ?></div>
                            <?php } ?>
                            <?php if (count($health['issues']) > 2) { ?><div class="text-body-secondary">and <?= count($health['issues']) - 2 ?> more</div><?php } ?>
                        <?php } ?>
                    </td>
                    <td class="d-none d-xl-table-cell small text-nowrap">
                        <?php if ($agents) { ?>
                            <?= intval($agents['active'] ?? 0) + intval($agents['invites'] ?? 0) ?><?= $seat_limit > 0 ? ' of ' . $seat_limit : '' ?>
                            <div class="text-body-secondary"><?= $seat_limit > 0 ? 'seats' : 'agents, no limit' ?></div>
                        <?php } else { ?>
                            <span class="text-body-secondary">&mdash;</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-sm-table-cell small text-nowrap"><?= $last_html ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="<?= $page ?>"><i class="fas fa-fw fa-eye text-secondary me-2"></i>Open</a>
                                <?php if (!$show_removed) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/managed_instance/managed_instance_form.php?id=<?= $instance_id ?>"><i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit</a>
                                    <?php if ($health['state'] !== 'pending' && $url !== '') { ?>
                                        <a class="dropdown-item" href="post.php?managed_support_sign_in=<?= $instance_id ?>&csrf_token=<?= $csrf ?>" target="_blank" rel="noopener"><i class="fas fa-fw fa-sign-in-alt text-secondary me-2"></i>Sign in as support</a>
                                        <?php if (!empty($update['available'])) { ?>
                                            <a class="dropdown-item confirm-link" href="post.php?managed_command=update_now&id=<?= $instance_id ?>&csrf_token=<?= $csrf ?>"
                                                data-confirm-title="Update <?= $name ?>?" data-confirm-text="It updates its files and then its database at its next check-in. Make sure it has a backup." data-confirm-button="Update"><i class="fas fa-fw fa-download text-secondary me-2"></i>Update now</a>
                                        <?php } ?>
                                    <?php } ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger confirm-link" href="post.php?remove_managed_instance=<?= $instance_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-title="Remove <?= $name ?>?" data-confirm-text="It stops being managed at its next check-in - what this manager set comes off it, and its support user is archived. Its data stays where it is." data-confirm-button="Remove">
                                        <i class="fas fa-fw fa-unlink me-2"></i>Remove
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_managed_instance=<?= $instance_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Its record and history here go for good. The instance itself isn't touched." data-confirm-button="Delete">
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
    <?php
    $empty_state = [
        'thing' => 'managed instances',
        'icon' => 'fa-network-wired',
        'filtered' => $q !== '',
        'archived' => $show_removed,
        'archived_title' => 'No removed instances',
        'views' => ['show'],
        'text' => 'Manage other ITFlow installs from here - a client\'s, a reseller\'s or your own hosted ones. Each checks in every minute with how it\'s doing; from here you set its seat limit, white label and updates, update it, make it read-only or suspend it, and sign in to it as support.',
        'actions' => [['label' => 'Add Instance', 'modal' => 'modals/managed_instance/managed_instance_form.php']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
