<div class="modal" id="editLinkModal<?php echo $custom_link_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-fw fa-external-link-alt mr-2"></i>Editing link: <strong><?php echo $custom_link_name; ?></strong></h5>
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
                <span class="input-group-text"><i class="fa fa-fw fa-list-ul"></i></span>
              </div>
              <input type="text" class="form-control" name="name" value="<?php echo $custom_link_name; ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>URI <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-external-link-alt"></i></span>
              </div>
              <input type="text" class="form-control" name="uri" value="<?php echo $custom_link_uri; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Icon</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-image"></i></span>
              </div>
              <input type="text" class="form-control" name="icon" placeholder="Icon ex handshake" value="<?php echo $custom_link_icon; ?>">
            </div>
          </div>
        
        </div>
        <div class="modal-footer bg-white">
          <button type="submit" name="edit_custom_link" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
          <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>