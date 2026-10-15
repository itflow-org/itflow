<?php

/*
 * Edit Calendar - its name and colour. Admins only.
 */

require_once '../../../includes/modal_header.php';

if (!calendarCanManage()) {
    echo json_encode(['error' => 'Only an admin can change a calendar.']);
    exit;
}

$calendar_id = intval($_GET['id'] ?? 0);
$calendar = calendarList()[$calendar_id] ?? null;
if (!$calendar) {
    echo json_encode(['error' => 'That calendar no longer exists.']);
    exit;
}
// The colour picker takes #rrggbb only - an old named colour shows as its shade
$color = calendarColor($calendar['calendar_color'], '#3c8dbc');
if (strlen($color) === 4) {
    $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
}

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-circle me-2 small" style="color:<?= escapeHtml(calendarColor($calendar['calendar_color'])) ?>"></i><?= escapeHtml($calendar['calendar_name']) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="calendar_id" value="<?= $calendar_id ?>">
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" maxlength="200" value="<?= escapeHtml($calendar['calendar_name']) ?>" required>
        </div>
        <div class="mb-0">
            <label class="form-label">Colour</label>
            <input type="color" class="form-control form-control-color" name="color" value="<?= escapeHtml($color) ?>">
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_calendar" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
