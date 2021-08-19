<div class="modal" id="editCustomLinkModal<?php echo $custom_link_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-link"></i> <?php echo $custom_link_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="custom_link_id" value="<?php echo $custom_link_id; ?>">

        <div class="modal-body bg-white">
          
          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Name" value="<?php echo $custom_link_name; ?>" required autofocus>
            </div>
          </div>

          <div class="form-group">
            <label>Icon</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-image"></i></span>
              </div>
              <input type="text" class="form-control" name="icon" placeholder="Icon" value="<?php echo $custom_link_icon; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>URL <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">https://</span>
              </div>
              <input type="text" class="form-control" name="url" placeholder="Enter URL here" value="<?php echo $custom_link_url; ?>" required>
            </div>
          </div>
        
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_custom_link" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>