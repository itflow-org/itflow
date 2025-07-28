<div class="modal" id="addCalendarEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-calendar-plus mr-2"></i>New Event</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">

                    <ul class="nav nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="pill" href="#pills-event"><i class="fa fa-fw fa-calendar mr-2"></i>Event</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-details"><i class="fa fa-fw fa-info-circle mr-2"></i>Details</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="pill" href="#pills-attendees"><i class="fa fa-fw fa-users mr-2"></i>Attendees</a>
                        </li>
                    </ul>

                    <hr>

                    <div class="tab-content">

                        <div class="tab-pane fade show active" id="pills-event">

                            <div class="form-group">
                                <label>Title <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="title" placeholder="Title of the event" maxlength="200" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Calendar <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                    </div>
                                    <select class="form-control select2" name="calendar" required>
                                        <option value="">- Calendar -</option>
                                        <?php

                                        $sql = mysqli_query($mysqli, "SELECT * FROM calendars ORDER BY calendar_name ASC");
                                        while ($row = mysqli_fetch_array($sql)) {
                                            $calendar_id = intval($row['calendar_id']);
                                            $calendar_name = nullable_htmlentities($row['calendar_name']);
                                            $calendar_color = nullable_htmlentities($row['calendar_color']);
                                            ?>
                                            <option <?php if ($config_default_calendar == $calendar_id) { echo "selected"; } ?> data-content="<i class='fa fa-circle mr-2' style='color:<?php echo $calendar_color; ?>;'></i> <?php echo $calendar_name; ?>" value="<?php echo $calendar_id; ?>"><?php echo $calendar_name; ?></option>
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
                                    <input type="datetime-local" class="form-control" id="event_add_start" name="start" required onblur="updateIncrementEndTime()">
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                    </div>
                                    <input type="datetime-local" class="form-control" id="event_add_end" name="end" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Repeat</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-recycle"></i></span>
                                    </div>
                                    <select class="form-control select2" name="repeat" disabled>
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

                            <div class="form-group">
                                <label>Location</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="location" placeholder="Location of the event">
                                </div>
                            </div>

                            <div class="form-group">
                                <textarea class="form-control" rows="8" name="description" placeholder="Enter a description"></textarea>
                            </div>

                        </div>

                        <div class="tab-pane fade" id="pills-attendees">

                            <?php if (isset($client_id)) { ?>
                                <input type="hidden" name="client" value="<?php echo $client_id; ?>">
                            <?php } else{ ?>

                                <div class="form-group">
                                    <label>Client</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <select class="form-control select2" name="client">
                                            <option value="">- Client -</option>
                                            <?php

                                            $sql = mysqli_query($mysqli, "SELECT * FROM clients LEFT JOIN contacts ON clients.client_id = contacts.contact_client_id AND contact_primary = 1 ORDER BY client_name ASC");
                                            while ($row = mysqli_fetch_array($sql)) {
                                                $client_id = intval($row['client_id']);
                                                $client_name = nullable_htmlentities($row['client_name']);
                                                $contact_email = nullable_htmlentities($row['contact_email']);
                                                ?>
                                                <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>

                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            <?php } ?>

                            <?php if (!empty($config_smtp_host)) { ?>
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="emailEventCheckbox" name="email_event" value="1" >
                                        <label class="custom-control-label" for="emailEventCheckbox">Email Event</label>
                                    </div>
                                </div>
                            <?php } ?>

                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_event" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
