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
          $sql = mysqli_query($mysqli,"SELECT * FROM invoices, clients WHERE invoices.client_id = clients.client_id AND invoices.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $invoice_id = $row['invoice_id'];
            $invoice_number = $row['invoice_number'];
            $invoice_due = $row['invoice_due'];
            $invoice_amount = $row['invoice_amount'];
            $client_name = addslashes($row['client_name']);
            
            echo "{ id: '$invoice_id', title: '$invoice_number - $client_name', start: '$invoice_due', color: 'red'},";
          }

          $sql = mysqli_query($mysqli,"SELECT * FROM domains WHERE company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $domain_id = $row['domain_id'];
            $domain = $row['domain_name'];
            $domain_expire = $row['domain_expire'];
            $event_end = $row['event_end'];
            
            echo "{ id: '$domain_id', title: '$domain', start: '$domain_expire', color: 'blue'},";
          }

          $sql = mysqli_query($mysqli,"SELECT * FROM recurring, clients, categories WHERE recurring.client_id = clients.client_id AND recurring.category_id = categories.category_id AND recurring.company_id = $session_company_id");
          while($row = mysqli_fetch_array($sql)){
            $recurring_id = $row['recurring_id'];
            $recurring_next_date = $row['recurring_next_date'];
            $recurring_amount = $row['recurring_amount'];
            $category_name = $row['category_name'];
            $client_name = addslashes($row['client_name']);
            
            echo "{ id: '$recurring_id', title: '$category_name - $$recurring_amount', start: '$recurring_next_date', color: 'green'},";
          }
          
          ?>
        ],
      });

      calendar.render();
    });

  </script>