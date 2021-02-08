<?php include("header.php");

  //Paging
  if(isset($_GET['p'])){
    $p = intval($_GET['p']);
    $record_from = (($p)-1)*$config_records_per_page;
    $record_to = $config_records_per_page;
  }else{
    $record_from = 0;
    $record_to = $config_records_per_page;
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
    $sb = "name";
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

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM users, permissions
    WHERE  users.user_id = permissions.user_id
    AND (name LIKE '%$q%' OR email LIKE '%$q%')
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-users"></i> Users</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addUserModal"><i class="fas fa-fw fa-plus"></i> New User</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Users">
        <div class="input-group-append">
          <button class="btn btn-primary"><i class="fa fa-search"></i></button>
        </div>
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th class="text-center"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=email&o=<?php echo $disp; ?>">Email</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=Permission_level&o=<?php echo $disp; ?>">Access Level</a></th>
            <th>Status</th>
            <th>Last Login</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $user_id = $row['user_id'];
            $name = $row['name'];
            $email = $row['email'];
            $password = $row['password'];
            $avatar = $row['avatar'];
            $permission_default_company = $row['permission_default_company'];
            $permission_level = $row['permission_level'];
            if($permission_level == 5){
              $permission_level_display = "Global Administrator";
            }elseif($permission_level == 4){
              $permission_level_display = "Administrator";
            }elseif($permission_level == 3){
              $permission_level_display = "Technician";
            }elseif($permission_level == 2){
              $permission_level_display = "IT Contractor";
            }else{
              $permission_level_display = "Accounting";  
            }
            $permission_companies = $row['permission_companies'];
            $permission_companies_array = explode(",",$permission_companies); 
            $permission_clients = $row['permission_clients'];
            $permission_clients_array = explode(",",$permission_clients);
            $permission_actions = $row['permission_actions'];
            $initials = initials($name);

            $sql_last_login = mysqli_query($mysqli,"SELECT * FROM logs 
              WHERE user_id = $user_id AND log_type = 'Login'
              ORDER BY log_id DESC LIMIT 1"
            );
            $row = mysqli_fetch_array($sql_last_login);
            $log_created_at = $row['log_created_at'];
            $last_login = $row['log_description'];
            if(empty($last_login)){
              $last_login = "Never logged in"; 
            }
  
          ?>
          <tr>
            <td class="text-center">
              <a class="text-dark" href="#" data-toggle="modal" data-target="#editUserModal<?php echo $user_id; ?>">
                <?php if(!empty($avatar)){ ?>
                <img height="48" width="48" class="img-fluid rounded-circle" src="<?php echo $avatar; ?>">
                <?php }else{ ?>
                <span class="fa-stack fa-2x">
                  <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                  <span class="fa fa-stack-1x text-white"><?php echo $initials; ?></span>
                </span>
                <br>
                <?php } ?>

                <div class="text-secondary"><?php echo $name; ?></div>
              </a>
            </td>
            <td><a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a></td>
            <td><?php echo $permission_level_display; ?></td>
            <td>-</td>
            <td><?php echo $log_created_at; ?> <br> <small class="text-secondary"><?php echo $log_description; ?></small></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editUserModal<?php echo $user_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editUserCompaniesModal<?php echo $user_id; ?>">Company Access</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editUserClientsModal<?php echo $user_id; ?>">Client Access</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="post.php?archive_user=<?php echo $user_id; ?>">Archive</a>
                </div>
              </div>   
            </td>
          </tr>

          <?php

          include("edit_user_modal.php");
          include("user_companies_modal.php");
          include("user_clients_modal.php");
          
          }
      
          ?>

        </tbody>
      </table>

      <?php include("pagination.php"); ?>
      
    </div>
  </div>
</div>

<?php
  
  include("add_user_modal.php");
  
  include("footer.php");

?>