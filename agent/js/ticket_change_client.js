itflowReady(function () {

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
                        // never parsed as markup. The picker draws their avatar
                        // from the data-* extras, as on every other contact field.
                        const option = new Option(contact.contact_name, contact.contact_id);
                        // Title, then Primary or Technical - contactOptionDescription() server side
                        const role = contact.contact_primary == '1' ? 'Primary' : (contact.contact_technical == '1' ? 'Technical' : '');
                        option.dataset.description = [contact.contact_title, role].filter(Boolean).join(' · ');
                        option.dataset.initials = contact.contact_initials || '';
                        option.dataset.shape = 'circle';
                        option.dataset.tone = contact.contact_tone || 'secondary';
                        option.dataset.soft = '1';
                        if (contact.contact_photo_url) {
                            option.dataset.image = contact.contact_photo_url;
                        }
                        contactSelect.appendChild(option);
                    });
                } else {
                    contactSelect.appendChild(new Option('No contacts found', ''));
                }
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
