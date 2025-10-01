<div class="modal" id="addCalendarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-calendar-plus mr-2"></i>New Calendar</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body">

                    <div class="form-group">
                        <label>Name</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Name your calendar" maxlength="200" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Color <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-paint-brush"></i></span>
                            </div>
                            <input type="color" class="form-control col-3" name="color" required>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_calendar" class="btn btn-primary"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
