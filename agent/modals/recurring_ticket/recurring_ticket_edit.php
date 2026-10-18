<?php

/*
 * Edit Recurring Ticket - the same tabs as New Recurring Ticket. The
 * Schedule tab shows the next few runs as it stands, and a paused one says
 * so. What it links to now stays on offer even once archived, so saving
 * doesn't drop it.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$recurring_ticket_id = intval($_GET['id'] ?? 0);
$recurring = recurringTicketRow($recurring_ticket_id);
if (!$recurring) {
    echo json_encode(['error' => 'That recurring ticket no longer exists.']);
    exit;
}
$client_id = intval($recurring['recurring_ticket_client_id']);
if ($client_id) {
    enforceClientAccess();
}

$contact_id = intval($recurring['recurring_ticket_contact_id']);
$asset_id = intval($recurring['recurring_ticket_asset_id']);
$assigned_to = intval($recurring['recurring_ticket_assigned_to']);
$category_id = intval($recurring['recurring_ticket_category']);
$billable = intval($recurring['recurring_ticket_billable']);
$frequency = recurringTicketFrequency($recurring['recurring_ticket_frequency']);
$next_run = substr((string) $recurring['recurring_ticket_next_run'], 0, 10);
$paused = recurringTicketPaused($recurring);
$upcoming = recurringTicketUpcoming($recurring, 4);

// Tasks already on this schedule, pre-filling the editable rows
$existing_tasks = [];
$sql_tasks = mysqli_query($mysqli, "SELECT recurring_ticket_task_name, recurring_ticket_task_completion_estimate FROM recurring_ticket_tasks
    WHERE recurring_ticket_task_recurring_ticket_id = $recurring_ticket_id ORDER BY recurring_ticket_task_order ASC, recurring_ticket_task_id ASC");
while ($row = mysqli_fetch_assoc($sql_tasks)) {
    $existing_tasks[] = ['name' => $row['recurring_ticket_task_name'], 'estimate' => intval($row['recurring_ticket_task_completion_estimate'])];
}

$other_asset_ids = [];
$sql_other_assets = mysqli_query($mysqli, "SELECT asset_id FROM recurring_ticket_assets WHERE recurring_ticket_id = $recurring_ticket_id");
while ($row = mysqli_fetch_row($sql_other_assets)) {
    $other_asset_ids[] = intval($row[0]);
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-redo-alt me-2"></i>Edit Recurring Ticket <small class="ms-1 opacity-75"><?= escapeHtml($recurring['client_name']) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="recurring_ticket_id" value="<?= $recurring_ticket_id ?>">

    <div class="modal-body">

        <?php if ($paused) { ?>
            <div class="alert alert-secondary d-flex align-items-center gap-2 py-2">
                <i class="fas fa-pause"></i>
                <div class="flex-grow-1">Paused <?= timeAgo($recurring['recurring_ticket_paused_at']) ?> - it raises nothing until it is resumed.</div>
                <a class="btn btn-sm btn-light" href="post.php?resume_recurring_ticket=<?= $recurring_ticket_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-play me-1"></i>Resume</a>
            </div>
        <?php } ?>

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-recurring-details"><i class="fa fa-fw fa-life-ring me-2"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-recurring-schedule"><i class="fa fa-fw fa-calendar-alt me-2"></i>Schedule</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-recurring-related"><i class="fa fa-fw fa-link me-2"></i>Related</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-recurring-tasks"><i class="fa fa-fw fa-tasks me-2"></i>Tasks</a>
            </li>
        </ul>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-recurring-details">

                <div class="mb-3">
                    <label>For</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        <select class="form-select select2" name="contact_id">
                            <option value="0">- No contact -</option>
                            <?php
                            $sql_contacts = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_client_id, contact_photo, contact_title, contact_primary, contact_technical, contact_archived_at
                                FROM contacts WHERE contact_client_id = $client_id AND (contact_archived_at IS NULL OR contact_id = $contact_id)
                                ORDER BY contact_primary DESC, contact_technical DESC, contact_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_contacts)) {
                                $contact_id_select = intval($row['contact_id']); ?>
                                <option value="<?= $contact_id_select ?>" <?= $contact_id_select === $contact_id ? 'selected' : '' ?><?= selectOptionContact($row['contact_client_id'], $row['contact_name'], $row['contact_photo'] ?? '', contactOptionDescription($row), $row['contact_id']) ?>><?= escapeHtml($row['contact_name']) ?><?= $row['contact_archived_at'] ? ' (archived)' : '' ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <?php
                $selected_ticket_template_id = intval($recurring['recurring_ticket_ticket_template_id']);
                require_once '../../includes/inc_ticket_template_select.php';
                ?>

                <div class="mb-3">
                    <label>Subject <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        <input type="text" class="form-control" id="subjectInput" name="subject" placeholder="Subject" maxlength="500" value="<?= escapeHtml($recurring['recurring_ticket_subject']) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <textarea class="form-control tinymceTicket" id="detailsInput" name="details"><?= escapeHtml($recurring['recurring_ticket_details']) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Priority <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                <select class="form-select select2" name="priority" required>
                                    <?php foreach (['Low' => 'var(--bs-info)', 'Medium' => 'var(--bs-warning)', 'High' => 'var(--bs-danger)', 'Urgent' => 'var(--bs-emphasis-color)'] as $priority => $color) { ?>
                                        <option <?= $recurring['recurring_ticket_priority'] === $priority ? 'selected' : '' ?> data-color="<?= $color ?>"><?= $priority ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Category</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                                <select class="form-select select2" name="category_id">
                                    <option value="0">- Not categorized -</option>
                                    <?= categoryOptionsHtml('Ticket', $category_id) ?>
                                </select>
                                <?= categoryAddButtonHtml('Ticket', 'btn btn-secondary') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Assign to</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                        <select class="form-select select2" name="assigned_to">
                            <option value="0">- Unassigned -</option>
                            <?php
                            $sql_agents = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users
                                WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_agents)) {
                                $user_id = intval($row['user_id']); ?>
                                <option <?= $assigned_to === $user_id ? 'selected' : '' ?> value="<?= $user_id ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?><?= $user_id === intval($session_user_id) ? ' (me)' : '' ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <?php if ($config_module_enable_accounting && lookupUserPermission('module_sales') >= 2) { ?>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="recurringBillable<?= $recurring_ticket_id ?>" name="billable" <?= $billable ? 'checked' : '' ?> value="1">
                        <label class="form-check-label" for="recurringBillable<?= $recurring_ticket_id ?>">The tickets it raises are billable</label>
                    </div>
                <?php } ?>

            </div>

            <div class="tab-pane fade" id="pills-recurring-schedule">

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>How often <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-redo-alt"></i></span>
                                <select class="form-select select2" name="frequency" required>
                                    <?php foreach (RECURRING_TICKET_FREQUENCIES as $option => $step) { ?>
                                        <option <?= $frequency === $option ? 'selected' : '' ?>><?= $option ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Next run <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                                <input class="form-control" type="date" name="next_date" min="<?= min($next_run, date('Y-m-d')) ?>" max="2999-12-31" value="<?= escapeHtml($next_run) ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="small text-body-secondary">
                    <div class="mb-1"><?= recurringTicketNextRunPill($recurring) ?></div>
                    <?php if ($upcoming && !$paused) { ?>
                        <div><i class="far fa-calendar me-1"></i>Then <?= escapeHtml(implode(', ', array_map(function ($date) { return date('M j, Y', strtotime($date)); }, array_slice($upcoming, 1)))) ?> - as it stands now.</div>
                    <?php } ?>
                    <div class="mt-1"><?= escapeHtml(ucfirst(recurringTicketCadenceText($recurring))) ?>. Moving the next run moves the day it keeps to.</div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-recurring-related">

                <div class="mb-3">
                    <label>Asset</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        <select class="form-select select2" name="asset_id">
                            <option value="0">- None -</option>
                            <?php
                            $assets = [];
                            $sql_assets = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_archived_at, contact_name FROM assets
                                LEFT JOIN contacts ON contact_id = asset_contact_id
                                WHERE asset_client_id = $client_id AND (asset_archived_at IS NULL OR asset_id IN (" . implode(',', array_merge([0, $asset_id], $other_asset_ids)) . "))
                                ORDER BY asset_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_assets)) {
                                $assets[] = $row;
                            }
                            foreach ($assets as $row) {
                                $asset_id_select = intval($row['asset_id']); ?>
                                <option <?= $asset_id === $asset_id_select ? 'selected' : '' ?> value="<?= $asset_id_select ?>"<?= selectOptionAsset($row['asset_type'] ?? '', trim(($row['asset_make'] ?? '') . ' ' . ($row['asset_model'] ?? '') . ($row['contact_name'] ? ' - ' . $row['contact_name'] : ''))) ?>><?= escapeHtml($row['asset_name']) ?><?= $row['asset_archived_at'] ? ' (archived)' : '' ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Other assets</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        <select class="form-select select2" name="additional_assets[]" data-placeholder="- Any other assets involved -" multiple>
                            <?php foreach ($assets as $row) {
                                $asset_id_select = intval($row['asset_id']); ?>
                                <option value="<?= $asset_id_select ?>" <?= in_array($asset_id_select, $other_asset_ids, true) ? 'selected' : '' ?><?= selectOptionAsset($row['asset_type'] ?? '', trim(($row['asset_make'] ?? '') . ' ' . ($row['asset_model'] ?? ''))) ?>><?= escapeHtml($row['asset_name']) ?><?= $row['asset_archived_at'] ? ' (archived)' : '' ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-recurring-tasks">

                <?php require_once '../../includes/inc_ticket_tasks_section.php'; ?>

            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_recurring_ticket" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save changes</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script src="/agent/js/ticket_tasks_modal.js"></script>

<?php
require_once '../../../includes/modal_footer.php';
