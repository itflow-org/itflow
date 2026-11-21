<?php

/*
 * Write a ticket up for the Knowledge Base - the New Article window
 * (kb_article_form.php) started from the ticket, as a draft for agents only.
 * With AI set up for documentation it writes the article (aiTicketKbArticle(),
 * functions/ai_ticket.php, through ai_ticket_kb_article in agent/ajax.php):
 * what to do the next time anyone has this problem, with the client, its
 * people and its systems left out - an article is for every client. Without
 * it, the window starts with the ticket's subject and what was asked, and the
 * steps are written here. add_kb_article (agent/post/kb.php) creates it, and
 * the ticket's history says it was written up.
 */

require_once '../../../includes/modal_header.php';

$kb_closed = kbClosedText(2);
if ($kb_closed !== '') {
    exit(json_encode(['error' => $kb_closed]));
}

$ticket = ticketForModal($_GET['ticket_id'] ?? 0, 2);
$ticket_id = intval($ticket['ticket_id']);
$kb_use_ai = aiReady('Documentation');

$kb_form_mode = 'add';
$kb_form = [
    'kb_article_title' => mb_substr((string) $ticket['ticket_subject'], 0, 200),
    'kb_article_review_months' => 12,
    'kb_article_published_at' => null,
    // Without AI: what was asked, as words - the steps are for the agent to write
    'kb_article_content' => $kb_use_ai ? '' : '<h2>Symptoms</h2><p>' . nl2br(escapeHtml(aiHtmlToText($ticket['ticket_details'], 2000))) . '</p><h2>Resolution</h2><ol><li>[the steps that put it right]</li></ol>',
];

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-book-medical me-2"></i>Write up <?= escapeHtml(ticketReference($ticket)) ?> for the Knowledge Base</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-kb-from-ticket="<?= $kb_use_ai ? 'ai' : '' ?>" data-ticket-id="<?= $ticket_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="from_ticket_id" value="<?= $ticket_id ?>">
    <div class="modal-body">
        <?php if ($kb_use_ai) { ?>
            <div class="alert alert-info py-2 small d-flex align-items-center gap-2" data-kb-from-ticket-status>
                <i class="fas fa-spinner fa-spin"></i><span>AI is writing it up from the ticket, for any client - what was wrong, why, and how it was put right...</span>
            </div>
        <?php } else { ?>
            <div class="alert alert-info py-2 small d-flex align-items-center gap-2">
                <i class="fas fa-fw fa-info-circle"></i><span>Started from what the ticket asked. Write the steps that put it right, and take out anything about the client before anyone outside your team can read it.</span>
            </div>
        <?php } ?>
        <?php require __DIR__ . '/kb_article_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_kb_article" class="btn btn-primary fw-bold" data-kb-from-ticket-save<?= $kb_use_ai ? ' disabled' : '' ?>><i class="fas fa-check me-2"></i>Create Article</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php if ($kb_use_ai) { ?>
<script>
(function () {
    var form = document.querySelector('[data-kb-from-ticket="ai"]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var status = form.querySelector('[data-kb-from-ticket-status]');
    var save = form.querySelector('[data-kb-from-ticket-save]');
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

    fetch('/agent/ajax.php?ai_ticket_kb_article', {
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
            form.querySelector('[name="title"]').value = answer.title;
            form.querySelector('[name="summary"]').value = answer.description;
            editor(function (ed) {
                if (ed) {
                    ed.setContent(answer.html);
                } else {
                    textarea.value = answer.html;
                }
                say('success', 'fa-check-circle', 'Written by AI from the ticket' + (answer.removed ? ' - ' + answer.removed + ' password' + (answer.removed === 1 ? '' : 's') + ' or key' + (answer.removed === 1 ? '' : 's') + ' left out as [removed]' : '') + '. Read it through for anything about the client, then create it.');
                status.setAttribute('data-kb-from-ticket-ready', '');
                save.disabled = false;
            });
        })
        .catch(function (error) {
            say('warning', 'fa-exclamation-triangle', (error && error.message ? error.message : 'It couldn\'t be written.') + ' You can still write it here yourself.');
            save.disabled = false;
        });
})();
</script>
<?php } ?>

<?php
require_once '../../../includes/modal_footer.php';
