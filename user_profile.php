<?php include("inc_all.php"); ?>

<?php

$sql_recent_logins = mysqli_query($mysqli,"SELECT * FROM logs 
    WHERE log_type = 'Login' OR log_type = 'Login 2FA' AND log_action = 'Success' AND log_user_id = $session_user_id
    ORDER BY log_id DESC LIMIT 3");

$sql_recent_logs = mysqli_query($mysqli,"SELECT * FROM logs 
    WHERE log_user_id = $session_user_id AND log_type NOT LIKE 'Login'
    ORDER BY log_id DESC LIMIT 5");

?>

<div class="row">
  <div class="col-md-3">
    <div class="card card-dark">
      <div class="card-header py-3">
        <h3 class="card-title"><i class="fa fa-fw fa-user"></i> Your User Details</h3>
      </div>
      <div class="card-body">

        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
          <input type="hidden" name="existing_file_name" value="<?php echo $session_avatar; ?>">

          <center class="mb-3 px-5">
            <?php if (empty($session_avatar)) { ?>
              <i class="fas fa-user-circle fa-8x text-secondary"></i>
            <?php }else{ ?>
              <img src="<?php echo "uploads/users/$session_user_id/$session_avatar"; ?>" class="img-fluid">
            <?php } ?>
            <h4 class="text-secondary mt-2"><?php echo htmlentities($session_user_role_display); ?></h4>
          </center>

          <hr>

          <div class="form-group">
            <label>Your Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
              </div>
              <input type="text" class="form-control" name="name" placeholder="Full Name" value="<?php echo htmlentities($session_name); ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Your Email <strong class="text-danger">*</strong></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
              </div>
              <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo htmlentities($session_email); ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Your New Password</label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
              </div>
              <input type="password" class="form-control" data-toggle="password" name="new_password" placeholder="Leave blank for no change" autocomplete="new-password">
              <div class="input-group-append">
                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Your Avatar</label>
            <input type="file" class="form-control-file" accept="image/*;capture=camera" name="file">
          </div>

          <?php if ($session_user_role > 1) { ?>

            <div class="form-group">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" name="extension" id="extension" value="Yes" <?php if (isset($_COOKIE['user_extension_key'])) {echo "checked";} ?>>
                <label class="form-check-label" for="extension">Enable Browser Extention?</label>
                <p class="small">Note: You must log out and back in again for these changes take effect.</p>
              </div>
            </div>

          <?php } ?>

          <button type="submit" name="edit_profile" class="btn btn-primary btn-block mt-3"><i class="fa fa-fw fa-check"></i> Save</button>


        </form>

        <hr>

        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

          <?php if (empty($session_token)) { ?>
            <button type="submit" name="enable_2fa" class="btn btn-success btn-block mt-3"><i class="fa fa-fw fa-lock"></i><br> Enable 2FA</button>
          <?php }else{ ?>
            <p>You have set up 2FA. Your QR code is below.</p>
            <button type="submit" name="disable_2fa" class="btn btn-danger btn-block mt-3"><i class="fa fa-fw fa-unlock"></i><br>Disable 2FA</button>
          <?php } ?>

          <center>
            <?php

            require_once('rfc6238.php');

            //Generate a base32 Key
            $secretkey = key32gen();

            if (!empty($session_token)) {

              //Generate QR Code based off the generated key
              print sprintf('<img src="%s"/>',TokenAuth6238::getBarCodeUrl($session_name,' ',$session_token,$_SERVER['SERVER_NAME']));

              echo "<p class='text-secondary'>$session_token</p>";
            }

            ?>
          </center>

          <input type="hidden" name="token" value="<?php echo $secretkey; ?>">

        </form>

        <?php if (!empty($session_token)) { ?>
          <form action="post.php" method="post" autocomplete="off">
            <div class="form-group">
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                </div>
                <input type="text" class="form-control" name="code" placeholder="Verify 2FA Code" required>
                <div class="input-group-append">
                  <button type="submit" name="verify" class="btn btn-success">Verify</button>
                </div>
              </div>
            </div>

          </form>
        <?php } ?>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card card-dark">
      <div class="card-header py-3">
        <h3 class="card-title"><i class="fa fa-fw fa-sign-in-alt"></i> Your Recent Sign ins</h3>
      </div>
      <table class="table table-borderless table-sm">
        <tbody>
        <?php

        while ($row = mysqli_fetch_array($sql_recent_logins)) {
          $log_id = $row['log_id'];
          $log_ip = htmlentities($row['log_ip']);
          $log_user_agent = htmlentities($row['log_user_agent']);
          $log_user_os = get_os($log_user_agent);
          $log_user_browser = get_web_browser($log_user_agent);
          $log_created_at = $row['log_created_at'];

          ?>

          <tr>
            <td><i class="fa fa-fw fa-clock text-secondary"></i> <?php echo $log_created_at; ?></td>
            <td><?php echo "<strong>$log_user_os</strong><br>$log_user_browser<br><i class='fa fa-fw fa-globe text-secondary'></i> $log_ip"; ?></td>

          </tr>
          <?php
        }
        ?>
        </tbody>
      </table>
      <div class="card-footer">
        <a href="logs.php?q=<?php echo "$session_name successfully logged in"; ?>">See More...</a>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card card-dark">
      <div class="card-header py-3">
        <h3 class="card-title"><i class="fa fa-fw fa-history"></i> Your Recent Activity</h3>
      </div>


      <table class="table table-borderless table-sm">
        <tbody>
        <?php

        while ($row = mysqli_fetch_array($sql_recent_logs)) {
          $log_id = $row['log_id'];
          $log_type = htmlentities($row['log_type']);
          $log_action = htmlentities($row['log_action']);
          $log_description = htmlentities($row['log_description']);
          $log_created_at = $row['log_created_at'];

          if ($log_action == 'Create') {
            $log_icon = "plus text-success";
          }elseif ($log_action == 'Modify') {
            $log_icon = "edit text-info";
          }elseif ($log_action == 'Delete') {
            $log_icon = "trash-alt text-danger";
          }else{
            $log_icon = "pencil";
          }

          ?>

          <tr>
            <td><i class="fa fa-fw fa-clock text-secondary"></i> <?php echo $log_created_at; ?></td>
            <td><strong><i class="fa fa-fw text-secondary fa-<?php echo $log_icon; ?>"></i> <?php echo $log_type; ?></strong>
              <br>
              <span class="text-secondary"><?php echo $log_description; ?></span>
            </td>

          </tr>
          <?php
        }
        ?>
        </tbody>
      </table>
      <div class="card-footer">
        <a href="logs.php?q=<?php echo htmlentities($session_name); ?>">See More...</a>
      </div>
    </div>
  </div>

</div>

<?php include("footer.php"); ?>
