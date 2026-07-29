// Used to populate dynamic content in recurring_ticket_add_modal and ticket_add_modal_v2 based on selected client

// Not every modal that loads this script has every dropdown, and a modal opened
// from a contact page has no client selector at all - the client arrives as a
// hidden field. Everything below therefore checks that an element exists before
// touching it, rather than assuming the full set is present.

// Client selected listener
//  We seem to have to use jQuery to listen for events, as the client input is a select2 component?

const clientSelectDropdown = document.getElementById("changeClientSelect"); // Define client selector

if (clientSelectDropdown) {

    // If the client selector is disabled, we must be on a client-specific page instead. Trigger the lists to update.
    if (clientSelectDropdown.disabled) {

        let client_id = $(clientSelectDropdown).find(':selected').val();

        populateLists(client_id);
    }

    // Listener for client selection. Populate select lists when a client is selected
    $(clientSelectDropdown).on('select2:select', function (e) {
        let client_id = $(this).find(':selected').val();

        // Update the dependent dropdown lists
        populateLists(client_id);

    });

} else {

    // No client selector - the modal was opened from a contact page, where the
    // client is fixed and arrives as a hidden field instead
    const clientIdHiddenField = document.getElementById("clientIdHidden");

    if (clientIdHiddenField && clientIdHiddenField.value) {
        populateLists(clientIdHiddenField.value);
    }

}

// Populates dropdowns with dynamic content based on the client ID
//  Called when the client select dropdown is used or if the client select is disabled
function populateLists(client_id) {

    populateContactsDropdown(client_id);

    populateAssetsDropdowns(client_id);

    populateLocationsDropdown(client_id);

    populateVendorsDropdown(client_id);

    populateProjectsDropdown(client_id);
}

// Empties a dropdown and adds its placeholder, returning the element - or null if
// this modal doesn't have it. Pass null as the label for a multi-select, which has
// no placeholder option of its own.
function resetDropdown(id, placeholderLabel, placeholderValue) {

    const dropdown = document.getElementById(id);

    if (!dropdown) {
        return null;
    }

    // innerHTML rather than removing options one by one, which leaves empty optgroups behind
    dropdown.innerHTML = '';

    // A multi-select keeps showing its old selections until select2 is told the value changed
    $(dropdown).val(null).trigger('change.select2');

    if (placeholderLabel !== null) {
        dropdown[dropdown.length] = new Option(placeholderLabel, placeholderValue);
    }

    return dropdown;
}

// Redraws a select2 component after its options have been replaced
function refreshDropdown(dropdown) {
    if (dropdown) {
        $(dropdown).trigger('change.select2');
    }
}

// Re-applies the value the modal was opened with (e.g. ticket_add.php?project_id=4),
// which can only be selected once the options it refers to exist
function applyPreselection(dropdown) {

    if (!hasPreselection(dropdown)) {
        return;
    }

    dropdown.value = dropdown.dataset.selected;
}

// True when the modal was opened with a specific value for this dropdown.
// '0' is the "none selected" value every one of these dropdowns uses, and is
// truthy as a string - so it has to be excluded explicitly.
function hasPreselection(dropdown) {
    return Boolean(dropdown && dropdown.dataset.selected && dropdown.dataset.selected !== '0');
}

// Adds an optgroup to a dropdown and returns it, so options can be appended into it
function appendOptionGroup(dropdown, label) {

    if (!dropdown) {
        return null;
    }

    const group = document.createElement("optgroup");
    group.label = label;
    dropdown.appendChild(group);

    return group;
}

// Adds an option to an optgroup
function appendGroupedOption(group, label, value) {

    if (!group) {
        return;
    }

    group.appendChild(new Option(label, value));
}

// Builds the asset label as "Name - Make Model - (Contact)", matching how assets read elsewhere
function buildAssetLabel(asset) {

    let label = asset.asset_name;

    if (asset.asset_make) {
        label = label + " - " + asset.asset_make;

        if (asset.asset_model) {
            label = label + " " + asset.asset_model;
        }
    }

    if (asset.contact_name) {
        label = label + " - (" + asset.contact_name + ")";
    }

    return label;
}

// Populate client contacts - one request feeds both the contact picker and the
// watchers list, as both are built from the same set of people
function populateContactsDropdown(client_id) {

    if (!document.getElementById("contactSelect") && !document.getElementById("watchersSelect")) {
        return;
    }

    // Send a GET request to ajax.php as ajax.php?get_client_contacts=true&client_id=NUM
    jQuery.get(
        "ajax.php",
        {get_client_contacts: 'true', client_id: client_id},
        function(data) {

            // If we get a response from ajax.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the data for contacts (multiple)
            const contacts = response.contacts || [];

            // Contacts dropdown
            const contactSelectDropdown = resetDropdown("contactSelect", '- No One -', '0');

            // Watchers is a tags field - any address can be typed in, these are just the handy ones
            const watchersDropdown = resetDropdown("watchersSelect", null, null);

            // Populate dropdown
            contacts.forEach(contact => {
                var appendText = "";
                if (contact.contact_title) {
                    appendText = " - " + contact.contact_title;
                }
                if (contact.contact_primary == "1") {
                    appendText = appendText + " (Primary)";
                } else if (contact.contact_technical == "1") {
                    appendText = appendText + " (Technical)";
                }

                if (contactSelectDropdown) {
                    contactSelectDropdown[contactSelectDropdown.length] = new Option(contact.contact_name + appendText, contact.contact_id);
                }

                if (watchersDropdown && contact.contact_email) {
                    watchersDropdown[watchersDropdown.length] = new Option(contact.contact_email, contact.contact_email);
                }
            });

            // Default to the client's primary contact unless the modal was opened for a
            // specific one. Contacts arrive primary-first.
            if (contactSelectDropdown && !hasPreselection(contactSelectDropdown)) {
                const primaryContact = contacts.find(contact => contact.contact_primary == "1");

                if (primaryContact) {
                    contactSelectDropdown.value = primaryContact.contact_id;
                }
            }

            applyPreselection(contactSelectDropdown);

            refreshDropdown(contactSelectDropdown);
            refreshDropdown(watchersDropdown);

        }
    );
}

// Populate client assets - feeds both the single asset picker and the additional assets
// multi-select from one request, as both need the same list
function populateAssetsDropdowns(client_id) {

    if (!document.getElementById("assetSelect") && !document.getElementById("additionalAssetsSelect")) {
        return;
    }

    jQuery.get(
        "ajax.php",
        {get_client_assets: 'true', client_id: client_id},
        function(data) {

            // If we get a response from ajax.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the data for assets (multiple)
            const assets = response.assets || [];

            const assetSelectDropdown = resetDropdown("assetSelect", '- None -', '0');
            const additionalAssetsDropdown = resetDropdown("additionalAssetsSelect", null, null);

            // Assets arrive ordered by type, so a change of type starts a new group
            let currentType = null;
            let assetGroup = null;
            let additionalAssetGroup = null;

            assets.forEach(asset => {
                const assetType = asset.asset_type || 'Uncategorized';

                if (assetType !== currentType) {
                    currentType = assetType;
                    assetGroup = appendOptionGroup(assetSelectDropdown, assetType);
                    additionalAssetGroup = appendOptionGroup(additionalAssetsDropdown, assetType);
                }

                const assetLabel = buildAssetLabel(asset);

                appendGroupedOption(assetGroup, assetLabel, asset.asset_id);
                appendGroupedOption(additionalAssetGroup, assetLabel, asset.asset_id);
            });

            applyPreselection(assetSelectDropdown);

            refreshDropdown(assetSelectDropdown);
            refreshDropdown(additionalAssetsDropdown);

        }
    );
}

// Populate client locations
function populateLocationsDropdown(client_id) {

    if (!document.getElementById("locationSelect")) {
        return;
    }

    jQuery.get(
        "ajax.php",
        {get_client_locations: 'true', client_id: client_id},
        function(data) {

            // If we get a response from ajax.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the data for locations (multiple)
            const locations = response.locations || [];

            // Locations dropdown
            const locationSelectDropdown = resetDropdown("locationSelect", '- Location -', '0');

            // Populate dropdown
            locations.forEach(location => {
                locationSelectDropdown[locationSelectDropdown.length] = new Option(location.location_name, location.location_id);
            });

            applyPreselection(locationSelectDropdown);

            refreshDropdown(locationSelectDropdown);

        }
    );
}

// Populate client vendors
function populateVendorsDropdown(client_id) {

    if (!document.getElementById("vendorSelect")) {
        return;
    }

    jQuery.get(
        "ajax.php",
        {get_client_vendors: 'true', client_id: client_id},
        function(data) {

            // If we get a response from ajax.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the data for vendors (multiple)
            const vendors = response.vendors || [];

            // Vendors dropdown
            const vendorSelectDropdown = resetDropdown("vendorSelect", '- Vendor -', '0');

            // Populate dropdown
            vendors.forEach(vendor => {
                vendorSelectDropdown[vendorSelectDropdown.length] = new Option(vendor.vendor_name, vendor.vendor_id);
            });

            applyPreselection(vendorSelectDropdown);

            refreshDropdown(vendorSelectDropdown);

        }
    );
}

// Populate client projects
function populateProjectsDropdown(client_id) {

    if (!document.getElementById("projectSelect")) {
        return;
    }

    jQuery.get(
        "ajax.php",
        {get_client_projects: 'true', client_id: client_id},
        function(data) {

            // If we get a response from ajax.php, parse it as JSON
            const response = JSON.parse(data);

            // Access the data for projects (multiple)
            const projects = response.projects || [];

            // Projects dropdown
            const projectSelectDropdown = resetDropdown("projectSelect", '- Select Project -', '0');

            // Populate dropdown
            projects.forEach(project => {
                projectSelectDropdown[projectSelectDropdown.length] = new Option(project.project_name, project.project_id);
            });

            applyPreselection(projectSelectDropdown);

            refreshDropdown(projectSelectDropdown);

        }
    );
}
