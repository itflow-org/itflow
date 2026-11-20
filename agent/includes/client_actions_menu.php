<?php
/*
 * The client actions menu - New Ticket, New Project, Edit, Onboarding, Banners, Export, Client Review, Archive/Restore, Delete.
 * Shared by the client header (inc_client_top_head.php) and the client
 * overview, so both offer exactly the same actions behind the same
 * permissions. Include it inside a .dropdown, after its toggle button.
 */
?>
<div class="dropdown-menu">
    <?php if (lookupUserPermission("module_support") >= 2) { ?>
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket/ticket_add.php?client_id=<?= $client_id ?>" data-modal-size="lg">
            <i class="fas fa-fw fa-life-ring me-2"></i>New Ticket
        </a>
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/project/project_add.php?client_id=<?= $client_id ?>" data-modal-size="lg">
            <i class="fas fa-fw fa-project-diagram me-2"></i>New Project
        </a>
    <?php } ?>
    <div class="dropdown-divider"></div>
    <a class="dropdown-item ajax-modal" href="#"
        data-modal-url="modals/client/client_edit.php?id=<?= $client_id ?>">
        <i class="fas fa-fw fa-edit me-2"></i>Edit Client
    </a>
    <?php if (empty($client_archived_at) && clientOnboardingCan()) {
        // Onboard it a step at a time (functions/client_onboarding.php) - or carry on, or see where it got to
        $client_onboarding_state = clientOnboardingState($client_id); ?>
        <a class="dropdown-item" href="<?= clientOnboardingUrl($client_id, $client_onboarding_state === 'finished' ? 'done' : '') ?>" data-client-onboarding="<?= $client_onboarding_state ?>">
            <i class="fas fa-fw fa-route me-2"></i><?= ['none' => 'Onboard', 'open' => 'Carry on onboarding', 'finished' => 'Onboarding'][$client_onboarding_state] ?>
        </a>
    <?php } ?>
    <?php if (agentBannersReady() && agentBannerCanEdit($client_id)) {
        // A banner along the top of this client's pages for agents, and the ones it has (functions/agent_banner.php) ?>
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/agent_banner/agent_banner_add.php?client_id=<?= $client_id ?>" data-modal-size="lg">
            <i class="fas fa-fw fa-bullhorn me-2"></i>New Banner
        </a>
        <?php foreach (agentBannersForClient($client_id) as $client_banner) {
            $client_banner_state = portalAnnouncementState($client_banner); ?>
            <a class="dropdown-item ajax-modal small" href="#" data-modal-url="modals/agent_banner/agent_banner_edit.php?id=<?= intval($client_banner['announcement_id']) ?>&client_id=<?= $client_id ?>" data-modal-size="lg" title="Change or archive this banner">
                <span class="itflow-announcement-dot is-<?= escapeHtml($client_banner['announcement_tone']) ?> me-2 ms-1"></span><?= escapeHtml(mb_strimwidth($client_banner['announcement_title'], 0, 40, '...')) ?><?= $client_banner_state['key'] === 'scheduled' ? ' <span class="text-body-secondary">(scheduled)</span>' : '' ?>
            </a>
        <?php } ?>
    <?php } ?>
    <?php if (lookupUserPermission("module_sales") >= 2) { ?>
        <?php if ($show_add_credit) { ?>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addCreditModal">
            <i class="fas fa-fw fa-wallet me-2"></i>Add Credit
        </a>
        <?php } ?>
    <?php } ?>
    <?php if (lookupUserPermission("module_client") >= 1) { ?>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/client/client_export_data.php?client_id=<?= $client_id ?>" data-modal-size="lg">
            <i class="fas fa-fw fa-file-export me-2"></i>Export Data
        </a>
    <?php } ?>
    <?php if (reportCan(reportFind('client_review'))) { ?>
        <a class="dropdown-item" href="/agent/reports/client_review.php?client_id=<?= $client_id ?>">
            <i class="fas fa-fw fa-clipboard-list me-2"></i>Client Review
        </a>
    <?php } ?>

    <?php if (empty($client_archived_at)) { ?>
        <div class="dropdown-divider"></div>
        <?php if (lookupUserPermission('module_client') >= 2) {
            // Portal sign-in, shared links, recurring invoices and tickets, and the archive, in one go (modals/client/client_offboard.php) ?>
            <a class="dropdown-item text-danger ajax-modal" href="#" data-modal-url="modals/client/client_offboard.php?client_id=<?= $client_id ?>">
                <i class="fas fa-fw fa-sign-out-alt me-2"></i>Offboard
            </a>
        <?php } ?>
        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_client=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
            <i class="fas fa-fw fa-archive me-2"></i>Archive Client
        </a>
    <?php } else { ?>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item text-primary confirm-link" href="post.php?restore_client=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
            <i class="fas fa-fw fa-archive me-2"></i>Restore Client
        </a>
    <?php } ?>

    <?php if (lookupUserPermission("module_client") >= 3) {
        // The same company added twice - everything moves to the one kept (modals/client/client_merge.php) ?>
        <a class="dropdown-item text-danger ajax-modal" href="#" data-modal-url="modals/client/client_merge.php?client_id=<?= $client_id ?>">
            <i class="fas fa-fw fa-compress-alt me-2"></i>Merge into another client
        </a>
    <?php } ?>

    <?php if (lookupUserPermission("module_client") >= 3 && $client_archived_at) { ?>
    <div class="dropdown-divider"></div>
    <a class="dropdown-item text-danger text-bold" href="#" data-bs-toggle="modal" data-bs-target="#deleteClientModal<?= $client_id ?>">
        <i class="fas fa-fw fa-trash me-2"></i>Delete Client
    </a>
    <?php } ?>
</div>
