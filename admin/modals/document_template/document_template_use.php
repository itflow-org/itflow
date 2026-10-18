<?php

/*
 * New document for a client from a document template - from Admin > Document
 * Templates and the template's page. When the client is picked, what the
 * template asks for - the site and contact its placeholders come from, and its
 * fill-ins - is drawn under it (agent/js/document_template_make.js). It goes in
 * the client's Files, at the top level, and opens (add_document_from_template,
 * agent/post/document.php). &client_id= picks the client - from the
 * template's list of clients missing one.
 */

require_once '../../includes/modal_header.php';

$template = documentTemplateRow($_GET['id'] ?? 0);
$picked_client_id = intval($_GET['client_id'] ?? 0);

ob_start();

if (!$template || $template['document_template_archived_at']) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-file-alt me-2"></i>New document</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That document template is gone or archived.</div>
<?php } else { ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-file-alt me-2"></i>New document from <strong><?= escapeHtml($template['document_template_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/agent/post.php" method="post" autocomplete="off" data-doc-template-make>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_template_id" value="<?= intval($template['document_template_id']) ?>" data-doc-template-select>

    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Client <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <select class="form-select select2" name="client_id" required data-doc-template-client>
                    <option value="">- Pick a client -</option>
                    <?php
                    $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_clients)) { ?>
                        <option value="<?= intval($row['client_id']) ?>" <?= intval($row['client_id']) === $picked_client_id ? 'selected' : '' ?>><?= escapeHtml($row['client_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <!-- What the template asks for, for the client picked - agent/js/document_template_make.js -->
        <div class="mb-3" data-doc-template-asks hidden></div>

        <div class="mb-3">
            <label class="form-label">Name</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                <input type="text" class="form-control" name="name" placeholder="<?= escapeHtml($template['document_template_name']) ?>" maxlength="200">
            </div>
        </div>

        <div class="mb-2">
            <label class="form-label">Summary</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="<?= escapeHtml($template['document_template_description'] ?: 'One line on what it covers') ?>" maxlength="500">
            </div>
        </div>
        <small class="text-secondary">It goes in the client's Files at the top level, and opens to fill in.</small>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_document_from_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/document_template_make.js"></script>

<?php }

require_once '../../../includes/modal_footer.php';
