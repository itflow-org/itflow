<?php
/*
 * Client Portal
 * A document - what it says, as written (its tables and colours too), and
 * the files that came with it: a PDF or picture shown on the page, any file
 * to open or download. Printable.
 *
 * SCOPING: primary and technical contacts (portalItGate()); the document must
 * be the session's client's, visible to the client and not archived, and its
 * files go through file.php, which checks the same.
 */

header("Content-Security-Policy: default-src 'self'");
$portal_csp_inline_styles = true; // a document's own tables and colours - see includes/header.php

require_once "includes/inc_all.php";

portalItGate();

$document_id = intval($_GET['id'] ?? 0);
$document = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_id, document_name, document_description, document_content, document_created_at, document_updated_at, folder_name
    FROM documents
    LEFT JOIN folders ON folder_id = document_folder_id AND folder_client_id = $session_client_id
    WHERE document_id = $document_id AND document_client_id = $session_client_id AND document_client_visible = 1 AND document_archived_at IS NULL
    LIMIT 1"));

if (!$document) { ?>
    <a class="itflow-portal-back" href="documents.php"><i class="fas fa-chevron-left"></i>Documents</a>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => "That document isn't here", 'text' => 'It may have been moved or is no longer shared with you.', 'icon' => 'fa-folder-open', 'filtered' => false,
            'actions' => [['label' => 'All documents', 'href' => 'documents.php', 'icon' => 'fa-arrow-left']]]) ?>
    </section>
    <?php
    require_once "includes/footer.php";
    exit;
}

$files = [];
$sql = mysqli_query($mysqli, "SELECT files.file_id, file_name, file_ext, file_size FROM files
    JOIN document_files ON document_files.file_id = files.file_id
    WHERE document_files.document_id = $document_id AND file_client_id = $session_client_id AND file_archived_at IS NULL
    ORDER BY file_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $files[] = $row;
}

// An uploaded file's document only says "Uploaded file: name" - the file itself is the content
$content = documentHtmlPurify($document['document_content']);
$stub = $files && strpos(trim(strip_tags((string) $document['document_content'])), 'Uploaded file:') === 0;
$updated = $document['document_updated_at'] ?: $document['document_created_at'];
$preview = null;
foreach ($files as $file) {
    $ext = strtolower((string) $file['file_ext']);
    if ($ext === 'pdf' || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $preview = $file + ['kind' => $ext === 'pdf' ? 'pdf' : 'image'];
        break;
    }
}
?>

<a class="itflow-portal-back d-print-none" href="documents.php"><i class="fas fa-chevron-left"></i>Documents</a>

<div class="itflow-portal-page-head">
    <div class="min-w-0">
        <h1><?= escapeHtml($document['document_name']) ?></h1>
        <p>
            <?= trim((string) $document['document_description']) !== '' ? escapeHtml($document['document_description']) . ' · ' : '' ?>
            <?php if (!empty($document['folder_name'])) { ?><i class="fas fa-folder me-1"></i><?= escapeHtml($document['folder_name']) ?> · <?php } ?>
            Updated <?= escapeHtml(date('M j, Y', strtotime($updated))) ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
        <button type="button" class="btn btn-outline-secondary" data-portal-print><i class="fas fa-print me-2"></i>Print</button>
        <a class="btn btn-outline-secondary" href="<?= escapeHtml(portalAskUrl('Question about ' . $document['document_name'])) ?>"><i class="fas fa-question-circle me-2"></i>Ask us about it</a>
    </div>
</div>

<?php if ($files) { ?>
    <section class="itflow-portal-panel mb-4 d-print-none">
        <ul class="itflow-portal-list">
            <?php foreach ($files as $file) { ?>
                <li class="itflow-portal-ticket">
                    <?= portalTile('fa-' . getFileIcon($file['file_ext'])) ?>
                    <span class="itflow-portal-ticket-main">
                        <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($file['file_name']) ?></span>
                        <span class="itflow-portal-ticket-meta"><span><?= escapeHtml(strtoupper((string) $file['file_ext'])) ?> file</span><span><?= escapeHtml(formatBytes(intval($file['file_size']))) ?></span></span>
                    </span>
                    <span class="d-flex gap-2">
                        <a class="btn btn-sm btn-outline-secondary" href="file.php?file_id=<?= intval($file['file_id']) ?>&amp;action=view" target="_blank" rel="noopener"><i class="fas fa-external-link-alt me-1"></i>Open</a>
                        <a class="btn btn-sm btn-outline-primary" href="file.php?file_id=<?= intval($file['file_id']) ?>"><i class="fas fa-download me-1"></i>Download</a>
                    </span>
                </li>
            <?php } ?>
        </ul>
    </section>
<?php } ?>

<?php if ($preview && $preview['kind'] === 'pdf') { ?>
    <section class="itflow-portal-panel mb-4 d-print-none">
        <embed class="itflow-portal-pdf" src="file.php?file_id=<?= intval($preview['file_id']) ?>&amp;action=view" type="application/pdf">
    </section>
<?php } elseif ($preview) { ?>
    <section class="itflow-portal-panel itflow-portal-panel-body mb-4 text-center">
        <img class="img-fluid itflow-portal-preview-image" src="file.php?file_id=<?= intval($preview['file_id']) ?>&amp;action=view" alt="<?= escapeHtml($preview['file_name']) ?>">
    </section>
<?php } ?>

<?php if (!$stub && trim(strip_tags($content, '<img><table>')) !== '') { ?>
    <article class="itflow-portal-panel itflow-portal-panel-body itflow-portal-document prettyContent">
        <?= $content ?>
    </article>
<?php } elseif (!$files) { ?>
    <section class="itflow-portal-panel">
        <?= emptyStateHtml(['title' => 'This document is empty', 'text' => '', 'icon' => 'fa-file-alt', 'filtered' => false]) ?>
    </section>
<?php } ?>

<?php
require_once "includes/footer.php";
