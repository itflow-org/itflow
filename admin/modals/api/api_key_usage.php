<?php

/*
 * What an API key is used for - its calls today, this week and the last 30
 * days, each day's as a bar, the endpoints it called and how often, the IP
 * addresses the calls came from, and the calls turned away after it expired.
 * Counted as calls come in (api_key_usage, functions/api_keys.php), kept as
 * long as the audit log.
 */

require_once '../../includes/modal_header.php';

$row = apiKeyRow($_GET['id'] ?? 0);

ob_start();

if (!$row) { ?>
    <div class="modal-body text-center text-secondary py-5">That key is gone - it may have been deleted.</div>
<?php } else {
    $api_key_id = intval($row['api_key_id']);
    $role = roleRow($row['user_role_id'] ?? 0);
    $daily = apiKeyUsageDaily($api_key_id, 30);
    $endpoints = apiKeyUsageEndpoints($api_key_id, 30);
    $ips = apiKeyUsageIps($api_key_id, 30);
    $turned_away = apiKeyTurnedAway($api_key_id, 30);
    $today = end($daily);
    $week = array_sum(array_slice($daily, -7));
    $month = array_sum($daily);
    $peak = max(1, max($daily)); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-chart-bar me-2"></i>API Key: <strong><?= escapeHtml($row['api_key_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <?= apiKeyStatePill($row, $role) ?>
        <span class="small text-body-secondary"><span class="font-monospace">••••<?= escapeHtml(substr((string) $row['api_key_secret'], -4)) ?></span> · last used <?= $row['api_key_last_used_at'] ? escapeHtml(timeAgo($row['api_key_last_used_at'])) : 'never' ?></span>
    </div>

    <div class="row g-2 mb-3 text-center" data-api-usage-totals>
        <?php foreach (['Today' => $today, 'Last 7 days' => $week, 'Last 30 days' => $month] as $label => $calls) { ?>
            <div class="col-4">
                <div class="border rounded py-2">
                    <div class="fs-4 fw-semibold"><?= number_format($calls) ?></div>
                    <div class="small text-body-secondary"><?= $label ?></div>
                </div>
            </div>
        <?php } ?>
    </div>

    <!-- Each day's calls, oldest on the left -->
    <div class="small fw-semibold text-uppercase text-body-secondary mb-1">Calls a day</div>
    <div class="d-flex align-items-end gap-1 border-bottom mb-1" style="height: 6rem;" data-api-usage-days>
        <?php foreach ($daily as $date => $calls) { ?>
            <div class="flex-fill rounded-top <?= $calls ? 'bg-primary' : 'bg-body-secondary' ?>" style="height: <?= $calls ? max(4, round($calls / $peak * 100)) : 2 ?>%;" title="<?= escapeHtml(date('D, M j', strtotime($date))) ?>: <?= number_format($calls) ?> call<?= $calls === 1 ? '' : 's' ?>"></div>
        <?php } ?>
    </div>
    <div class="d-flex justify-content-between small text-body-secondary mb-3">
        <span><?= escapeHtml(date('M j', strtotime(array_key_first($daily)))) ?></span>
        <span>Today</span>
    </div>

    <div class="row g-3">
        <div class="col-md-7">
            <div class="small fw-semibold text-uppercase text-body-secondary mb-1">Endpoints</div>
            <?php if ($endpoints) { ?>
                <table class="table table-sm align-middle mb-0" data-api-usage-endpoints>
                    <tbody>
                    <?php foreach ($endpoints as $endpoint) { ?>
                        <tr>
                            <td class="font-monospace small text-break"><?= escapeHtml($endpoint['endpoint'] !== '' ? $endpoint['endpoint'] : '-') ?></td>
                            <td class="text-end small text-nowrap"><?= number_format(intval($endpoint['calls'])) ?></td>
                            <td class="text-end small text-body-secondary text-nowrap" title="<?= escapeHtml($endpoint['last_at']) ?>"><?= escapeHtml(timeAgo($endpoint['last_at'])) ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <div class="small text-body-secondary">Not used in the last 30 days.</div>
            <?php } ?>
        </div>
        <div class="col-md-5">
            <div class="small fw-semibold text-uppercase text-body-secondary mb-1">From</div>
            <?php if ($ips) { ?>
                <ul class="list-unstyled small mb-0" data-api-usage-ips>
                    <?php foreach ($ips as $ip) { ?>
                        <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
                            <a class="font-monospace" href="/admin/audit_logs.php?q=<?= urlencode($ip['ip']) ?>" title="Everything from it in the audit log"><?= escapeHtml($ip['ip']) ?></a>
                            <span class="text-body-secondary text-nowrap" title="<?= escapeHtml($ip['last_at']) ?>"><?= escapeHtml(timeAgo($ip['last_at'])) ?></span>
                        </li>
                    <?php } ?>
                </ul>
            <?php } else { ?>
                <div class="small text-body-secondary">Nowhere yet.</div>
            <?php } ?>

            <?php if ($turned_away['count']) { ?>
                <div class="alert alert-warning small py-2 mt-3 mb-0" data-api-usage-turned-away>
                    <i class="fas fa-fw fa-ban me-1"></i><?= number_format($turned_away['count']) ?> call<?= $turned_away['count'] === 1 ? ' was' : 's were' ?> turned away after it expired, the last <?= escapeHtml(timeAgo($turned_away['last_at'])) ?>.
                    <a href="/admin/audit_logs.php?type=API&amp;entity=<?= $api_key_id ?>">See them</a>
                </div>
            <?php } ?>
        </div>
    </div>

</div>
<div class="modal-footer">
    <a class="btn btn-outline-secondary me-auto" href="/admin/audit_logs.php?type=<?= urlencode('API Key') ?>&amp;entity=<?= $api_key_id ?>"><i class="fas fa-fw fa-history me-2"></i>Its history</a>
    <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/api/api_key_edit.php?id=<?= $api_key_id ?>"><i class="fas fa-fw fa-edit me-2"></i>Edit</button>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Close</button>
</div>

<?php }

require_once '../../../includes/modal_footer.php';
