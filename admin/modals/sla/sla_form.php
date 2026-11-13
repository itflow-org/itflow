<?php

/*
 * An SLA's fields - for sla_add.php and sla_edit.php, which set $sla (its row,
 * or null when new). Each target is typed as hours and minutes, and the
 * handler makes minutes of the two (admin/post/sla.php, slaTargetMinutes()).
 * A resolution target left empty is an SLA that only times the first reply.
 */

isset($mysqli) || die("Direct file access is not allowed");

$f_name = (string) ($sla['sla_name'] ?? '');
$f_description = (string) ($sla['sla_description'] ?? '');
$f_response = intval($sla['sla_response_minutes'] ?? 0);
$f_resolution = intval($sla['sla_resolution_minutes'] ?? 0);

// Minutes into the two boxes - a box that would hold a 0 is left empty
$f_hours = function ($minutes) {
    return $minutes >= 60 ? (string) intdiv($minutes, 60) : '';
};
$f_minutes = function ($minutes) {
    return $minutes % 60 > 0 ? (string) ($minutes % 60) : '';
};

// What a day's worth of hours comes to on the business calendar - the part people are caught out by
$f_day_minutes = slaBusinessDayMinutes();

?>

<div class="mb-3">
    <label class="form-label">Name <strong class="text-danger">*</strong></label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-stopwatch"></i></span>
        <input type="text" class="form-control" name="name" maxlength="200" placeholder="Standard Support, Priority Support, Critical Response" value="<?= escapeHtml($f_name) ?>" required<?= $sla ? '' : ' autofocus' ?>>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Description</label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
        <input type="text" class="form-control" name="description" maxlength="500" placeholder="What it covers, and who gets it" value="<?= escapeHtml($f_description) ?>">
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Response target <strong class="text-danger">*</strong></label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-reply"></i></span>
        <input type="number" class="form-control" name="response_hours" min="0" max="8760" step="1" placeholder="0" value="<?= $f_hours($f_response) ?>" aria-label="Response target, hours">
        <span class="input-group-text">hours</span>
        <input type="number" class="form-control" name="response_minutes" min="0" max="59" step="1" placeholder="0" value="<?= $f_minutes($f_response) ?>" aria-label="Response target, minutes">
        <span class="input-group-text">minutes</span>
    </div>
    <small class="text-secondary">How long there is to first reply to a new ticket.</small>
</div>

<div class="mb-3">
    <label class="form-label">Resolution target</label>
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-flag-checkered"></i></span>
        <input type="number" class="form-control" name="resolution_hours" min="0" max="8760" step="1" placeholder="0" value="<?= $f_hours($f_resolution) ?>" aria-label="Resolution target, hours">
        <span class="input-group-text">hours</span>
        <input type="number" class="form-control" name="resolution_minutes" min="0" max="59" step="1" placeholder="0" value="<?= $f_minutes($f_resolution) ?>" aria-label="Resolution target, minutes">
        <span class="input-group-text">minutes</span>
    </div>
    <small class="text-secondary">How long there is to resolve it. Leave both empty for an SLA that only times the first reply.</small>
</div>

<div class="small text-secondary mb-1">
    <i class="fas fa-fw fa-business-time me-1"></i>
    <?php if ($f_day_minutes > 0) { ?>
        Targets count business hours only. Your business day is <?= escapeHtml(formatSlaClockMinutes($f_day_minutes)) ?>, so <strong>24 hours here is <?= escapeHtml(formatSlaMinutes(1440)) ?></strong>, not one - for a target of two business days, give <?= escapeHtml(formatSlaClockMinutes(2 * $f_day_minutes)) ?>.
    <?php } else { ?>
        No business hours are set, so the clocks run around the clock and a target is plain time - 24 hours is one day.
    <?php } ?>
</div>
