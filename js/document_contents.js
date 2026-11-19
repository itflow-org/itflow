/*
 * ITFlow - a contents list for a long document or Knowledge Base article (agent/document.php,
 * agent/kb_article.php): its headings, in the [data-document-contents] card beside it, each one
 * a link down to it. Only when it has three headings or more - a short one doesn't need it.
 * Headings without an id get one, so a link to a section can be shared.
 */
(function () {
    'use strict';

    const card = document.querySelector('[data-document-contents]');
    const body = document.querySelector('.itflow-document-body');
    if (!card || !body) {
        return;
    }
    const headings = Array.prototype.filter.call(body.querySelectorAll('h1, h2, h3, h4'), function (h) {
        return h.textContent.trim() !== '';
    });
    if (headings.length < 3) {
        return;
    }

    // Indent from the highest level the document actually uses
    const top = Math.min.apply(null, headings.map(function (h) { return Number(h.tagName[1]); }));
    const used = {};
    const list = document.createElement('ol');
    list.className = 'list-unstyled mb-0';

    headings.forEach(function (h, i) {
        if (!h.id) {
            let slug = 'section-' + h.textContent.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60);
            if (slug === 'section-' || used[slug] || document.getElementById(slug)) {
                slug = 'section-' + (i + 1);
            }
            h.id = slug;
        }
        used[h.id] = true;
        const li = document.createElement('li');
        li.className = 'itflow-document-contents-level-' + (Number(h.tagName[1]) - top);
        const a = document.createElement('a');
        a.href = '#' + h.id;
        a.textContent = h.textContent.trim();
        a.addEventListener('click', function (e) {
            e.preventDefault();
            h.scrollIntoView({ behavior: 'smooth', block: 'start' });
            history.replaceState(null, '', '#' + h.id);
        });
        li.appendChild(a);
        list.appendChild(li);
    });

    card.querySelector('nav').appendChild(list);
    card.hidden = false;

    // Opened at a section's link
    if (location.hash) {
        const target = document.getElementById(decodeURIComponent(location.hash.slice(1)));
        if (target && body.contains(target)) {
            target.scrollIntoView({ block: 'start' });
        }
    }
})();
