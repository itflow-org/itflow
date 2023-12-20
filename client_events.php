<?php

require_once "inc_all_client.php";


if (isset($_GET['calendar_id'])) {
    $calendar_selected_id = intval($_GET['calendar_id']);
}

?>

<link href='plugins/fullcalendar/main.min.css' rel='stylesheet' />

<!-- So that when hovering over a created event it turns into a hand instead of cursor -->
<style>
.fc-event {
    cursor: pointer;
}
</style>

<div class="card">
    <div id='calendar'></div>
</div>

<?php
include "calendar_event_add_modal.php";

include "calendar_add_modal.php";


//loop through IDs and create a modal for each
$sql = mysqli_query(
    $mysqli,
    "SELECT * FROM calendars LEFT JOIN events ON calendar_id = event_calendar_id
    WHERE event_client_id = $client_id"
    );
while ($row = mysqli_fetch_array($sql)) {
    $event_id = intval($row['event_id']);
    $event_title = nullable_htmlentities($row['event_title']);
    $event_description = nullable_htmlentities($row['event_description']);
    $event_start = nullable_htmlentities($row['event_start']);
    $event_end = nullable_htmlentities($row['event_end']);
    $event_repeat = nullable_htmlentities($row['event_repeat']);
    $calendar_id = intval($row['calendar_id']);
    $calendar_name = nullable_htmlentities($row['calendar_name']);
    $calendar_color = nullable_htmlentities($row['calendar_color']);

    require "calendar_event_edit_modal.php";


}

?>

<script src='plugins/fullcalendar/main.min.js'></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        themeSystem: 'bootstrap',
        defaultView: 'dayGridMonth',
        customButtons: {
            addEvent: {
                bootstrapFontAwesome: 'fa fa-fw fa-plus',
                click: function() {
                    $("#addCalendarEventModal").modal();
                }
            },
            addCalendar: {
                bootstrapFontAwesome: 'fa fa-fw fa-calendar-plus',
                click: function() {
                    $("#addCalendarModal").modal();
                }
            }
        },
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth addEvent addCalendar'
        },
        events: [
            <?php
                $sql = mysqli_query(
                    $mysqli,
                    "SELECT * FROM calendars LEFT JOIN events ON calendar_id = event_calendar_id
                    WHERE event_client_id = $client_id"
                    );
                while ($row = mysqli_fetch_array($sql)) {
                    $event_id = intval($row['event_id']);
                    $event_title = json_encode($row['event_title']);
                    $event_start = json_encode($row['event_start']);
                    $event_end = json_encode($row['event_end']);
                    $calendar_id = intval($row['calendar_id']);
                    $calendar_name = json_encode($row['calendar_name']);
                    $calendar_color = json_encode($row['calendar_color']);

                    echo "{
                        id: $event_id,
                        title: $event_title,
                        start: $event_start,
                        end: $event_end,
                        color: $calendar_color
                    },";
                }
                ?>
        ],
        eventClick: function(editEvent) {
            $('#editEventModal' + editEvent.event.id).modal();
        }
    });

    calendar.render();
});
</script>

<!-- Automatically set new event end date to 1 hr after start date -->
<script>
// Function - called when user leaves field (onblur)
function updateIncrementEndTime() {

    // Get the start date
    let start = document.getElementById("event_add_start").value;

    // Create a date object
    let new_end = new Date(start);

    // Get the time zone offset in minutes, convert it to milliseconds
    let offsetInMilliseconds = new_end.getTimezoneOffset() * 60 * 1000;

    // Adjust the date by the time zone offset before adding an hour
    new_end = new Date(new_end.getTime() - offsetInMilliseconds);

    // Set the end date to 1 hr in the future
    new_end.setHours(new_end.getHours() + 1);

    // Get the date back as a string, with the milliseconds trimmed off
    new_end = new_end.toISOString().replace(/.\d+Z$/g, "");

    // Update the end date field
    document.getElementById("event_add_end").value = new_end;
}
</script>

<?php
require "footer.php";