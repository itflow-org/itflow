<?php
/*
 * A ticket template's fields - shared by ticket_template_add.php and
 * ticket_template_edit.php, which set $template (ticketTemplateRow(), or []
 * for a new one) and $template_tag_ids before including this. Posts what
 * ticketTemplateFields() reads (functions/ticket_template.php).
 *
 * Two tabs: Ticket (its name, what it is for, the subject and details it
 * starts with) and Sets up (category, priority, billing, agent, due time and
 * tags - each left alone unless set).
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$t = $template;
$due_hours = intval($t['ticket_template_due_hours'] ?? 0);
$due_in_days = $due_hours === 0 || $due_hours % 24 === 0;
$due_amount = $due_hours ? ($due_in_days ? intdiv($due_hours, 24) : $due_hours) : '';
$billable = array_key_exists('ticket_template_billable', $t) && $t['ticket_template_billable'] !== null ? (string) intval($t['ticket_template_billable']) : '';
$priority = (string) ($t['ticket_template_priority'] ?? '');
$assigned_to = intval($t['ticket_template_assigned_to'] ?? 0);
$placeholder_help = implode(', ', array_map(function ($name) {
    return "[$name]";
}, array_keys(TICKET_PLACEHOLDERS)));

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#ticketTemplateFormTicket"><i class="fas fa-fw fa-life-ring me-2 d-none d-sm-inline"></i>Ticket</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#ticketTemplateFormSetUp"><i class="fas fa-fw fa-sliders-h me-2 d-none d-sm-inline"></i>Sets up</a>
    </li>
</ul>

<div class="tab-content">

<div class="tab-pane fade show active" id="ticketTemplateFormTicket">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Template name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
                <input type="text" class="form-control" name="name" placeholder="New Hire Onboarding" maxlength="200" value="<?= escapeHtml($t['ticket_template_name'] ?? '') ?>" required autofocus>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">What it is for</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Shown when it is picked" maxlength="500" value="<?= escapeHtml($t['ticket_template_description'] ?? '') ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Subject</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                <input type="text" class="form-control" name="subject" placeholder="New hire onboarding - [Employee Name]" maxlength="500" value="<?= escapeHtml($t['ticket_template_subject'] ?? '') ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Details</label>
            <textarea class="form-control tinymceTicket" name="details"><?= escapeHtml($t['ticket_template_details'] ?? '') ?></textarea>
            <div class="form-text">
                Filled in when a ticket is raised: <span class="font-monospace"><?= escapeHtml($placeholder_help) ?></span>.
                Anything else in square brackets - <span class="font-monospace">[Employee Name]</span> - is asked for when a ticket is raised from it; make it a date, a list or yes or no, or required, under Fill-ins on the template's page.
            </div>
        </div>
        <?php if (empty($t)) { ?>
            <div class="col-12">
                <label class="form-label">Add it to a project template</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                    <select class="form-select select2" name="project_template">
                        <option value="0">- No -</option>
                        <?php
                        $sql_project_templates = mysqli_query($mysqli, "SELECT project_template_id, project_template_name FROM project_templates WHERE project_template_archived_at IS NULL ORDER BY project_template_name ASC");
                        while ($row = mysqli_fetch_assoc($sql_project_templates)) { ?>
                            <option value="<?= intval($row['project_template_id']) ?>"><?= escapeHtml($row['project_template_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">It goes at the end of that project template's tickets.</div>
            </div>
        <?php } ?>
    </div>
</div>

<div class="tab-pane fade" id="ticketTemplateFormSetUp">
    <p class="small text-secondary">How a ticket from it is set up. Anything left as it is stays as the New Ticket window has it, and can still be changed there before the ticket is made.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Category</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                <select class="form-select select2" name="category_id">
                    <option value="0">- Leave as it is -</option>
                    <?= categoryOptionsHtml('Ticket', intval($t['ticket_template_category_id'] ?? 0) ?: null) ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Priority</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                <select class="form-select select2" name="priority">
                    <option value="">- Leave as it is -</option>
                    <?php foreach (['Low' => 'var(--bs-info)', 'Medium' => 'var(--bs-warning)', 'High' => 'var(--bs-danger)', 'Urgent' => 'var(--bs-emphasis-color)'] as $level => $color) { ?>
                        <option value="<?= $level ?>" data-color="<?= $color ?>" <?= $priority === $level ? 'selected' : '' ?>><?= $level ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Assign to</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                <select class="form-select select2" name="assigned_to">
                    <option value="0">- Leave as it is -</option>
                    <?php
                    $sql_agents = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1 AND user_status = 1 AND (user_archived_at IS NULL OR user_id = $assigned_to) ORDER BY user_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_agents)) { ?>
                        <option value="<?= intval($row['user_id']) ?>" <?= intval($row['user_id']) === $assigned_to ? 'selected' : '' ?><?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Due</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                <input type="number" class="form-control" name="due_amount" min="0" max="8784" placeholder="No due date" value="<?= $due_amount ?>">
                <select class="form-select" name="due_unit" style="max-width: 9rem;" aria-label="Hours or days">
                    <option value="days" <?= $due_in_days ? 'selected' : '' ?>>days after</option>
                    <option value="hours" <?= $due_in_days ? '' : 'selected' ?>>hours after</option>
                </select>
            </div>
            <div class="form-text">After it is raised.</div>
        </div>
        <?php if (!empty($config_module_enable_accounting)) { ?>
            <div class="col-md-6">
                <label class="form-label">Billable</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                    <select class="form-select" name="billable">
                        <option value="" <?= $billable === '' ? 'selected' : '' ?>>- Leave as it is -</option>
                        <option value="1" <?= $billable === '1' ? 'selected' : '' ?>>Billable</option>
                        <option value="0" <?= $billable === '0' ? 'selected' : '' ?>>Not billable</option>
                    </select>
                </div>
            </div>
        <?php } else { ?>
            <input type="hidden" name="billable" value="<?= escapeHtml($billable) ?>">
        <?php } ?>
        <div class="col-12">
            <?= tagFormFieldHtml('ticket', $template_tag_ids, 'Onboarding, Hardware, Escalated') ?>
        </div>
    </div>
</div>

</div>
