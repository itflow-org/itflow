<?php

if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "login_name";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM logins 
  WHERE login_client_id = $client_id 
  AND (login_name LIKE '%$q%' OR login_username LIKE '%$q%' OR login_uri LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-key"></i> Logins</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addLoginModal"><i class="fas fa-fw fa-plus"></i> New Login</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <input type="hidden" name="tab" value="<?php echo strip_tags($_GET['tab']); ?>">
      <div class="row">
        
        <div class="col-md-4">
          <div class="input-group mb-3 mb-md-0">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search <?php echo ucwords(strip_tags($_GET['tab'])); ?>">
            <div class="input-group-append">
              <button class="btn btn-dark"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="float-right">
            <a href="post.php?export_client_<?php echo strip_tags($_GET['tab']); ?>_csv=<?php echo $client_id; ?>" class="btn btn-default"><i class="fa fa-fw fa-download"></i> Export</a>
            <a href="#" class="btn btn-default"><i class="fa fa-fw fa-upload"></i> Import</a>
          </div>
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=login_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=login_uri&o=<?php echo $disp; ?>">URL/Host</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=login_username&o=<?php echo $disp; ?>">Username</a></th>
            <th>Password</th>
            <th>OTP</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $login_id = $row['login_id'];
            $login_name = $row['login_name'];
            $login_uri = $row['login_uri'];
            if(empty($login_uri)){
              $login_uri_display = "-";
            }else{
              $login_uri_display = "$login_uri<button class='btn btn-sm clipboardjs' data-clipboard-text='$login_uri'><i class='far fa-copy text-secondary'></i></button><a href='https://$login_uri' target='_blank'><i class='fa fa-external-link-alt text-secondary'></i></a>";
            }
            $login_username = $row['login_username'];
            if(empty($login_username)){
              $login_username_display = "-";
            }else{
              $login_username_display = "$login_username<button class='btn btn-sm clipboardjs' data-clipboard-text='$login_username'><i class='far fa-copy text-secondary'></i></button>";
            }
            $login_password = htmlentities(decryptLoginEntry($row['login_password']));
            $login_otp_secret = $row['login_otp_secret'];
            $login_id_with_secret = '"' . $row['login_id'] . '","' . $row['login_otp_secret'] . '"';
            if(empty($login_otp_secret)){
              $otp_display = "-";
            }else{
              $otp_display = "<span onmouseover='showOTP($login_id_with_secret)'><i class='far fa-clock'></i> <span id='otp_$login_id'><i>Hover..</i></span></span>";
            }
            $login_note = $row['login_note'];
            $login_contact_id = $row['login_contact_id'];
            $login_vendor_id = $row['login_vendor_id'];
            $login_asset_id = $row['login_asset_id'];
            $login_software_id = $row['login_software_id'];
      
          ?>
          <tr>
            <td>
              <i class="fa fa-fw fa-key text-secondary"></i> 
              <a class="text-dark" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">
                <?php echo $login_name; ?>
              </a>
            </td>
            <td><?php echo $login_uri_display; ?></td>
            <td><?php echo $login_username_display; ?></td>
            <td>
              <a tabindex="0" class="btn btn-sm" data-toggle="popover" data-trigger="focus" data-placement="left" data-content="<?php echo $login_password; ?>"><i class="far fa-eye text-secondary"></i></a><button class="btn btn-sm clipboardjs" data-clipboard-text="<?php echo $login_password; ?>"><i class="far fa-copy text-secondary"></i></button></td>
            </td>
            <td><?php echo $otp_display; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'Login', $login_id"; ?>)">Share</a>
                  <?php if($session_user_role == 3) { ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="post.php?delete_login=<?php echo $login_id; ?>">Delete</a>
                  <?php } ?>
                </div>
              </div> 
            </td>
          </tr>

          <?php
          
          include("client_login_edit_modal.php");
          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<script>
    function showOTP(id, secret){
        //Send a GET request to ajax.php as ajax.php?get_totp_token=true&totp_secret=SECRET
        jQuery.get(
            "ajax.php",
            {get_totp_token: 'true', totp_secret: secret},
            function(data){
                //If we get a response from post.php, parse it as JSON
                const token = JSON.parse(data);

                document.getElementById("otp_" + id).innerText = token

            }
        );
    }
</script>

<?php
include("client_login_add_modal.php");
include("share_modal.php");
?>