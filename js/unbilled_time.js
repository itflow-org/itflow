/*
 * Unbilled Time (agent/unbilled_time.php).
 *
 * One client: tick the tickets to bill and change their hours - each row's
 * amount, and the total at the rate and tax picked, follow as you type.
 * Every client: tick the clients to bill - the total is what their tickets
 * come to now. Either way the button says what it will make, and the new
 * invoice's date, category and scope only show when there is a new one.
 *
 * Needs js/billing_items.js (itflowBillingMoney, itflowBillingNumber).
 *
 *     itflowUnbilledTime({ currency: 'USD', locale: 'en-US' });
 */
function itflowUnbilledTime(options) {
    var opts = options || {};
    var form = document.querySelector('form[data-unbilled]');
    if (!form) {
        return;
    }
    var mode = form.getAttribute('data-unbilled');
    var rows = Array.prototype.slice.call(form.querySelectorAll('[data-unbilled-row]'));
    var all = form.querySelector('[data-unbilled-all]');
    var rate = form.querySelector('[data-unbilled-rate]');
    var tax = form.querySelector('[data-unbilled-tax]');
    var target = form.querySelector('[data-unbilled-target]');
    var summary = form.querySelector('[data-unbilled-summary]');
    var total = form.querySelector('[data-unbilled-total]');
    var submit = form.querySelector('[data-unbilled-submit]');
    var submitLabel = form.querySelector('[data-unbilled-submit-label]');

    function money(amount) {
        return itflowBillingMoney(amount, opts.currency, opts.locale);
    }

    function number(value) {
        var n = itflowBillingNumber(value);
        return isNaN(n) ? 0 : n;
    }

    function plural(n, word) {
        return n + ' ' + word + (n === 1 ? '' : 's');
    }

    function update() {
        var picked = 0;
        var tickets = 0;
        var hours = 0;
        var amount = 0;
        var perHour = rate ? number(rate.value) : 0;
        var option = tax && tax.options[tax.selectedIndex];
        var percent = option ? Number(option.getAttribute('data-percent') || 0) : 0;

        rows.forEach(function (row) {
            var pick = row.querySelector('[data-unbilled-pick]');
            var on = !pick || pick.checked;
            row.classList.toggle('itflow-unbilled-off', !on);
            if (mode === 'client') {
                var input = row.querySelector('[data-unbilled-hours]');
                var h = input ? number(input.value) : 0;
                var line = Math.round(h * perHour * 100) / 100;
                var cell = row.querySelector('[data-unbilled-amount]');
                if (cell && input) {
                    cell.textContent = money(line);
                }
                if (input) {
                    input.classList.toggle('is-invalid', input.value.trim() !== '' && (isNaN(itflowBillingNumber(input.value)) || h < 0));
                }
                if (on) {
                    picked++;
                    tickets++;
                    hours += h;
                    amount += line;
                }
            } else if (on) {
                picked++;
                tickets += Number(row.getAttribute('data-tickets') || 0);
                hours += Number(row.getAttribute('data-hours') || 0);
                amount += Number(row.getAttribute('data-value') || 0);
            }
        });

        var taxed = amount + Math.round(amount * percent) / 100;
        hours = Math.round(hours * 100) / 100;
        if (summary) {
            summary.textContent = (mode === 'client' ? '' : plural(picked, 'client') + ' · ') + plural(tickets, 'ticket') + ' · ' + hours + ' h' + (percent ? ' · with tax' : '');
        }
        if (total) {
            total.textContent = money(taxed);
        }
        if (submit) {
            submit.disabled = picked === 0;
        }
        if (submitLabel) {
            if (mode === 'client') {
                submitLabel.textContent = target && target.value !== '0' ? 'Add to Invoice' : 'Make Invoice';
            } else {
                submitLabel.textContent = picked === 1 ? 'Make 1 Invoice' : 'Make ' + picked + ' Invoices';
            }
        }
        if (all) {
            all.checked = picked > 0 && picked === rows.length;
            all.indeterminate = picked > 0 && picked < rows.length;
        }
        // Not billable needs something ticked too
        form.querySelectorAll('button[name="unbilled_write_off"]').forEach(function (button) {
            button.disabled = picked === 0;
        });
    }

    // A new invoice's own fields - only when one is made
    function targetChanged() {
        var making = !target || mode !== 'client' || target.value === '0';
        form.querySelectorAll('[data-unbilled-new]').forEach(function (section) {
            section.hidden = !making;
            section.querySelectorAll('input, select').forEach(function (input) {
                input.disabled = !making;
            });
        });
        update();
    }

    if (all) {
        all.addEventListener('change', function () {
            rows.forEach(function (row) {
                var pick = row.querySelector('[data-unbilled-pick]');
                if (pick) {
                    pick.checked = all.checked;
                }
            });
            update();
        });
    }
    form.addEventListener('input', function (e) {
        if (e.target.matches('[data-unbilled-hours], [data-unbilled-rate]')) {
            update();
        }
    });
    form.addEventListener('change', function (e) {
        if (e.target.matches('[data-unbilled-pick], [data-unbilled-tax]')) {
            update();
        } else if (e.target === target) {
            targetChanged();
        }
    });
    // Clicking a ticket's row (not its link or hours) ticks it
    rows.forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a, input, button, select, label')) {
                return;
            }
            var pick = row.querySelector('[data-unbilled-pick]');
            if (pick) {
                pick.checked = !pick.checked;
                update();
            }
        });
    });
    // Enter in an hours box moves to the next one rather than billing
    form.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.matches('[data-unbilled-hours]')) {
            return;
        }
        e.preventDefault();
        var inputs = Array.prototype.slice.call(form.querySelectorAll('[data-unbilled-hours]'));
        var next = inputs[inputs.indexOf(e.target) + 1];
        if (next) {
            next.focus();
            next.select();
        }
    });

    targetChanged();
}
