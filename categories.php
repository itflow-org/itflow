<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM categories ORDER BY category_id DESC"); ?>


<div class="card mb-3">
  <div class="card-header">
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addCategoryModal"><i class="fas fa-plus"></i> New</button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Color</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $category_id = $row['category_id'];
            $category_name = $row['category_name'];
            $category_type = $row['category_type'];
            $category_color = $row['category_color'];
      
          ?>
          <tr>
            <td><?php echo "$category_name"; ?></td>
            <td><?php echo "$category_type"; ?></td>
            <td><i class="fa fa-2x fa-circle" style="color:<?php echo $category_color; ?>;"></i></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editCategoryModal<?php echo $category_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_category=<?php echo $category_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_category_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_category_modal.php"); ?>

<?php include("footer.php");