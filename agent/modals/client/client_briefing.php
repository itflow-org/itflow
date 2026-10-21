<?php

/*
 * A client briefing by AI (aiClientBriefing(), functions/ai_client.php) -
 * before a call, a visit or a quarterly review: how things stand, open work,
 * what keeps coming up, what is coming up, money (with Sales) and what to
 * bring up. For the last 30 days, 90 (a quarter) or a year. Save as a
 * document keeps it in the client's documents (add_document); Copy and Again
 * are beside it.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client');

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();
$client_name = getFieldById('clients', $client_id, 'client_name');
if ($client_name === null) {
    echo json_encode(['error' => 'That client no longer exists.']);
    exit;
}

// Using AI is a permission of its own
if (!aiCan()) {
    permissionRefuse(permissionRefusedText('module_ai', 1, 0));
}

$can_keep = lookupUserPermission('module_support') >= 2 && intval($config_module_enable_itdoc ?? 0) === 1;

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-magic me-2"></i>Briefing on <?= escapeHtml($client_name) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-ai-briefing-form data-client-id="<?= $client_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="name" value="" data-ai-briefing-name>
    <input type="hidden" name="description" value="Written by AI from what ITFlow has on them">
    <input type="hidden" name="content" value="" data-ai-briefing-content>
    <input type="hidden" name="folder" value="0">
    <div class="modal-body">
        <div class="btn-group btn-group-sm mb-3" role="group" aria-label="Looking back over">
            <?php foreach (aiBriefingPeriods() as $days => $label) { ?>
                <input type="radio" class="btn-check" name="days" id="aiBriefing<?= $days ?>" value="<?= $days ?>" autocomplete="off" <?= $days === 90 ? 'checked' : '' ?> data-ai-briefing-days>
                <label class="btn btn-outline-secondary" for="aiBriefing<?= $days ?>" title="<?= escapeHtml(ucfirst($label)) ?>"><?= $days === 30 ? 'Last 30 days' : ($days === 90 ? 'Last quarter' : 'Last year') ?></label>
            <?php } ?>
        </div>
        <div class="itflow-ai-summary" data-ai-briefing aria-live="polite">
            <div class="text-body-secondary"><i class="fas fa-spinner fa-spin me-2"></i>Reading what ITFlow has on them...</div>
        </div>
        <div class="small text-body-secondary mt-3 d-none" data-ai-briefing-said><i class="fas fa-fw fa-robot me-1"></i>Written by AI from what ITFlow has on them<?= lookupUserPermission('module_sales') >= 1 ? '' : ' - their invoices left out, as your role can\'t see them' ?>. Check it before relying on it.</div>
    </div>
    <div class="modal-footer">
        <?php if ($can_keep) { ?>
            <button type="submit" name="add_document" class="btn btn-primary text-bold" data-ai-briefing-save disabled title="Keep it in their documents">
                <i class="fas fa-fw fa-file-alt me-2"></i>Save as a document
            </button>
        <?php } ?>
        <button type="button" class="btn btn-light border" data-ai-briefing-copy disabled><i class="fas fa-fw fa-copy me-2"></i>Copy</button>
        <button type="button" class="btn btn-light border" data-ai-briefing-again disabled><i class="fas fa-fw fa-redo me-2"></i>Again</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('[data-ai-briefing-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var target = form.querySelector('[data-ai-briefing]');
    var said = form.querySelector('[data-ai-briefing-said]');
    var content = form.querySelector('[data-ai-briefing-content]');
    var name = form.querySelector('[data-ai-briefing-name]');
    var save = form.querySelector('[data-ai-briefing-save]');
    var copy = form.querySelector('[data-ai-briefing-copy]');
    var again = form.querySelector('[data-ai-briefing-again]');
    var names = { '30': 'last 30 days', '90': 'last quarter', '365': 'last year' };

    function ready(on) {
        [save, copy, again].forEach(function (button) {
            if (button) {
                button.disabled = !on;
            }
        });
    }

    function days() {
        var picked = form.querySelector('[data-ai-briefing-days]:checked');
        return picked ? picked.value : '90';
    }

    function ask() {
        ready(false);
        content.value = '';
        said.classList.add('d-none');
        target.innerHTML = '<div class="text-body-secondary"><i class="fas fa-spinner fa-spin me-2"></i>Reading what ITFlow has on them...</div>';
        fetch('/agent/ajax.php?ai_client_briefing', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({ client_id: form.getAttribute('data-client-id'), days: days() }).toString()
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
                content.value = answer.html;
                name.value = 'Briefing - ' + new Date().toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) + ', ' + names[days()];
                said.classList.remove('d-none');
                ready(true);
            })
            .catch(function (error) {
                target.innerHTML = '';
                var p = document.createElement('p');
                p.className = 'text-danger-emphasis mb-0';
                p.setAttribute('data-ai-briefing-error', '');
                p.textContent = error && error.message ? error.message : 'Could not brief you - try again.';
                target.appendChild(p);
                again.disabled = false;
            });
    }

    form.querySelectorAll('[data-ai-briefing-days]').forEach(function (radio) {
        radio.addEventListener('change', ask);
    });
    again.addEventListener('click', ask);
    copy.addEventListener('click', function () {
        var text = target.innerText;
        if (navigator.clipboard && window.ClipboardItem) {
            navigator.clipboard.write([new ClipboardItem({
                'text/html': new Blob([content.value], { type: 'text/html' }),
                'text/plain': new Blob([text], { type: 'text/plain' })
            })]).then(function () {
                itflowToast('Briefing copied', 'success');
            }, function () {
                navigator.clipboard.writeText(text);
            });
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                itflowToast('Briefing copied', 'success');
            });
        }
    });
    ask();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
