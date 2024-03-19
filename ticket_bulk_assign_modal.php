<div class="modal" id="bulkAssignTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-user-check mr-2"></i>Bulk Assigning Selected Tickets:</strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">

                <div class="form-group">
                    <label>Assign to</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        </div>
                        <select class="form-control select2" name="assign_to">
                            <option value="0">Not Assigned</option>
                            <?php
                            $sql_users_select = mysqli_query($mysqli, "SELECT * FROM users 
                                LEFT JOIN user_settings on users.user_id = user_settings.user_id
                                WHERE user_role > 1
                                AND user_status = 1
                                AND user_archived_at IS NULL 
                                ORDER BY user_name DESC"
                            );
                            while ($row = mysqli_fetch_array($sql_users_select)) {
                                $user_id_select = intval($row['user_id']);
                                $user_name_select = nullable_htmlentities($row['user_name']);

                                ?>
                                <option value="<?php echo $user_id_select; ?>"><?php echo $user_name_select; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white">
                <button type="submit" name="bulk_assign_ticket" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Bulk Assign</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>

        </div>
    </div>
</div>
