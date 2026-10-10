/*
 * ITFlow - client overview (agent/client_overview.php).
 *
 * Saves the quick notes when you click away, but only if they changed, and
 * says so; draws the invoiced-and-paid chart from window.itflowClientOverviewData.
 */
(function () {
    'use strict';

    /* Quick notes */
    const notes = document.getElementById('clientNotes');
    const status = document.querySelector('[data-notes-status]');
    if (notes && !notes.readOnly && typeof itflowPost === 'function') {
        let saved = notes.value;

        notes.addEventListener('blur', function () {
            if (notes.value === saved) {
                return;
            }
            const value = notes.value;
            if (status) {
                status.textContent = 'Saving...';
            }
            itflowPost('ajax.php', {
                client_set_notes: 'TRUE',
                csrf_token: notes.dataset.csrf,
                client_id: notes.dataset.clientId,
                notes: value
            }, function () {
                saved = value;
                if (status) {
                    status.textContent = 'Saved';
                }
            }, function () {
                if (status) {
                    status.textContent = 'Not saved - try again';
                }
            });
        });
    }

    /* Invoiced and paid, last 12 months */
    const data = window.itflowClientOverviewData;
    const canvas = document.getElementById('clientMonthly');
    if (!data || !data.monthly || !canvas || typeof Chart === 'undefined') {
        return;
    }

    const base = typeof itflowChartDefaults === 'function' ? itflowChartDefaults() : { grid: 'rgba(0,0,0,.1)' };
    const root = getComputedStyle(document.body);
    const cssVar = function (name, fallback) {
        return (root.getPropertyValue(name) || '').trim() || fallback;
    };
    const alpha = function (color, a) {
        const m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(color);
        return m ? 'rgba(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ',' + a + ')' : color;
    };

    let money;
    try {
        money = new Intl.NumberFormat(undefined, { style: 'currency', currency: data.currency || 'USD', maximumFractionDigits: 0 });
    } catch (e) {
        money = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
    }
    const short = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 });

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.monthly.labels,
            datasets: [
                { label: 'Invoiced', data: data.monthly.invoiced, backgroundColor: alpha(cssVar('--itflow-accent', '#0d6efd'), 0.75), borderRadius: 3, maxBarThickness: 22 },
                { label: 'Paid', data: data.monthly.paid, backgroundColor: alpha(cssVar('--bs-success', '#198754'), 0.8), borderRadius: 3, maxBarThickness: 22 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 14 } },
                tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + money.format(c.parsed.y); } } }
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 10 } },
                y: { beginAtZero: true, grid: { color: base.grid }, border: { display: false }, ticks: { maxTicksLimit: 5, callback: function (v) { return short.format(v); } } }
            }
        }
    });
})();
