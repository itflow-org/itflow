<?php

/*
 * Mark a service reviewed - after checking it still matches what is out
 * there: what needs attention now, what it is linked to, who to call. The
 * next review falls due on its schedule (or a date picked here), and the
 * note goes in its history, so the review can be shown at the next QBR.
 */

require_once '../../../includes/modal_header.php';

$service = serviceForModal($_GET['id'] ?? 0, 2, true);
$service_id = intval($service['service_id']);
$interval = intval($service['service_review_interval']);
$counts = serviceCounts([$service_id])[$service_id];
$issues = serviceIssues([$service_id])[$service_id];
$review = serviceReview($service);
$next = serviceNextReview($interval ?: 12);

$checks = [
    ['fa-desktop', $counts['asset'] ? "The {$counts['asset']} asset" . ($counts['asset'] === 1 ? ' it runs on is' : 's it runs on are') . ' still the ones in service' : 'Nothing is linked as what it runs on - link the assets'],
    ['fa-building', $counts['vendor'] ? 'The vendor' . ($counts['vendor'] === 1 ? '' : 's') . ' and their support numbers are current' : 'No vendor is linked - who gets called when it breaks?'],
    ['fa-user', $counts['contact'] ? 'The people at the client who own it are still there' : 'Nobody at the client is linked as its owner'],
    ['fa-history', trim((string) $service['service_backup']) !== '' ? 'The backup is running, and the recovery steps still work' : 'No backup or recovery is written down'],
];

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-clipboard-check me-2"></i>Review <strong><?= escapeHtml($service['service_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="service_id" value="<?= $service_id ?>">

    <div class="modal-body">

        <div class="small text-body-secondary mb-2"><?= escapeHtml($review['text']) ?><?= $service['service_reviewed_at'] ? ' - last reviewed ' . escapeHtml(date('M j, Y', strtotime($service['service_reviewed_at']))) : '' ?>.</div>

        <?php if ($issues) { ?>
            <div class="alert alert-<?= in_array('danger', array_column($issues, 'tone'), true) ? 'danger' : 'warning' ?> py-2 small mb-3">
                <div class="fw-semibold mb-1">Needs attention now</div>
                <?= serviceIssuesHtml($issues, 6) ?>
            </div>
        <?php } ?>

        <div class="small text-body-secondary fw-semibold text-uppercase mb-1">Check</div>
        <ul class="list-unstyled small mb-3 itflow-service-checks">
            <?php foreach ($checks as [$icon, $text]) { ?>
                <li class="py-1"><i class="fas fa-fw <?= $icon ?> text-secondary me-2"></i><?= escapeHtml($text) ?></li>
            <?php } ?>
        </ul>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Review it</label>
                <select class="form-select select2" name="review_interval">
                    <?php foreach (SERVICE_REVIEW_INTERVALS as $months => $label) { ?>
                        <option value="<?= $months ?>" <?= $interval === $months ? 'selected' : '' ?>><?= $label ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Next review</label>
                <input type="date" class="form-control" name="review_due" value="<?= $interval ? escapeHtml($next) : '' ?>">
            </div>
        </div>

        <label class="form-label">Note <span class="text-body-secondary small fw-normal">- goes in its history</span></label>
        <textarea class="form-control" name="note" rows="2" maxlength="400" placeholder="Walked through it with the client, swapped the backup target, all good..."></textarea>

    </div>

    <div class="modal-footer">
        <a class="me-auto small" href="<?= escapeHtml(serviceUrl($service_id, $client_id)) ?>">Open the service</a>
        <button type="submit" name="review_service" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Mark reviewed</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
