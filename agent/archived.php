<?php

/*
 * Archived - what was archived lately, every kind on one list, newest first, to
 * find something archived by mistake and restore it (functions/archived.php).
 * Only the kinds the agent can see, for the clients they can reach. Restore on a
 * row goes through that record's own handler; ticked rows are restored together
 * (bulk_restore_archived).
 */

// Default Column Sortby Filter
$sort = "archived_at";
$order = "DESC";

require_once "includes/inc_all.php";

if (!archivedCan()) {
    flashAlert('Nothing archived is yours to see.', 'error');
    redirect('dashboard.php');
}

$kinds = archivedKindsFor(1);
$restore_kinds = archivedKindsFor(2);

// Kind filter
$kind_filter = (string) ($_GET['kind'] ?? '');
if (!isset($kinds[$kind_filter])) {
    $kind_filter = '';
}

// Client filter
$client_filter = intval($_GET['client'] ?? 0);

// How long ago - 30 days unless picked
$when_choices = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last year', 0 => 'Any time'];
$days = isset($_GET['days']) && array_key_exists(intval($_GET['days']), $when_choices) ? intval($_GET['days']) : 30;

// Only these sort - filter_header.php will hand through any lowercase word
if (!in_array($sort, ['archived_at', 'archived_name', 'archived_kind', 'client_name'], true)) {
    $sort = 'archived_at';
}

$union = archivedUnionSql($kind_filter ? [$kind_filter => $kinds[$kind_filter]] : $kinds, $days, $client_filter, $q);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS archived.*, client_name
    FROM ($union) AS archived
    LEFT JOIN clients ON client_id = archived_client_id
    ORDER BY $sort $order, archived_at DESC LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$rows = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $rows[] = $row;
}
$archived_by = archivedBy($rows);
$can_restore_any = (bool) $restore_kinds;

// The clients with something archived, for the filter
$client_options = [];
$sql_clients = mysqli_query($mysqli, "SELECT DISTINCT client_id, client_name, client_abbreviation
    FROM (" . archivedUnionSql($kinds, $days) . ") AS archived JOIN clients ON client_id = archived_client_id
    ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql_clients)) {
    $client_options[] = $row;
}

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-archive me-2"></i>Archived</h3>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <div class="row g-2 align-items-center">
                <div class="col-lg-3 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?= stripslashes(escapeHtml($q)) ?>" placeholder="Search by name...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="kind" onchange="this.form.submit()" aria-label="Kind">
                        <option value="">- Everything -</option>
                        <?php foreach ($kinds as $key => $kind) { ?>
                            <option value="<?= $key ?>" data-icon="fas <?= $kind['icon'] ?>" <?= $kind_filter === $key ? 'selected' : '' ?>><?= escapeHtml($kind['label']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="client" onchange="this.form.submit()" aria-label="Client">
                        <option value="">- Any client -</option>
                        <?php foreach ($client_options as $row) { ?>
                            <option value="<?= intval($row['client_id']) ?>" <?= $client_filter === intval($row['client_id']) ? 'selected' : '' ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="days" onchange="this.form.submit()" aria-label="Archived when">
                        <?php foreach ($when_choices as $value => $label) { ?>
                            <option value="<?= $value ?>" <?= $days === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if ($can_restore_any && $rows) { ?>
                    <div class="col-auto ms-auto">
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle text-nowrap" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <button class="dropdown-item text-info" type="submit" form="bulkActions" name="bulk_restore_archived">
                                    <i class="fas fa-fw fa-redo me-2"></i>Restore
                                </button>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </form>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-asset-table">
                <thead class="<?= $num_rows[0] ? '' : 'd-none' ?> text-nowrap table-light">
                <tr>
                    <?php if ($can_restore_any) { ?>
                        <th class="checkbox-column border-end">
                            <div class="form-check">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                            </div>
                        </th>
                    <?php } ?>
                    <th>
                        <?= sortLink('archived_name', 'Name') ?>
                    </th>
                    <th>
                        <?= sortLink('archived_kind', 'Kind') ?>
                    </th>
                    <th>
                        <?= sortLink('client_name', 'Client') ?>
                    </th>
                    <th>
                        <?= sortLink('archived_at', 'Archived') ?>
                    </th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) {
                    $kind_key = $row['archived_kind'];
                    $kind = $kinds[$kind_key];
                    $id = intval($row['archived_id']);
                    $row_client_id = intval($row['archived_client_id']);
                    $name = escapeHtml($row['archived_name']);
                    $url = archivedUrl($kind_key, $id, $row_client_id, $row['archived_name']);
                    $by = $archived_by[$kind_key . ':' . $id] ?? '';
                    $can_restore = archivedCanRestore($kind_key, $row_client_id); ?>
                    <tr data-archived="<?= $kind_key ?>:<?= $id ?>">
                        <?php if ($can_restore_any) { ?>
                            <td class="checkbox-column border-end">
                                <?php if ($can_restore) { ?>
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="archived_items[]" value="<?= $kind_key ?>:<?= $id ?>" aria-label="Select <?= $name ?>">
                                    </div>
                                <?php } ?>
                            </td>
                        <?php } ?>
                        <td class="fw-semibold">
                            <?php if ($url) { ?>
                                <a class="text-reset" href="<?= escapeHtml($url) ?>"><?= $name ?></a>
                            <?php } else { ?>
                                <?= $name ?>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap"><i class="fas fa-fw <?= $kind['icon'] ?> text-body-secondary me-1"></i><?= escapeHtml($kind['label']) ?></td>
                        <td>
                            <?php if ($row_client_id && $kind_key !== 'client') { ?>
                                <a data-client-card="<?= $row_client_id ?>" href="client_overview.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a>
                            <?php } elseif ($kind_key === 'client') { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } else { ?>
                                <span class="text-body-secondary"><?= $kind['client'] === '' ? '-' : 'Ours' ?></span>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap">
                            <span title="<?= formatDateTime($row['archived_at']) ?>"><?= escapeHtml(timeAgo($row['archived_at'])) ?></span>
                            <?php if ($by !== '') { ?><div class="small text-body-secondary">by <?= escapeHtml($by) ?></div><?php } ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <?php if ($can_restore) { ?>
                                <a class="btn btn-sm btn-outline-secondary" href="post.php?restore_<?= $kind_key ?>=<?= $id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-redo me-1"></i>Restore
                                </a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php
    $empty_state = [
        'thing' => 'archived records',
        'icon' => 'fa-archive',
        'filtered' => $q !== '' || $kind_filter !== '' || $client_filter,
        'title' => $days ? 'Nothing archived in ' . ($days === 365 ? 'the last year' : "the last $days days") : 'Nothing archived',
        'text' => $days ? 'Pick Any time to see everything archived.' : 'What is archived - clients, contacts, assets, documents and the rest - shows here to restore.',
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<script src="<?= staticFileUrl('/js/bulk_actions.js') ?>"></script>

<?php
require_once "../includes/footer.php";
