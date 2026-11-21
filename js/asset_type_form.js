// New Asset Type and Edit Asset Type (admin/modals/asset_type/asset_type_form.php).
// The preview follows the name, colour and icon. The icon search narrows the
// icons, and a whole Font Awesome icon name that isn't in the list can be used
// too. On a new type, Start from fills in that type's group, colour, icon,
// fields, details and rack. The OS field's name only matters while it asks
// for one. Its own details are rows: Add a Detail puts on a new one, each
// shows what its kind needs - a unit, a flag, the choices - and removing one
// with values kept in it asks first. The server checks it all again
// (assetTypeFromInput() in functions/asset_type_custom.php).
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file.

(function () {

    // Whether Font Awesome draws an icon by this name
    function iconExists(probe, name) {
        probe.className = 'fas fa-' + name;
        const content = window.getComputedStyle(probe, '::before').content;
        return content !== '' && content !== 'none' && content !== 'normal';
    }

    document.querySelectorAll('[data-asset-type-form]:not([data-wired])').forEach(function (form) {
        form.setAttribute('data-wired', '1');

        const starts = JSON.parse(form.dataset.assetTypeStarts || '{}');
        const previewIcon = form.querySelector('[data-asset-type-preview-icon]');
        const previewName = form.querySelector('[data-asset-type-preview-name]');
        const name = form.querySelector('[data-asset-type-name]');
        const nameWas = name.value.trim();
        const renameNote = form.querySelector('[data-asset-type-rename-note]');
        const tone = form.querySelector('[data-asset-type-tone]');
        const swatches = form.querySelectorAll('[data-asset-type-swatch]');
        const icon = form.querySelector('[data-asset-type-icon]');
        const search = form.querySelector('[data-asset-type-icon-search]');
        const choices = form.querySelectorAll('[data-asset-type-icon-choice]');
        const custom = form.querySelector('[data-asset-type-icon-custom]');
        const none = form.querySelector('[data-asset-type-icon-none]');
        const group = form.querySelector('[data-asset-type-group]');
        const start = form.querySelector('[data-asset-type-start]');
        const osChoice = form.querySelector('[data-asset-type-os-choice]');
        const osField = form.querySelector('[data-asset-type-field][value="os"]');
        const details = form.querySelector('[data-asset-type-details]');
        const template = form.querySelector('[data-asset-type-detail-template]');
        const add = form.querySelector('[data-asset-type-detail-add]');
        const probe = document.createElement('i');
        probe.style.position = 'absolute';
        probe.style.visibility = 'hidden';
        form.appendChild(probe);
        let nextKey = details.querySelectorAll('[data-asset-type-detail]').length;

        function draw() {
            previewName.textContent = name.value.trim() || 'New type';
            previewIcon.className = 'itflow-asset-icon itflow-asset-icon-sm itflow-asset-tone-' + (tone.value || 'other');
            previewIcon.querySelector('i').className = 'fas fa-fw fa-' + (icon.value || 'tag');
        }

        function setTone(value) {
            tone.value = value;
            swatches.forEach(function (swatch) {
                const on = swatch.dataset.assetTypeSwatch === value;
                swatch.classList.toggle('active', on);
                swatch.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            draw();
        }

        function setIcon(value) {
            icon.value = value;
            choices.forEach(function (choice) {
                const on = choice.dataset.assetTypeIconChoice === value;
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
                const value = choice.dataset.assetTypeIconChoice;
                const match = term === '' || value.indexOf(term) !== -1;
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
        }

        // Its operating system field's name, while it asks for one
        function showOs() {
            osChoice.hidden = !osField.checked;
        }

        // What a detail's kind needs - a unit, a flag, its choices
        function showKind(row) {
            const kind = row.querySelector('[data-asset-type-detail-input]').value;
            row.querySelectorAll('[data-asset-type-detail-for]').forEach(function (part) {
                const on = part.dataset.assetTypeDetailFor === kind;
                part.hidden = !on;
                part.querySelectorAll('input').forEach(function (field) {
                    field.disabled = !on;
                });
            });
        }

        function wireDetail(row) {
            showKind(row);
            const kind = row.querySelector('select[data-asset-type-detail-input]');
            if (kind) {
                kind.addEventListener('change', function () {
                    showKind(row);
                });
            }
            row.querySelector('[data-asset-type-detail-remove]').addEventListener('click', function () {
                const values = parseInt(row.dataset.values || '0', 10);
                const label = row.querySelector('[data-asset-type-detail-label]').value.trim() || 'this detail';
                const remove = function () {
                    row.remove();
                    countDetails();
                };
                if (!values) {
                    remove();
                    return;
                }
                itflowConfirm({
                    title: 'Remove ' + label + '?',
                    text: 'It is filled in on ' + values + (values === 1 ? ' asset' : ' assets') + ' - what was kept in it goes when you save.',
                    button: 'Remove',
                    danger: true
                }).then(function (yes) {
                    if (yes) {
                        remove();
                    }
                });
            });
        }

        function countDetails() {
            add.disabled = details.querySelectorAll('[data-asset-type-detail]').length >= parseInt(add.dataset.max || '30', 10);
        }

        name.addEventListener('input', function () {
            draw();
            if (renameNote) {
                renameNote.hidden = name.value.trim() === '' || name.value.trim() === nameWas;
            }
        });
        swatches.forEach(function (swatch) {
            swatch.addEventListener('click', function () {
                setTone(swatch.dataset.assetTypeSwatch);
            });
        });
        choices.forEach(function (choice) {
            choice.addEventListener('click', function () {
                setIcon(choice.dataset.assetTypeIconChoice);
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
                setIcon(visible[0].dataset.assetTypeIconChoice);
            }
        });
        osField.addEventListener('change', showOs);

        // Start from another of ITFlow's types - everything but the name, description and its own details
        if (start) {
            start.addEventListener('change', function () {
                const from = starts[start.value];
                if (!from) {
                    return;
                }
                setTone(from.tone);
                setIcon(from.icon);
                group.value = from.group;
                form.querySelectorAll('[data-asset-type-field]').forEach(function (box) {
                    box.checked = from.fields.indexOf(box.value) !== -1;
                });
                form.querySelectorAll('[data-asset-type-spec]').forEach(function (box) {
                    box.checked = from.specs.indexOf(box.value) !== -1;
                });
                form.querySelectorAll('[data-asset-type-os]').forEach(function (radio) {
                    radio.checked = radio.value === from.os;
                });
                form.querySelector('[data-asset-type-rack]').checked = !!from.rack;
                showOs();
            });
        }

        details.querySelectorAll('[data-asset-type-detail]').forEach(wireDetail);
        add.addEventListener('click', function () {
            const holder = document.createElement('div');
            holder.innerHTML = template.innerHTML.replace(/__KEY__/g, 'n' + (nextKey++));
            const row = holder.firstElementChild;
            details.appendChild(row);
            if (window.itflowSelectAll) {
                window.itflowSelectAll(row);
            }
            wireDetail(row);
            countDetails();
            row.querySelector('[data-asset-type-detail-label]').focus();
        });

        showOs();
        countDetails();
        draw();
    });
})();
