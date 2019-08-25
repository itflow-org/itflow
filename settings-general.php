<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-cog mr-2"></i>General Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
      <div class="form-group">
        <label>Starting Page</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-plane-arrival"></i></span>
          </div>
          <select class="form-control selectpicker show-tick" name="config_start_page" required>
            <option <?php if($config_start_page == 'dashboard.php'){ echo 'selected'; } ?> value="dashboard.php">Dashboard</option>
            <option <?php if($config_start_page == 'clients.php'){ echo 'selected'; } ?> value="clients.php">Clients</option>
            <option <?php if($config_start_page == 'invoices.php'){ echo 'selected'; } ?> value="invoices.php">Invoices</option>
            <option <?php if($config_start_page == 'expenses.php'){ echo 'selected'; } ?> value="expenses.php">Expenses</option>
            <option <?php if($config_start_page == 'calendar_events.php'){ echo 'selected'; } ?> value="calendar_events.php">Calendar</option>
            <option <?php if($config_start_page == 'tickets.php'){ echo 'selected'; } ?> value="tickets.php">Tickets</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Base URL</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
          </div>
          <input type="text" class="form-control" name="config_api_key" placeholder="Base URL ex example.com/crm or example.com" value="<?php echo $config_base_url; ?>">
        </div>
      </div>

      <div class="form-group">
        <label>API Key</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
          </div>
          <input type="text" class="form-control" name="config_api_key" placeholder="No spaces only numbers and letters" value="<?php echo $config_api_key; ?>">
        </div>
      </div>

      <div class="form-group mb-4">
        <label>Logo</label>
        <input type="file" class="form-control-file" name="file">
      </div>

      <img class="img-fluid" src="<?php echo $config_invoice_logo; ?>">
      
      <hr>
      
      <button type="submit" name="edit_general_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");