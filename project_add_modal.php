<div class="modal" id="addProjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-project-diagram mr-2"></i>New Project</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php if (isset($_GET['client_id'])) { echo $client_id; } else { echo 0; } ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Project Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Project Name" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Template</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                            </div>
                            <select class="form-control select2" name="project_template_id">
                                <option value="">- Template -</option>
                                <?php
                                $sql = mysqli_query($mysqli, "SELECT * FROM project_templates WHERE project_template_archived_at IS NULL ORDER BY project_template_name ASC");
                                while ($row = mysqli_fetch_array($sql)) {
                                    $project_template_id = intval($row['project_template_id']);
                                    $project_template_name = nullable_htmlentities($row['project_template_name']);
                                ?>
                                <option value="<?php echo $project_template_id; ?>"><?php echo $project_template_name; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                            </div>
                            <input type="text" class="form-control" name="description" placeholder="Description">
                        </div>
                    </div>


                    <div class="form-group">
                        <label>Date Due <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="due_date" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Project Manager</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user-tie"></i></span>
                            </div>
                            <select class="form-control select2" name="project_manager">
                                <option value="0">No Manager</option>
                                <?php

                                $sql = mysqli_query(
                                    $mysqli,
                                    "SELECT users.user_id, user_name FROM users
                                    LEFT JOIN user_settings on users.user_id = user_settings.user_id
                                    WHERE user_role > 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                                );
                                while ($row = mysqli_fetch_array($sql)) {
                                    $user_id = intval($row['user_id']);
                                    $user_name = nullable_htmlentities($row['user_name']); ?>
                                    <option value="<?php echo $user_id; ?>"><?php echo $user_name; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <?php if (empty($_GET['client_id'])) { ?>
                    <div class="form-group">
                        <label>Client</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                            </div>
                            <select class="form-control select2" name="client_id">
                                <option vlaue="0">- No Client -</option>
                                <?php
                                $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_array($sql)) {
                                    $client_id = intval($row['client_id']);
                                    $client_name = nullable_htmlentities($row['client_name']);
                                ?>
                                <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <?php } ?>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_project" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
