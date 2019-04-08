<div class="modal" id="editClientDomainModal<?php echo $client_domain_id; ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-globe"></i> New Domain</h5>
        <button type="button" class="close" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="client_domain_id" value="<?php echo $client_domain_id; ?>">
        <div class="modal-body">    
          <div class="form-group">
            <label>Domain Name</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-globe"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Domain name exmaple.com" value="<?php echo $client_domain_name; ?>" required>
            </div>
          </div>
          
          <div class="form-group">
            <label>Domain Registrar</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-server"></i></span>
              </div>
              <input type="text" class="form-control" name="registrar" placeholder="ex GoDaddy" value="<?php echo $client_domain_registrar; ?>" required>
            </div>
          </div>
        
          <div class="form-group">
            <label>Expire Date</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
              </div>
              <input type="date" class="form-control" name="expire" value="<?php echo $client_domain_expire; ?>" required> 
            </div>
          </div>

          <div class="form-group">
            <label>Server</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-server"></i></span>
              </div>
              <input type="text" class="form-control" name="server" placeholder="ex ns1.pittpc.com" value="<?php echo $client_domain_server; ?>" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_client_domain" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>