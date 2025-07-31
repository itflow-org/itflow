$('#summaryModal').on('shown.bs.modal', function (e) {
  // Perform AJAX request to get the summary
  $.ajax({
    url: 'post.php?ai_ticket_summary',
    method: 'POST',
    data: { ticket_id: <?php echo $ticket_id; ?> },
    success: function(response) {
      $('#summaryContent').html(response);
    },
    error: function() {
      $('#summaryContent').html('Error generating summary.');
    }
  });
});