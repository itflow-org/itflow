<?php

/*
 * One document template - what it says, with the words in square brackets
 * left to fill in marked, who made it and last changed it, and its use: how
 * many documents have been made from it, for how many clients, and the latest
 * of them. A new document for a client can be made from here. The work is in
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
$brackets = documentTemplateBracketsIn($template['document_template_content']);
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

$edit_url = "modals/document_template/document_template_edit.php?id=$document_template_id";
$use_url = "modals/document_template/document_template_use.php?id=$document_template_id";

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
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="<?= $use_url ?>"><i class="fas fa-fw fa-plus me-2"></i>New document for a client</button>
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

        <?php if ($brackets) { ?>
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-fw fa-pen-square me-2"></i>Left to fill in</h3></div>
                <div class="card-body small">
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <?php foreach ($brackets as $word) { ?>
                            <span class="badge rounded-pill text-bg-light border font-monospace fw-normal">[<?= escapeHtml($word) ?>]</span>
                        <?php } ?>
                    </div>
                    <div class="text-secondary">Words in square brackets stay in each document made from it, for whoever writes it to fill in.</div>
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

<?php
require_once "../includes/footer.php";
