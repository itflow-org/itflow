<?php

/*
 * The body of New Document and Edit Document (document_add.php,
 * document_edit.php): name and summary across the top, where it lives and
 * whether the portal shows it under them, the editor below.
 *
 * Expects $document_form_mode ('add' or 'edit'), $client_id, and the
 * escaped $document_name, $document_description, $document_content,
 * $document_folder_id, $document_client_visible.
 */
?>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label">Name <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-file-alt"></i></span>
            <input type="text" class="form-control" name="name" maxlength="200" value="<?= $document_name ?>" placeholder="e.g. Backup and recovery plan" required <?= $document_form_mode === 'add' ? 'autofocus' : '' ?>>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Summary</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
            <input type="text" class="form-control" name="description" value="<?= $document_description ?>" placeholder="One line on what it covers">
        </div>
    </div>

    <?php if ($document_form_mode === 'add') { ?>
        <div class="col-md-6">
            <label class="form-label">Folder</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-folder"></i></span>
                <select class="form-select select2" name="folder">
                    <?= folderOptionsHtml($client_id, $document_folder_id) ?>
                </select>
            </div>
        </div>
    <?php } ?>

    <?php if (!empty($config_client_portal_enable)) { ?>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
                <input type="hidden" name="client_visible" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="client_visible" value="1" id="documentFormVisible" <?= $document_client_visible ? 'checked' : '' ?>>
                <label class="form-check-label" for="documentFormVisible">
                    <i class="fas fa-fw fa-eye text-body-secondary me-1"></i>Show in the client portal <span class="text-body-secondary small">- to their technical contacts</span>
                </label>
            </div>
        </div>
    <?php } ?>
</div>

<textarea class="form-control tinymce" name="content" aria-label="Content"><?= $document_content ?></textarea>

<?php if ($document_form_mode === 'edit') { ?>
    <div class="form-text"><i class="fas fa-history me-1"></i>Saving keeps what it says now in the document's history, so it can be put back.</div>
<?php } ?>
