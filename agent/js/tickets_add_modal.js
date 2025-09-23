// Used to populate dynamic content in recurring_ticket_add_modal and ticket_add_modal_v2 based on selected client

// Client selected listener
//  We seem to have to use jQuery to listen for events, as the client input is a select2 component?

const clientSelectDropdown = document.getElementById("changeClientSelect"); // Define client selector

// // If the client selector is disabled, we must be on a client-specific page instead. Trigger the lists to update.
if (clientSelectDropdown.disabled) {

    let client_id = $(clientSelectDropdown).find(':selected').val();

    populateLists(client_id);
}

// Listener for client selection. Populate select lists when a client is selected
$(clientSelectDropdown).on('select2:select', function (e) {
    let client_id = $(this).find(':selected').val();

    // Update the contacts dropdown list
    populateLists(client_id);

});

// Populates dropdowns with dynamic content based on the client ID
//  Called the client select dropdown is used or if the client select is disabled
function populateLists(client_id) {

    populateContactsDropdown(client_id);

    populateAssetsDropdown(client_id);

    populateLocationsDropdown(client_id);

    populateVendorsDropdown(client_id);
}

// Populate client contacts
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

            // Clear dropdown
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

// Populate client assets
function populateAssetsDropdown(client_id) {
    jQuery.get(
        "ajax.php",
        {get_client_assets: 'true', client_id: client_id},
        function(data) {

            // If we get a response from ajax.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the data for assets (multiple)
            const assets = response.assets;

            // Assets dropdown
            const assetSelectDropdown = document.getElementById("assetSelect");

            // Clear dropdown
            let i, L = assetSelectDropdown.options.length - 1;
            for (i = L; i >= 0; i--) {
                assetSelectDropdown.remove(i);
            }
            assetSelectDropdown[assetSelectDropdown.length] = new Option('- Asset -', '0');

            // Populate dropdown with asset name (and contact, if set)
            assets.forEach(asset => {
                let displayText = asset.asset_name;
                if (asset.contact_name !== null) {
                    displayText = asset.asset_name + " - " + asset.contact_name;
                }

                assetSelectDropdown[assetSelectDropdown.length] = new Option(displayText, asset.asset_id);
            });

        }
    );
}

// Populate client locations
function populateLocationsDropdown(client_id) {
    jQuery.get(
        "ajax.php",
        {get_client_locations: 'true', client_id: client_id},
        function(data) {

            // If we get a response from ajax.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the data for locations (multiple)
            const locations = response.locations;

            // Locations dropdown
            const locationSelectDropdown = document.getElementById("locationSelect");

            // Clear dropdown
            let i, L = locationSelectDropdown.options.length - 1;
            for (i = L; i >= 0; i--) {
                locationSelectDropdown.remove(i);
            }
            locationSelectDropdown[locationSelectDropdown.length] = new Option('- Location -', '0');

            // Populate dropdown
            locations.forEach(location => {
                locationSelectDropdown[locationSelectDropdown.length] = new Option(location.location_name, location.location_id);
            });

        }
    );
}

// Populate client vendors
function populateVendorsDropdown(client_id) {
    jQuery.get(
        "ajax.php",
        {get_client_vendors: 'true', client_id: client_id},
        function(data) {

            // If we get a response from ajax.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the data for locations (multiple)
            const vendors = response.vendors;

            // Locations dropdown
            const vendorSelectDropdown = document.getElementById("vendorSelect");

            // Clear dropdown
            let i, L = vendorSelectDropdown.options.length - 1;
            for (i = L; i >= 0; i--) {
                vendorSelectDropdown.remove(i);
            }
            vendorSelectDropdown[vendorSelectDropdown.length] = new Option('- Vendor -', '0');

            // Populate dropdown
            vendors.forEach(vendor => {
                vendorSelectDropdown[vendorSelectDropdown.length] = new Option(vendor.vendor_name, vendor.vendor_id);
            });

        }
    );
}
