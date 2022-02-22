<?php include("header.php"); ?>

<link href='plugins/fullcalendar/main.min.css' rel='stylesheet' />

<?php 

if(isset($_GET['calendar_id'])){
  $calendar_selected_id = intval($_GET['calendar_id']);
}

?>

<div id='calendar'></div>
  
<?php 
  
  include("calendar_event_add_modal.php");
  include("calendar_add_modal.php");
  include("category_quick_add_modal.php");

?>

<?php
//loop through IDs and create a modal for each
$sql = mysqli_query($mysqli,"SELECT * FROM events LEFT JOIN calendars ON event_calendar_id = calendar_id WHERE calendars.company_id = $session_company_id");
while($row = mysqli_fetch_array($sql)){
  $event_id = $row['event_id'];
  $event_title = $row['event_title'];
  $event_description = $row['event_description'];
  $event_start = $row['event_start'];
  $event_end = $row['event_end'];
  $event_repeat = $row['event_repeat'];
  $calendar_id = $row['calendar_id'];
  $calendar_name = $row['calendar_name'];
  $calendar_color = $row['calendar_color'];
  $client_id = $row['event_client_id'];

  include("calendar_event_edit_modal.php");

}

?>

<?php include("footer.php"); ?>

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
          $sql = mysqli_query($mysqli,"SELECT * FROM events LEFT JOIN calendars ON event_calendar_id = calendar_id WHERE calendars.company_id = $session_company_id");
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
          
          <?php
          //Invoices Created
          $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN invoices ON client_id = invoice_client_id WHERE clients.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $event_id = $row['invoice_id'];
            $event_title = $row['invoice_prefix'] . $row['invoice_number'] . " " . $row['invoice_scope'];
            $event_start = $row['invoice_date'];
            
            echo "{ id: '$event_id', title: ". json_encode($event_title) .", start: '$event_start', color: 'blue'},";
          }
          ?>

          <?php
          //Quotes Created
          $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN quotes ON client_id = quote_client_id WHERE clients.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $event_id = $row['quote_id'];
            $event_title = $row['quote_prefix'] . $row['quote_number'] . " " . $row['quote_scope'];
            $event_start = $row['quote_date'];
            
            echo "{ id: '$event_id', title: ". json_encode($event_title) .", start: '$event_start', color: 'purple'},";
          }
          ?>

          <?php
          //Tickets Created
          $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN tickets ON client_id = ticket_client_id WHERE clients.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $event_id = $row['ticket_id'];
            $event_title = $row['ticket_prefix'] . $row['ticket_number'] . " " . $row['ticket_subject'];
            $event_start = $row['ticket_created_at'];
            
            echo "{ id: '$event_id', title: ". json_encode($event_title) .", start: '$event_start', color: 'orange'},";
          }
          ?>

          <?php
          //Vendors Added Created
          $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN vendors ON client_id = vendor_client_id WHERE clients.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $event_id = $row['vendor_id'];
            $event_title = $row['vendor_name'];
            $event_start = $row['vendor_created_at'];
            
            echo "{ id: '$event_id', title: ". json_encode($event_title) .", start: '$event_start', color: 'brown'},";
          }
          ?>

          <?php
          //Clients Added
          $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE clients.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $event_id = $row['client_id'];
            $event_title = $row['client_name'];
            $event_start = $row['client_created_at'];
            
            echo "{ id: '$event_id', title: ". json_encode($event_title) .", start: '$event_start', color: 'green'},";
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
