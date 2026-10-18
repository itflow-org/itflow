<?php

/*
 * One of a document template's fill-ins - what is asked for when a document
 * is made from it: its name (as it goes in square brackets), what kind of
 * answer it takes, its choices for a list, whether it must be answered and a
 * line of help. ?document_template_id= and, to change one, &name= - one in the
 * text that isn't set up yet included. Saved by save_document_template_fill_in
 * (admin/post/document_template.php).
 */

require_once '../../includes/modal_header.php';

$template = documentTemplateRow($_GET['document_template_id'] ?? 0);
$name = trim((string) ($_GET['name'] ?? ''));
$fill_in = null;
if ($template && $name !== '') {
    foreach (documentTemplateFillInsFor($template['document_template_id']) as $one) {
        if (strtolower($one['name']) === strtolower($name)) {
            $fill_in = $one;
        }
    }
}

ob_start();

if (!$template || ($name !== '' && !$fill_in)) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-pen-square me-2"></i>Fill-in</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That fill-in is gone - it may have been taken out of the template.</div>
<?php } else {
    $type = $fill_in['type'] ?? 'text';
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-pen-square me-2"></i><?= $fill_in ? 'Fill-in' : 'New fill-in' ?> <small class="ms-1 opacity-75"><?= escapeHtml($template['document_template_name']) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_template_id" value="<?= intval($template['document_template_id']) ?>">
    <input type="hidden" name="original_name" value="<?= escapeHtml($fill_in['name'] ?? '') ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-pen-square"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Firewall Model" maxlength="41" pattern="[A-Za-z][A-Za-z0-9 _\-\/]{0,40}" value="<?= escapeHtml($fill_in['name'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-text">
                It goes in what the template says as <span class="font-monospace">[<?= escapeHtml($fill_in['name'] ?? 'Firewall Model') ?>]</span>, and the answer takes its place.
                <?php if ($fill_in) { ?>Renaming it renames it there too.<?php } ?>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Answer</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-sliders-h"></i></span>
                <select class="form-select select2" name="type" id="documentFillInType">
                    <?php foreach (TICKET_FILL_IN_TYPES as $key => $kind) { ?>
                        <option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?> data-icon="fas <?= $kind['icon'] ?>"><?= $kind['label'] ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3" id="documentFillInChoices" <?= $type === 'list' ? '' : 'hidden' ?>>
            <label class="form-label">Choices <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-list-ul"></i></span>
                <textarea class="form-control" name="options" rows="4" placeholder="Critical&#10;High&#10;Normal"><?= escapeHtml(implode("\n", $fill_in['options'] ?? [])) ?></textarea>
            </div>
            <div class="form-text">One per line.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Help</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                <input type="text" class="form-control" name="help" maxlength="255" placeholder="Shown under it - as on the label on the back" value="<?= escapeHtml($fill_in['help'] ?? '') ?>">
            </div>
        </div>

        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="required" value="1" id="documentFillInRequired" <?= !empty($fill_in['required']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="documentFillInRequired">Must be answered to make a document from it</label>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="save_document_template_fill_in" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<script>
    (function () {
        // The choices are for a list only
        var type = document.getElementById('documentFillInType');
        var choices = document.getElementById('documentFillInChoices');
        if (type && choices) {
            type.addEventListener('change', function () {
                choices.hidden = type.value !== 'list';
            });
        }
    })();
</script>

<?php }

require_once '../../../includes/modal_footer.php';
