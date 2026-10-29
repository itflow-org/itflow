<?php

/*
 * Save your reply as a canned response - from the picker above a ticket's
 * reply (agent/includes/inc_canned_response_picker.php: ?ticket_id, ?editor -
 * the reply box's textarea id - and ?picker). The reply being written comes
 * into the window with this ticket's details swapped for placeholders, to
 * change before saving, offered on the ticket's category. It is saved as one
 * of yours - or, by an admin, shared with everyone - without leaving the
 * ticket: the reply stays as it is (agent/js/canned_response_save.js,
 * agent/ajax.php canned_response_unfill, agent/user/post/canned_response.php).
 * The fields are includes/canned_response_form.php.
 */

require_once '../../../includes/modal_header.php';

$ticket = ticketForModal($_GET['ticket_id'] ?? 0, 2);
$ticket_id = intval($ticket['ticket_id']);
$editor_id = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($_GET['editor'] ?? ''));
$picker_id = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($_GET['picker'] ?? ''));

$response = [];
$response_category_ids = array_filter([intval($ticket['ticket_category'] ?? 0)]);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-comment-dots me-2"></i>Save your reply as a canned response</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/agent/user/post.php" method="post" enctype="multipart/form-data" autocomplete="off" data-canned-save
    data-editor="<?= escapeHtml($editor_id) ?>" data-picker="<?= escapeHtml($picker_id) ?>" data-ticket-id="<?= $ticket_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="add_your_canned_response" value="1">
    <input type="hidden" name="respond_json" value="1">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

    <div class="modal-body">
        <div class="alert alert-danger small py-2" role="alert" data-canned-save-error hidden></div>

        <div class="d-flex flex-wrap align-items-center gap-2 small mb-3 p-2 rounded border bg-body-tertiary">
            <span><i class="fas fa-fw fa-reply text-secondary me-1"></i>From your reply on <strong><?= escapeHtml(ticketReference($ticket)) ?></strong></span>
            <div class="form-check form-switch mb-0 ms-sm-auto">
                <input class="form-check-input" type="checkbox" role="switch" id="cannedSaveUnfill" data-canned-save-unfill checked>
                <label class="form-check-label" for="cannedSaveUnfill">Swap this ticket's details for placeholders</label>
            </div>
            <div class="w-100 text-body-secondary" data-canned-save-swapped><i class="fas fa-spinner fa-spin me-1"></i>Reading your reply...</div>
        </div>

        <?php require __DIR__ . '/../../../includes/canned_response_form.php'; ?>

        <?php if ($session_is_admin) { ?>
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="share" value="1" id="cannedSaveShare">
                <label class="form-check-label" for="cannedSaveShare">Share with everyone <span class="small text-body-secondary">- every agent is offered it; it is kept in Admin > Canned Responses</span></label>
            </div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save Canned Response</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>
<script src="/agent/js/canned_response_save.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
