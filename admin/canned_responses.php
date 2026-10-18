<?php

/*
 * Admin > Canned Responses - replies an agent puts into a ticket in one go,
 * from the picker above the reply box. Each is offered on tickets in the
 * ticket categories it is set to, or on every ticket, and its placeholders
 * are filled in from the ticket as it goes in. Each row shows what it says,
 * where it is offered, and how often it has gone into a reply. A response
 * can be changed, copied as the start of another, archived - not offered on
 * tickets - or deleted. The work is in functions/canned_response.php; the
 * handlers are admin/post/canned_response.php.
 */

// Default Column Sortby Filter
$sort = "canned_response_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$show_archived = ($_GET['show'] ?? '') === 'archived';

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

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS canned_responses.canned_response_id, canned_response_name, canned_response_body, canned_response_created_at, canned_response_updated_at, canned_response_archived_at,
        COALESCE(used.use_count, 0) AS use_count, used.last_used
    FROM canned_responses
    LEFT JOIN (
        SELECT canned_response_use_canned_response_id AS used_id, COUNT(*) AS use_count, MAX(canned_response_use_created_at) AS last_used
        FROM canned_response_uses GROUP BY canned_response_use_canned_response_id
    ) used ON used.used_id = canned_responses.canned_response_id
    WHERE $filters AND canned_response_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL') . "
    ORDER BY {$sortable_columns[$sort]}, canned_responses.canned_response_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$responses = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $responses[intval($row['canned_response_id'])] = $row;
}
$response_categories = cannedResponseCategoriesFor(array_keys($responses));

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(canned_response_archived_at IS NULL), 0), COALESCE(SUM(canned_response_archived_at IS NOT NULL), 0) FROM canned_responses WHERE $filters"));

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($archived) use ($category_filter) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'category' => $category_filter, 'show' => $archived ? 'archived' : '']));
};

$add_url = 'modals/canned_response/canned_response_add.php' . (intval($category_filter) > 0 ? '?category=' . intval($category_filter) : '');
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
            <?php if ($show_archived) { ?><input type="hidden" name="show" value="archived"><?php } ?>
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
            <a class="nav-link <?= $show_archived ? '' : 'active' ?>" href="<?= escapeHtml($pill_url(false)) ?>"><i class="fas fa-fw fa-comment-dots me-1"></i>Active <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
            <a class="nav-link <?= $show_archived ? 'active' : '' ?>" href="<?= escapeHtml($pill_url(true)) ?>" title="Not offered on tickets"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('canned_response_name', 'Response') ?></th>
                <th class="d-none d-lg-table-cell">Offered on</th>
                <th class="d-none d-md-table-cell"><?= $sort_link('use_count', 'Used') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('last_used', 'Last used') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($responses as $canned_response_id => $row) {
                $name = escapeHtml($row['canned_response_name']);
                $edit_url = "modals/canned_response/canned_response_edit.php?id=$canned_response_id";
                $use_count = intval($row['use_count']);
                $used_text = $use_count ? $use_count . ' time' . ($use_count === 1 ? '' : 's') : '';
                $categories = $response_categories[$canned_response_id] ?? [];
                $offered_html = $categories
                    ? '<span class="d-inline-flex flex-wrap gap-1">' . implode('', array_map(function ($category) {
                        return '<span class="badge rounded-pill text-bg-light border fw-normal">' . categoryChip($category) . '</span>';
                    }, $categories)) . '</span>'
                    : '<span class="badge rounded-pill text-bg-light border fw-normal"><i class="fas fa-fw fa-globe text-secondary me-1"></i>Every ticket</span>';
                $preview = cannedResponsePreview($row['canned_response_body']);
                ?>
                <tr data-canned-response-id="<?= $canned_response_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw fa-comment-dots"></i></span>
                            <div class="min-w-0">
                                <a class="ajax-modal fw-semibold text-body" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name ?>"><?= $name ?></a>
                                <?php if ($preview !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($preview) ?></div>
                                <?php } ?>
                                <?php if ($row['canned_response_archived_at']) { ?>
                                    <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['canned_response_archived_at'])) ?></div>
                                <?php } ?>
                                <!-- On smaller screens where it is offered and its use come along under the name -->
                                <div class="d-lg-none mt-1"><?= $offered_html ?></div>
                                <div class="d-md-none small text-body-secondary mt-1"><?= $use_count ? 'Used ' . $used_text . ' · last ' . timeAgo($row['last_used']) : 'Not used yet' ?></div>
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
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $edit_url ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <a class="dropdown-item" href="post.php?copy_canned_response=<?= $canned_response_id ?>&csrf_token=<?= $csrf ?>" title="A new response starting from this one">
                                    <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($row['canned_response_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_canned_response=<?= $canned_response_id ?>&csrf_token=<?= $csrf ?>">
                                        <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger" href="post.php?archive_canned_response=<?= $canned_response_id ?>&csrf_token=<?= $csrf ?>" title="Not offered on tickets">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_canned_response=<?= $canned_response_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="It goes for good, with its record of use. Replies it went into stay as they are." data-confirm-button="Delete">
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
        'thing' => 'canned responses',
        'icon' => 'fa-comment-dots',
        'filtered' => $q !== '' || $category_filter !== '',
        'archived' => $show_archived,
        'views' => ['show'],
        'text' => 'Replies agents put into a ticket in one go - "Password reset done", "Waiting on you". <span class="font-monospace">[Contact]</span>, <span class="font-monospace">[Ticket]</span>, <span class="font-monospace">[Agent]</span> and the like are filled in from the ticket.',
        'actions' => [['label' => 'New Canned Response', 'modal' => $add_url, 'size' => 'lg']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
