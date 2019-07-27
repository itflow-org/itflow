<div class="modal" id="editDomainModal<?php echo $domain_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-globe mr-2"></i><?php echo $domain_name ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="domain_id" value="<?php echo $domain_id; ?>">
        <div class="modal-body bg-white">    
          <div class="form-group">
            <label>Domain Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Domain name exmaple.com" value="<?php echo $domain_name; ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Domain Registrar</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
              </div>
              <select class="form-control selectpicker show-tick" data-live-search="true" name="registrar">
                <option value="">- Vendor -</option>
                <?php 
                
                $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE client_id = $client_id"); 
                while($row = mysqli_fetch_array($sql_vendors)){
                  $vendor_id_select = $row['vendor_id'];
                  $vendor_name_select = $row['vendor_name'];
                ?>
                <option <?php if($domain_registrar == $vendor_id_select) { echo "selected"; } ?> value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Webhost</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
              </div>
              <select class="form-control selectpicker show-tick" data-live-search="true" name="webhost">
                <option value="">- Vendor -</option>
                <?php 
                
                $sql_vendors = mysqli_query($mysqli,"SELECT * FROM vendors WHERE client_id = $client_id"); 
                while($row = mysqli_fetch_array($sql_vendors)){
                  $vendor_id_select = $row['vendor_id'];
                  $vendor_name_select = $row['vendor_name'];
                ?>
                <option <?php if($domain_webhost == $vendor_id_select){ echo "selected"; } ?> value="<?php echo $vendor_id_select; ?>"><?php echo $vendor_name_select; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>
        
          <div class="form-group">
            <label>Expire Date</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="expire" value="<?php echo $domain_expire; ?>"> 
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_domain" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>