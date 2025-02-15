$(document).on('click', '.edit-contact-btn', function(e) {
    e.preventDefault();
    var contactId = $(this).data('contact-id');
    $('#editContactContent').html('Loading...'); // optional loading text
    $.get('ajax_edit_contact.php', { contact_id: contactId }, function(response) {
        $('#editContactContent').html(response);
        $('#editContactModal').modal('show');
    }).fail(function() {
        alert('Error loading contact details.');
    });
});