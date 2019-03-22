<div class="modal fade" id="addClientNetworkModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-network-wired"></i> New Network</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
        <div class="modal-body">    
          <div class="form-group">
            <label>Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-network-wired"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Network name (VLAN, WAN, LAN2 etc)" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Network</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-network-wired"></i></span>
              </div>
              <input type="text" class="form-control" name="network" placeholder="Network ex 192.168.1.0/24" required>
            </div>
          </div>
        
          <div class="form-group">
            <label>Gateway</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-route"></i></span>
              </div>
              <input type="text" class="form-control" name="gateway" placeholder="ex 192.168.1.1" required> 
            </div>
          </div>

          <div class="form-group">
            <label>DHCP Range</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-server"></i></span>
              </div>
              <input type="text" class="form-control" name="dhcp_range" placeholder="ex 192.168.1.11-199">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="add_client_network" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>