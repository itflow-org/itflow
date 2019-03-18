<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_assets WHERE client_id = $client_id ORDER BY client_asset_id DESC"); ?>

<div class="table-responsive">
  <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Type</th>
        <th>Name</th>
        <th>Make</th>
        <th>Serial</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
  
      while($row = mysqli_fetch_array($sql)){
        $client_asset_id = $row['client_asset_id'];
        $client_asset_type = $row['client_asset_type'];
        $client_asset_name = $row['client_asset_name'];
        $client_asset_make = $row['client_asset_make'];
        $client_asset_model = $row['client_asset_model'];
        $client_asset_serial = $row['client_asset_serial'];
  
      ?>
      <tr>
        <td><?php echo "$client_asset_type"; ?></td>
        <td><?php echo "$client_asset_name"; ?></td>
        <td><?php echo "$client_asset_make $client_asset_model"; ?></td>
        <td><?php echo "$client_asset_serial"; ?></td>
        <td>
          <div class="dropdown dropleft text-center">
            <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="fas fa-ellipsis-h"></i>
            </button>
            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientAssetModal<?php echo $client_asset_id; ?>">Edit</a>
              <a class="dropdown-item" href="#">Delete</a>
            </div>
          </div>      
        </td>
      </tr>

      <?php
      include("edit_client_asset_modal.php");
      }
      ?>

    </tbody>
  </table>
</div>