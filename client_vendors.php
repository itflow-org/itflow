<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_vendors WHERE client_id = $client_id ORDER BY client_vendor_id DESC"); ?>

<div class="table-responsive">
  <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Vendor</th>
        <th>Description</th>
        <th>Account Number</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
  
      while($row = mysqli_fetch_array($sql)){
        $client_vendor_id = $row['client_vendor_id'];
        $client_vendor_name = $row['client_vendor_name'];
        $client_vendor_description = $row['client_vendor_description'];
        $client_vendor_account_number = $row['client_vendor_account_number'];
          
      ?>
      <tr>
        <td><?php echo $client_vendor_name; ?></td>
        <td><?php echo $client_vendor_description; ?></td>
        <td><?php echo $client_vendor_account_number; ?></td>

        <td>
          <div class="dropdown dropleft text-center">
            <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="fas fa-ellipsis-h"></i>
            </button>
            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientVendorModal<?php echo $client_vendor_id; ?>">Edit</a>
              <a class="dropdown-item" href="post.php?delete_client_vendor=<?php echo $client_vendor_id; ?>">Delete</a>
            </div>
          </div>      
        </td>
      </tr>

      <?php
      include("edit_client_vendor_modal.php");
      }
      ?>

    </tbody>
  </table>
</div>