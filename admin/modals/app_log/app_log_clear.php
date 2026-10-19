<?php

/*
 * Clear the app logs - the entries the list is showing, with its search,
 * level, category and dates, or every one. Each clear is in the audit log,
 * saying what went (clear_app_logs, admin/post/app_log.php). The audit log
 * itself can't be cleared - it is only ever trimmed by its retention.
 */

require_once '../../includes/modal_header.php';

$views = appLogViews();
$src = [
    'q' => is_string($_GET['q'] ?? null) ? $_GET['q'] : '',
    'view' => is_string($_GET['view'] ?? null) && isset($views[$_GET['view']]) ? $_GET['view'] : '',
    'category' => is_string($_GET['category'] ?? null) ? $_GET['category'] : '',
    'dtf' => is_string($_GET['dtf'] ?? null) ? $_GET['dtf'] : '',
    'dtt' => is_string($_GET['dtt'] ?? null) ? $_GET['dtt'] : '',
];
$summary = [];
$where = appLogFiltersSql($src, $summary);
$matching = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM app_logs WHERE $where"))[0]);
$total = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(*) FROM app_logs"))[0]);
$filtered = !empty($summary);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-trash-alt me-2"></i>Clear App Logs</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($src as $name => $value) { if ($value !== '') { ?>
        <input type="hidden" name="<?= $name ?>" value="<?= escapeHtml($value) ?>">
    <?php } } ?>

    <div class="modal-body">

        <?php if (!$total) { ?>
            <div class="text-center text-secondary py-4">There is nothing in the app logs to clear.</div>
        <?php } else { ?>

            <p class="mb-3">The entries are deleted for good. Clearing is in the audit log, with what was cleared.</p>

            <?php if ($filtered) { ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="scope" value="shown" id="appLogClearShown" <?= $matching ? 'checked' : 'disabled' ?>>
                    <label class="form-check-label" for="appLogClearShown">
                        <span class="fw-semibold" data-app-log-clear-matching="<?= $matching ?>"><?= number_format($matching) ?> entr<?= $matching === 1 ? 'y' : 'ies' ?></span> - the ones shown:
                        <span class="text-body-secondary" data-app-log-clear-filters><?= escapeHtml(appLogFiltersText($summary)) ?></span>
                    </label>
                </div>
            <?php } ?>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="scope" value="all" id="appLogClearAll" <?= $filtered && $matching ? '' : 'checked' ?>>
                <label class="form-check-label" for="appLogClearAll">
                    <span class="fw-semibold" data-app-log-clear-total="<?= $total ?>">Every entry - all <?= number_format($total) ?></span>
                </label>
            </div>

        <?php } ?>

    </div>
    <div class="modal-footer">
        <?php if ($total) { ?>
            <button type="submit" name="clear_app_logs" class="btn btn-danger text-bold"><i class="fas fa-trash-alt me-2"></i>Clear</button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
