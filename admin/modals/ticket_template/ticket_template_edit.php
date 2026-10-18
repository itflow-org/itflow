<?php

/*
 * Edit a ticket template - the fields are ticket_template_form.php. Its tasks
 * are changed on its page (admin/ticket_template.php).
 */

require_once '../../includes/modal_header.php';

$template = ticketTemplateRow($_GET['id'] ?? 0);

ob_start();

if (!$template) { ?>
    <div class="modal-body text-center text-secondary py-5">That ticket template is gone - it may have been deleted.</div>
<?php } else {
    $ticket_template_id = intval($template['ticket_template_id']);
    $template_tag_ids = ticketTemplateTagIds($ticket_template_id); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-life-ring me-2"></i>Ticket template: <strong><?= escapeHtml($template['ticket_template_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_template_id" value="<?= $ticket_template_id ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/ticket_template_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_ticket_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }

require_once '../../../includes/modal_footer.php';
