/*
 * The Knowledge Base article window (agent/modals/kb/kb_article_form.php):
 * Who can read it shows the client choices only for Clients - the tags and
 * clients only while All clients is off - and the help center line only for
 * Anyone. The window is an ajax modal that runs this each time it opens, so
 * the listener is bound once and each new window is set up as it arrives.
 */
function itflowKbAudienceSync(root) {
    const who = root.querySelector('[name="visibility"]');
    if (!who) {
        return;
    }
    const all = root.querySelector('input[type="checkbox"][name="all_clients"]');
    root.querySelectorAll('[data-kb-for]').forEach(function (el) {
        el.hidden = el.dataset.kbFor !== who.value || (el.hasAttribute('data-kb-pick') && all !== null && all.checked);
    });
}

itflowBindOnce('itflowKbAudience', 'change', '[data-kb-audience] [name="visibility"], [data-kb-audience] [name="all_clients"]', function () {
    itflowKbAudienceSync(this.closest('[data-kb-audience]'));
});

document.querySelectorAll('[data-kb-audience]').forEach(itflowKbAudienceSync);
