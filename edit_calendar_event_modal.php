<div class="modal" id="editCalendarEventModal<?php echo $calendar_event_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-calendar mr-2"></i><?php echo $calendar_event_title; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="calendar_event_id" value="<?php echo $calendar_event_id; ?>">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Title</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
              </div>
              <input type="text" class="form-control" name="title" placeholder="Title of the event" value="<?php echo $calendar_event_title; ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label>Calendar</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
              </div>
              <select class="form-control" name="calendar" required>
                <?php 
                
                $sql_calendars_select = mysqli_query($mysqli,"SELECT * FROM calendars"); 
                while($row = mysqli_fetch_array($sql_calendars_select)){
                  $calendar_id_select = $row['calendar_id'];
                  $calendar_name_select = $row['calendar_name'];
                ?>
                  <option <?php if($calendar_id == $calendar_id_select){ echo "selected"; } ?> value="<?php echo $calendar_id_select; ?>"><?php echo $calendar_name_select; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col">
              <label>Starts</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                </div>
                <input type="datetime-local" class="form-control" name="start" value="<?php echo date('Y-m-d\TH:i:s', strtotime($calendar_event_start)); ?>" required>
              </div>
            </div>
            <div class="form-group col">
              <label>Ends</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                </div>
                <input type="datetime-local" class="form-control" name="end" value="<?php echo date('Y-m-d\TH:i:s', strtotime($calendar_event_end)); ?>" required>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_calendar_event" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>