<div class="modal" id="addCampaignModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-envelope"></i> New Campaign</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <div class="modal-body bg-white">  
          
          <div class="form-group">
            <label>Campaign Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" placeholder="Campaign Name" required autofocus>
          </div>

          <div class="form-group">
            <label>Email Subject <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="subject" placeholder="Email Subject" required>
          </div>
          
          <div class="form-group">
            <textarea class="form-control summernote" name="content"></textarea>
          </div>
        </div>
        
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_campaign" class="btn btn-primary">Save and Continue</button>
        </div>
      </form>
    </div>
  </div>
</div>