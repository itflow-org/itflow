/*
 * ITFlow - view tabs that don't fit fold into More.
 *
 * The tab bars over the lists (.itflow-asset-tabs, the ones that scroll sideways:
 * flex-nowrap overflow-auto) used to run off the edge of the card, with the last
 * tabs out of sight behind a scroll nobody noticed. Now the tabs that don't fit
 * go, from the end, into a More menu at the end of the bar, and come back as the
 * bar gets wider. The tab that is open never goes into it. More wears the alarm
 * of a tab in it that has one (Overdue in red, Unassigned in amber), with that
 * tab's count.
 *
 * The tabs themselves stay where they are, only hidden (.itflow-tab-folded), so
 * whatever a page does with them still works - the tickets list updating their
 * counts (agent/js/tickets_live.js), reconcile's tabs that filter in place.
 * More's items are built as the menu opens, from the tabs as they are then: a
 * link for a tab that is a link, otherwise a button that clicks the tab. More
 * and its items aren't .nav-link, so nothing counting the tabs counts them.
 */
(function () {
    'use strict';

    if (window.itflowTabFold) {
        return;
    }
    window.itflowTabFold = true;

    var FOLDED = 'itflow-tab-folded';

    function tabsOf(bar) {
        return Array.prototype.filter.call(bar.children, function (el) {
            return el.classList.contains('nav-link');
        });
    }

    // A tab the page itself hides isn't ours to show or fold
    function pageHidden(el) {
        return !el.classList.contains(FOLDED) && getComputedStyle(el).display === 'none';
    }

    function setup(bar) {
        bar.classList.add('itflow-tabs-fold');

        var more = document.createElement('div');
        more.className = 'dropdown itflow-tabs-more ' + FOLDED;
        more.innerHTML = '<button type="button" class="itflow-tabs-more-toggle text-nowrap" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true">'
            + 'More<span class="badge rounded-pill ms-1 d-none"></span><i class="fas fa-caret-down ms-1" aria-hidden="true"></i></button>'
            + '<div class="dropdown-menu dropdown-menu-end"></div>';
        bar.appendChild(more);
        var badge = more.querySelector('.badge');
        var menu = more.querySelector('.dropdown-menu');

        function folded() {
            return tabsOf(bar).filter(function (tab) {
                return tab.classList.contains(FOLDED);
            });
        }

        // More shows the most pressing alarm in it - red before amber - with that tab's count
        function markMore() {
            var alarm = null;
            ['text-bg-danger', 'text-bg-warning'].some(function (cls) {
                return folded().some(function (tab) {
                    var b = tab.querySelector('.badge.' + cls);
                    if (b) {
                        alarm = [cls, b.textContent.trim(), tab];
                    }
                    return !!b;
                });
            });
            badge.className = 'badge rounded-pill ms-1' + (alarm ? ' ' + alarm[0] : ' d-none');
            badge.textContent = alarm ? alarm[1] : '';
            more.firstChild.title = alarm ? alarm[2].textContent.replace(/\s+/g, ' ').trim() + ' is in here' : '';
        }

        function fit() {
            observer.disconnect();
            var tabs = tabsOf(bar);
            var rules = Array.prototype.filter.call(bar.children, function (el) {
                return el.classList.contains('vr');
            });
            tabs.concat(rules).forEach(function (el) {
                el.classList.remove(FOLDED);
            });
            more.classList.add(FOLDED);

            if (bar.scrollWidth > bar.clientWidth + 1) {
                more.classList.remove(FOLDED);
                for (var i = tabs.length - 1; i >= 0 && bar.scrollWidth > bar.clientWidth + 1; i--) {
                    if (!tabs[i].classList.contains('active') && !pageHidden(tabs[i])) {
                        tabs[i].classList.add(FOLDED);
                    }
                }
                // A line between groups only between tabs that are still out
                rules.forEach(function (rule) {
                    var before = false;
                    var after = false;
                    for (var el = rule.previousElementSibling; el; el = el.previousElementSibling) {
                        before = before || (el.classList.contains('nav-link') && !el.classList.contains(FOLDED) && !pageHidden(el));
                    }
                    for (el = rule.nextElementSibling; el; el = el.nextElementSibling) {
                        after = after || (el.classList.contains('nav-link') && !el.classList.contains(FOLDED) && !pageHidden(el));
                    }
                    rule.classList.toggle(FOLDED, !(before && after));
                });
            }
            markMore();
            observer.observe(bar, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['class'] });
        }

        // The menu, from the folded tabs as they are now - a line where a group starts
        more.addEventListener('show.bs.dropdown', function () {
            menu.textContent = '';
            var tabs = folded();
            tabs.forEach(function (tab, n) {
                var prev = tab.previousElementSibling;
                if (n && prev && prev.classList.contains('vr')) {
                    menu.appendChild(Object.assign(document.createElement('div'), { className: 'dropdown-divider' }));
                }
                var href = tab.getAttribute('href') || '';
                var item = document.createElement(href && href.charAt(0) !== '#' ? 'a' : 'button');
                item.className = 'dropdown-item d-flex align-items-center gap-2';
                if (item.tagName === 'A') {
                    item.href = tab.href;
                } else {
                    item.type = 'button';
                    item.addEventListener('click', function () {
                        tab.click();
                    });
                }
                if (tab.title) {
                    item.title = tab.title;
                }
                Array.prototype.forEach.call(tab.childNodes, function (node) {
                    item.appendChild(node.cloneNode(true));
                });
                // The gap spaces them out here, and the count goes to the right
                item.querySelectorAll('.me-1, .ms-1').forEach(function (el) {
                    el.classList.remove('me-1', 'ms-1');
                });
                var count = item.querySelector('.badge');
                if (count) {
                    count.classList.add('ms-auto');
                    item.appendChild(count);
                }
                menu.appendChild(item);
            });
        });

        var queued = false;
        function refit() {
            if (queued) {
                return;
            }
            queued = true;
            requestAnimationFrame(function () {
                queued = false;
                fit();
            });
        }

        // A count or the open tab changed (tickets_live.js, reconcile) - fit again
        var observer = new MutationObserver(function (records) {
            if (records.some(function (r) {
                return !more.contains(r.target);
            })) {
                refit();
            }
        });

        if (window.ResizeObserver) {
            new ResizeObserver(refit).observe(bar);
        } else {
            window.addEventListener('resize', refit);
        }
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(refit);
        }
        fit();
    }

    function init() {
        document.querySelectorAll('.itflow-asset-tabs.nav.flex-nowrap.overflow-auto, .itflow-asset-tabs > .nav.flex-nowrap.overflow-auto').forEach(function (bar) {
            if (!bar.classList.contains('itflow-tabs-fold') && tabsOf(bar).length > 1) {
                setup(bar);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
