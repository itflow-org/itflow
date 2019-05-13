<div class="modal" id="editClientDomainModal<?php echo $client_domain_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-globe mr-2"></i>Edit Domain</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_domain_id" value="<?php echo $client_domain_id; ?>">
        <div class="modal-body bg-white">    
          <div class="form-group">
            <label>Domain Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Domain name exmaple.com" value="<?php echo $client_domain_name; ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Domain Registrar</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
              </div>
              <select class="form-control" name="registrar">
                <option value="">- Vendor -</option>
                <?php 
                
                $sql_vendors1 = mysqli_query($mysqli,"SELECT * FROM client_vendors WHERE client_id = $client_id"); 
                while($row = mysqli_fetch_array($sql_vendors1)){
                  $client_vendor_id = $row['client_vendor_id'];
                  $client_vendor_name = $row['client_vendor_name'];
                ?>
                <option <?php if($client_domain_registrar == $client_vendor_id) { echo "selected"; } ?> value="<?php echo $client_vendor_id; ?>"><?php echo $client_vendor_name; ?></option>
                
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
              <select class="form-control" name="webhost">
                <option value="">- Vendor -</option>
                <?php 
                
                $sql_vendors2 = mysqli_query($mysqli,"SELECT * FROM client_vendors WHERE client_id = $client_id"); 
                while($row = mysqli_fetch_array($sql_vendors2)){
                  $client_vendor_id = $row['client_vendor_id'];
                  $client_vendor_name = $row['client_vendor_name'];
                ?>
                <option <?php if($client_domain_webhost == $client_vendor_id){ echo "selected"; } ?> value="<?php echo $client_vendor_id; ?>"><?php echo $client_vendor_name; ?></option>
                
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
              <input type="date" class="form-control" name="expire" value="<?php echo $client_domain_expire; ?>" required> 
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_client_domain" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>