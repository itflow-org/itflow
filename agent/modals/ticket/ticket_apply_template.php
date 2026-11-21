<?php

require_once '../../../includes/modal_header.php';

/*
 * Apply a template - a ticket template put on a ticket already open: its tasks, which can be
 * changed here first, what it asks for, and its tags and due time where the ticket has none
 * (apply_ticket_template). The ticket's subject, details, category and the rest are left alone.
 */

$row = ticketForModal($_GET['ticket_id'] ?? 0, 2, true);
$ticket_id = intval($row['ticket_id']);

// The template picker fills only the tasks and its questions in here (agent/js/ticket_tasks_modal.js)
$selected_ticket_template_id = 0;
$ticket_template_apply_on_load = false;
$ticket_template_hint = 'Its tasks are added after the ticket\'s own - change them below first. The ticket\'s subject, details, category and priority stay as they are.';
$existing_tasks = [];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-cube me-2"></i>Apply a Template <small class="ms-1 opacity-75"><?= escapeHtml(ticketReference($row)) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">

        <?php require __DIR__ . '/../../includes/inc_ticket_template_select.php'; ?>

        <div class="mb-3">
            <div class="form-check form-switch">
                <input type="hidden" name="template_defaults" value="0">
                <input type="checkbox" class="form-check-input" name="template_defaults" value="1" id="templateDefaults" checked>
                <label class="form-check-label" for="templateDefaults">Its tags and due time, where the ticket has none</label>
            </div>
        </div>

        <?php require __DIR__ . '/../../includes/inc_ticket_tasks_section.php'; ?>

    </div>
    <div class="modal-footer">
        <button type="submit" name="apply_ticket_template" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Apply</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>
<script src="<?= staticFileUrl('/agent/js/ticket_tasks_modal.js') ?>"></script>

<?php
require_once '../../../includes/modal_footer.php';
