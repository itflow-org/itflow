<?php
/*
 * Client Portal
 * Documents - the guides and records shared with the client, in tabs by
 * folder, searchable by name, description and what they say, 25 a page.
 * New document and Upload a file go to document_add.php.
 *
 * SCOPING: primary and technical contacts (portalItGate()); only documents
 * marked visible to the client, keyed to $session_client_id.
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

portalItGate();

$per_page = 25;
$q = trim((string) ($_GET['q'] ?? ''));
$visible = "document_client_id = $session_client_id AND document_client_visible = 1 AND document_archived_at IS NULL";

// The folders that hold something shared - and the documents in none
$folders = [];
$sql = mysqli_query($mysqli, "SELECT document_folder_id, COALESCE(folder_name, '') AS folder_name, COUNT(*) AS n FROM documents
    LEFT JOIN folders ON folder_id = document_folder_id AND folder_client_id = $session_client_id
    WHERE $visible GROUP BY document_folder_id, folder_name ORDER BY folder_name = '', folder_name ASC");
$all_count = 0;
while ($row = mysqli_fetch_assoc($sql)) {
    $folders[intval($row['document_folder_id'])] = ['name' => $row['folder_name'] !== '' ? $row['folder_name'] : 'Not in a folder', 'count' => intval($row['n'])];
    $all_count += intval($row['n']);
}
$folder = isset($_GET['folder']) && isset($folders[intval($_GET['folder'])]) ? intval($_GET['folder']) : null;

$where = $visible . ($folder !== null ? " AND document_folder_id = $folder" : '');
if ($q !== '') {
    $q_sql = escapeSql($q);
    $where .= " AND (document_name LIKE '%$q_sql%' OR document_description LIKE '%$q_sql%' OR document_content_raw LIKE '%$q_sql%')";
}
$total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM documents WHERE $where"))[0]);
$pages = max(1, (int) ceil($total / $per_page));
$page = min(max(1, intval($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per_page;

$documents = mysqli_query($mysqli, "SELECT document_id, document_name, document_description, document_created_at, document_updated_at, folder_name,
        (SELECT file_ext FROM document_files JOIN files ON files.file_id = document_files.file_id WHERE document_files.document_id = documents.document_id ORDER BY files.file_id LIMIT 1) AS file_ext
    FROM documents
    LEFT JOIN folders ON folder_id = document_folder_id AND folder_client_id = $session_client_id
    WHERE $where
    ORDER BY COALESCE(document_updated_at, document_created_at) DESC, document_name ASC
    LIMIT $per_page OFFSET $offset");

/** This page's address with some of its settings changed. */
function documentsUrl(array $changes) {
    global $folder, $q;
    $params = array_merge(['folder' => $folder, 'q' => $q !== '' ? $q : null], $changes);
    $params = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'documents.php' . ($params ? '?' . http_build_query($params) : '');
}
?>

<div class="itflow-portal-page-head">
    <div>
        <h1>Documents</h1>
        <p>Guides and records we share with you</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="get" class="d-flex" role="search">
            <?php if ($folder !== null) { ?><input type="hidden" name="folder" value="<?= $folder ?>"><?php } ?>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                <input type="search" class="form-control" name="q" value="<?= escapeHtml($q) ?>" placeholder="Search documents" aria-label="Search documents" maxlength="200">
            </div>
        </form>
        <div class="btn-group">
            <a class="btn btn-primary" href="document_add.php"><i class="fas fa-plus me-2"></i>New document</a>
            <a class="btn btn-outline-primary" href="document_add.php?upload=1"><i class="fas fa-upload me-2"></i>Upload a file</a>
        </div>
    </div>
</div>

<?php if (count($folders) > 1) { ?>
    <nav class="itflow-portal-tabs" aria-label="Folders">
        <a class="<?= $folder === null ? 'active' : '' ?>" href="<?= escapeHtml(documentsUrl(['folder' => null, 'page' => null])) ?>"><i class="fas fa-list"></i>All <span class="badge rounded-pill"><?= $all_count ?></span></a>
        <?php foreach ($folders as $id => $f) { ?>
            <a class="<?= $folder === $id ? 'active' : '' ?>" href="<?= escapeHtml(documentsUrl(['folder' => $id, 'page' => null])) ?>"><i class="fas <?= $id ? 'fa-folder' : 'fa-file' ?>"></i><?= escapeHtml($f['name']) ?> <span class="badge rounded-pill"><?= $f['count'] ?></span></a>
        <?php } ?>
    </nav>
<?php } ?>

<section class="itflow-portal-panel">
    <?php if (!$total) { ?>
        <?php if ($q !== '') { ?>
            <?= emptyStateHtml(['thing' => 'documents', 'filtered' => true, 'match_text' => 'No document mentions &ldquo;' . escapeHtml($q) . '&rdquo;.', 'clear_url' => documentsUrl(['q' => null, 'page' => null])]) ?>
        <?php } else { ?>
            <?= emptyStateHtml(['title' => 'No documents yet', 'text' => 'Guides and records we share with you show here - and you can add your own.', 'icon' => 'fa-folder-open', 'filtered' => false,
                'actions' => [['label' => 'New document', 'href' => 'document_add.php'], ['label' => 'Upload a file', 'href' => 'document_add.php?upload=1', 'icon' => 'fa-upload']]]) ?>
        <?php } ?>
    <?php } else { ?>
        <ul class="itflow-portal-list">
            <?php while ($row = mysqli_fetch_assoc($documents)) {
                $updated = $row['document_updated_at'] ?: $row['document_created_at']; ?>
                <li>
                    <a class="itflow-portal-ticket" href="document.php?id=<?= intval($row['document_id']) ?>">
                        <?= portalTile($row['file_ext'] !== null ? 'fa-' . getFileIcon($row['file_ext']) : 'fa-file-alt') ?>
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block"><?= escapeHtml($row['document_name']) ?></span>
                            <span class="itflow-portal-ticket-meta">
                                <?php if (!empty($row['folder_name']) && $folder === null) { ?><span><i class="fas fa-folder me-1"></i><?= escapeHtml($row['folder_name']) ?></span><?php } ?>
                                <?php if (trim((string) $row['document_description']) !== '') { ?><span class="text-truncate"><?= escapeHtml($row['document_description']) ?></span><?php } ?>
                            </span>
                        </span>
                        <span class="itflow-portal-ticket-side">
                            <small title="<?= escapeHtml($updated) ?>">Updated <?= escapeHtml(date('M j, Y', strtotime($updated))) ?></small>
                        </span>
                    </a>
                </li>
            <?php } ?>
        </ul>
        <?php if ($pages > 1) { ?>
            <div class="itflow-portal-pager">
                <span><?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?></span>
                <div class="btn-group">
                    <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= escapeHtml(documentsUrl(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>" aria-label="Previous page"><i class="fas fa-chevron-left"></i></a>
                    <a class="btn btn-sm btn-outline-secondary<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= escapeHtml(documentsUrl(['page' => $page + 1])) ?>" aria-label="Next page"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</section>

<?php
require_once "includes/footer.php";
