<?php

require_once '../../../includes/modal_header.php';

$calendar_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM calendars WHERE calendar_id = $calendar_id LIMIT 1");
                     
$row = mysqli_fetch_array($sql);
$calendar_name = nullable_htmlentities($row['calendar_name']);
$calendar_color = nullable_htmlentities($row['calendar_color']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-circle mr-2" style="color:<?php echo $calendar_color; ?>"></i><?php echo $calendar_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="calendar_id" value="<?php echo $calendar_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Name</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                </div>
                <input type="text" class="form-control" name="name" placeholder="Name your calendar" maxlength="200" value="<?php echo $calendar_name; ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Color <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                </div>
                <input type="color" class="form-control col-3" name="color" value="<?php echo $calendar_color; ?>" required>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_calendar" class="btn btn-primary"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
