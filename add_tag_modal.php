<div class="modal" id="addTagModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-tag"></i> New Tag</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">

        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" placeholder="Tag name" required autofocus>
          </div>

          <div class="form-group">
            <label>Type <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-th"></i></span>
              </div>
              <select class="form-control select2" name="type" required>
                <option value="">- Type -</option>
                <option value="1">Client Tag</option>
              </select>
            </div>
          </div>

          <label>Color</label>
          <div class="form-row">

            <?php
            
            foreach($colors_diff as $color) {
            
            ?>
            
            <div class="col-3 mb-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="color" value="<?php echo $color; ?>">
                <label class="form-check-label">
                  <i class="fa fa-fw fa-3x fa-circle" style="color:<?php echo $color; ?>"></i>
                </label>
              </div>
            </div>
           
            <?php } ?>
          </div>

          <div class="form-group">
            <label>Icon</label>
            <input type="text" class="form-control" name="icon" placeholder="Icon ex handshake">
          </div>
        
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_tag" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>