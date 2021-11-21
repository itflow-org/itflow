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
          
          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-campaign">Campaign</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-recipients">Recipients</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-campaign">

              <div class="form-group">
                <label>Campaign Name <strong class="text-danger">*</strong></label>
                <input type="text" class="form-control" name="name" placeholder="Campaign Name" required autofocus>
              </div>

              <div class="form-group">
                <label>Email Subject <strong class="text-danger">*</strong></label>
                <input type="text" class="form-control" name="subject" placeholder="Email Subject" required>
              </div>

              <div class="form-group">
                <label>From Name <strong class="text-danger">*</strong></label>
                <input type="text" class="form-control" name="from_name" placeholder="From Name" required>
              </div>

              <div class="form-group">
                <label>From Email <strong class="text-danger">*</strong></label>
                <input type="text" class="form-control" name="from_email" placeholder="From Email" required>
              </div>
              
              <div class="form-group">
                <textarea class="form-control summernote" name="content"></textarea>
              </div>
            </div>

            <div class="tab-pane fade" id="pills-recipients">
  
              <div class="form-group">
                <label>Email <strong class="text-danger">*</strong></label>
                <input type="text" class="form-control" name="to_email" placeholder="Recipient Email">
              </div>

              <legend>Schedule</legend>

              <div class="form-group">
                <label>Date <strong class="text-danger">*</strong></label>
                <input type="date" class="form-control" name="date" placeholder="Date">
              </div>

              <div class="form-group">
                <label>Time <strong class="text-danger">*</strong></label>
                <input type="time" class="form-control" name="date" placeholder="Date">
              </div>
            </div>

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