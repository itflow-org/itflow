<?php

require_once '../../includes/modal_header.php';

$event_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM calendar_events LEFT JOIN calendars ON event_calendar_id = calendar_id WHERE event_id = $event_id LIMIT 1");
                     
$row = mysqli_fetch_array($sql);
$event_title = nullable_htmlentities($row['event_title']);
$event_description = nullable_htmlentities($row['event_description']);
$event_location = nullable_htmlentities($row['event_location']);
$event_start = nullable_htmlentities($row['event_start']);
$event_end = nullable_htmlentities($row['event_end']);
$event_repeat = nullable_htmlentities($row['event_repeat']);
$calendar_id = intval($row['calendar_id']);
$calendar_name = nullable_htmlentities($row['calendar_name']);
$calendar_color = nullable_htmlentities($row['calendar_color']);
$client_id = intval($row['event_client_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-calendar mr-2" style="color:<?php echo $calendar_color; ?>"></i><?php echo $event_title; ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-event<?php echo $event_id; ?>"><i class="fa fa-fw fa-calendar mr-2"></i>Event</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-details<?php echo $event_id; ?>"><i class="fa fa-fw fa-info-circle mr-2"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-attendees<?php echo $event_id; ?>"><i class="fa fa-fw fa-users mr-2"></i>Attendees</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-event<?php echo $event_id; ?>">

                <div class="form-group">
                    <label>Title <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                        </div>
                        <input type="text" class="form-control" name="title" maxlength="200" value="<?php echo $event_title; ?>" placeholder="Title of the event" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Calendar <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                        </div>
                        <select class="form-control select2" name="calendar" required>
                            <?php

                            $sql_calendars_select = mysqli_query($mysqli, "SELECT * FROM calendars ORDER BY calendar_name ASC");
                            while ($row = mysqli_fetch_array($sql_calendars_select)) {
                                $calendar_id_select = intval($row['calendar_id']);
                                $calendar_name_select = nullable_htmlentities($row['calendar_name']);
                                $calendar_color_select = nullable_htmlentities($row['calendar_color']);
                                ?>
                                <option data-content="<i class='fa fa-circle mr-2' style='color:<?php echo $calendar_color_select; ?>;'></i> <?php echo $calendar_name_select; ?>"<?php if ($calendar_id == $calendar_id_select) { echo "selected"; } ?> value="<?php echo $calendar_id_select; ?>"><?php echo $calendar_name_select; ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Start / End <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                        </div>
                        <input type="datetime-local" class="form-control" name="start" value="<?php echo date('Y-m-d\TH:i:s', strtotime($event_start)); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                        </div>
                        <input type="datetime-local" class="form-control" name="end" value="<?php echo date('Y-m-d\TH:i:s', strtotime($event_end)); ?>"required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Repeat</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-recycle"></i></span>
                        </div>
                        <select class="form-control select2" name="repeat" disabled>
                            <option <?php if (empty($event_repeat)) { echo "selected"; } ?> value="">Never</option>
                            <option <?php if ($event_repeat == "Day") { echo "selected"; } ?>>Day</option>
                            <option <?php if ($event_repeat == "Week") { echo "selected"; } ?>>Week</option>
                            <option <?php if ($event_repeat == "Month") { echo "selected"; } ?>>Month</option>
                            <option <?php if ($event_repeat == "Year") { echo "selected"; } ?>>Year</option>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-details<?php echo $event_id; ?>">
                <div class="form-group">
                    <label>Location</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        </div>
                        <input type="text" class="form-control" name="location" value="<?php echo $event_location; ?>" placeholder="Location of the event">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" rows="8" name="description" placeholder="Enter a description"><?php echo $event_description; ?></textarea>
                </div>


            </div>

            <div class="tab-pane fade" id="pills-attendees<?php echo $event_id; ?>">

                <?php if (isset($_GET['client_id'])) { ?>
                    <input type="hidden" name="client" value="<?php echo $client_id; ?>">
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

                                $sql_clients = mysqli_query($mysqli, "SELECT * FROM clients LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1 ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_array($sql_clients)) {
                                    $client_id_select = intval($row['client_id']);
                                    $client_name_select = nullable_htmlentities($row['client_name']);
                                    $contact_email_select = nullable_htmlentities($row['contact_email']);
                                    ?>
                                    <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?php echo $client_id_select; ?>"><?php echo $client_name_select; ?></option>

                                <?php } ?>

                            </select>
                        </div>
                    </div>

                <?php } ?>

                <?php if (!empty($config_smtp_host)) { ?>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="customControlAutosizing<?php echo $event_id; ?>" name="email_event" value="1" >
                        <label class="custom-control-label" for="customControlAutosizing<?php echo $event_id; ?>">Email Event</label>
                    </div>
                <?php } ?>

            </div>

        </div>

    </div>
    <div class="modal-footer">
        <a class="btn btn-default text-danger mr-auto" href="post.php?delete_event=<?php echo $event_id; ?>"><i class="fa fa-calendar-times mr-2"></i>Delete</a>
        <button type="submit" name="edit_event" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../includes/modal_footer.php';
