<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM products ORDER BY product_name ASC"); ?>


<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-box"></i> Products</h6>
    <button type="button" class="btn btn-primary btn-sm mr-auto float-right" data-toggle="modal" data-target="#addProductModal"><i class="fas fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead class="thead-dark">
          <tr>
            <th>Name</th>
            <th>Description</th>
            <th class="text-right">Cost</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $product_id = $row['product_id'];
            $product_name = $row['product_name'];
            $product_description = $row['product_description'];
            $product_cost = $row['product_cost'];

          ?>
          <tr>
            <td><?php echo $product_name; ?></td>
            <td><?php echo $product_description; ?></td>
            <td class="text-right text-monospace">$<?php echo number_format($product_cost,2); ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editProductModal<?php echo $product_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_product=<?php echo $product_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_product_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_product_modal.php"); ?>

<?php include("footer.php");