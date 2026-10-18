<?php
/*
 * A canned response's fields - shared by canned_response_add.php and
 * canned_response_edit.php, which set $response (cannedResponseRow(), or []
 * for a new one) and $response_category_ids before including this. Posts
 * what cannedResponseFields() reads (functions/canned_response.php).
 *
 * The placeholders under the editor put themselves in where the cursor is
 * (js/canned_response_form.js).
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$r = $response;
$picked = array_map('intval', $response_category_ids);

?>

<div class="mb-3">
    <label class="form-label">Name <strong class="text-danger">*</strong></label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-comment-dots"></i></span>
        <input type="text" class="form-control" name="name" placeholder="What an agent picks it by - Password reset done" maxlength="200" value="<?= escapeHtml($r['canned_response_name'] ?? '') ?>" required autofocus>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Offered on</label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
        <select class="form-select select2" name="category_ids[]" multiple data-placeholder="Every ticket">
            <?php foreach (categoryPicker('Ticket', $picked) as $category_id => $category_row) { ?>
                <?= categoryOptionHtml($category_row, in_array(intval($category_id), $picked, true)) ?>
            <?php } ?>
        </select>
        <?= categoryAddButtonHtml('Ticket', 'btn btn-secondary') ?>
    </div>
    <small class="text-secondary">Tickets in these categories. Leave it empty to offer it on every ticket.</small>
</div>

<div class="mb-2">
    <label class="form-label" for="cannedResponseBody">Response</label>
    <textarea class="form-control tinymceTicket" id="cannedResponseBody" name="body"><?= escapeHtml($r['canned_response_body'] ?? '') ?></textarea>
</div>

<div class="small text-secondary mb-1">Filled in from the ticket as it goes in - click one to put it where the cursor is:</div>
<div class="d-flex flex-wrap gap-1 mb-2" data-canned-placeholders>
    <?php foreach (CANNED_RESPONSE_PLACEHOLDERS as $placeholder => $means) { ?>
        <button type="button" class="btn btn-sm btn-light border font-monospace" data-canned-placeholder="<?= escapeHtml($placeholder) ?>" title="<?= escapeHtml(ucfirst($means)) ?>">[<?= escapeHtml($placeholder) ?>]</button>
    <?php } ?>
</div>
<div class="small text-secondary">Anything else in square brackets - [Date of visit] - stays as it is, for whoever replies to fill in.</div>

<script src="/js/canned_response_form.js"></script>
