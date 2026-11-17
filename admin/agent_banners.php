<?php

/*
 * Admin > Agent Banners - every banner along the top of agents' pages (functions/agent_banner.php):
 * those for every client, made here, and those for one client, made here or from the client's
 * ⋮ menu. Each row shows how loud it is, where it stands (showing, scheduled, ended), who it is
 * for and when it shows. Current holds what isn't archived; Archived what is. Search looks in
 * the title and message; the client filter picks every client's or one client's. The handlers
 * are agent/post/agent_banner.php and the windows agent/modals/agent_banner/, shared with the
 * client's menu.
 */

// Default Column Sortby Filter
$sort = "starts";
$order = "DESC";

require_once "includes/inc_all_admin.php";

$views = [
    '' => ['label' => 'Current', 'icon' => 'fa-bullhorn', 'where' => 'announcement_archived_at IS NULL', 'title' => 'Showing, still to come, or ended and not archived'],
    'archived' => ['label' => 'Archived', 'icon' => 'fa-archive', 'where' => 'announcement_archived_at IS NOT NULL', 'title' => 'Nobody sees them'],
];
$show = (string) ($_GET['show'] ?? '');
$show = isset($views[$show]) ? $show : '';
$show_archived = $show === 'archived';

// Who it is for: any, every client (0), or one client
$client_filter = (string) ($_GET['client'] ?? '');
$client_filter = $client_filter === 'every' ? 'every' : (intval($client_filter) > 0 ? (string) intval($client_filter) : '');

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'announcement_title' => "announcement_title $order",
    'client_name' => "announcement_client_id > 0 $order, client_name $order",
    'starts' => "COALESCE(announcement_starts_at, announcement_created_at) $order",
    'ends' => "announcement_ends_at IS NULL $order, announcement_ends_at $order",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'starts';
}

$filters = "announcement_audience = 'agents' AND (announcement_title LIKE '%$q%' OR announcement_body LIKE '%$q%')";
if ($client_filter === 'every') {
    $filters .= " AND announcement_client_id = 0";
} elseif ($client_filter !== '') {
    $filters .= " AND announcement_client_id = " . intval($client_filter);
}

$ready = agentBannersReady();
$num_rows = [0];
$banners = [];
$view_counts = ['' => 0, 'archived' => 0];
if ($ready) {
    $sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS announcement_id, announcement_title, announcement_body, announcement_tone, announcement_client_id,
            announcement_starts_at, announcement_ends_at, announcement_created_at, announcement_archived_at, client_name, user_name
        FROM portal_announcements
        LEFT JOIN clients ON client_id = announcement_client_id
        LEFT JOIN users ON user_id = announcement_created_by
        WHERE $filters AND {$views[$show]['where']}
        ORDER BY {$sortable_columns[$sort]}, announcement_id DESC
        LIMIT $record_from, $record_to");
    $num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));
    while ($row = mysqli_fetch_assoc($sql)) {
        $banners[] = $row;
    }
    $counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(announcement_archived_at IS NULL), 0), COALESCE(SUM(announcement_archived_at IS NOT NULL), 0)
        FROM portal_announcements LEFT JOIN clients ON client_id = announcement_client_id WHERE $filters"));
    $view_counts = ['' => intval($counts[0]), 'archived' => intval($counts[1])];
}

$state_badges = ['showing' => 'text-bg-success', 'scheduled' => 'text-bg-info', 'ended' => 'text-bg-secondary', 'archived' => 'text-bg-secondary'];
$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($view) use ($client_filter) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'client' => $client_filter, 'show' => $view]));
};
$add_url = '/agent/modals/agent_banner/agent_banner_add.php';
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-bullhorn me-2"></i>Agent Banners</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $add_url ?>"<?= $ready ? '' : ' disabled' ?>>
                <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Banner</span>
            </button>
        </div>
    </div>
    <div class="card-header py-3">
        <?php if (!$ready) { ?>
            <div class="alert alert-warning py-2 small"><i class="fas fa-exclamation-triangle me-2"></i>Banners come with the 3.7.2 database update - run it in <a href="update.php">Update</a>.</div>
        <?php } ?>
        <p class="text-secondary small mb-3">Along the top of agents' pages - for every client on every page, or for one client on its pages and its tickets, invoices, quotes and projects. Agents who can edit a client add its banners from its <strong>⋮</strong> menu. Clients don't see them; for a note on the client portal, use <a href="settings_client_portal.php">announcements</a>.</p>
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= escapeHtml($show) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search banners..." title="Title or message">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="client" onchange="this.form.submit()" aria-label="For">
                        <option value="">- For any client -</option>
                        <option value="every" <?= $client_filter === 'every' ? 'selected' : '' ?>>Every client</option>
                        <?php
                        $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL OR client_id = " . intval($client_filter) . " ORDER BY client_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_clients)) { ?>
                            <option value="<?= intval($row['client_id']) ?>" <?= (string) intval($row['client_id']) === $client_filter ? 'selected' : '' ?>><?= escapeHtml($row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <?php foreach ($views as $view_key => $view) { ?>
                <a class="nav-link <?= $show === $view_key ? 'active' : '' ?>" href="<?= escapeHtml($pill_url($view_key)) ?>" title="<?= escapeHtml($view['title']) ?>"><i class="fas fa-fw <?= $view['icon'] ?> me-1"></i><?= escapeHtml($view['label']) ?> <span class="badge rounded-pill ms-1"><?= intval($view_counts[$view_key]) ?></span></a>
            <?php } ?>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('announcement_title', 'Banner') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('client_name', 'For') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('starts', 'From') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('ends', 'Until') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($banners as $row) {
                $announcement_id = intval($row['announcement_id']);
                $title = escapeHtml($row['announcement_title']);
                $edit_url = "/agent/modals/agent_banner/agent_banner_edit.php?id=$announcement_id";
                $state = portalAnnouncementState($row);
                $tone_key = isset(PORTAL_ANNOUNCEMENT_TONES[$row['announcement_tone']]) ? $row['announcement_tone'] : 'info';
                $preview = mb_strimwidth(trim(preg_replace('/\s+/', ' ', (string) $row['announcement_body'])), 0, 140, '...');
                $for_html = intval($row['announcement_client_id'])
                    ? ($row['client_name'] !== null ? '<a href="/agent/client_overview.php?client_id=' . intval($row['announcement_client_id']) . '">' . escapeHtml($row['client_name']) . '</a>' : '<span class="text-body-secondary">A client no longer here</span>')
                    : '<span class="badge text-bg-dark">Every client</span>';
                $from = $row['announcement_starts_at'] ? date('M j, Y g:i A', strtotime($row['announcement_starts_at'])) : date('M j, Y', strtotime($row['announcement_created_at']));
                $until = $row['announcement_ends_at'] ? date('M j, Y g:i A', strtotime($row['announcement_ends_at'])) : 'Until archived';
                ?>
                <tr data-agent-banner-row="<?= $announcement_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-start gap-2">
                            <span class="itflow-announcement-dot is-<?= $tone_key ?> flex-shrink-0 mt-2" title="<?= escapeHtml(PORTAL_ANNOUNCEMENT_TONES[$tone_key]['label']) ?>"></span>
                            <div class="min-w-0">
                                <a class="ajax-modal fw-semibold text-body" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>" title="Edit <?= $title ?>"><?= $title ?></a>
                                <span class="badge rounded-pill <?= $state_badges[$state['key']] ?> ms-1"><?= $state['label'] ?></span>
                                <span class="small text-body-secondary ms-1"><?= escapeHtml(PORTAL_ANNOUNCEMENT_TONES[$tone_key]['label']) ?></span>
                                <?php if ($preview !== '') { ?><div class="small text-body-secondary"><?= escapeHtml($preview) ?></div><?php } ?>
                                <div class="small text-body-secondary">Posted by <?= escapeHtml($row['user_name'] ?: 'someone') ?></div>
                                <!-- On smaller screens who it is for and when come along under the title -->
                                <div class="d-md-none small mt-1"><?= $for_html ?></div>
                                <div class="d-lg-none small text-body-secondary"><?= escapeHtml("From $from, " . lcfirst($until)) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell"><?= $for_html ?></td>
                    <td class="d-none d-lg-table-cell small text-nowrap"><?= escapeHtml($from) ?></td>
                    <td class="d-none d-lg-table-cell small text-nowrap<?= $row['announcement_ends_at'] ? '' : ' text-body-secondary' ?>"><?= escapeHtml($until) ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($row['announcement_archived_at']) { ?>
                                    <a class="dropdown-item" href="/agent/post.php?restore_agent_banner=<?= $announcement_id ?>&csrf_token=<?= $csrf ?>">
                                        <i class="fas fa-fw fa-redo text-secondary me-2"></i>Restore
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger" href="/agent/post.php?archive_agent_banner=<?= $announcement_id ?>&csrf_token=<?= $csrf ?>" title="Nobody sees it">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="/agent/post.php?delete_agent_banner=<?= $announcement_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= $title ?>?" data-confirm-text="It goes for good." data-confirm-button="Delete">
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
        'thing' => 'banners',
        'icon' => 'fa-bullhorn',
        'filtered' => $q !== '' || $client_filter !== '',
        'archived' => $show_archived,
        'views' => ['show'],
        'text' => 'A note your team sees along the top of their pages - "On credit hold, no work without approval", "Call before going onsite", an outage everyone should know about.',
        'actions' => $ready ? [['label' => 'New Banner', 'modal' => $add_url, 'size' => 'lg']] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
