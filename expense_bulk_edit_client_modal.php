<div class="modal" id="bulkEditClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-user mr-2"></i>Bulk Set Client</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">

                <div class="form-group">
                    <label>Client</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <select class="form-control select2" name="bulk_client_id">
                            <option value="0">- No Client -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                            while ($row = mysqli_fetch_array($sql)) {
                                $client_id = intval($row['client_id']);
                                $client_name = nullable_htmlentities($row['client_name']);
                                ?>
                                <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>

                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-white">
                <button type="submit" name="bulk_edit_expense_client" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check mr-2"></i>Set</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>
