<div class="modal" id="addCalendarEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-calendar-plus me-2"></i>New Event</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="pill" href="#pills-event"><i class="fa fa-fw fa-calendar me-2"></i>Event</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="pill" href="#pills-details"><i class="fa fa-fw fa-info-circle me-2"></i>Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="pill" href="#pills-attendees"><i class="fa fa-fw fa-users me-2"></i>Attendees</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-event">

                            <div class="mb-3">
                                <label>Calendar <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                    <select class="form-control select2" name="calendar" required>
                                        <option value="">- Calendar -</option>
                                        <?php

                                        $sql = mysqli_query($mysqli, "SELECT calendar_color, calendar_id, calendar_name FROM calendars ORDER BY calendar_name ASC");
                                        while ($row = mysqli_fetch_assoc($sql)) {
                                            $calendar_id = intval($row['calendar_id']);
                                            $calendar_name = escapeHtml($row['calendar_name']);
                                            $calendar_color = escapeHtml($row['calendar_color']);
                                            ?>
                                            <option <?php if ($config_default_calendar == $calendar_id) { echo "selected"; } ?> data-bs-content="<i class='fa fa-circle me-2' style='color:<?= $calendar_color ?>;'></i> <?= $calendar_name ?>" value="<?= $calendar_id ?>"><?= $calendar_name ?></option>
                                        <?php } ?>

                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label>Title <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                                    <input type="text" class="form-control" name="title" placeholder="Title of the event" maxlength="200" required autofocus>
                                </div>
                            </div>


                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input event-all-day-toggle" id="event_add_all_day" name="all_day" value="1" checked>
                                    <label class="form-check-label" for="event_add_all_day">All day</label>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="mb-3 col-md-6">
                                    <label>Date from <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                                        <input type="date" class="form-control event-start-date" id="event_add_start_date" name="start_date" required>
                                    </div>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label>Date to <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                        <input type="date" class="form-control" id="event_add_end_date" name="end_date" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden while All day is on. The toggle handler in app.js also
                                 adds and removes required, because a hidden required field
                                 blocks submission with an unfocusable-element error. -->
                            <div class="row g-2 d-none" id="event_add_time_fields">
                                <div class="mb-3 col-md-6">
                                    <label>Time from <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                                        <input type="time" class="form-control event-start-time" id="event_add_start_time" name="start_time">
                                    </div>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label>Time to <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                                        <input type="time" class="form-control" id="event_add_end_time" name="end_time">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label>Repeat</label>
                                <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-fw fa-recycle"></i></span>
                                    <select class="form-control select2" name="repeat">
                                        <option value="">Never</option>
                                        <option>Day</option>
                                        <option>Week</option>
                                        <option>Month</option>
                                        <option>Year</option>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-details">

                            <div class="mb-3">
                                <label>Location</label>
                                <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                    <input type="text" class="form-control" name="location" placeholder="Location of the event">
                                </div>
                            </div>

                            <div class="mb-3">
                                <textarea class="form-control" rows="8" name="description" placeholder="Enter a description"></textarea>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-attendees">

                            <?php if (isset($client_id)) { ?>
                                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                            <?php } else{ ?>

                                <div class="mb-3">
                                    <label>Client</label>
                                    <div class="input-group">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        <select class="form-control select2" name="client_id">
                                            <option value="">- Client -</option>
                                            <?php

                                            $sql = mysqli_query($mysqli, "SELECT client_id, client_name, contact_email FROM clients LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1 ORDER BY client_name ASC");
                                            while ($row = mysqli_fetch_assoc($sql)) {
                                                $client_id = intval($row['client_id']);
                                                $client_name = escapeHtml($row['client_name']);
                                                $contact_email = escapeHtml($row['contact_email']);
                                                ?>
                                                <option value="<?= $client_id ?>"><?= $client_name ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            <?php } ?>

                            <?php if (!empty($config_smtp_host)) { ?>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="emailEventCheckbox" name="email_event" value="1" >
                                        <label class="form-check-label" for="emailEventCheckbox">Email Event</label>
                                    </div>
                                </div>
                            <?php } ?>

                        </div>

                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_event" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
