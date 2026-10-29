<?php

/*
 * Add tickets to a project - picked from its client's tickets (any client's
 * for an internal project), open ones first, then the ones closed in the
 * last 90 days; older ones by number. A ticket on another project moves.
 */

require_once '../../../includes/modal_header.php';

$project = projectForModal($_GET['project_id'] ?? 0, 2, true);
$project_id = intval($project['project_id']);
$project_client_id = intval($project['project_client_id']);

$client_sql = $project_client_id ? "AND ticket_client_id = $project_client_id" : clientScopeSql('ticket_client_id');
$sql = mysqli_query($mysqli, "SELECT ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_client_id, ticket_resolved_at, ticket_closed_at,
        ticket_status_name, client_name, project_id, project_prefix, project_number, project_name
    FROM tickets
    LEFT JOIN ticket_statuses ON ticket_status_id = ticket_status
    LEFT JOIN clients ON client_id = ticket_client_id
    LEFT JOIN projects ON project_id = ticket_project_id AND ticket_project_id > 0
    WHERE ticket_project_id <> $project_id AND ticket_archived_at IS NULL $client_sql
    AND (" . TICKET_OPEN_SQL . " OR COALESCE(ticket_closed_at, ticket_resolved_at) > NOW() - INTERVAL 90 DAY)
    ORDER BY " . TICKET_OPEN_SQL . " DESC, ticket_number DESC
    LIMIT 500");
$open = [];
$done = [];
while ($row = mysqli_fetch_assoc($sql)) {
    if (ticketIsOpen($row)) {
        $open[] = $row;
    } else {
        $done[] = $row;
    }
}

$option = function ($row) use ($project_client_id) {
    $description = array_filter([
        $row['ticket_status_name'],
        !$project_client_id ? ($row['client_name'] ?? 'Internal') : '',
        $row['project_id'] ? 'now on ' . $row['project_prefix'] . $row['project_number'] . ' - ' . $row['project_name'] : '',
    ]);
    return '<option value="' . intval($row['ticket_id']) . '" data-icon="fas fa-life-ring" data-description="' . escapeHtml(implode(' · ', $description)) . '">'
        . escapeHtml(ticketReference($row) . ' - ' . $row['ticket_subject']) . '</option>';
};

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-link me-2"></i>Add tickets <small class="ms-1 opacity-75"><?= escapeHtml(projectReference($project) . ' - ' . $project['project_name']) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="project_id" value="<?= $project_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Tickets</label>
            <select class="form-select select2" data-placeholder="Pick tickets..." multiple name="tickets[]">
                <?php if ($open) { ?>
                    <optgroup label="Open">
                        <?php foreach ($open as $row) { echo $option($row); } ?>
                    </optgroup>
                <?php } ?>
                <?php if ($done) { ?>
                    <optgroup label="Resolved or closed in the last 90 days">
                        <?php foreach ($done as $row) { echo $option($row); } ?>
                    </optgroup>
                <?php } ?>
            </select>
            <div class="form-text"><?= $project_client_id ? escapeHtml($project['client_name']) . "'s tickets" : "Any client's tickets - this is an internal project" ?>. One on another project moves to this one.</div>
        </div>

        <div class="mb-0">
            <label class="form-label">Or by number</label>
            <input type="text" class="form-control" name="ticket_numbers" placeholder="<?= escapeHtml($config_ticket_prefix) ?>1042, <?= escapeHtml($config_ticket_prefix) ?>1043">
            <div class="form-text">For older tickets - separate them with commas or spaces.</div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="link_ticket_to_project" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Add</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
