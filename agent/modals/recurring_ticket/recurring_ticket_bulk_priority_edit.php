<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$recurring_ticket_ids = array_map('intval', $_GET['recurring_ticket_ids'] ?? []);

$count = count($recurring_ticket_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-thermometer-half me-2"></i>Set Priority for <strong><?= $count ?></strong> Tickets</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($recurring_ticket_ids as $recurring_ticket_id) { ?><input type="hidden" name="recurring_ticket_ids[]" value="<?= $recurring_ticket_id ?>"><?php } ?>

    <div class="modal-body">

        <div class="mb-3">
            <label>Priority</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                <select class="form-select select2" name="bulk_priority">
                    <option data-color="var(--bs-info)">Low</option>
                    <option data-color="var(--bs-warning)">Medium</option>
                    <option data-color="var(--bs-danger)">High</option>
                    <option data-color="var(--bs-emphasis-color)">Urgent</option>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_recurring_ticket_priority" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Set Priority</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
