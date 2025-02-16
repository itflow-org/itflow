<?php

require_once '../includes/ajax_header.php';

$contact_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT contact_name FROM contacts WHERE contact_id = $contact_id LIMIT 1");
$row = mysqli_fetch_array($sql);
$contact_name = nullable_htmlentities($row['contact_name']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class='fa fa-fw fa-sticky-note mr-2'></i>Creating note: <strong><?php echo $contact_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">

    <div class="modal-body bg-white">

        <div class="form-group">
            <label>Type</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                </div>
                <select class="form-control select2" name="type">
                    <?php foreach ($note_types_array as $note_type => $note_type_icon) { ?>
                    <option><?php echo nullable_htmlentities($note_type); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <textarea class="form-control" rows="6" name="note" placeholder="Notes, eg Personal tidbits to spark convo, temperment, etc"></textarea>
        </div>

    </div>

    <div class="modal-footer bg-white">
        <button type="submit" name="add_contact_note" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
