/*
 * Pick which - a starter content pack's items chosen one by one, on
 * Maintenance > Starter Content and Get started's Starter content step
 * (admin/includes/starter_content_pick.php):
 *
 *   [data-starter-pick]          a pack - its items ([data-starter-item]), its
 *                                own tick ([data-starter-pack-check]) and count
 *                                ([data-starter-count]) kept in step: ticking
 *                                the pack ticks every item, and the count says
 *                                how many are ticked. Its Add
 *                                ([data-starter-add]) waits while none are.
 *   [data-starter-all] / -none   tick every item of the pack, or none
 *   [data-starter-preview=pack]  the item ([data-starter-key]) whole, in the
 *                                #starterPreview window
 *
 * Without it, the items can still be ticked and unticked and are sent as they
 * are; the server goes by them.
 */
(function () {
    'use strict';

    function initPick(pack) {
        var items = Array.prototype.slice.call(pack.querySelectorAll('[data-starter-item]'));
        if (!items.length) {
            return;
        }
        var check = pack.querySelector('[data-starter-pack-check]');
        var counts = Array.prototype.slice.call(pack.querySelectorAll('[data-starter-count]'));
        var adds = Array.prototype.slice.call(pack.querySelectorAll('[data-starter-add]'));
        var of = items.length;

        function draw() {
            var ticked = items.filter(function (item) { return item.checked; }).length;
            if (check) {
                check.checked = ticked > 0;
                check.indeterminate = ticked > 0 && ticked < of;
            }
            counts.forEach(function (count) {
                var short = count.dataset.starterFormat === 'short';
                var more = count.dataset.starterMore === '1';
                if (short) {
                    count.textContent = ticked === of ? String(of) : ticked + ' of ' + of;
                } else {
                    count.textContent = ticked === of ? of + (more ? ' more to add' : ' to add') : (ticked ? ticked + ' of ' + of + ' to add' : 'None picked');
                }
            });
            adds.forEach(function (add) {
                add.disabled = ticked === 0;
            });
        }
        function setAll(on) {
            items.forEach(function (item) { item.checked = on; });
            draw();
        }

        if (check) {
            check.addEventListener('change', function () { setAll(check.checked); });
        }
        items.forEach(function (item) { item.addEventListener('change', draw); });
        pack.querySelectorAll('[data-starter-all]').forEach(function (button) {
            button.addEventListener('click', function () { setAll(true); });
        });
        pack.querySelectorAll('[data-starter-none]').forEach(function (button) {
            button.addEventListener('click', function () { setAll(false); });
        });
        draw();
    }

    // Preview - asked of the server each time, shown in the one window
    function preview(button) {
        var modal = document.getElementById('starterPreview');
        if (!modal || typeof bootstrap === 'undefined') {
            return;
        }
        var title = modal.querySelector('#starterPreviewTitle');
        var body = modal.querySelector('[data-starter-preview-body]');
        title.textContent = 'Preview';
        body.innerHTML = '<div class="text-center text-body-secondary py-5"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Opening it</div>';
        bootstrap.Modal.getOrCreateInstance(modal).show();

        var url = modal.dataset.starterPreviewUrl + '?pack=' + encodeURIComponent(button.dataset.starterPreview) + '&item=' + encodeURIComponent(button.dataset.starterKey);
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (answer) {
                if (!answer || answer.error) {
                    throw new Error(answer && answer.error ? answer.error : 'It could not be opened.');
                }
                title.textContent = answer.title;
                var pack = document.createElement('span');
                pack.className = 'small fw-normal text-body-secondary ms-2';
                pack.textContent = answer.pack;
                title.appendChild(pack);
                body.innerHTML = answer.html;
                body.scrollTop = 0;
            })
            .catch(function (error) {
                body.textContent = '';
                var message = document.createElement('div');
                message.className = 'alert alert-warning mb-0';
                message.textContent = error && error.message ? error.message : 'It could not be opened.';
                body.appendChild(message);
            });
    }

    function init() {
        document.querySelectorAll('[data-starter-pick]').forEach(initPick);
        document.addEventListener('click', function (e) {
            var button = e.target.closest('[data-starter-preview]');
            if (button) {
                e.preventDefault();
                preview(button);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
