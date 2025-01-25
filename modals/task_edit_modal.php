<div class="modal" id="editTaskModal<?php echo $task_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-tasks mr-2"></i>Editing task</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                <!-- Check to see if its a ticket template task or ticket task by checking to see if ticket_id is set -->
                <input type="hidden" name="is_ticket" value="<?php if ($ticket_id) { echo 1; } else { echo 0; } ?>">
                
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Name the task" maxlength="255" value="<?php echo $task_name; ?>" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Order</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-sort-numeric-down"></i></span>
                            </div>
                            <input type="number" class="form-control" name="order" placeholder="Order" value="<?php echo $task_order; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Estimated Completion Time <span class="text-secondary">(Minutes)</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                            </div>
                            <input type="number" class="form-control" name="completion_estimate" placeholder="Estimated time to complete task in mins" value="<?php echo $task_completion_estimate; ?>">
                        </div>
                    </div>
                
                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_task" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>

            </form>

        </div>
    </div>
</div>
