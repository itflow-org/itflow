/*
 * The Review with AI window - a document's (agent/modals/document/document_review.php)
 * or a Knowledge Base article's (agent/modals/kb/kb_article_review.php). It asks
 * data-ai-review-url for a review of data-ai-review-id (sent as
 * data-ai-review-param) and shows the verdict, what to fix or check, the
 * passwords and keys written in it (with data-ai-review-secrets as what to do
 * about them), and a summary to use. Again asks once more.
 *
 * The window runs this each time it opens; each form is set up once.
 */
(function () {
    var form = document.querySelector('[data-ai-review-form]:not([data-ready])');
    if (!form) {
        return;
    }
    form.setAttribute('data-ready', '1');
    var params = {};
    params[form.getAttribute('data-ai-review-param')] = form.getAttribute('data-ai-review-id');
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
        fetch(form.getAttribute('data-ai-review-url'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams(params).toString()
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
                var badge = el('span', 'badge rounded-pill text-bg-' + verdict[0] + ' fs-6 fw-normal');
                badge.appendChild(el('i', 'fas fa-fw ' + verdict[1] + ' me-1'));
                badge.appendChild(document.createTextNode(verdict[2]));
                head.appendChild(badge);
                head.appendChild(el('span', 'text-body-secondary small', answer.findings.length ? answer.findings.length + ' thing' + (answer.findings.length === 1 ? '' : 's') + ' to look at' : 'Nothing to fix'));
                target.appendChild(head);
                if (answer.secrets) {
                    var secrets = el('div', 'alert alert-warning py-2 small');
                    secrets.setAttribute('data-ai-review-secrets', answer.secrets);
                    secrets.appendChild(el('i', 'fas fa-fw fa-key me-1'));
                    secrets.appendChild(document.createTextNode(answer.secrets + ' password' + (answer.secrets === 1 ? ' or key is' : 's or keys are') + ' written in it. ' + form.getAttribute('data-ai-review-secrets')));
                    target.appendChild(secrets);
                }
                if (answer.findings.length) {
                    var list = el('ul', 'list-unstyled mb-0');
                    answer.findings.forEach(function (finding) {
                        var item = el('li', 'd-flex gap-2 mb-2');
                        item.setAttribute('data-ai-review-finding', finding[0]);
                        item.appendChild(el('span', 'badge rounded-pill text-bg-' + (severities[finding[0]] || 'secondary') + ' align-self-start mt-1 text-uppercase', finding[0]));
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
