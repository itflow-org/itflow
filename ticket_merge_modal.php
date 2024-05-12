<div class="modal" id="mergeTicketModal<?php echo $ticket_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-clone mr-2"></i>Merge & Close <?php echo "$ticket_prefix$ticket_number"; ?> into another ticket</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" id="current_ticket_id" name="ticket_id" value="<?php echo $ticket_id; ?>">
                <input type="hidden" name="merge_move_replies" value="0"> <!-- Default 0 -->
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Ticket number to merge this ticket into <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <?php
                                // Show the ticket prefix, or just the tag icon
                                if (empty($ticket_prefix)) {
                                    echo "<span class=\"input-group-text\"><i class=\"fa fa-fw fa-tag\"></i></span>";
                                } else {
                                    echo "<div class=\"input-group-text\"> $ticket_prefix </div>";
                                }
                                ?>
                            </div>
                            <input type="text" class="form-control" id="merge_into_ticket_number" name="merge_into_ticket_number" placeholder="Ticket number" required onfocusout="merge_into_number_get_details()">
                            <!-- Calls Javascript function merge_into_number_get_details() after leaving intput field -->
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Reason for merge <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-sticky-note"></i></span>
                            </div>
                            <input type="text" class="form-control" name="merge_comment" placeholder="Comments" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="merge_move_replies" value="1" id="checkMoveReplies">
                            <label class="form-check-label" for="checkMoveReplies">
                                Move notes & replies to the new parent ticket
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-dark" role="alert">
                        <i>The current ticket will be closed once merging is complete.</i>
                    </div>


                    <hr>
                    <div class="form-group" id="merge_into_details_div" hidden>
                        <h5 id="merge_into_details_number"></h5>
                        <p id="merge_into_details_client"></p>
                        <p id="merge_into_details_subject"></p>
                        <p id="merge_into_details_priority"></p>
                        <p id="merge_into_details_status"></p>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" id="merge_ticket_btn" name="merge_ticket" class="btn btn-primary text-bold" disabled><i class="fa fa-check mr-2"></i>Merge</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                    <!-- Merge button starts disabled. Is enabled by the merge_into_number_get_details Javascript function-->
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ticket merge JS -->
<script src="js/ticket_merge.js"></script>
