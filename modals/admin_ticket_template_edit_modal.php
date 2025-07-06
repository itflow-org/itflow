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

                    <div class="form-group">
                        <textarea class="form-control tinymceTicket<?php if($config_ai_enable) { echo "AI"; } ?>" name="details"><?php echo $ticket_template_details; ?></textarea>
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
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_ticket_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
