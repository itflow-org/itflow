function populateNetworkEditModal(client_id, network_id) {

    // Send a GET request to post.php as post.php?network_get_json_details=true&client_id=NUM&network_id=NUM
    jQuery.get(
        "ajax.php",
        {network_get_json_details: 'true', client_id: client_id, network_id: network_id},
        function(data) {

            // If we get a response from post.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the network (only one!) and locations (possibly multiple)
            const network = response.network[0];
            const locations = response.locations;

            // Populate the network modal fields
            document.getElementById("editNetworkHeader").innerText = network.network_name;
            document.getElementById("editNetworkId").value = network_id;
            document.getElementById("showNetworkId").innerText = "Network ID: " + network_id;
            document.getElementById("editNetworkName").value = network.network_name;
            document.getElementById("editNetworkDescription").value = network.network_description;
            document.getElementById("editNetworkVlan").value = network.network_vlan;
            document.getElementById("editNetworkCidr").value = network.network;
            document.getElementById("editNetworkSubnet").value = network.network_subnet;
            document.getElementById("editNetworkGw").value = network.network_gateway;
            document.getElementById("editNetworkPrimaryDNS").value = network.network_primary_dns;
            document.getElementById("editNetworkSecondaryDNS").value = network.network_secondary_dns;
            document.getElementById("editNetworkDhcp").value = network.network_dhcp_range;
            document.getElementById("editNetworkNotes").value = network.network_notes;

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
                if (parseInt(location.location_id) == parseInt(network.network_location_id)) {
                    locationDropdown[locationDropdown.length] = new Option(location.location_name, location.location_id, true, true);
                }
                else{
                    locationDropdown[locationDropdown.length] = new Option(location.location_name, location.location_id);
                }
            });
        }
    );
}
