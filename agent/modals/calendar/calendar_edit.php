<?php

require_once '../../../includes/modal_header.php';

$calendar_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT calendar_color, calendar_name FROM calendars WHERE calendar_id = $calendar_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$calendar_name = escapeHtml($row['calendar_name']);
$calendar_color = escapeHtml($row['calendar_color']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-circle me-2" style="color:<?= $calendar_color ?>"></i><?= $calendar_name ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="calendar_id" value="<?= $calendar_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label>Name</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Name your calendar" maxlength="200" value="<?= $calendar_name ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label>Color <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                <input type="color" class="form-control form-control-color w-auto flex-grow-0" name="color" value="<?= $calendar_color ?>" required>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_calendar" class="btn btn-primary"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
