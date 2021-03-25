<div class="modal" id="addCalendarEventModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-calendar-plus"></i> New Event</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Title <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
              </div>
              <input type="text" class="form-control" name="title" placeholder="Title of the event" required autofocus>
            </div>
          </div>
          
          <div class="form-group">
            <label>Calendar <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <select class="form-control select2" name="calendar" required>
                <option value="">- Calendar -</option>
                <?php 
                
                $sql = mysqli_query($mysqli,"SELECT * FROM calendars WHERE company_id = $session_company_id ORDER BY calendar_name ASC"); 
                while($row = mysqli_fetch_array($sql)){
                  $calendar_id = $row['calendar_id'];
                  $calendar_name = $row['calendar_name'];
                  $calendar_color = $row['calendar_color'];
                ?>
                  <option <?php if($config_default_calendar == $calendar_id){ echo "selected"; } ?> data-content="<i class='fa fa-circle mr-2' style='color:<?php echo $calendar_color; ?>;'></i> <?php echo $calendar_name; ?>" value="<?php echo $calendar_id; ?>"><?php echo $calendar_name; ?></option>
                
                <?php
                }
                ?>
              </select>
              <div class="input-group-append">
                <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#addQuickCalendarModal"><i class="fas fa-fw fa-plus"></i></button>
              </div>
            </div>
          </div>
          
          <div class="form-group">
            <label>Starts <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
              </div>
              <input type="text" data-toggle="datetimepicker" data-target="#datetimepicker" class="form-control datetimepicker datetimepicker-input" name="start" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Ends <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
              </div>
              <input type="datetime-local" class="form-control" name="end" required>
            </div>
          </div>

          <?php if(isset($client_id)){ ?>

          <input type="hidden" name="client" value="<?php echo $client_id; ?>">

          <?php }else{ ?>

          <div class="form-group">
            <label>Client</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <select class="form-control select2" name="client">
                <option value="">- Client -</option>
                <?php 
                
                $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE company_id = $session_company_id ORDER BY client_name ASC"); 
                while($row = mysqli_fetch_array($sql)){
                  $client_id = $row['client_id'];
                  $client_name = $row['client_name'];
                  $client_email = $row['client_email'];
                ?>
                  <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>
            
          <?php } ?>

          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="customControlAutosizing" name="email_event" value="1" >
            <label class="custom-control-label" for="customControlAutosizing">Email Event</label>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_event" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
