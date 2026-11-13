<?php

/*
 * Add a year's US federal holidays as closure days - the eleven of them, each
 * on the day it is observed (usFederalHolidays()). A day already listed is
 * left as it is, so a year partly filled in by hand is safe to run over. The
 * window says how many of each year's are in, and offers the first year with
 * some still to add.
 */

require_once '../../includes/modal_header.php';

$listed = getBusinessHolidays();
$this_year = intval(date('Y'));

$years = [];
$offer_year = 0;
for ($year = $this_year; $year <= $this_year + 2; $year++) {
    $days = usFederalHolidays($year);
    $in = count(array_filter($days, function ($day) use ($listed) {
        return isset($listed[$day['date']]);
    }));
    $years[$year] = [$in, count($days)];
    if (!$offer_year && $in < count($days)) {
        $offer_year = $year;
    }
}
$offer_year = $offer_year ?: $this_year + 3;

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-magic me-2"></i>Add US Holidays</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="text-secondary">Adds the eleven US federal holidays for a year as closure days, each on the day it is observed - a holiday that falls on a Saturday is taken the Friday before, and one on a Sunday the Monday after.</p>

        <div class="mb-3">
            <label class="form-label">Year <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar-alt"></i></span>
                <input type="number" class="form-control" name="holiday_year" min="2000" max="2100" step="1" value="<?= $offer_year ?>" required autofocus>
            </div>
            <small class="text-secondary">
                <?php foreach ($years as $year => [$in, $total]) { ?>
                    <span class="d-block"><?= $year ?> - <?= $in === $total ? "all $total are in" : ($in ? "$in of $total are in" : 'none are in yet') ?></span>
                <?php } ?>
            </small>
        </div>

        <div class="small text-secondary"><i class="fas fa-fw fa-stopwatch me-1"></i>A day already on the list is left as it is, name and all. Adding them works the targets out again on every ticket with an SLA that isn't closed yet.</div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="generate_holidays" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add holidays</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
