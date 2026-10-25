<?php

/*
 * Get started - Starter content: the packs Maintenance > Starter Content has,
 * each with how much it would add, ticked to add (getting_started_content).
 * Pick which, under each, lists what it would add - every item ticked, so the
 * whole pack goes in unless some are unticked (picked[pack] says the list was
 * there, items[pack][] what is ticked in it; js/getting_started.js keeps the
 * pack's tick and count in step). Only what isn't here already is listed or
 * added.
 */

defined('FROM_GETTING_STARTED') || die("Direct file access is not allowed");

defined('FROM_STARTER_CONTENT') || define('FROM_STARTER_CONTENT', true);
require_once dirname(__DIR__) . '/post/starter_content_model.php';

$gs_packs = starterContentPacks();

?>
<form action="post.php" method="post" data-gs-form>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="list-group mb-3 itflow-gs-packs" data-gs-packs>
        <?php foreach ($gs_packs as $gs_key => $gs_pack) {
            $gs_items = starterContentItems($mysqli, $gs_key);
            $gs_total = starterContentTotal($gs_key);
            $gs_missing = count($gs_items);
            $gs_all_here = $gs_missing === 0;
            $gs_count = $gs_all_here ? '' : ($gs_missing < $gs_total ? "$gs_missing more to add" : "$gs_missing to add"); ?>
            <div class="list-group-item<?= $gs_all_here ? ' is-here' : '' ?>" data-gs-pack="<?= $gs_key ?>">
                <div class="d-flex gap-3 align-items-start">
                    <input class="form-check-input flex-shrink-0 mt-1" type="checkbox" name="packs[]" value="<?= $gs_key ?>" id="gsPack_<?= $gs_key ?>"<?= $gs_all_here ? ' disabled' : ' checked' ?> data-gs-pack-check>
                    <span class="itflow-gs-pack-icon" aria-hidden="true"><i class="fas fa-fw <?= escapeHtml($gs_pack['icon']) ?>"></i></span>
                    <div class="min-w-0 flex-grow-1">
                        <label class="d-flex flex-wrap align-items-baseline gap-2 mb-0" for="gsPack_<?= $gs_key ?>">
                            <span class="fw-semibold"><?= escapeHtml($gs_pack['label']) ?></span>
                            <?php if ($gs_all_here) { ?>
                                <span class="small text-body-secondary"><i class="fas fa-check text-success me-1"></i>All <?= $gs_total ?> here</span>
                            <?php } else { ?>
                                <span class="small text-body-secondary" data-gs-pack-count data-gs-of="<?= $gs_missing ?>" data-gs-more="<?= $gs_missing < $gs_total ? 1 : 0 ?>"><?= $gs_count ?></span>
                            <?php } ?>
                        </label>
                        <div class="small text-body-secondary itflow-gs-pack-about" title="<?= escapeHtml($gs_pack['description']) ?>"><?= escapeHtml($gs_pack['description']) ?></div>
                        <?php if (!$gs_all_here) { ?>
                            <details class="itflow-gs-pick mt-1" data-gs-pick>
                                <summary class="small">Pick which</summary>
                                <input type="hidden" name="picked[<?= $gs_key ?>]" value="1">
                                <div class="itflow-gs-pick-tools small mt-2">
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-gs-pick-all>All</button>
                                    <span class="text-body-secondary" aria-hidden="true">·</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-gs-pick-none>None</button>
                                </div>
                                <div class="itflow-gs-pick-list mt-2">
                                    <div class="itflow-gs-pick-cols">
                                    <?php $gs_group = null;
                                    foreach ($gs_items as $gs_n => $gs_item) {
                                        if ($gs_item['group'] !== '' && $gs_item['group'] !== $gs_group) {
                                            $gs_group = $gs_item['group']; ?>
                                            <div class="itflow-gs-pick-group"><?= escapeHtml($gs_group) ?></div>
                                        <?php } ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="items[<?= $gs_key ?>][]" value="<?= escapeHtml($gs_item['key']) ?>" id="gsItem_<?= $gs_key ?>_<?= $gs_n ?>" checked data-gs-item>
                                            <label class="form-check-label" for="gsItem_<?= $gs_key ?>_<?= $gs_n ?>"><?= escapeHtml($gs_item['name']) ?></label>
                                        </div>
                                    <?php } ?>
                                    </div>
                                </div>
                            </details>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
    <p class="small text-body-secondary">Nothing you already have is changed. Each pack can be added later, or brought up to date, in Maintenance &gt; Starter Content.</p>

    <button type="submit" name="getting_started_content" value="1" class="btn btn-primary" data-gs-save>Add the ones ticked<i class="fas fa-arrow-right ms-2"></i></button>
</form>
