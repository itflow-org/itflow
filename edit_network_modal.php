<div class="modal" id="editNetworkModal<?php echo $network_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title text-white"><i class="fa fa-fw fa-network-wired mr-2"></i><?php echo $network_name; ?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="network_id" value="<?php echo $network_id; ?>">
        <div class="modal-body bg-white">    
          <div class="form-group">
            <label>Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Network name (VLAN, WAN, LAN2 etc)"  value="<?php echo $network_name; ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Network</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
              </div>
              <input type="text" class="form-control" name="network" placeholder="Network ex 192.168.1.0/24" value="<?php echo $network; ?>" required data-inputmask="'mask': '999.999.999.999/99'">
            </div>
          </div>
        
          <div class="form-group">
            <label>Gateway</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-route"></i></span>
              </div>
              <input type="text" class="form-control" name="gateway" placeholder="ex 192.168.1.1" value="<?php echo $network_gateway; ?>" required data-inputmask="'mask': '999.999.999.999'"> 
            </div>
          </div>

          <div class="form-group">
            <label>DHCP Range</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
              </div>
              <input type="text" class="form-control" name="dhcp_range" placeholder="ex 192.168.1.11-199" value="<?php echo $network_dhcp_range; ?>" data-inputmask="'mask': '999.999.999.999-999'">
            </div>
          </div>

          <div class="form-group">
            <label>Location</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
              </div>
              <select class="form-control selectpicker show-tick" data-live-search="true" name="location">
                <option value="">- Location -</option>
                <?php 
                
                $sql_locations = mysqli_query($mysqli,"SELECT * FROM locations WHERE client_id = $client_id"); 
                while($row = mysqli_fetch_array($sql_locations)){
                  $location_id_select = $row['location_id'];
                  $location_name_select = $row['location_name'];
                ?>
                <option <?php if($location_id == $location_id_select){ echo "selected"; } ?> value="<?php echo $location_id_select; ?>"><?php echo $location_name_select; ?></option>
                
                <?php
                }
                ?>
              </select>
            </div>
          </div>
          
        </div>
        <div class="modal-footer bg-white">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_network" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>