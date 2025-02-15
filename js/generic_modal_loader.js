// Generic Ajax Modal Load Script
//
/*Trigger Button using data-ajax attributes -->
        <button type="button"
                class="btn btn-primary ajax-trigger"
                data-ajax-url="ajax_contact_edit.php"
                data-ajax-id="123">
            Edit Contact
        </button>
*/
$(document).on('click', '.ajax-trigger', function (e) {
    e.preventDefault();
    
    // Get the URL and ID from the element's data attributes.
    var $trigger = $(this);
    var ajaxUrl = $trigger.data('ajax-url');
    var ajaxId = $trigger.data('ajax-id');
    
    // Make the AJAX call to fetch modal content.
    $.ajax({
        url: ajaxUrl,
        method: 'GET',
        data: { id: ajaxId },
        dataType: 'json',
        cache: false, // Prevent caching if necessary
        success: function (response) {
            if (response.error) {
                alert(response.error);
                return;
            }
            
            // Create a modal ID by appending the ajaxId.
            var modalId = 'dynamicAjaxModal_' + ajaxId;
            
            // Remove any existing modal with this ID.
            $('#' + modalId).remove();
            
            // Build the modal HTML using the returned title and content.
            var modalHtml = 
                '<div class="modal text-sm" id="' + modalId + '" tabindex="-1">' +
                '    <div class="modal-dialog">' +
                '        <div class="modal-content bg-dark">' +
                '            <div class="modal-header">' +
                '                <h5 class="modal-title" id="' + modalId + 'Label">' + response.title + '</h5>' +
                '                <button type="button" class="close text-white" data-dismiss="modal">' +
                '                    <span>&times;</span>' +
                '                </button>' +
                '            </div>' +
                         response.content +
                '        </div>' +
                '    </div>' +
                '</div>';
            
            // Append the modal to the body and show it.
            $('body').append(modalHtml);
            var $modal = $('#' + modalId);
            $modal.modal('show');
            
            // Remove the modal from the DOM once it's hidden.
            $modal.on('hidden.bs.modal', function () {
                $(this).remove();
            });
        },
        error: function () {
            alert('Error loading modal content.');
        }
    });
});