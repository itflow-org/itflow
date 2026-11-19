/*
 * A long list keeps its column names in view. The table sits in .table-responsive, which scrolls
 * sideways - and .app-main is a scroll box too - so a CSS sticky header would stick to those, not
 * the window. Instead a copy of the header row is shown fixed at the top of the window once the
 * real one has scrolled off, each column as wide as the real one and moved sideways with the
 * table. Clicks on it (sort links, the tick-all box) go to the real header. Windows are left out.
 */
(function () {
    'use strict';

    var heads = [];
    var queued = false;

    function pathTo(node, root) {
        var path = [];
        while (node && node !== root) {
            path.unshift(Array.prototype.indexOf.call(node.parentNode.children, node));
            node = node.parentNode;
        }
        return node === root ? path : null;
    }

    function nodeAt(root, path) {
        var node = root;
        for (var i = 0; node && i < path.length; i++) {
            node = node.children[path[i]];
        }
        return node;
    }

    function setUp(wrap) {
        var table = wrap.querySelector(':scope > table');
        var thead = table && table.tHead;
        if (!thead || !thead.rows.length || wrap.closest('.modal') || wrap.dataset.itflowFloatHead) {
            return;
        }
        wrap.dataset.itflowFloatHead = '1';

        var box = document.createElement('div');
        box.className = 'itflow-float-head d-print-none';
        box.hidden = true;
        var copy = document.createElement('table');
        copy.className = table.className + ' mb-0';
        copy.setAttribute('aria-hidden', 'true');
        var headCopy = thead.cloneNode(true);
        headCopy.classList.remove('d-none');
        headCopy.querySelectorAll('[id]').forEach(function (node) {
            node.removeAttribute('id');
        });
        headCopy.querySelectorAll('[onclick]').forEach(function (node) {
            node.removeAttribute('onclick');
        });
        headCopy.querySelectorAll('a, button, input').forEach(function (node) {
            node.tabIndex = -1;
        });
        copy.appendChild(headCopy);
        box.appendChild(copy);
        document.body.appendChild(box);

        var head = { wrap: wrap, table: table, thead: thead, box: box, copy: copy };
        heads.push(head);

        box.addEventListener('click', function (e) {
            var target = e.target.closest('a, button, input, label');
            if (!target || !headCopy.contains(target)) {
                return;
            }
            var path = pathTo(target, headCopy);
            var real = path && nodeAt(thead, path);
            if (!real) {
                return;
            }
            e.preventDefault();
            real.click();
            setTimeout(function () {
                place(head);
            });
        });
        wrap.addEventListener('scroll', queue, { passive: true });
    }

    function place(head) {
        var body = head.table.tBodies[0];
        var rect = head.wrap.getBoundingClientRect();
        var headHeight = head.thead.getBoundingClientRect().height;
        var show = !head.thead.classList.contains('d-none') && body && body.rows.length > 0
            && rect.top < 0 && rect.bottom > headHeight * 2 && head.wrap.offsetParent !== null;
        head.box.hidden = !show;
        if (!show) {
            return;
        }
        head.box.style.left = rect.left + 'px';
        head.box.style.width = head.wrap.clientWidth + 'px';
        head.copy.style.width = head.table.getBoundingClientRect().width + 'px';
        head.copy.style.transform = 'translateX(' + (-head.wrap.scrollLeft) + 'px)';
        var from = head.thead.rows[0].cells;
        var to = head.copy.tHead.rows[0].cells;
        for (var i = 0; i < from.length && i < to.length; i++) {
            var width = from[i].getBoundingClientRect().width + 'px';
            to[i].style.width = width;
            to[i].style.minWidth = width;
            to[i].style.maxWidth = width;
        }
        // The tick-all box shows what the real one does
        var realBoxes = head.thead.querySelectorAll('input[type=checkbox]');
        var copyBoxes = head.copy.tHead.querySelectorAll('input[type=checkbox]');
        for (var j = 0; j < realBoxes.length && j < copyBoxes.length; j++) {
            copyBoxes[j].checked = realBoxes[j].checked;
            copyBoxes[j].indeterminate = realBoxes[j].indeterminate;
        }
    }

    function placeAll() {
        queued = false;
        heads.forEach(place);
    }

    function queue() {
        if (!queued) {
            queued = true;
            window.requestAnimationFrame(placeAll);
        }
    }

    function start() {
        document.querySelectorAll('.app-main .table-responsive').forEach(setUp);
        window.addEventListener('scroll', queue, { passive: true });
        var main = document.querySelector('.app-main');
        if (main) {
            main.addEventListener('scroll', queue, { passive: true });
        }
        window.addEventListener('resize', queue);
        document.addEventListener('change', queue);
        queue();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
