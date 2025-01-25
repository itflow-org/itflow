<div class="modal" id="editProjectModal<?php echo $project_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-fw fa-project-diagram mr-2"></i>Editing Project: <strong><?php echo $project_name; ?></strong>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                <div class="modal-body bg-white">
                    <div class="form-group">
                        <label>Project Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Project Name" maxlength="255" value="<?php echo $project_name; ?>" required autofocus>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                            </div>
                            <input type="text" class="form-control" name="description" placeholder="Description" value="<?php echo $project_description; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Date Due <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="due_date" value="<?php echo $project_due; ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Manager</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user-tie"></i></span>
                            </div>
                            <select class="form-control select2" name="project_manager">
                                <option value="0">No Manager</option>
                                <?php
                                $sql_project_managers_select = mysqli_query(
                                    $mysqli,
                                    "SELECT users.user_id, user_name FROM users
                                    LEFT JOIN user_settings on users.user_id = user_settings.user_id
                                    WHERE user_role > 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                                );
                                while ($row = mysqli_fetch_array($sql_project_managers_select)) {
                                    $user_id_select = intval($row['user_id']);
                                    $user_name_select = nullable_htmlentities($row['user_name']); ?>
                                    <option <?php if ($project_manager == $user_id_select) { echo "selected"; } ?> value="<?php echo $user_id_select; ?>"><?php echo $user_name_select; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_project" class="btn btn-primary text-bold">
                        <i class="fas fa-check mr-2"></i>Save
                    </button>
                    <button type="button" class="btn btn-light" data-dismiss="modal">
                        <i class="fa fa-times mr-2"></i>Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
