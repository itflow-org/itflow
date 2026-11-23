<?php

require_once '../../../includes/modal_header.php';

/*
 * New Ticket - who it is for and what it is about, what it is related to,
 * and its tasks. Opened from a client, contact, asset, site, vendor,
 * project or service, it starts with that filled in; picking an asset fills
 * in the person it is assigned to and its site. When the person or the asset
 * already has open tickets, they show under the contact - so a second ticket
 * for the same problem gets noticed before it is raised.
 *
 * Copy of a ticket (copy_ticket_id - its More actions > Copy as a new ticket): it starts with
 * that ticket's subject, details, priority, category, billing, contact, asset, site, vendor and
 * tasks, for the same client - which can be changed, and its lists follow.
 */

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
$contact_id = intval($_GET['contact_id'] ?? 0);
// From a drag over empty time on Dispatch: that agent, booked for that time on the viewer's own
// clock, for that long - and back to Dispatch once it is saved
$prefill_assigned_to = isset($_GET['assigned_to']) && preg_match('/^\d+$/', (string) $_GET['assigned_to']) ? intval($_GET['assigned_to']) : intval($session_user_id);
$prefill_schedule = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) ($_GET['schedule'] ?? '')) ? (string) $_GET['schedule'] : '';
$prefill_schedule_minutes = $prefill_schedule !== '' ? ticketScheduleMinutesValue($_GET['schedule_minutes'] ?? 0) : CALENDAR_TICKET_MINUTES;
$return_to = ($_GET['return_to'] ?? '') === 'dispatch' ? 'dispatch' : '';
$project_id = intval($_GET['project_id'] ?? 0);
$asset_id = intval($_GET['asset_id'] ?? 0);
$location_id = intval($_GET['location_id'] ?? 0);
$vendor_id = intval($_GET['vendor_id'] ?? 0);

// Opened for one template - New ticket from it, on the template's page - it starts filled in
$selected_ticket_template_id = intval($_GET['ticket_template_id'] ?? 0);
$ticket_template_apply_on_load = $selected_ticket_template_id > 0;

// A ticket started from somewhere that knows what it is about (a license's
// Renewal Ticket) - plain text in, escaped here
$prefill_subject = mb_substr(trim((string) ($_GET['subject'] ?? '')), 0, 500);
$prefill_details = trim((string) ($_GET['details'] ?? ''));
$prefill_details_html = $prefill_details !== '' ? '<p>' . nl2br(escapeHtml($prefill_details)) . '</p>' : '';

// From a call's Add to Ticket (functions/phone.php) - the call goes on the ticket it makes
$phone_call_id = intval($_GET['phone_call_id'] ?? 0);
$phone_call = $phone_call_id && function_exists('phoneCallRow') ? phoneCallRow($phone_call_id) : null;
if ($phone_call && phoneCallClientFor($phone_call) && !hasClientAccess($phone_call['phone_call_client_id'])) {
    $phone_call = null;
}

if ($client_id) {
    enforceClientAccess();
}

// A copy of a ticket the agent may reach
$copy = intval($_GET['copy_ticket_id'] ?? 0) ? ticketRow($_GET['copy_ticket_id']) : null;
if ($copy && intval($copy['ticket_client_id']) && !hasClientAccess($copy['ticket_client_id'])) {
    $copy = null;
}
$copy_client_id = 0;
$prefill_priority = 'Medium';
$prefill_category_id = 0;
$prefill_billable = intval($config_ticket_default_billable) === 1;
$existing_tasks = [];
if ($copy) {
    $prefill_subject = (string) $copy['ticket_subject'];
    $prefill_details_html = (string) $copy['ticket_details'];
    $prefill_priority = (string) $copy['ticket_priority'];
    $prefill_category_id = intval($copy['ticket_category']);
    $prefill_billable = intval($copy['ticket_billable']) === 1;
    if (!$client_id) {
        $copy_client_id = intval($copy['ticket_client_id']);
    }
    if ($client_id === intval($copy['ticket_client_id']) || $copy_client_id) {
        $contact_id = $contact_id ?: intval($copy['ticket_contact_id']);
        $asset_id = $asset_id ?: intval($copy['ticket_asset_id']);
        $location_id = $location_id ?: intval($copy['ticket_location_id']);
        $vendor_id = $vendor_id ?: intval($copy['ticket_vendor_id']);
    }
    $sql_copy_tasks = mysqli_query($mysqli, "SELECT task_name, task_completion_estimate FROM tasks WHERE task_ticket_id = " . intval($copy['ticket_id']) . " ORDER BY task_order ASC, task_id ASC");
    while ($task = mysqli_fetch_assoc($sql_copy_tasks)) {
        $existing_tasks[] = ['name' => (string) $task['task_name'], 'estimate' => intval($task['task_completion_estimate'])];
    }
}

// Raised about a service (its page's Report a problem) - one of this client's that is not archived
$service = intval($_GET['service_id'] ?? 0) ? serviceRow($_GET['service_id']) : null;
if ($service && (!$client_id || intval($service['service_client_id']) !== $client_id || $service['service_archived_at'])) {
    $service = null;
}
// ...and starts with what whoever picks it up needs: what it runs on, who to call, how it comes back
if ($service) {
    if ($prefill_subject === '') {
        $prefill_subject = $service['service_name'] . ': ';
    }
    $service_assets = serviceLinked($service['service_id'], 'asset');
    if ($prefill_details === '') {
        $prefill_details = serviceTicketDetails($service, $service_assets, serviceLinked($service['service_id'], 'vendor'));
        $prefill_details_html = '<p>' . nl2br(escapeHtml($prefill_details)) . '</p>';
    }
    // Runs on one thing - that is the asset
    $service_assets = array_values(array_filter($service_assets, function ($row) { return !$row['archived_at']; }));
    if (!$asset_id && count($service_assets) === 1) {
        $asset_id = intval($service_assets[0]['id']);
    }
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-life-ring me-2"></i>New Ticket<?php if ($copy) { ?> <small class="ms-1 opacity-75">copy of <?= escapeHtml(ticketReference($copy)) ?></small><?php } ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($return_to !== '') { ?>
        <input type="hidden" name="return_to" value="<?= $return_to ?>">
    <?php } ?>
    <!-- Hidden/System fields -->
    <?php if ($client_id) { ?>
        <input type="hidden" name="client_id" id="clientIdHidden" value="<?= $client_id ?>">
    <?php } ?>
    <input type="hidden" name="billable" value="0">
    <?php if (function_exists('phonePopKey') && phonePopKey($_GET['phone_pop_call'] ?? '') !== '') { ?>
        <input type="hidden" name="phone_pop_call" value="<?= phonePopKey($_GET['phone_pop_call']) ?>">
    <?php } ?>
    <?php if ($phone_call) { ?>
        <input type="hidden" name="phone_call_id" value="<?= intval($phone_call['phone_call_id']) ?>">
    <?php } ?>

    <div class="modal-body">

        <!-- Nav -->
        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-add-details"><i class="fas fa-fw fa-life-ring me-2"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-add-relationships"><i class="fas fa-fw fa-link me-2"></i>Related</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-add-tasks"><i class="fas fa-fw fa-tasks me-2"></i>Tasks</a>
            </li>
        </ul>

        <!-- Content -->
        <div class="tab-content">

            <!-- Ticket details -->
            <div class="tab-pane fade show active" id="pills-add-details">

                <?php if ($service) { ?>
                    <!-- What it is about - it shows on the service's page -->
                    <div class="d-flex align-items-center gap-2 border rounded px-2 py-2 mb-3 itflow-ticket-service">
                        <input type="hidden" name="service_ids[]" value="<?= intval($service['service_id']) ?>">
                        <?= serviceTile($service['service_category'], 'sm') ?>
                        <div class="min-w-0 flex-grow-1">
                            <div class="small text-body-secondary">About the service</div>
                            <div class="fw-semibold text-truncate"><?= escapeHtml($service['service_name']) ?></div>
                        </div>
                        <?= serviceImportancePill($service['service_importance'], true) ?>
                    </div>
                <?php } ?>

                <!-- Ticket client/contact -->
                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label">Client <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                                <select class="form-select select2" name="client_id" id="changeClientSelect" required <?php if ($client_id) { echo "disabled"; } ?>>
                                    <option value="">- Select a client -</option>
                                    <?php

                                    // Hide leads from the general list, but include the current client when opening a ticket from a lead page.
                                    $selectedClientCondition = intval($client_id) > 0 ? " OR client_id = " . intval($client_id) : "";
                                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL AND (client_lead = 0 $selectedClientCondition) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql)) {
                                        $client_id_select = intval($row['client_id']);
                                        $client_name = escapeHtml($row['client_name']); ?>

                                        <option value="<?= $client_id_select ?>" <?php if (($client_id ?: $copy_client_id) == $client_id_select) {echo "selected"; } ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation']) ?>><?= $client_name ?></option>

                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label">Contact </label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                                <select class="form-select select2" name="contact_id" id="contactSelect" data-selected="<?= $contact_id ?>">
                                    <option value="0">- Nobody -</option>
                                </select>
                                <?= quickAddButton('contact', 0) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Other open tickets for the contact or asset picked - js/tickets_add_modal.js -->
                <div id="ticketAlsoOpen"></div>

                <?php require_once '../../includes/inc_ticket_template_select.php'; ?>

                <div class="mb-3">
                    <label class="form-label">Subject <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-tag"></i></span>
                        <input type="text" class="form-control" id="subjectInput" name="subject" placeholder="Subject" maxlength="500" value="<?= escapeHtml($prefill_subject) ?>" data-template-field="subject" required>
                    </div>
                </div>

                <div class="mb-3">
                    <textarea class="form-control tinymceTicket" id="detailsInput" name="details" data-template-field="details"><?= escapeHtml($prefill_details_html) ?></textarea>
                </div>

                <div class="row">

                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label">Priority <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-thermometer-half"></i></span>
                                <select class="form-select select2" name="priority" data-template-field="priority" required>
                                    <option <?= $prefill_priority === 'Low' ? 'selected' : '' ?> data-color="var(--bs-info)">Low</option>
                                    <option <?= $prefill_priority === 'Medium' || !in_array($prefill_priority, TICKET_PRIORITIES, true) ? 'selected' : '' ?> data-color="var(--bs-warning)">Medium</option>
                                    <option <?= $prefill_priority === 'High' ? 'selected' : '' ?> data-color="var(--bs-danger)">High</option>
                                    <option <?= $prefill_priority === 'Urgent' ? 'selected' : '' ?> data-color="var(--bs-emphasis-color)">Urgent</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-layer-group"></i></span>
                                <select class="form-select select2" name="category_id" data-template-field="category">
                                    <option value="0">- Not categorized -</option>
                                    <?= categoryOptionsHtml('Ticket', $prefill_category_id ?: null) ?>
                                </select>
                                <?= categoryAddButtonHtml('Ticket') ?>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label">Assign to</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-user-check"></i></span>
                                <select class="form-select select2" name="assigned_to" data-template-field="assigned">
                                    <option value="0">- Unassigned -</option>
                                    <?php

                                    $sql = mysqli_query(
                                        $mysqli,
                                        "SELECT user_id, user_name, user_avatar FROM users
                                        WHERE user_type = 1" . serviceAccountsOutSql() . " AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                                    );
                                    while ($row = mysqli_fetch_assoc($sql)) {
                                        $user_id = intval($row['user_id']);
                                        $user_name = escapeHtml($row['user_name']); ?>
                                        <option <?php if ($prefill_assigned_to === $user_id) { echo "selected"; } ?> value="<?= $user_id ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= $user_name ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label">Due</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-calendar-check"></i></span>
                                <input type="datetime-local" class="form-control" name="due" data-template-field="due">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Where it starts - as the ticket's own status menu - and its tags -->
                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-info-circle"></i></span>
                                <select class="form-select select2" name="status">
                                    <option value="0" data-color="var(--bs-secondary-color)">- New, or Open once assigned -</option>
                                    <?php foreach (ticketPickableStatuses() as $status_id => $status) { ?>
                                        <option value="<?= intval($status_id) ?>" data-color="<?= escapeHtml($status['ticket_status_color']) ?>"><?= escapeHtml($status['ticket_status_name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="mb-3">
                            <?= tagFormFieldHtml('ticket', [], 'Onboarding, Hardware, Escalated', 'Tags', 'data-template-field="tags"') ?>
                        </div>
                    </div>
                </div>

                <!-- The work booked, as the ticket's own Schedule window - a row of its own, so the date has room beside how long and where -->
                <div class="mb-3">
                    <label class="form-label">Schedule</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-calendar-day"></i></span>
                        <input type="datetime-local" class="form-control" name="schedule" min="<?= date('Y-m-d\TH:i') ?>" value="<?= escapeHtml($prefill_schedule) ?>" aria-label="Date and time of the work">
                        <select class="form-select select2 flex-grow-0 w-auto" name="schedule_minutes" aria-label="How long">
                            <?php foreach (ticketScheduleLengths($prefill_schedule_minutes) as $minutes => $label) { ?>
                                <option value="<?= intval($minutes) ?>" <?= intval($minutes) === $prefill_schedule_minutes ? 'selected' : '' ?>><?= escapeHtml($label) ?></option>
                            <?php } ?>
                        </select>
                        <select class="form-select select2 flex-grow-0 w-auto" name="onsite" aria-label="Visit">
                            <option value="0">Remote</option>
                            <option value="1">Onsite</option>
                        </select>
                    </div>
                    <div class="form-text">Leave it empty to book the work later.</div>
                </div>

                <?php if ($config_module_enable_accounting) { ?>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="billable" <?php if ($prefill_billable) { echo "checked"; } ?> value="1" id="billable" data-template-field="billable">
                        <label class="form-check-label" for="billable">Mark Billable</label>
                    </div>
                </div>
                <?php } ?>

            </div>

            <div class="tab-pane fade" id="pills-add-tasks">

                <?php require_once '../../includes/inc_ticket_tasks_section.php'; ?>

            </div>

            <div class="tab-pane fade" id="pills-add-relationships">

                <div class="mb-3">
                    <label class="form-label">Project</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-project-diagram"></i></span>
                        <select class="form-select select2" name="project_id" id="projectSelect" data-selected="<?= $project_id ?>">
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Asset</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-desktop"></i></span>
                        <select class="form-select select2" name="asset_id" id="assetSelect" data-selected="<?= $asset_id ?>">
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Other assets</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-desktop"></i></span>
                        <select class="form-select select2" name="additional_assets[]" id="additionalAssetsSelect" data-placeholder="- Select additional assets -" multiple>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Site</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-map-marker-alt"></i></span>
                        <select class="form-select select2" name="location_id" id="locationSelect" data-selected="<?= $location_id ?>">
                        </select>
                        <?= quickAddButton('location', 0) ?>
                    </div>
                </div>

                <div class="row">

                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label">Vendor</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-building"></i></span>
                                <select class="form-select select2" name="vendor_id" id="vendorSelect" data-selected="<?= $vendor_id ?>">
                                </select>
                                <?= quickAddButton('vendor', 0) ?>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label">Their reference</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-hashtag"></i></span>
                                <input type="text" class="form-control" name="vendor_ticket_number" placeholder="The vendor's case number" maxlength="255">
                            </div>
                        </div>
                    </div>

                </div>

                <div class="mb-3">
                    <label class="form-label">Watchers</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                        <select class="form-select select2" name="watchers[]" id="watchersSelect" data-tags="true" data-placeholder="Type a name or an email address" multiple>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-fw fa-paperclip me-1"></i>Attachments</label>
                    <input type="file" class="form-control" name="attachments[]" multiple accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .md, .doc, .docx, .odt, .csv, .xls, .xlsx, .ods, .pptx, .odp, .zip, .tar, .gz, .xml, .msg, .json, .wav, .mp3, .ogg, .mov, .mp4, .av1, .ovpn">
                </div>

            </div>

        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_ticket" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>

</form>


<!-- Ticket Client/Contact JS -->
<script src="<?= staticFileUrl('/agent/js/tickets_add_modal.js') ?>"></script>

<script src="<?= staticFileUrl('/agent/js/ticket_tasks_modal.js') ?>"></script>

<?php

require_once '../../../includes/modal_footer.php';
