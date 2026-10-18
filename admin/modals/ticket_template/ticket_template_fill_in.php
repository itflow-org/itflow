<?php

/*
 * One of a ticket template's fill-ins - what is asked for when a ticket is
 * raised from it: its name (as it goes in square brackets), what kind of
 * answer it takes, its choices for a list, whether it must be answered,
 * whether the client is asked it (on the portal's request form, and sees the
 * answer on their ticket) and a line of help. ?ticket_template_id= and, to change one, &name= - one in
 * the text that isn't set up yet included. Saved by
 * save_ticket_template_fill_in (admin/post/ticket_template.php).
 */

require_once '../../includes/modal_header.php';

$template = ticketTemplateRow($_GET['ticket_template_id'] ?? 0);
$name = trim((string) ($_GET['name'] ?? ''));
$fill_in = null;
if ($template && $name !== '') {
    foreach (ticketTemplateFillInsFor($template['ticket_template_id']) as $one) {
        if (strtolower($one['name']) === strtolower($name)) {
            $fill_in = $one;
        }
    }
}

ob_start();

if (!$template || ($name !== '' && !$fill_in)) { ?>
    <div class="modal-body text-center text-secondary py-5">That fill-in is gone - it may have been taken out of the template.</div>
<?php } else {
    $type = $fill_in['type'] ?? 'text';
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-pen-square me-2"></i><?= $fill_in ? 'Fill-in' : 'New fill-in' ?> <small class="ms-1 opacity-75"><?= escapeHtml($template['ticket_template_name']) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_template_id" value="<?= intval($template['ticket_template_id']) ?>">
    <input type="hidden" name="original_name" value="<?= escapeHtml($fill_in['name'] ?? '') ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-pen-square"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Employee Name" maxlength="41" pattern="[A-Za-z][A-Za-z0-9 _\-\/]{0,40}" value="<?= escapeHtml($fill_in['name'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-text">
                It goes in the subject, details or a task as <span class="font-monospace">[<?= escapeHtml($fill_in['name'] ?? 'Employee Name') ?>]</span>, and the answer takes its place.
                <?php if ($fill_in) { ?>Renaming it renames it there too.<?php } ?>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Answer</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-sliders-h"></i></span>
                <select class="form-select select2" name="type" id="fillInType">
                    <?php foreach (TICKET_FILL_IN_TYPES as $key => $kind) { ?>
                        <option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?> data-icon="fas <?= $kind['icon'] ?>"><?= $kind['label'] ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="mb-3" id="fillInChoices" <?= $type === 'list' ? '' : 'hidden' ?>>
            <label class="form-label">Choices <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-list-ul"></i></span>
                <textarea class="form-control" name="options" rows="4" placeholder="Business Basic&#10;Business Standard&#10;Business Premium"><?= escapeHtml(implode("\n", $fill_in['options'] ?? [])) ?></textarea>
            </div>
            <div class="form-text">One per line. A task can be for one of them only - set that on the task.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Help</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                <input type="text" class="form-control" name="help" maxlength="255" placeholder="Shown under it - as on their offer letter" value="<?= escapeHtml($fill_in['help'] ?? '') ?>">
            </div>
        </div>

        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" name="required" value="1" id="fillInRequired" <?= !empty($fill_in['required']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="fillInRequired">Must be answered to raise a ticket from it</label>
            <div class="form-text">Not for tickets a project template raises - those are answered on the ticket.</div>
        </div>

        <div class="form-check form-switch">
            <input type="hidden" name="client" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="client" value="1" id="fillInClient" <?= ($fill_in['client'] ?? true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="fillInClient">Ask the client</label>
            <div class="form-text">On the client portal's request form, and they see the answer on their ticket. Off, it is for agents to answer.</div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="save_ticket_template_fill_in" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<script>
    (function () {
        // The choices are for a list only
        var type = document.getElementById('fillInType');
        var choices = document.getElementById('fillInChoices');
        if (type && choices) {
            type.addEventListener('change', function () {
                choices.hidden = type.value !== 'list';
            });
        }
    })();
</script>

<?php }

require_once '../../../includes/modal_footer.php';
