<?php

/*
 * One document template - what it says, with its [words] marked: the
 * placeholders filled in from the client, the tables of the client's records,
 * and what it asks for when a document is made (its fill-ins: set up, dragged
 * into order) - who made it and last changed it, and its use: how many
 * documents have been made from it, for how many clients, and the latest of
 * them - and who should have one: the clients missing one, and the documents
 * made from it due a review. A new document can be made from here for a
 * client, for several at once, or for every client missing one. The work is in
 * functions/document_template.php.
 */

require_once "includes/inc_all_admin.php";

$template = documentTemplateRow($_GET['document_template_id'] ?? 0);
if (!$template) {
    echo emptyStateHtml(['thing' => 'document templates', 'title' => 'That document template is gone', 'text' => 'It may have been deleted.', 'icon' => 'fa-file-alt', 'filtered' => false,
        'actions' => [['label' => 'Document Templates', 'href' => 'document_templates.php']]]);
    require_once "../includes/footer.php";
    exit();
}

$document_template_id = intval($template['document_template_id']);
$name = escapeHtml($template['document_template_name']);
$archived = !empty($template['document_template_archived_at']);
$usage = documentTemplateUsage([$document_template_id])[$document_template_id] ?? ['count' => 0, 'clients' => 0, 'last' => null];
$content = trim((string) $template['document_template_content']) !== '' ? documentTemplateMarkBrackets(documentHtmlPurify($template['document_template_content'])) : '';
$used = documentTemplateWordsUsed($template);
$fill_ins = documentTemplateFillIns($template, documentTemplateFillInRowsFor([$document_template_id])[$document_template_id]);
$fill_in_url = "modals/document_template/document_template_fill_in.php?document_template_id=$document_template_id";
$images = is_dir(documentTemplateDir($document_template_id)) ? count(glob(documentTemplateDir($document_template_id) . '/*')) : 0;
$csrf = $_SESSION['csrf_token'];

// The documents made from it most lately
$recent = [];
$sql = mysqli_query($mysqli, "SELECT document_id, document_name, document_client_id, document_created_at, client_name, user_name FROM documents
    LEFT JOIN clients ON client_id = document_client_id
    LEFT JOIN users ON user_id = document_created_by AND document_created_by > 0
    WHERE document_template_id = $document_template_id AND document_archived_at IS NULL
    ORDER BY document_created_at DESC, document_id DESC LIMIT 10");
while ($row = mysqli_fetch_assoc($sql)) {
    $recent[] = $row;
}

// Who should have one, and the documents due a review - neither counts while it is archived
$required = intval($template['document_template_required']);
$required_tags = documentTemplateRequiredTags([$document_template_id])[$document_template_id];
$scope_text = documentTemplateScopeText($required, $required_tags);
$coverage = $required && !$archived ? documentTemplateCoverage($document_template_id, $required, array_keys($required_tags)) : null;
$review_months = intval($template['document_template_review_months']);
$due = $review_months && !$archived ? documentsReviewDue("documents.document_template_id = $document_template_id") : [];
$list_limit = 8;
$missing_text = $coverage && $coverage['missing'] ? (count($coverage['missing']) === 1 ? 'the client missing one' : 'the ' . count($coverage['missing']) . ' clients missing one') : '';

$edit_url = "modals/document_template/document_template_edit.php?id=$document_template_id";
$use_url = "modals/document_template/document_template_use.php?id=$document_template_id";
$several_url = "modals/document_template/document_template_use_several.php?id=$document_template_id";
$required_url = "modals/document_template/document_template_required.php?id=$document_template_id";

?>

<ol class="breadcrumb d-print-none">
    <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
    <li class="breadcrumb-item"><a href="users.php">Admin</a></li>
    <li class="breadcrumb-item"><a href="document_templates.php">Document Templates</a></li>
    <li class="breadcrumb-item active"><?= $name ?></li>
</ol>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="itflow-asset-icon itflow-asset-tone-other" aria-hidden="true"><i class="fas fa-fw fa-file-alt"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h1 class="h4 mb-0"><?= $name ?></h1>
            <?php if (trim((string) $template['document_template_description']) !== '') { ?>
                <div class="text-secondary"><?= escapeHtml($template['document_template_description']) ?></div>
            <?php } ?>
            <?php if ($archived) { ?>
                <div class="small text-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($template['document_template_archived_at'])) ?> - not offered for new documents</div>
            <?php } ?>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <?php if (!$archived) { ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $use_url ?>"><i class="fas fa-fw fa-plus me-2"></i>New document for a client</button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="More ways to make one"></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $several_url ?>"><i class="fas fa-fw fa-copy me-2"></i>For several clients</a>
                        <?php if ($coverage && $coverage['missing']) { ?>
                            <a class="dropdown-item ajax-modal" href="#" data-modal-size="lg" data-modal-url="<?= $several_url ?>&missing=1"><i class="fas fa-fw fa-clipboard-check me-2"></i>For <?= $missing_text ?></a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
            <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="xl" data-modal-url="<?= $edit_url ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-h"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="post.php?copy_document_template=<?= $document_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-copy me-2"></i>Copy</a>
                    <a class="dropdown-item" href="#" onclick="window.print(); return false;"><i class="fas fa-fw fa-print me-2"></i>Print</a>
                    <?php if ($archived) { ?>
                        <a class="dropdown-item" href="post.php?restore_document_template=<?= $document_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-redo me-2"></i>Restore</a>
                    <?php } else { ?>
                        <a class="dropdown-item" href="post.php?archive_document_template=<?= $document_template_id ?>&csrf_token=<?= $csrf ?>"><i class="fas fa-fw fa-archive me-2"></i>Archive</a>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger confirm-link" href="post.php?delete_document_template=<?= $document_template_id ?>&from_page=1&csrf_token=<?= $csrf ?>"
                        data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Its images go with it. Documents made from it stay as they are." data-confirm-button="Delete"><i class="fas fa-fw fa-trash me-2"></i>Delete</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center d-print-none">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-file-alt me-2"></i>What it says</h3>
                <button type="button" class="btn btn-sm btn-link ms-auto ajax-modal" data-modal-size="xl" data-modal-url="<?= $edit_url ?>"><i class="fas fa-edit me-1"></i>Edit</button>
            </div>
            <div class="card-body prettyContent">
                <?= $content !== '' ? $content : '<span class="text-secondary">Nothing yet - edit it to write what each document starts with.</span>' ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4 d-print-none">

        <div class="card mb-3" id="documentTemplateCoverage">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-clipboard-check me-2"></i>Who should have one</h3>
                <button type="button" class="btn btn-sm btn-link ms-auto ajax-modal" data-modal-url="<?= $required_url ?>"><i class="fas fa-edit me-1"></i>Set up</button>
            </div>
            <div class="card-body">
                <?php if (!$required && !$review_months) { ?>
                    <div class="small text-secondary">No one in particular, and no reviews. Set it up to see which clients are missing one - every client, or the ones with a tag - and which documents made from it are due a review.</div>
                <?php } elseif ($archived) { ?>
                    <div class="small text-secondary"><?= escapeHtml($scope_text ?: 'No one in particular') ?><?= $review_months ? ', reviewed ' . lcfirst(DOCUMENT_TEMPLATE_REVIEW_MONTHS[$review_months]) : '' ?> - not counted while it is archived.</div>
                <?php } else { ?>
                    <?php if ($coverage) {
                        $percent = $coverage['clients'] ? intval(floor($coverage['covered'] * 100 / $coverage['clients'])) : 100;
                        ?>
                        <div class="d-flex align-items-baseline gap-2 mb-1">
                            <span class="fw-semibold min-w-0 text-truncate" title="<?= escapeHtml($scope_text) ?>"><?= escapeHtml($scope_text) ?></span>
                            <span class="ms-auto small text-nowrap"><strong><?= $coverage['covered'] ?></strong> of <?= $coverage['clients'] ?> have one</span>
                        </div>
                        <div class="progress mb-2" role="progressbar" aria-label="Clients with one" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100" style="height: 6px;">
                            <div class="progress-bar <?= $coverage['missing'] ? 'bg-warning' : 'bg-success' ?>" style="width: <?= $percent ?>%"></div>
                        </div>
                        <?php if ($coverage['missing']) { ?>
                            <div class="small text-secondary mb-1">Missing one</div>
                            <div class="list-group list-group-flush small mb-2">
                                <?php foreach (array_slice($coverage['missing'], 0, $list_limit, true) as $client_id => $client_name) { ?>
                                    <div class="list-group-item px-0 d-flex align-items-center gap-2" data-missing-client-id="<?= $client_id ?>">
                                        <i class="fas fa-fw fa-user text-secondary"></i>
                                        <a class="min-w-0 flex-grow-1 text-truncate text-reset" href="/agent/files.php?client_id=<?= $client_id ?>"><?= escapeHtml($client_name) ?></a>
                                        <button type="button" class="btn btn-sm btn-light py-0 ajax-modal" data-modal-size="lg" data-modal-url="<?= $use_url ?>&client_id=<?= $client_id ?>"><i class="fas fa-plus me-1"></i>Make</button>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if (count($coverage['missing']) > $list_limit) { ?>
                                <div class="small text-secondary mb-2">and <?= count($coverage['missing']) - $list_limit ?> more</div>
                            <?php } ?>
                            <button type="button" class="btn btn-sm btn-outline-primary ajax-modal" data-modal-size="lg" data-modal-url="<?= $several_url ?>&missing=1"><i class="fas fa-copy me-1"></i>Make for <?= $missing_text ?></button>
                        <?php } elseif ($coverage['clients']) { ?>
                            <div class="small text-success"><i class="fas fa-fw fa-check me-1"></i>Every one of them has one.</div>
                        <?php } else { ?>
                            <div class="small text-secondary"><?= $required === DOCUMENT_TEMPLATE_REQUIRED_TAGGED ? ($required_tags ? 'No clients have ' . (count($required_tags) === 1 ? 'that tag' : 'those tags') : 'No tags picked, so no clients') : 'No clients yet' ?>.</div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="small text-secondary">No one in particular.</div>
                    <?php } ?>

                    <div class="border-top mt-3 pt-3 small">
                        <?php if (!$review_months) { ?>
                            <span class="text-secondary"><i class="fas fa-fw fa-calendar-check me-1"></i>The documents made from it aren't reviewed.</span>
                        <?php } else { ?>
                            <div class="d-flex align-items-baseline gap-2 mb-1">
                                <span><i class="fas fa-fw fa-calendar-check me-1 text-secondary"></i>Reviewed <?= lcfirst(DOCUMENT_TEMPLATE_REVIEW_MONTHS[$review_months]) ?></span>
                                <span class="ms-auto text-nowrap <?= $due ? 'text-warning-emphasis fw-semibold' : 'text-success' ?>"><?= $due ? count($due) . ' due' : '<i class="fas fa-check me-1"></i>None due' ?></span>
                            </div>
                            <?php if ($due) { ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach (array_slice($due, 0, $list_limit, true) as $due_id => $due_row) { ?>
                                        <a class="list-group-item list-group-item-action px-0 d-flex align-items-center gap-2" href="/agent/document.php?client_id=<?= intval($due_row['document_client_id']) ?>&document_id=<?= $due_id ?>" data-due-document-id="<?= $due_id ?>">
                                            <i class="fas fa-fw fa-file-alt text-secondary"></i>
                                            <span class="min-w-0 flex-grow-1">
                                                <span class="d-block text-truncate"><?= escapeHtml($due_row['client_name']) ?></span>
                                                <span class="d-block text-truncate text-secondary"><?= escapeHtml($due_row['document_name']) ?></span>
                                            </span>
                                            <span class="text-secondary text-nowrap" title="Last edited or reviewed <?= escapeHtml($due_row['reviewed_at']) ?>"><?= timeAgo($due_row['reviewed_at']) ?></span>
                                        </a>
                                    <?php } ?>
                                </div>
                                <?php if (count($due) > $list_limit) { ?>
                                    <div class="text-secondary mt-1">and <?= count($due) - $list_limit ?> more</div>
                                <?php } ?>
                            <?php } ?>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-chart-line me-2"></i>Used</h3></div>
            <div class="card-body">
                <?php if ($usage['count']) { ?>
                    <div class="mb-2"><strong><?= $usage['count'] ?></strong> document<?= $usage['count'] === 1 ? '' : 's' ?> made from it<?= $usage['clients'] > 1 ? ', for <strong>' . $usage['clients'] . '</strong> clients' : '' ?> <span class="small text-secondary">- last <?= timeAgo($usage['last']) ?></span></div>
                    <div class="list-group list-group-flush small">
                        <?php foreach ($recent as $row) { ?>
                            <a class="list-group-item list-group-item-action px-0 d-flex align-items-center gap-2" href="/agent/document.php?client_id=<?= intval($row['document_client_id']) ?>&document_id=<?= intval($row['document_id']) ?>">
                                <i class="fas fa-fw fa-file-alt text-secondary"></i>
                                <span class="min-w-0 flex-grow-1">
                                    <span class="d-block text-truncate"><?= escapeHtml($row['client_name'] ?? 'A client that is gone') ?></span>
                                    <span class="d-block text-truncate text-secondary"><?= escapeHtml($row['document_name']) ?></span>
                                </span>
                                <span class="text-secondary text-nowrap" title="<?= escapeHtml($row['document_created_at'] . ($row['user_name'] !== null ? ' by ' . $row['user_name'] : '')) ?>"><?= timeAgo($row['document_created_at']) ?></span>
                            </a>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="small text-secondary">No documents made from it yet - counting started with this version of ITFlow. Agents start one from <strong>Document from template</strong> in a client's Files.</div>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-fw fa-pen-square me-2"></i>Fill-ins</h3>
                <span class="ms-auto small text-secondary me-2"><?= $fill_ins ? count($fill_ins) . ' asked for' : '' ?></span>
                <button type="button" class="btn btn-sm btn-link ajax-modal" data-modal-url="<?= $fill_in_url ?>"><i class="fas fa-plus me-1"></i>Add</button>
            </div>
            <div class="card-body">
                <?php if ($fill_ins) { ?>
                    <p class="small text-secondary">Asked for when a document is made from it - each answer goes in wherever its <span class="font-monospace">[Name]</span> is, and one left blank stays for whoever writes it. Drag to change the order they are asked in.</p>
                    <div class="list-group" id="documentTemplateFillIns">
                        <?php foreach ($fill_ins as $fill_in) {
                            $kind = TICKET_FILL_IN_TYPES[$fill_in['type']];
                            $about = [];
                            if ($fill_in['type'] === 'list') {
                                $about[] = escapeHtml(implode(' · ', $fill_in['options']));
                            }
                            if ($fill_in['help'] !== '') {
                                $about[] = escapeHtml($fill_in['help']);
                            }
                            ?>
                            <div class="list-group-item d-flex align-items-center gap-2" data-fill-in-name="<?= escapeHtml($fill_in['name']) ?>">
                                <span class="drag-handle text-secondary" title="Drag to reorder" style="cursor: grab;"><i class="fas fa-fw fa-grip-vertical"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        <span class="fw-semibold me-1"><?= escapeHtml($fill_in['name']) ?></span>
                                        <span class="badge rounded-pill text-bg-light border fw-normal"><i class="fas fa-fw <?= $kind['icon'] ?> me-1 text-secondary"></i><?= $kind['label'] ?></span>
                                        <?php if ($fill_in['required']) { ?><span class="badge rounded-pill text-bg-light border fw-normal"><i class="fas fa-fw fa-asterisk me-1 text-danger"></i>Required</span><?php } ?>
                                        <?php if (!$fill_in['in_text']) { ?><span class="badge rounded-pill text-bg-warning fw-normal" title="Not in what it says - asked for, but put in nowhere">Not in the text</span><?php } ?>
                                    </div>
                                    <?php if ($about) { ?><div class="small text-secondary text-truncate"><?= implode(' - ', $about) ?></div><?php } ?>
                                </div>
                                <div class="dropdown dropstart">
                                    <button class="btn btn-light text-secondary btn-sm" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $fill_in_url ?>&name=<?= urlencode($fill_in['name']) ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</a>
                                        <?php if ($fill_in['id']) { ?>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($fill_in['in_text']) { ?>
                                                <a class="dropdown-item confirm-link" href="post.php?delete_document_template_fill_in=<?= $fill_in['id'] ?>&csrf_token=<?= $csrf ?>"
                                                    data-confirm-title="Reset <?= escapeHtml($fill_in['name']) ?>?" data-confirm-text="It is still in what it says, so it stays asked for - as plain, optional text." data-confirm-button="Reset"><i class="fas fa-fw fa-undo me-2"></i>Reset</a>
                                            <?php } else { ?>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?delete_document_template_fill_in=<?= $fill_in['id'] ?>&csrf_token=<?= $csrf ?>"
                                                    data-confirm-title="Remove <?= escapeHtml($fill_in['name']) ?>?" data-confirm-text="It is no longer asked for." data-confirm-button="Remove"><i class="fas fa-fw fa-trash-alt me-2"></i>Remove</a>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="text-secondary small">Nothing to ask. Put a word in square brackets in what it says - <span class="font-monospace">[Firewall Model]</span>, <span class="font-monospace">[Review Date]</span> - and it is asked for when a document is made from it, then filled in. Add one here to make it a date, a choice from a list or yes or no, or to make it required.</div>
                <?php } ?>
            </div>
        </div>

        <?php if ($used['placeholder'] || $used['table']) { ?>
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-magic me-2"></i>Filled in from the client</h3></div>
                <div class="card-body small">
                    <?php if ($used['placeholder']) { ?>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <?php foreach ($used['placeholder'] as $key => $word) { ?>
                                <span class="badge rounded-pill text-bg-light border font-monospace fw-normal" title="<?= escapeHtml(ucfirst(array_change_key_case(DOCUMENT_TEMPLATE_PLACEHOLDERS)[$key] ?? '')) ?>">[<?= escapeHtml($word) ?>]</span>
                            <?php } ?>
                        </div>
                        <?php if (array_intersect(array_keys($used['placeholder']), DOCUMENT_TEMPLATE_SITE_PLACEHOLDERS) || array_intersect(array_keys($used['placeholder']), DOCUMENT_TEMPLATE_CONTACT_PLACEHOLDERS)) { ?>
                            <div class="text-secondary mb-2">The <?= implode(' and ', array_filter([array_intersect(array_keys($used['placeholder']), DOCUMENT_TEMPLATE_SITE_PLACEHOLDERS) ? 'site' : '', array_intersect(array_keys($used['placeholder']), DOCUMENT_TEMPLATE_CONTACT_PLACEHOLDERS) ? 'contact' : ''])) ?> - the client's primary, unless another is picked when the document is made.</div>
                        <?php } ?>
                    <?php } ?>
                    <?php if ($used['table']) { ?>
                        <div class="text-secondary mb-1">The client's records, as they are then</div>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($used['table'] as $key => $word) { ?>
                                <li class="mb-1"><span class="badge rounded-pill text-info-emphasis bg-info-subtle border border-info-subtle font-monospace fw-normal">[<?= escapeHtml($word) ?>]</span> <span class="text-secondary"><?= escapeHtml(array_change_key_case(DOCUMENT_TEMPLATE_TABLES)[$key] ?? '') ?></span></li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-info-circle me-2"></i>Details</h3></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 fw-normal text-secondary">Made</dt>
                    <dd class="col-7 mb-2" title="<?= escapeHtml($template['document_template_created_at']) ?>"><?= date('M j, Y', strtotime($template['document_template_created_at'])) ?><?= $template['created_by_name'] !== null ? '<div class="text-secondary">' . escapeHtml($template['created_by_name']) . '</div>' : '' ?></dd>
                    <?php if ($template['document_template_updated_at']) { ?>
                        <dt class="col-5 fw-normal text-secondary">Last changed</dt>
                        <dd class="col-7 mb-2" title="<?= escapeHtml($template['document_template_updated_at']) ?>"><?= timeAgo($template['document_template_updated_at']) ?><?= $template['updated_by_name'] !== null ? '<div class="text-secondary">' . escapeHtml($template['updated_by_name']) . '</div>' : '' ?></dd>
                    <?php } ?>
                    <dt class="col-5 fw-normal text-secondary">Images</dt>
                    <dd class="col-7 mb-0"><?= $images ? $images . ' - copied into each document' : 'None' ?></dd>
                </dl>
            </div>
        </div>

    </div>
</div>

<script src="../js/pretty_content.js"></script>
<script src="../libs/SortableJS/Sortable.min.js"></script>
<script>
    (function () {
        var list = document.getElementById('documentTemplateFillIns');
        if (!list) {
            return;
        }
        new Sortable(list, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () {
                itflowPost('post.php', {
                    reorder_document_template_fill_ins: 1,
                    csrf_token: <?= json_encode($csrf) ?>,
                    document_template_id: <?= $document_template_id ?>,
                    names: Array.from(list.querySelectorAll('[data-fill-in-name]')).map(function (row) {
                        return row.dataset.fillInName;
                    })
                });
            }
        });
    })();
</script>

<?php
require_once "../includes/footer.php";
