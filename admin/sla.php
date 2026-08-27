<?php

require_once "includes/inc_all_admin.php";

// Current SLA / business hours settings
$sql_sla_settings = mysqli_query($mysqli, "SELECT config_business_days, config_business_hours_start, config_business_hours_end, config_sla_warning_percent, config_sla_notification_email FROM settings WHERE company_id = 1");
$row = mysqli_fetch_assoc($sql_sla_settings);
$config_business_days = explode(',', $row['config_business_days']);
$config_business_hours_start = substr($row['config_business_hours_start'], 0, 5);
$config_business_hours_end = substr($row['config_business_hours_end'], 0, 5);
$config_sla_warning_percent = intval($row['config_sla_warning_percent']);
$config_sla_notification_email = escapeHtml($row['config_sla_notification_email']);

// SLA plans (active first)
$sql_slas = mysqli_query($mysqli, "SELECT sla_archived_at, sla_description, sla_id, sla_name, sla_resolution_minutes,
    sla_response_minutes FROM slas ORDER BY sla_archived_at IS NOT NULL, sla_name ASC");

// Active plans for the assignment dropdowns
$active_slas = [];
$sql_active_slas = mysqli_query($mysqli, "SELECT sla_id, sla_name FROM slas WHERE sla_archived_at IS NULL ORDER BY sla_name ASC");
while ($active_sla_row = mysqli_fetch_assoc($sql_active_slas)) {
    $active_slas[intval($active_sla_row['sla_id'])] = $active_sla_row['sla_name'];
}

// Closure days, newest first - past ones are kept as a record of what was applied
$holidays = [];
$sql_holidays = mysqli_query($mysqli, "SELECT holiday_date, holiday_id, holiday_name FROM business_holidays ORDER BY holiday_date DESC");
while ($holiday_row = mysqli_fetch_assoc($sql_holidays)) {
    $holidays[] = $holiday_row;
}

$holiday_year = intval(date('Y'));

// Global default assignments: [priority] = sla_id (per-client overrides live on the client edit modal)
$assignments = [];
$sql_assignments = mysqli_query($mysqli, "SELECT sla_assignment_priority, sla_assignment_sla_id FROM sla_assignments WHERE sla_assignment_client_id = 0");
while ($assignment_row = mysqli_fetch_assoc($sql_assignments)) {
    $assignments[0][$assignment_row['sla_assignment_priority']] = intval($assignment_row['sla_assignment_sla_id']);
}


?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-stopwatch me-2"></i>SLAs</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/sla/sla_add.php"><i class="fas fa-plus me-2"></i>New SLA</button>
        </div>
    </div>

    <div class="card-body">

        <p class="text-muted">SLAs are optional. Tickets only get response/resolution targets when an assignment below matches their client and priority - with nothing assigned, nothing changes.</p>

        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark">
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Response</th>
                    <th>Resolution</th>
                    <th>Status</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($sla_row = mysqli_fetch_assoc($sql_slas)) {

                    $sla_id = intval($sla_row['sla_id']);
                    $sla_name = escapeHtml($sla_row['sla_name']);
                    $sla_description = escapeHtml($sla_row['sla_description']);
                    $sla_response_minutes = intval($sla_row['sla_response_minutes']);
                    $sla_resolution_minutes = intval($sla_row['sla_resolution_minutes']);
                    $sla_archived_at = $sla_row['sla_archived_at'];

                    ?>
                    <tr>
                        <td>
                            <a href="#" class="ajax-modal" data-modal-url="modals/sla/sla_edit.php?id=<?= $sla_id ?>">
                                <?= $sla_name ?>
                            </a>
                        </td>
                        <td><?= $sla_description ?></td>
                        <td><?= $sla_response_minutes ?> min</td>
                        <td><?= $sla_resolution_minutes ? "$sla_resolution_minutes min" : "-" ?></td>
                        <td>
                            <?php if ($sla_archived_at) { ?>
                                <div class="text-secondary">Archived</div>
                            <?php } else { ?>
                                <div class="text-success text-bold">Active</div>
                            <?php } ?>
                        </td>
                        <td>
                            <div class="dropdown dropstart text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/sla/sla_edit.php?id=<?= $sla_id ?>">
                                        <i class="fas fa-fw fa-edit me-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($sla_archived_at) { ?>
                                        <a class="dropdown-item" href="post.php?unarchive_sla=<?= $sla_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-undo me-2"></i>Restore
                                        </a>
                                    <?php } else { ?>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?archive_sla=<?= $sla_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-archive me-2"></i>Archive
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-random me-2"></i>SLA Assignments</h3>
    </div>

    <div class="card-body">

        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <strong>Default (all clients)</strong>
            <div class="row g-2 mt-2">
                <?php foreach (['Low', 'Medium', 'High', 'Urgent'] as $priority) {
                    $selected_sla_id = $assignments[0][$priority] ?? 0;
                    ?>
                    <div class="mb-3 col-md-3">
                        <label><?= $priority ?></label>
                        <select class="form-select" name="global_sla_<?= strtolower($priority) ?>">
                            <option value="0">None</option>
                            <?php foreach ($active_slas as $active_sla_id => $active_sla_name) { ?>
                                <option value="<?= $active_sla_id ?>" <?php if ($selected_sla_id == $active_sla_id) { echo "selected"; } ?>><?= escapeHtml($active_sla_name) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
            </div>
            <button type="submit" name="save_sla_assignments" class="btn btn-primary"><i class="fas fa-check me-2"></i>Save Defaults</button>
        </form>

        <small class="text-muted d-block mt-2">Per-client overrides are set on each client (edit client, Notes tab).</small>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-business-time me-2"></i>Business Hours &amp; Notifications</h3>
    </div>

    <div class="card-body">

        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <label>Business days</label>
                <div>
                    <?php foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $day_number => $day_name) { ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="business_days[]" value="<?= $day_number ?>" id="business_day_<?= $day_number ?>" <?php if (in_array($day_number, $config_business_days)) { echo "checked"; } ?>>
                            <label class="form-check-label" for="business_day_<?= $day_number ?>"><?= $day_name ?></label>
                        </div>
                    <?php } ?>
                </div>
                <small class="text-muted">SLA clocks only run during business hours on these days. Unchecking everything makes SLAs count 24x7.</small>
            </div>

            <div class="row g-2">
                <div class="mb-3 col-md-3">
                    <label>Business hours start</label>
                    <input type="time" class="form-control" name="business_hours_start" value="<?= $config_business_hours_start ?>" required>
                </div>
                <div class="mb-3 col-md-3">
                    <label>Business hours end</label>
                    <input type="time" class="form-control" name="business_hours_end" value="<?= $config_business_hours_end ?>" required>
                </div>
                <div class="mb-3 col-md-3">
                    <label>Warn at % of target</label>
                    <input type="number" class="form-control" name="warning_percent" min="1" max="99" value="<?= $config_sla_warning_percent ?>" required>
                </div>
                <div class="mb-3 col-md-3">
                    <label>Notification email <small class="text-muted">(optional)</small></label>
                    <input type="email" class="form-control" name="notification_email" placeholder="dispatch@example.com" maxlength="200" value="<?= $config_sla_notification_email ?>">
                </div>
            </div>

            <small class="text-muted d-block mb-3">Warnings and breach alerts also notify the assigned agent in-app and by email. Requires cron/ticket_sla.php running every minute.</small>

            <button type="submit" name="edit_sla_settings" class="btn btn-primary"><i class="fas fa-check me-2"></i>Save Settings</button>
        </form>

    </div>
</div>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-calendar-times me-2"></i>Holidays &amp; Closure Days</h3>
        <div class="card-tools">
            <form action="post.php" method="post" class="d-inline" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="input-group input-group-sm d-inline-flex w-auto align-middle me-2">
                    <input type="number" class="form-control" name="holiday_year" min="2000" max="2100" value="<?= $holiday_year ?>" required>
                    <button type="submit" name="generate_holidays" class="btn btn-secondary"><i class="fas fa-magic me-2"></i>Add US Holidays</button>
                </div>
            </form>
            <button type="button" class="btn btn-sm btn-primary ajax-modal" data-modal-url="modals/sla/holiday_add.php"><i class="fas fa-plus me-2"></i>New Closure Day</button>
        </div>
    </div>

    <div class="card-body">

        <p class="text-muted">
            SLA clocks stop completely on these dates, the same as a day outside your business days.
            Use them for public holidays or any other day the office is shut. Adding or removing one
            recalculates the targets on every open ticket.
        </p>

        <?php if (empty($holidays)) { ?>
            <p class="text-secondary mb-0">No closure days configured - SLA clocks run on every business day.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-borderless table-hover align-middle">
                    <thead class="border-bottom">
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Name</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($holidays as $holiday) {
                            $holiday_id = intval($holiday['holiday_id']);
                            $holiday_date_raw = $holiday['holiday_date'];
                            $holiday_date = escapeHtml(date('M j, Y', strtotime($holiday_date_raw)));
                            $holiday_day = escapeHtml(date('l', strtotime($holiday_date_raw)));
                            $holiday_name = escapeHtml($holiday['holiday_name']);
                            $holiday_is_past = strtotime($holiday_date_raw) < strtotime(date('Y-m-d'));
                        ?>
                            <tr class="<?php if ($holiday_is_past) { echo "text-secondary"; } ?>">
                                <td><?= $holiday_date ?></td>
                                <td><?= $holiday_day ?></td>
                                <td><?= $holiday_name ?></td>
                                <td class="text-end">
                                    <form action="post.php" method="post" onsubmit="return confirm('Remove this closure day? Open ticket targets will be recalculated.');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="holiday_id" value="<?= $holiday_id ?>">
                                        <button type="submit" name="delete_holiday" class="btn btn-sm btn-light border"><i class="fas fa-fw fa-trash text-danger"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

    </div>
</div>

<?php require_once "../includes/footer.php";
