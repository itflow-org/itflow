// Ajax Modal Load Script
//
/* Example Triggering  -->

<button type="button"
    class="btn btn-primary"
    data-toggle = "ajax-modal" // Triggers the AJAX Modal
    data-modal-size = "lg" // Optional: Defaults to md
    data-ajax-url="ajax/ajax_contact_edit.php"
    data-ajax-id="123">
    Edit Contact
</button>

*/
$(document).on('click', '[data-toggle="ajax-modal"]', function (e) {
    e.preventDefault();
    
    // Get the URL and ID from the element's data attributes.
    var $trigger = $(this);
    var ajaxUrl = $trigger.data('ajax-url');
    var ajaxId = $trigger.data('ajax-id');
    var modalSize = $trigger.data('modal-size') || 'md';
    
    // Make the AJAX call to fetch modal content.
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
            
            // Create a modal ID by appending the ajaxId.
            var modalId = 'ajaxModal_' + ajaxId + '_' + new Date().getTime();
            
            // Remove any existing modal with this ID.
            $('#' + modalId).remove();
            
            // Build the modal HTML using the returned title and content.
            var modalHtml = 
                '<div class="modal fade" id="' + modalId + '" tabindex="-1">' +
                '    <div class="modal-dialog modal-'+ modalSize +'">' +
                '       <div class="modal-content border-dark">'
                            + response.content +
                '        </div>' +
                '    </div>' +
                '</div>';
            
            // Append the modal to the body and show it.
            $('.content-wrapper').append(modalHtml);
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
