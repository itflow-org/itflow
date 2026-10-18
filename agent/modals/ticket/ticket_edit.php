<?php

/*
 * Edit Ticket - the same two tabs as New Ticket: what it is (with who it is
 * for), and what it is related to. A ticket with no client is internal
 * work, so it has no contact, asset or site - only our own vendors and
 * internal projects. Whatever it links to now stays on offer even when that
 * has since been archived or completed, so saving doesn't drop it.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$ticket_id = intval($_GET['id'] ?? 0);
$ticket = ticketRow($ticket_id);
if (!$ticket) {
    echo json_encode(['error' => 'That ticket no longer exists.']);
    exit;
}
$client_id = intval($ticket['ticket_client_id']);
if ($client_id) {
    enforceClientAccess();
}
if (!empty($ticket['ticket_closed_at'])) {
    echo json_encode(['error' => 'Ticket ' . ticketReference($ticket) . ' is closed - a closed ticket stays as it was.']);
    exit;
}

$contact_id = intval($ticket['ticket_contact_id']);
$asset_id = intval($ticket['ticket_asset_id']);
$location_id = intval($ticket['ticket_location_id']);
$vendor_id = intval($ticket['ticket_vendor_id']);
$project_id = intval($ticket['ticket_project_id']);
$ticket_assigned_to = intval($ticket['ticket_assigned_to']);
$ticket_category = intval($ticket['ticket_category']);
$ticket_billable = intval($ticket['ticket_billable']);
$ticket_due_at = $ticket['ticket_due_at'] ? date('Y-m-d\TH:i', strtotime($ticket['ticket_due_at'])) : '';

$other_asset_ids = [];
$sql_other_assets = mysqli_query($mysqli, "SELECT asset_id FROM ticket_assets WHERE ticket_id = $ticket_id");
while ($row = mysqli_fetch_row($sql_other_assets)) {
    $other_asset_ids[] = intval($row[0]);
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-life-ring me-2"></i>Edit Ticket <small class="ms-1 opacity-75 font-monospace"><?= escapeHtml(ticketReference($ticket)) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-edit-details"><i class="fa fa-fw fa-life-ring me-2"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-edit-related"><i class="fa fa-fw fa-link me-2"></i>Related</a>
            </li>
        </ul>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-edit-details">

                <div class="mb-3">
                    <label>For</label>
                    <?php if ($client_id) { ?>
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
                        <div class="form-text"><?= escapeHtml($ticket['client_name']) ?></div>
                        <?php if (!empty($config_smtp_provider)) { ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="contact_notify" value="1" id="checkNotifyContact<?= $ticket_id ?>">
                                <label class="form-check-label" for="checkNotifyContact<?= $ticket_id ?>">Email the contact a link to this ticket</label>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="form-control-plaintext text-body-secondary"><i class="fas fa-building me-2"></i>Internal - no client</div>
                    <?php } ?>
                </div>

                <div class="mb-3">
                    <label>Subject <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        <input type="text" class="form-control" name="subject" maxlength="500" value="<?= escapeHtml($ticket['ticket_subject']) ?>" placeholder="Subject" required>
                    </div>
                </div>

                <div class="mb-3">
                    <textarea class="form-control tinymceTicket" rows="8" name="details"><?= escapeHtml($ticket['ticket_details']) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Priority <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                <select class="form-select select2" name="priority" required>
                                    <?php foreach (['Low' => 'var(--bs-info)', 'Medium' => 'var(--bs-warning)', 'High' => 'var(--bs-danger)', 'Urgent' => 'var(--bs-emphasis-color)'] as $priority => $color) { ?>
                                        <option <?= $ticket['ticket_priority'] === $priority ? 'selected' : '' ?> data-color="<?= $color ?>"><?= $priority ?></option>
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
                                    <?= categoryOptionsHtml('Ticket', $ticket_category) ?>
                                </select>
                                <?= categoryAddButtonHtml('Ticket', 'btn btn-secondary') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Assigned to</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                                <select class="form-select select2" name="assigned_to">
                                    <option value="0">- Unassigned -</option>
                                    <?php
                                    $sql_agents = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users
                                        WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_agents)) {
                                        $user_id = intval($row['user_id']); ?>
                                        <option <?= $ticket_assigned_to === $user_id ? 'selected' : '' ?> value="<?= $user_id ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= escapeHtml($row['user_name']) ?><?= $user_id === intval($session_user_id) ? ' (me)' : '' ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Due</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                                <input type="datetime-local" class="form-control" name="due" value="<?= escapeHtml($ticket_due_at) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <?= tagFormFieldHtml('ticket', tagIdsForRecord('ticket', $ticket_id), 'Onboarding, Hardware, Escalated') ?>
                </div>

                <?php if ($config_module_enable_accounting && lookupUserPermission("module_sales") >= 2) { ?>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="billable" <?= $ticket_billable ? 'checked' : '' ?> value="1" id="billableSwitch<?= $ticket_id ?>">
                        <label class="form-check-label" for="billableSwitch<?= $ticket_id ?>">Billable</label>
                    </div>
                <?php } else { ?>
                    <!-- No switch for this agent - the current value goes through untouched -->
                    <input type="hidden" name="billable" value="<?= $ticket_billable ?>">
                <?php } ?>

            </div>

            <div class="tab-pane fade" id="pills-edit-related">

                <?php if ($client_id) { ?>

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
                                    WHERE asset_client_id = $client_id AND (asset_archived_at IS NULL OR asset_id = $asset_id OR asset_id IN (0" . ($other_asset_ids ? ',' . implode(',', $other_asset_ids) : '') . "))
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

                    <div class="mb-3">
                        <label>Site</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                            <select class="form-select select2" name="location_id">
                                <option value="0">- None -</option>
                                <?php
                                $sql_locations = mysqli_query($mysqli, "SELECT location_id, location_name, location_archived_at FROM locations
                                    WHERE location_client_id = $client_id AND (location_archived_at IS NULL OR location_id = $location_id) ORDER BY location_primary DESC, location_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_locations)) {
                                    $location_id_select = intval($row['location_id']); ?>
                                    <option <?= $location_id === $location_id_select ? 'selected' : '' ?> value="<?= $location_id_select ?>" data-icon="fas fa-map-marker-alt"><?= escapeHtml($row['location_name']) ?><?= $row['location_archived_at'] ? ' (archived)' : '' ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                <?php } ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Vendor</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                <select class="form-select select2" name="vendor_id">
                                    <option value="0">- None -</option>
                                    <?php
                                    $sql_vendors = mysqli_query($mysqli, "SELECT vendor_id, vendor_name, vendor_description, vendor_archived_at FROM vendors
                                        WHERE vendor_client_id = $client_id AND (vendor_archived_at IS NULL OR vendor_id = $vendor_id)
                                        ORDER BY vendor_favorite DESC, vendor_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_vendors)) {
                                        $vendor_id_select = intval($row['vendor_id']); ?>
                                        <option <?= $vendor_id === $vendor_id_select ? 'selected' : '' ?> value="<?= $vendor_id_select ?>"<?= selectOptionVendor($row['vendor_name'], $row['vendor_description']) ?>><?= escapeHtml($row['vendor_name']) ?><?= $row['vendor_archived_at'] ? ' (archived)' : '' ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Their reference</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
                                <input type="text" class="form-control" name="vendor_ticket_number" placeholder="The vendor's case number" maxlength="255" value="<?= escapeHtml($ticket['ticket_vendor_ticket_number']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Project</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                        <select class="form-select select2" name="project_id">
                            <option value="0">- None -</option>
                            <?php
                            $sql_projects = mysqli_query($mysqli, "SELECT project_id, project_prefix, project_number, project_name, project_client_id, project_completed_at, project_archived_at FROM projects
                                WHERE project_client_id IN (0, $client_id) AND ((project_completed_at IS NULL AND project_archived_at IS NULL) OR project_id = $project_id)
                                ORDER BY project_client_id DESC, project_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_projects)) {
                                $project_id_select = intval($row['project_id']);
                                $project_state = $row['project_archived_at'] ? ' (archived)' : ($row['project_completed_at'] ? ' (completed)' : ''); ?>
                                <option <?= $project_id === $project_id_select ? 'selected' : '' ?> value="<?= $project_id_select ?>" data-icon="fas fa-project-diagram" data-description="<?= escapeHtml($row['project_prefix'] . intval($row['project_number']) . (intval($row['project_client_id']) ? '' : ' - internal')) ?>"><?= escapeHtml($row['project_name']) ?><?= $project_state ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_ticket" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save changes</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<?php
require_once '../../../includes/modal_footer.php';
