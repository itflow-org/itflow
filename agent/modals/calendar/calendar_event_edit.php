<?php

require_once '../../../includes/modal_header.php';

$event_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT calendar_color, calendar_id, calendar_name, event_all_day, event_client_id,
    event_description, event_end, event_location, event_repeat, event_start, event_title FROM calendar_events LEFT JOIN calendars ON event_calendar_id = calendar_id WHERE event_id = $event_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$event_title = escapeHtml($row['event_title']);
$event_description = escapeHtml($row['event_description']);
$event_location = escapeHtml($row['event_location']);
$event_start = escapeHtml($row['event_start']);
$event_end = escapeHtml($row['event_end']);
$event_repeat = escapeHtml($row['event_repeat']);
$event_all_day = intval($row['event_all_day'] ?? 0);

// Split the stored datetimes into the four fields the form now uses. An empty
// event_end previously fed strtotime('') and rendered as 1970 - fall back to the
// start instead.
$event_start_ts = strtotime($event_start) ?: time();
$event_end_ts = !empty($row['event_end']) ? (strtotime($event_end) ?: $event_start_ts) : $event_start_ts;

$event_start_date = date('Y-m-d', $event_start_ts);
$event_start_time = date('H:i', $event_start_ts);
$event_end_date = date('Y-m-d', $event_end_ts);
$event_end_time = date('H:i', $event_end_ts);
$calendar_id = intval($row['calendar_id']);
$calendar_name = escapeHtml($row['calendar_name']);
$calendar_color = escapeHtml($row['calendar_color']);
$client_id = intval($row['event_client_id']);

if ($client_id) {
    enforceClientAccess();
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-calendar-check mr-2" style="color:<?= $calendar_color ?>"></i>Editing: <strong><?= $event_title ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="event_id" value="<?= $event_id ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-event<?= $event_id ?>"><i class="fa fa-fw fa-calendar-check mr-2"></i>Event</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-details<?= $event_id ?>"><i class="fa fa-fw fa-info-circle mr-2"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-attendees<?= $event_id ?>"><i class="fa fa-fw fa-users mr-2"></i>Attendees</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-event<?= $event_id ?>">

                <div class="form-group">
                    <label>Calendar <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                        </div>
                        <select class="form-control select2" name="calendar" required>
                            <?php

                            $sql_calendars_select = mysqli_query($mysqli, "SELECT calendar_color, calendar_id, calendar_name FROM calendars ORDER BY calendar_name ASC");
                            while ($row = mysqli_fetch_assoc($sql_calendars_select)) {
                                $calendar_id_select = intval($row['calendar_id']);
                                $calendar_name_select = escapeHtml($row['calendar_name']);
                                $calendar_color_select = escapeHtml($row['calendar_color']);
                                ?>
                                <option data-content="<i class='fa fa-circle mr-2' style='color:<?= $calendar_color_select ?>;'></i> <?= $calendar_name_select ?>"<?php if ($calendar_id == $calendar_id_select) { echo "selected"; } ?> value="<?= $calendar_id_select ?>"><?= $calendar_name_select ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Title <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                        </div>
                        <input type="text" class="form-control" name="title" maxlength="200" value="<?= $event_title ?>" placeholder="Title of the event" required>
                    </div>
                </div>

                <?php if (!empty($event_repeat)) { ?>
                    <div class="alert alert-info">
                        <i class="fas fa-fw fa-redo mr-2"></i>
                        This event repeats <strong>every <?= strtolower($event_repeat) ?></strong>.
                        Saving or deleting affects <strong>every occurrence</strong> &mdash; a single
                        occurrence cannot be moved or cancelled on its own.
                    </div>
                <?php } ?>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input event-all-day-toggle" id="event_edit_all_day" name="all_day" value="1" <?php if ($event_all_day) { echo "checked"; } ?>>
                        <label class="custom-control-label" for="event_edit_all_day">All day</label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Date from <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                            </div>
                            <input type="date" class="form-control event-start-date" id="event_edit_start_date" name="start_date" value="<?= $event_start_date ?>" required>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Date to <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                            </div>
                            <input type="date" class="form-control" id="event_edit_end_date" name="end_date" value="<?= $event_end_date ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-row<?= $event_all_day ? ' d-none' : '' ?>" id="event_edit_time_fields">
                    <div class="form-group col-md-6">
                        <label>Time from <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                            </div>
                            <input type="time" class="form-control event-start-time" id="event_edit_start_time" name="start_time" value="<?= $event_start_time ?>"<?= $event_all_day ? '' : ' required' ?>>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Time to <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                            </div>
                            <input type="time" class="form-control" id="event_edit_end_time" name="end_time" value="<?= $event_end_time ?>"<?= $event_all_day ? '' : ' required' ?>>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Repeat</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-recycle"></i></span>
                        </div>
                        <select class="form-control select2" name="repeat">
                            <option <?php if (empty($event_repeat)) { echo "selected"; } ?> value="">Never</option>
                            <option <?php if ($event_repeat == "Day") { echo "selected"; } ?>>Day</option>
                            <option <?php if ($event_repeat == "Week") { echo "selected"; } ?>>Week</option>
                            <option <?php if ($event_repeat == "Month") { echo "selected"; } ?>>Month</option>
                            <option <?php if ($event_repeat == "Year") { echo "selected"; } ?>>Year</option>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-details<?= $event_id ?>">
                <div class="form-group">
                    <label>Location</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        </div>
                        <input type="text" class="form-control" name="location" value="<?= $event_location ?>" placeholder="Location of the event">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" rows="8" name="description" placeholder="Enter a description"><?= $event_description ?></textarea>
                </div>


            </div>

            <div class="tab-pane fade" id="pills-attendees<?= $event_id ?>">

                <?php if (isset($_GET['client_id'])) { ?>
                    <input type="hidden" name="client_id" value="<?= $client_id ?>">
                <?php } else { ?>

                    <div class="form-group">
                        <label>Client</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <select class="form-control select2" name="client">
                                <option value="">- Client -</option>
                                <?php

                                $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name, contact_email FROM clients LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1 ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_assoc($sql_clients)) {
                                    $client_id_select = intval($row['client_id']);
                                    $client_name_select = escapeHtml($row['client_name']);
                                    $contact_email_select = escapeHtml($row['contact_email']);
                                    ?>
                                    <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?= $client_id_select ?>"><?= $client_name_select ?></option>

                                <?php } ?>

                            </select>
                        </div>
                    </div>

                <?php } ?>

                <?php if (!empty($config_smtp_host)) { ?>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="customControlAutosizing<?= $event_id ?>" name="email_event" value="1" >
                        <label class="custom-control-label" for="customControlAutosizing<?= $event_id ?>">Email Event</label>
                    </div>
                <?php } ?>

            </div>

        </div>

    </div>
    <div class="modal-footer">
        <a class="btn btn-default text-danger mr-auto confirm-link" href="post.php?delete_event=<?= $event_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fa fa-calendar-times mr-2"></i><?= empty($event_repeat) ? 'Delete' : 'Delete series' ?></a>
        <button type="submit" name="edit_event" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
