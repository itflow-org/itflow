<?php include("inc_all_settings.php");

  if(!empty($_GET['sb'])){
    $sb = strip_tags(mysqli_real_escape_string($mysqli,$_GET['sb']));
  }else{
    $sb = "api_key_name";
  }

  //Rebuild URL
  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM api_keys
    LEFT JOIN clients on api_keys.api_key_client_id = clients.client_id
    WHERE (api_key_name LIKE '%$q%')
    AND api_keys.company_id = $session_company_id
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-key"></i> API Keys</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addApiKeyModal"><i class="fas fa-fw fa-plus"></i> New Key</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if(isset($q)){ echo stripslashes(htmlentities($q)); } ?>" placeholder="Search keys">
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
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=api_key_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=api_key_client_id&o=<?php echo $disp; ?>">Client</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=api_key_secret&o=<?php echo $disp; ?>">Secret</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=api_key_created_at&o=<?php echo $disp; ?>">Created</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=api_key_expire&o=<?php echo $disp; ?>">Expire</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $api_key_id = $row['api_key_id'];
            $api_key_name = htmlentities($row['api_key_name']);
            $api_key_secret = htmlentities("************" . substr($row['api_key_secret'], -4));
            $api_key_created_at = $row['api_key_created_at'];
            $api_key_expire = $row['api_key_expire'];
            if($api_key_expire < date("Y-m-d H:i:s")){
              $api_key_expire = $api_key_expire . " (Expired)";
            }

            if($row['api_key_client_id'] == 0){
              $api_key_client = "<i>All Clients</i>";
            }
            else{
              $api_key_client = htmlentities($row['client_name']);
            }
  
          ?>
          <tr>
            <td><?php echo $api_key_name; ?></td>
            <td><?php echo $api_key_client; ?></td>
            <td><?php echo $api_key_secret; ?></td>
            <td><?php echo $api_key_created_at; ?></td>
            <td><?php echo $api_key_expire; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item text-danger" href="post.php?delete_api_key=<?php echo $api_key_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">Revoke</a>
                </div>
              </div>   
            </td>
          </tr>

          <?php
          
          }
      
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php
  
  include("api_key_add_modal.php");
  
  include("footer.php");

?>