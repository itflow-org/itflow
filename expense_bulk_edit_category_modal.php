<div class="modal" id="bulkEditCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-list mr-2"></i>Bulk Set Category</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">

                <div class="form-group">
                    <label>Category <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                        </div>
                        <select class="form-control select2" name="bulk_category_id">
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Expense' AND category_archived_at IS NULL ORDER BY category_name ASC");
                            while ($row = mysqli_fetch_array($sql)) {
                                $category_id = intval($row['category_id']);
                                $category_name = nullable_htmlentities($row['category_name']);
                                ?>
                                <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>

                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-white">
                <button type="submit" name="bulk_edit_expense_category" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check mr-2"></i>Set</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>
