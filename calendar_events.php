<?php include("inc_all.php"); ?>

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
  $event_title = htmlentities($row['event_title']);
  $event_description = htmlentities($row['event_description']);
  $event_start = htmlentities($row['event_start']);
  $event_end = htmlentities($row['event_end']);
  $event_repeat = htmlentities($row['event_repeat']);
  $calendar_id = $row['calendar_id'];
  $calendar_name = htmlentities($row['calendar_name']);
  $calendar_color = htmlentities($row['calendar_color']);
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
            $event_id = json_encode($row['event_id']);
            $event_title = json_encode($row['event_title']);
            $event_start = json_encode($row['event_start']);
            $event_end = json_encode($row['event_end']);
            $calendar_id = json_encode($row['calendar_id']);
            $calendar_name = json_encode($row['calendar_name']);
            $calendar_color = json_encode($row['calendar_color']);
            
            echo "{ id: $event_id, title: $event_title, start: $event_start, end: $event_end, color: $calendar_color },";
          }
          ?>
          
          <?php
          //Invoices Created
          $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN invoices ON client_id = invoice_client_id WHERE clients.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $event_id = json_encode($row['invoice_id']);
            $event_title = json_encode($row['invoice_prefix'] . $row['invoice_number'] . " " . $row['invoice_scope']);
            $event_start = json_encode($row['invoice_date']);
            
            echo "{ id: $event_id, title: $event_title, start: $event_start, color: 'blue', url: 'invoice.php?invoice_id=$event_id' },";
          }
          ?>

          <?php
          //Quotes Created
          $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN quotes ON client_id = quote_client_id WHERE clients.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $event_id = json_encode($row['quote_id']);
            $event_title = json_encode($row['quote_prefix'] . $row['quote_number'] . " " . $row['quote_scope']);
            $event_start = json_encode($row['quote_date']);
            
            echo "{ id: $event_id, title: $event_title, start: $event_start, color: 'purple', url: 'quote.php?quote_id=$event_id' },";
          }
          ?>

          <?php
          //Tickets Created
          $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN tickets ON client_id = ticket_client_id WHERE clients.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $event_id = json_encode($row['ticket_id']);
            $event_title = json_encode($row['ticket_prefix'] . $row['ticket_number'] . " " . $row['ticket_subject']);
            $event_start = json_encode($row['ticket_created_at']);
            
            echo "{ id: $event_id, title: $event_title, start: $event_start, color: 'orange', url: 'ticket.php?ticket_id=$event_id' },";
         
          }
          
          ?>

          <?php
          //Vendors Added Created
          $sql = mysqli_query($mysqli,"SELECT * FROM clients LEFT JOIN vendors ON client_id = vendor_client_id WHERE clients.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $event_id = json_encode($row['vendor_id']);
            $event_title = json_encode($row['vendor_name']);
            $event_start = json_encode($row['vendor_created_at']);
            
            echo "{ id: $event_id, title: $event_title, start: $event_start, color: 'brown', url: 'client_vendors.php?client_id=$event_id' },";
          }
          ?>

          <?php
          //Clients Added
          $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE clients.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $event_id = json_encode($row['client_id']);
            $event_title = json_encode($row['client_name']);
            $event_start = json_encode($row['client_created_at']);
            
            echo "{ id: $event_id, title: $event_title, start: $event_start, color: 'green', url: 'client.php?client_id=$event_id' },";
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