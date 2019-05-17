<div class="modal" id="addDomainModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fa fa-fw fa-globe mr-2"></i>New Domain</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">    
          <div class="form-group">
            <label>Domain Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Domain name exmaple.com" required autofocus>
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
                
                $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE client_id = $client_id"); 
                while($row = mysqli_fetch_array($sql)){
                  $vendor_id = $row['vendor_id'];
                  $vendor_name = $row['vendor_name'];
                ?>
                <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                
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
                
                $sql = mysqli_query($mysqli,"SELECT * FROM vendors WHERE client_id = $client_id"); 
                while($row = mysqli_fetch_array($sql)){
                  $vendor_id = $row['vendor_id'];
                  $vendor_name = $row['vendor_name'];
                ?>
                <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                
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
              <input type="date" class="form-control" name="expire" required> 
            </div>
          </div>

        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_domain" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>