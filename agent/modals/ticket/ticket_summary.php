<?php

require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['ticket_id']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title" id="summaryModalTitle">Ticket Summary</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<div class="modal-body">
    <div id="summaryContent">
        <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Generating summary...</div>
    </div>
</div>

<script>
$(function() {
    $.ajax({
        url: 'ajax.php?ai_ticket_summary',
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
</script>

<?php
require_once '../../../includes/modal_footer.php';
