<?php

/*
 * Who should have a document from a template - no one in particular, every
 * client, or the clients with one of the client tags picked - and how often
 * the documents made from it are reviewed. ?id= the template. Saved by
 * save_document_template_required (admin/post/document_template.php); the
 * template's page then shows the clients missing one and the documents due a
 * review.
 */

require_once '../../includes/modal_header.php';

$template = documentTemplateRow($_GET['id'] ?? 0);

ob_start();

if (!$template) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-clipboard-check me-2"></i>Who should have one</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That document template no longer exists.</div>
<?php } else {
    $document_template_id = intval($template['document_template_id']);
    $required = intval($template['document_template_required']);
    $tag_ids = array_keys(documentTemplateRequiredTags([$document_template_id])[$document_template_id]);
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-clipboard-check me-2"></i>Who should have one <small class="ms-1 opacity-75"><?= escapeHtml($template['document_template_name']) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_template_id" value="<?= $document_template_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Every one of these should have a document from it</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                <select class="form-select select2" name="required" id="documentTemplateRequired">
                    <option value="<?= DOCUMENT_TEMPLATE_REQUIRED_NONE ?>" <?= $required === DOCUMENT_TEMPLATE_REQUIRED_NONE ? 'selected' : '' ?>>No one in particular</option>
                    <option value="<?= DOCUMENT_TEMPLATE_REQUIRED_EVERY ?>" <?= $required === DOCUMENT_TEMPLATE_REQUIRED_EVERY ? 'selected' : '' ?>>Every client</option>
                    <option value="<?= DOCUMENT_TEMPLATE_REQUIRED_TAGGED ?>" <?= $required === DOCUMENT_TEMPLATE_REQUIRED_TAGGED ? 'selected' : '' ?>>Clients with a tag</option>
                </select>
            </div>
            <div class="form-text">Leads and archived clients don't count. The template's page and each client's Files show who is missing one.</div>
        </div>

        <div class="mb-3" id="documentTemplateRequiredTags" <?= $required === DOCUMENT_TEMPLATE_REQUIRED_TAGGED ? '' : 'hidden' ?>>
            <label class="form-label">Client tags</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                <select class="form-select select2" name="tags[]" multiple data-placeholder="- Pick client tags -">
                    <?php foreach (tagOptionsFor('client', $tag_ids) as $tag) { ?>
                        <?= tagOptionHtml($tag, $tag_ids) ?>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">Clients with any of them - Managed, say.</div>
        </div>

        <div class="mb-1">
            <label class="form-label">Review the documents made from it</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                <select class="form-select select2" name="review_months">
                    <?php foreach (DOCUMENT_TEMPLATE_REVIEW_MONTHS as $months => $label) { ?>
                        <option value="<?= $months ?>" <?= intval($template['document_template_review_months']) === $months ? 'selected' : '' ?>><?= $label ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">A document is due once it hasn't been edited or marked as reviewed for that long - shown on it, in its client's Files and here.</div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="save_document_template_required" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<script>
    (function () {
        // The tags are for "Clients with a tag" only
        var required = document.getElementById('documentTemplateRequired');
        var tags = document.getElementById('documentTemplateRequiredTags');
        if (required && tags) {
            required.addEventListener('change', function () {
                tags.hidden = required.value !== '<?= DOCUMENT_TEMPLATE_REQUIRED_TAGGED ?>';
            });
        }
    })();
</script>

<?php }

require_once '../../../includes/modal_footer.php';
