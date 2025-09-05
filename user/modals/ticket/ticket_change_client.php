<div class="modal" id="clientChangeTicketModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-people-carry mr-2"></i>Change <?php echo "$ticket_prefix$ticket_number"; ?> to another client</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                <div class="modal-body">

                    <div class="form-group">
                        <label>New Client <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                            </div>
                            <select class="form-control select2" name="new_client_id" id="changeClientSelect" required>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>New Contact</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <select class="form-control select2" name="new_contact_id" id="changeContactSelect">
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="change_client_ticket" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Change</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ticket Change Client JS -->
<link rel="stylesheet" href="../plugins/jquery-ui/jquery-ui.min.css">
<script src="../plugins/jquery-ui/jquery-ui.min.js"></script>
<script src="js/ticket_change_client.js"></script>
