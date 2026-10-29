<?php

/*
 * Move the ticked files and documents to one folder. The ids come from the
 * ticked rows (js/bulk_actions.js adds them to this modal's URL).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$current_folder_id = folderOwnedId($_GET['current_folder_id'] ?? 0, $client_id);
$file_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['file_ids'] ?? [])))));
$document_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['document_ids'] ?? [])))));
$total = count($file_ids) + count($document_ids);

if (!$total) {
    echo json_encode(['error' => 'Tick the files or documents to move first.']);
    exit;
}

$summary = implode(' and ', array_filter([
    $document_ids ? count($document_ids) . ' document' . (count($document_ids) === 1 ? '' : 's') : '',
    $file_ids ? count($file_ids) . ' file' . (count($file_ids) === 1 ? '' : 's') : '',
]));

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-folder-open me-2"></i>Move <?= $summary ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($file_ids as $id) { ?>
        <input type="hidden" name="file_ids[]" value="<?= $id ?>">
    <?php } ?>
    <?php foreach ($document_ids as $id) { ?>
        <input type="hidden" name="document_ids[]" value="<?= $id ?>">
    <?php } ?>

    <div class="modal-body">
        <label class="form-label">To folder</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-folder"></i></span>
            <select class="form-select select2" name="bulk_folder_id">
                <?= folderOptionsHtml($client_id, $current_folder_id) ?>
            </select>
        </div>
    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_move_files" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Move</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
