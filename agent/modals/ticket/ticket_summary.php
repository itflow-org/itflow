<?php

require_once '../../../includes/modal_header.php';

$ticket_id = intval($_GET['ticket_id']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title" id="summaryModalTitle">Ticket Summary</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div id="summaryContent">
        <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Generating summary...</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var target = document.getElementById('summaryContent');
    if (!target) {
        return;
    }

    fetch('ajax.php?ai_ticket_summary', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: new URLSearchParams({ ticket_id: '<?= $ticket_id ?>' }).toString()
    })
        .then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.text();
        })
        .then(function (html) {
            target.innerHTML = html;
        })
        .catch(function () {
            target.textContent = 'Error generating summary.';
        });
});
</script>

<?php
require_once '../../../includes/modal_footer.php';
