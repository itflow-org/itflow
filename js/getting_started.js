/*
 * Get started (admin/getting_started.php, admin/getting_started/) - in the
 * browser. The server checks everything (admin/post/getting_started.php);
 * this only shows and hides, and watches for cron. The Starter content
 * step's Pick which is js/starter_content.js, shared with Maintenance >
 * Starter Content.
 *
 *   [data-gs-reveal=id]        a switch that shows that element while on
 *   [data-gs-show=id]          shows the secret in that field, or hides it again
 *   [data-gs-cron-poll=url]    asks that address every 10 seconds whether cron
 *                              has been heard from, and shows the step again
 *                              once it has
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
        document.querySelectorAll('form').forEach(initBusy);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
