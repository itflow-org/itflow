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

        setTimeout(function () {
            window.location.reload();
        }, seconds * 1000);

    });
})();
