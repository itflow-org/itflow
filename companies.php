<?php include("header.php");

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
    $sb = "company_name";
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

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM companies, settings
    WHERE companies.company_id = settings.company_id 
    AND companies.company_name LIKE '%$q%'
    ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
  $total_found_rows = $num_rows[0];
  $total_pages = ceil($total_found_rows / 10);

?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-building mr-2"></i>Companies</h6>
    <button type="button" class="btn btn-primary btn-sm mr-auto float-right" data-toggle="modal" data-target="#addCompanyModal"><i class="fas fa-fw fa-plus"></i></button>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Companies">
        <div class="input-group-append">
          <button class="btn btn-primary"><i class="fa fa-search"></i></button>
        </div>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th class="text-center"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=company_name&o=<?php echo $disp; ?>">Name</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $company_id = $row['company_id'];
            $company_name = $row['company_name'];
            $company_country = $row['company_country'];
            $company_address = $row['company_address'];
            $company_city = $row['company_city'];
            $company_state = $row['company_state'];
            $company_zip = $row['company_zip'];
            $company_phone = $row['company_phone'];
            $company_site = $row['company_site'];
            $company_logo = $row['company_invoice_logo'];
            
            $initials = initials($company_name);
      
          ?>
          <tr>
            <td class="text-center">
              <a class="text-dark" href="#" data-toggle="modal" data-target="#editCompanyModal<?php echo $company_id; ?>">
                <?php if(!empty($company_logo)){ ?>
                <img height="48" width="48" class="img-fluid rounded-circle" src="<?php echo $company_logo; ?>">
                <?php }else{ ?>
                <span class="fa-stack fa-2x">
                  <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                  <span class="fa fa-stack-1x text-white"><?php echo $initials; ?></span>
                </span>
                <br>
                <?php } ?>

                <div class="text-secondary"><?php echo $company_name; ?></div>
              </a>
            </td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editCompanyModal<?php echo $company_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_company=<?php echo $company_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("edit_company_modal.php"); ?>      
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

<?php include("add_company_modal.php"); ?>

<?php include("footer.php");