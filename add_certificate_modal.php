<div class="modal" id="addCertificateModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-lock mr-2"></i>New Certificate</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">    
          <div class="form-group">
            <label>Certificate Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Certificate name" required autofocus>
            </div>
          </div>

          <div class="form-group">
            <label>Domain</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
              </div>
              <input type="text" class="form-control" name="domain" placeholder="Domain name">
            </div>
          </div>
          
          <div class="form-group">
            <label>Issued By <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
              </div>
              <input type="text" class="form-control" name="issued_by" placeholder="Issued By" required>
            </div>
          </div>
        
          <div class="form-group">
            <label>Expire Date</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="expire"> 
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_certificate" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>