<?php include("header.php"); ?>

<?php include("settings-nav.php"); ?>

<?php

//fetch the latest code changes but don't apply them
exec("git fetch");
$latest_version = exec("gitrev-parse origin/master");
$current_version = exec("git rev-parse HEAD");

if($current_version == $latest_version){
  $update_message = "No Updates available";
}else{
  $update_message = "New Updates are Available [$latest_version]";
}

$git_log = shell_exec("git log master..origin/master --pretty=format:'<tr><td>%h</td><td>%ar</td><td>%s</td></tr>'");

?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-wrench"></i> Update</h3>
  </div>
  <div class="card-body">
    <center>
      <h5><small class="text-secondary">Current Version</small><br><?php echo $current_version; ?></h5>
      <?php if(!empty($git_log)){ ?>
      <a class="btn btn-primary btn-lg my-4" href="post.php?update"><i class="fa fa-fw fa-4x fa-check-square"></i><br>Update<br>App</a>
      <?php
      }else{
      ?>
      <a class="btn btn-dark btn-lg my-4" href="post.php?update_db"><i class="fa fa-fw fa-4x fa-check-square"></i><br>Update<br>Database Structure</a>
      <h3 class="text-success">Congratulations you are up to date!</h3>
      <?php
      }
      ?>
    </center>

    <?php
    if(!empty($git_log)){
    ?>  
    <table class="table ">
      <thead>
        <tr>
          <th>Commit</th>
          <th>When</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        echo $git_log;
        ?>
      </tbody>
    </table>
    <?php
    }
    ?>
  </div>
</div>

<!-- Updater to upgrade to new encryption -->
<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-fw fa-wrench"></i> Update AES Key</h3>
    </div>
    <div class="card-body">
    <center>
        <div class="col-8">
            <div class="alert alert-danger" role="alert">
                <strong>You only need to continue with this action if you are upgrading/migrating to the new encryption setup.</strong>
                <ul>
                    <li>Please take a backup of your current AES config key (for each company), and your 'logins' database table</li>
                    <li>Please ensure you have access to ALL companies registered under this instance, if using multiple companies. Only one user should perform the entire migration.</li>
                    <li>This activity will invalidate all existing user passwords. You must set them again using THIS user account.</li>
                </ul>
            </div>
        </div>
<?php

//Get current settings
include_once('get_settings.php');
echo "Current Company ID: $session_company_id <br>";
echo "Current User ID: $session_user_id <br>";

if ($config_aes_key) {
    echo "Current AES key:  $config_aes_key <br><br>";
    echo "<b>Below are the decrypted credentials for five login entries, please confirm they show and are correct before continuing. <br>Do NOT continue if no entries are shown or if the decrypted passwords are incorrect.</b><br>";
    $sql = mysqli_query($mysqli,"SELECT *, AES_DECRYPT(login_password, '$config_aes_key') AS login_password FROM logins WHERE (company_id = '$session_company_id' AND login_password IS NOT NULL) LIMIT 5");
    foreach ($sql as $row){
        echo $row['login_username'] . ":" . $row['login_password'];
        echo "<br>";
    }
    echo "<br>";
    ?>

    <form method="POST" action="post.php">
        <div class="form-group">
            <div class="input-group col-3">
                <input type="password" class="form-control" placeholder="Account Password" name="password" value="" required="">
            </div>
            <br>
            <button type="submit" class="btn btn-danger" name="encryption_update">Update encryption scheme for this company</button>
        </div>
    </form>
<?php
}
else {
    echo "Config AES key is not set for this company.<br>";
    echo "Please ensure upgrade is required. If you are sure you need to update, ensure the AES key is for this company.";
}

?>
    </center>
    </div>
</div>

<?php include("footer.php");