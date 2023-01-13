<div class="modal" id="editClientModal<?php echo $client_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-user-edit"></i> Editing: <strong><?php echo $client_name; ?></strong></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">
          
          <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
              <a class="nav-link active" data-toggle="pill" href="#pills-client-details<?php echo $client_id; ?>">Details</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-client-notes<?php echo $client_id; ?>">Notes</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" data-toggle="pill" href="#pills-client-tag<?php echo $client_id; ?>">Tag</a>
            </li>
          </ul>

          <hr>
          
          <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-client-details<?php echo $client_id; ?>">

              <div class="form-group">
                <label>Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                  </div>
                  <input type="text" class="form-control" name="name" placeholder="Name or Company" value="<?php echo $client_name; ?>" required>
                </div>
              </div>

              <div class="form-group">
                <label>Industry</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-briefcase"></i></span>
                  </div>
                  <input type="text" class="form-control" name="type" placeholder="Industry" value="<?php echo $client_type; ?>">
                </div>
              </div>

              <div class="form-group">
                <label>Referral</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-smile-wink"></i></span>
                  </div>
                  <select class="form-control select2" name="referral">
                    <option value="">N/A</option>
                    <?php 
                    
                    $referral_sql = mysqli_query($mysqli,"SELECT * FROM categories WHERE category_type = 'Referral' AND (category_archived_at > '$client_created_at' OR category_archived_at IS NULL) AND company_id = $session_company_id ORDER BY category_name ASC"); 
                    while($row = mysqli_fetch_array($referral_sql)){
                      $referral = htmlentities($row['category_name']);
                    ?>
                      <option <?php if($client_referral == $referral){ echo "selected"; } ?> > <?php echo $referral; ?></option>
                    
                    <?php
                    }
                    ?>
                  </select>
                  <div class="input-group-append">
                    <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#addQuickReferralModal"><i class="fas fa-fw fa-plus"></i></button>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label>Website</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                  </div>
                  <input type="text" class="form-control" name="website" placeholder="ex. google.com" value="<?php echo $client_website; ?>">
                </div>
              </div>
              
              <?php if($config_module_enable_accounting){ ?>
              <div class="form-group">
                <label>Currency <strong class="text-danger">*</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                  </div>
                  <select class="form-control select2" name="currency_code" required>
                    <option value="">- Currency -</option>
                    <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                    <option <?php if($client_currency_code == $currency_code){ echo "selected"; } ?> value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label>Invoice Net Terms</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                  </div>
                  <select class="form-control select2" name="net_terms">
                    <option value="">- Net Terms -</option>
                    <?php foreach($net_terms_array as $net_term_value => $net_term_name) { ?>
                    <option <?php if($net_term_value == $client_net_terms) { echo "selected"; } ?> value="<?php echo $net_term_value; ?>"><?php echo $net_term_name; ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
              
              <?php }else{ ?>
              
              <input type="hidden" name="currency_code" value="<?php echo $currency_code; ?>">
              <input type="hidden" name="net_terms" value="<?php echo $net_term_value; ?>">
              <?php } ?>

            </div>

            <div class="tab-pane fade" id="pills-client-notes<?php echo $client_id; ?>">

              <div class="form-group">
                <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"><?php echo $client_notes; ?></textarea>
              </div>
            
            </div>

            <div class="tab-pane fade" id="pills-client-tag<?php echo $client_id; ?>">

              <ul class="list-group">

                <?php
              
                $sql_tags_select = mysqli_query($mysqli,"SELECT * FROM tags WHERE tag_type = 1 AND company_id = $session_company_id ORDER BY tag_name ASC");

                while($row = mysqli_fetch_array($sql_tags_select)){
                  $tag_id_select = $row['tag_id'];
                  $tag_name_select = htmlentities($row['tag_name']);
                  $tag_color_select = htmlentities($row['tag_color']);
                  $tag_icon_select = htmlentities($row['tag_icon']);

                ?>
                  <li class="list-group-item">
                    <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="tags[]" value="<?php echo $tag_id_select; ?>" <?php if(in_array($tag_id_select, $client_tag_id_array)){ echo "checked"; } ?>>
                      <label class="form-check-label ml-2 badge bg-<?php echo $tag_color_select; ?>"><?php echo "<i class='fa fw fa-$tag_icon_select'></i>"; ?> <?php echo $tag_name_select; ?></label>
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
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_client" class="btn btn-primary text-bold"><i class="fa fa-check"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
