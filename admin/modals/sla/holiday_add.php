<?php
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
            <label>Date <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                <input type="date" class="form-control" name="holiday_date" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="holiday_name" placeholder="e.g. Christmas Day, Office closed for move" maxlength="200" required>
            </div>
        </div>

        <small class="text-muted">SLA clocks do not run on this day at all, the same as a day outside your business days. Open tickets have their targets recalculated when you save.</small>
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_holiday" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
