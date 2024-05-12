<div class="modal" id="addProjectTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-life-ring mr-2"></i>Adding Ticket to Project: <strong><?php echo $project_name; ?></strong></h5>
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
                            <select class="form-control select2" name="ticket_id" required>
                                <option value="">- Select a Ticket -</option>
                                <?php

                                $sql_tickets_select = mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_project_id != $project_id AND ticket_client_id = $client_id AND ticket_closed_at IS NULL");
                                while ($row = mysqli_fetch_array($sql_tickets_select)) {
                                    $ticket_id_select = intval($row['ticket_id']);
                                    $ticket_subject_select = nullable_htmlentities($row['ticket_subject']);
                                    ?>
                                    <option value="<?php echo $ticket_id_select; ?>"><?php echo $ticket_subject_select; ?></option>
                                    <?php
                                }

                                ?>
                            </select>
                        </div>
                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="add_project_ticket" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Add</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
