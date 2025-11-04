$(document).ready(function() {

    // Function to load contacts for a given client
    function loadContacts(clientId) {
        if (!clientId) return;

        var $contactSelect = $('#contact_select');
        $contactSelect.html('<option value="">Loading...</option>');

        $.ajax({
            url: 'ajax.php',
            type: 'GET',
            dataType: 'json',
            data: {
                get_client_contacts: 1,
                client_id: clientId
            },
            success: function(response) {
                $contactSelect.empty();
                if (response.contacts && response.contacts.length > 0) {
                    $contactSelect.append('<option value="">Select a contact</option>');
                    $.each(response.contacts, function(i, contact) {
                        $contactSelect.append(
                            $('<option>', { 
                                value: contact.contact_id,
                                text: contact.contact_name
                            })
                        );
                    });
                } else {
                    $contactSelect.append('<option value="">No contacts found</option>');
                }

                // Refresh Select2
                if ($.fn.select2) {
                    $contactSelect.trigger('change.select2');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $contactSelect.html('<option value="">Failed to load contacts</option>');
            }
        });
    }

    // Load contacts for the currently selected client when modal opens
    var initialClientId = $('#client_select').val();
    loadContacts(initialClientId);

    // Load contacts when client changes
    $('#client_select').on('change', function() {
        var clientId = $(this).val();
        loadContacts(clientId);
    });
});
