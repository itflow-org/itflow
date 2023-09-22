<div class="modal" id="editNetworkModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-fw fa-network-wired mr-2"></i>Edit network: <span class="text-bold" id="editNetworkHeader"></span></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="network_id" id="editNetworkId" value="">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body bg-white">    
          
          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
              </div>
              <input type="text" class="form-control" id="editNetworkName" name="name" placeholder="Network name (VLAN, WAN, LAN2 etc)" required>
            </div>
          </div>

          <div class="form-group">
            <label>vLAN</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
              </div>
              <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*" id="editNetworkVlan" name="vlan" placeholder="ex. 20">
            </div>
          </div>
          
          <div class="form-group">
            <label>Network <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
              </div>
              <input type="text" class="form-control" id="editNetworkCidr" name="network" placeholder="Network ex 192.168.1.0/24" required>
            </div>
          </div>
        
          <div class="form-group">
            <label>Gateway <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-route"></i></span>
              </div>
              <input type="text" class="form-control" id="editNetworkGw" name="gateway" placeholder="ex 192.168.1.1" data-inputmask="'alias': 'ip'" data-mask required>
            </div>
          </div>

          <div class="form-group">
            <label>DHCP Range / IPs</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
              </div>
              <input type="text" class="form-control" id="editNetworkDhcp" name="dhcp_range" placeholder="ex 192.168.1.11-199">
            </div>
          </div>

          <div class="form-group">
            <label>Location</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
              </div>
              <select class="form-control select2" id="editNetworkLocation" name="location">
                <option value="">- Location -</option>
              </select>
            </div>
          </div>
          
        </div>
        <div class="modal-footer bg-white">
          <button type="submit" name="edit_network" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
          <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
