<?php

require_once '../../../includes/modal_header.php';

/*
 * New Ticket - who it is for and what it is about, what it is related to,
 * and its tasks. Opened from a client, contact, asset, site, vendor,
 * project or service, it starts with that filled in; picking an asset fills
 * in the person it is assigned to and its site. When the person or the asset
 * already has open tickets, they show under the contact - so a second ticket
 * for the same problem gets noticed before it is raised.
 */

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
$contact_id = intval($_GET['contact_id'] ?? 0);
$project_id = intval($_GET['project_id'] ?? 0);
$asset_id = intval($_GET['asset_id'] ?? 0);
$location_id = intval($_GET['location_id'] ?? 0);
$vendor_id = intval($_GET['vendor_id'] ?? 0);

// A ticket started from somewhere that knows what it is about (a license's
// Renewal Ticket) - plain text in, escaped here
$prefill_subject = mb_substr(trim((string) ($_GET['subject'] ?? '')), 0, 500);
$prefill_details = trim((string) ($_GET['details'] ?? ''));
$prefill_details_html = $prefill_details !== '' ? '<p>' . nl2br(escapeHtml($prefill_details)) . '</p>' : '';

if ($client_id) {
    enforceClientAccess();
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
    <h5 class="modal-title"><i class="fas fa-fw fa-life-ring me-2"></i>New Ticket</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <!-- Hidden/System fields -->
    <?php if ($client_id) { ?>
        <input type="hidden" name="client_id" id="clientIdHidden" value="<?= $client_id ?>">
    <?php } ?>
    <input type="hidden" name="billable" value="0">

    <div class="modal-body">

        <!-- Nav -->
        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-add-details"><i class="fa fa-fw fa-life-ring me-2"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-add-relationships"><i class="fa fa-fw fa-link me-2"></i>Related</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-add-tasks"><i class="fa fa-fw fa-tasks me-2"></i>Tasks</a>
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
                            <label>Client <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                <select class="form-select select2" name="client_id" id="changeClientSelect" required <?php if ($client_id) { echo "disabled"; } ?>>
                                    <option value="">- Select a Client -</option>
                                    <?php

                                    // Hide leads from the general list, but include the current client when opening a ticket from a lead page.
                                    $selectedClientCondition = intval($client_id) > 0 ? " OR client_id = " . intval($client_id) : "";
                                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL AND (client_lead = 0 $selectedClientCondition) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql)) {
                                        $client_id_select = intval($row['client_id']);
                                        $client_name = escapeHtml($row['client_name']); ?>

                                        <option value="<?= $client_id_select ?>" <?php if ($client_id == $client_id_select) {echo "selected"; } ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation']) ?>><?= $client_name ?></option>

                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="mb-3">
                            <label>Contact </label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                <select class="form-select select2" name="contact_id" id="contactSelect" data-selected="<?= $contact_id ?>">
                                    <option value="0">- No One -</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Other open tickets for the contact or asset picked - js/tickets_add_modal.js -->
                <div id="ticketAlsoOpen"></div>

                <?php require_once '../../includes/inc_ticket_template_select.php'; ?>

                <div class="mb-3">
                    <label>Subject <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        <input type="text" class="form-control" id="subjectInput" name="subject" placeholder="Subject" maxlength="500" value="<?= escapeHtml($prefill_subject) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <textarea class="form-control tinymceTicket" id="detailsInput" name="details"><?= escapeHtml($prefill_details_html) ?></textarea>
                </div>

                <div class="row">

                    <div class="col">
                        <div class="mb-3">
                            <label>Priority <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                <select class="form-select select2" name="priority" required>
                                    <option data-color="var(--bs-info)">Low</option>
                                    <option selected data-color="var(--bs-warning)">Medium</option>
                                    <option data-color="var(--bs-danger)">High</option>
                                    <option data-color="var(--bs-emphasis-color)">Urgent</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="mb-3">
                            <label>Category</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                                <select class="form-select select2" name="category_id">
                                    <option value="0">- Not Categorized -</option>
                                    <?php
                                    $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name, category_color, category_icon FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_categories)) {
                                        $category_id = intval($row['category_id']);
                                        $category_name = escapeHtml($row['category_name']);
                                        ?>
                                        <option value="<?= $category_id ?>"<?= selectOptionColor($row['category_color'] ?? '', $row['category_icon'] ?? '') ?>><?= $category_name ?></option>
                                    <?php } ?>

                                </select>
                                    <button class="btn btn-secondary ajax-modal" title="New category" type="button"
                                            data-modal-url="../admin/modals/category/category_add.php?category=Ticket">
                                        <i class="fas fa-fw fa-plus"></i>
                                    </button>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label>Assign to</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                                <select class="form-select select2" name="assigned_to">
                                    <option value="0">- Unassigned -</option>
                                    <?php

                                    $sql = mysqli_query(
                                        $mysqli,
                                        "SELECT user_id, user_name, user_avatar FROM users
                                        WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                                    );
                                    while ($row = mysqli_fetch_assoc($sql)) {
                                        $user_id = intval($row['user_id']);
                                        $user_name = escapeHtml($row['user_name']); ?>
                                        <option <?php if ($session_user_id == $user_id) { echo "selected"; } ?> value="<?= $user_id ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= $user_name ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="mb-3">
                            <label>Due</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                                <input type="datetime-local" class="form-control" name="due">
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($config_module_enable_accounting) { ?>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="billable" <?php if ($config_ticket_default_billable == 1) { echo "checked"; } ?> value="1" id="billable">
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
                    <label>Project</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                        <select class="form-select select2" name="project_id" id="projectSelect" data-selected="<?= $project_id ?>">
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Asset</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        <select class="form-select select2" name="asset_id" id="assetSelect" data-selected="<?= $asset_id ?>">
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Other assets</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        <select class="form-select select2" name="additional_assets[]" id="additionalAssetsSelect" data-placeholder="- Select Additional Assets -" multiple>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Site</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        <select class="form-select select2" name="location_id" id="locationSelect" data-selected="<?= $location_id ?>">
                        </select>
                    </div>
                </div>

                <div class="row">

                    <div class="col">
                        <div class="mb-3">
                            <label>Vendor</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                <select class="form-select select2" name="vendor_id" id="vendorSelect" data-selected="<?= $vendor_id ?>">
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="mb-3">
                            <label>Their reference</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
                                <input type="text" class="form-control" name="vendor_ticket_number" placeholder="The vendor's case number" maxlength="255">
                            </div>
                        </div>
                    </div>

                </div>

                <div class="mb-3">
                    <label>Watchers</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        <select class="form-select select2" name="watchers[]" id="watchersSelect" data-tags="true" data-placeholder="Enter or select email address" multiple>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label><i class="fa fa-fw fa-paperclip me-1"></i>Attachments</label>
                    <input type="file" class="form-control" name="attachments[]" multiple accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .md, .doc, .docx, .odt, .csv, .xls, .xlsx, .ods, .pptx, .odp, .zip, .tar, .gz, .xml, .msg, .json, .wav, .mp3, .ogg, .mov, .mp4, .av1, .ovpn">
                </div>

            </div>

        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="add_ticket" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create Ticket</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>

</form>


<!-- Ticket Client/Contact JS -->
<script src="/agent/js/tickets_add_modal.js"></script>

<script src="/agent/js/ticket_tasks_modal.js"></script>

<?php

require_once '../../../includes/modal_footer.php';
