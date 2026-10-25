<?php

/*
 * Get started - the summary: every step, where it stands and a way back to
 * it. Once each is done or skipped, the dashboard stops showing it.
 */

defined('FROM_GETTING_STARTED') || die("Direct file access is not allowed");

$gs_left = array_filter($gs_steps, function ($step) {
    return !$step['done'];
});
// Neither done nor skipped
$gs_todo = $gs_progress['total'] - $gs_progress['done'];

?>
<div class="text-center mb-4" data-gs-summary="<?= $gs_progress['finished'] ? 'finished' : 'unfinished' ?>">
    <span class="itflow-gs-done-mark mb-3<?= $gs_left ? ' is-partial' : '' ?>" aria-hidden="true"><i class="fas <?= $gs_left ? 'fa-flag-checkered' : 'fa-check' ?>"></i></span>
    <h1 class="h4 fw-semibold mb-1"><?= $gs_left ? ($gs_progress['finished'] ? "That's the lot for now" : ($gs_todo <= 2 ? 'Nearly there' : 'Still to set up')) : 'All set' ?></h1>
    <p class="text-body-secondary mb-0">
        <?php if (!$gs_left) { ?>
            Everything is set up. Each of these is in Admin whenever it needs changing.
        <?php } elseif ($gs_progress['finished']) { ?>
            What you skipped is here when you want it - in Admin &gt; Get started.
        <?php } else { ?>
            <?= $gs_todo === 1 ? 'One step is' : $gs_todo . ' steps are' ?> still to do - each ticks itself off however it's set up.
        <?php } ?>
    </p>
</div>

<ul class="list-group mb-4 itflow-gs-summary">
    <?php foreach ($gs_steps as $gs_key => $gs_step_row) { ?>
        <li class="list-group-item d-flex align-items-center gap-3" data-gs-summary-step="<?= $gs_key ?>" data-gs-state="<?= $gs_step_row['done'] ? 'done' : ($gs_step_row['skipped'] ? 'skipped' : 'todo') ?>">
            <span class="itflow-gs-summary-mark is-<?= $gs_step_row['done'] ? 'done' : ($gs_step_row['skipped'] ? 'skipped' : 'todo') ?>" aria-hidden="true">
                <i class="fas fa-fw <?= $gs_step_row['done'] ? 'fa-check' : ($gs_step_row['skipped'] ? 'fa-minus' : $gs_step_row['icon']) ?>"></i>
            </span>
            <div class="min-w-0 flex-grow-1">
                <div class="fw-semibold"><?= escapeHtml($gs_step_row['title']) ?></div>
                <div class="small text-body-secondary"><?= escapeHtml($gs_step_row['status']) ?><?= !$gs_step_row['done'] && $gs_step_row['skipped'] ? ' - skipped for now' : '' ?></div>
            </div>
            <a href="<?= gettingStartedUrl($gs_key) ?>" class="btn btn-sm <?= $gs_step_row['done'] ? 'btn-link text-decoration-none' : 'btn-outline-primary' ?> flex-shrink-0"><?= $gs_step_row['done'] ? 'Change' : 'Set up' ?></a>
        </li>
    <?php } ?>
</ul>

<div class="d-flex flex-wrap justify-content-center gap-2">
    <a href="/agent/dashboard.php" class="btn btn-primary" data-gs-finish>Go to the dashboard<i class="fas fa-arrow-right ms-2"></i></a>
</div>
