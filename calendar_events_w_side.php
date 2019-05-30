<?php include("header.php"); ?>

<?php 

if(isset($_GET['calendar_id'])){
  $calendar_selected_id = intval($_GET['calendar_id']);
}

?>

<div class="row">
  <div class="col-md-2">
    <nav class="nav flex-column mb-5">
      <h2 class="text-center">Calendars</h2>
      <a class="btn btn-primary btn-block" href="#">New Event</a>
      <a class="nav-link active" href="#">Jobs</a>
      <a class="nav-link" href="#">Domains</a>
      <a class="nav-link" href="#">Clients</a>
      <a class="nav-link" href="#">Invoices</a>
      <a class="nav-link" href="#">Recurring</a>
      <a class="nav-link" href="#">Payments</a>
      <a class="nav-link" href="#">Trips</a>
      <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Expenses</a>
    </nav>
    <form>
      <div class="input-group mb-3">
        <input type="text" class="form-control" name="calendar" placeholder="Calendar name">
        <div class="input-group-append">
          <button class="btn btn-outline-secondary btn-sm" type="button"><i class="fa fa-fw fa-check"></i></button>
        </div>
      </div>
    </form>
  </div>
  <div class="col-md-10">
    <div id='calendar'></div>
  </div>
</div>

<?php include("add_calendar_event_modal.php"); ?>

<?php include("footer.php"); ?>

<script>

    document.addEventListener('DOMContentLoaded', function() {
      var calendarEl = document.getElementById('calendar');

      var calendar = new FullCalendar.Calendar(calendarEl, {
        plugins: [ 'interaction', 'dayGrid', 'timeGrid', 'list' ],
        defaultView: 'dayGridMonth',
        customButtons: {
          myCustomButton: {
            text: 'New',
            click: function() {
              $("#addCalendarEventModal").modal("show");
            }
          }
        },
        header: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth myCustomButton'
        },
        events: [
          <?php
          $sql = mysqli_query($mysqli,"SELECT * FROM calendar_events");
          while($row = mysqli_fetch_array($sql)){
            $calendar_event_id = $row['calendar_event_id'];
            $calendar_event_title = $row['calendar_event_title'];
            $calendar_event_start = $row['calendar_event_start'];
            $calendar_event_end = $row['calendar_event_end'];
            $calendar_id = $row['calendar_id'];
            $calendar_name = $row['calendar_name'];
            echo "{ id: '$calendar_event_id', title: '$calendar_event_title', start: '$calendar_event_start'},";
          }
          ?>
        ],
        eventClick: function(calEvent, jsEvent, view, resourceObj) {
          $("#addCalendarEventModal").modal("show"); 
        }
      });

      calendar.render();
    });

  </script>