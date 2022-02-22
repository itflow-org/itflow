<?php include("inc_all.php"); 

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
  $sb = "tag_name";
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

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM tags 
  WHERE tag_name LIKE '%$q%'
  AND company_id = $session_company_id 
  ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

if($num_row > 0){
//Colors Used
$sql_colors_used = mysqli_query($mysqli,"SELECT tag_color FROM tags 
  WHERE tag_archived_at IS NULL
  AND company_id = $session_company_id"
);

while($color_used_row = mysqli_fetch_array($sql_colors_used)){
  $colors_used_array[] = $color_used_row['tag_color'];
}
$colors_diff = array_diff($colors_array,$colors_used_array);

}else{
  $colors_diff = $colors_array;
}

?>


<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-tags"></i> Tags</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTagModal"><i class="fas fa-fw fa-plus"></i> New</button>
    </div>
  </div>
  
  <div class="card-body"> 
    <div class="row">
      <div class="col-sm-4 mb-2">
        <form autocomplete="off">  
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Tags">
            <div class="input-group-append">
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </form>
      </div>
      <div class="col-sm-8">
      </div>
    </div>
    
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=tag_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=tag_type&o=<?php echo $disp; ?>">Type</a></th>
            <th>Color</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php

          while($row = mysqli_fetch_array($sql)){
            $tag_id = $row['tag_id'];
            $tag_name = $row['tag_name'];
            $tag_type = $row['tag_type'];
            $tag_color = $row['tag_color'];
            $tag_icon = $row['tag_icon'];
      
          ?>
          <tr>
            <td><?php echo "<i class='fa fa-fw fa-$tag_icon'></i>"; ?> <a class="text-dark" href="#" data-toggle="modal" data-target="#editTagModal<?php echo $tag_id; ?>"><?php echo "$tag_name"; ?></a></td>
            <td><?php echo $tag_type; ?></td>
            <td><i class="fa fa-3x fa-circle" style="color:<?php echo $tag_color; ?>;"></i></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTagModal<?php echo $tag_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?delete_tag=<?php echo $tag_id; ?>">Delete</a>
                </div>
              </div>
            </td>
          </tr>

          <?php

          include("tag_edit_modal.php");

          }

          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php 

  include("tag_add_modal.php");

  include("footer.php");

?>