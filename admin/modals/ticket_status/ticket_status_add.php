<?php

/*
 * New ticket status - its name, what it is for, its colour, whether the SLA
 * clock runs in it and what a client's reply does. It goes at the end of the
 * order. The fields are ticket_status_form.php.
 */

require_once '../../includes/modal_header.php';

$ticket_status = null;

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-info-circle me-2"></i>New Ticket Status</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php require __DIR__ . '/ticket_status_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_ticket_status" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
