<?php

/*
 * Try an email - what the Ticket Email Parser would do with an email from this
 * address with this subject, step by step, without doing it (ticketMailTry() in
 * functions/ticket_mail_try.php). From Admin > Settings > Tickets, Email to ticket.
 * Asked again with ?try=1, it answers with just the result, which the window
 * puts under the form.
 */

require_once '../../includes/modal_header.php';

/** The result - the steps, and where the email ends up. */
function ticketMailTryHtml(array $result) {
    [$icon, $tone, $title, $text] = $result['outcome'];
    $html = '<ol class="list-unstyled small mb-3 itflow-mail-try-steps">';
    foreach ($result['steps'] as [$question, $answer]) {
        $html .= '<li class="d-flex justify-content-between gap-3 py-1 border-bottom"><span class="text-body-secondary">' . escapeHtml($question) . '</span><span class="text-end">' . escapeHtml($answer) . '</span></li>';
    }
    $html .= '</ol>';
    $html .= '<div class="alert alert-' . escapeHtml($tone) . ' d-flex gap-3 mb-0" data-mail-try-outcome><i class="fas fa-fw ' . escapeHtml($icon) . ' fa-lg mt-1"></i>'
        . '<div><div class="fw-semibold">' . escapeHtml($title) . '</div>' . ($text !== '' ? '<div class="small mt-1">' . escapeHtml($text) . '</div>' : '') . '</div></div>';
    return $html;
}

if (isset($_GET['try'])) {
    $from = trim((string) ($_GET['from'] ?? ''));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['result' => '<div class="alert alert-danger small mb-0">' . ($from === '' ? 'Type who it is from.' : '<strong>' . escapeHtml($from) . '</strong> is not an email address.') . '</div>']);
        exit;
    }
    echo json_encode(['result' => ticketMailTryHtml(ticketMailTry($from, (string) ($_GET['subject'] ?? ''), !empty($_GET['auto'])))]);
    exit;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-vial me-2"></i>Try an Email</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form autocomplete="off" id="ticketMailTryForm">
    <div class="modal-body">
        <p class="small text-body-secondary">What the support mailbox would do with an email - a reply on a ticket, a new ticket and for whom, or left in the mailbox - and why. Nothing is made or sent.</p>
        <div class="mb-3">
            <label class="form-label">From <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-envelope"></i></span>
                <input type="email" class="form-control" name="from" required maxlength="200" placeholder="someone@client.com">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Subject</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-heading"></i></span>
                <input type="text" class="form-control" name="subject" maxlength="500" placeholder="Re: [<?= escapeHtml($config_ticket_prefix) ?>123] Printer offline">
            </div>
            <div class="form-text">A reply to one of our emails has the ticket number in brackets.</div>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" name="auto" value="1" id="ticketMailTryAuto">
            <label class="form-check-label" for="ticketMailTryAuto">It's an automatic reply</label>
            <div class="form-text mt-0">An out-of-office answer, which says so in the email's headers.</div>
        </div>
        <div data-mail-try-result></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-vial me-2"></i>Try It</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
    </div>
</form>

<script>
    (function () {
        var form = document.getElementById('ticketMailTryForm');
        var result = form.querySelector('[data-mail-try-result]');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!form.reportValidity()) {
                return;
            }
            itflowGet('/admin/modals/ticket_mail/ticket_mail_try.php', {
                try: 1, from: form.elements.from.value, subject: form.elements.subject.value, auto: form.elements.auto.checked ? 1 : 0
            }, function (text) {
                try {
                    result.innerHTML = JSON.parse(text).result;
                } catch (err) {
                    result.textContent = 'That could not be tried - reload the page and try again.';
                }
            });
        });
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
