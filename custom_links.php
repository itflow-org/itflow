<?php include("inc_all_admin.php"); 

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
  $sb = "custom_link_name";
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM custom_links 
  WHERE custom_link_name LIKE '%$q%'
  AND company_id = $session_company_id 
  ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>


<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-link"></i> Custom Links</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCustomLinkModal"><i class="fas fa-fw fa-plus"></i> New</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="row">
        <div class="col-sm-4 mb-2">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search custom links">
            <div class="input-group-append">
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=custom_link_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=custom_link_url&o=<?php echo $disp; ?>">URL</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php

          while($row = mysqli_fetch_array($sql)){
            $custom_link_id = $row['custom_link_id'];
            $custom_link_name = $row['custom_link_name'];
            $custom_link_icon = $row['custom_link_icon'];
            if(empty($custom_link_icon)){
              $custom_link_icon_display = "<i class='fa fa-fw fa-circle mr-2'></i>";
            }else{
              $custom_link_icon_display = "<i class='fa fa-fw fa-$custom_link_icon mr-2'></i>";
            }
            $custom_link_url = $row['custom_link_url'];
      
          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editCustomLinkModal<?php echo $custom_link_id; ?>"><?php echo "$custom_link_icon_display $custom_link_name"; ?></a></td>
            <td><?php echo $custom_link_url; ?><a href="//<?php echo $custom_link_url; ?>" target="_blank"><i class="fa fa-fw fa-link text-info"></i></a></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editCustomLinkModal<?php echo $custom_link_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?delete_custom_link=<?php echo $custom_link_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php

          include("custom_link_edit_modal.php");

          }

          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php 

  include("custom_link_add_modal.php");

  include("footer.php");

?>