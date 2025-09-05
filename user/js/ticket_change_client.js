/*
 *   LISTENERS
 */

// Modal loaded listener - populate client select
const changeClientModalLoad = document.getElementById('clientChangeTicketModalLoad');
changeClientModalLoad.addEventListener('click', function() {
    populateChangeClientModal_Clients();
})

// Client selected listener - populate contact select
//  We seem to have to use jQuery to listen for events, as the client input is a select2 component?
const clientSelectDropdown = document.getElementById("changeClientSelect");
$(clientSelectDropdown).on('select2:select', function (e) {
    let client_id = $(this).find(':selected').val();
    populateChangeClientModal_Contacts(client_id);
});


/*
 *   FUNCTIONS
 */

// Populate client list function
function populateChangeClientModal_Clients() {

    // Get current client ID
    let current_client_id = document.getElementById("client_id").value;

    // Send a GET request to ajax.php as ajax.php?get_active_clients=true
    jQuery.get(
        "ajax.php",
        {get_active_clients: 'true'},
        function(data) {

            // If we get a response from ajax.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the data for clients (multiple)
            const clients = response.clients;

            // Client dropdown already defined in listeners as clientSelectDropdown

            // Clear dropdown
            let i, L = clientSelectDropdown.options.length - 1;
            for (i = L; i >= 0; i--) {
                clientSelectDropdown.remove(i);
            }
            clientSelectDropdown[clientSelectDropdown.length] = new Option('- Client -', '0');

            // Populate dropdown
            clients.forEach(client => {
                if (parseInt(current_client_id) !== parseInt(client.client_id)) {
                    // Show clients returned (excluding the current client ID - we can't change a ticket client to itself)
                    clientSelectDropdown[clientSelectDropdown.length] = new Option(client.client_name, client.client_id);
                }
            });

        }
    );
}

// Populate client contact function (after a client is selected)
function populateChangeClientModal_Contacts(client_id) {
    // Send a GET request to ajax.php as ajax.php?get_client_contacts=true&client_id=NUM
    jQuery.get(
        "ajax.php",
        {get_client_contacts: 'true', client_id: client_id},
        function(data) {

            // If we get a response from ajax.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the data for contacts (multiple)
            const contacts = response.contacts;

            // Contacts dropdown
            const contactSelectDropdown = document.getElementById("changeContactSelect");

            // Clear Category dropdown
            let i, L = contactSelectDropdown.options.length - 1;
            for (i = L; i >= 0; i--) {
                contactSelectDropdown.remove(i);
            }
            contactSelectDropdown[contactSelectDropdown.length] = new Option('- Contact -', '0');

            // Populate dropdown
            contacts.forEach(contact => {
                contactSelectDropdown[contactSelectDropdown.length] = new Option(contact.contact_name, contact.contact_id);
            });

        }
    );
}
