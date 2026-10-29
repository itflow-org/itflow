<?php

/*
 * Onboarding - the summary: every step, where it stands and a way back to
 * it, and Finish (client_onboarding_finish) - after which the client's
 * overview stops showing it. It is still in the client's menu.
 */

defined('FROM_CLIENT_ONBOARDING') || die("Direct file access is not allowed");

$co_left = array_filter($co_steps, function ($step) {
    return !$step['done'];
});
$co_todo = $co_progress['total'] - $co_progress['done'];
$co_finished = !empty($co_row['onboarding_finished_at']);

?>
<div class="text-center mb-4" data-co-summary="<?= $co_progress['finished'] ? 'finished' : 'unfinished' ?>">
    <span class="itflow-gs-done-mark mb-3<?= $co_left ? ' is-partial' : '' ?>" aria-hidden="true"><i class="fas <?= $co_left ? 'fa-flag-checkered' : 'fa-check' ?>"></i></span>
    <h1 class="h4 fw-semibold mb-1"><?= $co_left ? ($co_progress['finished'] ? "That's the lot for now" : ($co_todo <= 2 ? 'Nearly there' : 'Still to do')) : escapeHtml($co_name) . ' is on board' ?></h1>
    <p class="text-body-secondary mb-0">
        <?php if (!$co_left) { ?>
            Everything is set up. Each part is on the client whenever it needs changing.
        <?php } elseif ($co_progress['finished']) { ?>
            What you skipped is here when you want it - Onboarding, in the client's menu.
        <?php } else { ?>
            <?= $co_todo === 1 ? 'One step is' : $co_todo . ' steps are' ?> still to do - each ticks itself off however it's done.
        <?php } ?>
    </p>
</div>

<ul class="list-group mb-4 itflow-gs-summary">
    <?php foreach ($co_steps as $co_key => $co_step_row) {
        $co_state = $co_step_row['done'] ? 'done' : ($co_step_row['skipped'] ? 'skipped' : 'todo'); ?>
        <li class="list-group-item d-flex align-items-center gap-3" data-co-summary-step="<?= $co_key ?>" data-co-state="<?= $co_state ?>">
            <span class="itflow-gs-summary-mark is-<?= $co_state ?>" aria-hidden="true">
                <i class="fas fa-fw <?= $co_state === 'done' ? 'fa-check' : ($co_state === 'skipped' ? 'fa-minus' : $co_step_row['icon']) ?>"></i>
            </span>
            <div class="min-w-0 flex-grow-1">
                <div class="fw-semibold"><?= escapeHtml($co_step_row['title']) ?></div>
                <div class="small text-body-secondary"><?= escapeHtml($co_step_row['status']) ?><?= $co_state === 'skipped' ? ' - skipped for now' : '' ?></div>
            </div>
            <a href="<?= clientOnboardingUrl($client_id, $co_key) ?>" class="btn btn-sm <?= $co_state === 'done' ? 'btn-link text-decoration-none' : 'btn-outline-primary' ?> flex-shrink-0"><?= $co_state === 'done' ? 'Change' : 'Do it now' ?></a>
        </li>
    <?php } ?>
</ul>

<div class="d-flex flex-wrap justify-content-center gap-2">
    <?php if ($co_finished) { ?>
        <a href="/agent/client_overview.php?client_id=<?= $client_id ?>" class="btn btn-primary" data-co-finish>Go to <?= escapeHtml($co_name) ?><i class="fas fa-arrow-right ms-2"></i></a>
    <?php } else { ?>
        <form action="post.php" method="post" class="m-0">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <button type="submit" name="client_onboarding_finish" value="1" class="btn btn-primary" data-co-finish>
                <i class="fas fa-flag-checkered me-2"></i><?= $co_progress['finished'] ? 'Finish' : 'Finish for now' ?>
            </button>
        </form>
    <?php } ?>
</div>
<?php if (!$co_finished && !$co_progress['finished']) { ?>
    <p class="text-center small text-body-secondary mt-3 mb-0">Finishing stops the client's overview showing what is left. Onboarding is still in the client's menu.</p>
<?php } ?>
