<?php

/*
 * Changing a banner for agents (functions/agent_banner.php) - a client's from its ⋮ menu
 * (?client_id=, which keeps it on that client), or any from Admin > Agent Banners.
 */

require_once '../../../includes/modal_header.php';

$announcement = agentBannerRow($_GET['id'] ?? 0);
if ($announcement) {
    $client_id = intval($announcement['announcement_client_id']);
    if ($client_id) {
        enforceUserPermission('module_client', 2);
        enforceClientAccess();
    } elseif (empty($session_is_admin)) {
        permissionRefuse('Only an admin can change a banner for every client.');
    }
}
$announcement_id = intval($announcement['announcement_id'] ?? 0);
$announcement_for_agents = true;
if (empty($session_is_admin) || isset($_GET['client_id'])) {
    $announcement_client_fixed = intval($announcement['announcement_client_id'] ?? 0);
}

ob_start();

if (!$announcement) { ?>
    <div class="modal-body text-center text-secondary py-5">That banner is gone.</div>
<?php } else { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-bullhorn me-2"></i>Editing banner: <strong><?= escapeHtml($announcement['announcement_title']) ?></strong></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <form action="/agent/post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="announcement_id" value="<?= $announcement_id ?>">

        <div class="modal-body">
            <?php require __DIR__ . '/../../../includes/announcement_form.php'; ?>
        </div>
        <div class="modal-footer">
            <div class="me-auto">
                <?php if ($announcement['announcement_archived_at']) { ?>
                    <a class="btn btn-light" href="/agent/post.php?restore_agent_banner=<?= $announcement_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-redo me-2"></i>Restore</a>
                <?php } else { ?>
                    <a class="btn btn-light text-danger" href="/agent/post.php?archive_agent_banner=<?= $announcement_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Nobody sees it, and it moves to Archived"><i class="fas fa-archive me-2"></i>Archive</a>
                <?php } ?>
                <a class="btn btn-light text-danger confirm-link" href="/agent/post.php?delete_agent_banner=<?= $announcement_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"
                    data-confirm-title="Delete this banner?" data-confirm-text="It goes for good." data-confirm-button="Delete"><i class="fas fa-trash me-2"></i>Delete</a>
            </div>
            <button type="submit" name="edit_agent_banner" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
        </div>
    </form>
<?php }

require_once '../../../includes/modal_footer.php';
