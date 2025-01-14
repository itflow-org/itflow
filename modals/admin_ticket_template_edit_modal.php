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

                    <div class="form-group">
                        <label>Template Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" maxlength="200" value="<?php echo $ticket_template_name; ?>" placeholder="Template name" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Subject</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                            </div>
                            <input type="text" class="form-control" name="subject" maxlength="500" value="<?php echo $ticket_template_subject; ?>" placeholder="Subject">
                        </div>
                    </div>

                    <?php if($config_ai_enable) { ?>
                    <div class="form-group">
                        <textarea class="form-control tinymceai" id="textInput" name="details"><?php echo $ticket_template_details; ?></textarea>
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
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_ticket_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
