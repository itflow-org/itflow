<?php

require_once '../../../includes/modal_header.php';

$recurring_ticket_ids = array_map('intval', $_GET['recurring_ticket_ids'] ?? []);

$count = count($recurring_ticket_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-dollar-sign me-2"></i>Mark <strong><?= $count ?></strong> Tickets Billable</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($recurring_ticket_ids as $recurring_ticket_id) { ?><input type="hidden" name="recurring_ticket_ids[]" value="<?= $recurring_ticket_id ?>"><?php } ?>

    <div class="modal-body">

        <div class="mb-3">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="billable" value="1" id="billable">
                <label class="form-check-label" for="billable">Mark Billable</label>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_recurring_ticket_billable" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Set</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
