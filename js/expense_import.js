/*
 * Import Statement's review (agent/expense_import.php?step=review).
 *
 * Each line's vendor, category and client start as they were matched. A
 * vendor picked for a line fills in its category (the vendor's last one)
 * and the same vendor on the other lines from that merchant that nothing
 * matched yet; + New vendor shows a name to make it with. Bill on shows
 * once there is a client. Lines not new (money in, lines already in) are
 * hidden until asked for. The ticked lines need a vendor and category;
 * the summary and button follow the ticks. On Import every line's choices
 * go as one JSON field, so a long statement stays under max_input_vars.
 *
 * Needs js/billing_items.js for itflowBillingMoney when it is loaded, and
 * falls back to Intl otherwise.
 *
 *     itflowExpenseImport({ currency: 'USD', locale: 'en-US', defaults: { vendor_id: category_id } });
 */
function itflowExpenseImport(options) {
    var opts = options || {};
    var form = document.getElementById('expenseImportReview');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';
    var rows = Array.prototype.slice.call(form.querySelectorAll('[data-import-row]'));
    var all = form.querySelector('[data-import-all]');
    var showAll = form.querySelector('[data-import-show-all]');
    var summary = form.querySelector('[data-import-summary]');
    var submit = form.querySelector('[data-import-submit]');
    var submitLabel = form.querySelector('[data-import-submit-label]');
    var picks = form.querySelector('[data-import-picks]');
    var bulkCategory = form.querySelector('[data-import-bulk-category]');
    var bulkClient = form.querySelector('[data-import-bulk-client]');
    var defaults = opts.defaults || {};

    function money(amount) {
        try {
            return new Intl.NumberFormat(opts.locale || undefined, { style: 'currency', currency: opts.currency || 'USD' }).format(amount);
        } catch (e) {
            return amount.toFixed(2);
        }
    }

    function part(row, name) {
        return row.querySelector('[data-import-' + name + ']');
    }

    // One line: the new vendor's name only for + New vendor, Bill on only with a client
    function tidy(row) {
        var vendor = part(row, 'vendor');
        if (!vendor) {
            return;
        }
        var take = part(row, 'take');
        part(row, 'new-vendor').hidden = vendor.value !== 'new';
        part(row, 'billable-box').hidden = part(row, 'client').value === '0' || Number(row.getAttribute('data-amount')) <= 0;
        var on = take.checked;
        vendor.required = on;
        part(row, 'category').required = on;
        part(row, 'new-vendor').required = on && vendor.value === 'new';
        row.classList.toggle('itflow-unbilled-off', !on);
    }

    function update() {
        var count = 0;
        var total = 0;
        rows.forEach(function (row) {
            var take = part(row, 'take');
            tidy(row);
            if (take.checked && !take.disabled) {
                count++;
                total += Number(row.getAttribute('data-amount'));
            }
        });
        summary.textContent = count ? count + ' line' + (count === 1 ? '' : 's') + ' · ' + money(Math.round(total * 100) / 100) : 'Tick the lines to import';
        submitLabel.textContent = count ? 'Import ' + count : 'Import';
        submit.disabled = count === 0;
        if (all) {
            var shown = rows.filter(function (row) { return !row.hidden && !part(row, 'take').disabled; });
            var on = shown.filter(function (row) { return part(row, 'take').checked; }).length;
            all.checked = on > 0 && on === shown.length;
            all.indeterminate = on > 0 && on < shown.length;
        }
    }

    // Start each pick where it was matched
    rows.forEach(function (row) {
        ['vendor', 'category', 'client'].forEach(function (name) {
            var select = part(row, name);
            if (select && select.getAttribute('data-value') !== null) {
                var value = select.getAttribute('data-value');
                if (select.querySelector('option[value="' + CSS.escape(value) + '"]')) {
                    select.value = value;
                }
            }
        });
        if (row.hasAttribute('data-import-extra')) {
            row.hidden = true;
        }
    });

    // A vendor picked fills in its category, and the same merchant's other unmatched lines
    form.addEventListener('change', function (e) {
        var row = e.target.closest('[data-import-row]');
        if (row && e.target.matches('[data-import-vendor]')) {
            row.dataset.touched = '1';
            var vendor = e.target.value;
            var category = part(row, 'category');
            if (vendor !== '' && vendor !== 'new' && defaults[vendor] && !category.dataset.touched) {
                category.value = String(defaults[vendor]);
            }
            var key = row.getAttribute('data-key');
            if (key && vendor !== '') {
                rows.forEach(function (other) {
                    var otherVendor = part(other, 'vendor');
                    if (other === row || other.getAttribute('data-key') !== key || other.dataset.touched || !otherVendor || (otherVendor.value !== '' && otherVendor.value !== 'new')) {
                        return;
                    }
                    otherVendor.value = vendor;
                    part(other, 'new-vendor').value = part(row, 'new-vendor').value;
                    if (!part(other, 'category').dataset.touched) {
                        part(other, 'category').value = category.value;
                    }
                });
            }
        } else if (row && e.target.matches('[data-import-category], [data-import-client]')) {
            e.target.dataset.touched = '1';
        }
        if (e.target === bulkCategory || e.target === bulkClient) {
            var name = e.target === bulkCategory ? 'category' : 'client';
            if (e.target.value !== '') {
                rows.forEach(function (r) {
                    if (part(r, 'take').checked && part(r, name)) {
                        part(r, name).value = e.target.value;
                        part(r, name).dataset.touched = '1';
                    }
                });
            }
            e.target.value = '';
        }
        update();
    });

    // A new vendor's name typed on one line follows to the same merchant's lines
    form.addEventListener('input', function (e) {
        if (!e.target.matches('[data-import-new-vendor]')) {
            return;
        }
        var row = e.target.closest('[data-import-row]');
        var key = row.getAttribute('data-key');
        rows.forEach(function (other) {
            if (other !== row && key && other.getAttribute('data-key') === key && part(other, 'vendor') && part(other, 'vendor').value === 'new' && !other.dataset.touched) {
                part(other, 'new-vendor').value = e.target.value;
            }
        });
    });

    if (all) {
        all.addEventListener('change', function () {
            rows.forEach(function (row) {
                var take = part(row, 'take');
                if (!row.hidden && !take.disabled) {
                    take.checked = all.checked;
                }
            });
            update();
        });
    }
    if (showAll) {
        showAll.addEventListener('change', function () {
            rows.forEach(function (row) {
                if (row.hasAttribute('data-import-extra')) {
                    row.hidden = !showAll.checked;
                    if (row.hidden) {
                        part(row, 'take').checked = false;
                    }
                }
            });
            update();
        });
    }

    // Clicking a line (not its picks) ticks it
    rows.forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a, input, button, select, label, textarea')) {
                return;
            }
            var take = part(row, 'take');
            if (!take.disabled) {
                take.checked = !take.checked;
                update();
            }
        });
    });

    form.addEventListener('submit', function (e) {
        if (!form.reportValidity()) {
            e.preventDefault();
            return;
        }
        var list = rows.map(function (row) {
            var vendor = part(row, 'vendor');
            return {
                n: Number(row.getAttribute('data-n')),
                take: part(row, 'take').checked && !part(row, 'take').disabled,
                vendor: vendor ? vendor.value : '',
                new_vendor: vendor ? part(row, 'new-vendor').value : '',
                category: part(row, 'category') ? part(row, 'category').value : '',
                client: part(row, 'client') ? part(row, 'client').value : '0',
                billable: part(row, 'billable') ? part(row, 'billable').checked : false
            };
        });
        picks.value = JSON.stringify(list);
    });

    update();
}
