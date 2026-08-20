document.addEventListener('DOMContentLoaded', function () {

    // Function to load contacts for a given client
    function loadContacts(clientId) {
        if (!clientId) {
            return;
        }

        const contactSelect = document.getElementById('contact_select');
        if (!contactSelect) {
            return;
        }
        contactSelect.innerHTML = '<option value="">Loading...</option>';

        const query = new URLSearchParams({
            get_client_contacts: 1,
            client_id: clientId
        });

        fetch('ajax.php?' + query.toString(), {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (response) {
                contactSelect.innerHTML = '';
                if (response.contacts && response.contacts.length > 0) {
                    contactSelect.appendChild(new Option('Select a contact', ''));
                    response.contacts.forEach(function (contact) {
                        // new Option() sets text content, so contact names are
                        // never parsed as markup
                        contactSelect.appendChild(
                            new Option(contact.contact_name, contact.contact_id)
                        );
                    });
                } else {
                    contactSelect.appendChild(new Option('No contacts found', ''));
                }

                // Let Tom Select re-read the replaced option list
                refreshTomSelect(contactSelect);
            })
            .catch(function (error) {
                console.error('AJAX Error:', error);
                contactSelect.innerHTML = '<option value="">Failed to load contacts</option>';
            });
    }

    const clientSelect = document.getElementById('client_select');
    if (!clientSelect) {
        return;
    }

    // Load contacts for the currently selected client when modal opens
    loadContacts(clientSelect.value);

    // Load contacts when client changes
    clientSelect.addEventListener('change', function () {
        loadContacts(this.value);
    });
});
