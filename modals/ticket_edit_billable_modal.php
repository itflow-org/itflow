<div class="modal" id="editTicketBillableModal<?php echo $ticket_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-fw fa-user mr-2"></i>
                    Edit Billable Status for <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                    <div class="form-group">
                        <label>Billable</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                            </div>
                            <select class="form-control" name="billable_status">
                                <option <?php if ($ticket_billable == 1) { echo "selected"; } ?> value="1">Yes</option>
                                <option <?php if ($ticket_billable == 0) { echo "selected"; } ?> value="0">No</option>
                            </select>
                        </div>
                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_ticket_billable_status" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>

            </form>

        </div>
    </div>
</div>
