// Delegated on document rather than bound to the links present at page load, so
// that confirm-link also works on markup injected by an ajax modal
$(document).ready(function() {
  $(document).off('click.itflowConfirm').on('click.itflowConfirm', 'a.confirm-link', function(e) {
      e.preventDefault();

      // Save the link reference to use after confirmation
      var linkReference = this;

      // Show the confirmation modal
      bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmationModal')).show();

      // When the submission is confirmed via the modal
      $("#confirmSubmitBtn").off('click').on('click', function() {
          window.location.href = $(linkReference).attr('href');
      });
  });
});