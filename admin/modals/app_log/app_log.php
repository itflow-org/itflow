<?php

/*
 * An app log entry's quick look - all of it: when, the level and category,
 * the whole of what it says (to copy, for a bug report), then the other
 * entries in the five minutes either side and what was done in the audit log
 * then - so a payment error sits beside the payment, a mail error beside the
 * ticket reply. Opened from Admin > App Logs.
 */

require_once '../../includes/modal_header.php';

$app_log_id = intval($_GET['id'] ?? 0);
$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM app_logs WHERE app_log_id = $app_log_id"));
if (!$row) {
    echo json_encode(['error' => 'That app log entry no longer exists - it may have been cleared.']);
    exit;
}

$level = appLogLevel($row['app_log_type']);
$style = appLogLevels()[$level];
$category = (string) $row['app_log_category'];
$created = strtotime($row['app_log_created_at']);
$at = escapeSql($row['app_log_created_at']);
$window = "BETWEEN '$at' - INTERVAL 5 MINUTE AND '$at' + INTERVAL 5 MINUTE";

// The other app log entries in the five minutes either side
$around_where = "app_log_created_at $window AND app_log_id <> $app_log_id";
$around_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM app_logs WHERE $around_where"))[0]);
$around = [];
$sql = mysqli_query($mysqli, "SELECT app_log_id, app_log_category, app_log_type, app_log_details, app_log_created_at FROM app_logs WHERE $around_where ORDER BY app_log_created_at ASC, app_log_id ASC LIMIT 8");
while ($around_row = mysqli_fetch_assoc($sql)) {
    $around[] = $around_row;
}

// What was done in the audit log then
$audit_where = "log_created_at $window";
$audit_count = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM logs WHERE $audit_where"))[0]);
$audit = [];
$sql = mysqli_query($mysqli, "SELECT log_id, log_type, log_action, log_description, log_created_at FROM logs WHERE $audit_where ORDER BY log_created_at ASC, log_id ASC LIMIT 8");
while ($audit_row = mysqli_fetch_assoc($sql)) {
    $audit[] = $audit_row;
}

$category_count = $category !== '' ? intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM app_logs WHERE app_log_category = '" . escapeSql($category) . "'"))[0]) : 0;
$day = date('Y-m-d', $created);
$day_query = 'canned_date=custom&dtf=' . $day . '&dtt=' . $day;

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
        <span class="itflow-asset-icon itflow-asset-icon-lg bg-<?= $style['tone'] ?>-subtle text-<?= $style['tone'] ?>-emphasis" aria-hidden="true"><i class="<?= appLogCategoryIcon($category) ?>"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h5 class="modal-title fw-semibold text-break"><?= escapeHtml($category !== '' ? $category : 'No category') ?> &middot; <?= $style['one'] ?></h5>
            <div class="small text-body-secondary mt-1"><?= escapeHtml(date('D, M j, Y \a\t g:i:s A', $created)) ?> &middot; <?= escapeHtml(timeAgo($row['app_log_created_at'])) ?></div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <div class="p-3 rounded border bg-body-tertiary mb-3 position-relative">
        <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2 clipboardjs" title="Copy" aria-label="Copy" data-clipboard-text="<?= escapeHtml($row['app_log_details']) ?>"><i class="far fa-fw fa-copy"></i></button>
        <div class="text-break font-monospace small pe-5" style="white-space: pre-wrap;" data-app-log-full-details><?= escapeHtml((string) $row['app_log_details']) ?></div>
    </div>

    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Level">
                <i class="fas fa-fw fa-signal"></i>
                <span><?= appLogLevelPill($level) ?> <span class="small text-body-secondary ms-1"><?= escapeHtml($style['title']) ?></span></span>
            </div>
            <div class="itflow-glance-item" title="Category">
                <i class="fas fa-fw fa-folder"></i>
                <?php if ($category !== '') { ?>
                    <span><?= escapeHtml($category) ?> <a class="small ms-1" href="/admin/app_logs.php?category=<?= urlencode($category) ?>">All <?= number_format($category_count) ?> from it</a></span>
                <?php } else { ?>
                    <span class="text-body-secondary">None</span>
                <?php } ?>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="Entry">
                <i class="fas fa-fw fa-hashtag"></i>
                <span class="text-body-secondary">Entry <?= $app_log_id ?></span>
            </div>
            <div class="itflow-glance-item" title="When">
                <i class="fas fa-fw fa-clock"></i>
                <span class="font-monospace small"><?= escapeHtml($row['app_log_created_at']) ?></span>
            </div>
        </div>
    </div>

    <div class="mt-4" data-app-log-around>
        <div class="d-flex align-items-center mb-2">
            <div class="small fw-semibold text-uppercase text-body-secondary">The app logs around then</div>
            <?php if ($around_count > 0) { ?>
                <a class="small ms-auto" href="/admin/app_logs.php?<?= $day_query ?>">All of that day</a>
            <?php } ?>
        </div>
        <?php if ($around) { ?>
            <div class="list-group">
                <?php foreach ($around as $entry) { ?>
                    <a href="#" class="list-group-item list-group-item-action d-flex align-items-start gap-2 small ajax-modal" data-modal-size="lg" data-modal-url="/admin/modals/app_log/app_log.php?id=<?= intval($entry['app_log_id']) ?>">
                        <span class="text-body-secondary text-nowrap flex-shrink-0" style="min-width: 4.5rem;" title="<?= escapeHtml($entry['app_log_created_at']) ?>"><?= escapeHtml(date('g:i:s A', strtotime($entry['app_log_created_at']))) ?></span>
                        <span class="flex-shrink-0"><?= appLogLevelPill($entry['app_log_type']) ?></span>
                        <span class="text-break"><span class="fw-semibold"><?= escapeHtml((string) $entry['app_log_category']) ?></span> <?= escapeHtml(mb_strimwidth((string) $entry['app_log_details'], 0, 160, '…')) ?></span>
                    </a>
                <?php } ?>
            </div>
            <?php if ($around_count > count($around)) { ?>
                <div class="small text-body-secondary mt-1">and <?= $around_count - count($around) ?> more</div>
            <?php } ?>
        <?php } else { ?>
            <div class="small text-body-secondary">Nothing else in the five minutes either side.</div>
        <?php } ?>
    </div>

    <div class="mt-4" data-app-log-audit>
        <div class="d-flex align-items-center mb-2">
            <div class="small fw-semibold text-uppercase text-body-secondary">The audit log then</div>
            <?php if ($audit_count > 0) { ?>
                <a class="small ms-auto" href="/admin/audit_logs.php?<?= $day_query ?>">All of that day</a>
            <?php } ?>
        </div>
        <?php if ($audit) { ?>
            <div class="list-group">
                <?php foreach ($audit as $entry) { ?>
                    <a href="#" class="list-group-item list-group-item-action d-flex align-items-start gap-2 small ajax-modal" data-modal-size="lg" data-modal-url="/admin/modals/audit_log/audit_log.php?id=<?= intval($entry['log_id']) ?>">
                        <span class="text-body-secondary text-nowrap flex-shrink-0" style="min-width: 4.5rem;" title="<?= escapeHtml($entry['log_created_at']) ?>"><?= escapeHtml(date('g:i:s A', strtotime($entry['log_created_at']))) ?></span>
                        <span class="flex-shrink-0"><?= auditLogActionPill($entry['log_action']) ?></span>
                        <span class="text-break"><?= escapeHtml($entry['log_description']) ?></span>
                    </a>
                <?php } ?>
            </div>
            <?php if ($audit_count > count($audit)) { ?>
                <div class="small text-body-secondary mt-1">and <?= $audit_count - count($audit) ?> more</div>
            <?php } ?>
        <?php } else { ?>
            <div class="small text-body-secondary">Nothing was done in the five minutes either side.</div>
        <?php } ?>
    </div>

</div>

<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Close</button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
