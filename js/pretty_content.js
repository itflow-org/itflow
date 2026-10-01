/**
 * Bootstrap-styles user-authored HTML inside div.prettyContent (documents,
 * ticket replies, templates, queued mail): tables get .table, images get
 * .img-fluid so they stay inside their container. Idempotent - safe to run
 * again over content it has already touched.
 */
function prettyContent() {
    // Add class to tables
    document.querySelectorAll('div.prettyContent table').forEach(function (el) {
        el.classList.add('table');
    });

    // Add img-fluid class to img tags
    document.querySelectorAll('div.prettyContent img').forEach(function (el) {
        el.classList.add('img-fluid');
    });
}

/*
 * Not a bare DOMContentLoaded listener: ajax modal payloads (document_view,
 * document_version_view, mail_queue_message_view) load this file long after
 * DOMContentLoaded has fired, so the listener never ran there. Not
 * itflowReady() either - full pages load this before app.js, and the client
 * and guest portals never load app.js at all.
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', prettyContent);
} else {
    prettyContent();
}
