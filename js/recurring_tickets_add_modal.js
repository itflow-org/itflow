// Client selected listener
//  We seem to have to use jQuery to listen for events, as the client input is a select2 component?

const clientSelectDropdown = document.getElementById("changeClientSelect"); // Define client selector

// // If the client selector is disabled, we must be on client_recurring_tickets.php instead. Trigger the contact list update.
if (clientSelectDropdown.disabled) {

    let client_id = $(clientSelectDropdown).find(':selected').val();

    // Update the contacts dropdown list
    populateContactsDropdown(client_id);
}

// Listener for client selection. Populate contact select when a client is selected
$(clientSelectDropdown).on('select2:select', function (e) {
    let client_id = $(this).find(':selected').val();

    // Update the contacts dropdown list
    populateContactsDropdown(client_id);

    // TODO: Update the assets dropdown list

});

// Populate client contact function (after a client is selected)
function populateContactsDropdown(client_id) {
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
            const contactSelectDropdown = document.getElementById("contactSelect");

            // Clear Category dropdown
            let i, L = contactSelectDropdown.options.length - 1;
            for (i = L; i >= 0; i--) {
                contactSelectDropdown.remove(i);
            }
            contactSelectDropdown[contactSelectDropdown.length] = new Option('- Contact -', '0');

            // Populate dropdown
            contacts.forEach(contact => {
                var appendText = "";
                if (contact.contact_primary == "1") {
                    appendText = " (Primary)";
                } else if (contact.contact_technical == "1") {
                    appendText = " (Technical)";
                }
                contactSelectDropdown[contactSelectDropdown.length] = new Option(contact.contact_name + appendText, contact.contact_id);
            });

        }
    );
}
