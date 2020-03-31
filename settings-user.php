<?php include("header.php"); ?>

<?php

$sql_recent_logins = mysqli_query($mysqli,"SELECT * FROM logs 
    WHERE log_type = 'Login' AND log_action = 'Success' AND user_id = $session_user_id
    ORDER BY log_id DESC LIMIT 5");

$sql_recent_logs = mysqli_query($mysqli,"SELECT * FROM logs 
    WHERE user_id = $session_user_id AND log_type NOT LIKE 'Login'
    ORDER BY log_id DESC LIMIT 10");

$sql_user = mysqli_query($mysqli,"SELECT * FROM users WHERE user_id = $session_user_id");
  
$row = mysqli_fetch_array($sql_user);
$password = $row['password'];

?>

<div class="row">
  <div class="col-md-4">
    <div class="card">
      <div class="card-header bg-dark text-white">
        <h3 class="card-title"><i class="fa fa-fw fa-user"></i> User Details</h3>
      </div>
      <div class="card-body">

        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
          <input type="hidden" name="user_id" value="<?php echo $session_user_id; ?>">
          <input type="hidden" name="current_avatar_path" value="<?php echo $session_avatar; ?>">
          <input type="hidden" name="current_password_hash" value="<?php echo $password; ?>">

          <center class="mb-3 p-4">
            <img src="<?php echo "$session_avatar"; ?>" class="img-circle img-fluid elevation-2">
          </center>

          <hr>

          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Full Name" value="<?php echo $session_name; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Email <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
              </div>
              <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo $session_email; ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Password <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
              </div>
              <input type="password" class="form-control" name="password" placeholder="Enter a password" value="<?php echo $password; ?>">
            </div>
          </div>
          <div class="form-group">
            <label>Avatar</label>
            <input type="file" class="form-control-file" accept="image/*;capture=camera" name="file">
          </div>
          <button type="submit" name="edit_user" class="btn btn-primary mt-3"><i class="fa fa-fw fa-check"></i> Save</button>     
          
        </form>

        <form class="p-3" action="post.php" method="post" autocomplete="off">
        <?php
          
          require_once('rfc6238.php');

          //Generate a base32 Key
          $secretkey = key32gen();
          
          if(!empty($session_token)){ 
            //Generate QR Code based off the generated key
            print sprintf('<img src="%s"/>',TokenAuth6238::getBarCodeUrl('','',$session_token,$config_company_name));
          }
    
        ?>
        
        <input type="hidden" name="token" value="<?php echo $secretkey; ?>">
       
        <hr>

        <?php if(empty($session_token)){ ?>
          <button type="submit" name="enable_2fa" class="btn btn-primary"><i class="fa fa-fw fa-lock"></i> Enable 2FA</button>
        <?php }else{ ?>
          <button type="submit" name="disable_2fa" class="btn btn-danger"><i class="fa fa-fw fa-unlock"></i> Disable 2FA</button> 
        <?php } ?>      
      
      </form>

      <?php if(!empty($session_token)){ ?>
      <form class="p-3" action="post.php" method="post" autocomplete="off">
        <div class="form-group">
          <label>Verify 2FA is Working</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
            </div>
            <input type="text" class="form-control" name="code" placeholder="Enter Code" required>
          </div>
        </div>
        <hr>
        <button type="submit" name="verify" class="btn btn-primary">Verify</button>        
      </form>
      <?php } ?>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card">
      <div class="card-header bg-dark text-white">
        <h3 class="card-title"><i class="fa fa-fw fa-sign-in-alt"></i> Recent Logins</h3>
      </div>
      <table class="table">
        <tbody>
        <?php
      
          while($row = mysqli_fetch_array($sql_recent_logins)){
            $log_id = $row['log_id'];
            $log_description = $row['log_description'];
            $log_created_at = $row['log_created_at'];

          ?>

            <tr>
              <td><i class="fa fa-fw fa-sign-in-alt text-secondary"></i> <?php echo $log_description; ?></td>
              <td><i class="fa fa-fw fa-clock text-secondary"></i> <?php echo $log_created_at; ?></td>
            </tr>
          <?php
          }
          ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-header bg-dark text-white">
        <h3 class="card-title"><i class="fa fa-fw fa-history"></i> Recent Logs</h3>
      </div>


      <table class="table">
        <tbody>
        <?php
      
          while($row = mysqli_fetch_array($sql_recent_logs)){
            $log_id = $row['log_id'];
            $log_type = $row['log_type'];
            $log_action = $row['log_action'];
            $log_description = $row['log_description'];
            $log_created_at = $row['log_created_at'];

            if($log_action == 'Created'){
              $log_icon = "plus";
            }elseif($log_action == 'Modified'){
              $log_icon = "edit";
            }elseif($log_action == 'Deleted'){
              $log_icon = "trash-alt";
            }else{
              $log_icon = "pencil";
            }

          ?>

            <tr>
              <td><i class="fa fa-fw text-secondary fa-<?php echo $log_icon; ?>"></i> <?php echo $log_type; ?></td>
              <td><i class="fa fa-fw fa-clock text-secondary"></i> <?php echo $log_created_at; ?></td>
            </tr>
          <?php
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include("footer.php"); ?>