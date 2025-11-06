// Ajax Modal Load Script
$(document).on('click', '.ajax-modal', function (e) {
  e.preventDefault();

  const $trigger = $(this);
  let modalUrl = $trigger.data('modal-url');
  const modalSize = $trigger.data('modal-size') || 'md';
  const modalId = 'ajaxModal_' + new Date().getTime();

  // -------- Optional bulk mode (activated via data-bulk="true") --------
  if ($trigger.data('bulk') === true || $trigger.data('bulk') === 'true') {
    const selector = $trigger.data('bulk-selector') || '.bulk-select:checked';
    const param    = $trigger.data('bulk-param') || 'selected_ids[]';

    const ids = Array.from(document.querySelectorAll(selector)).map(cb => cb.value);

    if (!ids.length) {
      alert('Please select at least one item.');
      return; // abort opening modal
    }

    // Merge ids into existing query string safely
    const urlObj = new URL(modalUrl, window.location.href);
    ids.forEach(id => urlObj.searchParams.append(param, id));

    // Preserve path + updated query (avoid absolute origin for relative AJAX)
    modalUrl = urlObj.pathname + (urlObj.search ? '?' + urlObj.searchParams.toString() : '');
  }
  // --------------------------------------------------------------------

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
