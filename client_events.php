<?php

require_once("inc_all_client.php");

if (isset($_GET['calendar_id'])) {
    $calendar_selected_id = intval($_GET['calendar_id']);
}

?>

<link href='plugins/fullcalendar/main.min.css' rel='stylesheet' />

<div class="card">
    <div id='calendar'></div>
</div>

<?php
include("calendar_event_add_modal.php");
include("calendar_add_modal.php");

//loop through IDs and create a modal for each
$sql = mysqli_query($mysqli, "SELECT * FROM calendars LEFT JOIN events ON calendar_id = event_calendar_id WHERE event_client_id = $client_id");
while ($row = mysqli_fetch_array($sql)) {
    $event_id = intval($row['event_id']);
    $event_title = htmlentities($row['event_title']);
    $event_description = htmlentities($row['event_description']);
    $event_start = htmlentities($row['event_start']);
    $event_end = htmlentities($row['event_end']);
    $event_repeat = htmlentities($row['event_repeat']);
    $calendar_id = intval($row['calendar_id']);
    $calendar_name = htmlentities($row['calendar_name']);
    $calendar_color = htmlentities($row['calendar_color']);

    require("calendar_event_edit_modal.php");

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
                    bootstrapFontAwesome: 'fa fa-plus',
                    click: function() {
                        $("#addCalendarEventModal").modal();
                    }
                },
                addCalendar: {
                    bootstrapFontAwesome: 'fa fa-calendar-plus',
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
                $sql = mysqli_query($mysqli, "SELECT * FROM calendars LEFT JOIN events ON calendar_id = event_calendar_id WHERE event_client_id = $client_id");
                while ($row = mysqli_fetch_array($sql)) {
                    $event_id = intval($row['event_id']);
                    $event_title = json_encode($row['event_title']);
                    $event_start = json_encode($row['event_start']);
                    $event_end = json_encode($row['event_end']);
                    $calendar_id = intval($row['calendar_id']);
                    $calendar_name = json_encode($row['calendar_name']);
                    $calendar_color = json_encode($row['calendar_color']);

                    echo "{ id: $event_id, title: $event_title, start: $event_start, end: $event_end, color: $calendar_color },";
                }
                ?>
            ],
            eventClick: function(editEvent) {
                $('#editEventModal'+editEvent.event.id).modal();
            }
        });

        calendar.render();
    });

</script>

<?php
require("footer.php");
