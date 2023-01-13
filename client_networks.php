<?php include("inc_all_client.php"); ?>

<?php

if(!empty($_GET['sb'])){
  $sb = strip_tags(mysqli_real_escape_string($mysqli,$_GET['sb']));
}else{
  $sb = "network_name";
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET,array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query($mysqli,"SELECT SQL_CALC_FOUND_ROWS * FROM networks
  LEFT JOIN locations ON location_id = network_location_id
  WHERE network_client_id = $client_id 
  AND (network_name LIKE '%$q%' OR network_vlan LIKE '%$q%' OR network LIKE '%$q%' OR network_gateway LIKE '%$q%' OR network_dhcp_range LIKE '%$q%' OR location_name LIKE '%$q%') 
  ORDER BY $sb $o LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli,"SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
  <div class="card-header py-2">
    <h3 class="card-title mt-2"><i class="fa fa-fw fa-network-wired"></i> Networks</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addNetworkModal"><i class="fas fa-fw fa-plus"></i> New Network</button>
    </div>
  </div>
  <div class="card-body">
    <form autocomplete="off">
      <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
      <div class="row">
        
        <div class="col-md-4">
          <div class="input-group mb-3 mb-md-0">
            <input type="search" class="form-control" name="q" value="<?php if(isset($q)){ echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Networks">
            <div class="input-group-append">
              <button class="btn btn-dark"><i class="fa fa-search"></i></button>
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="float-right">
            <a href="post.php?export_client_networks_csv=<?php echo $client_id; ?>" class="btn btn-default"><i class="fa fa-fw fa-download"></i> Export</a>
          </div>
        </div>

      </div>
    </form>
    <hr>
    <div class="table-responsive">
      <table class="table table-striped table-borderless table-hover">
        <thead class="text-dark <?php if($num_rows[0] == 0){ echo "d-none"; } ?>">
          <tr>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=network_name&o=<?php echo $disp; ?>">Name</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=network_vlan&o=<?php echo $disp; ?>">vLAN</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=network&o=<?php echo $disp; ?>">Network</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=network_gateway&o=<?php echo $disp; ?>">Gateway</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=network_dhcp_range&o=<?php echo $disp; ?>">DHCP Range</a></th>
            <th><a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=location_name&o=<?php echo $disp; ?>">Location</a></th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
      
          while($row = mysqli_fetch_array($sql)){
            $network_id = $row['network_id'];
            $network_name = htmlentities($row['network_name']);
            $network_vlan = htmlentities($row['network_vlan']);
            if(empty($network_vlan)){
              $network_vlan_display = "-";
            }else{
              $network_vlan_display = $network_vlan;
            }
            $network = htmlentities($row['network']);
            $network_gateway = htmlentities($row['network_gateway']);
            $network_dhcp_range = htmlentities($row['network_dhcp_range']);
            if(empty($network_dhcp_range)){
              $network_dhcp_range_display = "-";
            }else{
              $network_dhcp_range_display = $network_dhcp_range;
            }
            $network_location_id = $row['network_location_id'];
            $location_name = htmlentities($row['location_name']);
            if(empty($location_name)){
              $location_name_display = "-";
            }else{
              $location_name_display = $location_name;
            }
      
          ?>
          <tr>
            <th>
              <i class="fa fa-fw fa-network-wired text-secondary"></i> 
              <a class="text-dark" href="#" data-toggle="modal" onclick="populateNetworkEditModal(<?php echo $client_id, ",", $network_id ?>)" data-target="#editNetworkModal"><?php echo $network_name; ?></a></th>
            <td><?php echo $network_vlan_display; ?></td>
            <td><?php echo $network; ?></td>
            <td><?php echo $network_gateway; ?></td>
            <td><?php echo $network_dhcp_range_display; ?></td>
            <td><?php echo $location_name_display; ?></td>
            <td>
              <div class="dropdown dropleft text-center">
                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#" data-toggle="modal" onclick="populateNetworkEditModal(<?php echo $client_id, ",", $network_id ?>)" data-target="#editNetworkModal">Edit</a>
                  <?php if($session_user_role == 3) { ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="post.php?delete_network=<?php echo $network_id; ?>">Delete</a>
                  <?php } ?>
                </div>
              </div>
            </td>
          </tr>

          <?php

          }
          
          ?>

        </tbody>
      </table>
    </div>
    <?php include("pagination.php"); ?>
  </div>
</div>

<?php

include("client_network_edit_modal.php");
include("client_network_add_modal.php");

?>

<script>
function populateNetworkEditModal(client_id, network_id) {
  
  // Send a GET request to post.php as post.php?network_get_json_details=true&client_id=NUM&network_id=NUM
  jQuery.get(
    "ajax.php",
    {network_get_json_details: 'true', client_id: client_id, network_id: network_id},
    function(data){

      // If we get a response from post.php, parse it as JSON
      const response = JSON.parse(data);

      // Access the network (only one!) and locations (possibly multiple)
      const network = response.network[0];
      const locations = response.locations;

      // Populate the network modal fields
      document.getElementById("editNetworkHeader").innerText = network.network_name;
      document.getElementById("editNetworkId").value = network_id;
      document.getElementById("editNetworkName").value = network.network_name;
      document.getElementById("editNetworkVlan").value = network.network_vlan;
      document.getElementById("editNetworkCidr").value = network.network;
      document.getElementById("editNetworkGw").value = network.network_gateway;
      document.getElementById("editNetworkDhcp").value = network.network_dhcp_range;

      // Select the location dropdown
      var locationDropdown = document.getElementById("editNetworkLocation");

      // Clear location dropdown
      var i, L = locationDropdown.options.length -1;
      for(i = L; i >= 0; i--) {
        locationDropdown.remove(i);
      }
      locationDropdown[locationDropdown.length] = new Option('- Location -', '0');

      // Populate location dropdown
      locations.forEach(location => {
        if(parseInt(location.location_id) == parseInt(network.network_location_id)){
            locationDropdown[locationDropdown.length] = new Option(location.location_name, location.location_id, true, true);
        }
        else{
            locationDropdown[locationDropdown.length] = new Option(location.location_name, location.location_id);
        }
      });
    }
  );
}
</script>

<?php include("footer.php"); ?>