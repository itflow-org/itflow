<div class="modal" id="addProjectTemplateTicketTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-life-ring mr-2"></i>Adding Ticket Template</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="project_template_id" value="<?php echo $project_template_id; ?>">
                <div class="modal-body">

                    <div class="form-group">
                        <label>Ticket Template <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                            </div>
                            <select class="form-control select2" name="ticket_template_id" required>
                                <option value="">- Select a Ticket Template -</option>
                                <?php

                                $sql_ticket_templates_select = mysqli_query($mysqli, "SELECT ticket_template_id, ticket_template_name FROM ticket_templates
                                    WHERE ticket_template_id NOT IN (
                                        SELECT ticket_template_id FROM project_template_ticket_templates
                                        WHERE project_template_id = $project_template_id
                                    )
                                    AND ticket_template_archived_at IS NULL
                                    ORDER BY ticket_template_name ASC"
                                );
                                while ($row = mysqli_fetch_array($sql_ticket_templates_select)) {
                                    $ticket_template_id_select = intval($row['ticket_template_id']);
                                    $ticket_template_name_select = nullable_htmlentities($row['ticket_template_name']);
                                    ?>
                                    <option value="<?php echo $ticket_template_id_select; ?>"><?php echo $ticket_template_name_select; ?></option>
                                    <?php
                                }

                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Order</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-sort-numeric-down"></i></span>
                            </div>
                            <input type="text" class="form-control" name="order" value="1">
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" name="add_ticket_template_to_project_template" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Add</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
