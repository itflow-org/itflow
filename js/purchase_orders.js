/*
 * Purchasing > Receiving (agent/receiving.php): All fills in what is left on
 * a line, and a line's serials box grows to as many rows as came in.
 */
(function () {
    var form = document.querySelector('[data-receiving]');
    if (!form || form.dataset.wired) {
        return;
    }
    form.dataset.wired = '1';

    function rows(line) {
        var qty = line.querySelector('[data-receive-qty]');
        var serials = line.querySelector('textarea');
        if (!qty || !serials) {
            return;
        }
        var n = Math.floor(Number(String(qty.value).replace(/[\s,]/g, '')));
        serials.rows = isFinite(n) && n > 1 ? Math.min(n, 8) : 1;
    }

    form.addEventListener('click', function (e) {
        var all = e.target.closest('[data-receive-all]');
        if (!all) {
            return;
        }
        var line = all.closest('[data-receiving-line]');
        var qty = line.querySelector('[data-receive-qty]');
        qty.value = line.dataset.left;
        rows(line);
        qty.focus();
    });

    form.addEventListener('input', function (e) {
        if (e.target.matches('[data-receive-qty]')) {
            rows(e.target.closest('[data-receiving-line]'));
        }
    });
})();
