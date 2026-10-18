<?php

/*
 * Your account > Canned Responses - your own canned responses, offered to you
 * alone (first in the picker, as Yours), and the shared ones from Admin >
 * Canned Responses, offered to everyone. Yours are made here, copied from a
 * shared one, or saved from a reply on a ticket (Save your reply as a canned response, in
 * the picker); each can be changed, copied, archived - not offered - or
 * deleted. How often each went into a reply is yours alone. An admin can
 * share one of theirs with everyone (admin/post/canned_response.php). The
 * work is in functions/canned_response.php; the handlers are
 * agent/user/post/canned_response.php.
 */

// Default Column Sortby Filter
$sort = "canned_response_name";
$order = "ASC";

require_once "includes/inc_all_user.php";

enforceUserPermission('module_support', 2);

$user_id = intval($session_user_id);

// The views: your own, archived or not, and the shared ones offered now
$views = [
    '' => ['label' => 'Yours', 'icon' => 'fa-user', 'where' => "canned_response_user_id = $user_id", 'title' => 'Offered to you alone - first in the picker'],
    'shared' => ['label' => 'Shared', 'icon' => 'fa-users', 'where' => "canned_response_user_id = 0 AND canned_response_archived_at IS NULL", 'title' => 'Offered to everyone - copy one to make it yours'],
];
$show = (string) ($_GET['show'] ?? '');
$show = isset($views[$show]) ? $show : '';
$yours = $show === '';

// Where it is offered: any, every ticket (no category), or one ticket category
$category_filter = (string) ($_GET['category'] ?? '');
$category_filter = $category_filter === 'every' || intval($category_filter) > 0 ? ($category_filter === 'every' ? 'every' : (string) intval($category_filter)) : '';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'canned_response_name' => "canned_response_name $order",
    'use_count' => "use_count $order, canned_response_name ASC",
    'last_used' => "last_used IS NULL, last_used $order, canned_response_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'canned_response_name';
}

$filters = "(canned_response_name LIKE '%$q%' OR canned_response_body LIKE '%$q%')";
if ($category_filter === 'every') {
    $filters .= " AND NOT EXISTS (SELECT 1 FROM canned_response_categories c WHERE c.canned_response_id = canned_responses.canned_response_id)";
} elseif ($category_filter !== '') {
    $filters .= " AND EXISTS (SELECT 1 FROM canned_response_categories c WHERE c.canned_response_id = canned_responses.canned_response_id AND c.category_id = " . intval($category_filter) . ")";
}

// How often each went into a reply of yours
$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS canned_responses.canned_response_id, canned_response_name, canned_response_body, canned_response_reply_type, canned_response_ticket_status, canned_response_minutes,
        canned_response_user_id, canned_response_created_at, canned_response_updated_at, canned_response_archived_at,
        COALESCE(used.use_count, 0) AS use_count, used.last_used
    FROM canned_responses
    LEFT JOIN (
        SELECT canned_response_use_canned_response_id AS used_id, COUNT(*) AS use_count, MAX(canned_response_use_created_at) AS last_used
        FROM canned_response_uses WHERE canned_response_use_user_id = $user_id GROUP BY canned_response_use_canned_response_id
    ) used ON used.used_id = canned_responses.canned_response_id
    WHERE $filters AND {$views[$show]['where']}
    ORDER BY {$sortable_columns[$sort]}, canned_responses.canned_response_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$responses = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $responses[intval($row['canned_response_id'])] = $row;
}
$response_categories = cannedResponseCategoriesFor(array_keys($responses));
$response_files = cannedResponseFilesFor(array_keys($responses));

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', array_map(function ($view) {
    return "COALESCE(SUM({$view['where']}), 0)";
}, $views)) . " FROM canned_responses WHERE $filters"));
$view_counts = array_combine(array_keys($views), $counts);

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($view) use ($category_filter) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'category' => $category_filter, 'show' => $view]));
};

$add_url = 'modals/canned_response_add.php' . (intval($category_filter) > 0 ? '?category=' . intval($category_filter) : '');
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-comment-dots me-2"></i>Canned Responses</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= escapeHtml($add_url) ?>"><i class="fas fa-plus me-2"></i>New Canned Response</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= escapeHtml($show) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search canned responses..." title="Name or what it says">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select class="form-select select2" name="category" onchange="this.form.submit()" aria-label="Offered on">
                        <option value="">- Offered on any ticket -</option>
                        <option value="every" <?= $category_filter === 'every' ? 'selected' : '' ?>>Every ticket</option>
                        <?php foreach (categoryPicker('Ticket', $category_filter !== '' && $category_filter !== 'every' ? [intval($category_filter)] : []) as $category_id => $category_row) { ?>
                            <?= categoryOptionHtml($category_row, (string) intval($category_id) === $category_filter) ?>
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
                <th class="ps-3"><?= $sort_link('canned_response_name', 'Response') ?></th>
                <th class="d-none d-lg-table-cell">Offered on</th>
                <th class="d-none d-md-table-cell" title="In your replies"><?= $sort_link('use_count', 'You used it') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('last_used', 'Last used') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($responses as $canned_response_id => $row) {
                $name = escapeHtml($row['canned_response_name']);
                $mine = intval($row['canned_response_user_id']) === $user_id;
                $edit_url = "modals/canned_response_edit.php?id=$canned_response_id";
                $use_count = intval($row['use_count']);
                $used_text = $use_count ? $use_count . ' time' . ($use_count === 1 ? '' : 's') : '';
                $categories = $response_categories[$canned_response_id] ?? [];
                $offered_html = $categories
                    ? '<span class="d-inline-flex flex-wrap gap-1">' . implode('', array_map(function ($category) {
                        return '<span class="badge rounded-pill text-bg-light border fw-normal">' . categoryChip($category) . '</span>';
                    }, $categories)) . '</span>'
                    : '<span class="badge rounded-pill text-bg-light border fw-normal"><i class="fas fa-fw fa-globe text-secondary me-1"></i>Every ticket</span>';
                $preview = cannedResponsePreview($row['canned_response_body']);
                $sets_up = cannedResponseSetsUpHtml($row);
                $files = $response_files[$canned_response_id] ?? [];
                ?>
                <tr data-canned-response-id="<?= $canned_response_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw fa-<?= $mine ? 'comment-dots' : 'users' ?>"></i></span>
                            <div class="min-w-0">
                                <?php if ($mine) { ?>
                                    <a class="ajax-modal fw-semibold text-body" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name ?>"><?= $name ?></a>
                                <?php } else { ?>
                                    <span class="fw-semibold"><?= $name ?></span>
                                <?php } ?>
                                <?php if ($preview !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($preview) ?></div>
                                <?php } ?>
                                <?php if ($sets_up !== '' || $files) { ?>
                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                        <?php if ($sets_up !== '') { ?><span title="Set on the reply when it is picked"><?= $sets_up ?></span><?php } ?>
                                        <?php if ($files) { ?>
                                            <span class="badge rounded-pill text-bg-light border fw-normal" title="Goes out with the reply: <?= escapeHtml(implode(', ', array_column($files, 'canned_response_file_name'))) ?>"><i class="fas fa-fw fa-paperclip text-secondary me-1"></i><?= count($files) ?> file<?= count($files) === 1 ? '' : 's' ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                                <?php if ($row['canned_response_archived_at']) { ?>
                                    <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['canned_response_archived_at'])) ?> - not offered</div>
                                <?php } ?>
                                <!-- On smaller screens where it is offered and its use come along under the name -->
                                <div class="d-lg-none mt-1"><?= $offered_html ?></div>
                                <div class="d-md-none small text-body-secondary mt-1"><?= $use_count ? 'You used it ' . $used_text . ' · last ' . timeAgo($row['last_used']) : 'Not used by you yet' ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-lg-table-cell"><?= $offered_html ?></td>
                    <td class="d-none d-md-table-cell small text-nowrap"><?= $use_count ? $used_text : '<span class="text-body-secondary">Not yet</span>' ?></td>
                    <td class="d-none d-md-table-cell small text-body-secondary text-nowrap"<?= $row['last_used'] ? ' title="' . escapeHtml($row['last_used']) . '"' : '' ?>><?= $row['last_used'] ? timeAgo($row['last_used']) : '-' ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <?php if ($mine) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>">
                                        <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                    </a>
                                    <a class="dropdown-item" href="post.php?copy_your_canned_response=<?= $canned_response_id ?>&csrf_token=<?= $csrf ?>" title="A new one of yours starting from this one">
                                        <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                    </a>
                                    <?php if ($session_is_admin && !$row['canned_response_archived_at']) { ?>
                                        <a class="dropdown-item confirm-link" href="/admin/post.php?share_canned_response=<?= $canned_response_id ?>&csrf_token=<?= $csrf ?>"
                                            data-confirm-title="Share <?= $name ?> with everyone?" data-confirm-text="It moves to the shared ones in Admin > Canned Responses, and every agent is offered it." data-confirm-button="Share">
                                            <i class="fas fa-fw fa-users text-secondary me-2"></i>Share with everyone
                                        </a>
                                    <?php } ?>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($row['canned_response_archived_at']) { ?>
                                        <a class="dropdown-item" href="post.php?restore_your_canned_response=<?= $canned_response_id ?>&csrf_token=<?= $csrf ?>">
                                            <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                        </a>
                                    <?php } else { ?>
                                        <a class="dropdown-item text-danger" href="post.php?archive_your_canned_response=<?= $canned_response_id ?>&csrf_token=<?= $csrf ?>" title="Not offered on tickets">
                                            <i class="fas fa-fw fa-archive me-2"></i>Archive
                                        </a>
                                    <?php } ?>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_your_canned_response=<?= $canned_response_id ?>&csrf_token=<?= $csrf ?>"
                                        data-confirm-title="Delete <?= $name ?>?" data-confirm-text="It goes for good, with its record of use. Replies it went into stay as they are." data-confirm-button="Delete">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item" href="post.php?copy_canned_response_to_yours=<?= $canned_response_id ?>&csrf_token=<?= $csrf ?>" title="A new one of yours starting from this one, to change as you like">
                                        <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy to yours
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
        'thing' => 'canned responses',
        'icon' => 'fa-comment-dots',
        'filtered' => $q !== '' || $category_filter !== '',
        'views' => ['show'],
        'title' => 'No canned responses of your own yet',
        'text' => 'Yours are offered to you alone, first in the picker above a reply. Make one here, copy a shared one, or write a reply on a ticket and save it from the picker with <strong>Save your reply as a canned response</strong>.',
        'actions' => [['label' => 'New Canned Response', 'modal' => $add_url, 'size' => 'lg'], ['label' => 'See the shared ones', 'href' => $pill_url('shared'), 'icon' => 'fa-users']],
        'view' => $yours ? [] : [
            'title' => 'No shared canned responses',
            'icon' => 'fa-users',
            'text' => 'An admin makes them in Admin > Canned Responses.',
            'actions' => [],
        ],
    ];
    require_once "../../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../../includes/footer.php";
