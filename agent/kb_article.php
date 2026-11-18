<?php

/*
 * A Knowledge Base article - read it, edit it, publish it, print it, see
 * every earlier version and who did what to it. Laid out like a document's
 * page: a header card with the actions, the article beside a column with its
 * category, its review and its versions.
 *
 * Open to anyone who can read Support; changing it takes Support edit, like
 * the handlers behind it (agent/post/kb.php).
 */

require_once "includes/inc_all.php";

$kb_closed = kbClosedText(1);
if ($kb_closed !== '') {
    flashAlert(escapeHtml($kb_closed), 'error');
    redirect('/agent/dashboard.php');
}

$kb_article_id = intval($_GET['kb_article_id'] ?? 0);
$row = kbArticleRow($kb_article_id);

if (!$row) {
    echo "<div class='text-center'><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='kb.php'><i class='fa fa-fw fa-arrow-left me-2'></i>Back to the Knowledge Base</a></div>";
    require_once "../includes/footer.php";
    exit();
}

$can_edit = kbCan(2);
$can_delete = kbCan(3);

$title = escapeHtml($row['kb_article_title']);
$summary = escapeHtml((string) $row['kb_article_summary']);
$archived = !empty($row['kb_article_archived_at']);
$published = !empty($row['kb_article_published_at']);
$updated_at = $row['kb_article_updated_at'] ?: $row['kb_article_created_at'];
$updated_by = escapeHtml($row['updated_by_name'] ?: (string) $row['created_by_name']);
$review = kbReviewState($row);

$versions = [];
$sql_versions = mysqli_query($mysqli, "SELECT kb_article_version_id, kb_article_version_title, kb_article_version_created_at, user_name
    FROM kb_article_versions LEFT JOIN users ON user_id = kb_article_version_created_by AND kb_article_version_created_by > 0
    WHERE kb_article_version_kb_article_id = $kb_article_id
    ORDER BY kb_article_version_created_at DESC, kb_article_version_id DESC");
while ($version = mysqli_fetch_assoc($sql_versions)) {
    $versions[] = $version;
}

$csrf = $_SESSION['csrf_token'];
$edit_url = "modals/kb/kb_article_edit.php?id=$kb_article_id";
$tags_display = tagPills('kb_article', $kb_article_id, tagsForRecord('kb_article', $kb_article_id), 'kb.php?', $can_edit && !$archived);

// Tab title - used raw, the header escapes it
$page_title = $row['kb_article_title'];

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="kb.php">Knowledge Base</a></li>
    <?php if ($row['category_name'] !== null) { ?>
        <li class="breadcrumb-item"><a href="kb.php?category=<?= intval($row['category_id']) ?>"><?= escapeHtml($row['category_name']) ?></a></li>
    <?php } ?>
    <li class="breadcrumb-item active"><?= $title ?></li>
</ol>

<!-- What it is, and what you can do with it -->
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-start gap-3">
        <?= kbArticleTile($row, 'lg') ?>
        <div class="min-w-0 flex-grow-1">
            <h1 class="h3 mb-1 d-flex flex-wrap align-items-center column-gap-2">
                <span class="text-break"><?= $title ?></span>
                <?= kbArticleBadges($row, 'lg') ?>
            </h1>
            <?php if ($summary !== '') { ?>
                <div class="text-body-secondary"><?= $summary ?></div>
            <?php } ?>
            <?php if ($tags_display) { ?>
                <div class="mt-1 d-print-none"><?= $tags_display ?></div>
            <?php } ?>
            <div class="small text-body-secondary mt-1">
                <span title="<?= escapeHtml($updated_at) ?>">Updated <?= timeAgo($updated_at) ?></span><?= $updated_by !== '' ? " by $updated_by" : '' ?>
                &middot; Written <?= date('M j, Y', strtotime($row['kb_article_created_at'])) ?><?= $row['created_by_name'] !== null ? ' by ' . escapeHtml($row['created_by_name']) : '' ?>
                <?php if ($versions) { ?>&middot; <a class="text-reset d-print-none" href="#kbArticleVersions"><?= count($versions) ?> earlier version<?= count($versions) === 1 ? '' : 's' ?></a><?php } ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <?php if ($can_edit && !$archived) { ?>
                <button type="button" class="btn btn-primary ajax-modal" data-modal-size="xl" data-modal-url="<?= $edit_url ?>">
                    <i class="fas fa-edit me-2"></i>Edit
                </button>
                <?php if (!$published) { ?>
                    <a class="btn btn-success" href="post.php?publish_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>" title="It's finished">
                        <i class="fas fa-check-circle me-2"></i>Publish
                    </a>
                <?php } ?>
            <?php } ?>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print();" title="Print"><i class="fas fa-print"></i></button>
            <?php if ($can_edit) { ?>
                <div class="dropdown">
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="dropdown" title="More"><i class="fas fa-ellipsis-v"></i></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <?php if (!$archived) { ?>
                            <?php if ($published) { ?>
                                <a class="dropdown-item" href="post.php?draft_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>" title="Back to a draft - not finished"><i class="fas fa-fw fa-pencil-alt me-2"></i>Make draft</a>
                            <?php } ?>
                            <?php if ($review) { ?>
                                <a class="dropdown-item" href="post.php?mark_kb_article_reviewed=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-calendar-check me-2"></i>Mark reviewed</a>
                            <?php } ?>
                            <a class="dropdown-item" href="post.php?copy_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>" title="A new draft starting from this one"><i class="fas fa-fw fa-copy me-2"></i>Copy</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="post.php?archive_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                        <?php } else { ?>
                            <a class="dropdown-item" href="post.php?restore_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-box-open me-2"></i>Restore</a>
                        <?php } ?>
                        <?php if ($can_delete) { ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger confirm-link" href="post.php?delete_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>"
                                data-confirm-title="Delete <?= $title ?>?" data-confirm-text="It goes for good, with its versions." data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row">

    <!-- The article -->
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body prettyContent itflow-document-body">
                <?= documentHtmlPurify($row['kb_article_content']) ?: '<p class="text-body-secondary">Nothing written yet.' . ($can_edit && !$archived ? ' <a class="ajax-modal" href="#" data-modal-size="xl" data-modal-url="' . $edit_url . '">Start writing</a>' : '') . '</p>' ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4 d-print-none">

        <!-- Where it sits, and how it is kept up -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="itflow-glance-item" title="Category">
                    <i class="fas fa-fw fa-list-ul"></i>
                    <span class="min-w-0 d-flex align-items-center">
                        <?php if ($row['category_name'] !== null) { ?>
                            <a class="text-reset text-decoration-none" href="kb.php?category=<?= intval($row['category_id']) ?>"><?= categoryChip($row) ?></a>
                        <?php } else { ?>
                            <span class="text-body-secondary">No category</span>
                        <?php } ?>
                    </span>
                </div>
                <div class="itflow-glance-item" title="Status">
                    <i class="fas fa-fw fa-<?= $archived ? 'archive' : ($published ? 'check-circle' : 'pencil-alt') ?>"></i>
                    <span class="min-w-0">
                        <?php if ($archived) { ?>
                            Archived <?= timeAgo($row['kb_article_archived_at']) ?>
                        <?php } elseif ($published) { ?>
                            Published <span title="<?= escapeHtml($row['kb_article_published_at']) ?>"><?= date('M j, Y', strtotime($row['kb_article_published_at'])) ?></span>
                        <?php } else { ?>
                            A draft - not finished
                        <?php } ?>
                    </span>
                </div>
                <div class="itflow-glance-item<?= $review && $review['due'] ? ' text-warning-emphasis' : '' ?>" title="Review">
                    <i class="fas fa-fw fa-calendar-check"></i>
                    <span class="min-w-0">
                        <?php if ($review) { ?>
                            <span class="d-block"><?= $review['due'] ? '<strong>Review due</strong> since ' . date('M j, Y', strtotime($review['next'])) : 'Next review ' . date('M j, Y', strtotime($review['next'])) ?></span>
                            <span class="d-block small text-body-secondary">
                                <?= $review['reviewed'] ? 'Reviewed' : (strtotime($review['last']) === strtotime($row['kb_article_created_at']) ? 'Written' : 'Edited') ?> <?= timeAgo($review['last']) ?><?= $review['reviewed'] && $row['reviewed_by_name'] !== null ? ' by ' . escapeHtml($row['reviewed_by_name']) : '' ?>
                                &middot; <?= lcfirst(KB_REVIEW_MONTHS[intval($row['kb_article_review_months'])] ?? '') ?>
                            </span>
                            <?php if ($can_edit) { ?>
                                <a class="btn btn-sm <?= $review['due'] ? 'btn-warning' : 'btn-light border' ?> mt-2" href="post.php?mark_kb_article_reviewed=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-check me-1"></i>Mark reviewed</a>
                            <?php } ?>
                        <?php } elseif (intval($row['kb_article_review_months']) > 0 && !$archived) { ?>
                            <span class="text-body-secondary">Reviewed <?= lcfirst(KB_REVIEW_MONTHS[intval($row['kb_article_review_months'])] ?? '') ?> once it's published</span>
                        <?php } else { ?>
                            <span class="text-body-secondary">Not reviewed on a schedule</span>
                        <?php } ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Every earlier version -->
        <div class="card mb-3" id="kbArticleVersions">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-layer-group me-2 text-body-secondary"></i>Versions</h3>
            </div>
            <ul class="list-group list-group-flush itflow-document-history">
                <li class="list-group-item">
                    <div class="fw-semibold small">Now</div>
                    <div class="small text-body-secondary">Updated <?= timeAgo($updated_at) ?><?= $updated_by !== '' ? " by $updated_by" : '' ?></div>
                </li>
                <?php foreach ($versions as $version) {
                    $version_id = intval($version['kb_article_version_id']); ?>
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <div class="min-w-0 flex-grow-1">
                            <a class="small fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/kb/kb_article_version.php?id=<?= $version_id ?>" title="<?= escapeHtml($version['kb_article_version_created_at']) ?>">
                                <?= date('M j, Y g:ia', strtotime($version['kb_article_version_created_at'])) ?>
                            </a>
                            <div class="small text-body-secondary text-truncate">
                                <?= escapeHtml($version['user_name'] ?: 'Unknown') ?>
                                <?php if ($version['kb_article_version_title'] !== $row['kb_article_title']) { ?> &middot; was "<?= escapeHtml($version['kb_article_version_title']) ?>"<?php } ?>
                            </div>
                        </div>
                        <?php if ($can_delete) { ?>
                            <a class="itflow-copy confirm-link text-danger" href="post.php?delete_kb_article_version=<?= $version_id ?>&csrf_token=<?= $csrf ?>" title="Delete this version" data-confirm-text="This version is removed from the history for good."><i class="fas fa-trash-alt"></i></a>
                        <?php } ?>
                    </li>
                <?php } ?>
                <?php if (!$versions) { ?>
                    <li class="list-group-item small text-body-secondary">No earlier versions - each edit keeps the one before it here.</li>
                <?php } ?>
            </ul>
        </div>

        <!-- Who did what to it, from the audit log -->
        <?= auditLogHistoryCard(auditLogHistory('Knowledge Base', $kb_article_id, 10)) ?>

    </div>

</div>

<script src="<?= staticFileUrl('/js/pretty_content.js') ?>"></script>

<?php

require_once "../includes/footer.php";
