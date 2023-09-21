<div class="modal" id="editTicketWatchersModal<?php echo $ticket_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-users mr-2"></i>Editing ticket Watchers: <strong><?php echo "$ticket_prefix$ticket_number"; ?></strong> - <?php echo $client_name; ?></h5>
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
                        <label>Watchers</label>

                        <div class="watchers">
        
                            <?php
                            $sql_watchers = mysqli_query($mysqli, "SELECT * FROM ticket_watchers WHERE watcher_ticket_id = $ticket_id");
                            while ($row = mysqli_fetch_array($sql_watchers)) {
                                $watcher_id = intval($row['ticket_watcher_id']);
                                $watcher_email = nullable_htmlentities($row['watcher_email']);
                            ?>
                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                                </div>
                                <input type="text" class="form-control" name="watchers[]" value="<?php echo $watcher_email; ?>">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-danger" onclick="removeWatcher(this)"><i class="fas fa-fw fa-minus"></i></button>
                                </div>
                            </div>
                            <?php
                            }
                            ?>

                        </div>

                        <button class="btn btn-primary" type="button" onclick="addWatcher(this)"><i class="fas fa-fw fa-plus"></i> Add Watcher</button>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_ticket_watchers" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>

            </form>

        </div>
    </div>
</div>
