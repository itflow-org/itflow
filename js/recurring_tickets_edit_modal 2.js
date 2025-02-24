function populateRecurringTicketEditModal(client_id, ticket_id) {

    // Send a GET request to ajax.php as ajax.php?recurring_ticket_get_json_details=true&client_id=NUM&ticket_id=NUM
    jQuery.get(
        "ajax.php",
        {recurring_ticket_get_json_details: 'true', client_id: client_id, ticket_id: ticket_id},
        function(data){

            // If we get a response from post.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the ticket info, agents and all potential assets
            const contacts = response.contacts;
            const ticket = response.ticket[0];
            const agents = response.agents;
            const assets = response.assets;

            // Populate the scheduled ticket modal fields
            document.getElementById("editHeader").innerText = " Edit Recurring ticket: " + ticket.scheduled_ticket_subject;
            document.getElementById("editTicketId").value = ticket_id;
            document.getElementById("editClientId").value = client_id;
            document.getElementById("editTicketBillable").checked = !!parseInt(ticket.scheduled_ticket_billable);
            document.getElementById("editTicketSubject").value = ticket.scheduled_ticket_subject;
            document.getElementById("editTicketNextRun").value = ticket.scheduled_ticket_next_run;
            tinyMCE.get('editTicketDetails').setContent(ticket.scheduled_ticket_details);

            // Agent assignment dropdown
            var agentDropdown = document.getElementById("editTicketAgent");

            // Clear agent dropdown
            var i, L = agentDropdown.options.length -1;
            for(i = L; i >= 0; i--) {
                agentDropdown.remove(i);
            }
            agentDropdown[agentDropdown.length] = new Option('- Agent -', '0');


            // Populate dropdown
            agents.forEach(agent => {
                if(parseInt(agent.user_id) == parseInt(ticket.scheduled_ticket_assigned_to)){
                    // Selected agent
                    agentDropdown[agentDropdown.length] = new Option(agent.user_name, agent.user_id, true, true);
                }
                else{
                    agentDropdown[agentDropdown.length] = new Option(agent.user_name, agent.user_id);
                }
            });

            // Contact dropdown
            var contactDropdown = document.getElementById("editTicketContact");

            // Clear contact dropdown
            var i, L = contactDropdown.options.length -1;
            for(i = L; i >= 0; i--) {
                contactDropdown.remove(i);
            }
            contactDropdown[contactDropdown.length] = new Option('- Contact -', '0');


            // Populate dropdown
            contacts.forEach(contact => {
                if(parseInt(contact.contact_id) == parseInt(ticket.scheduled_ticket_contact_id)){
                    // Selected contact
                    contactDropdown[contactDropdown.length] = new Option(contact.contact_name, contact.contact_id, true, true);
                }
                else{
                    contactDropdown[contactDropdown.length] = new Option(contact.contact_name, contact.contact_id);
                }
            });

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
            if (assets && assets.length > 0) {
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

        }
    );
}
