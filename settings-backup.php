<?php include("inc_all_admin.php"); ?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-database"></i> Backup</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" autocomplete="off">

      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="config_backup_enable" <?php if($config_backup_enable == 1){ echo "checked"; } ?> value="1" id="backupSwitch">
        <label class="custom-control-label" for="backupSwitch">Enable Backups <small>(cron.php must also be added to cron and run nightly at 11:00PM for backups to work)</small></label>
      </div>

      <div class="form-group">
        <label>Backup Path</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
          </div>
          <input type="text" class="form-control" name="config_invoice_overdue_reminders" placeholder="Specify Full File System Path ex /home/user/web/itflow.example.com/private/backups" value="<?php echo $config_backup_path; ?>">
        </div>
      </div>

      <hr>

      <button type="submit" name="edit_backup_settings" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<div class="card card-dark mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-fw fa-database"></i> Backup Database</h3>
    </div>
    <div class="card-body">
        <center>
            <a class="btn btn-primary btn-lg p-3" href="post.php?download_database"><i class="fa fa-fw fa-4x fa-download"></i><br><br>Download Database</a>
        </center>
    </div>
</div>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-fw fa-key"></i> Backup Master Encryption Key</h3>
    </div>
    <div class="card-body">
        <center>
            <form action="post.php" method="POST">
                <div class="input-group col-4">
                    <div class="input-group-prepend">
                        <input type="password" class="form-control" placeholder="Enter your account password" name="password" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-primary" type="submit" name="backup_master_key"><i class="fa fa-fw fa-key"></i> Get Master Key</button>
                </div>
            </form>
        </center>
    </div>
</div>

<?php include("footer.php");