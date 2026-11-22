/*
 * ITFlow select, before it runs - loaded in the <head>, ahead of everything on the page.
 *
 * js/itflow_select.js draws every <select class="select2">, but it loads at the end of the page,
 * so the browser first paints the plain <select> it has read so far. A "nothing" choice written
 * "- Anyone -" showed with its dashes for that moment, then as "Anyone" once the picker drew it.
 * This takes the dashes off those labels as the page is read, before anything is painted, and
 * marks the option (data-none="1") so the picker still knows it is the "nothing" choice. Its
 * quieter colour comes from css/itflow_custom.css. It stops once the page has been read - a
 * select added later is drawn by the picker straight away, so it never shows the dashes.
 *
 * A file rather than an inline script: the client portal's pages allow no inline scripts.
 */
(function () {
    'use strict';

    var NONE_LABEL = /^\s*-\s*(.*?)\s*-\s*$/;

    function tidy(option) {
        if (!option || option.tagName !== 'OPTION' || option.dataset.none === '1' || option.hasAttribute('label')) {
            return;
        }
        var select = option.closest('select');
        if (!select || !select.classList.contains('select2')) {
            return;
        }
        var none = NONE_LABEL.exec(option.textContent || '');
        if (none && none[1] !== '') {
            option.dataset.none = '1';
            option.textContent = none[1];
        }
    }

    // As the parser adds each option, and the text inside it - in one piece or several
    var watcher = new MutationObserver(function (records) {
        records.forEach(function (record) {
            if (record.type === 'characterData') {
                tidy(record.target.parentNode);
                return;
            }
            tidy(record.target);
            record.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) {
                    return;
                }
                if (node.tagName === 'OPTION') {
                    tidy(node);
                } else if (node.firstElementChild) {
                    // Added with its insides already there - by a script, not the parser
                    node.querySelectorAll('select.select2 option').forEach(tidy);
                }
            });
        });
    });
    watcher.observe(document.documentElement, { childList: true, subtree: true, characterData: true });

    // Once the page has been read: one last look, then stop
    document.addEventListener('DOMContentLoaded', function () {
        watcher.disconnect();
        document.querySelectorAll('select.select2 option').forEach(tidy);
    });
})();
