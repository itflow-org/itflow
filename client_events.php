<?php include("header.php"); ?>

<?php 

if(isset($_GET['calendar_id'])){
  $calendar_selected_id = intval($_GET['calendar_id']);
}

?>

<div id='calendar'></div>

<?php include("add_calendar_event_modal.php"); ?>
<?php include("edit_calendar_event_modal.php"); ?>

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
          $sql = mysqli_query($mysqli,"SELECT * FROM events");
          while($row = mysqli_fetch_array($sql)){
            $event_id = $row['event_id'];
            $event_title = $row['event_title'];
            $event_start = $row['event_start'];
            $event_end = $row['event_end'];
            $calendar_id = $row['calendar_id'];
            $calendar_name = $row['calendar_name'];
            echo "{ id: '$event_id', title: '$event_title', start: '$event_start'},";
          }
          ?>
        ],
        eventClick:  function(event, jsEvent, view) {
            $('#modalTitle').html(event.title);
            $('#modalBody').html(event.description);
            $('#eventUrl').attr('href',event.url);
            $('#editEventModal').modal();
        },
      });

      calendar.render();
    });

  </script>