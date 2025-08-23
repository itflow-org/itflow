<div class="modal" id="bulkAddTicketModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-life-ring mr-2"></i>Creating Tickets for Assets</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            
            <input type="hidden" name="bulk_billable" value="0">
            <input type="hidden" name="bulk_client" value="<?php echo $client_id; ?>">
            <div class="modal-body">

                <div class="form-group">
                    <label>Subject <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="bulk_subject" placeholder="Asset Name will be prepended to Subject" maxlength="200">
                    </div>
                </div>

                <div class="form-group">
                    <textarea class="form-control tinymceTicket<?php if($config_ai_enable) { echo "AI"; } ?>" id="textInput" name="bulk_details"></textarea>
                </div>

                <div class="row">
                                
                    <div class="col">
                        <div class="form-group">
                            <label>Priority <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                </div>
                                <select class="form-control select2" name="bulk_priority">
                                    <option>Low</option>
                                    <option>Medium</option>
                                    <option>High</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="form-group">
                            <label>Category</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                                </div>
                                <select class="form-control select2" name="bulk_category">
                                    <option value="0">- Not Categorized -</option>
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

                </div>

                <div class="form-group">
                    <label>Assign to</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        </div>
                        <select class="form-control select2" name="bulk_assigned_to">
                            <option value="0">Not Assigned</option>
                            <?php

                            $sql = mysqli_query(
                                $mysqli,
                                "SELECT user_id, user_name FROM users
                                WHERE user_role_id > 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                            );
                            while ($row = mysqli_fetch_array($sql)) {
                                $user_id = intval($row['user_id']);
                                $user_name = nullable_htmlentities($row['user_name']); ?>
                                <option <?php if ($session_user_id == $user_id) { echo "selected"; } ?> value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Project</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                        </div>
                        <select class="form-control select2" name="bulk_project">
                            <option value="0">- None -</option>
                            <?php

                            $sql_projects = mysqli_query($mysqli, "SELECT * FROM projects WHERE project_client_id = $client_id AND project_completed_at IS NULL AND project_archived_at IS NULL ORDER BY project_name ASC");
                            while ($row = mysqli_fetch_array($sql_projects)) {
                                $project_id_select = intval($row['project_id']);
                                $project_name_select = nullable_htmlentities($row['project_name']); ?>
                                <option value="<?php echo $project_id_select; ?>"><?php echo $project_name_select; ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

                <?php if ($config_module_enable_accounting) { ?>
                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="bulk_billable" <?php if ($config_ticket_default_billable == 1) { echo "checked"; } ?> value="1" id="billableSwitch">
                        <label class="custom-control-label" for="billableSwitch">Billable</label>
                    </div>
                </div>
                <?php } ?>

            </div>

            <div class="modal-footer">
                <button type="submit" name="bulk_add_asset_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
            </div>
            
        </div>
    </div>
</div>
