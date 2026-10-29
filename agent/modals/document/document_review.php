<?php

/*
 * Review a document with AI (aiDocumentReview(), functions/ai_document.php) -
 * whether it looks good, needs work or is out of date, what to fix or check
 * (the most important first), a one-line summary for it, and the passwords
 * and keys written in it, which belong in Credentials. Use as its summary
 * saves that line (rename_document); Mark reviewed is the document page's.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support');

$document_id = intval($_GET['id'] ?? 0);
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_name, document_description, document_client_id, document_archived_at FROM documents WHERE document_id = $document_id"));
if (!$row) {
    echo json_encode(['error' => 'That document no longer exists.']);
    exit;
}
$client_id = intval($row['document_client_id']);
enforceClientAccess();

// Using AI is a permission of its own
if (!aiCan()) {
    permissionRefuse(permissionRefusedText('module_ai', 1, 0));
}

$can_edit = lookupUserPermission('module_support') >= 2 && !$row['document_archived_at'];

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-clipboard-check me-2"></i>Review <strong><?= escapeHtml($row['document_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-ai-review-form data-document-id="<?= $document_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_id" value="<?= $document_id ?>">
    <input type="hidden" name="name" value="<?= escapeHtml($row['document_name']) ?>">
    <input type="hidden" name="description" value="" data-ai-review-summary-value>
    <div class="modal-body">
        <div data-ai-review aria-live="polite">
            <div class="text-body-secondary"><i class="fas fa-spinner fa-spin me-2"></i>AI is reading it...</div>
        </div>
        <div class="d-none mt-3" data-ai-review-summary-box>
            <div class="small text-body-secondary mb-1">A summary for it</div>
            <div class="p-2 border rounded bg-body-tertiary" data-ai-review-summary></div>
            <?php if (trim((string) $row['document_description']) !== '') { ?>
                <div class="small text-body-secondary mt-1">Now: <?= escapeHtml($row['document_description']) ?></div>
            <?php } ?>
        </div>
        <div class="small text-body-secondary mt-3 d-none" data-ai-review-said><i class="fas fa-fw fa-robot me-1"></i>Reviewed by AI - a second pair of eyes, not the last word. Passwords and keys in it weren't sent.</div>
    </div>
    <div class="modal-footer">
        <?php if ($can_edit) { ?>
            <button type="submit" name="rename_document" class="btn btn-primary text-bold d-none" data-ai-review-use-summary><i class="fas fa-fw fa-align-left me-2"></i>Use as its summary</button>
            <a class="btn btn-light border d-none" href="post.php?mark_document_reviewed=<?= $document_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" data-ai-review-mark><i class="fas fa-fw fa-check me-2"></i>Mark reviewed</a>
        <?php } ?>
        <button type="button" class="btn btn-light border" data-ai-review-again disabled><i class="fas fa-fw fa-redo me-2"></i>Again</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('[data-ai-review-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var target = form.querySelector('[data-ai-review]');
    var said = form.querySelector('[data-ai-review-said]');
    var summaryBox = form.querySelector('[data-ai-review-summary-box]');
    var summary = form.querySelector('[data-ai-review-summary]');
    var summaryValue = form.querySelector('[data-ai-review-summary-value]');
    var useSummary = form.querySelector('[data-ai-review-use-summary]');
    var mark = form.querySelector('[data-ai-review-mark]');
    var again = form.querySelector('[data-ai-review-again]');
    var verdicts = {
        'good': ['success', 'fa-check-circle', 'Looks good'],
        'needs work': ['warning', 'fa-tools', 'Needs work'],
        'out of date': ['danger', 'fa-history', 'Out of date']
    };
    var severities = { high: 'danger', medium: 'warning', low: 'secondary' };

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function ask() {
        again.disabled = true;
        [useSummary, mark].forEach(function (button) {
            if (button) {
                button.classList.add('d-none');
            }
        });
        said.classList.add('d-none');
        summaryBox.classList.add('d-none');
        target.innerHTML = '<div class="text-body-secondary"><i class="fas fa-spinner fa-spin me-2"></i>AI is reading it...</div>';
        fetch('/agent/ajax.php?ai_document_review', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({ document_id: form.getAttribute('data-document-id') }).toString()
        })
            .then(function (response) {
                return response.json().catch(function () {
                    throw new Error('');
                });
            })
            .then(function (answer) {
                if (answer.error || !answer.verdict) {
                    throw new Error(answer.error || '');
                }
                target.innerHTML = '';
                var verdict = verdicts[answer.verdict] || verdicts['needs work'];
                var head = el('div', 'd-flex align-items-center gap-2 mb-3');
                head.setAttribute('data-ai-review-verdict', answer.verdict);
                var badge = el('span', 'badge text-bg-' + verdict[0] + ' fs-6 fw-normal');
                badge.appendChild(el('i', 'fas fa-fw ' + verdict[1] + ' me-1'));
                badge.appendChild(document.createTextNode(verdict[2]));
                head.appendChild(badge);
                head.appendChild(el('span', 'text-body-secondary small', answer.findings.length ? answer.findings.length + ' thing' + (answer.findings.length === 1 ? '' : 's') + ' to look at' : 'Nothing to fix'));
                target.appendChild(head);
                if (answer.secrets) {
                    var secrets = el('div', 'alert alert-warning py-2 small');
                    secrets.setAttribute('data-ai-review-secrets', answer.secrets);
                    secrets.appendChild(el('i', 'fas fa-fw fa-key me-1'));
                    secrets.appendChild(document.createTextNode(answer.secrets + ' password' + (answer.secrets === 1 ? ' or key is' : 's or keys are') + ' written in it. Keep them in Credentials, and link the credential to this document instead - anyone who can read it can read them.'));
                    target.appendChild(secrets);
                }
                if (answer.findings.length) {
                    var list = el('ul', 'list-unstyled mb-0');
                    answer.findings.forEach(function (finding) {
                        var item = el('li', 'd-flex gap-2 mb-2');
                        item.setAttribute('data-ai-review-finding', finding[0]);
                        item.appendChild(el('span', 'badge text-bg-' + (severities[finding[0]] || 'secondary') + ' align-self-start mt-1 text-uppercase', finding[0]));
                        item.appendChild(el('span', '', finding[1]));
                        list.appendChild(item);
                    });
                    target.appendChild(list);
                }
                if (answer.summary) {
                    summary.textContent = answer.summary;
                    summaryValue.value = answer.summary;
                    summaryBox.classList.remove('d-none');
                    if (useSummary) {
                        useSummary.classList.remove('d-none');
                    }
                }
                if (mark) {
                    mark.classList.remove('d-none');
                }
                said.classList.remove('d-none');
                again.disabled = false;
            })
            .catch(function (error) {
                target.innerHTML = '';
                var p = el('p', 'text-danger-emphasis mb-0', error && error.message ? error.message : 'Could not review it - try again.');
                p.setAttribute('data-ai-review-error', '');
                target.appendChild(p);
                again.disabled = false;
            });
    }

    again.addEventListener('click', ask);
    ask();
})();
</script>

<?php
require_once '../../../includes/modal_footer.php';
