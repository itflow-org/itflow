<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM vendors ORDER BY vendor_id DESC"); ?>


<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-briefcase"></i> Vendors</h6>
    <button type="button" class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addVendorModal"><i class="fas fa-plus"></i> Add New</button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Website</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $vendor_id = $row['vendor_id'];
            $vendor_name = $row['vendor_name'];
            $vendor_email = $row['vendor_email'];
            $vendor_phone = $row['vendor_phone'];
            if(strlen($vendor_phone)>2){ 
              $vendor_phone = substr($row['vendor_phone'],0,3)."-".substr($row['vendor_phone'],3,3)."-".substr($row['vendor_phone'],6,4);
            }
            $vendor_website = $row['vendor_website'];
      
          ?>
          <tr>
            <td><?php echo "$vendor_name"; ?></td>
            <td><?php echo "$vendor_email"; ?></td>
            <td><?php echo "$vendor_phone"; ?></td>
            <td><?php echo "$vendor_website"; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editvendorModal<?php echo $vendor_id; ?>">Edit</a>
                  <a class="dropdown-item" href="#">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_vendor_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_vendor_modal.php"); ?>

<?php include("footer.php");