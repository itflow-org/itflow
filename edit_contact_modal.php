<div class="modal" id="editContactModal<?php echo $contact_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-user-edit mr-2"></i><?php echo $contact_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
        <div class="modal-body bg-white">    
          <center>
            <?php if(!empty($contact_photo)){ ?>
            <img class="img-fluid rounded-circle" src="<?php echo $contact_photo; ?>" height="256" width="256">
            <?php }else{ ?>
            <span class="fa-stack fa-4x">
              <i class="fa fa-circle fa-stack-2x text-secondary"></i>
              <span class="fa fa-stack-1x text-white"><?php echo $contact_initials; ?></span>
            </span>
            <?php } ?>
          </center>
          <div class="form-group">
            <label>Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-user"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Full Name" value="<?php echo $contact_name; ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Title</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-id-badge"></i></span>
              </div>
              <input type="text" class="form-control" name="title" placeholder="Title" value="<?php echo $contact_title; ?>" required>
            </div>
          </div>
        
          <div class="form-group">
            <label>Phone</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-phone"></i></span>
              </div>
              <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'" value="<?php echo $contact_phone; ?>" required> 
            </div>
          </div>

          <div class="form-group">
            <label>Email</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-envelope"></i></span>
              </div>
              <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo $contact_email; ?>" required>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_contact" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>