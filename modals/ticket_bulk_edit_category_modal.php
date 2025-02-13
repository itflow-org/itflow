<div class="modal" id="bulkEditCategoryTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-layer-group mr-2"></i>Bulk Categorizing Selected Tickets:</strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">

                <div class="form-group">
                    <label>Category</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        </div>
                        <select class="form-control select2" name="bulk_category">
                            <option value="0">- Uncategorized -</option>
                            <?php
                            $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL");
                            while ($row = mysqli_fetch_array($sql_categories)) {
                                $category_id = intval($row['category_id']);
                                $category_name = nullable_htmlentities($row['category_name']);

                                ?>
                                <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white">
                <button type="submit" name="bulk_edit_ticket_category" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Bulk Edit</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>

        </div>
    </div>
</div>
