<?php

/*
 * New Calendar - admins only. Calendars group events: Onsite visits,
 * Maintenance windows, Internal, Time off...
 */

require_once '../../../includes/modal_header.php';

if (!calendarCanManage()) {
    echo json_encode(['error' => 'Only an admin can add a calendar.']);
    exit;
}

$colors = ['#3c8dbc', '#0f9d8a', '#7c3aed', '#d97706', '#dc2626', '#db2777', '#15803d', '#475569'];
$used = array_map('strtolower', array_column(calendarList(), 'calendar_color'));
$suggested = current(array_diff($colors, $used)) ?: $colors[0];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="far fa-fw fa-calendar-plus me-2"></i>New Calendar</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-calendar"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Onsite visits, Maintenance windows, Time off..." maxlength="200" required autofocus>
            </div>
        </div>
        <div class="mb-0">
            <label class="form-label">Color</label>
            <input type="color" class="form-control form-control-color" name="color" value="<?= $suggested ?>">
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_calendar" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
