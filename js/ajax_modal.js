// Ajax Modal Load Script
//
/* Example Triggering  -->

<button type="button"
    class="btn btn-primary ajax-modal" // Triggers the AJAX Modal
    data-modal-size = "lg" // Optional: Defaults to md
    data-modal-url="modals/contact/contact_edit.php?id=id">
    Edit Contact
</button>

*/
$(document).on('click', '.ajax-modal', function (e) {
    e.preventDefault();

    const $trigger = $(this);
    const modalUrl = $trigger.data('modal-url');
    const modalSize = $trigger.data('modal-size') || 'md';
    const modalId = 'ajaxModal_' + new Date().getTime();

    // Show loading spinner while fetching content
    const loadingSpinner = `
        <div id="modal-loading-spinner" class="text-center p-5">
            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
        </div>`;
    $('.content-wrapper').append(loadingSpinner);

    // Make AJAX request
    $.ajax({
        url: modalUrl,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            $('#modal-loading-spinner').remove();

            if (response.error) {
                alert(response.error);
                return;
            }

            // Build modal wrapper
            const modalHtml = `
                <div class="modal fade" id="${modalId}" tabindex="-1">
                    <div class="modal-dialog modal-${modalSize}">
                        <div class="modal-content border-dark">
                            ${response.content}
                        </div>
                    </div>
                </div>`;

            $('.content-wrapper').append(modalHtml);
            const $modal = $('#' + modalId);
            $modal.modal('show');

            // Remove modal after it's closed
            $modal.on('hidden.bs.modal', function () {
                $(this).remove();
            });
        },
        error: function (xhr, status, error) {
            $('#modal-loading-spinner').remove();
            alert('Error loading modal content. Please try again.');
            console.error('Modal AJAX Error:', status, error);
        }
    });
});