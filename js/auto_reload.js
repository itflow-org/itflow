/**
 * Reload a page that is waiting on something cron will do.
 *
 * Opt in by putting data-itflow-reload-seconds="<n>" on the element that says so - the
 * "Checking for updates" alert on Maintenance > Update is the first user. Rendering that
 * attribute is what starts the timer, so a view that is not waiting costs one failed
 * querySelector.
 *
 * Deliberately an attribute rather than an inline <script>: inline scripts are the thing
 * standing between ITFlow and a CSP without unsafe-inline, and this would have been another.
 *
 * The page is never reloaded out from under somebody. While a window, a menu or a
 * confirmation is open, a field is being typed in, or text on the page is selected to copy,
 * the reload waits and looks again every couple of seconds.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        const waiting = document.querySelector('[data-itflow-reload-seconds]');

        if (!waiting) {
            return;
        }

        const seconds = parseInt(waiting.dataset.itflowReloadSeconds, 10);

        if (!Number.isFinite(seconds) || seconds < 1) {
            return;
        }

        const inUse = function () {
            const field = document.activeElement;
            return document.querySelector('.modal.show, .dropdown-menu.show, .swal2-container') !== null
                || (field !== null && /^(INPUT|TEXTAREA|SELECT)$/.test(field.tagName))
                || String(window.getSelection() || '') !== '';
        };

        const reload = function () {
            if (inUse()) {
                setTimeout(reload, 2000);
                return;
            }
            window.location.reload();
        };

        setTimeout(reload, seconds * 1000);

    });
})();
