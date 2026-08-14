<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);

enforceClientAccess();
$contact_id = intval($_GET['contact_id'] ?? 0);
$asset_id = intval($_GET['asset_id'] ?? 0);
intval($_GET['folder_id'] ?? 0);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-alt me-2"></i>New Document from Template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <div class="modal-body">

        <label>Template</label>
        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-puzzle-piece"></i></span>
                <select class="form-select" name="document_template_id" required>
                    <option value="">- Select Template -</option>
                    <?php
                    $sql_document_templates = mysqli_query($mysqli, "SELECT document_template_id, document_template_name FROM document_templates WHERE document_template_archived_at IS NULL ORDER BY document_template_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_document_templates)) {
                        $document_template_id = intval($row['document_template_id']);
                        $document_template_name = escapeHtml($row['document_template_name']);
                    ?>
                        <option value="<?= $document_template_id ?>"><?= $document_template_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

        <label>Document name</label>
        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-file"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Name" maxlength="200" required>
            </div>
        </div>

        <label>Description</label>
        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Short summary of the document">
            </div>
        </div>

        <label>Folder</label>
        <div class="mb-3">
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                <select class="form-select" name="folder">
                    <option value="0">/</option>
                    <?php
                    $sql_folders = mysqli_query($mysqli, "SELECT folder_id, folder_name FROM folders WHERE folder_client_id = $client_id ORDER BY folder_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_folders)) {
                        $folder_id = intval($row['folder_id']);
                        $folder_name = escapeHtml($row['folder_name']);
                    ?>
                        <option <?php if (isset($_GET['folder_id']) && $_GET['folder_id'] == $folder_id) echo "selected"; ?> value="<?= $folder_id ?>"><?= $folder_name ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_document_from_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
