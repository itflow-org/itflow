<?php

/*
 * Project quick look - from the projects list, a ticket that is on a
 * project, the client overview: how far along it is, its time against the
 * budget, when it is due and who runs it, and its open tickets. The project
 * page is one click away for the rest.
 */

require_once '../../../includes/modal_header.php';

$project = projectForModal($_GET['id'] ?? 0, 1);
$project_id = intval($project['project_id']);
$project_client_id = intval($project['project_client_id']);
$is_open = projectIsOpen($project);
$stats = projectStats([$project_id])[$project_id];
$flags = projectFlags($project, $stats);
$reference = escapeHtml(projectReference($project));
$url = escapeHtml(projectUrl($project_id, $project_client_id));
$can_edit = projectCan(2);

$open_tickets = ticketRows("tickets.ticket_project_id = $project_id AND " . TICKET_OPEN_SQL . ' ' . clientScopeSql('tickets.ticket_client_id'), 8);
$description = trim((string) $project['project_description']);

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="min-w-0">
        <div class="small d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="font-monospace text-body-secondary"><?= $reference ?></span>
            <?= projectStatePill($project) ?>
            <?= projectDuePill($project) ?>
        </div>
        <h5 class="modal-title fw-semibold text-break"><?= escapeHtml($project['project_name']) ?></h5>
        <div class="small text-body-secondary mt-1">
            <?= $project_client_id ? escapeHtml($project['client_name']) : 'Internal - no client' ?>
            <span class="mx-1">&middot;</span>started <?= timeAgo($project['project_start'] ?: $project['project_created_at']) ?>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">

    <?php foreach ($flags as $flag) { ?>
        <div class="alert alert-<?= $flag['tone'] ?> py-1 px-2 small mb-2"><?= escapeHtml($flag['text']) ?></div>
    <?php } ?>

    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <div class="small text-body-secondary fw-semibold text-uppercase mb-1">Progress</div>
            <?= projectProgressHtml($stats) ?>
        </div>
        <div class="col-sm-6">
            <div class="small text-body-secondary fw-semibold text-uppercase mb-1">Time<?= floatval($project['project_budget_hours']) > 0 ? ' / budget' : '' ?></div>
            <?= projectTimeHtml($project, $stats) ?>
            <?php if ($stats['unbilled_seconds']) { ?>
                <div class="small text-body-secondary mt-1"><?= projectHoursText($stats['unbilled_seconds']) ?> billable, not invoiced</div>
            <?php } ?>
        </div>
    </div>

    <dl class="itflow-asset-dl mb-3">
        <dt>Manager</dt>
        <dd><?= formatAssignee($project['project_manager'], $project['user_name'], $project['user_avatar'], false, 'No manager') ?></dd>
        <?php if (intval($project['project_contact_id']) && $project['contact_name'] !== null) { ?>
            <dt>Contact</dt>
            <dd>
                <?= contactLink($project_client_id, $project['project_contact_id'], $project['contact_name'], $project['contact_photo'] ?? '') ?>
                <?php if ($project['contact_email']) { ?>
                    <div class="small"><a href="mailto:<?= escapeHtml($project['contact_email']) ?>"><?= escapeHtml($project['contact_email']) ?></a></div>
                <?php } ?>
            </dd>
        <?php } ?>
        <?php if ($project['project_start']) { ?>
            <dt>Start</dt><dd><?= escapeHtml(date('D M j, Y', strtotime($project['project_start']))) ?></dd>
        <?php } ?>
        <dt>Due</dt><dd><?= $project['project_due'] ? escapeHtml(date('D M j, Y', strtotime($project['project_due']))) : '<span class="text-body-secondary">-</span>' ?></dd>
        <?php if ($project['project_completed_at']) { ?>
            <dt>Completed</dt><dd><?= escapeHtml(date('D M j, Y', strtotime($project['project_completed_at']))) ?></dd>
        <?php } ?>
    </dl>

    <?php if ($description !== '') { ?>
        <h6 class="small text-body-secondary fw-semibold text-uppercase">Scope</h6>
        <div class="border rounded p-2 mb-3 small itflow-project-scope"><?= nl2br(escapeHtml(mb_strlen($description) > 600 ? mb_substr($description, 0, 600) . '…' : $description)) ?></div>
    <?php } ?>

    <h6 class="small text-body-secondary fw-semibold text-uppercase">
        Open tickets<?php if ($stats['open'] > count($open_tickets)) { ?> <span class="fw-normal text-lowercase">- <?= count($open_tickets) ?> of <?= $stats['open'] ?></span><?php } ?>
    </h6>
    <?php if ($open_tickets) { ?>
        <?= ticketTableHtml($open_tickets, ['compact' => true]) ?>
    <?php } else { ?>
        <div class="small text-body-secondary"><?= $stats['tickets'] ? 'None - every ticket is resolved or closed.' : 'No tickets on it yet.' ?></div>
    <?php } ?>

</div>

<div class="modal-footer">
    <?php if ($can_edit && $is_open) { ?>
        <button type="button" class="btn btn-outline-secondary me-auto ajax-modal" data-modal-size="lg" data-modal-url="modals/ticket/ticket_add.php?<?= $project_client_id ? "client_id=$project_client_id&" : '' ?>project_id=<?= $project_id ?>"><i class="fas fa-plus me-2"></i>New ticket</button>
    <?php } ?>
    <?php if ($can_edit && !$project['project_archived_at']) { ?>
        <button type="button" class="btn btn-light ajax-modal" data-modal-size="lg" data-modal-url="modals/project/project_edit.php?id=<?= $project_id ?>"><i class="fas fa-edit me-2"></i>Edit</button>
    <?php } ?>
    <a class="btn btn-primary text-bold" href="<?= $url ?>"><i class="fas fa-project-diagram me-2"></i>Open project</a>
</div>

<?php
require_once '../../../includes/modal_footer.php';
