<?php

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_event_query = "WHERE event_client_id = $client_id";
    $client_query = "WHERE 1 = 1 AND client_id = $client_id";
    $client_url = "&client_id=$client_id";
} else {
    require_once "includes/inc_all.php";
    $client_event_query = '';
    $client_query = 'WHERE 1 = 1';
    $client_url = '';
}

if (isset($_GET['calendar_id'])) {
    $calendar_selected_id = intval($_GET['calendar_id']);
}

?>

<!-- So that when hovering over a created event it turns into a hand instead of cursor -->
<style>
    .fc-event {
        cursor: pointer;
    }
</style>

<div class="row">

    <div class="col-md-3 d-print-none">
        <div class="card">
            <div class="card-header bg-dark">
                <h3 class="card-title">Calendars</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool ajax-modal" data-modal-url="modals/calendar/calendar_add.php"><i class="fas fa-plus" title="New Calendar"></i></button>
                </div>
            </div>
            <div class="card-body">
                <?php
                $sql = mysqli_query($mysqli, "SELECT calendar_color, calendar_feed_key, calendar_id, calendar_name FROM calendars");
                while ($row = mysqli_fetch_assoc($sql)) {
                    $calendar_id = intval($row['calendar_id']);
                    $calendar_name = escapeHtml($row['calendar_name']);
                    $calendar_color = escapeHtml($row['calendar_color']);
                    $calendar_feed_key = escapeHtml($row['calendar_feed_key'] ?? null);
                ?>
                <div class="form-group d-flex align-items-center">
                    <i class="fas fa-fw fa-circle mr-2" style="color:<?= $calendar_color ?>;"></i><?= $calendar_name ?>
                    <?php if (!empty($calendar_feed_key)) { ?>
                        <i class="fas fa-fw fa-share-alt text-info ml-2" title="Published as a subscription link"></i>
                    <?php } ?>

                    <div class="dropdown dropright ml-auto">
                        <button class="btn btn-tool" type="button" data-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item ajax-modal" href="#"
                                data-modal-url="modals/calendar/calendar_edit.php?id=<?= $calendar_id ?>">
                                <i class="fas fa-fw fa-pencil-alt mr-2"></i>Rename
                            </a>
                            <?php if ($session_is_admin) { ?>
                                <a class="dropdown-item ajax-modal" href="#"
                                    data-modal-url="modals/calendar/calendar_share.php?id=<?= $calendar_id ?>">
                                    <i class="fas fa-fw fa-share-alt mr-2"></i><?= empty($calendar_feed_key) ? 'Share' : 'Manage sharing' ?>
                                </a>
                            <?php } ?>
                            <?php if ($session_user_role == 3) { ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_calendar=<?= $calendar_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                    <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php
                }
                ?>

            </div>
        </div>
        <div class="card">
            <div class="card-header bg-dark">
                <h3 class="card-title">Built-in</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <i class="fas fa-fw fa-circle mr-2" style="color:blue;"></i>Invoices
                </div>

                <div class="form-group">
                    <i class="fas fa-fw fa-circle mr-2" style="color:purple;"></i>Quotes
                </div>

                <div class="form-group">
                    <i class="fas fa-fw fa-circle mr-2" style="color:red;"></i>Tickets (Created)
                </div>

                <div class="form-group">
                    <i class="fas fa-fw fa-circle mr-2" style="color:grey;"></i>Recurring Tickets
                </div>

                <div class="form-group">
                    <i class="fas fa-fw fa-circle mr-2" style="color:grey;"></i>Tickets (Scheduled)
                </div>

                <div class="form-group">
                    <i class="fas fa-fw fa-circle mr-2" style="color:brown;"></i>Vendors
                </div>

                <?php if (!isset($_GET['client_id'])) { ?>

                <div class="form-group">
                    <i class="fas fa-fw fa-circle mr-2" style="color:brown;"></i>Clients
                </div>

                <?php } ?>

            </div>
        </div>
    </div>

    <div class="col-md-9">
        <div class="card">
            <div id='calendar'></div>
        </div>
    </div>

</div>

<?php

require_once "modals/calendar/calendar_event_add.php";

//loop through IDs and create a modal for each
$sql = mysqli_query($mysqli, "SELECT calendar_color, calendar_id, calendar_name, event_client_id, event_description, event_end,
    event_id, event_location, event_repeat, event_start, event_title FROM calendar_events LEFT JOIN calendars ON event_calendar_id = calendar_id $client_event_query");
while ($row = mysqli_fetch_assoc($sql)) {
    $event_id = intval($row['event_id']);
    $event_title = escapeHtml($row['event_title']);
    $event_description = escapeHtml($row['event_description']);
    $event_location = escapeHtml($row['event_location']);
    $event_start = escapeHtml($row['event_start']);
    $event_end = escapeHtml($row['event_end']);
    $event_repeat = escapeHtml($row['event_repeat']);
    $calendar_id = intval($row['calendar_id']);
    $calendar_name = escapeHtml($row['calendar_name']);
    $calendar_color = escapeHtml($row['calendar_color']);
    $client_id = intval($row['event_client_id']);
}

?>

<?php require_once "../includes/footer.php";
?>

<!-- FullCalendar v7: theme + CSS are now separate plugins, must be loaded alongside the core bundle -->
<link href='/libs/fullcalendar/skeleton.css' rel='stylesheet' />
<link href='/libs/fullcalendar/themes/classic/theme.css' rel='stylesheet' />
<link href='/libs/fullcalendar/themes/classic/palette.css' rel='stylesheet' />
<script src='/libs/fullcalendar/fullcalendar.global.js'></script>
<script src='/libs/fullcalendar/themes/classic/global.js'></script>

<script>
    // Local-time formatters for the date and datetime-local inputs.
    // Date.toISOString() would convert to UTC and silently shift the event by the
    // browser's offset.
    function formatLocalDate(date) {
        const pad = (n) => String(n).padStart(2, "0");
        return date.getFullYear() + "-" + pad(date.getMonth() + 1) + "-" + pad(date.getDate());
    }

    function formatLocalTime(date) {
        const pad = (n) => String(n).padStart(2, "0");
        return pad(date.getHours()) + ":" + pad(date.getMinutes());
    }

    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            buttons: {
                newEvent: {
                    text: 'New Event',
                    iconClass: 'fas fa-plus',
                    click: function() {
                        // Reset to the all-day default; without this the modal keeps
                        // whatever state the last calendar selection left it in
                        const allDayToggle = document.getElementById("event_add_all_day");
                        if (allDayToggle) {
                            allDayToggle.checked = true;
                            $(allDayToggle).trigger("change");
                        }
                        $("#addCalendarEventModal").modal();
                    }
                }
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth newEvent'
            },
            <?php if (!$session_mobile) {
            ?>aspectRatio: 2.5,
        <?php } else { ?>
            aspectRatio: 0.7,
        <?php } ?>
        navLinks: true, // can click day/week names to navigate views
        selectable: true,
        height: '90vh',

        selectMirror: true,
        eventDidMount: function(info) {
            // Always show full title when hovering
            info.el.setAttribute('title', info.event.title);

            // Mark occurrences of a repeating event, so a series is recognisable
            // without opening it. Every occurrence carries the parent event id, so
            // this is the only cue that a click will edit the whole series.
            const repeat = info.event.extendedProps.repeat;
            if (repeat) {
                info.el.setAttribute('title', info.event.title + ' (repeats every ' + repeat.toLowerCase() + ')');

                const titleEl = info.el.querySelector('.fc-event-title') || info.el.querySelector('.fc-list-event-title');
                if (titleEl) {
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-redo fa-xs mr-1';
                    titleEl.prepend(icon);
                }
            }
        },
        // Clicking - or dragging across - empty calendar space opens the New Event
        // modal prefilled with whatever was selected. Month view yields an all-day
        // range; the time grid views yield a slot range.
        select: function(selectionInfo) {

            const allDayToggle = document.getElementById("event_add_all_day");
            const startDate = document.getElementById("event_add_start_date");
            const endDate = document.getElementById("event_add_end_date");
            const startTime = document.getElementById("event_add_start_time");
            const endTime = document.getElementById("event_add_end_time");

            if (!startDate || !endDate) {
                return;
            }

            // FullCalendar reports an exclusive end for an all-day range, but the form
            // asks for the last day the event covers - step back a day so a single-day
            // click does not come back as a two-day event. A time-grid selection is not
            // exclusive in the same way, so its end date is used as-is (a slot running
            // to midnight legitimately ends on the next day).
            const lastDay = new Date(selectionInfo.end.getTime());
            if (selectionInfo.allDay) {
                lastDay.setDate(lastDay.getDate() - 1);
            }

            startDate.value = formatLocalDate(selectionInfo.start);
            endDate.value = formatLocalDate(lastDay);

            // A time-grid selection carries a real time; a month-view click does not,
            // so its time fields are left for the user to fill in if they uncheck
            if (!selectionInfo.allDay && startTime && endTime) {
                startTime.value = formatLocalTime(selectionInfo.start);
                endTime.value = formatLocalTime(selectionInfo.end);
            }

            // Last, so the handler in app.js shows or hides the time row to match
            if (allDayToggle) {
                allDayToggle.checked = selectionInfo.allDay;
                $(allDayToggle).trigger("change");
            }

            calendar.unselect();
            $("#addCalendarEventModal").modal();
        },
        eventClick: function(editEvent) {
            var eventId = editEvent.event.id;
            var $link = $('<a>', {
                href: '#',
                'class': 'ajax-modal',
                'data-modal-url': 'modals/calendar/calendar_event_edit.php?<?= $client_url ?>&id=' + eventId
            });

            $('body').append($link); // Append to the body
            $link.trigger('click');  // Trigger the modal
            $link.remove(); // Cleanup
        },
        dayMaxEvents: true, // allow "more" link when too many events
        views: {
            timeGrid: {
                dayMaxEventRows: 3, // adjust to 6 only for timeGridWeek/timeGridDay
                expandRows: true,
                nowIndicator: true,
                eventMaxStack: 1,
            },
            dayGrid: {
                dayMaxEvents: 3, // adjust to 6 only for timeGridWeek/timeGridDay
                expandRows: true,
            },

        },
        events: [
            <?php
            $sql = mysqli_query($mysqli, "SELECT calendar_color, calendar_id, calendar_name, event_all_day, event_end, event_id,
                event_repeat, event_start, event_title FROM calendar_events LEFT JOIN calendars ON event_calendar_id = calendar_id $client_event_query");

            // Repeating events are stored as a single row, so the occurrences have to
            // be materialised here - the bundled FullCalendar build has no rrule
            // plugin. Every occurrence keeps the parent event_id, so clicking any of
            // them opens the series in the edit modal.
            $recur_window_start = date('Y-m-d H:i:s', strtotime('-6 months'));
            $recur_window_end = date('Y-m-d H:i:s', strtotime('+18 months'));

            while ($row = mysqli_fetch_assoc($sql)) {
                $event_id = intval($row['event_id']);
                $event_title = json_encode($row['event_title']);
                $calendar_id = intval($row['calendar_id']);
                $calendar_name = json_encode($row['calendar_name']);
                $calendar_color = json_encode($row['calendar_color']);
                $event_is_all_day = !empty($row['event_all_day'] ?? 0);
                $event_all_day = $event_is_all_day ? 'true' : 'false';
                $event_repeat = json_encode($row['event_repeat'] ?? '');

                foreach (expandRecurringEvent($row, $recur_window_start, $recur_window_end) as $occurrence) {

                    $occurrence_end = $occurrence['end'];

                    // event_end holds the last day an all-day event covers, but
                    // FullCalendar's all-day end is exclusive - without this the final
                    // day of a multi-day event is not drawn
                    if ($event_is_all_day && !empty($occurrence_end)) {
                        $occurrence_end = date('Y-m-d H:i:s', strtotime($occurrence_end . ' +1 day'));
                    }

                    $event_start = json_encode($occurrence['start']);
                    $event_end = json_encode($occurrence_end);

                    echo "{ id: $event_id, title: $event_title, start: $event_start, end: $event_end, allDay: $event_all_day, color: $calendar_color, extendedProps: { repeat: $event_repeat } },";
                }
            }

            // Invoices Created
            $sql = mysqli_query($mysqli, "SELECT invoice_date, invoice_id, invoice_number, invoice_prefix, invoice_scope FROM clients LEFT JOIN invoices ON client_id = invoice_client_id $client_query " . clientScopeSql('clients.client_id') . "");
            while ($row = mysqli_fetch_assoc($sql)) {
                $event_id = intval($row['invoice_id']);
                $scope = strval($row['invoice_scope']);
                if (empty($scope)) {
                    $scope = "Not Set";
                }
                $event_title = json_encode($row['invoice_prefix'] . $row['invoice_number'] . " created -scope: " . $scope);
                $event_start = json_encode($row['invoice_date']);


                echo "{ id: $event_id, title: $event_title, start: $event_start, display: 'list-item', color: 'blue', url: 'invoice.php?invoice_id=$event_id$client_url' },";
            }

            // Quotes Created
            $sql = mysqli_query($mysqli, "SELECT quote_date, quote_id, quote_number, quote_prefix, quote_scope FROM clients LEFT JOIN quotes ON client_id = quote_client_id $client_query " . clientScopeSql('clients.client_id') . "");
            while ($row = mysqli_fetch_assoc($sql)) {
                $event_id = intval($row['quote_id']);
                $event_title = json_encode($row['quote_prefix'] . $row['quote_number'] . " " . $row['quote_scope']);
                $event_start = json_encode($row['quote_date']);

                echo "{ id: $event_id, title: $event_title, start: $event_start, display: 'list-item', color: 'purple', url: 'quote.php?quote_id=$event_id$client_url' },";
            }

            // Tickets Created
            $sql = mysqli_query($mysqli, "SELECT ticket_created_at, ticket_id, ticket_number, ticket_prefix, ticket_status,
                ticket_status_name, ticket_subject, user_name FROM clients
                LEFT JOIN tickets ON client_id = ticket_client_id
                LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
                LEFT JOIN users ON ticket_assigned_to = user_id
                $client_query " . clientScopeSql('clients.client_id') . ""
            );
            while ($row = mysqli_fetch_assoc($sql)) {
                $event_id = intval($row['ticket_id']);
                $ticket_status = intval($row['ticket_status']);
                $ticket_status_name = strval($row['ticket_status_name']);
                $username = $row['user_name'];
                if (empty($username)) {
                    $username = "";
                } else {
                    //Limit to  characters and add ...
                    $username = "[". substr($row['user_name'], 0, 9) . "...]";
                }

                $event_title = json_encode($row['ticket_prefix'] . $row['ticket_number'] . " created - " . $row['ticket_subject'] . " " . $username . "{" . $ticket_status_name . "}");
                $event_start = json_encode($row['ticket_created_at']);

                if ($ticket_status == 1) {
                    $event_color = "red";
                } elseif ($ticket_status == 2) {
                    $event_color = "blue";
                }  elseif ($ticket_status == 3) {
                    $event_color = "grey";
                } else {
                    $event_color = "black";
                }

                echo "{ id: $event_id, title: $event_title, start: $event_start, color: '$event_color', url: 'ticket.php?ticket_id=$event_id$client_url' },";
            }

            // Recurring Tickets
            $sql = mysqli_query($mysqli, "SELECT client_id, recurring_ticket_frequency, recurring_ticket_id, recurring_ticket_next_run,
                recurring_ticket_subject, user_name FROM clients
                LEFT JOIN recurring_tickets ON client_id = recurring_ticket_client_id
                LEFT JOIN users ON recurring_ticket_assigned_to = user_id
                $client_query " . clientScopeSql('clients.client_id') . ""
            );
            while ($row = mysqli_fetch_assoc($sql)) {
                $event_id = intval($row['recurring_ticket_id']);
                $client_id = intval($row['client_id']);
                $username = $row['user_name'];
                $frequency = $row['recurring_ticket_frequency'];
                if (empty($username)) {
                    $username = "";
                } else {
                    //Limit to  characters and add ...
                    $username = "[". substr($row['user_name'], 0, 9) . "...]";
                }

                $event_title = json_encode("R Ticket ($frequency) - " . $row['recurring_ticket_subject'] . " " . $username);
                $event_start = json_encode($row['recurring_ticket_next_run']);

                echo "{ id: $event_id, title: $event_title, start: $event_start, color: '$event_color', url: 'recurring_tickets.php?client_id=$client_id$client_url' },";
            }

            // Tickets Scheduled
            $sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_number, ticket_prefix, ticket_schedule, ticket_status_name,
                ticket_subject, user_name FROM clients
                LEFT JOIN tickets ON client_id = ticket_client_id
                LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
                LEFT JOIN users ON ticket_assigned_to = user_id
                $client_query " . clientScopeSql('clients.client_id') . " AND ticket_schedule IS NOT NULL"
            );
            while ($row = mysqli_fetch_assoc($sql)) {
                $event_id = intval($row['ticket_id']);
                $username = $row['user_name'];
                if (empty($username)) {
                    $username = "";
                } else {
                    //Limit to  characters and add ...
                    $username = substr($row['user_name'], 0, 9) . "...";
                }

                if (strtotime($row['ticket_schedule']) < time()) {
                    if (!empty($row['ticket_schedule'])) {
                        $event_color = "red";
                    } else {
                        $event_color = "green";
                    }
                } else {
                    $event_color = "grey";
                }

                $ticket_status = strval($row['ticket_status_name']);
                $event_title = json_encode($row['ticket_prefix'] . $row['ticket_number'] . " scheduled - " . $row['ticket_subject'] . " [" . $username . "]{" . $ticket_status . "}");
                $event_start = json_encode($row['ticket_schedule']);


                echo "{ id: $event_id, title: $event_title, start: $event_start, color: '$event_color', url: 'ticket.php?ticket_id=$event_id$client_url' },";
            }

            // Vendors Added Created
            $sql = mysqli_query($mysqli, "SELECT client_id, vendor_created_at, vendor_id, vendor_name FROM clients LEFT JOIN vendors ON client_id = vendor_client_id $client_query " . clientScopeSql('clients.client_id') . "");
            while ($row = mysqli_fetch_assoc($sql)) {
                $event_id = intval($row['vendor_id']);
                $client_id = intval($row['client_id']);
                $event_title = json_encode("Vendor : '" . $row['vendor_name'] . "' created");
                $event_start = json_encode($row['vendor_created_at']);

                echo "{ id: $event_id, title: $event_title, start: $event_start, color: 'brown', url: 'vendors.php?$client_url' },";
            }

            if (!isset($_GET['client_id'])) {
                //Clients Added
                $sql = mysqli_query($mysqli, "SELECT client_created_at, client_id, client_name FROM clients");
                while ($row = mysqli_fetch_assoc($sql)) {
                    $event_id = intval($row['client_id']);
                    $event_title = json_encode("Client: '" . $row['client_name'] . "' created");
                    $event_start = json_encode($row['client_created_at']);

                    echo "{ id: $event_id, title: $event_title, start: $event_start, color: 'brown', url: 'client_overview.php?client_id=$event_id' },";
                }
            }
            ?>
        ],
        eventOrder: 'allDay,start,-duration,title',

        <?php
        // User preference for Calendar start day (Sunday/Monday)
        // Fetch User Dashboard Settings
        $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_config_calendar_first_day FROM user_settings WHERE user_id = $session_user_id"));
        $user_config_calendar_first_day = intval($row['user_config_calendar_first_day']);
        ?>
        firstDay: <?= $user_config_calendar_first_day ?>,
        });

        calendar.render();
    });
</script>
