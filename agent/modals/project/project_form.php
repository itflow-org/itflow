<?php

/*
 * The fields of New Project and Edit Project - included by project_add.php
 * and project_edit.php, which set $form (the values, raw), $form_client_id
 * (the project's client, 0 for internal or not picked yet) and $is_new.
 * A new project picks its client here when opened outside one, and can
 * bring in a project template's tickets; the client contact picker follows
 * the client (agent/js/project_modal.js).
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_contact_id = intval($form['project_contact_id'] ?? 0);
$form_manager_id = intval($form['project_manager'] ?? 0);

?>

<?php if ($is_new && !$form_client_id && !isset($form['fixed_client'])) { ?>
    <div class="mb-3">
        <label class="form-label">Client <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
            <select class="form-select select2" name="client_id" id="projectClientSelect" required>
            <option value="">- Pick a client -</option>
            <option value="0" data-icon="fas fa-building" data-description="Our own work - no client">Internal</option>
            <?php
            $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
            while ($row = mysqli_fetch_assoc($sql)) { ?>
                <option value="<?= intval($row['client_id']) ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
            <?php } ?>
            </select>
        </div>
    </div>
<?php } elseif ($is_new) { ?>
    <input type="hidden" name="client_id" id="projectClientId" value="<?= $form_client_id ?>">
<?php } ?>

<div class="mb-3">
    <label class="form-label">Name <strong class="text-danger">*</strong></label>
    <div class="input-group">
        <span class="input-group-text"><i class="fas fa-fw fa-project-diagram"></i></span>
        <input type="text" class="form-control" name="name" placeholder="Onboarding, server refresh, office move..." maxlength="255" value="<?= escapeHtml($form['project_name'] ?? '') ?>" required autofocus>
    </div>
</div>

<?php if ($is_new) {
    // A template's tickets and tasks, counted, so picking one says what it brings
    $sql_templates = mysqli_query($mysqli, "SELECT project_templates.project_template_id, project_template_name, project_template_description,
            COUNT(DISTINCT ptt.ticket_template_id) AS ticket_count, COUNT(task_template_id) AS task_count
        FROM project_templates
        LEFT JOIN project_template_ticket_templates ptt ON ptt.project_template_id = project_templates.project_template_id
        LEFT JOIN task_templates ON task_template_ticket_template_id = ptt.ticket_template_id
        WHERE project_template_archived_at IS NULL
        GROUP BY project_templates.project_template_id ORDER BY project_template_name ASC");
    if (mysqli_num_rows($sql_templates)) { ?>
        <div class="mb-3">
            <label class="form-label">Template</label>
            <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-cubes"></i></span>
            <select class="form-select select2" name="project_template_id" id="projectTemplateSelect">
                <option value="0">None - start empty</option>
                <?php while ($row = mysqli_fetch_assoc($sql_templates)) {
                    $ticket_count = intval($row['ticket_count']);
                    $task_count = intval($row['task_count']); ?>
                    <option value="<?= intval($row['project_template_id']) ?>" data-icon="fas fa-cubes"
                        data-description="<?= escapeHtml(trim("$ticket_count ticket" . ($ticket_count === 1 ? '' : 's') . ($task_count ? ", $task_count task" . ($task_count === 1 ? '' : 's') : '') . ($row['project_template_description'] ? ' - ' . $row['project_template_description'] : ''))) ?>"><?= escapeHtml($row['project_template_name']) ?></option>
                <?php } ?>
            </select>
            </div>
            <div class="form-check mt-2" id="projectAssignToManager" hidden>
                <input class="form-check-input" type="checkbox" name="assign_to_manager" value="1" id="assignToManager" checked>
                <label class="form-check-label small" for="assignToManager">Assign the template's tickets to the manager</label>
            </div>
        </div>
    <?php }
} ?>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label">Manager</label>
        <div class="input-group">
        <span class="input-group-text"><i class="fas fa-fw fa-user-tie"></i></span>
        <select class="form-select select2" name="project_manager">
            <option value="0" data-icon="fas fa-user-slash">No manager</option>
            <?php
            $sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users
                WHERE user_type = 1 AND ((user_status = 1 AND user_archived_at IS NULL) OR user_id = $form_manager_id)
                ORDER BY user_id = " . intval($session_user_id) . " DESC, user_name ASC");
            while ($row = mysqli_fetch_assoc($sql)) {
                $user_id = intval($row['user_id']); ?>
                <option value="<?= $user_id ?>" <?= $user_id === $form_manager_id ? 'selected' : '' ?><?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?><?= $user_id === intval($session_user_id) ? ' (me)' : '' ?></option>
            <?php } ?>
        </select>
        </div>
    </div>
    <div class="col-md-6" id="projectContactField" <?= $form_client_id || ($is_new && !isset($form['fixed_client'])) ? '' : 'hidden' ?>>
        <label class="form-label">Client contact</label>
        <div class="input-group">
        <span class="input-group-text"><i class="fas fa-fw fa-address-card"></i></span>
        <select class="form-select select2" name="contact_id" id="projectContactSelect" data-selected="<?= $form_contact_id ?>">
            <option value="0">Nobody</option>
            <?php if ($form_client_id) {
                $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_photo, contact_primary, contact_technical FROM contacts
                    WHERE contact_client_id = $form_client_id AND (contact_archived_at IS NULL OR contact_id = $form_contact_id)
                    ORDER BY contact_primary DESC, contact_technical DESC, contact_name ASC");
                while ($row = mysqli_fetch_assoc($sql)) {
                    $contact_id = intval($row['contact_id']); ?>
                    <option value="<?= $contact_id ?>" <?= $contact_id === $form_contact_id ? 'selected' : '' ?><?= selectOptionContact($form_client_id, $row['contact_name'], $row['contact_photo'] ?? '', contactOptionDescription($row), $contact_id) ?>><?= escapeHtml($row['contact_name']) ?></option>
                <?php }
            } ?>
        </select>
        </div>
        <div class="form-text">Who signs it off at the client - tickets from a template go to them.</div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <label class="form-label">Start</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-play"></i></span>
            <input type="date" class="form-control" name="start" id="projectStart" value="<?= escapeHtml($form['project_start'] ?? '') ?>">
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Due</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-flag-checkered"></i></span>
            <input type="date" class="form-control" name="due_date" id="projectDue" value="<?= escapeHtml($form['project_due'] ?? '') ?>" min="<?= escapeHtml($form['project_start'] ?? '') ?>">
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Budget</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-hourglass-half"></i></span>
            <input type="number" class="form-control" name="budget_hours" min="0" max="999999" step="0.25" inputmode="decimal" placeholder="None" value="<?= floatval($form['project_budget_hours'] ?? 0) > 0 ? escapeHtml(rtrim(rtrim((string) $form['project_budget_hours'], '0'), '.')) : '' ?>">
            <span class="input-group-text">hours</span>
        </div>
    </div>
</div>

<div class="mb-0">
    <label class="form-label">Scope</label>
    <textarea class="form-control" name="description" rows="5" placeholder="What is in and out of the work, and what &quot;done&quot; looks like"><?= escapeHtml($form['project_description'] ?? '') ?></textarea>
</div>
