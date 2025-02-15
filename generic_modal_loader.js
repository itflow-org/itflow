// Generic Ajax Modal Load Script
//
/*Trigger Button using data-ajax attributes -->
        <button type="button"
                class="btn btn-primary ajax-trigger"
                data-ajax="ajax_edit_contact.php"
                data-ajax-id="123">
            Edit Contact
        </button>
*/
$(document).on('click', '.ajax-trigger', function (e) {
    e.preventDefault();
    
    // Get the URL and ID from the element's data attributes
    var $trigger = $(this);
    var ajaxUrl = $trigger.data('ajax-url');
    var ajaxId = $trigger.data('ajax-id');
    
    // Make the AJAX call to fetch modal content
    $.ajax({
        url: ajaxUrl,
        method: 'GET',
        data: { id: ajaxId },
        dataType: 'json',
        success: function (response) {
            if (response.error) {
                alert(response.error);
                return;
            }
            
            // Create a unique modal ID (you can enhance this as needed)
            var modalId = 'dynamicAjaxModal';
            
            // Build the modal HTML using the returned title and content
            var modalHtml = 
                '<div class="modal fade" id="' + modalId + '" tabindex="-1" role="dialog" aria-labelledby="' + modalId + 'Label" aria-hidden="true">' +
                '    <div class="modal-dialog" role="document">' +
                '        <div class="modal-content bg-dark">' +
                '            <div class="modal-header">' +
                '                <h5 class="modal-title" id="' + modalId + 'Label">' + response.title + '</h5>' +
                '                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">' +
                '                    <span aria-hidden="true">&times;</span>' +
                '                </button>' +
                '            </div>' +
                '            <div class="modal-body bg-white">' + response.content + '</div>' +
                '        </div>' +
                '    </div>' +
                '</div>';
            
            // Append the modal to the body and show it
            $('body').append(modalHtml);
            $('#' + modalId).modal('show');
            
            // Remove the modal from the DOM once it's hidden
            $('#' + modalId).on('hidden.bs.modal', function () {
                $(this).remove();
            });
        },
        error: function () {
            alert('Error loading modal content.');
        }
    });
});