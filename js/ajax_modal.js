// Ajax Modal Load Script
$(document).on('click', '.ajax-modal', function (e) {
  e.preventDefault();

  const $trigger  = $(this);

  // Prefer data-modal-url, fallback to href
  let modalUrl = $trigger.data('modal-url') || $trigger.attr('href') || '#';
  const modalSize = $trigger.data('modal-size') || 'md';
  const modalId   = 'ajaxModal_' + Date.now();

  // If no usable URL, bail
  if (!modalUrl || modalUrl === '#') {
    console.warn('ajax-modal: No modal URL found on trigger:', this);
    return;
  }

  // Show loading spinner while fetching content
  const loadingSpinner = `
    <div id="modal-loading-spinner" class="text-center p-5">
      <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
    </div>`;
  $('.app-main').append(loadingSpinner);

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

      const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1">
          <div class="modal-dialog modal-${modalSize}">
            <div class="modal-content border-dark">
              ${response.content}
            </div>
          </div>
        </div>`;

      $('.app-main').append(modalHtml);
      const $modal = $('#' + modalId);
      bootstrap.Modal.getOrCreateInstance($modal[0]).show();

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
