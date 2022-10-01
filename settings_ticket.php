<?php include("inc_all_settings.php"); ?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-life-ring"></i> Ticket Settings</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" autocomplete="off">
      
      <div class="form-group">
        <label>Ticket Prefix</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-life-ring"></i></span>
          </div>
          <input type="text" class="form-control" name="config_ticket_prefix" placeholder="Ticket Prefix" value="<?php echo $config_ticket_prefix; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Next Number</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-barcode"></i></span>
          </div>
          <input type="number" min="0" class="form-control" name="config_ticket_next_number" placeholder="Next Ticket Number" value="<?php echo $config_ticket_next_number; ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label>From Email</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
          </div>
          <input type="email" class="form-control" name="config_ticket_from_email" placeholder="From Email" value="<?php echo $config_ticket_from_email; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>From Name</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
          </div>
          <input type="text" class="form-control" name="config_ticket_from_name" placeholder="Name" value="<?php echo $config_ticket_from_name; ?>">
        </div>
      </div>
    
      <hr>
      
      <button type="submit" name="edit_ticket_settings" class="btn btn-primary">Save</button>        
    
    </form>
  </div>
</div>

<?php include("footer.php");