/*
 * Reconcile (agent/reconcile.php): tick the lines the statement shows. Each
 * tick is saved as it is made (agent/ajax.php's reconcile_tick); the
 * cleared balance and the difference follow, and Finish opens once the
 * difference is nothing. The pills and the search box only change what is
 * shown - Tick all shown and Untick all shown act on what they leave.
 *
 *     itflowReconcile({ id: 12, csrf: '...', starting: 1000.00, ending: 1250.00,
 *         card: false, currency: 'USD', locale: 'en-US' });
 *
 * Money is counted in cents, so a long statement adds up exactly.
 */
function itflowReconcile(opts) {
    var root = document.getElementById('reconcile');
    if (!root || root.dataset.wired) {
        return;
    }
    root.dataset.wired = '1';

    var rows = Array.prototype.slice.call(root.querySelectorAll('tr[data-reconcile-line]'));
    var format = new Intl.NumberFormat(opts.locale || undefined, { style: 'currency', currency: opts.currency || 'USD' });
    var money = function (cents) {
        return format.format(cents / 100);
    };
    // A card's balance below zero is what is owed on it
    var balance = function (cents) {
        return opts.card && cents < 0 ? money(-cents) + ' owed' : money(cents);
    };
    var toCents = function (value) {
        return Math.round(parseFloat(value || 0) * 100);
    };
    var starting = toCents(opts.starting);
    var ending = toCents(opts.ending);
    var show = '';
    var query = '';
    var sent = 0;
    var pending = 0;

    var finishes = document.querySelectorAll('[data-reconcile-finish]');
    var finishForm = document.getElementById('reconcileFinish');
    var cleared = document.querySelector('[data-reconcile-cleared]');
    var ticked = document.querySelector('[data-reconcile-ticked]');
    var differences = document.querySelectorAll('[data-reconcile-difference]');
    var differenceCard = document.querySelector('[data-reconcile-difference-card]');
    var differenceNote = document.querySelector('[data-reconcile-difference-note]');

    function box(row) {
        return row.querySelector('[data-reconcile-tick]');
    }

    function matches(row, key) {
        var amount = toCents(row.dataset.amount);
        var on = box(row).checked;
        return key === '' || (key === 'in' && amount > 0) || (key === 'out' && amount < 0)
            || (key === 'ticked' && on) || (key === 'unticked' && !on);
    }

    function searched(row) {
        return query === '' || (row.dataset.search || '').indexOf(query) !== -1;
    }

    // What shows, the pill counts, and where the statement stands
    function redraw() {
        var counts = { '': 0, 'in': 0, 'out': 0, 'ticked': 0, 'unticked': 0 };
        var inCount = 0;
        var inCents = 0;
        var outCount = 0;
        var outCents = 0;
        rows.forEach(function (row) {
            var on = box(row).checked;
            var amount = toCents(row.dataset.amount);
            row.classList.toggle('itflow-reconcile-ticked', on);
            if (on && amount > 0) {
                inCount++;
                inCents += amount;
            } else if (on && amount < 0) {
                outCount++;
                outCents -= amount;
            }
            if (searched(row)) {
                Object.keys(counts).forEach(function (key) {
                    if (matches(row, key)) {
                        counts[key]++;
                    }
                });
            }
            row.hidden = !(searched(row) && matches(row, show));
        });
        Object.keys(counts).forEach(function (key) {
            var badge = root.querySelector('[data-reconcile-count="' + key + '"]');
            if (badge) {
                badge.textContent = counts[key];
            }
        });

        var clearedCents = starting + inCents - outCents;
        var differenceCents = ending - clearedCents;
        if (cleared) {
            cleared.textContent = balance(clearedCents);
        }
        if (ticked) {
            ticked.textContent = (inCount + outCount) + ' ticked - ' + money(inCents) + ' in, ' + money(outCents) + ' out';
        }
        differences.forEach(function (difference) {
            difference.textContent = money(differenceCents);
            difference.classList.toggle('text-success-emphasis', differenceCents === 0);
            difference.classList.toggle('text-danger-emphasis', differenceCents !== 0);
        });
        if (differenceCard) {
            differenceCard.classList.toggle('itflow-reconcile-balanced', differenceCents === 0);
        }
        if (differenceNote) {
            differenceNote.textContent = differenceCents === 0
                ? 'It balances - Finish it'
                : (differenceCents > 0 ? 'What is ticked comes to less than the statement' : 'What is ticked comes to more than the statement');
        }
        finishes.forEach(function (finish) {
            finish.disabled = differenceCents !== 0 || pending > 0;
        });
    }

    // Save ticks; on a refusal put them back as they were
    function save(changed, tick) {
        if (!changed.length) {
            return;
        }
        var ticket = ++sent;
        pending++;
        redraw();
        var undo = function (message) {
            changed.forEach(function (row) {
                box(row).checked = !tick;
            });
            pending--;
            redraw();
            itflowToast(message || 'That tick was not saved - try again.', 'error');
        };
        itflowPost('ajax.php', {
            reconcile_tick: 1,
            csrf_token: opts.csrf,
            reconciliation_id: opts.id,
            tick: tick ? 1 : 0,
            lines: changed.map(function (row) {
                return row.dataset.reconcileLine;
            })
        }, function (text) {
            var result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                result = null;
            }
            if (!result || result.error || !result.totals) {
                undo(result && result.error);
                return;
            }
            pending--;
            // The latest answer is the one to trust for where it stands
            if (ticket === sent && pending === 0) {
                starting = toCents(result.totals.starting);
                ending = toCents(result.totals.ending);
            }
            redraw();
        }, function () {
            undo();
        });
    }

    root.addEventListener('change', function (e) {
        var input = e.target.closest('[data-reconcile-tick]');
        if (!input) {
            return;
        }
        save([input.closest('tr')], input.checked);
    });

    root.querySelectorAll('[data-reconcile-all]').forEach(function (button) {
        button.addEventListener('click', function () {
            var tick = button.dataset.reconcileAll === '1';
            var changed = rows.filter(function (row) {
                return !row.hidden && box(row).checked !== tick && !box(row).disabled;
            });
            changed.forEach(function (row) {
                box(row).checked = tick;
            });
            save(changed, tick);
        });
    });

    root.querySelectorAll('[data-reconcile-show]').forEach(function (pill) {
        pill.addEventListener('click', function (e) {
            e.preventDefault();
            show = pill.dataset.reconcileShow;
            root.querySelectorAll('[data-reconcile-show]').forEach(function (other) {
                other.classList.toggle('active', other === pill);
            });
            redraw();
        });
    });

    var search = root.querySelector('[data-reconcile-search]');
    if (search) {
        search.addEventListener('input', function () {
            query = search.value.trim().toLowerCase().replace(/\$/g, '');
            redraw();
        });
    }

    // Finishing with a tick still being saved would finish on the old ones
    if (finishForm) {
        finishForm.addEventListener('submit', function (e) {
            if (pending > 0) {
                e.preventDefault();
            }
        });
    }

    redraw();
}
