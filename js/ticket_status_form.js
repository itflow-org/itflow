// New Ticket Status and Edit (admin/modals/ticket_status/ticket_status_form.php).
// The preview is the status's badge as tickets will show it, redrawn as its
// name and colour change - with dark or white text, whichever reads on the
// colour, as ticketStatusBadgeHtml() in functions/ticket_status.php draws it.
// A swatch, the colour picker or a typed hex sets the colour. The server
// checks it all again.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file.

(function () {

    // Dark or white text on a #rrggbb colour - tagTextColor() in functions/tags.php
    function textColor(value) {
        const channel = function (hex) {
            const c = parseInt(hex, 16) / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        };
        const luminance = 0.2126 * channel(value.substr(1, 2)) + 0.7152 * channel(value.substr(3, 2)) + 0.0722 * channel(value.substr(5, 2));
        return luminance > 0.4 ? '#212529' : '#fff';
    }

    // #rrggbb from what was typed - null when it isn't a colour yet
    function hexColor(value) {
        value = String(value).trim().toLowerCase();
        if (value.charAt(0) !== '#') {
            value = '#' + value;
        }
        if (/^#[0-9a-f]{3}$/.test(value)) {
            value = '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
        }
        return /^#[0-9a-f]{6}$/.test(value) ? value : null;
    }

    document.querySelectorAll('[data-ticket-status-form]:not([data-wired])').forEach(function (form) {
        form.setAttribute('data-wired', '1');

        const badge = form.querySelector('[data-ticket-status-preview] .badge');
        const name = form.querySelector('[data-ticket-status-name]');
        const color = form.querySelector('[data-ticket-status-color]');
        const pick = form.querySelector('[data-ticket-status-color-pick]');
        const hex = form.querySelector('[data-ticket-status-color-hex]');
        const swatches = form.querySelectorAll('[data-ticket-status-swatch]');

        function draw() {
            badge.textContent = name.value.trim() || 'New status';
            badge.style.setProperty('--itflow-status-bg', color.value);
            badge.style.setProperty('--itflow-status-text', textColor(color.value));
        }

        // $from says which control set it, so that one isn't rewritten as it is typed in
        function setColor(value, from) {
            color.value = value;
            if (from !== 'pick') {
                pick.value = value;
            }
            if (from !== 'hex') {
                hex.value = value;
            }
            hex.classList.remove('is-invalid');
            swatches.forEach(function (swatch) {
                const on = swatch.dataset.ticketStatusSwatch === value;
                swatch.classList.toggle('active', on);
                swatch.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            draw();
        }

        swatches.forEach(function (swatch) {
            swatch.addEventListener('click', function () {
                setColor(swatch.dataset.ticketStatusSwatch, 'swatch');
            });
        });
        pick.addEventListener('input', function () {
            setColor(pick.value.toLowerCase(), 'pick');
        });
        hex.addEventListener('input', function () {
            const value = hexColor(hex.value);
            if (value) {
                setColor(value, 'hex');
            } else {
                hex.classList.toggle('is-invalid', hex.value.trim() !== '');
            }
        });
        // A half-typed hex goes back to the colour in use when the field is left
        hex.addEventListener('change', function () {
            if (!hexColor(hex.value)) {
                hex.value = color.value;
                hex.classList.remove('is-invalid');
            }
        });
        name.addEventListener('input', draw);

        draw();
    });
})();
