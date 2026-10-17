<?php

require_once '../../includes/modal_header.php';

$tag = tagRow(intval($_GET['id'] ?? 0));

ob_start();

if (!$tag) { ?>
    <div class="modal-body text-center text-secondary py-5">That tag is gone - it may have been merged into another, or deleted.</div>
<?php } else {
    $tag_id = intval($tag['tag_id']);
    $type = intval($tag['tag_type']);
    $tag_used = tagUsage($tag_id);
    $tag_lines = tagSeatLines($tag_id); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-tag me-2"></i>Tag: <strong><?= escapeHtml($tag['tag_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="tag_id" value="<?= $tag_id ?>">

    <div class="modal-body">
        <?php if ($tag['tag_archived_at']) { ?>
            <div class="alert alert-secondary small py-2"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($tag['tag_archived_at'])) ?> - it stays on its records, but isn't offered for tagging or in the filters.</div>
        <?php } ?>
        <?php require __DIR__ . '/tag_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_tag" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save changes</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
