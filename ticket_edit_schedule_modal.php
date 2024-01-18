<div class="modal" id="editTicketScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-fw fa-user mr-2"></i>
                    Edit Scheduled Time for <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                    <div class="form-group">
                        <label>Scheduled Date and Time</label>
                        <?php if (!$ticket_scheduled_for) { ?>
                        <input type="datetime-local" class="form-control" name="scheduled_date_time"
                            placeholder="Scheduled Date & Time">
                        <?php } else { ?>
                        <input type="datetime-local" class="form-control" name="scheduled_date_time"
                            value="<?php echo $ticket_scheduled_for; ?>">
                        <?php } ?>

                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_ticket_schedule" class="btn btn-primary text-bold"><i
                            class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i
                            class="fa fa-times mr-2"></i>Cancel</button>
                </div>

            </form>

        </div>
    </div>
</div>