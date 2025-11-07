<?php

require_once '../../../includes/modal_header.php';

$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);

$count = count($selected_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-calendar-day mr-2"></i>Set Next Run Date for <strong><?= $count ?></strong> Tickets</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?><input type="hidden" name="recurring_ticket_ids[]" value="<?= $id ?>"><?php } ?>
                    
    <div class="modal-body">

        <div class="form-group">
            <label>Next Run Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                </div>
                <input class="form-control" type="date" name="next_run_date" min="<?= date("Y-m-d"); ?>" max="2999-12-31">
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_recurring_ticket_next_run_date" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Set Run Date</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
