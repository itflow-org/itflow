<div class="modal" id="ticketMergeModal<?php echo $ticket_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-tag"></i> Merge & Close <?php echo "$ticket_prefix$ticket_number"; ?> into another ticket</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" id="current_ticket_id" name="ticket_id" value="<?php echo $ticket_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Ticket number to merge this ticket into <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <?php
                                // Show the ticket prefix, or just the tag icon
                                if(empty($ticket_prefix)){
                                    echo "<span class=\"input-group-text\"><i class=\"fa fa-fw fa-tag\"></i></span>";
                                }
                                else{
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="merge_ticket_btn" name="merge_ticket" class="btn btn-primary" disabled>Merge</button>
                    <!-- Merge button starts disabled. Is enabled by the merge_into_number_get_details Javascript function-->
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    //Gets details of the ticket we're going to merge this ticket into
    //Shows the details under the comments box & enables the merge button if the status of the merge into ticket is not closed
    function merge_into_number_get_details() {

        //Get the ticket number to merge into
        var merge_into_ticket_number = document.getElementById("merge_into_ticket_number").value;

        //Reset the form
        document.getElementById("merge_ticket_btn").disabled = true;
        document.getElementById("merge_into_details_div").hidden = true;

        //Send a GET request to post.php as post.php?merge_ticket_get_json_details=true&merge_into_ticket_number=NUMBER
        jQuery.get(
            "post.php",
            {merge_ticket_get_json_details: 'true', merge_into_ticket_number: merge_into_ticket_number},
            function(data){
                //If we get a response from post.php, parse it as JSON
                const merge_into_ticket_info = JSON.parse(data);

                //Check that the current ticket ID isn't also the new/merge ticket ID
                if(parseInt(merge_into_ticket_info.ticket_id) !== parseInt(document.getElementById("current_ticket_id").value)){

                    //Show the div with the master ticket details, populate
                    document.getElementById("merge_into_details_div").hidden = false;
                    document.getElementById("merge_into_details_number").innerText = "Master ticket details: " + merge_into_ticket_info.ticket_prefix + merge_into_ticket_info.ticket_number;
                    document.getElementById("merge_into_details_client").innerText = "Client Contact: " + merge_into_ticket_info.client_name + " / " + merge_into_ticket_info.contact_name;
                    document.getElementById("merge_into_details_subject").innerText = "Subject: " + merge_into_ticket_info.ticket_subject;
                    document.getElementById("merge_into_details_priority").innerText = "Priority: " + merge_into_ticket_info.ticket_priority;
                    document.getElementById("merge_into_details_status").innerText = "Status: " + merge_into_ticket_info.ticket_status;

                    //Enable the merge button if the merge into ticket isn't in a closed state
                    if(merge_into_ticket_info.ticket_status.toLowerCase() != "closed"){
                        document.getElementById("merge_ticket_btn").disabled = false;
                    }
                }
            }
        );
    }
</script>