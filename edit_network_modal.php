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
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Network name (VLAN, WAN, LAN2 etc)"  value="<?php echo $network_name; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>vLAN</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
              </div>
              <input type="number" class="form-control" name="vlan" placeholder="ex. 20" value="<?php echo $network_vlan; ?>" data-inputmask="'mask': '9999'">
            </div>
          </div>
          
          <div class="form-group">
            <label>Network <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
              </div>
              <input type="text" class="form-control" name="network" placeholder="Network ex 192.168.1.0/24" value="<?php echo $network; ?>" required>
            </div>
          </div>
        
          <div class="form-group">
            <label>Gateway <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-route"></i></span>
              </div>
              <input type="text" class="form-control" name="gateway" placeholder="ex 192.168.1.1" value="<?php echo $network_gateway; ?>" data-inputmask="'alias': 'ip'" required> 
            </div>
          </div>

          <div class="form-group">
            <label>DHCP Range</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
              </div>
              <input type="text" class="form-control" name="dhcp_range" placeholder="ex 192.168.1.11-199" value="<?php echo $network_dhcp_range; ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Location</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
              </div>
              <select class="form-control select2" name="location">
                <option value="">- Location -</option>
                <?php 
                
                $sql_locations = mysqli_query($mysqli,"SELECT * FROM locations WHERE client_id = $client_id ORDER BY location_name ASC"); 
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
          <a href="post.php?delete_network=<?php echo $network_id; ?>" class="btn btn-danger mr-auto"><i class="fa fa-trash text-white"></i></a>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_network" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>