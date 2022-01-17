<div class="modal" id="editCertificateModal<?php echo $certificate_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-lock"></i> <?php echo $certificate_name ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="certificate_id" value="<?php echo $certificate_id; ?>">
        <div class="modal-body bg-white">    
          
          <div class="form-group">
            <label>Certificate Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Certificate name" value="<?php echo $certificate_name; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Domain <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i>&nbsp;https://</span>
              </div>
              <input type="text" class="form-control" name="domain" placeholder="Domain" value="<?php echo $certificate_domain; ?>" required>
            </div>
              <p align="right">Fetch</p>
          </div>

          <div class="form-group">
            <label>Issued By</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
              </div>
              <input type="text" class="form-control" name="issued_by" placeholder="Issued By" value="<?php echo $certificate_issued_by; ?>">
            </div>
          </div>
        
          <div class="form-group">
            <label>Expire Date</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="expire" value="<?php echo $certificate_expire; ?>"> 
            </div>
          </div>

          <div class="form-group">
            <label>Public Key </label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
              </div>
              <textarea class="form-control" name="public_key"><?php echo $certificate_public_key; ?></textarea>
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_certificate" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>