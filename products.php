<?php include("header.php");

  //Rebuild URL

  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

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
    $sb = "product_name";
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

  $sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM products, categories WHERE products.category_id = categories.category_id AND products.company_id = $session_company_id AND (product_name LIKE '%$q%' OR category_name LIKE '%$q%') ORDER BY $sb $o LIMIT $record_from, $record_to");

  $num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));
  $total_found_rows = $num_rows[0];
  $total_pages = ceil($total_found_rows / 10);

?>


<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-2"><i class="fa fa-fw fa-box mr-2"></i>Products</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addProductModal"><i class="fas fa-fw fa-plus"></i></button>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <div class="input-group">
        <input type="search" class="form-control col-md-4" name="q" value="<?php if(isset($q)){echo stripslashes($q);} ?>" placeholder="Search Products">
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
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=product_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=category_name&o=<?php echo $disp; ?>">Category</a></th>
            <th>Description</th>
            <th class="text-right"><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=product_cost&o=<?php echo $disp; ?>">Cost</a></th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $product_id = $row['product_id'];
            $product_name = $row['product_name'];
            $product_description = $row['product_description'];
            $product_cost = $row['product_cost'];
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];

          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editProductModal<?php echo $product_id; ?>"><?php echo $product_name; ?></a></td>
            <td><?php echo $category_name; ?></td>
            <td><?php echo $product_description; ?></td>
            <td class="text-right">$<?php echo number_format($product_cost,2); ?></td>      
          </tr>

          <?php include("edit_product_modal.php"); ?>

          <?php
          
          }
          
          ?>

        </tbody>
      </table>

      <?php include("pagination.php"); ?>

    </div>
  </div>
</div>

<?php include("add_product_modal.php"); ?>

<?php include("footer.php");