<div class="modal" id="replyEditTicketModal<?php echo $ticket_reply_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-edit"></i> Editing Ticket Reply</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="ticket_reply_id" value="<?php echo $ticket_reply_id; ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <textarea class="form-control summernote" rows="8" name="ticket_reply"><?php echo $ticket_reply; ?></textarea>
                    </div>

                    <?php if (!empty($ticket_reply_time_worked)) { ?>
                        <b>Time worked</b>
                        <div class="col-3">
                            <div class="form-group">
                                <input class="form-control timepicker" id="time_worked" name="time" type="time" step="1" value="<?php echo date_format($ticket_reply_time_worked, 'H:i:s') ?>"/>
                            </div>
                        </div>
                    <?php } ?>

                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_ticket_reply" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
