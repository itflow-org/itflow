<div class="modal" id="replyEditTicketModal<?php echo $ticket_reply_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-edit mr-2"></i>Editing Ticket Reply</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="ticket_reply_id" value="<?php echo $ticket_reply_id; ?>">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <div class="btn-group btn-block btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-outline-secondary active">
                                <input type="radio" name="ticket_reply_type" value="Internal" <?php if ($ticket_reply_type == 'Internal') { echo "checked"; } ?>>Internal Note
                            </label>
                            <label class="btn btn-outline-secondary">
                                <input type="radio" name="ticket_reply_type" value="Public" <?php if ($ticket_reply_type == 'Public') { echo "checked"; } ?>>Public Comment
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control tinymce" name="ticket_reply"><?php echo nullable_htmlentities($ticket_reply); ?></textarea>
                    </div>

                    <?php if (!empty($ticket_reply_time_worked)) { ?>
                        <div class="col-3">
                            <div class="form-group">
                                <label>Time worked</label>
                                <input class="form-control timepicker" id="time_worked" name="time" type="text" placeholder="HH:MM:SS" pattern="([01]?[0-9]|2[0-3]):([0-5]?[0-9]):([0-5]?[0-9])" value="<?php echo date_format($ticket_reply_time_worked, 'H:i:s') ?>" required/>
                            </div>
                        </div>
                    <?php } ?>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_ticket_reply" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
