// New Category and Edit Category (admin/modals/category/category_form.php).
// The preview shows the category as it will look on records while its name,
// colour and icon change - an asset status as its badge, anything else its
// icon (or a dot) in its colour, as categoryChip() in functions/categories.php
// draws it. "None" is a choice for both. A swatch, the colour picker or a
// typed hex sets the colour; the search narrows the icons, and a whole Font
// Awesome icon name that isn't in the list can be used too. Renaming a kind
// held by name says the records will be renamed with it. The server checks
// it all again.
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

    // Whether Font Awesome draws an icon by this name
    function iconExists(probe, name) {
        probe.className = 'fas fa-' + name;
        const content = window.getComputedStyle(probe, '::before').content;
        return content !== '' && content !== 'none' && content !== 'normal';
    }

    // #rrggbb from what was typed - '' for none, null when it isn't a colour yet
    function hexColor(value) {
        value = String(value).trim().toLowerCase();
        if (value === '') {
            return '';
        }
        if (value.charAt(0) !== '#') {
            value = '#' + value;
        }
        if (/^#[0-9a-f]{3}$/.test(value)) {
            value = '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
        }
        return /^#[0-9a-f]{6}$/.test(value) ? value : null;
    }

    document.querySelectorAll('[data-category-form]:not([data-wired])').forEach(function (form) {
        form.setAttribute('data-wired', '1');

        const preview = form.querySelector('[data-category-preview]');
        // An asset status's badge - its built-in tone when it has no colour of its own
        const badge = preview.dataset.categoryBadge || '';
        const name = form.querySelector('[data-category-name]');
        const nameWas = form.dataset.categoryNameWas || '';
        const renameNote = form.querySelector('[data-category-rename-note]');
        const color = form.querySelector('[data-category-color]');
        const pick = form.querySelector('[data-category-color-pick]');
        const hex = form.querySelector('[data-category-color-hex]');
        const swatches = form.querySelectorAll('[data-category-swatch]');
        const icon = form.querySelector('[data-category-icon]');
        const search = form.querySelector('[data-category-icon-search]');
        const choices = form.querySelectorAll('[data-category-icon-choice]');
        const custom = form.querySelector('[data-category-icon-custom]');
        const none = form.querySelector('[data-category-icon-none]');
        const probe = document.createElement('i');
        probe.style.position = 'absolute';
        probe.style.visibility = 'hidden';
        form.appendChild(probe);

        // Redraw the preview from the fields
        function draw() {
            const value = color.value;
            const label = name.value.trim() || 'New category';
            if (badge) {
                const pill = preview.querySelector('.badge');
                pill.textContent = label;
                pill.className = 'badge rounded-pill fw-normal itflow-category-badge' + (value ? '' : ' text-bg-' + badge);
                pill.style.backgroundColor = value;
                pill.style.color = value ? textColor(value) : '';
                return;
            }
            // Its icon in its colour, or a dot of it
            const old = preview.querySelector('.itflow-category-mark');
            const mark = document.createElement(icon.value ? 'i' : 'span');
            mark.className = icon.value ? 'fas fa-fw fa-' + icon.value + ' itflow-category-mark' : 'itflow-category-dot itflow-category-mark';
            mark.setAttribute('aria-hidden', 'true');
            mark.style[icon.value ? 'color' : 'backgroundColor'] = value;
            old.replaceWith(mark);
            preview.querySelector('.itflow-category-name').textContent = label;
        }

        function setColor(value, from) {
            value = hexColor(value);
            if (value === null) {
                return;
            }
            color.value = value;
            if (from !== pick && value !== '') {
                pick.value = value;
            }
            if (from !== hex) {
                hex.value = value;
            }
            swatches.forEach(function (swatch) {
                const on = swatch.dataset.categorySwatch === value;
                swatch.classList.toggle('active', on);
                swatch.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            draw();
        }

        function setIcon(value) {
            icon.value = value;
            choices.forEach(function (choice) {
                const on = choice.dataset.categoryIconChoice === value;
                choice.classList.toggle('active', on);
                choice.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            custom.classList.toggle('active', value !== '' && custom.dataset.icon === value);
            draw();
        }

        function filterIcons() {
            const term = search.value.trim().toLowerCase().replace(/^(fa[srb]?\s+)?fa-/, '').replace(/\s+/g, '-');
            let shown = 0;
            let exact = false;
            choices.forEach(function (choice) {
                const value = choice.dataset.categoryIconChoice;
                // "None" stays while nothing is searched
                const match = term === '' || (value !== '' && value.indexOf(term) !== -1);
                choice.hidden = !match;
                shown += match ? 1 : 0;
                exact = exact || value === term;
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
            draw();
            if (renameNote) {
                renameNote.hidden = name.value.trim() === '' || name.value.trim() === nameWas;
            }
        });
        swatches.forEach(function (swatch) {
            swatch.addEventListener('click', function () {
                setColor(swatch.dataset.categorySwatch);
            });
        });
        pick.addEventListener('input', function () {
            setColor(pick.value, pick);
        });
        hex.addEventListener('input', function () {
            setColor(hex.value, hex);
        });
        hex.addEventListener('change', function () {
            hex.value = color.value;
        });
        // Enter in a colour or search box picks, rather than saving
        hex.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                hex.value = color.value;
            }
        });

        choices.forEach(function (choice) {
            choice.addEventListener('click', function () {
                setIcon(choice.dataset.categoryIconChoice);
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
                return !choice.hidden && choice.dataset.categoryIconChoice !== '';
            });
            if (!custom.hidden) {
                setIcon(custom.dataset.icon);
            } else if (visible.length === 1) {
                setIcon(visible[0].dataset.categoryIconChoice);
            }
        });
    });

})();
