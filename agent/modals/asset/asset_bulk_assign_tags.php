<?php

require_once '../../../includes/modal_header.php';

$selected_ids = array_map('intval', $_GET['selected_ids'] ?? []);

$count = count($selected_ids);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-tags mr-2"></i>Assign Tags for <strong><?= $count ?></strong> Assets</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($selected_ids as $id) { ?><input type="hidden" name="asset_ids[]" value="<?= $id ?>"><?php } ?>

    <div class="modal-body">
        <input type="hidden" name="remove_tags" value="0">

        <div class="form-group form-check">
            <input type="checkbox" class="form-check-input" name="remove_tags" value="1">
            <label class="form-check-label text-danger">Remove Existing Tags</label>
        </div>

        <div class="form-group">
            <label>Tags</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                </div>
                <select class="form-control select2" name="tags[]" data-placeholder="Add some tags" multiple>
                    <?php

                    $sql_tags_select = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 5 ORDER BY tag_name ASC");
                    while ($row = mysqli_fetch_array($sql_tags_select)) {
                        $tag_id_select = intval($row['tag_id']);
                        $tag_name_select = nullable_htmlentities($row['tag_name']);
                        ?>
                        <option value="<?php echo $tag_id_select; ?>"><?php echo $tag_name_select; ?></option>
                    <?php } ?>

                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_assign_asset_tags" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Assign Tags</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
