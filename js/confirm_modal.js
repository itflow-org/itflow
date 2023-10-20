$(document).ready(function() {
  $("a.confirm-link").click(function(e) {
      e.preventDefault();

      // Save the link reference to use after confirmation
      var linkReference = this;

      // Show the confirmation modal
      $("#confirmationModal").modal('show');

      // When the submission is confirmed via the modal
      $("#confirmSubmitBtn").off('click').on('click', function() {
          window.location.href = $(linkReference).attr('href');
      });
  });
});