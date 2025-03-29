<div class="modal" id="linkTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-life-ring mr-2"></i>Link Ticket to Project: <strong><?php echo $project_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Ticket <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                            </div>
                            <select class="form-control select2" multiple name="tickets[]" required>
                                <option value="">- Select a Tickets -</option>
                                <?php

                                $sql_tickets_select = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_project_id = 0 AND ticket_closed_at IS NULL $client_ticket_select_query");
                                while ($row = mysqli_fetch_array($sql_tickets_select)) {
                                    $ticket_id_select = intval($row['ticket_id']);
                                    $ticket_prefix_select = nullable_htmlentities($row['ticket_prefix']);
                                    $ticket_number_select = intval($row['ticket_number']);
                                    $ticket_subject_select = nullable_htmlentities($row['ticket_subject']);
                                    ?>
                                    <option value="<?php echo $ticket_id_select; ?>"><?php echo "$ticket_prefix_select $ticket_number_select - $ticket_subject_select"; ?></option>
                                    <?php
                                }

                                ?>
                            </select>
                        </div>
                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="link_ticket_to_project" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
