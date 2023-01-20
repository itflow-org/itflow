<?php include("inc_all_settings.php");

  if(!empty($_GET['sb'])){
    $sb = strip_tags(mysqli_real_escape_string($mysqli,$_GET['sb']));
  }else{
    $sb = "user_name";
  }

  //Rebuild URL
  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM users, user_settings
    WHERE users.user_id = user_settings.user_id
    AND (user_name LIKE '%$q%' OR user_email LIKE '%$q%')
    AND user_archived_at IS NULL
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-users"></i> Users</h3>
    <div class="card-tools">
      <div class="btn-group">
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addUserModal">
          <i class="fas fa-fw fa-plus"></i> New User
        </button>
        <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
        <div class="dropdown-menu">
          <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#userInviteModal"><i class="fas fa-paper-plane mr-2"></i>Invite User</a>
        </div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if(isset($q)){echo strip_tags(htmlentities($q));} ?>" placeholder="Search Users">
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
            <th class="text-center"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=user_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=user_email&o=<?php echo $disp; ?>">Email</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=user_role&o=<?php echo $disp; ?>">Role</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=user_status&o=<?php echo $disp; ?>">Status</a></th>
            <th>Last Login</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $user_id = $row['user_id'];
            $user_name = htmlentities($row['user_name']);
            $user_email = htmlentities($row['user_email']);
            $user_status = intval($row['user_status']);
            if($user_status == 2){
              $user_status_display = "<span class='text-info'>Invited</span>";
            }elseif($user_status == 1){
              $user_status_display = "<span class='text-success'>Active</span>";
            }else{
              $user_status_display = "<span class='text-danger'>Disabled</span>";  
            }
            $user_avatar = htmlentities($row['user_avatar']);
            $user_token = htmlentities($row['user_token']);
            $user_default_company = $row['user_default_company'];
            $user_role = $row['user_role'];
            if($user_role == 3){
              $user_role_display = "Administrator";
            }elseif($user_role == 2){
              $user_role_display = "Technician";
            }else{
              $user_role_display = "Accountant";  
            }
            $user_company_access_sql = mysqli_query($mysqli,"SELECT company_id FROM user_companies WHERE user_id = $user_id");
            $user_company_access_array = array();
            while($row = mysqli_fetch_array($user_company_access_sql)){
              $user_company_access_array[] = $row['company_id'];
            }
            $user_company_access = implode(',',$user_company_access_array);

            $user_initials = htmlentities(initials($user_name));

            $sql_last_login = mysqli_query($mysqli,"SELECT * FROM logs 
              WHERE log_user_id = $user_id AND log_type = 'Login'
              ORDER BY log_id DESC LIMIT 1"
            );
            $row = mysqli_fetch_array($sql_last_login);
            $log_created_at = $row['log_created_at'];
            $log_ip = htmlentities($row['log_ip']);
            $log_user_agent = htmlentities($row['log_user_agent']);
            $log_user_os = get_os($log_user_agent);
            $log_user_browser = get_web_browser($log_user_agent);
            $last_login = "$log_user_os<br>$log_user_browser<br><i class='fa fa-fw fa-globe'></i> $log_ip";
            if(empty($last_login)){
              $last_login = "Never logged in"; 
            }
  
          ?>
          <tr>
            <td class="text-center">
              <a class="text-dark" href="#" data-toggle="modal" data-target="#editUserModal<?php echo $user_id; ?>">
                <?php if(!empty($user_avatar)){ ?>
                <img class="img-size-50 img-circle" src="<?php echo "uploads/users/$user_id/$user_avatar"; ?>">
                <?php }else{ ?>
                <span class="fa-stack fa-2x">
                  <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                  <span class="fa fa-stack-1x text-white"><?php echo $user_initials; ?></span>
                </span>
                <br>
                <?php } ?>

                <div class="text-secondary"><?php echo $user_name; ?></div>
              </a>
            </td>
            <td><a href="mailto:<?php echo $user_email; ?>"><?php echo $user_email; ?></a></td>
            <td><?php echo $user_role_display; ?></td>
            <td><?php echo $user_status_display; ?></td>
            <td>
              <?php echo $log_created_at; ?> 
              <br>
              <small class="text-secondary"><?php echo $last_login; ?></small>
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editUserModal<?php echo $user_id; ?>">Edit</a>
                  <?php if($user_status == 0){ ?>
                  <a class="dropdown-item text-success" href="post.php?activate_user=<?php echo $user_id; ?>">Activate</a>
                  <?php }elseif($user_status == 1){ ?>
                  <a class="dropdown-item text-danger" href="post.php?disable_user=<?php echo $user_id; ?>">Disable</a>
                  <?php } ?>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editUserCompaniesModal<?php echo $user_id; ?>">Company Access</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#archiveUserModal<?php echo $user_id; ?>">Archive</a>
                </div>
              </div>   
            </td>
          </tr>

          <?php

          include("user_edit_modal.php");
          include("user_companies_modal.php");
          include("user_archive_modal.php");
          
          }
      
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>
<script>
    function generatePassword(){
        document.getElementById("password").value = "<?php echo keygen() ?>"
    }
</script>

<?php
  
  include("user_add_modal.php");
  include("user_invite_modal.php");
  
  include("footer.php");

?>