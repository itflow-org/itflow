<div class="modal" id="campaignEditModal<?php echo $campaign_id; ?>" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-envelope"></i> <?php echo $campaign_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
        <div class="modal-body bg-white">  
          
          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-campaign<?php echo $campaign_id; ?>">Campaign</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-content<?php echo $campaign_id; ?>">Content</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-recipients<?php echo $campaign_id; ?>">Recipients</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-campaign<?php echo $campaign_id; ?>">

              <div class="form-group">
                <label>Campaign Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-paper-plane"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Campaign Name" value="<?php echo $campaign_name; ?>" required autofocus>
                </div>
              </div>

              <div class="form-group">
                <label>Email Subject <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-smile-wink"></i></span>
                  </div>
                  <input type="text" class="form-control" name="subject" placeholder="Email Subject" value="<?php echo $campaign_subject; ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label>From Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="from_name" placeholder="From Name" value="<?php echo $campaign_from_name; ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label>From Email <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                  </div>
                  <input type="text" class="form-control" name="from_email" placeholder="From Email" value="<?php echo $campaign_from_email; ?>" required>
                </div>
              </div>

              <hr>

              <div class="form-group">
                <label>Schedule <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                  </div>
                  <input type="datetime-local" class="form-control" name="scheduled_time" placeholder="Schedule Date and Time" value="<?php echo $campaign_scheduled_at; ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Set Status <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-smile-wink"></i></span>
                  </div>
                  <select class="form-control" name="status">
                    <option <?php if($campaign_status == 'Draft'){ echo "selected"; } ?>>Draft</option>
                    <option <?php if($campaign_status == 'Queued'){ echo "selected"; } ?>>Queued</option>
                  </select>
                </div>
              </div>
              
            </div>

            <div class="tab-pane fade" id="pills-content<?php echo $campaign_id; ?>">

              <div class="form-group">
                <textarea class="form-control summernote" name="content"><?php echo $campaign_content; ?></textarea>
              </div>
            </div>

            <div class="tab-pane fade" id="pills-recipients<?php echo $campaign_id; ?>">
  
              <label>Select Recipients <strong class="text-danger">*</strong></label>

              <ul class="list-group mb-3">

                <?php
                $sql_tags_select = mysqli_query($mysqli,"SELECT * FROM tags WHERE tag_type = 1 AND company_id = $session_company_id ORDER BY tag_name ASC");

                while($row = mysqli_fetch_array($sql_tags_select)){
                  $tag_id_select = $row['tag_id'];
                  $tag_name_select = $row['tag_name'];
                  $tag_color_select = $row['tag_color'];
                  $tag_icon_select = $row['tag_icon'];
                  //Get Contact Count
                  $row = mysqli_fetch_assoc(mysqli_query($mysqli,"SELECT COUNT(*) AS client_count FROM clients, client_tags 
                    WHERE clients.client_id = client_tags.client_id
                    AND tag_id = $tag_id_select 
                    AND company_id = $session_company_id 
                  "));
                  $client_count = $row['client_count'];

                ?>
                  <li class="list-group-item">
                    <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="tags[]" value="<?php echo $tag_id_select; ?>">
                      <label class="form-check-label ml-2 badge bg-<?php echo $tag_color_select; ?>"><?php echo "<i class='fa fw fa-$tag_icon_select'></i>"; ?> <?php echo $tag_name_select; ?></label><span class="right badge badge-light"><?php echo $client_count; ?></span>
                    </div>
                  </li>

                <?php
                }
                ?>

              </ul>

            </div>

          </div>

        </div>
        
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_campaign" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>