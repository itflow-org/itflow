<?php

/*
 * Receivables Aging - what clients owe right now on unpaid invoices (sent,
 * viewed or part-paid), split by how long past due: not due yet, 1-30,
 * 31-60, 61-90 and over 90 days. Per client with their oldest invoice and
 * last payment, then every overdue invoice, latest first. The same invoices
 * and total as the dashboard's Receivables, and the Unpaid invoice list.
 */

require_once "includes/inc_report.php";
reportEnforce('outstanding_balances');

$client_id = reportClientId();
$open = dashboardOpenInvoicesSql(reportClientSql('invoice_client_id', $client_id));
$buckets = ['current' => 'Not due yet', '1-30' => '1–30 days', '31-60' => '31–60 days', '61-90' => '61–90 days', '90+' => 'Over 90 days'];

$invoices = [];
$sql = mysqli_query($mysqli, "
    SELECT open_invoices.invoice_id, open_invoices.invoice_client_id, open_invoices.invoice_due, balance,
        invoice_prefix, invoice_number, invoice_date, invoice_amount, invoice_scope, client_name, client_abbreviation,
        DATEDIFF(CURDATE(), open_invoices.invoice_due) AS days_late
    FROM ($open) AS open_invoices
    JOIN invoices ON invoices.invoice_id = open_invoices.invoice_id
    JOIN clients ON client_id = open_invoices.invoice_client_id
    WHERE balance > 0.004
    ORDER BY open_invoices.invoice_due ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $late = intval($row['days_late']);
    $row['bucket'] = $late <= 0 ? 'current' : ($late <= 30 ? '1-30' : ($late <= 60 ? '31-60' : ($late <= 90 ? '61-90' : '90+')));
    $row['balance'] = floatval($row['balance']);
    $row['paid'] = floatval($row['invoice_amount']) - $row['balance'];
    $invoices[] = $row;
}

// Per client, a column per bucket
$clients = [];
$totals = array_fill_keys(array_keys($buckets), 0.0);
foreach ($invoices as $invoice) {
    $id = intval($invoice['invoice_client_id']);
    if (!isset($clients[$id])) {
        $clients[$id] = ['client_id' => $id, 'client' => $invoice['client_name'], 'abbr' => $invoice['client_abbreviation'],
            'count' => 0, 'total' => 0.0, 'oldest_late' => null, 'last_payment' => null] + array_fill_keys(array_keys($buckets), 0.0);
    }
    $clients[$id][$invoice['bucket']] += $invoice['balance'];
    $clients[$id]['total'] += $invoice['balance'];
    $clients[$id]['count']++;
    $clients[$id]['oldest_late'] = max($clients[$id]['oldest_late'] ?? -100000, intval($invoice['days_late']));
    $totals[$invoice['bucket']] += $invoice['balance'];
}
if ($clients) {
    $sql = mysqli_query($mysqli, "SELECT invoice_client_id AS client_id, MAX(payment_date) AS last_paid FROM payments JOIN invoices ON payment_invoice_id = invoice_id
        WHERE invoice_client_id IN (" . implode(',', array_keys($clients)) . ") GROUP BY invoice_client_id");
    while ($row = mysqli_fetch_assoc($sql)) {
        $clients[intval($row['client_id'])]['last_payment'] = $row['last_paid'];
    }
}
uasort($clients, function ($a, $b) {
    return $b['total'] <=> $a['total'];
});
$total = array_sum($totals);
$overdue_total = $total - $totals['current'];

$bucket_cell = function ($key) {
    return function ($row) use ($key) {
        if ($row[$key] < 0.005) {
            return '<span class="itflow-report-zero">–</span>';
        }
        $class = in_array($key, ['61-90', '90+'], true) ? 'text-danger-emphasis fw-semibold' : '';
        return '<span class="' . $class . '">' . reportMoney($row[$key]) . '</span>';
    };
};

$client_columns = ['client' => ['label' => 'Client', 'html' => function ($row) {
    return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'invoices.php') ;
}]];
foreach ($buckets as $key => $label) {
    $client_columns[$key] = ['label' => $label, 'type' => 'money', 'html' => $bucket_cell($key)];
}
$client_columns += [
    'total' => ['label' => 'Owed', 'type' => 'money', 'html' => function ($row) {
        return '<a class="itflow-report-amount fw-semibold" href="/agent/invoices.php?client_id=' . $row['client_id'] . '&status=Unpaid">' . reportMoney($row['total']) . '</a>';
    }],
    'count' => ['label' => 'Invoices', 'type' => 'count'],
    'oldest_late' => ['label' => 'Oldest', 'type' => 'count', 'csv_label' => 'Oldest days late', 'title' => 'Days the oldest unpaid invoice is past due', 'html' => function ($row) {
        return $row['oldest_late'] > 0 ? '<span class="' . ($row['oldest_late'] > 60 ? 'text-danger-emphasis fw-semibold' : '') . '">' . $row['oldest_late'] . ' day' . ($row['oldest_late'] === 1 ? '' : 's') . ' late</span>' : '<span class="text-body-secondary">Not due</span>';
    }],
    'last_payment' => ['label' => 'Last paid', 'type' => 'date', 'class' => 'text-nowrap', 'html' => function ($row) {
        return $row['last_payment'] ? '<span title="' . escapeHtml($row['last_payment']) . '">' . escapeHtml(timeAgo($row['last_payment'])) . '</span>' : '<span class="text-body-secondary">Never</span>';
    }],
];

$invoice_columns = [
    'invoice' => ['label' => 'Invoice', 'csv' => function ($row) { return $row['invoice_prefix'] . $row['invoice_number']; }, 'html' => function ($row) {
        return '<a class="font-monospace" href="/agent/invoice.php?client_id=' . intval($row['invoice_client_id']) . '&invoice_id=' . intval($row['invoice_id']) . '">' . escapeHtml($row['invoice_prefix'] . $row['invoice_number']) . '</a>'
            . ($row['invoice_scope'] !== '' && $row['invoice_scope'] !== null ? '<div class="small text-body-secondary text-truncate" style="max-width: 18rem">' . escapeHtml($row['invoice_scope']) . '</div>' : '');
    }],
    'client_name' => ['label' => 'Client', 'html' => function ($row) {
        return reportClientCell($row['invoice_client_id'], $row['client_name'], $row['client_abbreviation'], 'invoices.php');
    }],
    'invoice_date' => ['label' => 'Date', 'type' => 'date', 'class' => 'text-nowrap'],
    'invoice_due' => ['label' => 'Due', 'type' => 'date', 'class' => 'text-nowrap'],
    'days_late' => ['label' => 'Late', 'type' => 'count', 'csv_label' => 'Days late', 'html' => function ($row) {
        $late = intval($row['days_late']);
        if ($late <= 0) {
            return '<span class="text-body-secondary">' . ($late === 0 ? 'Due today' : 'In ' . (-$late) . ' day' . ($late === -1 ? '' : 's')) . '</span>';
        }
        $tone = $late > 60 ? 'danger' : ($late > 30 ? 'warning' : 'secondary');
        return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis border border-' . $tone . '-subtle fw-normal">' . $late . ' day' . ($late === 1 ? '' : 's') . '</span>';
    }],
    'invoice_amount' => ['label' => 'Amount', 'type' => 'money', 'class' => 'text-body-secondary'],
    'paid' => ['label' => 'Paid', 'type' => 'money', 'class' => 'text-body-secondary', 'html' => function ($row) {
        return $row['paid'] > 0.004 ? reportMoney($row['paid']) : '<span class="itflow-report-zero">–</span>';
    }],
    'balance' => ['label' => 'Owed', 'type' => 'money', 'class' => 'fw-semibold'],
];

if (reportExporting('invoices')) {
    reportCsv('Unpaid Invoices', $invoice_columns, $invoices, ['invoice' => 'Total', 'balance' => $total]);
}
if (reportExporting()) {
    reportCsv('Receivables Aging', $client_columns, array_values($clients), ['client' => 'Total', 'total' => $total, 'count' => count($invoices)] + $totals);
}

require_once "includes/inc_report_layout.php";

$late_90 = $totals['90+'];
$late_60 = $totals['61-90'] + $totals['90+'];
$overdue = array_values(array_filter($invoices, function ($i) { return intval($i['days_late']) > 0; }));
usort($overdue, function ($a, $b) { return intval($b['days_late']) <=> intval($a['days_late']); });

?>

<div class="itflow-report">

    <?php reportHeader('outstanding_balances', [
        'client' => $client_id,
        'subtitle' => 'As of ' . date('M j, Y'),
        'export' => ['clients' => 'By client', 'invoices' => 'Every unpaid invoice'],
    ]); ?>

    <?= reportStats([
        ['label' => 'Owed to you', 'value' => reportMoney($total), 'url' => '/agent/invoices.php?status=Unpaid',
            'sub' => reportCount(count($invoices)) . ' <span class="text-body-tertiary">unpaid invoice' . (count($invoices) === 1 ? '' : 's') . '</span>'],
        ['label' => 'Overdue', 'value' => reportMoney($overdue_total), 'tone' => $overdue_total > 0 ? 'danger' : '', 'url' => '/agent/invoices.php?status=Overdue',
            'sub' => $total > 0 ? escapeHtml(reportPercent(reportShare($overdue_total, $total))) . ' <span class="text-body-tertiary">of what is owed</span>' : ''],
        ['label' => 'Over 60 days late', 'value' => reportMoney($late_60), 'tone' => $late_60 > 0 ? 'danger' : '',
            'sub' => $late_90 > 0 ? escapeHtml(reportMoney($late_90)) . ' <span class="text-body-tertiary">over 90</span>' : '<span class="text-body-tertiary">Nothing over 90 days</span>'],
        ['label' => 'Clients owing', 'value' => reportCount(count($clients)),
            'sub' => count(array_filter($clients, function ($c) { return $c['oldest_late'] > 0; })) . ' <span class="text-body-tertiary">with something overdue</span>'],
    ]) ?>

    <?php if ($total > 0) { ?>
        <?= reportSectionOpen('By how late', '') ?>
            <div class="card-body">
                <div class="itflow-dash-aging mb-3" role="img" aria-label="What is owed, by days past due">
                    <?php foreach ($buckets as $key => $label) {
                        if ($totals[$key] > 0) { ?>
                            <span class="itflow-dash-age-<?= str_replace('+', 'plus', $key) ?>" style="width: <?= round($totals[$key] / $total * 100, 2) ?>%" title="<?= escapeHtml($label . ': ' . reportMoney($totals[$key])) ?>"></span>
                        <?php }
                    } ?>
                </div>
                <div class="row row-cols-2 row-cols-md-5 g-2 small">
                    <?php foreach ($buckets as $key => $label) { ?>
                        <div>
                            <span class="d-block text-body-secondary text-nowrap"><span class="itflow-dash-legend-swatch d-inline-block me-1 itflow-dash-age-<?= str_replace('+', 'plus', $key) ?>"></span><?= escapeHtml($label) ?></span>
                            <span class="d-block fw-semibold fs-6 <?= $totals[$key] > 0 ? '' : 'text-body-tertiary' ?>"><?= reportMoney($totals[$key]) ?></span>
                            <span class="d-block text-body-tertiary"><?= reportPercent(reportShare($totals[$key], $total)) ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen('By client', 'Most owed first') ?>
        <?= reportTable($client_columns, array_values($clients), [
            'empty' => reportEmpty('Nothing is owed on unpaid invoices.'),
            'totals' => ['client' => 'Total', 'total' => $total, 'count' => count($invoices)] + $totals,
        ]) ?>
    </div>

    <?= reportSectionOpen('Overdue invoices', $overdue ? 'Longest overdue first' : '', 'itflow-report-long') ?>
        <?= reportTable($invoice_columns, $overdue, [
            'empty' => reportEmpty('No invoice is past its due date.'),
            'limit' => 300, 'export_table' => 'invoices',
            'totals' => ['invoice' => 'Total', 'balance' => array_sum(array_column($overdue, 'balance'))],
        ]) ?>
        <div class="card-footer small text-body-secondary">
            Unpaid invoices are the ones sent, viewed or part-paid; drafts and cancelled invoices are not owed. Days late count from the due date.
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
