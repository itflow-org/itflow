<?php

/*
 * Write a ticket up as a document - the New Document window
 * (modals/document/document_form.php), filled in by AI from the ticket
 * (aiTicketDocument(), functions/ai_ticket.php, through ai_ticket_document in
 * agent/ajax.php): its name, summary and what to do next time, with passwords
 * and keys left out. It goes in the ticket's client's documents - or your own
 * company's, for a ticket with no client - linked to the ticket's asset, and
 * the ticket's history says it was written up. Everything can be changed
 * before it is created, by add_document (agent/post/document.php).
 */

require_once '../../../includes/modal_header.php';

$ticket = ticketForModal($_GET['ticket_id'] ?? 0, 2);
$ticket_id = intval($ticket['ticket_id']);

// Using AI is a permission of its own
if (!aiCan()) {
    permissionRefuse(permissionRefusedText('module_ai', 1, 0));
}

$client_id = intval($ticket['ticket_client_id']) ?: intval($config_internal_client_id ?? 0);
if (!$client_id) {
    echo json_encode(['error' => 'This ticket has no client to keep the document with. Pick your own company\'s client record in Admin > Settings > Company, then try again.']);
    exit;
}
if (!hasClientAccess($client_id)) {
    permissionRefuse("You don't have access to that client's documents.");
}
$client_name = (string) getFieldById('clients', $client_id, 'client_name');
$asset_id = intval($ticket['ticket_asset_id']) && intval(getFieldById('assets', intval($ticket['ticket_asset_id']), 'asset_client_id')) === $client_id ? intval($ticket['ticket_asset_id']) : 0;

$document_form_mode = 'add';
$document_name = escapeHtml($ticket['ticket_subject']);
$document_description = $document_content = '';
$document_folder_id = 0;
$document_client_visible = 0;

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-file-medical me-2"></i>Write up <?= escapeHtml(ticketReference($ticket)) ?> as a document <small class="opacity-75 ms-1">for <?= escapeHtml($client_name) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-ai-document-form data-ticket-id="<?= $ticket_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="asset" value="<?= $asset_id ?>">
    <input type="hidden" name="from_ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">
        <div class="alert alert-info py-2 small d-flex align-items-center gap-2" data-ai-document-status>
            <i class="fas fa-spinner fa-spin"></i><span>AI is writing it up from the ticket - what was wrong, why, and how it was put right...</span>
        </div>
        <?php require __DIR__ . '/../document/document_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_document" class="btn btn-primary text-bold" data-ai-document-save disabled><i class="fa fa-check me-2"></i>Create document</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('[data-ai-document-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var status = form.querySelector('[data-ai-document-status]');
    var save = form.querySelector('[data-ai-document-save]');
    var textarea = form.querySelector('textarea[name="content"]');

    function say(tone, icon, text) {
        status.className = 'alert alert-' + tone + ' py-2 small d-flex align-items-center gap-2';
        status.innerHTML = '<i class="fas fa-fw ' + icon + '"></i><span></span>';
        status.querySelector('span').textContent = text;
    }

    // The editor is made as the window opens - wait for it
    function editor(then, tries) {
        var found = window.tinymce ? tinymce.get().filter(function (ed) { return ed.getElement() === textarea; })[0] : null;
        if (found && found.initialized) {
            then(found);
        } else if ((tries || 0) < 100) {
            setTimeout(function () { editor(then, (tries || 0) + 1); }, 100);
        } else {
            then(null);
        }
    }

    fetch('/agent/ajax.php?ai_ticket_document', {
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
            form.querySelector('[name="name"]').value = answer.title;
            form.querySelector('[name="description"]').value = answer.description;
            editor(function (ed) {
                if (ed) {
                    ed.setContent(answer.html);
                } else {
                    textarea.value = answer.html;
                }
                say('success', 'fa-check-circle', 'Drafted by AI from the ticket' + (answer.removed ? ' - ' + answer.removed + ' password' + (answer.removed === 1 ? '' : 's') + ' or key' + (answer.removed === 1 ? '' : 's') + ' left out as [removed]' : '') + '. Check it, change what you like, then create it.');
                status.setAttribute('data-ai-document-ready', '');
                save.disabled = false;
            });
        })
        .catch(function (error) {
            say('warning', 'fa-exclamation-triangle', (error && error.message ? error.message : 'It couldn\'t be drafted.') + ' You can still write it here yourself.');
            save.disabled = false;
        });
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
