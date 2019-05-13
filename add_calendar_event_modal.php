<div class="modal" id="addCalendarEventModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-calendar-plus mr-2"></i>New Event</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Title</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
              </div>
              <input type="text" class="form-control" name="title" placeholder="Title of the event" required autofocus>
            </div>
          </div>
          <div class="form-group">
            <label>Calendar</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <select class="form-control" name="calendar" required>
                <option value="">- Calendar -</option>
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
                  <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                </div>
                <input type="datetime-local" class="form-control" name="start" required>
              </div>
            </div>
            <div class="form-group col">
              <label>Ends</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
                </div>
                <input type="datetime-local" class="form-control" name="end" required>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_calendar_event" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>