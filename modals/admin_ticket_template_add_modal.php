<div class="modal" id="addTicketTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-life-ring mr-2"></i>Creating Ticket Template</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Template Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Template name" maxlength="200" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Subject</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                            </div>
                            <input type="text" class="form-control" name="subject" placeholder="Subject" maxlength="500">
                        </div>
                    </div>

                   <div class="form-group">
                        <textarea class="form-control tinymceTicket<?php if($config_ai_enable) { echo "AI"; } ?>" name="details"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                            </div>
                            <input type="text" class="form-control" name="description" placeholder="Short description">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Add it to a Project Template?</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                            </div>
                            <select class="form-control select2" name="project_template">
                                <option value="0">- No -</option>
                                <?php

                                $sql_project_templates = mysqli_query($mysqli, "SELECT * FROM project_templates WHERE project_template_archived_at IS NULL ORDER BY project_template_name ASC");
                                while ($row = mysqli_fetch_array($sql_project_templates)) {
                                    $project_template_id_select = intval($row['project_template_id']);
                                    $project_template_name_select = nullable_htmlentities($row['project_template_name']); ?>
                                    <option value="<?php echo $project_template_id_select; ?>"><?php echo $project_template_name_select; ?></option>

                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_ticket_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
