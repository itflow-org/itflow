<?php

/*
 * Shared Links - one client's, or every one the agent can see from All
 * Client Docs (functions/share.php). Each row is what went out (or what was
 * asked for), who it was for, who made it and when, when it stops working,
 * how often it was opened, what it asks for before it opens, and where it is
 * now. The pills along the top: the links still open, logins clients sent
 * back to save, the ones that ended, all of them.
 *
 * From here: a secure note or a login request for the client, a link's quick
 * look (agent/modals/share/share.php) with who opened it and from where,
 * turning links off - one, the ticked ones, or every open link the client
 * has - and saving what a client sent back.
 */

// Default Column Sortby Filter
$sort = "item_created_at";
$order = "DESC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND item_client_id = $client_id";
    $client_url = "client_id=$client_id&";
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
}

// Perms
enforceUserPermission('module_support');

// Secrets that are no longer needed go before anything is listed
shareSweep();

$types = shareTypes();
$visible_types = array_values(array_filter(array_keys($types), 'shareCanSee'));
$can_end = (bool) array_filter($visible_types, 'shareCanMake');
$can_note = $client_url && shareCanMake('Note');
$can_request = $client_url && shareCanMake('Request');

// Client Filter (all clients)
$client = '';
if (!$client_url && !empty($_GET['client'])) {
    $client = intval($_GET['client']);
    $client_query = "AND item_client_id = $client";
}

// Type Filter
$type_filter = (string) ($_GET['type'] ?? '');
if (!in_array($type_filter, $visible_types, true)) {
    $type_filter = '';
}
$type_query = $type_filter !== '' ? "AND item_type = '$type_filter'" : '';

// Which ones - the pills along the top
$shows = [
    '' => ['label' => 'Open', 'icon' => 'fa-link', 'where' => shareOpenSql(), 'title' => 'Still work - not used up, expired or turned off'],
    'received' => ['label' => 'Sent back', 'icon' => 'fa-inbox', 'where' => "item_type = 'Request' AND item_received_at IS NOT NULL AND item_ended_at IS NULL", 'title' => 'Logins clients sent back, waiting to be saved'],
    'ended' => ['label' => 'Ended', 'icon' => 'fa-history', 'where' => 'NOT (' . shareLiveSql() . ')', 'title' => 'Used up, expired, turned off, locked - kept for ' . SHARE_KEEP_DAYS . ' days'],
    'all' => ['label' => 'All', 'where' => '1'],
];
$show = isset($shows[$_GET['show'] ?? '']) ? ($_GET['show'] ?? '') : '';
$show_query = 'AND ' . $shows[$show]['where'];

// Only these sort - filter_header.php will hand through any lowercase word
$sortable_columns = ['item_created_at' => 'item_created_at', 'item_name' => 'item_name', 'item_recipient' => 'item_recipient',
    'item_expire_at' => 'item_expire_at', 'item_last_viewed_at' => 'item_last_viewed_at', 'client_name' => 'client_name'];
if (!isset($sortable_columns[$sort])) {
    $sort = 'item_created_at';
}
$sort_sql = ($sort === 'item_last_viewed_at' ? 'item_last_viewed_at IS NULL, ' : '') . $sortable_columns[$sort] . " $order";

$search_query = "AND (item_name LIKE '%$q%' OR item_recipient LIKE '%$q%' OR item_note LIKE '%$q%' OR creator.user_name LIKE '%$q%' OR client_name LIKE '%$q%')";
$scope_query = clientScopeSql('item_client_id');
$visible_sql = "item_type IN (" . shareVisibleTypesSql() . ")";
$filters = "$visible_sql $type_query $search_query $scope_query $client_query";

$shares = shareRows("$filters $show_query", "$sort_sql, shared_items.item_id DESC", $user_config_records_per_page, $record_from);
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// How many of each, for the pills - everything in scope, before the pill itself
$count_sql = [];
foreach ($shows as $key => $s) {
    $count_sql[] = "COALESCE(SUM({$s['where']}), 0)";
}
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', $count_sql) . " FROM shared_items
    LEFT JOIN clients ON client_id = item_client_id
    LEFT JOIN users creator ON creator.user_id = item_created_by
    WHERE $filters"));
$counts = [];
foreach (array_keys($shows) as $i => $key) {
    $counts[$key] = intval($row[$i]);
}

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
};

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-share-alt me-2"></i>Shared Links</h3>
        <div class="card-tools">
            <?php if ($can_note || $can_request || ($client_url && $can_end && $counts[''])) { ?>
            <div class="btn-group">
                <?php if ($can_note) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/share/share_add.php?type=Note&amp;client_id=<?= intval($client_id) ?>">
                        <i class="fas fa-user-secret me-2"></i>Secure Note
                    </button>
                <?php } ?>
                <button type="button" class="btn btn-primary dropdown-toggle <?= $can_note ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown"><?= $can_note ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($can_request) { ?>
                        <a class="dropdown-item text-dark ajax-modal" href="#" data-modal-url="modals/share/share_add.php?type=Request&amp;client_id=<?= intval($client_id) ?>">
                            <i class="fa fa-fw fa-inbox me-2"></i>Ask the client for a login
                        </a>
                    <?php } ?>
                    <?php if ($client_url && $can_end && $counts['']) { ?>
                        <?php if ($can_request) { ?><div class="dropdown-divider"></div><?php } ?>
                        <form action="post.php" method="post" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="client_id" value="<?= intval($client_id) ?>">
                            <button type="submit" name="share_revoke_all" value="1" class="dropdown-item text-danger confirm-link"
                                data-confirm-text="Every open link for <?= $client_name ?> stops working straight away - <?= $counts[''] ?> of them. Requests not answered yet are cancelled too.">
                                <i class="fa fa-fw fa-unlink me-2"></i>Turn off every open link
                            </button>
                        </form>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <div class="row g-2 align-items-center">
                <div class="<?= $client_url ? 'col-lg-5' : 'col-lg-4' ?> col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search what, who it went to, who made it...">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="<?= $client_url ? 'col-lg-4' : 'col-lg-3' ?> col-md-6">
                    <select class="form-select select2" name="type" onchange="this.form.submit()">
                        <option value="">- Any kind -</option>
                        <?php foreach ($visible_types as $type) { ?>
                            <option <?= $type_filter === $type ? 'selected' : '' ?> value="<?= $type ?>" data-icon="fas <?= $types[$type]['icon'] ?>"><?= $types[$type]['label'] ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if (!$client_url) { ?>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="client" onchange="this.form.submit()">
                        <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>
                        <?php
                        $sql_clients_filter = mysqli_query($mysqli, "
                            SELECT DISTINCT client_id, client_name, client_abbreviation
                            FROM clients
                            JOIN shared_items ON item_client_id = client_id
                            WHERE 1 = 1
                            " . clientScopeSql('clients.client_id') . "
                            ORDER BY client_name ASC
                        ");
                        while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                            $client_id_filter = intval($row['client_id']); ?>
                            <option <?php if ($client == $client_id_filter) { echo "selected"; } ?> value="<?= $client_id_filter ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>
                <div class="<?= $client_url ? 'col-lg-3' : 'col-lg-2' ?> col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <?php if ($can_end) { ?>
                        <div class="dropdown" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <button class="dropdown-item text-danger confirm-link" type="submit" form="bulkActions" name="share_bulk_revoke" value="1"
                                    data-confirm-text="The ticked links stop working straight away.">
                                    <i class="fas fa-fw fa-unlink me-2"></i>Turn off
                                </button>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </form>
        <!-- Which ones - counted over what the filters above leave -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $s) {
                $alarm = $key === 'received' && $counts[$key]; ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key])) ?>"<?= !empty($s['title']) ? ' title="' . escapeHtml($s['title']) . '"' : '' ?>>
                    <?php if (!empty($s['icon'])) { ?><i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?php } ?><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? 'text-bg-warning' : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 itflow-asset-table itflow-share-table">
                <thead class="<?php if (!$num_rows[0]) { echo "d-none"; } ?> text-nowrap table-light">
                <tr>
                    <?php if ($can_end) { ?>
                    <th class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                        </div>
                    </th>
                    <?php } ?>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=item_name&order=<?= $disp ?>">
                            Shared <?php if ($sort == 'item_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=item_recipient&order=<?= $disp ?>">
                            With <?php if ($sort == 'item_recipient') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=item_created_at&order=<?= $disp ?>">
                            Made <?php if ($sort == 'item_created_at') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=item_expire_at&order=<?= $disp ?>">
                            Works until <?php if ($sort == 'item_expire_at') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=item_last_viewed_at&order=<?= $disp ?>">
                            Opened <?php if ($sort == 'item_last_viewed_at') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>Status</th>
                    <?php if (!$client_url) { ?>
                    <th>
                        <a class="text-reset text-decoration-none" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                            Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php } ?>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php

                foreach ($shares as $row) {
                    $item_id = intval($row['item_id']);
                    $row_client_id = intval($row['item_client_id']);
                    $type = $row['item_type'];
                    $type_info = $types[$type] ?? ['label' => $type, 'icon' => 'fa-share-alt'];
                    $state = shareState($row);
                    $is_open = in_array($state, ['active', 'waiting'], true);
                    $can_end_row = shareCanMake($type);
                    $protection = shareProtection($row);
                    $quick_url = "modals/share/share.php?id=$item_id";

                    ?>
                    <tr>
                        <?php if ($can_end) { ?>
                        <td class="checkbox-column bg-light border-end">
                            <?php if ($is_open && $can_end_row) { ?>
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="share_ids[]" value="<?= $item_id ?>" aria-label="Select <?= escapeHtml(shareName($row)) ?>">
                            </div>
                            <?php } ?>
                        </td>
                        <?php } ?>
                        <td class="itflow-asset-name-cell">
                            <div class="d-flex align-items-center gap-3">
                                <span class="itflow-link-icon" title="<?= escapeHtml($type_info['label']) ?>" aria-hidden="true"><i class="fas fa-fw <?= $type_info['icon'] ?>"></i></span>
                                <div class="min-w-0">
                                    <a class="fw-semibold text-body ajax-modal" href="#" data-modal-url="<?= $quick_url ?>"><?= escapeHtml(shareName($row)) ?></a>
                                    <div class="small text-body-secondary text-truncate itflow-asset-desc">
                                        <?= escapeHtml($type_info['label']) ?>
                                        <?php if ($protection === 'passphrase') { ?><span class="ms-1" title="Needs a passphrase"><i class="fas fa-key"></i></span><?php } ?>
                                        <?php if ($protection === 'code') { ?><span class="ms-1" title="Needs a code emailed to the recipient"><i class="fas fa-envelope-open-text"></i></span><?php } ?>
                                        <?php if (trim((string) $row['item_note']) !== '') { ?><span class="ms-1" title="<?= escapeHtml($row['item_note']) ?>"><i class="far fa-comment"></i></span><?php } ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="small">
                            <?php if (trim((string) $row['item_recipient']) !== '') { ?>
                                <span class="text-truncate d-inline-block itflow-share-recipient" title="<?= escapeHtml($row['item_recipient']) ?>"><?= escapeHtml($row['item_recipient']) ?></span>
                            <?php } else { ?>
                                <span class="text-body-secondary">Anyone with the link</span>
                            <?php } ?>
                        </td>
                        <td class="small text-nowrap">
                            <div title="<?= escapeHtml($row['item_created_at']) ?>"><?= timeAgo($row['item_created_at']) ?></div>
                            <div class="text-body-secondary"><?= $row['creator_name'] !== null ? escapeHtml($row['creator_name']) : '' ?></div>
                        </td>
                        <td class="small text-nowrap" title="<?= escapeHtml($row['item_expire_at']) ?>">
                            <?php if ($is_open) { ?>
                                <?= escapeHtml(date('M j, g:i A', strtotime($row['item_expire_at']))) ?>
                                <div class="text-body-secondary"><?= timeAgo($row['item_expire_at']) ?></div>
                            <?php } else { ?>
                                <span class="text-body-secondary"><?= escapeHtml(date('M j, g:i A', strtotime($row['item_expire_at']))) ?></span>
                            <?php } ?>
                        </td>
                        <td class="small text-nowrap">
                            <?= escapeHtml(shareViewsText($row)) ?>
                            <?php if ($row['item_last_viewed_at']) { ?>
                                <div class="text-body-secondary" title="<?= escapeHtml($row['item_last_viewed_at']) ?>">Last <?= timeAgo($row['item_last_viewed_at']) ?></div>
                            <?php } ?>
                        </td>
                        <td class="text-nowrap"><?= shareStateBadge($row) ?></td>
                        <?php if (!$client_url) { ?>
                        <td><a href="shares.php?client_id=<?= $row_client_id ?>"><?= escapeHtml($row['client_name']) ?></a></td>
                        <?php } ?>
                        <td class="text-end">
                            <div class="dropdown dropstart">
                                <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $quick_url ?>">
                                        <i class="fas fa-fw fa-eye text-secondary me-2"></i>Details and history
                                    </a>
                                    <?= shareItemMenuLink($row) ?>
                                    <?php if ($can_end_row && $state === 'received') { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $quick_url ?>">
                                            <i class="fas fa-fw fa-save text-secondary me-2"></i>Review and save
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?share_request_discard=<?= $item_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            data-confirm-text="What they sent is deleted without being saved.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Discard
                                        </a>
                                    <?php } elseif ($can_end_row && $is_open) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?share_revoke=<?= $item_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                                            data-confirm-text="The link stops working straight away.">
                                            <i class="fas fa-fw fa-unlink me-2"></i><?= $type === 'Request' ? 'Cancel the request' : 'Turn off' ?>
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
        'thing' => 'shared links',
        'icon' => 'fa-share-alt',
        'filtered' => $q || $type_query,
        'views' => ['show'],
        'view' => emptyStatePill($shows, $show),
        'title' => 'No open links' . ($client_url ? ' for this client' : ''),
        'text' => 'Share a credential, document or file from its menu' . ($can_note || $can_request ? ', or send a secure note or ask for a login here' : '') . '.',
        'actions' => array_merge(
            $can_note ? [['label' => 'Secure Note', 'modal' => 'modals/share/share_add.php?type=Note&client_id=' . intval($client_id), 'icon' => 'fa-user-secret']] : [],
            $can_request ? [['label' => 'Ask the client for a login', 'modal' => 'modals/share/share_add.php?type=Request&client_id=' . intval($client_id), 'icon' => 'fa-inbox']] : []
        ),
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php if (!empty($_GET['share'])) { ?>
    <script>
        // Opened from a notification - straight to that link's quick look
        document.addEventListener('DOMContentLoaded', function () {
            var open = document.createElement('a');
            open.className = 'ajax-modal';
            open.href = '#';
            open.dataset.modalUrl = 'modals/share/share.php?id=<?= intval($_GET['share']) ?>';
            document.body.appendChild(open);
            open.click();
            open.remove();
        });
    </script>
<?php } ?>

<script src="../js/bulk_actions.js"></script>

<?php require_once "../includes/footer.php";
