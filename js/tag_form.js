// New Tag and Edit Tag (admin/modals/tag/tag_form.php). The preview shows the
// tag as it will look while its name, colour and icon change - its text dark
// or white by the colour's luminance, like tagTextColor() in
// functions/tags.php. A swatch, the colour picker or a typed hex sets the
// colour. The search narrows the icons; a whole Font Awesome icon name that
// isn't in the list can be used too. The server checks it all again.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file.

(function () {

    // Dark or white text on a #rrggbb colour
    function textColor(value) {
        const channel = function (hex) {
            const c = parseInt(hex, 16) / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        };
        const luminance = 0.2126 * channel(value.substr(1, 2)) + 0.7152 * channel(value.substr(3, 2)) + 0.0722 * channel(value.substr(5, 2));
        return luminance > 0.4 ? '#212529' : '#fff';
    }

    // Whether Font Awesome draws an icon by this name
    function iconExists(probe, name) {
        probe.className = 'fas fa-' + name;
        const content = window.getComputedStyle(probe, '::before').content;
        return content !== '' && content !== 'none' && content !== 'normal';
    }

    document.querySelectorAll('[data-tag-form]:not([data-wired])').forEach(function (form) {
        form.setAttribute('data-wired', '1');

        const preview = form.querySelector('[data-tag-preview]');
        const previewName = form.querySelector('[data-tag-preview-name]');
        const previewIcon = form.querySelector('[data-tag-preview-icon]');
        const name = form.querySelector('[data-tag-name]');
        const color = form.querySelector('[data-tag-color]');
        const hex = form.querySelector('[data-tag-color-hex]');
        const swatches = form.querySelectorAll('[data-tag-swatch]');
        const icon = form.querySelector('[data-tag-icon]');
        const search = form.querySelector('[data-tag-icon-search]');
        const choices = form.querySelectorAll('[data-tag-icon-choice]');
        const custom = form.querySelector('[data-tag-icon-custom]');
        const none = form.querySelector('[data-tag-icon-none]');
        const probe = document.createElement('i');
        probe.style.position = 'absolute';
        probe.style.visibility = 'hidden';
        form.appendChild(probe);

        function setColor(value, from) {
            value = String(value).trim().toLowerCase();
            if (value.charAt(0) !== '#') {
                value = '#' + value;
            }
            if (/^#[0-9a-f]{3}$/.test(value)) {
                value = '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
            }
            if (!/^#[0-9a-f]{6}$/.test(value)) {
                return;
            }
            if (from !== color) {
                color.value = value;
            }
            if (from !== hex) {
                hex.value = value;
            }
            preview.style.setProperty('--itflow-tag-color', value);
            preview.style.setProperty('--itflow-tag-text', textColor(value));
            swatches.forEach(function (swatch) {
                const on = swatch.dataset.tagSwatch === value;
                swatch.classList.toggle('active', on);
                swatch.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        function setIcon(value) {
            icon.value = value;
            previewIcon.className = 'fas fa-fw fa-' + value;
            choices.forEach(function (choice) {
                const on = choice.dataset.tagIconChoice === value;
                choice.classList.toggle('active', on);
                choice.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            custom.classList.toggle('active', custom.dataset.icon === value);
        }

        function filterIcons() {
            const term = search.value.trim().toLowerCase().replace(/^(fa[srb]?\s+)?fa-/, '').replace(/\s+/g, '-');
            let shown = 0;
            let exact = false;
            choices.forEach(function (choice) {
                const match = term === '' || choice.dataset.tagIconChoice.indexOf(term) !== -1;
                choice.hidden = !match;
                shown += match ? 1 : 0;
                exact = exact || choice.dataset.tagIconChoice === term;
            });
            const usable = term !== '' && !exact && /^[a-z0-9-]+$/.test(term) && iconExists(probe, term);
            custom.hidden = !usable;
            if (usable) {
                custom.dataset.icon = term;
                custom.querySelector('i').className = 'fas fa-fw fa-' + term + ' me-1';
                custom.querySelector('span').textContent = 'Use ' + term;
                custom.classList.toggle('active', icon.value === term);
            }
            none.hidden = shown > 0 || usable;
            return shown;
        }

        name.addEventListener('input', function () {
            previewName.textContent = name.value.trim() || 'New tag';
        });
        swatches.forEach(function (swatch) {
            swatch.addEventListener('click', function () {
                setColor(swatch.dataset.tagSwatch);
            });
        });
        color.addEventListener('input', function () {
            setColor(color.value, color);
        });
        hex.addEventListener('input', function () {
            setColor(hex.value, hex);
        });
        hex.addEventListener('change', function () {
            hex.value = color.value;
        });
        // Enter in a colour or search box picks, rather than saving the tag
        hex.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                hex.value = color.value;
            }
        });

        choices.forEach(function (choice) {
            choice.addEventListener('click', function () {
                setIcon(choice.dataset.tagIconChoice);
            });
        });
        custom.addEventListener('click', function () {
            setIcon(custom.dataset.icon);
        });
        search.addEventListener('input', filterIcons);
        search.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            const visible = Array.prototype.filter.call(choices, function (choice) {
                return !choice.hidden;
            });
            if (!custom.hidden) {
                setIcon(custom.dataset.icon);
            } else if (visible.length === 1) {
                setIcon(visible[0].dataset.tagIconChoice);
            }
        });
    });

})();
