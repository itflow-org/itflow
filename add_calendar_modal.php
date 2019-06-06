<div class="modal" id="addCalendarModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-calendar-plus mr-2"></i>New Calendar</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Name your calendar" required autofocus>
            </div>
          </div>
          
          <div class="form-group">
            <label>Color</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-palette"></i></span>
              </div>
              <input type="color" class="form-control" name="color" required>
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_calendar" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>