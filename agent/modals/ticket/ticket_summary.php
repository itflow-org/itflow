<?php

/*
 * A ticket summed up by AI (aiTicketSummary(), functions/ai_ticket.php) - in
 * short, then the problem, what has been done, where it stands and what is
 * next. Asked as the window opens; Again asks once more. Save as a note puts
 * it on the ticket as an internal note (add_ticket_ai_note,
 * agent/post/ticket.php), so the next agent has it without asking; Copy puts
 * it on the clipboard.
 */

require_once '../../../includes/modal_header.php';

$ticket = ticketForModal($_GET['ticket_id'] ?? 0, 1);
$ticket_id = intval($ticket['ticket_id']);

// Using AI is a permission of its own
if (!aiCan()) {
    permissionRefuse(permissionRefusedText('module_ai', 1, 0));
}

// A note can go on a ticket that is still open to them
$can_note = lookupUserPermission('module_support') >= 2 && empty($ticket['ticket_closed_at']);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-magic me-2"></i>Summary of <?= escapeHtml(ticketReference($ticket)) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-ai-summary-form data-ticket-id="<?= $ticket_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
    <input type="hidden" name="note" value="" data-ai-summary-note>
    <div class="modal-body">
        <div class="itflow-ai-summary" data-ai-summary aria-live="polite">
            <div class="text-body-secondary"><i class="fas fa-spinner fa-spin me-2"></i>Reading the ticket...</div>
        </div>
        <div class="small text-body-secondary mt-3 d-none" data-ai-summary-said><i class="fas fa-fw fa-robot me-1"></i>Written by AI from the ticket - check it before relying on it.</div>
    </div>
    <div class="modal-footer">
        <?php if ($can_note) { ?>
            <button type="submit" name="add_ticket_ai_note" class="btn btn-primary text-bold" data-ai-summary-save disabled title="Put it on the ticket as an internal note">
                <i class="fas fa-fw fa-sticky-note me-2"></i>Save as a note
            </button>
        <?php } ?>
        <button type="button" class="btn btn-light border" data-ai-summary-copy disabled><i class="fas fa-fw fa-copy me-2"></i>Copy</button>
        <button type="button" class="btn btn-light border" data-ai-summary-again disabled><i class="fas fa-fw fa-redo me-2"></i>Again</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('[data-ai-summary-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var target = form.querySelector('[data-ai-summary]');
    var note = form.querySelector('[data-ai-summary-note]');
    var said = form.querySelector('[data-ai-summary-said]');
    var save = form.querySelector('[data-ai-summary-save]');
    var copy = form.querySelector('[data-ai-summary-copy]');
    var again = form.querySelector('[data-ai-summary-again]');

    function ready(on) {
        [save, copy, again].forEach(function (button) {
            if (button) {
                button.disabled = !on;
            }
        });
    }

    function ask() {
        ready(false);
        note.value = '';
        said.classList.add('d-none');
        target.innerHTML = '<div class="text-body-secondary"><i class="fas fa-spinner fa-spin me-2"></i>Reading the ticket...</div>';
        fetch('/agent/ajax.php?ai_ticket_summary', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({ ticket_id: form.getAttribute('data-ticket-id') }).toString()
        })
            .then(function (response) {
                return response.json().catch(function () {
                    throw new Error('');
                });
            })
            .then(function (answer) {
                if (answer.error || !answer.html) {
                    throw new Error(answer.error || '');
                }
                // HTML as the server cleaned it
                target.innerHTML = answer.html;
                note.value = answer.html;
                said.classList.remove('d-none');
                ready(true);
            })
            .catch(function (error) {
                target.innerHTML = '';
                var p = document.createElement('p');
                p.className = 'text-danger-emphasis mb-0';
                p.setAttribute('data-ai-summary-error', '');
                p.textContent = error && error.message ? error.message : 'Could not summarize it - try again.';
                target.appendChild(p);
                again.disabled = false;
            });
    }

    again.addEventListener('click', ask);
    copy.addEventListener('click', function () {
        var text = target.innerText;
        if (navigator.clipboard && window.ClipboardItem) {
            navigator.clipboard.write([new ClipboardItem({
                'text/html': new Blob([note.value], { type: 'text/html' }),
                'text/plain': new Blob([text], { type: 'text/plain' })
            })]).then(function () {
                itflowToast('Summary copied', 'success');
            }, function () {
                navigator.clipboard.writeText(text);
            });
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                itflowToast('Summary copied', 'success');
            });
        }
    });
    ask();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
