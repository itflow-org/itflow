/*
 * A list's filters that are on stand out - their field gets a coloured edge - and an x at the end
 * of the search box takes them all off at once. What is on is read from the page's address, so a
 * filter only counts once it has been applied, against the filter form above the list (a GET form
 * in a card header): its dropdowns, its search box and anything in its collapsed extra filters.
 * Hidden fields outside those - the client the list is for, the view tab - are where you are, not
 * filters, and stay, as do the columns, view and sort picked. A list that has its own "Clear all" keeps it and gets no x.
 */
(function () {
    'use strict';

    function start() {
        var params = new URLSearchParams(window.location.search);

        document.querySelectorAll('.app-main .card-header form').forEach(function (form) {
            if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') {
                return;
            }
            var on = [];
            Array.prototype.forEach.call(form.elements, function (el) {
                // How the list is shown (its columns, view, sort) isn't a filter
                if (!el.name || el.disabled || /^(show_column|view|show|sort|order)\b/.test(el.name)) {
                    return;
                }
                var isFilter = el.tagName === 'SELECT'
                    || (el.tagName === 'INPUT' && ['search', 'text'].indexOf(el.type) !== -1)
                    || (el.type === 'hidden' && el.closest('.collapse'));
                if (!isFilter) {
                    return;
                }
                var applied = params.getAll(el.name).filter(function (value) {
                    return value !== '' && !(el.name === 'canned_date' && ['custom', 'alltime'].indexOf(value) !== -1);
                });
                if (!applied.length) {
                    return;
                }
                if (on.indexOf(el.name) === -1) {
                    on.push(el.name);
                }
                if (el.type !== 'hidden') {
                    el.classList.add('itflow-filter-on');
                }
            });
            if (!on.length) {
                return;
            }

            var header = form.closest('.card-header') || form;
            var hasOwnClear = Array.prototype.some.call(header.querySelectorAll('a'), function (a) {
                return /^\s*clear all\s*$/i.test(a.textContent);
            });
            var search = form.querySelector('input[name="q"]');
            var group = search && search.closest('.input-group');
            if (hasOwnClear || !group) {
                return;
            }
            var keep = new URLSearchParams(window.location.search);
            on.concat(['page']).forEach(function (name) {
                keep.delete(name);
            });
            var onlySearch = on.length === 1 && on[0] === 'q';
            var clear = document.createElement('a');
            clear.className = 'btn btn-outline-danger';
            clear.href = window.location.pathname + (keep.toString() ? '?' + keep.toString() : '');
            clear.title = onlySearch ? 'Clear the search' : 'Clear search and filters';
            clear.setAttribute('aria-label', clear.title);
            clear.innerHTML = '<i class="fas fa-times"></i>';
            group.appendChild(clear);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
