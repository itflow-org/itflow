<?php include("header.php"); 

  //Rebuild URL

  $url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

  if(!empty($_GET['sb'])){
    $sb = mysqli_real_escape_string($mysqli,$_GET['sb']);
  }else{
    $sb = "tax_name";
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

  $sql = mysqli_query($mysqli,"SELECT * FROM taxes WHERE company_id = $session_company_id ORDER BY $sb $o");

  $num_rows = mysqli_num_rows($sql);

  ?>

<div class="card mb-3">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-balance-scale mr-2"></i>Taxes</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addTaxModal"><i class="fas fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=tax_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-dark" href="?<?php echo $url_query_strings_sb; ?>&sb=tax_percent&o=<?php echo $disp; ?>">Percent</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $tax_id = $row['tax_id'];
            $tax_name = $row['tax_name'];
            $tax_percent = $row['tax_percent'];
      
          ?>
          <tr>
            <td><a class="text-dark" href="#" data-toggle="modal" data-target="#editTaxModal<?php echo $tax_id; ?>"><?php echo "$tax_name"; ?></a></td>
            <td><?php echo "$tax_percent%"; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTaxModal<?php echo $tax_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_tax=<?php echo $tax_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("edit_tax_modal.php"); ?> 
            </td>
          </tr>

          <?php
          }
          ?>

          <?php
          if($num_rows == 0){
            echo "<center><h3 class='text-secondary mt-3'>No Records Here</h3></center>";
          }
          ?>

        </tbody>
      </table>

    </div>
  </div>
</div>

<?php include("add_tax_modal.php"); ?>

<?php include("footer.php");