<?php

/*
 * Edit an SLA. Saving works its targets out again on the tickets that have it
 * and aren't closed yet (admin/post/sla.php), so the window says how many
 * that is. The fields are sla_form.php.
 */

require_once '../../includes/modal_header.php';

$sla_id = intval($_GET['id'] ?? 0);

$sla = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT sla_archived_at, sla_description, sla_id, sla_name, sla_resolution_minutes, sla_response_minutes FROM slas WHERE sla_id = $sla_id LIMIT 1"));

ob_start();

if (!$sla) { ?>
    <div class="modal-body text-center text-secondary py-5">That SLA is gone.</div>
<?php } else {
    // The tickets a save moves the targets on - the handler's own rule
    $sla_tickets = intval(mysqli_fetch_row(mysqli_query($mysqli, "SELECT COUNT(ticket_id) FROM tickets WHERE ticket_sla_id = $sla_id AND ticket_closed_at IS NULL AND ticket_archived_at IS NULL"))[0]); ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-stopwatch me-2"></i>SLA: <strong><?= escapeHtml($sla['sla_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="sla_id" value="<?= $sla_id ?>">

    <div class="modal-body">
        <?php if ($sla['sla_archived_at']) { ?>
            <div class="alert alert-secondary small py-2"><i class="fas fa-fw fa-archive me-1"></i>Archived - new tickets don't get it. Restore it from the list.</div>
        <?php } ?>
        <?php require __DIR__ . '/sla_form.php'; ?>
        <?php if ($sla_tickets) { ?>
            <div class="small text-secondary mt-2"><i class="fas fa-fw fa-life-ring me-1"></i>Saving works the targets out again on the <?= number_format($sla_tickets) ?> ticket<?= $sla_tickets === 1 ? '' : 's' ?> with this SLA that <?= $sla_tickets === 1 ? "isn't" : "aren't" ?> closed yet.</div>
        <?php } ?>
    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_sla" class="btn btn-primary text-bold"><i class="fas fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }
require_once '../../../includes/modal_footer.php';
