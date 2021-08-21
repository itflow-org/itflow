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
  $sb = "software_name";
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM software 
  WHERE client_id = $client_id 
  AND (software_name LIKE '%$q%' OR software_type LIKE '%$q%' OR software_license LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-rocket"></i> Software</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addSoftwareModal"><i class="fas fa-fw fa-plus"></i> New Software</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>">
      <div class="row">
        
        <div class="col-md-4">
          <div class="input-group mb-3 mb-md-0">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search <?php echo ucwords($_GET['tab']); ?>">
            <div class="input-group-append">
              <button class="btn btn-dark"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="float-right">
            <a href="post.php?export_client_<?php echo $_GET['tab']; ?>_csv=<?php echo $client_id; ?>" class="btn btn-default"><i class="fa fa-fw fa-download"></i> Export</a>
          </div>
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=software_name&o=<?php echo $disp; ?>">Software</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=software_type&o=<?php echo $disp; ?>">Type</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=software_license&o=<?php echo $disp; ?>">License</a></th>
            <th></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $software_id = $row['software_id'];
            $software_name = $row['software_name'];
            $software_type = $row['software_type'];
            $software_license = $row['software_license'];
            if(empty($software_license)){
              $software_license_display = "-";
            }else{
              $software_license_display = $software_license;
            }
            $software_notes = $row['software_notes'];

            $sql_login = mysqli_query($mysqli,"SELECT *, AES_DECRYPT(login_password, '$config_aes_key') AS login_password FROM logins WHERE software_id = $software_id");
            $row = mysqli_fetch_array($sql_login);
            $login_id = $row['login_id'];
            $login_username = $row['login_username'];
            $login_password = $row['login_password'];
            $software_id_relation = $row['software_id'];

          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editSoftwareModal<?php echo $software_id; ?>"><?php echo $software_name; ?></a></td>
            <td><?php echo $software_type; ?></td>
            <td><?php echo $software_license_display; ?></td>
            <td>
              <?php
              if($software_id == $software_id_relation){
              ?>  
              <button type="button" class="btn btn-dark btn-sm" data-toggle="modal" data-target="#viewPasswordModal<?php echo $login_id; ?>"><i class="fas fa-key"></i></button>

              <div class="modal" id="viewPasswordModal<?php echo $login_id; ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content bg-dark">
                    <div class="modal-header">
                      <h5 class="modal-title"><i class="fa fa-fw fa-key"></i> <?php echo $software_name; ?></h5>
                      <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                      </button>
                    </div>
                    <div class="modal-body bg-white">
                      <div class="form-group">
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-user"></i></span>
                          </div>
                          <input type="text" class="form-control" value="<?php echo $login_username; ?>" readonly>
                        </div>
                      </div>
                      <div class="form-group">
                        <div class="input-group">
                          <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-lock"></i></span>
                          </div>
                          <input type="text" class="form-control" value="<?php echo $login_password; ?>" readonly>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <?php
              }
              ?>
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editSoftwareModal<?php echo $software_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?delete_software=<?php echo $software_id; ?>">Delete</a>
                </div>
              </div> 
            </td>
          </tr>

          <?php

          include("edit_software_modal.php");
          }
          
          ?>

        </tbody>
      </table>      
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php include("add_software_modal.php"); ?>