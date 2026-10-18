<?php

/*
 * ITFlow - Ticket template picker
 *
 * Shared by New Ticket, the New Tickets windows of the assets and clients
 * lists, and the recurring ticket add/edit windows. Each option carries what
 * the template puts into the form (ticketTemplatePickerData(),
 * functions/ticket_template.php) - its subject, details and tasks, and its
 * category, priority, billing, agent, due time and tags - which
 * agent/js/ticket_tasks_modal.js writes into the form's fields marked
 * data-template-field when it is picked. A form leaves out the fields it
 * doesn't have.
 *
 * Set before including:
 *   $selected_ticket_template_id   the one to start with (an archived one stays listed
 *                                  while it is the one selected, so an existing link shows)
 *   $ticket_template_field         the field's name - ticket_template_id by default
 *   $ticket_template_apply_on_load fill the form in from the one selected as it opens
 *   $ticket_template_hint          the line under it, when it should say something else
 */

$selected_ticket_template_id = intval($selected_ticket_template_id ?? 0);
$ticket_template_field = $ticket_template_field ?? 'ticket_template_id';
$ticket_template_apply_on_load = !empty($ticket_template_apply_on_load);
$ticket_template_hint = $ticket_template_hint ?? 'Picking one fills in the ticket - change anything before it is made. <span class="font-monospace">[Client]</span>, <span class="font-monospace">[Month]</span> and the like are filled in when it is saved.';

// Every template's tasks and tags in one pass each, rather than a query per option
$ticket_template_tasks = [];
$sql_ticket_template_tasks = mysqli_query($mysqli, "SELECT task_template_id, task_template_ticket_template_id, task_template_name, task_template_completion_estimate
    FROM task_templates ORDER BY task_template_order ASC, task_template_id ASC");
while ($row = mysqli_fetch_assoc($sql_ticket_template_tasks)) {
    $ticket_template_tasks[intval($row['task_template_ticket_template_id'])][] = ['id' => intval($row['task_template_id']), 'name' => $row['task_template_name'], 'estimate' => intval($row['task_template_completion_estimate'])];
}
$ticket_template_tags = [];
$sql_ticket_template_tags = mysqli_query($mysqli, "SELECT ticket_template_id, ticket_template_tags.tag_id FROM ticket_template_tags
    JOIN tags ON tags.tag_id = ticket_template_tags.tag_id AND tag_archived_at IS NULL");
while ($row = mysqli_fetch_row($sql_ticket_template_tags)) {
    $ticket_template_tags[intval($row[0])][] = intval($row[1]);
}

$sql_ticket_templates = mysqli_query($mysqli, "SELECT ticket_templates.*, category_name, user_name AS assignee_name FROM ticket_templates
    LEFT JOIN categories ON category_id = ticket_template_category_id AND ticket_template_category_id > 0
    LEFT JOIN users ON user_id = ticket_template_assigned_to AND ticket_template_assigned_to > 0
    WHERE ticket_template_archived_at IS NULL OR ticket_template_id = $selected_ticket_template_id
    ORDER BY ticket_template_name ASC");

?>

<div class="mb-3">
    <label class="form-label">Template</label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
        <select class="form-select select2" id="ticket_template_select" name="<?= escapeHtml($ticket_template_field) ?>" data-template-picker<?= $ticket_template_apply_on_load ? ' data-template-apply="1"' : '' ?>>
            <option value="0">- No Template -</option>
            <?php while ($row = mysqli_fetch_assoc($sql_ticket_templates)) {
                $id = intval($row['ticket_template_id']);
                $tasks = $ticket_template_tasks[$id] ?? [];
                // A second line saying what it sets up, and what it is for
                $about = [count($tasks) . ' task' . (count($tasks) === 1 ? '' : 's')];
                if (!empty($row['ticket_template_priority'])) {
                    $about[] = $row['ticket_template_priority'];
                }
                if (!empty($row['category_name'])) {
                    $about[] = $row['category_name'];
                }
                if (intval($row['ticket_template_due_hours'])) {
                    $about[] = 'due in ' . ticketTemplateDueText($row['ticket_template_due_hours']);
                }
                $description = trim((string) $row['ticket_template_description']);
                $data = ticketTemplatePickerData($row, $tasks, $ticket_template_tags[$id] ?? []);
                ?>
                <option value="<?= $id ?>" <?= $selected_ticket_template_id === $id ? 'selected' : '' ?><?= selectOptionData(['icon' => 'fas fa-cube', 'description' => implode(' · ', $about) . ($description !== '' ? " - $description" : ''), 'keywords' => $description]) ?><?php foreach ($data as $key => $value) { ?> data-template-<?= $key ?>="<?= escapeHtml($value) ?>"<?php } ?>><?= escapeHtml($row['ticket_template_name']) ?><?= $row['ticket_template_archived_at'] ? ' (archived)' : '' ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="form-text"><?= $ticket_template_hint ?></div>
</div>
