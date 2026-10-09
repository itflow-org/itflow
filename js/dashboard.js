/*
 * ITFlow - dashboard charts.
 *
 * agent/dashboard.php hands the numbers over as window.itflowDashboardData;
 * this draws them. Colours are read from the page's own CSS variables when
 * the charts are built, so they follow the theme accent and dark mode the
 * same way everything else on the page does.
 */
(function () {
    'use strict';

    const data = window.itflowDashboardData;
    if (!data || typeof Chart === 'undefined') {
        return;
    }

    const base = typeof itflowChartDefaults === 'function' ? itflowChartDefaults() : { text: '#6c757d', grid: 'rgba(0,0,0,.1)' };
    const root = getComputedStyle(document.body);

    function cssVar(name, fallback) {
        return (root.getPropertyValue(name) || '').trim() || fallback;
    }

    function alpha(color, a) {
        // #rrggbb -> rgba(); anything else is returned as is
        const m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(color);
        return m ? 'rgba(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ',' + a + ')' : color;
    }

    const colors = {
        accent: cssVar('--itflow-accent', '#007bff'),
        success: cssVar('--bs-success', '#198754'),
        danger: cssVar('--bs-danger', '#dc3545'),
        warning: cssVar('--bs-warning', '#ffc107'),
        info: cssVar('--bs-info', '#0dcaf0'),
        muted: cssVar('--bs-secondary-color', '#6c757d'),
        faint: cssVar('--bs-tertiary-color', '#adb5bd'),
        grid: base.grid
    };
    // For slices with no colour of their own (vendors, uncoloured categories)
    const palette = [colors.accent, colors.info, colors.success, colors.warning, cssVar('--bs-purple', '#6f42c1'), colors.danger];

    let money;
    try {
        money = new Intl.NumberFormat(undefined, { style: 'currency', currency: data.currency || 'USD', maximumFractionDigits: 0 });
    } catch (e) {
        money = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
    }
    const moneyShort = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 });
    let moneyCompact;
    try {
        moneyCompact = new Intl.NumberFormat(undefined, { style: 'currency', currency: data.currency || 'USD', notation: 'compact', maximumFractionDigits: 1 });
    } catch (e) {
        moneyCompact = moneyShort;
    }
    const share = new Intl.NumberFormat(undefined, { style: 'percent', maximumFractionDigits: 0 });

    const legend = { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 14 } };

    function draw(id, config) {
        const canvas = document.getElementById(id);
        if (!canvas) {
            return null;
        }
        config.options = Object.assign({ responsive: true, maintainAspectRatio: false }, config.options || {});
        return new Chart(canvas, config);
    }

    function axes(tickFormat, stacked) {
        return {
            x: { stacked: !!stacked, grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 10 } },
            y: { stacked: !!stacked, beginAtZero: true, grid: { color: colors.grid }, border: { display: false }, ticks: { maxTicksLimit: 5, callback: tickFormat } }
        };
    }

    // In a stack, only the top bar gets rounded corners: a bar with something
    // stacked on it in that column stays square
    function roundUnless(above) {
        return function (c) {
            return above[c.dataIndex] > 0 ? 0 : 3;
        };
    }

    function hoursMinutes(hours) {
        const minutes = Math.round(hours * 60);
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return h ? (m ? h + 'h ' + m + 'm' : h + 'h') : m + 'm';
    }

    /* Tickets opened vs resolved per week */
    if (data.ticket_trend) {
        draw('dashTicketTrend', {
            type: 'bar',
            data: {
                labels: data.ticket_trend.map(function (w) { return w.label; }),
                datasets: [
                    { label: 'Opened', data: data.ticket_trend.map(function (w) { return w.opened; }), backgroundColor: colors.accent, borderRadius: 3, maxBarThickness: 14 },
                    { label: 'Resolved', data: data.ticket_trend.map(function (w) { return w.resolved; }), backgroundColor: alpha(colors.success, 0.75), borderRadius: 3, maxBarThickness: 14 }
                ]
            },
            options: {
                plugins: { legend: legend },
                scales: axes(function (v) { return Number.isInteger(v) ? v : ''; })
            }
        });
    }

    /* Cash flow: income and expense bars, last year's income as a line. From
       this month on, what is expected in and out sits on top of each bar as a
       lighter "ghost" of the same colour. */
    if (data.cash_flow) {
        const cf = data.cash_flow;
        const forecast = cf.expected_income.some(function (v) { return v > 0; }) || cf.expected_expenses.some(function (v) { return v > 0; });

        function ghost(color) {
            return {
                backgroundColor: alpha(color, 0.2),
                borderColor: alpha(color, 0.85),
                borderWidth: { top: 1.5, left: 1.5, right: 1.5, bottom: 0 },
                borderRadius: 3,
                maxBarThickness: 22,
                forecast: true
            };
        }

        const datasets = [
            { label: 'Income', data: cf.income, backgroundColor: alpha(colors.success, 0.8), borderRadius: roundUnless(cf.expected_income), maxBarThickness: 22, stack: 'income' }
        ];
        if (forecast) {
            datasets.push(Object.assign({ label: 'Expected income', data: cf.expected_income, stack: 'income' }, ghost(colors.success)));
        }
        datasets.push({ label: 'Expenses', data: cf.expenses, backgroundColor: alpha(colors.danger, 0.75), borderRadius: roundUnless(cf.expected_expenses), maxBarThickness: 22, stack: 'expenses' });
        if (forecast) {
            datasets.push(Object.assign({ label: 'Expected expenses', data: cf.expected_expenses, stack: 'expenses' }, ghost(colors.danger)));
        }
        datasets.push({ type: 'line', label: 'Income last year', data: cf.income_last_year, borderColor: colors.muted, borderDash: [4, 4], borderWidth: 1.5, pointRadius: 0, tension: 0.3, stack: 'last-year', order: -1 });

        draw('dashCashFlow', {
            type: 'bar',
            data: { labels: cf.labels, datasets: datasets },
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: legend,
                    tooltip: {
                        // Leave out the nothing: no expected amount in a past month, no actuals in a future one
                        filter: function (item) {
                            if (item.dataset.forecast) {
                                return item.parsed.y > 0;
                            }
                            return item.dataset.type === 'line' || cf.first_future < 0 || item.dataIndex < cf.first_future;
                        },
                        callbacks: { label: function (c) { return c.dataset.label + ': ' + money.format(c.parsed.y); } }
                    }
                },
                scales: axes(function (v) { return moneyShort.format(v); }, true)
            }
        });
    }

    /* My hours this week, billable under not billable */
    if (data.time_week) {
        const tw = data.time_week;
        const other = tw.map(function (d) { return d.other; });
        draw('dashTimeWeek', {
            type: 'bar',
            data: {
                labels: tw.map(function (d) { return d.label; }),
                datasets: [
                    { label: 'Billable', data: tw.map(function (d) { return d.billable; }), backgroundColor: colors.accent, borderRadius: roundUnless(other), maxBarThickness: 18, stack: 'time' },
                    { label: 'Not billable', data: other, backgroundColor: colors.faint, borderRadius: 3, maxBarThickness: 18, stack: 'time' }
                ]
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        filter: function (item) { return item.parsed.y > 0; },
                        callbacks: { label: function (c) { return c.dataset.label + ': ' + hoursMinutes(c.parsed.y); } }
                    }
                },
                scales: axes(function (v) { return Number.isInteger(v) ? v + 'h' : ''; }, true)
            }
        });
    }

    /* The three donuts: the total in the hole, and a legend underneath with
       each slice's amount and share. The legend is plain HTML so long vendor
       and category names truncate with CSS instead of wrapping the card. */
    function donut(id, set) {
        if (!set || !set.values.length) {
            return;
        }
        let next = 0;
        const slices = set.colors.map(function (c, i) {
            if (set.labels[i] === 'Other') {
                return alpha(colors.muted, 0.5);
            }
            return c || palette[next++ % palette.length];
        });
        const total = set.values.reduce(function (sum, v) { return sum + v; }, 0);

        const chart = draw(id, {
            type: 'doughnut',
            data: { labels: set.labels, datasets: [{ data: set.values, backgroundColor: slices, borderWidth: 0, hoverOffset: 6 }] },
            options: {
                cutout: '72%',
                layout: { padding: 6 },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (c) { return c.label + ': ' + money.format(c.parsed); } } }
                }
            }
        });

        const totalEl = document.querySelector('[data-donut-total="' + id + '"]');
        if (totalEl) {
            totalEl.textContent = moneyCompact.format(total);
            totalEl.title = money.format(total);
        }

        const list = document.querySelector('[data-donut-legend="' + id + '"]');
        if (!list) {
            return;
        }
        set.labels.forEach(function (label, i) {
            const item = document.createElement('li');
            const parts = ['swatch', 'label', 'value', 'share'].map(function (name) {
                const span = document.createElement('span');
                span.className = 'itflow-dash-legend-' + name;
                item.appendChild(span);
                return span;
            });
            parts[0].style.backgroundColor = slices[i];
            parts[1].textContent = label;
            parts[1].title = label;
            parts[2].textContent = money.format(set.values[i]);
            parts[3].textContent = share.format(total ? set.values[i] / total : 0);

            // Pointing at a row lifts its slice out of the ring
            if (chart) {
                item.addEventListener('mouseenter', function () {
                    chart.setActiveElements([{ datasetIndex: 0, index: i }]);
                    chart.update();
                });
                item.addEventListener('mouseleave', function () {
                    chart.setActiveElements([]);
                    chart.update();
                });
            }
            list.appendChild(item);
        });
    }
    if (data.breakdowns) {
        donut('dashIncomeByCategory', data.breakdowns.income_by_category);
        donut('dashExpensesByCategory', data.breakdowns.expenses_by_category);
        donut('dashExpensesByVendor', data.breakdowns.expenses_by_vendor);
    }

    /* Miles per month */
    if (data.trips) {
        draw('dashTrips', {
            type: 'line',
            data: {
                labels: data.trips.labels,
                datasets: [{
                    label: 'Miles', data: data.trips.miles, borderColor: colors.accent, backgroundColor: alpha(colors.accent, 0.12),
                    fill: true, cubicInterpolationMode: 'monotone', pointRadius: 2, borderWidth: 2
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: axes(function (v) { return moneyShort.format(v); })
            }
        });
    }
})();
