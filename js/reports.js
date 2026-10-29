/*
 * ITFlow - reports (agent/reports/).
 *
 * The filters at the top of a report submit as soon as they change, except
 * "Custom dates", which opens From and To. Charts are declared in the page as
 * <canvas data-report-chart="{...}"> (reportChart() in functions/reports.php
 * documents the settings) and drawn here in the page's own colours, so they
 * follow the theme accent and dark mode. Tables with data-report-sort sort by
 * any column whose heading is clicked. On the Reports page, typing in the
 * search box narrows the list of reports.
 *
 * Loaded once per report page, after includes/footer.php.
 */
(function () {
    'use strict';

    /* ---------- filters ---------- */

    document.querySelectorAll('[data-report-filters]').forEach(function (form) {
        form.addEventListener('change', function (e) {
            const field = e.target;
            if (field.matches('[data-report-period]')) {
                const custom = form.querySelector('[data-report-custom]');
                const isCustom = field.value === 'custom';
                if (custom) {
                    custom.hidden = !isCustom;
                    custom.querySelectorAll('input').forEach(function (input) {
                        input.disabled = !isCustom;
                    });
                }
                if (isCustom) {
                    const from = custom && custom.querySelector('input');
                    if (from) {
                        from.focus();
                    }
                    return;
                }
                form.requestSubmit ? form.requestSubmit() : form.submit();
                return;
            }
            if (field.matches('[data-report-autosubmit]')) {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
    });

    /* ---------- colours and numbers ---------- */

    const style = getComputedStyle(document.body);
    function cssVar(name, fallback) {
        return (style.getPropertyValue(name) || '').trim() || fallback;
    }
    const tones = {
        accent: cssVar('--itflow-accent', '#007bff'),
        success: cssVar('--bs-success', '#198754'),
        danger: cssVar('--bs-danger', '#dc3545'),
        warning: cssVar('--bs-warning', '#ffc107'),
        info: cssVar('--bs-info', '#0dcaf0'),
        muted: cssVar('--bs-secondary-color', '#6c757d'),
        faint: cssVar('--bs-tertiary-color', '#adb5bd'),
        purple: cssVar('--bs-purple', '#6f42c1'),
        teal: cssVar('--bs-teal', '#20c997'),
        orange: cssVar('--bs-orange', '#fd7e14'),
        indigo: cssVar('--bs-indigo', '#6610f2'),
        pink: cssVar('--bs-pink', '#d63384'),
        dark: cssVar('--bs-emphasis-color', '#212529')
    };
    function tone(name) {
        if (typeof name === 'string' && /^#[0-9a-f]{3,8}$/i.test(name)) {
            return name;
        }
        return tones[name] || tones.accent;
    }
    function alpha(color, a) {
        let m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(color);
        if (m) {
            return 'rgba(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ',' + a + ')';
        }
        m = /^rgba?\(([^)]+)\)$/i.exec(color);
        if (m) {
            const parts = m[1].split(',').slice(0, 3);
            return 'rgba(' + parts.join(',') + ',' + a + ')';
        }
        return color;
    }

    // The legend swatches beside a doughnut take the same colours as its slices
    document.querySelectorAll('[data-report-swatch]').forEach(function (swatch) {
        swatch.style.backgroundColor = tone(swatch.dataset.reportSwatch);
    });

    function formatter(format, currency, compact) {
        let money;
        try {
            money = new Intl.NumberFormat(undefined, compact
                ? { style: 'currency', currency: currency || 'USD', notation: 'compact', maximumFractionDigits: 1 }
                : { style: 'currency', currency: currency || 'USD', maximumFractionDigits: 0 });
        } catch (e) {
            money = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
        }
        const number = new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 });
        return function (value) {
            if (value === null || value === undefined || value === '') {
                return '';
            }
            switch (format) {
                case 'money':
                    return money.format(value);
                case 'hours':
                    return number.format(value) + ' h';
                case 'percent':
                    return number.format(value) + '%';
                case 'count':
                    return Number.isInteger(value) ? value.toLocaleString() : (compact ? '' : number.format(value));
                default:
                    return number.format(value);
            }
        };
    }

    /* ---------- charts ---------- */

    const charts = [];

    function drawChart(canvas) {
        if (typeof Chart === 'undefined') {
            return;
        }
        let config;
        try {
            config = JSON.parse(canvas.dataset.reportChart);
        } catch (e) {
            return;
        }
        const base = typeof itflowChartDefaults === 'function' ? itflowChartDefaults() : { grid: 'rgba(0,0,0,.1)' };
        const type = config.type || 'bar';
        const format = config.format || 'count';
        const tick = formatter(format, config.currency, true);
        const full = formatter(format, config.currency, false);
        const tick2 = formatter(config.format2 || format, config.currency, true);
        const full2 = formatter(config.format2 || format, config.currency, false);
        const horizontal = !!config.horizontal;
        const showLegend = config.legend !== undefined ? !!config.legend : (type !== 'doughnut' && (config.series || []).length > 1);

        let datasets;
        if (type === 'doughnut') {
            const series = (config.series || [])[0] || { data: [] };
            const colors = (config.colors || []).map(tone);
            datasets = [{
                label: series.label || '',
                data: series.data,
                backgroundColor: series.data.map(function (v, i) { return colors[i] || tone(['accent', 'info', 'success', 'warning', 'purple', 'danger'][i % 6]); }),
                borderColor: cssVar('--bs-body-bg', '#fff'),
                borderWidth: 2
            }];
        } else {
            datasets = (config.series || []).map(function (s, i) {
                const color = tone(s.tone || ['accent', 'success', 'info', 'warning', 'purple', 'danger'][i % 6]);
                const line = s.type === 'line' || type === 'line';
                const set = {
                    label: s.label,
                    data: s.data,
                    type: s.type || undefined,
                    yAxisID: s.axis === 'y2' ? 'y2' : (horizontal ? undefined : 'y'),
                    xAxisID: horizontal ? 'x' : undefined,
                    stack: s.stack,
                    hidden: !!s.hidden,
                    order: line ? 0 : 1
                };
                if (line) {
                    Object.assign(set, {
                        borderColor: color,
                        backgroundColor: s.fill ? alpha(color, 0.15) : color,
                        fill: !!s.fill,
                        borderWidth: 2,
                        // Monotone curves never swing past the points - a share stays within 0-100%
                        cubicInterpolationMode: 'monotone',
                        pointRadius: s.data.length > 40 ? 0 : 2,
                        pointHoverRadius: 4,
                        borderDash: s.dashed ? [5, 4] : undefined,
                        spanGaps: true
                    });
                } else if (s.ghost) {
                    Object.assign(set, {
                        backgroundColor: alpha(color, 0.22),
                        borderColor: alpha(color, 0.8),
                        borderWidth: 1.5,
                        borderRadius: 3,
                        maxBarThickness: 28
                    });
                } else {
                    Object.assign(set, {
                        // A colour per bar when the series has one (tones), e.g. red where below target
                        backgroundColor: Array.isArray(s.tones) ? s.tones.map(tone) : color,
                        borderRadius: 3,
                        maxBarThickness: horizontal ? 18 : 28
                    });
                }
                return set;
            });
        }

        const valueAxis = {
            beginAtZero: true,
            stacked: !!config.stacked,
            grid: { color: base.grid },
            border: { display: false },
            ticks: { maxTicksLimit: 5, callback: function (v) { return tick(v); } }
        };
        if (config.max !== undefined) {
            valueAxis.max = config.max;
        }
        const categoryAxis = {
            stacked: !!config.stacked,
            grid: { display: false },
            ticks: { maxRotation: 0, autoSkipPadding: 10 }
        };
        if (horizontal) {
            categoryAxis.ticks = { autoSkip: false, callback: function (v) { const l = this.getLabelForValue(v); return l && l.length > 28 ? l.slice(0, 27) + '…' : l; } };
        }

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: type === 'doughnut' ? 'nearest' : 'index', intersect: type === 'doughnut' },
            plugins: {
                legend: { display: showLegend, position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 14 } },
                tooltip: {
                    callbacks: {
                        label: function (c) {
                            const value = horizontal ? c.parsed.x : (type === 'doughnut' ? c.parsed : c.parsed.y);
                            const shown = c.dataset.yAxisID === 'y2' ? full2(value) : full(value);
                            if (type === 'doughnut') {
                                const total = c.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                return ' ' + c.label + ': ' + shown + (total ? ' (' + Math.round(value / total * 100) + '%)' : '');
                            }
                            return ' ' + (c.dataset.label ? c.dataset.label + ': ' : '') + shown;
                        }
                    }
                }
            }
        };

        if (type === 'doughnut') {
            options.cutout = '68%';
        } else if (horizontal) {
            options.indexAxis = 'y';
            options.scales = { x: valueAxis, y: categoryAxis };
        } else {
            options.scales = { x: categoryAxis, y: valueAxis };
            if (datasets.some(function (d) { return d.yAxisID === 'y2'; })) {
                options.scales.y2 = {
                    position: 'right',
                    beginAtZero: true,
                    grid: { display: false },
                    border: { display: false },
                    ticks: { maxTicksLimit: 5, callback: function (v) { return tick2(v); } }
                };
                if (config.max2 !== undefined) {
                    options.scales.y2.max = config.max2;
                }
            }
        }

        charts.push(new Chart(canvas, {
            type: type === 'line' ? 'line' : (type === 'doughnut' ? 'doughnut' : 'bar'),
            data: { labels: config.labels || [], datasets: datasets },
            options: options
        }));
    }

    document.querySelectorAll('canvas[data-report-chart]').forEach(drawChart);

    // A printed page is narrower than the screen - fit the charts to it, and back
    window.addEventListener('beforeprint', function () {
        charts.forEach(function (chart) { chart.resize(); });
    });
    window.addEventListener('afterprint', function () {
        charts.forEach(function (chart) { chart.resize(); });
    });

    /* ---------- sortable tables ---------- */

    document.querySelectorAll('table[data-report-sort]').forEach(function (table) {
        const heads = table.querySelectorAll('thead th');
        heads.forEach(function (th, index) {
            if (!th.dataset.sort) {
                return;
            }
            th.tabIndex = 0;
            th.classList.add('itflow-report-sortable');
            function sort() {
                const tbody = table.tBodies[0];
                const rows = Array.from(tbody.rows).filter(function (r) { return r.cells.length === heads.length; });
                if (rows.length < 2) {
                    return;
                }
                const ascending = th.getAttribute('aria-sort') !== 'ascending' && (th.getAttribute('aria-sort') === 'descending' || th.dataset.sort === 'text');
                heads.forEach(function (h) { h.removeAttribute('aria-sort'); });
                th.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
                const numeric = th.dataset.sort === 'num';
                function key(row) {
                    const cell = row.cells[index];
                    const raw = cell.dataset.value !== undefined ? cell.dataset.value : cell.textContent.trim().toLowerCase();
                    if (numeric) {
                        return raw === '' ? null : parseFloat(raw);
                    }
                    return raw;
                }
                rows.sort(function (a, b) {
                    const ka = key(a);
                    const kb = key(b);
                    // Blanks go last whichever way it is sorted
                    if (ka === null || ka === '') { return 1; }
                    if (kb === null || kb === '') { return -1; }
                    const cmp = numeric ? ka - kb : String(ka).localeCompare(String(kb), undefined, { numeric: true });
                    return ascending ? cmp : -cmp;
                });
                rows.forEach(function (row) { tbody.appendChild(row); });
            }
            th.addEventListener('click', sort);
            th.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    sort();
                }
            });
        });
    });

    /* ---------- the budget editor ---------- */

    const budget = document.querySelector('[data-budget-form]');
    if (budget) {
        let money;
        try {
            money = new Intl.NumberFormat(undefined, { style: 'currency', currency: budget.dataset.currency || 'USD' });
        } catch (e) {
            money = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        const amount = function (input) {
            const value = parseFloat(String(input.value).replace(/,/g, ''));
            return isNaN(value) ? 0 : value;
        };
        const totals = function () {
            const months = {};
            let all = 0;
            budget.querySelectorAll('[data-budget-row]').forEach(function (row) {
                let sum = 0;
                row.querySelectorAll('[data-budget-cell]').forEach(function (cell, i) {
                    const value = amount(cell);
                    sum += value;
                    months[i + 1] = (months[i + 1] || 0) + value;
                });
                all += sum;
                row.querySelector('[data-budget-row-total]').textContent = money.format(sum);
            });
            budget.querySelectorAll('[data-budget-month-total]').forEach(function (cell) {
                cell.textContent = money.format(months[cell.dataset.budgetMonthTotal] || 0);
            });
            const total = budget.querySelector('[data-budget-total]');
            if (total) {
                total.textContent = money.format(all);
            }
        };
        budget.addEventListener('input', function (e) {
            if (e.target.matches('[data-budget-cell]')) {
                totals();
            }
        });
        budget.addEventListener('click', function (e) {
            const fill = e.target.closest('[data-budget-fill]');
            if (!fill) {
                return;
            }
            const row = fill.closest('[data-budget-row]');
            const source = row.querySelector('[data-budget-fill-value]');
            const value = amount(source);
            row.querySelectorAll('[data-budget-cell]').forEach(function (cell) {
                cell.value = value > 0 ? value.toFixed(2) : '';
            });
            source.value = '';
            totals();
        });
        // Enter in "every month" fills the row instead of saving the form
        budget.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.matches('[data-budget-fill-value]')) {
                e.preventDefault();
                e.target.closest('[data-budget-row]').querySelector('[data-budget-fill]').click();
            }
        });
    }

    /* ---------- finding a report ---------- */

    const find = document.querySelector('[data-report-find]');
    if (find) {
        find.addEventListener('input', function () {
            const words = find.value.toLowerCase().split(/\s+/).filter(Boolean);
            let shown = 0;
            document.querySelectorAll('[data-report-group]').forEach(function (group) {
                let inGroup = 0;
                group.querySelectorAll('[data-report-card]').forEach(function (card) {
                    const text = card.dataset.reportText || '';
                    const match = words.every(function (w) { return text.indexOf(w) !== -1; });
                    card.hidden = !match;
                    inGroup += match ? 1 : 0;
                });
                group.hidden = inGroup === 0;
                shown += inGroup;
            });
            const none = document.querySelector('[data-report-none]');
            if (none) {
                none.hidden = shown > 0;
            }
        });
    }
})();
