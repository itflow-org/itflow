<?php 

//Rebuild URL

$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

if(isset($_GET['p'])){
  $p = intval($_GET['p']);
  $record_from = (($p)-1)*10;
  $record_to =  10;
}else{
  $record_from = 0;
  $record_to = 10;
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


$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS *, AES_DECRYPT(login_password, '$config_aes_key') AS login_password FROM logins 
  WHERE client_id = $client_id 
  AND (login_name LIKE '%$q%' OR login_username LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
$total_found_rows = $num_rows[0];
$total_pages = ceil($total_found_rows / 10);

?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-key"></i> Logins</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addLoginModal"><i class="fa fa-plus"></i></button>
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
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=login_username&o=<?php echo $disp; ?>">Username</a></th>
            <th>Password</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $login_id = $row['login_id'];
            $login_name = $row['login_name'];
            $login_uri = $row['login_uri'];
            $login_username = $row['login_username'];
            $login_password = $row['login_password'];
            $login_note = $row['login_note'];
            $vendor_id = $row['vendor_id'];
            $asset_id = $row['asset_id'];
            $software_id = $row['software_id'];
      
          ?>
          <tr>
            <td>
              <a class="text-dark" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>"><?php echo $login_name; ?></a>
              <br>
              <small class="text-secondary"><?php echo $login_uri; ?></small>

            </td>
            <td><?php echo $login_username; ?></td>
            <td><?php echo $login_password; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <?php if(!empty($login_uri)){ ?> <a class="dropdown-item" href="<?php echo $login_uri; ?>" target='_blank'><i class="fa fa-link"></i> Open URI</a> <?php } ?>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLoginModal<?php echo $login_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_login=<?php echo $login_id; ?>">Delete</a>
                </div>
              </div> 
              <?php include("edit_login_modal.php"); ?>     
            </td>
          </tr>

          <?php
          
          }
          
          ?>

        </tbody>
      </table>

      <?php include("pagination.php"); ?>

    </div>
  </div>
</div>

<?php include("add_login_modal.php"); ?>