<?php

/*
 * Copy a document - in the same client's files, or into another client's (copy_document,
 * functions/files.php documentCopy()). The folder list is the client's own; picking another
 * client puts the copy at their top level.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$document_id = intval($_GET['id'] ?? 0);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_client_id, document_folder_id, document_name FROM documents WHERE document_id = $document_id LIMIT 1"));
if (!$row) {
    echo json_encode(['error' => 'That document no longer exists.']);
    exit;
}
$client_id = intval($row['document_client_id']);
enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="far fa-fw fa-copy me-2"></i>Copy <strong><?= escapeHtml($row['document_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-document-copy="<?= $client_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-file-alt"></i></span>
                <input type="text" class="form-control" name="name" maxlength="200" value="<?= escapeHtml($row['document_name']) ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">For</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                <select class="form-select select2" name="client_id">
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                        WHERE (client_archived_at IS NULL OR client_id = $client_id) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                    while ($client = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($client['client_id']) ?>" <?= intval($client['client_id']) === $client_id ? 'selected' : '' ?><?= selectOptionClient($client['client_id'], $client['client_name'], $client['client_abbreviation']) ?>><?= escapeHtml($client['client_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-2" data-document-copy-folder>
            <label class="form-label">Folder</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-folder"></i></span>
                <select class="form-select select2" name="folder_id">
                    <?= folderOptionsHtml($client_id, intval($row['document_folder_id'])) ?>
                </select>
            </div>
        </div>
        <div class="small text-body-secondary mb-2" data-document-copy-elsewhere hidden>
            <i class="fas fa-folder me-1"></i>It goes in the top level of their files - move it from there.
        </div>

        <div class="small text-body-secondary">
            <i class="fas fa-info-circle me-1"></i>The copy has the content, summary, tags and pictures. It isn't in the client portal or linked to anything, and starts with no history.
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="copy_document" class="btn btn-primary fw-bold"><i class="far fa-copy me-2"></i>Copy</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    (function () {
        var form = document.querySelector('form[data-document-copy]:not([data-wired])');
        if (!form) {
            return;
        }
        form.dataset.wired = '1';
        // Another client's folders aren't these - the copy goes in their top level
        var client = form.querySelector('select[name="client_id"]');
        var folder = form.querySelector('[data-document-copy-folder]');
        var elsewhere = form.querySelector('[data-document-copy-elsewhere]');
        function follow() {
            var same = client.value === form.dataset.documentCopy;
            folder.hidden = !same;
            elsewhere.hidden = same;
            folder.querySelector('select').disabled = !same;
        }
        client.addEventListener('change', follow);
        follow();
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
