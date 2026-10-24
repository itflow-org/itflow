<?php

/*
 * Edit a custom link - its name, where it goes, the menu it shows in, its
 * description and icon. The fields are custom_link_form.php.
 */

require_once '../../includes/modal_header.php';

$custom_link = customLinkRow(intval($_GET['id'] ?? 0));
$default_location = 1;

ob_start();

if (!$custom_link) { ?>
    <div class="modal-body text-center text-secondary py-5">That link is gone - it may have been deleted.</div>
<?php } else {
    $custom_link_id = intval($custom_link['custom_link_id']); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-<?= escapeHtml(customLinkIcon($custom_link['custom_link_icon'])) ?> me-2"></i>Link: <strong><?= escapeHtml($custom_link['custom_link_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="custom_link_id" value="<?= $custom_link_id ?>">

    <div class="modal-body">
        <?php if ($custom_link['custom_link_archived_at']) { ?>
            <div class="alert alert-secondary small py-2"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($custom_link['custom_link_archived_at'])) ?> - it isn't shown in its menu. Restore it from the list.</div>
        <?php } ?>
        <?php require __DIR__ . '/custom_link_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_custom_link" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save changes</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
