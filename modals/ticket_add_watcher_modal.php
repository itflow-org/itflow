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
                <input type="hidden" name="watcher_notify" value="0"> <!-- Default 0 -->
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Watcher Email</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                            </div>
                            <select class="form-control select2" data-tags="true" name="watcher_email">
                                <option value="">- Select a contact or enter an email -</option>
                                <?php

                                $sql_client_contacts_select = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email FROM contacts WHERE contact_client_id = $client_id AND contact_email <> '' ORDER BY contact_name ASC");
                                while ($row = mysqli_fetch_array($sql_client_contacts_select)) {
                                    $contact_id_select = intval($row['contact_id']);
                                    $contact_name_select = nullable_htmlentities($row['contact_name']);
                                    $contact_email_select = nullable_htmlentities($row['contact_email']);
                                    ?>
                                    <option value="<?php echo $contact_email_select; ?>"><?php echo "$contact_name_select - $contact_email_select"; ?></option>

                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <?php if (!empty($config_smtp_host)) { ?>
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="watcher_notify" value="1" id="checkNotifyWatcher">
                                <label class="form-check-label" for="checkNotifyWatcher">
                                    Send email notification
                                </label>
                            </div>
                        </div>
                    <?php } ?>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="add_ticket_watcher" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Add</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>

            </form>

        </div>
    </div>
</div>
