<?php

/*
 * Clear out unused - from a pack's row on Maintenance > Starter Content (?pack=).
 * Its items that are here and nothing uses (starterContentUnused()), every one
 * ticked; Archive them (clear_starter_unused, admin/post/starter_content.php)
 * archives the ones ticked. js/starter_content.js keeps the count in step.
 */

require_once '../../includes/modal_header.php';

defined('FROM_STARTER_CONTENT') || define('FROM_STARTER_CONTENT', true);
require_once __DIR__ . '/../../post/starter_content_model.php';
require_once __DIR__ . '/../../includes/starter_content_pick.php';

$pack = (string) ($_GET['pack'] ?? '');
$packs = starterContentPacks();
if (!isset($packs[$pack]) || !starterPackRecords($pack)) {
    exit('<div class="modal-body">There is no pack by that name.</div>');
}
$pack_details = $packs[$pack];
$unused = starterContentUnused($mysqli, $pack);
$items = $unused['items'];

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-broom me-2"></i>Clear Out Unused <?= escapeHtml($pack_details['label']) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-starter-pick>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php if ($items) { ?>
            <p class="mb-2">
                <span class="fw-semibold">These came from the pack, and nothing uses them yet.</span>
                <span class="text-body-secondary">They're archived, not deleted - Undo puts them back straight after, and any can be restored later from its own list's Archived view.</span>
            </p>
            <?php if ($unused['in_use']) { ?>
                <p class="small text-body-secondary mb-2"><i class="fas fa-fw fa-check text-success me-1"></i><?= intval($unused['in_use']) ?> more from the pack <?= $unused['in_use'] === 1 ? 'is' : 'are' ?> in use, so <?= $unused['in_use'] === 1 ? 'it stays' : 'they stay' ?>.</p>
            <?php } ?>
            <p class="small fw-semibold mb-2" data-starter-count data-starter-format="clear"><?= count($items) ?> to archive</p>
            <?= starterPickHtml($pack, $items, '', true) ?>
        <?php } else { ?>
            <p class="mb-0 text-body-secondary">Everything from the pack that's here is in use - there's nothing to clear out.</p>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <?php if ($items) { ?>
            <button type="submit" name="clear_starter_unused" value="<?= escapeHtml($pack) ?>" class="btn btn-danger fw-bold" data-starter-add>
                <i class="fas fa-fw fa-archive me-2"></i>Archive Them
            </button>
        <?php } ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
