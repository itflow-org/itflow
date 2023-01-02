<?php include("inc_all_settings.php"); 

if(isset($_GET['category'])){
  $category = strip_tags(mysqli_real_escape_string($mysqli,$_GET['category']));
}else{
  $category = "Expense";
}

if(!empty($_GET['sb'])){
  $sb = strip_tags(mysqli_real_escape_string($mysqli,$_GET['sb']));
}else{
  $sb = "category_name";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM categories 
  WHERE category_name LIKE '%$q%'
  AND category_type = '$category'
  AND category_archived_at IS NULL
  AND company_id = $session_company_id 
  ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

//Colors Used
$sql_colors_used = mysqli_query($mysqli,"SELECT category_color FROM categories 
  WHERE category_type = '$category'
  AND category_archived_at IS NULL
  AND company_id = $session_company_id"
);

while($color_used_row = mysqli_fetch_array($sql_colors_used)){
  $colors_used_array[] = $color_used_row['category_color'];
}
$colors_diff = array_diff($colors_array,$colors_used_array);

?>


<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-list"></i> <?php echo htmlentities($category); ?> Categories</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCategoryModal"><i class="fas fa-fw fa-plus"></i> New</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="category" value="<?php echo htmlentities($category); ?>">
      <div class="row">
        <div class="col-sm-4 mb-2">
          <div class="input-group">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){ echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Categories">
            <div class="input-group-append">
              <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>
        <div class="col-sm-8">
          <div class="btn-group float-right">
            <a href="?category=Expense" class="btn <?php if($category == 'Expense'){ echo 'btn-primary'; }else{ echo 'btn-default'; } ?>">Expense</a>
            <a href="?category=Income" class="btn <?php if($category == 'Income'){ echo 'btn-primary'; }else{ echo 'btn-default'; } ?>">Income</a>
            <a href="?category=Referral" class="btn <?php if($category == 'Referral'){ echo 'btn-primary'; }else{ echo 'btn-default'; } ?>">Referral</a>
            <a href="?category=Payment Method" class="btn <?php if($category == 'Payment Method'){ echo 'btn-primary'; }else{ echo 'btn-default'; } ?>">Payment Method</a>
          </div>
        </div>
      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Name</a></th>
            <th>Color</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php

          while($row = mysqli_fetch_array($sql)){
            $category_id = $row['category_id'];
            $category_name = htmlentities($row['category_name']);
            $category_color = htmlentities($row['category_color']);
            //$colors_used_array[] = $row['category_color'];
      
          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editCategoryModal<?php echo $category_id; ?>"><?php echo "$category_name"; ?></a></td>
            <td><i class="fa fa-3x fa-circle" style="color:<?php echo $category_color; ?>;"></i></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editCategoryModal<?php echo $category_id; ?>">Edit</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item text-danger" href="post.php?archive_category=<?php echo $category_id; ?>">Archive</a>
                </div>
              </div>
            </td>
          </tr>

          <?php

          //$colors_diff = array_diff($colors_array,$colors_used_array);

          include("category_edit_modal.php");

          }

          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php 

  include("category_add_modal.php");

  include("footer.php");

?>