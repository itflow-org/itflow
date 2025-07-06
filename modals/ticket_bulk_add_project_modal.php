<div class="modal" id="bulkAssignTicketToProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-user-check mr-2"></i>Adding Tickets to Project</strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">

                <div class="form-group">
                    <label>Project</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                        </div>
                        <select class="form-control select2" name="project_id">
                            <option value="0">No Project</option>
                            <?php
                            $sql_projects_select = mysqli_query($mysqli, "SELECT project_id, project_name, project_prefix, project_number FROM projects 
                                WHERE project_archived_at IS NULL
                                AND project_completed_at IS NULL
                                ORDER BY project_name DESC"
                            );
                            while ($row = mysqli_fetch_array($sql_projects_select)) {
                                $project_id_select = intval($row['project_id']);
                                $project_prefix_select = nullable_htmlentities($row['project_prefix']);
                                $project_number_select = intval($row['project_number']);
                                $project_name_select = nullable_htmlentities($row['project_name']);

                                ?>
                                <option value="<?php echo $project_id_select; ?>"><?php echo " $project_prefix_select$project_number_select - $project_name_select"; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white">
                <button type="submit" name="bulk_add_ticket_project" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Assign</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>

        </div>
    </div>
</div>
