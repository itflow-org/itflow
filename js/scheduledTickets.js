function populateScheduledTicketEditModal(client_id, ticket_id) {

    // Send a GET request to ajax.php as ajax.php?scheduled_ticket_get_json_details=true&client_id=NUM&ticket_id=NUM
    jQuery.get(
        "ajax.php",
        {scheduled_ticket_get_json_details: 'true', client_id: client_id, ticket_id: ticket_id},
        function(data){

            // If we get a response from post.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the ticket info, and all potential assets
            const ticket = response.ticket[0];
            const assets = response.assets;

            // Populate the scheduled ticket modal fields
            document.getElementById("editHeader").innerText = " Edit Scheduled ticket: " + ticket.scheduled_ticket_subject;
            document.getElementById("editTicketId").value = ticket_id;
            document.getElementById("editClientId").value = client_id;
            document.getElementById("editTicketSubject").value = ticket.scheduled_ticket_subject;
            document.getElementById("editTicketNextRun").value = ticket.scheduled_ticket_next_run;
            $('#editTicketDetails').summernote('code', ticket.scheduled_ticket_details);


            // Frequency dropdown
            var frequencyDropdown = document.querySelector("#editTicketFrequency");
            Array.from(frequencyDropdown.options).forEach(function (option, index){
                if(option.id === ticket.scheduled_ticket_frequency){
                    frequencyDropdown.selectedIndex = index;
                }
            });

            // Priority dropdown
            var priorityDropdown = document.querySelector("#editTicketPriority");
            Array.from(priorityDropdown.options).forEach(function (option, index){
                if(option.id === ticket.scheduled_ticket_priority){
                    priorityDropdown.selectedIndex = index;
                }
            });

            // Asset dropdown
            var assetDropdown = document.getElementById("editTicketAssetId");

            // Clear asset dropdown
            var i, L = assetDropdown.options.length -1;
            for(i = L; i >= 0; i--) {
                assetDropdown.remove(i);
            }
            assetDropdown[assetDropdown.length] = new Option('- Asset -', '0');

            // Populate dropdown
            assets.forEach(asset => {
                if(parseInt(asset.asset_id) == parseInt(ticket.scheduled_ticket_asset_id)){
                    // Selected asset
                    assetDropdown[assetDropdown.length] = new Option(asset.asset_name, asset.asset_id, true, true);
                }
                else{
                    assetDropdown[assetDropdown.length] = new Option(asset.asset_name, asset.asset_id);
                }
            });
        }
    );
}