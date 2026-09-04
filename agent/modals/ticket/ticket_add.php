<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$contact_id = intval($_GET['contact_id'] ?? 0);
$project_id = intval($_GET['project_id'] ?? 0);
$asset_id = intval($_GET['asset_id'] ?? 0);

if ($client_id) {
    enforceClientAccess();
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
                <a class="nav-link" data-bs-toggle="pill" href="#pills-add-tasks"><i class="fa fa-fw fa-tasks me-2"></i>Tasks</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-add-relationships"><i class="fa fa-fw fa-desktop me-2"></i>Assignment</a>
            </li>
        </ul>

        <!-- Content -->
        <div class="tab-content">

            <!-- Ticket details -->
            <div class="tab-pane fade show active" id="pills-add-details">

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
                                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL AND (client_lead = 0 $selectedClientCondition) " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql)) {
                                        $client_id_select = intval($row['client_id']);
                                        $client_name = escapeHtml($row['client_name']); ?>

                                        <option value="<?= $client_id_select ?>" <?php if ($client_id == $client_id_select) {echo "selected"; } ?>><?= $client_name ?></option>

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

                <?php require_once '../../includes/inc_ticket_template_select.php'; ?>

                <div class="mb-3">
                    <label>Subject <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        <input type="text" class="form-control" id="subjectInput" name="subject" placeholder="Subject" maxlength="500" required>
                    </div>
                </div>

                <div class="mb-3">
                    <textarea class="form-control tinymceTicket" id="detailsInput" name="details"></textarea>
                </div>

                <div class="row">

                    <div class="col">
                        <div class="mb-3">
                            <label>Priority <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                <select class="form-select select2" name="priority" required>
                                    <option>Low</option>
                                    <option selected>Medium</option>
                                    <option>High</option>
                                    <option>Urgent</option>
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
                                    $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_categories)) {
                                        $category_id = intval($row['category_id']);
                                        $category_name = escapeHtml($row['category_name']);
                                        ?>
                                        <option value="<?= $category_id ?>"><?= $category_name ?></option>
                                    <?php } ?>

                                </select>
                                    <button class="btn btn-secondary ajax-modal" type="button"
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
                                        "SELECT user_id, user_name FROM users
                                        WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC"
                                    );
                                    while ($row = mysqli_fetch_assoc($sql)) {
                                        $user_id = intval($row['user_id']);
                                        $user_name = escapeHtml($row['user_name']); ?>
                                        <option <?php if ($session_user_id == $user_id) { echo "selected"; } ?> value="<?= $user_id ?>"><?= $user_name ?></option>
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
                    <label>Additional Assets</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        <select class="form-select select2" name="additional_assets[]" id="additionalAssetsSelect" data-placeholder="- Select Additional Assets -" multiple>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Location</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        <select class="form-select select2" name="location_id" id="locationSelect">
                        </select>
                    </div>
                </div>

                <div class="row">

                    <div class="col">
                        <div class="mb-3">
                            <label>Vendor</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                <select class="form-select select2" name="vendor_id" id="vendorSelect">
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col">
                        <div class="mb-3">
                            <label>Vendor Ticket Number</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                <input type="text" class="form-control" name="vendor_ticket_number" placeholder="Vendor ticket number" maxlength="255">
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
