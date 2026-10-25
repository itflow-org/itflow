<?php

/*
 * Get started - Starter content: the packs Maintenance > Starter Content has,
 * each with how much it would add, ticked to add (getting_started_content).
 * Only what isn't here already is added, so ticking one that is changes
 * nothing.
 */

defined('FROM_GETTING_STARTED') || die("Direct file access is not allowed");

defined('FROM_STARTER_CONTENT') || define('FROM_STARTER_CONTENT', true);
require_once dirname(__DIR__) . '/post/starter_content_model.php';

$gs_packs = starterContentPacks();
$gs_pack_status = starterContentStatus($mysqli);

?>
<form action="post.php" method="post" data-gs-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="list-group mb-3 itflow-gs-packs" data-gs-packs>
        <?php foreach ($gs_packs as $gs_key => $gs_pack) {
            $gs_status = $gs_pack_status[$gs_key];
            $gs_all_here = $gs_status['missing'] === 0; ?>
            <label class="list-group-item d-flex gap-3 align-items-start<?= $gs_all_here ? ' is-here' : '' ?>" data-gs-pack="<?= $gs_key ?>">
                <input class="form-check-input flex-shrink-0 mt-1" type="checkbox" name="packs[]" value="<?= $gs_key ?>"<?= $gs_all_here ? ' disabled' : ' checked' ?>>
                <span class="itflow-gs-pack-icon" aria-hidden="true"><i class="fas fa-fw <?= escapeHtml($gs_pack['icon']) ?>"></i></span>
                <span class="min-w-0 flex-grow-1">
                    <span class="d-flex flex-wrap align-items-baseline gap-2">
                        <span class="fw-semibold"><?= escapeHtml($gs_pack['label']) ?></span>
                        <span class="small text-body-secondary"><?= $gs_all_here ? '<i class="fas fa-check text-success me-1"></i>All ' . $gs_status['total'] . ' here' : ($gs_status['present'] ? $gs_status['missing'] . ' more to add' : $gs_status['total'] . ' to add') ?></span>
                    </span>
                    <span class="small text-body-secondary itflow-gs-pack-about" title="<?= escapeHtml($gs_pack['description']) ?>"><?= escapeHtml($gs_pack['description']) ?></span>
                </span>
            </label>
        <?php } ?>
    </div>
    <p class="small text-body-secondary">Nothing you already have is changed. Each pack can be added later, or brought up to date, in Maintenance &gt; Starter Content.</p>

    <button type="submit" name="getting_started_content" value="1" class="btn btn-primary" data-gs-save>Add the ones ticked<i class="fas fa-arrow-right ms-2"></i></button>
</form>
