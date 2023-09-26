<div class="modal" id="addTicketWatcherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-eye mr-2"></i>Adding a ticket Watcher: <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong> - <?php echo $client_name; ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <input type="hidden" name="ticket_number" value="<?php echo "$ticket_prefix$ticket_number"; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Watcher Email</label>
                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                            </div>
                            <input type="email" class="form-control" name="watcher_email" placeholder="sombody@company.com">
                        </div>
                    </div>
                
                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="add_ticket_watcher" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Add</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>

            </form>

        </div>
    </div>
</div>
