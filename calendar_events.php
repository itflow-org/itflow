<?php include("header.php"); ?>

<?php 

if(isset($_GET['calendar_id'])){
  $calendar_selected_id = intval($_GET['calendar_id']);
}

//Get Calendar list for the select box
$sql_calendars = mysqli_query($mysqli,"SELECT * FROM calendars ORDER BY calendar_name DESC");

$sql = mysqli_query($mysqli,"SELECT * FROM calendars, calendar_events WHERE calendars.calendar_id = calendar_events.calendar_id AND calendar_events.calendar_id LIKE '%$calendar_selected_id%' ORDER BY calendar_event_id DESC"); 

?>

 <div id='calendar'></div>

<div class="row">
  <div class="col-md-2">
  </div>
  <div class="col-md-10">
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="float-left mt-2"><i class="fa fa-calendar mr-2"></i>Events</h5>
        <button type="button" class="btn btn-primary badge-pill mr-auto float-right" data-toggle="modal" data-target="#addCalendarEventModal"><i class="fas fa-fw fa-calendar-plus"></i></button>
        <form>
          <select onchange="this.form.submit()" class="form-control mt-5" name="calendar_id">
            <option value="">- ALL Calendars -</option>
            <?php 
                    
            while($row = mysqli_fetch_array($sql_calendars)){
              $calendar_id = $row['calendar_id'];
              $calendar_name = $row['calendar_name'];
            ?>
            <option <?php if($calendar_id == $calendar_selected_id){ ?> selected <?php } ?> value="<?php echo $calendar_id; ?>"> <?php echo $calendar_name; ?></option>
            
            <?php
            }
            ?>

          </select>
        </form>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
            <thead class="thead-dark">
              <tr>
                <th>Date</th>
                <th>Times</th>
                <th>Title</th>
                <th>Calendar</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
          
              while($row = mysqli_fetch_array($sql)){
                $calendar_event_id = $row['calendar_event_id'];
                $calendar_event_title = $row['calendar_event_title'];
                $calendar_event_start = $row['calendar_event_start'];
                $calendar_event_end = $row['calendar_event_end'];
                $calendar_id = $row['calendar_id'];
                $calendar_name = $row['calendar_name'];

              ?>
              <tr>
                <td><?php echo date( 'm/d/y', strtotime($calendar_event_start)); ?></td>
                <td><?php echo date( 'g:i A', strtotime($calendar_event_start)); ?> - <?php echo date( 'g:i A', strtotime($calendar_event_end)); ?></td>
                <td><?php echo $calendar_event_title; ?></td>
                <td><?php echo $calendar_name; ?></td>
                <td>
                  <div class="dropdown dropleft text-center">
                    <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                      <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                      <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editCalendarEventModal<?php echo $calendar_event_id; ?>">Edit</a>
                      <a class="dropdown-item" href="post.php?delete_calendar_event=<?php echo $calendar_event_id; ?>">Delete</a>
                    </div>
                  </div>      
                </td>
              </tr>

              <?php
              include("edit_calendar_event_modal.php");
              }
              ?>

            </tbody>
          </table>
        </div>
      </div>
    </div>
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
        header: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
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
            echo "{ title: '$calendar_event_title', start: '$calendar_event_start' },";
          }
          ?>
        ]
      });

      calendar.render();
    });

  </script>