<?php 

//Paging
if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*$_SESSION['records_per_page'];
  $record_to = $_SESSION['records_per_page'];
}else{
  $record_from = 0;
  $record_to = $_SESSION['records_per_page'];
  $p = 1;
}
  
if(isset($_GET['q'])){
  $q = mysqli_real_escape_string($mysqli,$_GET['q']);
}else{
  $q = "";
}

if(!empty($_GET['sb'])){
  $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
}else{
  $sb = "login_name";
}

if(isset($_GET['o'])){
  if($_GET['o'] == 'ASC'){
    $o = "ASC";
    $disp = "DESC";
  }else{
    $o = "DESC";
    $disp = "ASC";
  }
}else{
  $o = "ASC";
  $disp = "DESC";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS *, AES_DECRYPT(login_password, '$config_aes_key') AS login_password FROM logins 
  WHERE client_id = $client_id 
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
      <input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>">
      <div class="input-group">
        <input type="search" class="form-control " name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search <?php echo ucwords($_GET['tab']); ?>">
        <div class="input-group-append">
          <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
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
              $login_uri_display = "$login_uri<button class='btn btn-sm' data-clipboard-text='$login_uri'><i class='far fa-copy text-secondary'></i></button>";
            }
            $login_username = $row['login_username'];
            if(empty($login_username)){
              $login_username_display = "-";
            }else{
              $login_username_display = "$login_username<button class='btn btn-sm' data-clipboard-text='$login_username'><i class='far fa-copy text-secondary'></i></button>";
            }
            $login_password = $row['login_password'];
            $login_otp_secret = $row['login_otp_secret'];
            if(empty($login_otp_secret)){
              $otp_display = "-";
            }else{
              $otp = get_otp($login_otp_secret);
              $otp_display = "<i class='far fa-clock text-secondary'></i> $otp<button class='btn btn-sm' data-clipboard-text='$otp'><i class='far fa-copy text-secondary'></i></button>";
            }
            $login_note = $row['login_note'];
            $vendor_id = $row['vendor_id'];
            $asset_id = $row['asset_id'];
            $software_id = $row['software_id'];
      
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
            <td><?php echo $login_password; ?><button class="btn btn-sm"><i class="far fa-eye text-secondary"></i></button><button class="btn btn-sm" data-clipboard-text="<?php echo $login_password; ?>"><i class="far fa-copy text-secondary"></i></button></td>
            </td>
            <td><?php echo $otp_display; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?delete_login=<?php echo $login_id; ?>">Delete</a>
                </div>
              </div> 
            </td>
          </tr>

          <?php
          
          include("edit_login_modal.php");
          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("add_login_modal.php"); ?>