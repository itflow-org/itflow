<?php $sql = mysqli_query($mysqli,"SELECT * FROM networks WHERE client_id = $client_id ORDER BY network_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-network-wired"></i> Networks</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addNetworkModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Network</th>
            <th>Gateway</th>
            <th>DHCP Range</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $network_id = $row['network_id'];
            $network_name = $row['network_name'];
            $network = $row['network'];
            $network_gateway = $row['network_gateway'];
            $network_dhcp_range = $row['network_dhcp_range'];
            $network_dhcp_range = $row['network_dhcp_range'];
            $location_id = $row['location_id'];
      
          ?>
          <tr>
            <td><?php echo $network_name; ?></td>
            <td><?php echo $network; ?></td>
            <td><?php echo $network_gateway; ?></td>
            <td><?php echo $network_dhcp_range; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editNetworkModal<?php echo $network_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_network=<?php echo $network_id; ?>">Delete</a>
                </div>
              </div>
              <?php include("edit_network_modal.php"); ?>      
            </td>
          </tr>

          <?php
          
          }
          
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_network_modal.php"); ?>