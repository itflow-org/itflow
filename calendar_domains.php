<?php include("header.php"); ?>

<div id='calendar'></div>

<?php include("footer.php"); ?>

<script>

    document.addEventListener('DOMContentLoaded', function() {
      var calendarEl = document.getElementById('calendar');

      var calendar = new FullCalendar.Calendar(calendarEl, {
        plugins: [ 'bootstrap', 'dayGrid', 'timeGrid', 'list' ],
        themeSystem: 'bootstrap',
        defaultView: 'dayGridMonth',
        header: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        events: [
          <?php
          $sql = mysqli_query($mysqli,"SELECT * FROM domains");
          while($row = mysqli_fetch_array($sql)){
            $domain_id = $row['domain_id'];
            $domain = $row['domain_name'];
            $domain_expire = $row['domain_expire'];
            $event_end = $row['event_end'];
            
            echo "{ id: '$domain_id', title: '$domain', start: '$domain_expire', color: 'blue'},";
          }
          ?>
        ],
      });

      calendar.render();
    });

  </script>