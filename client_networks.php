<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_networks WHERE client_id = $client_id ORDER BY client_network_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-network-wired"></i> Networks</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addClientNetworkModal"><i class="fa fa-plus"></i></button>
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
            $client_network_id = $row['client_network_id'];
            $client_network_name = $row['client_network_name'];
            $client_network = $row['client_network'];
            $client_network_gateway = $row['client_network_gateway'];
            $client_network_dhcp_range = $row['client_network_dhcp_range'];

      
          ?>
          <tr>
            <td><?php echo $client_network_name; ?></td>
            <td><?php echo $client_network; ?></td>
            <td><?php echo $client_network_gateway; ?></td>
            <td><?php echo $client_network_dhcp_range; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientNetworkModal<?php echo $client_network_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_client_network=<?php echo $client_network_id; ?>">Delete</a>
                </div>
              </div>      
            </td>
          </tr>

          <?php
          include("edit_client_network_modal.php");
          }
          ?>

        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("add_client_network_modal.php"); ?>