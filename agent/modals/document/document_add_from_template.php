<?php

/*
 * New document from one of the admin's document templates - its content and
 * its images, copied, in the folder it was opened from (add_document_from_template,
 * functions/document_template.php). Each template is offered with its summary
 * and how often it has been used; ?document_template_id picks one. What the
 * one picked asks for - the site and contact its placeholders come from, and
 * its fill-ins - is drawn under it (agent/js/document_template_make.js).
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$folder_id = folderOwnedId($_GET['folder_id'] ?? 0, $client_id);

$templates = [];
$sql = mysqli_query($mysqli, "SELECT document_template_id, document_template_name, document_template_description FROM document_templates
    WHERE document_template_archived_at IS NULL ORDER BY document_template_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $templates[intval($row['document_template_id'])] = $row;
}
$usage = documentTemplateUsage(array_keys($templates));
$picked = intval($_GET['document_template_id'] ?? 0);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-puzzle-piece me-2"></i>New document from a template</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-doc-template-make>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>" data-doc-template-client>
    <div class="modal-body">

        <?php if (!$templates) { ?>
            <div class="itflow-asset-empty py-4">
                <i class="fas fa-puzzle-piece fa-2x mb-2"></i>
                <div>No document templates yet.</div>
                <?php if (!empty($session_is_admin)) { ?>
                    <a class="small" href="/admin/document_templates.php">Add one in Admin &rsaquo; Document Templates</a>
                <?php } ?>
            </div>
        <?php } else { ?>

            <div class="mb-3">
                <label class="form-label">Template <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-puzzle-piece"></i></span>
                    <select class="form-select select2" name="document_template_id" required data-doc-template-select>
                        <option value="">- Pick a template -</option>
                        <?php foreach ($templates as $template_id => $template) {
                            $used = $usage[$template_id]['count'] ?? 0;
                            $about = trim(implode(' · ', array_filter([trim((string) $template['document_template_description']), $used ? 'used ' . $used . ' time' . ($used === 1 ? '' : 's') : '']))); ?>
                            <option value="<?= $template_id ?>"<?= $template_id === $picked ? ' selected' : '' ?><?= selectOptionData(['icon' => 'fas fa-file-alt', 'description' => $about]) ?>><?= escapeHtml($template['document_template_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <!-- What the template picked asks for - agent/js/document_template_make.js -->
            <div class="mb-3" data-doc-template-asks hidden></div>

            <div class="mb-3">
                <label class="form-label">Name</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-file-alt"></i></span>
                    <input type="text" class="form-control" name="name" placeholder="The template's name, unless you give it another" maxlength="200">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Summary</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                    <input type="text" class="form-control" name="description" placeholder="The template's summary, unless you give it another" maxlength="500">
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label">Folder</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-folder"></i></span>
                    <select class="form-select select2" name="folder">
                        <?= folderOptionsHtml($client_id, $folder_id) ?>
                    </select>
                </div>
            </div>

        <?php } ?>

    </div>
    <div class="modal-footer">
        <?php if ($templates) { ?>
            <button type="submit" name="add_document_from_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= $templates ? 'Cancel' : 'Close' ?></button>
    </div>
</form>

<script src="/agent/js/document_template_make.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
