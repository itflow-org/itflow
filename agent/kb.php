<?php

/*
 * The Knowledge Base - every article, for every client: how-tos, fixes and
 * policies written once. Each row has its category's icon, its title and what
 * it is for, its tags, its category, who can read it, when it last changed
 * and when it is next reviewed, and how its readers found it. The pills are the views - every
 * article, the published ones, the drafts, the ones due for review, the ones
 * readers said didn't help that nobody has dealt with, and the archived ones -
 * counted over what the search, category and tags leave. Ticked rows can be
 * put in a category, published, made drafts, archived, restored or deleted.
 * Not found, in the header, lists what readers searched for and didn't find.
 *
 * The work is in functions/kb.php; the handlers are agent/post/kb.php.
 */

// Default Column Sortby/Order Filter
$sort = "kb_article_updated";
$order = "DESC";

require_once "includes/inc_all.php";

$kb_closed = kbClosedText(1);
if ($kb_closed !== '') {
    flashAlert(escapeHtml($kb_closed), 'error');
    redirect('/agent/dashboard.php');
}

$can_edit = kbCan(2);
$can_delete = kbCan(3);

$get_copy = $_GET;
unset($get_copy['sort'], $get_copy['order']);
$url_query_strings_sort = http_build_query($get_copy);

// Category - one of the kind's, or 'none'
$category_filter = (string) ($_GET['category'] ?? '');
$category_filter = $category_filter === 'none' ? 'none' : (intval($category_filter) > 0 ? (string) intval($category_filter) : '');
$category_query = $category_filter === '' ? '' : 'AND kb_article_category_id = ' . ($category_filter === 'none' ? 0 : intval($category_filter));

// Who can read it
$who_filter = isset(KB_VISIBILITY[$_GET['who'] ?? '']) ? (string) $_GET['who'] : '';
$who_query = $who_filter === '' ? '' : "AND kb_article_visibility = '$who_filter'";

// Tags
$tag_filter_ids = tagFilterIds();
$tag_query = tagFilterSql('kb_article', $tag_filter_ids, 'kb_articles.kb_article_id');

// The search - the title, the summary, the words in it and its tags
$search_query = $q !== ''
    ? "AND (kb_article_title LIKE '%$q%' OR kb_article_summary LIKE '%$q%' OR MATCH(kb_article_content_raw) AGAINST ('$q') " . tagSearchSql('kb_article', $q, 'kb_articles.kb_article_id') . ")"
    : '';

$filters = "1 $search_query $category_query $tag_query $who_query";
$joins = "LEFT JOIN categories ON category_id = kb_article_category_id AND kb_article_category_id > 0
    LEFT JOIN users updater ON updater.user_id = COALESCE(NULLIF(kb_article_updated_by, 0), kb_article_created_by)";

// The views
$shows = [
    '' => ['label' => 'All', 'icon' => 'fa-book', 'where' => 'kb_article_archived_at IS NULL', 'title' => 'Every article, published or not'],
    'published' => ['label' => 'Published', 'icon' => 'fa-check-circle', 'where' => 'kb_article_archived_at IS NULL AND kb_article_published_at IS NOT NULL', 'title' => 'Finished'],
    'drafts' => ['label' => 'Drafts', 'icon' => 'fa-pencil-alt', 'where' => 'kb_article_archived_at IS NULL AND kb_article_published_at IS NULL', 'title' => 'Not finished - publish one when it is'],
    'review' => ['label' => 'Review due', 'icon' => 'fa-calendar-check', 'where' => kbReviewDueSql(), 'title' => 'Published articles due for a read-through - an edit, or Mark reviewed, counts', 'alarm' => 'warning'],
    'archived' => ['label' => 'Archived', 'icon' => 'fa-archive', 'where' => 'kb_article_archived_at IS NOT NULL', 'title' => 'Kept, out of the way'],
];
// Readers' feedback, from database 3.9.7 - before Archived
$feedback_ready = kbFeedbackReady();
if ($feedback_ready) {
    $shows = array_slice($shows, 0, 4, true)
        + ['attention' => ['label' => 'Needs a look', 'icon' => 'fa-thumbs-down', 'where' => kbNeedsLookSql(), 'title' => "Readers said these didn't help - and nobody has dealt with that yet", 'alarm' => 'warning']]
        + array_slice($shows, 4, null, true);
}
$show = isset($shows[$_GET['show'] ?? '']) ? ($_GET['show'] ?? '') : '';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'kb_article_title' => "kb_article_title $order",
    'category_name' => "kb_article_category_id = 0 $order, category_order $order, category_name $order, kb_article_title ASC",
    'kb_article_updated' => "COALESCE(kb_article_updated_at, kb_article_created_at) $order",
] + ($feedback_ready ? ['kb_article_views' => "kb_article_views $order"] : []);
if (!isset($sortable_columns[$sort])) {
    $sort = 'kb_article_updated';
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS kb_article_id, kb_article_title, kb_article_summary, kb_article_content, kb_article_category_id,
        kb_article_published_at, kb_article_visibility, kb_article_all_clients, kb_article_review_months, kb_article_reviewed_at, kb_article_created_at,
        kb_article_updated_at, kb_article_archived_at, " . ($feedback_ready ? 'kb_article_views' : '0 AS kb_article_views') . ",
        category_name, category_description, category_color, category_icon, category_type, updater.user_name AS updated_by_name
    FROM kb_articles $joins
    WHERE $filters AND {$shows[$show]['where']}
    ORDER BY {$sortable_columns[$sort]}, kb_article_id DESC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$articles = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $articles[intval($row['kb_article_id'])] = $row;
}
$article_tags = tagsForRecords('kb_article', array_keys($articles));
$audiences = kbAudiencesFor(array_keys($articles));
$feedback_counts = kbFeedbackCounts(array_keys($articles));
$search_misses = kbSearchMissCount();

// How many of each, for the pills - over what the filters leave
$row = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', array_map(function ($s) {
    return "COALESCE(SUM({$s['where']}), 0)";
}, $shows)) . " FROM kb_articles $joins WHERE $filters"));
$counts = array_combine(array_keys($shows), array_map('intval', $row));

$filter_url = function ($changes) {
    $params = array_merge($_GET, $changes);
    unset($params['page']);
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null && $v !== []; }));
};

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};

$add_url = 'modals/kb/kb_article_add.php' . (intval($category_filter) > 0 ? '?category=' . intval($category_filter) : '');
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-book me-2"></i>Knowledge Base</h3>
        <div class="card-tools d-flex gap-2">
            <?php if ($search_misses) { ?>
                <!-- What readers searched for and didn't find (modals/kb/kb_search_misses.php) -->
                <button type="button" class="btn btn-outline-light ajax-modal" data-modal-size="lg" data-modal-url="modals/kb/kb_search_misses.php" title="What readers searched Help and the help center for, and didn't find - in the last <?= KB_SEARCH_MISS_DAYS ?> days">
                    <i class="fas fa-search-minus"></i><span class="d-none d-lg-inline ms-2">Not found</span><span class="badge rounded-pill text-bg-warning ms-2"><?= $search_misses ?></span>
                </button>
            <?php } ?>
            <div class="btn-group">
                <?php if ($can_edit) { ?>
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="xl" data-modal-url="<?= escapeHtml($add_url) ?>">
                        <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Article</span>
                    </button>
                <?php } ?>
                <?php if ($session_is_admin) { ?>
                    <button type="button" class="btn btn-primary dropdown-toggle <?= $can_edit ? 'dropdown-toggle-split' : '' ?>" data-bs-toggle="dropdown" aria-label="More"><?= $can_edit ? '' : '<i class="fas fa-ellipsis-h"></i>' ?></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="/admin/categories.php?category=kb">
                            <i class="fas fa-fw fa-list-ul text-secondary me-2"></i>Categories
                        </a>
                        <a class="dropdown-item" href="/admin/tags.php?type=<?= tagEntity('kb_article')['type'] ?>">
                            <i class="fas fa-fw fa-tags text-secondary me-2"></i>Tags
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="card-header py-3">
        <form autocomplete="off">
            <input type="hidden" name="show" value="<?= escapeHtml($show) ?>">
            <div class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search the Knowledge Base..." title="Title, summary, the words in it and its tags">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="category" onchange="this.form.submit()" aria-label="Category">
                        <option value="">- Any category -</option>
                        <option value="none" <?= $category_filter === 'none' ? 'selected' : '' ?> data-icon="fas fa-minus">No category</option>
                        <?php foreach (categoryPicker('kb', intval($category_filter) > 0 ? [intval($category_filter)] : []) as $category_id => $category_row) { ?>
                            <?= categoryOptionHtml($category_row, (string) intval($category_id) === $category_filter) ?>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="tags[]" multiple data-placeholder="- Any tag -" onchange="this.form.submit()" aria-label="Tags">
                        <?= tagFilterOptionsHtml('kb_article', $tag_filter_ids) ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <select class="form-select select2" name="who" onchange="this.form.submit()" aria-label="Who can read it">
                        <option value="">- Any audience -</option>
                        <?php foreach (KB_VISIBILITY as $visibility => [$label, $icon]) { ?>
                            <option value="<?= $visibility ?>"<?= $who_filter === $visibility ? ' selected' : '' ?> data-icon="fas <?= $icon ?>"><?= $label ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <?php if ($can_edit) { ?>
                            <div class="dropdown" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/kb/kb_article_bulk_category.php" data-bulk="true">
                                        <i class="fas fa-fw fa-list-ul me-2"></i>Set category
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_publish_kb_articles">
                                        <i class="fas fa-fw fa-check-circle me-2"></i>Publish
                                    </button>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_draft_kb_articles">
                                        <i class="fas fa-fw fa-pencil-alt me-2"></i>Make drafts
                                    </button>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_archive_kb_articles">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </button>
                                    <button class="dropdown-item" type="submit" form="bulkActions" name="bulk_restore_kb_articles">
                                        <i class="fas fa-fw fa-box-open me-2"></i>Restore
                                    </button>
                                    <?php if ($can_delete) { ?>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-danger text-bold confirm-link" type="submit" form="bulkActions" name="bulk_delete_kb_articles"
                                            data-confirm-text="The articles go for good, with their versions.">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </form>

        <!-- The views - counted over what the filters above leave -->
        <nav class="nav nav-pills itflow-asset-tabs mt-2 flex-nowrap overflow-auto">
            <?php foreach ($shows as $key => $s) {
                $alarm = $counts[$key] && !empty($s['alarm']) && $show !== $key ? $s['alarm'] : ''; ?>
                <?php if ($key === 'archived') { ?><span class="vr mx-1 my-1"></span><?php } ?>
                <a class="nav-link text-nowrap <?= $show === $key ? 'active' : '' ?>" href="<?= escapeHtml($filter_url(['show' => $key, 'sort' => null, 'order' => null])) ?>" title="<?= escapeHtml($s['title']) ?>">
                    <i class="fas fa-fw <?= $s['icon'] ?> me-1"></i><?= $s['label'] ?>
                    <span class="badge rounded-pill ms-1 <?= $alarm ? "text-bg-$alarm" : '' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php } ?>
        </nav>
    </div>

    <form id="bulkActions" action="post.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="<?php if (!$articles) { echo "d-none"; } ?> text-nowrap table-light">
                <tr>
                    <?php if ($can_edit) { ?>
                        <th class="checkbox-column border-end">
                            <div class="form-check">
                                <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)" title="Select all on this page">
                            </div>
                        </th>
                    <?php } ?>
                    <th class="<?= $can_edit ? '' : 'ps-3' ?>"><?= $sort_link('kb_article_title', 'Article') ?></th>
                    <th class="d-none d-lg-table-cell"><?= $sort_link('category_name', 'Category') ?></th>
                    <th class="d-none d-lg-table-cell">Who can read it</th>
                    <th class="d-none d-md-table-cell"><?= $sort_link('kb_article_updated', 'Updated') ?></th>
                    <th class="d-none d-xl-table-cell">Review</th>
                    <?php if ($feedback_ready) { ?><th class="d-none d-xl-table-cell"><?= $sort_link('kb_article_views', 'Readers') ?></th><?php } ?>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($articles as $kb_article_id => $row) {
                    $title = escapeHtml($row['kb_article_title']);
                    $url = "kb_article.php?kb_article_id=$kb_article_id";
                    $excerpt = kbArticleExcerpt($row);
                    $updated_at = $row['kb_article_updated_at'] ?: $row['kb_article_created_at'];
                    $review = kbReviewState($row);
                    $category_html = $row['category_name'] !== null ? categoryChip($row) : '<span class="text-body-secondary">-</span>';
                    $tags_html = tagPills('kb_article', $kb_article_id, $article_tags[$kb_article_id] ?? [], 'kb.php?', $can_edit && !$row['kb_article_archived_at']);
                    ?>
                    <tr>
                        <?php if ($can_edit) { ?>
                            <td class="checkbox-column border-end">
                                <div class="form-check">
                                    <input class="form-check-input bulk-select" type="checkbox" name="kb_article_ids[]" value="<?= $kb_article_id ?>" aria-label="Select <?= $title ?>">
                                </div>
                            </td>
                        <?php } ?>
                        <td class="<?= $can_edit ? '' : 'ps-3' ?>">
                            <div class="d-flex align-items-start gap-3">
                                <span class="d-none d-sm-inline-flex"><?= kbArticleTile($row) ?></span>
                                <div class="min-w-0">
                                    <div class="d-flex flex-wrap align-items-center column-gap-2">
                                        <a class="fw-semibold text-body" href="<?= $url ?>"><?= $title ?></a>
                                        <?= kbArticleBadges($row) ?>
                                    </div>
                                    <?php if ($excerpt !== '') { ?>
                                        <div class="small text-body-secondary"><?= escapeHtml($excerpt) ?></div>
                                    <?php } ?>
                                    <?php if ($tags_html) { ?>
                                        <div class="itflow-tags-row mt-1"><?= $tags_html ?></div>
                                    <?php } ?>
                                    <!-- On smaller screens its category and when it changed come along under it -->
                                    <div class="d-lg-none small mt-1 d-flex flex-wrap align-items-center gap-2"><?= $row['category_name'] !== null ? categoryChip($row) : '' ?><?= kbAudienceBadge($row, $audiences[$kb_article_id]) ?></div>
                                    <div class="d-md-none small text-body-secondary">Updated <?= timeAgo($updated_at) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-lg-table-cell"><?= $category_html ?></td>
                        <td class="d-none d-lg-table-cell"><?= kbAudienceBadge($row, $audiences[$kb_article_id]) ?></td>
                        <td class="d-none d-md-table-cell small text-nowrap">
                            <span title="<?= escapeHtml($updated_at) ?>"><?= timeAgo($updated_at) ?></span>
                            <?php if ($row['updated_by_name'] !== null) { ?><div class="text-body-secondary"><?= escapeHtml($row['updated_by_name']) ?></div><?php } ?>
                        </td>
                        <td class="d-none d-xl-table-cell small text-nowrap">
                            <?php if ($review) { ?>
                                <span class="<?= $review['due'] ? 'text-warning-emphasis fw-semibold' : 'text-body-secondary' ?>" title="<?= escapeHtml(KB_REVIEW_MONTHS[intval($row['kb_article_review_months'])] ?? '') ?>">
                                    <?= $review['due'] ? 'Due since ' : '' ?><?= date('M j, Y', strtotime($review['next'])) ?>
                                </span>
                            <?php } else { ?>
                                <span class="text-body-secondary">-</span>
                            <?php } ?>
                        </td>
                        <?php if ($feedback_ready) { ?>
                            <td class="d-none d-xl-table-cell small text-nowrap">
                                <?= $row['kb_article_visibility'] === 'agents' && !intval($row['kb_article_views']) ? '<span class="text-body-secondary">-</span>' : kbReadersHtml($row, $feedback_counts[$kb_article_id]) ?>
                            </td>
                        <?php } ?>
                        <td class="text-end">
                            <?php if ($can_edit) { ?>
                                <div class="dropdown dropstart">
                                    <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <?php if (!$row['kb_article_archived_at']) { ?>
                                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="xl" data-modal-url="modals/kb/kb_article_edit.php?id=<?= $kb_article_id ?>">
                                                <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                            </a>
                                            <?= tagMenuItem('kb_article', $kb_article_id) ?>
                                            <?php if ($row['kb_article_published_at']) { ?>
                                                <a class="dropdown-item" href="post.php?draft_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>" title="Back to a draft - not finished">
                                                    <i class="fas fa-fw fa-pencil-alt text-secondary me-2"></i>Make draft
                                                </a>
                                            <?php } else { ?>
                                                <a class="dropdown-item" href="post.php?publish_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>">
                                                    <i class="fas fa-fw fa-check-circle text-secondary me-2"></i>Publish
                                                </a>
                                            <?php } ?>
                                            <?php if ($review) { ?>
                                                <a class="dropdown-item" href="post.php?mark_kb_article_reviewed=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>">
                                                    <i class="fas fa-fw fa-calendar-check text-secondary me-2"></i>Mark reviewed
                                                </a>
                                            <?php } ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="post.php?archive_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>">
                                                <i class="fas fa-fw fa-archive me-2"></i>Archive
                                            </a>
                                        <?php } else { ?>
                                            <a class="dropdown-item" href="post.php?restore_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>">
                                                <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                            </a>
                                        <?php } ?>
                                        <?php if ($can_delete) { ?>
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>"
                                                data-confirm-title="Delete <?= $title ?>?" data-confirm-text="It goes for good, with its versions." data-confirm-button="Delete">
                                                <i class="fas fa-fw fa-trash me-2"></i>Delete
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
    </form>
    <?php
    $empty_state = [
        'thing' => 'articles',
        'icon' => 'fa-book',
        'filtered' => $q !== '' || $category_filter !== '' || $tag_filter_ids || $who_filter !== '',
        'archived' => $show === 'archived',
        'views' => ['show'],
        'view' => emptyStatePill($shows, $show, '', ['review', 'attention']),
        'text' => 'Write down the fixes and how-tos your team gives again and again - setting up email on a phone, connecting to the VPN, the printer that needs a nudge - once, for every client.',
        'actions' => $can_edit ? [['label' => 'New Article', 'modal' => $add_url, 'size' => 'xl']] : [],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<script src="<?= staticFileUrl('/js/bulk_actions.js') ?>"></script>

<?php
require_once "../includes/footer.php";
