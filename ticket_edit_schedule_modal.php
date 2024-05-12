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
                        <label>Scheduled Date and Time </label>

                        <?php if (!$ticket_scheduled_for) { ?>
                            <input type="datetime-local" class="form-control" name="scheduled_date_time" placeholder="Scheduled Date & Time" min="<?php echo date('Y-m-d\TH:i'); ?>">
                        <?php } else { ?>
                            <input type="datetime-local" class="form-control" name="scheduled_date_time" min="<?php echo date('Y-m-d\TH:i'); ?>" value="<?php echo $ticket_scheduled_for; ?>">
                        <?php } ?>

                    </div>
                    <div class="form-group">
                        <label>Onsite </label>
                        <select class="form-control" name="onsite" required>
                            <option value="0" <?php if ($ticket_onsite == 0) echo "selected"; ?>>No</option>
                            <option value="1" <?php if ($ticket_onsite == 1) echo "selected"; ?>>Yes</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                <?php if (!empty($ticket_scheduled_for)) { ?>
                    <a href="post.php?cancel_ticket_schedule=<?php echo htmlspecialchars($ticket_id); ?>" class="btn btn-danger text-bold">
                        <i class="fa fa-trash mr-2"></i>Cancel Scheduled Time
                    </a>
                <?php } ?>
                    <button type="submit" name="edit_ticket_schedule" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>

            </form>

        </div>
    </div>
</div>
