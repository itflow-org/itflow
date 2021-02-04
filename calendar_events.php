<?php include("header.php"); ?>

<?php 

if(isset($_GET['calendar_id'])){
  $calendar_selected_id = intval($_GET['calendar_id']);
}

?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-calendar-alt"></i> Calendar</h3>
  </div>
  <div class="card-body pt-0">
    <div id='calendar'></div>
  </div>
</div>

<?php include("add_calendar_event_modal.php"); ?>
<?php include("add_calendar_modal.php"); ?>

<?php
//loop through IDs and create a modal for each
$sql = mysqli_query($mysqli,"SELECT * FROM events, calendars WHERE events.calendar_id = calendars.calendar_id AND calendars.company_id = $session_company_id");
while($row = mysqli_fetch_array($sql)){
  $event_id = $row['event_id'];
  $event_title = $row['event_title'];
  $event_start = $row['event_start'];
  $event_end = $row['event_end'];
  $calendar_id = $row['calendar_id'];
  $calendar_name = $row['calendar_name'];
  $calendar_color = $row['calendar_color'];
  $client_id = $row['client_id'];

  include("edit_calendar_event_modal.php");

}

?>

<?php include("footer.php"); ?>

<script>

    document.addEventListener('DOMContentLoaded', function() {
      var calendarEl = document.getElementById('calendar');

      var calendar = new FullCalendar.Calendar(calendarEl, {
        plugins: [ 'bootstrap', 'dayGrid', 'timeGrid', 'list' ],
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
        header: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth addEvent addCalendar '
        },
        events: [
          <?php
          $sql = mysqli_query($mysqli,"SELECT * FROM events, calendars WHERE events.calendar_id = calendars.calendar_id AND calendars.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $event_id = $row['event_id'];
            $event_title = $row['event_title'];
            $event_start = $row['event_start'];
            $event_end = $row['event_end'];
            $calendar_id = $row['calendar_id'];
            $calendar_name = $row['calendar_name'];
            $calendar_color = $row['calendar_color'];
            
            echo "{ id: '$event_id', title: '$event_title', start: '$event_start', end: '$event_end', color: '$calendar_color'},";
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