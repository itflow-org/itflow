<?php

require_once '../../includes/modal_header.php';

$announcement_id = intval($_GET['id'] ?? 0);
$announcement = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT announcement_id, announcement_title, announcement_body, announcement_tone, announcement_client_id,
    announcement_starts_at, announcement_ends_at FROM portal_announcements WHERE announcement_id = $announcement_id LIMIT 1"));

ob_start();

if (!$announcement) { ?>
    <div class="modal-body text-center text-secondary py-5">That announcement is gone.</div>
<?php } else { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-bullhorn me-2"></i>Editing announcement: <strong><?= escapeHtml($announcement['announcement_title']) ?></strong></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="announcement_id" value="<?= $announcement_id ?>">

        <div class="modal-body">
            <?php require __DIR__ . '/announcement_form.php'; ?>
        </div>
        <div class="modal-footer">
            <button type="submit" name="edit_portal_announcement" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
        </div>
    </form>
<?php }

require_once '../../../includes/modal_footer.php';
