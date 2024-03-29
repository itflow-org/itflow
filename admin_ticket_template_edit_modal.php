<div class="modal" id="editTicketTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-life-ring mr-2"></i>Editing Ticket Template: <?php echo $ticket_template_name; ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="ticket_template_id" value="<?php echo $ticket_template_id; ?>">
                <div class="modal-body bg-white">
                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-details"><i class="fa fa-fw fa-life-ring mr-2"></i>Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-ticket"><i class="fa fa-fw fa-life-ring mr-2"></i>Template</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-project-template"><i class="fa fa-fw fa-project-diagram mr-2"></i>Project Template</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-details">

                            <div class="form-group">
                                <label>Template Name <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" value="<?php echo $ticket_template_name; ?>" placeholder="Template name" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="description" value="<?php echo $ticket_template_description; ?>" placeholder="Short description">
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-ticket">

                            <div class="form-group">
                                <label>Subject</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="subject" value="<?php echo $ticket_template_subject; ?>" placeholder="Subject">
                                </div>
                            </div>

                            <?php if($config_ai_enable) { ?>
                            <div class="form-group">
                                <textarea class="form-control tinymceai" id="textInput" name="details"><?php echo $ticket_template_details; ?>"</textarea>
                            </div>

                            <div class="mb-3">
                                <button id="rewordButton" class="btn btn-primary" type="button"><i class="fas fa-fw fa-robot mr-2"></i>Reword</button>
                                <button id="undoButton" class="btn btn-secondary" type="button" style="display:none;"><i class="fas fa-fw fa-redo-alt mr-2"></i>Undo</button>
                            </div>
                            <?php } else { ?>
                            <div class="form-group">
                                <textarea class="form-control tinymce" rows="5" name="details"><?php echo $ticket_template_details; ?></textarea>
                            </div>
                            <?php } ?>
                        
                        </div>

                        <div class="tab-pane fade" id="pills-project-template">

                            <div class="form-group">
                                <label>Project Template</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                                    </div>
                                    <select class="form-control select2" name="project_template">
                                        <option value="0">- None -</option>
                                        <?php

                                        $sql_project_templates = mysqli_query($mysqli, "SELECT * FROM project_templates WHERE project_template_archived_at IS NULL ORDER BY project_template_name ASC");
                                        while ($row = mysqli_fetch_array($sql_project_templates)) {
                                            $project_template_id_select = intval($row['project_template_id']);
                                            $project_template_name_select = nullable_htmlentities($row['project_template_name']); ?>
                                            <option <?php if($project_template_id == $project_template_id_select) { echo "selected"; } ?> value="<?php echo $project_template_id_select; ?>"><?php echo $project_template_name_select; ?></option>

                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                    </div>
                
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_ticket_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
