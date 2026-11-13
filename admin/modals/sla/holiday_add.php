<?php

/*
 * New closure day - a date the office is shut, and what for. No SLA clock
 * runs on it, the same as a day outside the business days, and adding one
 * works the targets out again on every ticket with an SLA that isn't closed yet
 * (admin/post/sla.php).
 */

require_once '../../includes/modal_header.php';

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-calendar-times me-2"></i>New Closure Day</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                <input type="date" class="form-control" name="holiday_date" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="holiday_name" maxlength="200" placeholder="Christmas Day, Office closed for the move" required>
            </div>
        </div>

        <div class="small text-secondary"><i class="fas fa-fw fa-stopwatch me-1"></i>No SLA clock runs on the day at all, the same as a day outside your business days. Adding it works the targets out again on every ticket with an SLA that isn't closed yet.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_holiday" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
