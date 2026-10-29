// New Tax and Edit Tax (admin/modals/tax/tax_form.php): one rate, or parts.
// "Split into parts" turns the rate into a first part; parts can be added
// and removed, and "Comes to" shows the whole rate as it is typed - a part
// on top is charged on the line plus the parts above it, like
// taxEffectivePercent() in functions/tax.php. Removing every part goes back
// to one rate. The server works the rate out again on saving.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file.

(function () {

    document.querySelectorAll('[data-tax-form]:not([data-wired])').forEach(function (form) {
        form.setAttribute('data-wired', '1');

        const single = form.querySelector('[data-tax-single]');
        const percent = form.querySelector('[data-tax-percent]');
        const partsBox = form.querySelector('[data-tax-parts]');
        const rows = form.querySelector('[data-tax-part-rows]');
        const template = form.querySelector('[data-tax-part-template]');
        const total = form.querySelector('[data-tax-total]');
        const name = form.querySelector('input[name="name"]');
        let nextKey = rows.querySelectorAll('[data-tax-part]').length;

        function number(value) {
            const n = parseFloat(String(value).replace(/[%\s]/g, ''));
            return isNaN(n) ? 0 : n;
        }

        function text(n) {
            return String(Math.round(n * 10000) / 10000);
        }

        function update() {
            const parts = rows.querySelectorAll('[data-tax-part]');
            const usingParts = parts.length > 0;
            single.classList.toggle('d-none', usingParts);
            partsBox.classList.toggle('d-none', !usingParts);
            percent.disabled = usingParts;
            percent.required = !usingParts;

            let sum = 0;
            parts.forEach(function (row, i) {
                const compound = row.querySelector('[data-tax-part-compound]');
                const nameInput = row.querySelector('[data-tax-part-name]');
                const percentInput = row.querySelector('[data-tax-part-percent]');
                // The first part has nothing above it to go on top of
                compound.disabled = i === 0;
                if (i === 0) {
                    compound.checked = false;
                }
                compound.closest('.form-check').classList.toggle('invisible', i === 0);
                nameInput.required = true;
                percentInput.required = true;
                const p = number(percentInput.value);
                sum += compound.checked ? p * (1 + sum / 100) : p;
            });
            total.textContent = text(sum) + '%';
        }

        function addPart(values) {
            const key = 'n' + (nextKey++);
            const holder = document.createElement('div');
            holder.innerHTML = template.innerHTML.replace(/__KEY__/g, key);
            const row = holder.firstElementChild;
            if (values) {
                row.querySelector('[data-tax-part-name]').value = values.name || '';
                row.querySelector('[data-tax-part-percent]').value = values.percent || '';
            }
            rows.appendChild(row);
            update();
            return row;
        }

        form.querySelector('[data-tax-split]').addEventListener('click', function () {
            addPart({name: name.value.trim(), percent: percent.value.trim()});
            addPart().querySelector('[data-tax-part-name]').focus();
        });

        form.querySelector('[data-tax-add-part]').addEventListener('click', function () {
            addPart().querySelector('[data-tax-part-name]').focus();
        });

        rows.addEventListener('click', function (e) {
            const remove = e.target.closest('[data-tax-remove-part]');
            if (!remove) {
                return;
            }
            const parts = rows.querySelectorAll('[data-tax-part]');
            // Down to one part, it is just the rate again
            if (parts.length === 2) {
                const keep = parts[0] === remove.closest('[data-tax-part]') ? parts[1] : parts[0];
                const keepPercent = keep.querySelector('[data-tax-part-percent]').value;
                parts.forEach(function (row) {
                    row.remove();
                });
                percent.value = keepPercent;
            } else {
                remove.closest('[data-tax-part]').remove();
            }
            update();
        });

        rows.addEventListener('input', update);
        rows.addEventListener('change', update);
        update();
    });

})();
