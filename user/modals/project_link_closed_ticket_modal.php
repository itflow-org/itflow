<div class="modal" id="linkClosedTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-life-ring mr-2"></i>Link closed ticket to project: <strong><?php echo $project_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                <div class="modal-body">

                    <div class="form-group">
                        <label>Ticket number <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <?php
                                // Show the ticket prefix, or just the tag icon
                                $config_row = mysqli_fetch_array(mysqli_query($mysqli, "SELECT config_ticket_prefix FROM settings WHERE company_id = 1"));
                                $config_ticket_prefix = $config_row['config_ticket_prefix'];
                                if (empty($config_ticket_prefix)) {
                                    echo "<span class=\"input-group-text\"><i class=\"fa fa-fw fa-tag\"></i></span>";
                                } else {
                                    echo "<div class=\"input-group-text\"> $config_ticket_prefix </div>";
                                }
                                ?>
                            </div>
                            <input type="text" class="form-control" name="ticket_number" inputmode="numeric" pattern="[0-9]*\.?[0-9]{0,2}" placeholder="Closed ticket number to link with project" required>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" name="link_closed_ticket_to_project" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
