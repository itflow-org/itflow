/*
 * Get started (admin/getting_started.php, admin/getting_started/) - in the
 * browser. The server checks everything (admin/post/getting_started.php);
 * this only shows and hides, and watches for cron.
 *
 *   [data-gs-reveal=id]        a switch that shows that element while on
 *   [data-gs-show=id]          shows the secret in that field, or hides it again
 *   [data-gs-cron-poll=url]    asks that address every 10 seconds whether cron
 *                              has been heard from, and shows the step again
 *                              once it has
 *   [data-gs-pack]             a starter content pack's tick and its Pick which
 *                              list kept in step - ticking the pack ticks every
 *                              item, and its count says how many are ticked
 *   form                       sent once, the button pressed showing it is on
 *                              its way
 */
(function () {
    'use strict';

    function initReveal(toggle) {
        var target = document.getElementById(toggle.dataset.gsReveal);
        if (!target) {
            return;
        }
        function sync() {
            target.hidden = !toggle.checked;
        }
        toggle.addEventListener('change', sync);
        sync();
    }

    function initShow(button) {
        var field = document.getElementById(button.dataset.gsShow);
        if (!field) {
            return;
        }
        button.addEventListener('click', function () {
            var shown = field.type === 'text';
            field.type = shown ? 'password' : 'text';
            button.title = shown ? 'Show' : 'Hide';
            var icon = button.querySelector('i');
            if (icon) {
                icon.className = shown ? 'far fa-eye' : 'far fa-eye-slash';
            }
        });
    }

    // Cron's heartbeat - checked while the step waits, and the page shown again once it beats
    function initCronPoll(box) {
        var url = box.dataset.gsCronPoll;
        var waiting = false;
        function ask() {
            if (waiting || document.hidden) {
                return;
            }
            waiting = true;
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (answer) {
                    waiting = false;
                    if (answer && answer.running) {
                        window.location.reload();
                    }
                })
                .catch(function () { waiting = false; });
        }
        setInterval(ask, 10000);
    }

    // A pack and the items under its Pick which
    function initPack(pack) {
        var check = pack.querySelector('[data-gs-pack-check]');
        var items = Array.prototype.slice.call(pack.querySelectorAll('[data-gs-item]'));
        var count = pack.querySelector('[data-gs-pack-count]');
        if (!check || !items.length) {
            return;
        }
        var of = items.length;
        var more = count && count.dataset.gsMore === '1';
        function draw() {
            var ticked = items.filter(function (item) { return item.checked; }).length;
            check.checked = ticked > 0;
            check.indeterminate = ticked > 0 && ticked < of;
            if (count) {
                count.textContent = ticked === of ? of + (more ? ' more to add' : ' to add') : (ticked ? ticked + ' of ' + of + ' to add' : 'None picked');
            }
        }
        function setAll(on) {
            items.forEach(function (item) { item.checked = on; });
            draw();
        }
        check.addEventListener('change', function () { setAll(check.checked); });
        items.forEach(function (item) { item.addEventListener('change', draw); });
        var all = pack.querySelector('[data-gs-pick-all]');
        var none = pack.querySelector('[data-gs-pick-none]');
        if (all) { all.addEventListener('click', function () { setAll(true); }); }
        if (none) { none.addEventListener('click', function () { setAll(false); }); }
        draw();
    }

    function initBusy(form) {
        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) {
                return;
            }
            if (form.dataset.gsSent) {
                e.preventDefault();
                return;
            }
            form.dataset.gsSent = '1';
            var button = e.submitter || form.querySelector('[type="submit"]');
            if (button && !button.classList.contains('btn-close')) {
                button.classList.add('disabled');
                button.setAttribute('aria-disabled', 'true');
                button.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>');
            }
        });
    }

    function init() {
        document.querySelectorAll('[data-gs-reveal]').forEach(initReveal);
        document.querySelectorAll('[data-gs-show]').forEach(initShow);
        document.querySelectorAll('[data-gs-cron-poll]').forEach(initCronPoll);
        document.querySelectorAll('[data-gs-pack]').forEach(initPack);
        document.querySelectorAll('form').forEach(initBusy);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
