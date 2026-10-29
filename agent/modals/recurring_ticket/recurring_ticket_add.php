<?php

require_once '../../../includes/modal_header.php';

/*
 * New Recurring Ticket - what the tickets it raises will say, how often and
 * from when, what they are about and their tasks. Opened from a client,
 * contact or asset, it starts with that filled in; picking an asset fills
 * in the person it is assigned to.
 */

enforceUserPermission('module_support', 2);

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id) {
    enforceClientAccess();
}
$contact_id = intval($_GET['contact_id'] ?? 0);
$asset_id = intval($_GET['asset_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-calendar-check me-2"></i>New Recurring Ticket</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($client_id) { ?>
        <input type="hidden" name="client_id" id="clientIdHidden" value="<?= $client_id ?>">
    <?php } ?>
    <input type="hidden" name="billable" value="0">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-add-details"><i class="fa fa-fw fa-life-ring me-2"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-add-schedule"><i class="fa fa-fw fa-calendar-alt me-2"></i>Schedule</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-add-assets"><i class="fa fa-fw fa-link me-2"></i>Related</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-add-tasks"><i class="fa fa-fw fa-tasks me-2"></i>Tasks</a>
            </li>
        </ul>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-add-details">

                <?php if ($contact_id) { ?>
                <input type="hidden" name="contact_id" value="<?= $contact_id ?>">
                <?php } else { ?>
                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label>Client <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                <select class="form-select select2" name="client_id" id="changeClientSelect" required <?php if ($client_id) { echo "disabled"; } ?>>
                                    <option value="">- Select a Client -</option>
                                    <?php

                                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql)) {
                                        $client_id_select = intval($row['client_id']);
                                        $client_name = escapeHtml($row['client_name']); ?>

                                        <option value="<?= $client_id_select ?>" <?php if ($client_id == $client_id_select) {echo "selected"; } ?><?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= $client_name ?></option>

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
                                <select class="form-select select2" name="contact_id" id="contactSelect">
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <?php } ?>

                <?php
                $ticket_template_hint = "Picking one fills in the recurring ticket - change anything before it is saved. <span class=\"font-monospace\">[Client]</span>, <span class=\"font-monospace\">[Month]</span> and the like are filled in each time it raises a ticket, and what the template asks for is answered once here.";
                require_once '../../includes/inc_ticket_template_select.php';
                ?>

                <div class="mb-3">
                    <label>Subject <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        <input type="text" class="form-control" id="subjectInput" name="subject" data-template-field="subject" placeholder="Subject" maxlength="500" required>
                    </div>
                </div>

                <div class="mb-3">
                    <textarea class="form-control tinymceTicket" id="detailsInput" name="details" data-template-field="details"></textarea>
                </div>

                <div class="row">

                    <div class="col">
                        <div class="mb-3">
                            <label>Priority <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                                <select class="form-select select2" name="priority" data-template-field="priority" required>
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
                                <select class="form-select select2" name="category_id" data-template-field="category">
                                    <option value="0">- Not Categorized -</option>
                                    <?= categoryOptionsHtml('Ticket') ?>
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
                        <select class="form-select select2" name="assigned_to" data-template-field="assigned">
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
                                <option value="<?= $user_id ?>"<?= selectOptionUser($row['user_id'], $row['user_name'], $row['user_avatar'] ?? '') ?>><?= $user_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <?php if ($config_module_enable_accounting && lookupUserPermission('module_sales') >= 2) { ?>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="billable" <?php if ($config_ticket_default_billable == 1) { echo "checked"; } ?> value="1" id="billable" data-template-field="billable">
                        <label class="form-check-label" for="billable">The tickets it raises are billable</label>
                    </div>
                </div>
                <?php } ?>

            </div>

            <div class="tab-pane fade" id="pills-add-tasks">

                <?php require_once '../../includes/inc_ticket_tasks_section.php'; ?>

            </div>

            <div class="tab-pane fade" id="pills-add-schedule">

                <div class="mb-3">
                    <label>Frequency <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-recycle"></i></span>
                        <select class="form-select select2" name="frequency" required>
                            <option value="">- Select Frequency -</option>
                            <optgroup label="Days">
                                <option>Three Days</option>
                                <option>Weekly</option>
                                <option>Biweekly</option>
                            </optgroup>
                            <optgroup label="Months">
                                <option>Monthly</option>
                                <option>Quarterly</option>
                                <option>Biannually</option>
                                <option>Annually</option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>First run <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                        <input class="form-control" type="date" name="start_date" min="<?= date("Y-m-d") ?>" max="2999-12-31" value="<?= date("Y-m-d", strtotime('+1 day')) ?>" required>
                    </div>
                    <div class="form-text">The nightly cron raises a ticket on this day, then again each time it comes round. Monthly ones keep to this day of the month - the last day, in a shorter month.</div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-add-assets">

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
                        <select class="form-select select2" name="additional_assets[]" id="additionalAssetsSelect" data-placeholder="- Any other assets involved -" multiple>
                        </select>
                    </div>
                </div>

                <?php if (!$client_id) { ?>
                <small class="form-text text-muted">Select a client on the Details tab to load its assets.</small>
                <?php } ?>

            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_recurring_ticket" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Create Recurring Ticket</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>


<!-- Recurring Ticket Client/Contact JS -->
<script src="/agent/js/tickets_add_modal.js"></script>

<script src="/agent/js/ticket_tasks_modal.js"></script>

<?php

require_once '../../../includes/modal_footer.php';
