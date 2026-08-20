<?php

/*
 * ITFlow - Ticket template picker
 *
 * Shared by the ticket add modal and the recurring ticket add/edit modals. Each
 * option carries the template's subject, details and task list as data
 * attributes, which agent/js/ticket_tasks_modal.js applies to the form when the
 * template is picked.
 *
 * Set $selected_ticket_template_id before including to pre-select a template (an
 * archived one stays listed while it is the one selected, so an existing link
 * remains visible). Defaults to none.
 */

$selected_ticket_template_id = intval($selected_ticket_template_id ?? 0);

// Every template's tasks in one pass, rather than a query per option
$ticket_template_tasks = [];

$sql_ticket_template_tasks = mysqli_query(
    $mysqli,
    "SELECT task_template_ticket_template_id, task_template_name, task_template_completion_estimate
    FROM task_templates
    ORDER BY task_template_order ASC, task_template_id ASC"
);

while ($row = mysqli_fetch_assoc($sql_ticket_template_tasks)) {
    $ticket_template_tasks[intval($row['task_template_ticket_template_id'])][] = [
        'name' => $row['task_template_name'],
        'estimate' => intval($row['task_template_completion_estimate'])
    ];
}

?>

<div class="mb-3">
    <label>Template</label>
    <div class="input-group">
            <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
        <select class="form-select select2" id="ticket_template_select" name="ticket_template_id">
            <option value="0">- No Template -</option>
            <?php
            $sql_ticket_templates = mysqli_query(
                $mysqli,
                "SELECT ticket_template_id, ticket_template_name, ticket_template_subject, ticket_template_details
                FROM ticket_templates
                WHERE ticket_template_archived_at IS NULL OR ticket_template_id = $selected_ticket_template_id
                ORDER BY ticket_template_name ASC"
            );

            while ($row = mysqli_fetch_assoc($sql_ticket_templates)) {
                $ticket_template_id_select = intval($row['ticket_template_id']);
                $ticket_template_name_select = escapeHtml($row['ticket_template_name']);
                $ticket_template_subject_select = escapeHtml($row['ticket_template_subject']);
                $ticket_template_details_select = escapeHtml($row['ticket_template_details']);
                $ticket_template_task_list = $ticket_template_tasks[$ticket_template_id_select] ?? [];
                $task_count = count($ticket_template_task_list);
                ?>
                <option value="<?= $ticket_template_id_select ?>"
                        data-subject="<?= $ticket_template_subject_select ?>"
                        data-details="<?= $ticket_template_details_select ?>"
                        data-tasks="<?= escapeHtml(json_encode($ticket_template_task_list)) ?>"
                        <?php if ($selected_ticket_template_id == $ticket_template_id_select) { echo "selected"; } ?>>
                    <?= $ticket_template_name_select ?> (<?= $task_count ?> tasks)
                </option>
            <?php } ?>
        </select>
    </div>
    <small class="form-text text-muted">Picking a template fills in the subject, details and tasks below. You can edit them afterwards.</small>
</div>
