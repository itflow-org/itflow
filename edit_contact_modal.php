<div class="modal" id="editContactModal<?php echo $contact_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-user-edit mr-2"></i><?php echo $contact_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <input type="hidden" name="current_avatar_path" value="<?php echo $contact_photo; ?>">
        <div class="modal-body bg-white">

          <ul class="nav nav-pills nav-justified mb-3" id="pills-tab">
            <li class="nav-item">
              <a class="nav-link active" id="pills-details-tab<?php echo $contact_id; ?>" data-toggle="pill" href="#pills-details<?php echo $contact_id; ?>">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-photo-tab<?php echo $contact_id; ?>" data-toggle="pill" href="#pills-photo<?php echo $contact_id; ?>">Photo</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="pills-notes-tab<?php echo $contact_id; ?>" data-toggle="pill" href="#pills-notes<?php echo $contact_id; ?>">Notes</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content" id="pills-tabContent<?php echo $contact_id; ?>">

            <div class="tab-pane fade show active" id="pills-details<?php echo $contact_id; ?>">

              <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
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
                  <input type="text" class="form-control" name="title" placeholder="Title" value="<?php echo $contact_title; ?>">
                </div>
              </div>

              <label>Phone</label>
              <div class="form-row">
                <div class="col-8">
                  <div class="form-group">
                    <div class="input-group">
                      <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                      </div>
                      <input type="text" class="form-control" name="phone" placeholder="Phone Number" data-inputmask="'mask': '999-999-9999'" value="<?php echo $contact_phone; ?>"> 
                    </div>
                  </div>
                </div>
                <div class="col-4">
                  <input type="text" class="form-control" name="extension" placeholder="Extension" value="<?php echo $contact_extension; ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Mobile</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                  </div>
                  <input type="text" class="form-control" name="mobile" placeholder="Mobile Phone Number" data-inputmask="'mask': '999-999-9999'" value="<?php echo $contact_mobile; ?>"> 
                </div>
              </div>

              <div class="form-group">
                <label>Email</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                  </div>
                  <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo $contact_email; ?>">
                </div>
              </div>

            </div>

            <div class="tab-pane fade" id="pills-photo<?php echo $contact_id; ?>">

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
                <label>Photo</label>
                <input type="file" class="form-control-file" name="file">
              </div>

            </div>

            <div class="tab-pane fade" id="pills-notes<?php echo $contact_id; ?>">
              
              <div class="form-group">
                <textarea class="form-control" rows="8" name="notes"><?php echo $contact_notes; ?></textarea>
              </div>

            </div>

          </div>

        </div>
        <div class="modal-footer bg-light">
          <a href="#"  data-toggle="modal" data-target="#deleteConfirmModal" class="btn btn-danger mr-auto"><i class="fa fa-trash text-white"></i></a>
          <a href="post.php?delete_contact=<?php echo $contact_id; ?>" class="btn btn-danger mr-auto"><i class="fa fa-trash text-white"></i></a>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_contact" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>