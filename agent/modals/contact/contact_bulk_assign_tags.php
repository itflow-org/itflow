<?php

require_once '../../../includes/modal_header.php';

$contact_ids = array_map('intval', $_GET['contact_ids'] ?? []);

$count = count($contact_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-tags mr-2"></i>Assign Tags for <strong><?= $count ?></strong> Contacts</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($contact_ids as $contact_id) { ?><input type="hidden" name="contact_ids[]" value="<?= $contact_id ?>"><?php } ?>

    <div class="modal-body">
        <input type="hidden" name="bulk_remove_tags" value="0">

        <div class="form-group form-check">
            <input type="checkbox" class="form-check-input" name="bulk_remove_tags" value="1">
            <label class="form-check-label text-danger">Remove Existing Tags</label>
        </div>

        <div class="form-group">
            <label>Tags</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                </div>
                <select class="form-control select2" name="bulk_tags[]" data-placeholder="Add some tags" multiple>
                    <?php

                    $sql_tags_select = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 3 ORDER BY tag_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_tags_select)) {
                        $tag_id_select = intval($row['tag_id']);
                        $tag_name_select = escapeHtml($row['tag_name']);
                        ?>
                        <option value="<?php echo $tag_id_select; ?>"><?php echo $tag_name_select; ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_assign_contact_tags" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Assign Tags</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
