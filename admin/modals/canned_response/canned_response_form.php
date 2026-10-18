<?php
/*
 * A canned response's fields - shared by canned_response_add.php and
 * canned_response_edit.php, which set $response (cannedResponseRow(), or []
 * for a new one) and $response_category_ids before including this. Posts
 * what cannedResponseFields() reads (functions/canned_response.php).
 *
 * Two tabs: Response (its name, where it is offered and what it says, with
 * the placeholders under the editor putting themselves in where the cursor
 * is - js/canned_response_form.js) and Sets up (what picking it sets up on
 * the reply: who sees it, the status after it and time worked added).
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$r = $response;
$picked = array_map('intval', $response_category_ids);
$reply_type = isset($r['canned_response_reply_type']) && $r['canned_response_reply_type'] !== null ? (string) intval($r['canned_response_reply_type']) : '';
$ticket_status = isset($r['canned_response_ticket_status']) && $r['canned_response_ticket_status'] !== null ? intval($r['canned_response_ticket_status']) : 0;
$minutes = intval($r['canned_response_minutes'] ?? 0);

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#cannedResponseFormResponse"><i class="fas fa-fw fa-comment-dots me-2 d-none d-sm-inline"></i>Response</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#cannedResponseFormSetsUp"><i class="fas fa-fw fa-sliders-h me-2 d-none d-sm-inline"></i>Sets up</a>
    </li>
</ul>

<div class="tab-content">

<div class="tab-pane fade show active" id="cannedResponseFormResponse">
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
    <div class="small text-secondary">Anything else in square brackets - [Date of visit] - stays as it is, and the agent is asked about it before sending.</div>
</div>

<div class="tab-pane fade" id="cannedResponseFormSetsUp">
    <p class="small text-secondary">Set on the reply when the response is picked - the agent can still change them before sending. Each is left as it is unless set here.</p>

    <div class="mb-3">
        <label class="form-label">Reply as</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-reply"></i></span>
            <select class="form-select select2" name="reply_type">
                <option value=""<?= $reply_type === '' ? ' selected' : '' ?>>- Leave as it is -</option>
                <?php foreach (CANNED_RESPONSE_REPLY_TYPES as $type => [$label, $icon]) { ?>
                    <option value="<?= $type ?>"<?= $reply_type === (string) $type ? ' selected' : '' ?><?= selectOptionData(['icon' => "fas $icon"]) ?>><?= $label ?></option>
                <?php } ?>
            </select>
        </div>
        <small class="text-secondary">An email reply on a ticket with nobody to email is a public note.</small>
    </div>

    <div class="mb-3">
        <label class="form-label">Status after it</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
            <select class="form-select select2" name="ticket_status">
                <option value="">- Leave as it is -</option>
                <?php foreach (cannedResponseStatuses() as $status_id => $status) { ?>
                    <option value="<?= $status_id ?>"<?= $ticket_status === $status_id ? ' selected' : '' ?><?= selectOptionColor($status['color']) ?>><?= escapeHtml($status['name']) ?></option>
                <?php } ?>
            </select>
        </div>
        <small class="text-secondary">Resolved waits for a ticket's open tasks to be done.</small>
    </div>

    <div class="mb-3">
        <label class="form-label">Time worked</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
            <input type="number" class="form-control" name="minutes" min="0" max="<?= CANNED_RESPONSE_MAX_MINUTES ?>" step="1" placeholder="None" value="<?= $minutes ?: '' ?>">
            <span class="input-group-text">minutes</span>
        </div>
        <small class="text-secondary">Added to the reply's time worked.</small>
    </div>
</div>

</div>

<script src="/js/canned_response_form.js"></script>
