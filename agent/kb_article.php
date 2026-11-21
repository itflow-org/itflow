<?php

/*
 * A Knowledge Base article - read it, edit it, publish it, print it, see
 * every earlier version and who did what to it. Laid out like a document's
 * page: a header card with the actions, the article beside a column with its
 * category, who can read it, its review, how its readers found it, what they
 * said didn't help and its versions; its files under it. It can be taken as
 * a PDF, and reviewed by AI (modals/kb/kb_article_review.php).
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
    echo "<div class='text-center'><h1 class='text-secondary mt-5'>Nothing to see here</h1><a class='btn btn-lg btn-secondary mt-3' href='kb.php'><i class='fas fa-fw fa-arrow-left me-2'></i>Back to the Knowledge Base</a></div>";
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
$audience = kbAudiencesFor([$kb_article_id])[$kb_article_id];
$visibility = isset(KB_VISIBILITY[$row['kb_article_visibility']]) ? $row['kb_article_visibility'] : 'agents';
$public_url = kbPublicUrl($kb_article_id, $row['kb_article_title']);

$versions = [];
$sql_versions = mysqli_query($mysqli, "SELECT kb_article_version_id, kb_article_version_title, kb_article_version_created_at, user_name
    FROM kb_article_versions LEFT JOIN users ON user_id = kb_article_version_created_by AND kb_article_version_created_by > 0
    WHERE kb_article_version_kb_article_id = $kb_article_id
    ORDER BY kb_article_version_created_at DESC, kb_article_version_id DESC");
while ($version = mysqli_fetch_assoc($sql_versions)) {
    $versions[] = $version;
}

// How readers outside ITFlow found it - its reads, their answers, and what they said didn't help (database 3.9.7)
$feedback_ready = kbFeedbackReady();
$feedback_counts = kbFeedbackCounts([$kb_article_id])[$kb_article_id];
$feedback = kbFeedbackFor($kb_article_id);

// Its files (database 3.9.8), and whether AI can review it
$files_ready = kbFilesReady();
$files = kbArticleFiles($kb_article_id);
$ai_review = aiReady('Documentation');

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
                <span title="<?= formatDateTime($updated_at) ?>">Updated <?= timeAgo($updated_at) ?></span><?= $updated_by !== '' ? " by $updated_by" : '' ?>
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
            <?php if ($ai_review) { ?>
                <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/kb/kb_article_review.php?id=<?= $kb_article_id ?>" title="AI reads it and says what to fix or check, what is out of date, and what shouldn't be shared">
                    <i class="fas fa-clipboard-check"></i><span class="d-none d-md-inline ms-2">Review with AI</span>
                </button>
            <?php } ?>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print();" title="Print"><i class="fas fa-print"></i></button>
            <a class="btn btn-outline-secondary" href="post.php?export_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>" title="Export as PDF"><i class="fas fa-file-pdf"></i><span class="d-none d-md-inline ms-2">PDF</span></a>
            <?php if ($can_edit) { ?>
                <div class="dropdown">
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="dropdown" title="More actions"><i class="fas fa-ellipsis-v"></i></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <?php if (!$archived) { ?>
                            <?php if ($published) { ?>
                                <a class="dropdown-item" href="post.php?draft_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>" title="Back to a draft - not finished"><i class="fas fa-fw fa-pencil-alt me-2"></i>Make Draft</a>
                            <?php } ?>
                            <?php if ($review) { ?>
                                <a class="dropdown-item" href="post.php?mark_kb_article_reviewed=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-calendar-check me-2"></i>Mark Reviewed</a>
                            <?php } ?>
                            <a class="dropdown-item" href="post.php?copy_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>" title="A new draft starting from this one"><i class="far fa-fw fa-copy me-2"></i>Copy</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="post.php?archive_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                        <?php } else { ?>
                            <a class="dropdown-item" href="post.php?restore_kb_article=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
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

        <!-- The files that go with it - downloaded by whoever can read it -->
        <?php if ($files_ready && ($files || ($can_edit && !$archived))) { ?>
            <div class="card mb-3 d-print-none" id="kbArticleFiles">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-paperclip me-2 text-body-secondary"></i>Files<?= $files ? ' <span class="badge rounded-pill text-bg-secondary ms-1">' . count($files) . '</span>' : '' ?></h3>
                    <?php if ($visibility !== 'agents') { ?>
                        <div class="card-tools small text-body-secondary">Its readers can download them</div>
                    <?php } ?>
                </div>
                <?php if ($files) { ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($files as $file) {
                            $file_id = intval($file['kb_article_file_id']);
                            $ext = strtolower(pathinfo($file['kb_article_file_name'], PATHINFO_EXTENSION)); ?>
                            <li class="list-group-item d-flex align-items-center gap-3">
                                <?= fileTypeTile($ext) ?>
                                <div class="min-w-0 flex-grow-1">
                                    <a class="fw-semibold text-break" href="kb_file.php?file_id=<?= $file_id ?>&action=view" target="_blank" rel="noopener" title="Open it - or download it, for a kind the browser can't show"><?= escapeHtml($file['kb_article_file_name']) ?></a>
                                    <div class="small text-body-secondary"><?= formatBytes(intval($file['kb_article_file_size'])) ?> &middot; added <?= timeAgo($file['kb_article_file_created_at']) ?><?= $file['user_name'] !== null ? ' by ' . escapeHtml($file['user_name']) : '' ?></div>
                                </div>
                                <a class="btn btn-sm btn-light border" href="kb_file.php?file_id=<?= $file_id ?>" title="Download"><i class="fas fa-download"></i></a>
                                <?php if ($can_edit) { ?>
                                    <a class="itflow-copy text-danger confirm-link" href="post.php?delete_kb_article_file=<?= $file_id ?>&csrf_token=<?= $csrf ?>" title="Take it off"
                                        data-confirm-title="Take <?= escapeHtml($file['kb_article_file_name']) ?> off?" data-confirm-text="It goes for good." data-confirm-button="Take it off"><i class="fas fa-trash"></i></a>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
                <?php if ($can_edit && !$archived && count($files) < KB_ARTICLE_MAX_FILES) { ?>
                    <div class="card-body<?= $files ? ' border-top' : '' ?>">
                        <form action="post.php" method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="kb_article_id" value="<?= $kb_article_id ?>">
                            <input type="file" class="form-control form-control-sm flex-grow-1 w-auto" name="files[]" multiple required aria-label="Files to add" accept="<?= escapeHtml('.' . implode(', .', fileUploadExtensions())) ?>">
                            <button type="submit" class="btn btn-sm btn-primary" name="upload_kb_article_files"><i class="fas fa-upload me-1"></i>Add</button>
                        </form>
                        <?php if (!$files) { ?><div class="form-text">A script, a form, a vendor's guide - whoever can read the article can download them.</div><?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <div class="col-lg-4 d-print-none">

        <!-- Its headings, to jump to - filled by js/document_contents.js when it has a few -->
        <div class="card mb-3 itflow-document-contents" data-document-contents hidden>
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-list-ul me-2 text-body-secondary"></i>Contents</h3>
            </div>
            <nav class="card-body py-2" aria-label="Contents"></nav>
        </div>

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
                <div class="itflow-glance-item" title="Who can read it">
                    <i class="fas fa-fw <?= KB_VISIBILITY[$visibility][1] ?>"></i>
                    <span class="min-w-0">
                        <span class="d-flex align-items-center">
                            <?= escapeHtml(kbAudienceText($row, $audience)) ?>
                            <?php if ($can_edit && !$archived) { ?>
                                <a class="itflow-copy ajax-modal" href="#" data-modal-size="xl" data-modal-url="<?= $edit_url ?>" title="Change who can read it"><i class="fas fa-pen"></i></a>
                            <?php } ?>
                        </span>
                        <?php if ($visibility === 'clients' && !intval($row['kb_article_all_clients']) && ($audience['tags'] || $audience['clients'])) { ?>
                            <span class="d-flex flex-wrap gap-1 mt-1">
                                <?php foreach ($audience['tags'] as $tag) { ?>
                                    <a class="text-decoration-none" href="clients.php?tags[]=<?= intval($tag['tag_id']) ?>" title="The clients tagged <?= escapeHtml($tag['tag_name']) ?>"><?= tagPill($tag) ?></a>
                                <?php } ?>
                            </span>
                            <?php if ($audience['clients']) { ?>
                                <span class="d-block small mt-1">
                                    <?= implode(', ', array_map(function ($client_id, $name) {
                                        return hasClientAccess($client_id) ? '<a href="client_overview.php?client_id=' . intval($client_id) . '">' . escapeHtml($name) . '</a>' : escapeHtml($name);
                                    }, array_keys($audience['clients']), $audience['clients'])) ?>
                                </span>
                            <?php } ?>
                        <?php } ?>
                        <?php if ($visibility !== 'agents') { ?>
                            <span class="d-block small text-body-secondary">
                                <?php if (!$published || $archived) { ?>
                                    Not shown yet - only <?= $archived ? 'restored and ' : '' ?>published articles are
                                <?php } elseif ($visibility === 'clients') { ?>
                                    In their client portal, under Help
                                <?php } elseif (kbPublicOn()) { ?>
                                    In every client's portal, and the public help center
                                <?php } else { ?>
                                    In every client's portal. The public help center is off<?= $session_is_admin ? ' - <a href="/admin/settings_kb.php">switch it on</a>' : '' ?>
                                <?php } ?>
                            </span>
                            <?php if ($visibility === 'public' && kbPublicOn() && $published && !$archived) { ?>
                                <span class="d-flex align-items-center small mt-1 min-w-0">
                                    <a class="text-truncate" href="<?= escapeHtml($public_url) ?>" target="_blank" rel="noopener"><?= escapeHtml(preg_replace('#^https://#', '', $public_url)) ?></a>
                                    <button type="button" class="itflow-copy clipboardjs" title="Copy the link" data-clipboard-text="<?= escapeHtml($public_url) ?>"><i class="far fa-copy"></i></button>
                                </span>
                            <?php } ?>
                        <?php } ?>
                    </span>
                </div>
                <div class="itflow-glance-item" title="Status">
                    <i class="fas fa-fw fa-<?= $archived ? 'archive' : ($published ? 'check-circle' : 'pencil-alt') ?>"></i>
                    <span class="min-w-0">
                        <?php if ($archived) { ?>
                            Archived <?= timeAgo($row['kb_article_archived_at']) ?>
                        <?php } elseif ($published) { ?>
                            Published <span title="<?= formatDateTime($row['kb_article_published_at']) ?>"><?= date('M j, Y', strtotime($row['kb_article_published_at'])) ?></span>
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
                                <a class="btn btn-sm <?= $review['due'] ? 'btn-warning' : 'btn-light border' ?> mt-2" href="post.php?mark_kb_article_reviewed=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-check me-1"></i>Mark Reviewed</a>
                            <?php } ?>
                        <?php } elseif (intval($row['kb_article_review_months']) > 0 && !$archived) { ?>
                            <span class="text-body-secondary">Reviewed <?= lcfirst(KB_REVIEW_MONTHS[intval($row['kb_article_review_months'])] ?? '') ?> once it's published</span>
                        <?php } else { ?>
                            <span class="text-body-secondary">Not reviewed on a schedule</span>
                        <?php } ?>
                    </span>
                </div>
                <?php if ($feedback_ready && $visibility !== 'agents') {
                    $views = intval($row['kb_article_views']);
                    $answers = $feedback_counts['yes'] + $feedback_counts['no']; ?>
                    <div class="itflow-glance-item" title="Readers - in the client portal and the public help center">
                        <i class="fas fa-fw fa-chart-bar"></i>
                        <span class="min-w-0">
                            <span class="d-block">Read <?= number_format($views) ?> time<?= $views === 1 ? '' : 's' ?></span>
                            <span class="d-block small text-body-secondary">
                                <?php if ($answers) { ?>
                                    <?= $feedback_counts['yes'] ?> said it helped, <?= $feedback_counts['no'] ?> said it didn't &middot; <?= round(100 * $feedback_counts['yes'] / $answers) ?>%
                                <?php } else { ?>
                                    Nobody has said whether it helped yet
                                <?php } ?>
                            </span>
                        </span>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- What readers said didn't help -->
        <?php if ($feedback) { ?>
            <div class="card mb-3" id="kbArticleFeedback">
                <div class="card-header">
                    <h3 class="card-title mb-0"><i class="fas fa-fw fa-thumbs-down me-2 text-body-secondary"></i>Feedback
                        <?php if ($feedback_counts['open']) { ?><span class="badge rounded-pill text-bg-warning ms-1" title="Not dealt with yet"><?= number_format(intval($feedback_counts['open'])) ?></span><?php } ?>
                    </h3>
                    <?php if ($can_edit && $feedback_counts['open'] > 1) { ?>
                        <div class="card-tools">
                            <a class="btn btn-tool" href="post.php?deal_with_kb_article_feedback=<?= $kb_article_id ?>&csrf_token=<?= $csrf ?>" title="Mark them all dealt with"><i class="fas fa-check-double"></i></a>
                        </div>
                    <?php } ?>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($feedback as $answer) {
                        $open = $answer['kb_article_feedback_dealt_at'] === null;
                        $comment = trim((string) $answer['kb_article_feedback_comment']);
                        $answer_client_id = intval($answer['kb_article_feedback_client_id']);
                        if ($answer['kb_article_feedback_source'] === 'public') {
                            $who = 'Someone on the public help center';
                        } elseif ($answer['contact_name'] !== null) {
                            $who = '<a href="contact.php?client_id=' . $answer_client_id . '&amp;contact_id=' . intval($answer['kb_article_feedback_contact_id']) . '">' . escapeHtml($answer['contact_name']) . '</a>'
                                . ($answer['client_name'] !== null ? ' &middot; <a class="text-reset" href="client_overview.php?client_id=' . $answer_client_id . '">' . escapeHtml($answer['client_name']) . '</a>' : '');
                        } else {
                            $who = $answer['client_name'] !== null ? escapeHtml($answer['client_name']) : 'A contact at a client';
                        } ?>
                        <li class="list-group-item d-flex align-items-start gap-2<?= $open ? '' : ' text-body-secondary' ?>">
                            <div class="min-w-0 flex-grow-1">
                                <div class="small<?= $comment === '' ? ' fst-italic' : '' ?>"><?= $comment !== '' ? nl2br(escapeHtml($comment)) : "Said it didn't help" ?></div>
                                <div class="small text-body-secondary">
                                    <?= $who ?> &middot; <span title="<?= formatDateTime($answer['kb_article_feedback_created_at']) ?>"><?= timeAgo($answer['kb_article_feedback_created_at']) ?></span>
                                    <?php if (!$open) { ?>&middot; dealt with<?= $answer['dealt_by_name'] !== null ? ' by ' . escapeHtml($answer['dealt_by_name']) : '' ?><?php } ?>
                                </div>
                            </div>
                            <?php if ($open && $can_edit) { ?>
                                <a class="btn btn-sm btn-light border flex-shrink-0" href="post.php?deal_with_kb_feedback=<?= intval($answer['kb_article_feedback_id']) ?>&csrf_token=<?= $csrf ?>" title="You've seen to it - it leaves Needs a look"><i class="fas fa-check me-1"></i>Dealt With</a>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>

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
                            <a class="small fw-semibold ajax-modal" href="#" data-modal-size="lg" data-modal-url="modals/kb/kb_article_version.php?id=<?= $version_id ?>" title="<?= formatDateTime($version['kb_article_version_created_at']) ?>">
                                <?= date('M j, Y g:i A', strtotime($version['kb_article_version_created_at'])) ?>
                            </a>
                            <div class="small text-body-secondary text-truncate">
                                <?= escapeHtml($version['user_name'] ?: 'Unknown') ?>
                                <?php if ($version['kb_article_version_title'] !== $row['kb_article_title']) { ?> &middot; was "<?= escapeHtml($version['kb_article_version_title']) ?>"<?php } ?>
                            </div>
                        </div>
                        <?php if ($can_delete) { ?>
                            <a class="itflow-copy confirm-link text-danger" href="post.php?delete_kb_article_version=<?= $version_id ?>&csrf_token=<?= $csrf ?>" title="Delete this version" data-confirm-text="This version is removed from the history for good."><i class="fas fa-trash"></i></a>
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
<script src="<?= staticFileUrl('/js/document_contents.js') ?>"></script>

<?php

require_once "../includes/footer.php";
