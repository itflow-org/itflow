<div class="modal" id="bulkAssignTagsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-tags mr-2"></i>Bulk Assigning Tags</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body bg-white">
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

                            $sql_tags_select = mysqli_query($mysqli, "SELECT * FROM tags WHERE tag_type = 4 ORDER BY tag_name ASC");
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

            <div class="modal-footer bg-white">
                <button type="submit" name="bulk_assign_login_tags" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Assign</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>