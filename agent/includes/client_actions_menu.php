<?php
/*
 * The client actions menu - New Ticket, New Project, Edit, Export, Client Review, Archive/Restore, Delete.
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
    <?php if (lookupUserPermission("module_billing") >= 2) { ?>
        <?php if ($show_add_credit) { ?>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addCreditModal">
            <i class="fas fa-fw fa-wallet me-2"></i>Add Credit
        </a>
        <?php } ?>
    <?php } ?>
    <?php if (lookupUserPermission("module_client") >= 1) { ?>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exportClientPDFModal">
            <i class="fas fa-fw fa-file-pdf me-2"></i>Export Data
        </a>
    <?php } ?>
    <?php if (reportCan(reportFind('client_review'))) { ?>
        <a class="dropdown-item" href="/agent/reports/client_review.php?client_id=<?= $client_id ?>">
            <i class="fas fa-fw fa-clipboard-list me-2"></i>Client Review
        </a>
    <?php } ?>

    <?php if (empty($client_archived_at)) { ?>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_client=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
            <i class="fas fa-fw fa-archive me-2"></i>Archive Client
        </a>
    <?php } else { ?>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item text-primary confirm-link" href="post.php?restore_client=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
            <i class="fas fa-fw fa-archive me-2"></i>Restore Client
        </a>
    <?php } ?>

    <?php if (lookupUserPermission("module_client") >= 3 && $client_archived_at) { ?>
    <div class="dropdown-divider"></div>
    <a class="dropdown-item text-danger text-bold" href="#" data-bs-toggle="modal" data-bs-target="#deleteClientModal<?= $client_id ?>">
        <i class="fas fa-fw fa-trash me-2"></i>Delete Client
    </a>
    <?php } ?>
</div>
