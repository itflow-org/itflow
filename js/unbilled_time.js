/*
 * Unbilled Time (agent/unbilled_time.php).
 *
 * One client: tick the tickets to bill and change their hours - each row's
 * amount, and the total at the rate and tax picked, follow as you type. The
 * same for its expenses and their prices, each with its margin on its cost,
 * and its trips and their prices. Every client: tick the clients to bill -
 * the total is what their tickets, expenses and trips come to now. Either way the button says what it will make,
 * and the new invoice's date, category and scope only show when there is a
 * new one.
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
    var expenseRows = Array.prototype.slice.call(form.querySelectorAll('[data-unbilled-expense-row]'));
    var tripRows = Array.prototype.slice.call(form.querySelectorAll('[data-unbilled-trip-row]'));
    var all = form.querySelector('[data-unbilled-all]');
    var expenseAll = form.querySelector('[data-unbilled-expense-all]');
    var tripAll = form.querySelector('[data-unbilled-trip-all]');
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
        var expenses = 0;
        var trips = 0;
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
                expenses += Number(row.getAttribute('data-expenses') || 0);
                trips += Number(row.getAttribute('data-trips') || 0);
                hours += Number(row.getAttribute('data-hours') || 0);
                amount += Number(row.getAttribute('data-value') || 0);
            }
        });

        // A client's expenses - each at its price, with the margin on its cost
        var expensePicked = 0;
        expenseRows.forEach(function (row) {
            var pick = row.querySelector('[data-unbilled-expense-pick]');
            var on = !pick || pick.checked;
            var input = row.querySelector('[data-unbilled-expense-price]');
            var price = input ? number(input.value) : 0;
            var cost = Number(row.getAttribute('data-cost') || 0);
            var margin = row.querySelector('[data-unbilled-expense-margin]');
            row.classList.toggle('itflow-unbilled-off', !on);
            if (input) {
                input.classList.toggle('is-invalid', input.value.trim() !== '' && (isNaN(itflowBillingNumber(input.value)) || price < 0));
                if (margin) {
                    margin.textContent = price > 0 ? Math.round((price - cost) / price * 100) + '% margin' : 'No charge';
                    margin.classList.toggle('text-danger-emphasis', price < cost);
                }
            }
            if (on && pick) {
                expensePicked++;
                expenses++;
                amount += Math.round(price * 100) / 100;
            }
        });
        if (expenseAll) {
            expenseAll.checked = expensePicked > 0 && expensePicked === expenseRows.length;
            expenseAll.indeterminate = expensePicked > 0 && expensePicked < expenseRows.length;
        }

        // A client's trips - each at its price
        var tripPicked = 0;
        tripRows.forEach(function (row) {
            var pick = row.querySelector('[data-unbilled-trip-pick]');
            var on = !pick || pick.checked;
            var input = row.querySelector('[data-unbilled-trip-price]');
            var price = input ? number(input.value) : 0;
            row.classList.toggle('itflow-unbilled-off', !on);
            if (input) {
                input.classList.toggle('is-invalid', input.value.trim() !== '' && (isNaN(itflowBillingNumber(input.value)) || price < 0));
            }
            if (on && pick) {
                tripPicked++;
                trips++;
                amount += Math.round(price * 100) / 100;
            }
        });
        if (tripAll) {
            tripAll.checked = tripPicked > 0 && tripPicked === tripRows.length;
            tripAll.indeterminate = tripPicked > 0 && tripPicked < tripRows.length;
        }

        var taxed = amount + Math.round(amount * percent) / 100;
        hours = Math.round(hours * 100) / 100;
        if (summary) {
            var parts = mode === 'client' ? [] : [plural(picked, 'client')];
            if (tickets || (!expenses && !trips)) {
                parts.push(plural(tickets, 'ticket'), hours + ' h');
            }
            if (expenses) {
                parts.push(plural(expenses, 'expense'));
            }
            if (trips) {
                parts.push(plural(trips, 'trip'));
            }
            summary.textContent = parts.join(' · ') + (percent ? ' · with tax' : '');
        }
        if (total) {
            total.textContent = money(taxed);
        }
        if (mode === 'client') {
            picked += expensePicked + tripPicked;
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
            var rowsPicked = picked - (mode === 'client' ? expensePicked + tripPicked : 0);
            all.checked = rowsPicked > 0 && rowsPicked === rows.length;
            all.indeterminate = rowsPicked > 0 && rowsPicked < rows.length;
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
    if (expenseAll) {
        expenseAll.addEventListener('change', function () {
            expenseRows.forEach(function (row) {
                var pick = row.querySelector('[data-unbilled-expense-pick]');
                if (pick) {
                    pick.checked = expenseAll.checked;
                }
            });
            update();
        });
    }
    if (tripAll) {
        tripAll.addEventListener('change', function () {
            tripRows.forEach(function (row) {
                var pick = row.querySelector('[data-unbilled-trip-pick]');
                if (pick) {
                    pick.checked = tripAll.checked;
                }
            });
            update();
        });
    }
    form.addEventListener('input', function (e) {
        if (e.target.matches('[data-unbilled-hours], [data-unbilled-rate], [data-unbilled-expense-price], [data-unbilled-trip-price]')) {
            update();
        }
    });
    form.addEventListener('change', function (e) {
        if (e.target.matches('[data-unbilled-pick], [data-unbilled-expense-pick], [data-unbilled-trip-pick], [data-unbilled-tax]')) {
            update();
        } else if (e.target === target) {
            targetChanged();
        }
    });
    // Clicking a ticket's, expense's or trip's row (not its link or box) ticks it
    rows.concat(expenseRows, tripRows).forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a, input, button, select, label')) {
                return;
            }
            var pick = row.querySelector('[data-unbilled-pick], [data-unbilled-expense-pick], [data-unbilled-trip-pick]');
            if (pick) {
                pick.checked = !pick.checked;
                update();
            }
        });
    });
    // Enter in an hours or price box moves to the next one rather than billing
    form.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.matches('[data-unbilled-hours], [data-unbilled-expense-price], [data-unbilled-trip-price]')) {
            return;
        }
        e.preventDefault();
        var inputs = Array.prototype.slice.call(form.querySelectorAll('[data-unbilled-hours], [data-unbilled-expense-price], [data-unbilled-trip-price]'));
        var next = inputs[inputs.indexOf(e.target) + 1];
        if (next) {
            next.focus();
            next.select();
        }
    });

    targetChanged();
}
