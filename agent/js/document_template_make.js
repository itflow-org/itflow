/*
 * Making a document from a template - the Document from template window
 * (agent/modals/document/document_add_from_template.php) and New document for
 * a client (admin/modals/document_template/document_template_use.php). When
 * the template or the client is picked, what it asks for - the site and
 * contact its placeholders come from, and its fill-ins - is drawn in the
 * form's [data-doc-template-asks] box (agent/ajax.php document_template_asks).
 *
 * The windows are ajax modals that run this each time they open: each form is
 * drawn as it opens, and the change listener is bound once.
 */
(function () {

    function draw(form) {
        const box = form.querySelector('[data-doc-template-asks]');
        const template = form.querySelector('[data-doc-template-select]');
        const client = form.querySelector('[data-doc-template-client]');
        if (!box || !template || !client) {
            return;
        }
        const templateId = parseInt(template.value, 10) || 0;
        const clientId = parseInt(client.value, 10) || 0;
        const key = templateId + ':' + clientId;
        if (box.dataset.drawn === key) {
            return;
        }
        box.dataset.drawn = key;
        if (!templateId || !clientId) {
            box.innerHTML = '';
            box.hidden = true;
            return;
        }
        fetch('/agent/ajax.php?document_template_asks=' + templateId + '&client_id=' + clientId, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                // Picked again meanwhile - the later answer draws it
                if (box.dataset.drawn !== key) {
                    return;
                }
                box.innerHTML = data.html || '';
                box.hidden = !data.html;
            })
            .catch(function () {
                box.innerHTML = '';
                box.hidden = true;
            });
    }

    document.querySelectorAll('form[data-doc-template-make]').forEach(draw);

    if (window.itflowDocumentTemplateMakeBound) {
        return;
    }
    window.itflowDocumentTemplateMakeBound = true;

    document.addEventListener('change', function (e) {
        const field = e.target.closest ? e.target.closest('[data-doc-template-select], [data-doc-template-client]') : null;
        const form = field ? field.closest('form[data-doc-template-make]') : null;
        if (form) {
            draw(form);
        }
    });
})();
