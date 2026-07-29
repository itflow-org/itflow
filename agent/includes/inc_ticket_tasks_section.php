<?php

/*
 * ITFlow - Editable task rows
 *
 * Shared by the ticket add modal and the recurring ticket add/edit modals. Rows
 * are added and removed in the browser by agent/js/ticket_tasks_modal.js and
 * submit as parallel tasks[] / task_estimates[] arrays, read back by
 * parseSubmittedTasks().
 *
 * Set $existing_tasks before including to pre-fill rows - a list of
 * ['name' => string, 'estimate' => int]. Defaults to none.
 *
 * The tasks_submitted marker distinguishes "the user cleared every row" from
 * "this form has no task section", which an empty tasks[] cannot express because
 * a form with no inputs of that name posts nothing at all.
 */

$existing_tasks = $existing_tasks ?? [];

?>

<input type="hidden" name="tasks_submitted" value="1">

<div class="form-group">
    <label>Tasks</label>

    <div class="form-row mb-1 text-muted small">
        <div class="col-7">Task</div>
        <div class="col-3">Estimate (mins)</div>
        <div class="col-2"></div>
    </div>

    <div id="ticketTasksContainer">
        <?php foreach ($existing_tasks as $existing_task) { ?>
        <div class="form-row mb-2 ticket-task-row">
            <div class="col-7">
                <input type="text" class="form-control" name="tasks[]" placeholder="Task name" maxlength="255" value="<?= escapeHtml($existing_task['name']) ?>">
            </div>
            <div class="col-3">
                <input type="number" class="form-control" name="task_estimates[]" placeholder="Mins" min="0" value="<?= intval($existing_task['estimate']) ?: '' ?>">
            </div>
            <div class="col-2">
                <button type="button" class="btn btn-secondary btn-block ticket-task-remove" title="Remove task"><i class="fa fa-fw fa-trash"></i></button>
            </div>
        </div>
        <?php } ?>
    </div>

    <button type="button" class="btn btn-secondary" id="ticketTaskAdd"><i class="fas fa-plus mr-2"></i>Add Task</button>

    <small class="form-text text-muted">Leave the estimate blank if you don't track one. Blank task names are ignored.</small>
</div>
