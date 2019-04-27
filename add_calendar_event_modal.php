<div class="modal" id="addCalendarEventModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-calendar-plus"></i> New Event</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body">
          <div class="form-group">
            <label>Title</label>
            <input type="text" class="form-control" name="title" placeholder="Title of the event" required>
          </div>
          <div class="form-group">
            <label>Calendar</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
              </div>
              <select class="form-control" name="calendar" required>
                <option value="">- Select Calendar -</option>
                <?php 
                
                $sql = mysqli_query($mysqli,"SELECT * FROM calendars"); 
                while($row = mysqli_fetch_array($sql)){
                  $calendar_id = $row['calendar_id'];
                  $calendar_name = $row['calendar_name'];
                ?>
                  <option value="<?php echo $calendar_id; ?>"><?php echo $calendar_name; ?></option>
                
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
                  <span class="input-group-text"><i class="fa fa-clock"></i></span>
                </div>
                <input type="datetime-local" class="form-control" name="start" required>
              </div>
            </div>
            <div class="form-group col">
              <label>Ends</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-stopwatch"></i></span>
                </div>
                <input type="datetime-local" class="form-control" name="end" required>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_calendar_event" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>