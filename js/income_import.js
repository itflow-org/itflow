/*
 * Import Deposits' review (agent/income_import.php?step=review).
 *
 * Each deposit starts as it was matched: a payment on a client's invoices,
 * other income, or a transfer from another account - the details for what
 * it is show beside it. A payment lists the client's invoices still owed;
 * picking the client ticks the one owing the amount, the ones that add up
 * to it, or the oldest first, and the line says what it pays off - in full,
 * part paid, or how much more than they owe. The deposits are applied in
 * order, so two deposits on one invoice don't pay it twice. A client or
 * category picked for one deposit fills in the same payer's others not
 * touched yet. Deposits already in are hidden until asked for. On Import
 * every line's choices go as one JSON field.
 *
 *     itflowIncomeImport({ currency: 'USD', locale: 'en-US', invoices: { client_id: [{ id, ref, balance, due, scope, currency }] } });
 */
function itflowIncomeImport(options) {
    var opts = options || {};
    var form = document.getElementById('incomeImportReview');
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
    var bulkCategory = form.querySelector('[data-income-bulk-category]');
    var invoices = opts.invoices || {};
    var byId = {};
    Object.keys(invoices).forEach(function (client) {
        invoices[client].forEach(function (invoice) {
            byId[invoice.id] = invoice;
        });
    });

    function money(amount, currency) {
        try {
            return new Intl.NumberFormat(opts.locale || undefined, { style: 'currency', currency: currency || opts.currency || 'USD' }).format(amount);
        } catch (e) {
            return amount.toFixed(2);
        }
    }

    function cents(value) {
        return Math.round(Number(value) * 100);
    }

    function part(row, name) {
        return row.querySelector('[data-income-' + name + ']');
    }

    function take(row) {
        return row.querySelector('[data-import-take]');
    }

    function action(row) {
        var select = part(row, 'action');
        return select ? select.value : '';
    }

    // The invoices ticked on a line, the oldest due first
    function picked(row) {
        var box = part(row, 'invoices');
        if (!box) {
            return [];
        }
        return Array.prototype.slice.call(box.querySelectorAll('input:checked')).map(function (input) {
            return byId[input.value];
        }).filter(Boolean).sort(function (a, b) {
            return a.due === b.due ? a.id - b.id : (a.due < b.due ? -1 : 1);
        });
    }

    // Which of a client's invoices a deposit pays: the one owing it, the fewest adding up to it, or the oldest first
    function suggest(list, amount) {
        var want = cents(amount);
        var i;
        for (i = 0; i < list.length; i++) {
            if (cents(list[i].balance) === want) {
                return [list[i].id];
            }
        }
        var count = Math.min(list.length, 14);
        var best = null;
        for (var mask = 1; mask < (1 << count); mask++) {
            var sum = 0;
            var ids = [];
            for (i = 0; i < count; i++) {
                if (mask & (1 << i)) {
                    sum += cents(list[i].balance);
                    ids.push(list[i].id);
                }
            }
            if (sum === want && (best === null || ids.length < best.length)) {
                best = ids;
            }
        }
        if (best) {
            return best;
        }
        var total = 0;
        var oldest = [];
        for (i = 0; i < list.length; i++) {
            oldest.push(list[i].id);
            total += cents(list[i].balance);
            if (total >= want) {
                break;
            }
        }
        return oldest;
    }

    // The client's invoices as ticks, with those given ticked
    function drawInvoices(row, ticked) {
        var box = part(row, 'invoices');
        var client = part(row, 'payer').value;
        var list = invoices[client] || [];
        var n = row.getAttribute('data-n');
        box.innerHTML = '';
        list.forEach(function (invoice) {
            var wrap = document.createElement('div');
            wrap.className = 'form-check small mb-0';
            var input = document.createElement('input');
            input.type = 'checkbox';
            input.className = 'form-check-input';
            input.value = invoice.id;
            input.id = 'incomeImport' + n + 'Invoice' + invoice.id;
            input.checked = ticked.indexOf(invoice.id) !== -1;
            var label = document.createElement('label');
            label.className = 'form-check-label text-truncate d-block';
            label.htmlFor = input.id;
            label.title = invoice.scope || '';
            label.textContent = invoice.ref + ' · owes ' + money(invoice.balance, invoice.currency) + ' · due ' + invoice.due;
            wrap.appendChild(input);
            wrap.appendChild(label);
            box.appendChild(wrap);
        });
        box.hidden = list.length === 0;
    }

    // One line: the details for what it is, what is required when it is ticked
    function tidy(row) {
        var kind = action(row);
        if (!kind) {
            return;
        }
        var on = take(row).checked && !take(row).disabled;
        row.querySelectorAll('[data-income-for]').forEach(function (block) {
            block.hidden = block.getAttribute('data-income-for') !== kind;
        });
        part(row, 'payer').required = on && kind === 'payment';
        part(row, 'category').required = on && kind === 'income';
        part(row, 'account').required = on && kind === 'transfer';
        row.classList.toggle('itflow-unbilled-off', !on);
    }

    function update() {
        var count = 0;
        var total = 0;
        var kinds = { payment: 0, income: 0, transfer: 0 };
        var left = {};
        Object.keys(byId).forEach(function (id) {
            left[id] = cents(byId[id].balance);
        });
        rows.forEach(function (row) {
            tidy(row);
            var on = take(row).checked && !take(row).disabled;
            if (on) {
                count++;
                total += Number(row.getAttribute('data-amount'));
                if (kinds[action(row)] !== undefined) {
                    kinds[action(row)]++;
                }
            }
            if (action(row) !== 'payment') {
                return;
            }
            // What this deposit pays off, after the deposits above it
            var applies = part(row, 'applies');
            var remaining = cents(row.getAttribute('data-amount'));
            var parts = [];
            picked(row).forEach(function (invoice) {
                if (remaining <= 0 || left[invoice.id] <= 0) {
                    return;
                }
                var paying = Math.min(remaining, left[invoice.id]);
                parts.push(paying === left[invoice.id] ? invoice.ref + ' in full' : invoice.ref + ' ' + money(paying / 100, invoice.currency) + ' of ' + money(left[invoice.id] / 100, invoice.currency));
                if (on) {
                    left[invoice.id] -= paying;
                }
                remaining -= paying;
            });
            row.dataset.over = remaining > 0 ? '1' : '';
            applies.className = 'small mt-1 ' + (remaining > 0 ? 'text-danger-emphasis' : 'text-body-secondary');
            if (!part(row, 'payer').value) {
                applies.textContent = '';
            } else if (!parts.length && !picked(row).length) {
                applies.textContent = (invoices[part(row, 'payer').value] || []).length ? 'Tick the invoices it pays' : 'Nothing of theirs is owed - bring it in as other income';
            } else {
                applies.textContent = (parts.length ? 'Pays ' + parts.join(', ') : '') + (remaining > 0 ? (parts.length ? ' - ' : '') + money(remaining / 100) + ' more than they owe' : '');
            }
        });
        var said = [];
        if (kinds.payment) {
            said.push(kinds.payment + ' payment' + (kinds.payment === 1 ? '' : 's'));
        }
        if (kinds.income) {
            said.push(kinds.income + ' other income');
        }
        if (kinds.transfer) {
            said.push(kinds.transfer + ' transfer' + (kinds.transfer === 1 ? '' : 's'));
        }
        summary.textContent = count ? count + ' deposit' + (count === 1 ? '' : 's') + ' · ' + money(Math.round(total * 100) / 100) + ' - ' + said.join(', ') : 'Tick the deposits to import';
        submitLabel.textContent = count ? 'Import ' + count : 'Import';
        submit.disabled = count === 0;
        if (all) {
            var shown = rows.filter(function (row) { return !row.hidden && !take(row).disabled; });
            var on = shown.filter(function (row) { return take(row).checked; }).length;
            all.checked = on > 0 && on === shown.length;
            all.indeterminate = on > 0 && on < shown.length;
        }
    }

    // Start each pick where it was matched
    rows.forEach(function (row) {
        ['action', 'payer', 'category', 'client', 'account'].forEach(function (name) {
            var select = part(row, name);
            if (select && select.getAttribute('data-value') !== null) {
                var value = select.getAttribute('data-value');
                if (value !== '' && select.querySelector('option[value="' + CSS.escape(value) + '"]:not([disabled])')) {
                    select.value = value;
                }
            }
        });
        if (part(row, 'invoices')) {
            var given = (part(row, 'invoices').getAttribute('data-value') || '').split(',').filter(Boolean).map(Number);
            drawInvoices(row, given);
        }
        if (row.hasAttribute('data-import-extra')) {
            row.hidden = true;
        }
    });

    // The same payer's other deposits not touched yet take what was picked for this one
    function follow(row, name, value) {
        var key = row.getAttribute('data-key');
        if (!key) {
            return;
        }
        rows.forEach(function (other) {
            if (other === row || other.getAttribute('data-key') !== key || other.dataset.touched || !part(other, name) || part(other, name).value !== '' && part(other, name).value !== '0') {
                return;
            }
            if (name === 'payer' && action(other) !== 'payment') {
                return;
            }
            part(other, name).value = value;
            if (name === 'payer') {
                drawInvoices(other, suggest(invoices[value] || [], other.getAttribute('data-amount')));
            }
        });
    }

    form.addEventListener('change', function (e) {
        var row = e.target.closest('[data-import-row]');
        if (row && e.target.matches('[data-income-payer]')) {
            row.dataset.touched = '1';
            drawInvoices(row, suggest(invoices[e.target.value] || [], row.getAttribute('data-amount')));
            follow(row, 'payer', e.target.value);
        } else if (row && e.target.matches('[data-income-category]')) {
            row.dataset.touched = '1';
            follow(row, 'category', e.target.value);
        } else if (row && e.target.matches('[data-income-action], [data-income-client], [data-income-account], [data-income-invoices] input')) {
            row.dataset.touched = '1';
            if (e.target.matches('[data-income-action]') && e.target.value !== 'payment' && part(row, 'invoices')) {
                part(row, 'invoices').querySelectorAll('input').forEach(function (input) { input.checked = false; });
            }
            if (e.target.matches('[data-income-action]') && e.target.value === 'payment' && part(row, 'payer').value && !picked(row).length) {
                drawInvoices(row, suggest(invoices[part(row, 'payer').value] || [], row.getAttribute('data-amount')));
            }
        }
        if (e.target === bulkCategory) {
            if (e.target.value !== '') {
                rows.forEach(function (r) {
                    if (take(r).checked && action(r) === 'income') {
                        part(r, 'category').value = e.target.value;
                    }
                });
            }
            e.target.value = '';
        }
        if (row && !e.target.matches('[data-import-take]') && !take(row).disabled && !take(row).checked && e.isTrusted) {
            take(row).checked = true;
        }
        update();
    });

    if (all) {
        all.addEventListener('change', function () {
            rows.forEach(function (row) {
                if (!row.hidden && !take(row).disabled) {
                    take(row).checked = all.checked;
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
                        take(row).checked = false;
                    }
                }
            });
            update();
        });
    }

    // Clicking a line (not its picks) ticks it
    rows.forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a, input, button, select, label, textarea, [data-income-invoices]')) {
                return;
            }
            if (!take(row).disabled) {
                take(row).checked = !take(row).checked;
                update();
            }
        });
    });

    form.addEventListener('submit', function (e) {
        if (!form.reportValidity()) {
            e.preventDefault();
            return;
        }
        // A payment needs its invoices, and no more than they owe
        var wrong = rows.filter(function (row) {
            return take(row).checked && !take(row).disabled && action(row) === 'payment' && (!picked(row).length || row.dataset.over);
        });
        rows.forEach(function (row) {
            var box = part(row, 'invoices');
            if (box) {
                box.classList.toggle('is-invalid', wrong.indexOf(row) !== -1);
            }
        });
        if (wrong.length) {
            e.preventDefault();
            wrong[0].scrollIntoView({ block: 'center' });
            var applies = part(wrong[0], 'applies');
            if (!picked(wrong[0]).length) {
                applies.className = 'small mt-1 text-danger-emphasis';
                applies.textContent = 'Tick the invoices it pays';
            }
            return;
        }
        var list = rows.map(function (row) {
            return {
                n: Number(row.getAttribute('data-n')),
                take: take(row).checked && !take(row).disabled,
                action: action(row),
                client: action(row) === 'payment' ? (part(row, 'payer') ? part(row, 'payer').value : '') : (part(row, 'client') ? part(row, 'client').value : '0'),
                invoices: action(row) === 'payment' ? picked(row).map(function (invoice) { return invoice.id; }) : [],
                category: part(row, 'category') ? part(row, 'category').value : '',
                account: part(row, 'account') ? part(row, 'account').value : ''
            };
        });
        picks.value = JSON.stringify(list);
    });

    update();
}
