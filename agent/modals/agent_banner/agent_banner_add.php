<?php

/*
 * A new banner for agents (functions/agent_banner.php) - for one client, from its ⋮ menu
 * (?client_id=), or, from Admin > Agent Banners, for every client or any one.
 */

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
if (!agentBannersReady()) {
    permissionRefuse('Banners come with the 3.7.2 database update - an admin can run it in Admin > Update.');
}
if ($client_id) {
    enforceUserPermission('module_client', 2);
    enforceClientAccess();
} elseif (empty($session_is_admin)) {
    permissionRefuse('Only an admin can add a banner for every client.');
}

$announcement = null;
$announcement_for_agents = true;
if ($client_id) {
    $announcement_client_fixed = $client_id;
}

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-bullhorn me-2"></i>New Banner</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/agent/post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <p class="text-secondary small">A note along the top of <?= $client_id ? "this client's pages, tickets, invoices, quotes and projects" : 'agents\' pages' ?>, for your team - clients don't see it.</p>
        <?php require __DIR__ . '/../../../includes/announcement_form.php'; ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_agent_banner" class="btn btn-primary fw-bold"><i class="fas fa-check me-2"></i>Post Banner</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
